<?php
require __DIR__ . '/../../src/helpers.php';
bootstrap_session();
require __DIR__ . '/../../src/auth.php';
require_role('customer');

$pdo = get_db();
$myUserId = (int) $_SESSION['user_id'];

$stmt = $pdo->prepare('SELECT lab_id FROM customers WHERE user_id = ?');
$stmt->execute([$myUserId]);
$labId = (int) $stmt->fetchColumn();

function describe_status_transition(?string $from, string $to): string
{
    if ($from === null && $to === 'pending') {
        return 'Placed';
    }
    if ($from === 'pending' && $to === 'accepted') {
        return 'Accepted by staff';
    }
    if ($from === 'accepted' && $to === 'pending') {
        return 'Returned to pending';
    }
    if ($from === 'accepted' && $to === 'completed') {
        return 'Completed';
    }
    if ($to === 'canceled') {
        return 'Canceled';
    }
    return 'Changed from ' . ($from ?? 'none') . ' to ' . $to;
}

function datetime_local_to_dt(string $value): ?DateTime
{
    $dt = DateTime::createFromFormat('Y-m-d\TH:i', $value);
    return $dt !== false ? $dt : null;
}

function format_lead_hours(float $hours): string
{
    $formatted = rtrim(rtrim(number_format($hours, 1), '0'), '.');
    return $formatted === '' ? '0' : $formatted;
}

function field_error(array $fieldErrors, string $key): string
{
    if (!isset($fieldErrors[$key])) {
        return '';
    }
    return '<span class="field-error">' . e($fieldErrors[$key]) . '</span>';
}

function fetch_order(PDO $pdo, int $orderId, int $labId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT
            o.order_id, o.customer_id, o.compound_id, o.delivery_option_id, o.status, o.created_at,
            cm.name AS compound_name, cm.order_type, cm.min_lead_time_hours,
            iso.isotope_name,
            del.name AS delivery_name,
            cust.first_name, cust.last_name, u.username,
            a.activity_mci, a.requested_datetime,
            b.mode, b.beam_current, b.bombardment_minutes, b.eob_activity_mci, b.eob_datetime
         FROM orders o
         JOIN compounds cm ON cm.compound_id = o.compound_id
         JOIN isotopes iso ON iso.isotope_id = o.isotope_id
         JOIN delivery_options del ON del.delivery_option_id = o.delivery_option_id
         JOIN customers cust ON cust.user_id = o.customer_id
         JOIN users u ON u.user_id = o.customer_id
         LEFT JOIN order_type_a_details a ON a.order_id = o.order_id
         LEFT JOIN order_type_b_details b ON b.order_id = o.order_id
         WHERE o.order_id = ? AND cust.lab_id = ?
         LIMIT 1'
    );
    $stmt->execute([$orderId, $labId]);
    $row = $stmt->fetch();
    return $row !== false ? $row : null;
}

$orderId = isset($_GET['id']) && ctype_digit((string) $_GET['id']) ? (int) $_GET['id'] : 0;
$order = $orderId > 0 ? fetch_order($pdo, $orderId, $labId) : null;

$commentError = '';
$editFieldErrors = [];
$editMode = false;

// Delivery options are always relative to THIS compound -- isotope and
// compound are locked once an order is placed (changing either is really
// "place a different order," not an edit), so only the order-type-specific
// fields and delivery are editable.
$deliveryOptionsForCompound = [];

// Edit form defaults: the order's current DB values, formatted for the
// inputs. Overwritten with the submitted values below if this request is
// a failed edit attempt, so the customer doesn't lose what they typed.
$editOld = [
    'activity_mci'        => '',
    'requested_datetime'  => '',
    'mode'                => '',
    'beam_current'        => '',
    'bombardment_minutes' => '',
    'eob_activity_mci'    => '',
    'eob_datetime'        => '',
    'delivery_option_id'  => '',
];

if ($order !== null) {
    $stmt = $pdo->prepare(
        'SELECT delivery_option_id, name FROM delivery_options
         WHERE delivery_option_id IN (SELECT delivery_option_id FROM compound_delivery_options WHERE compound_id = ?)
         ORDER BY name'
    );
    $stmt->execute([$order['compound_id']]);
    $deliveryOptionsForCompound = $stmt->fetchAll();

    if ($order['order_type'] === 'A') {
        $editOld['activity_mci'] = $order['activity_mci'] !== null ? rtrim(rtrim((string) $order['activity_mci'], '0'), '.') : '';
        $editOld['requested_datetime'] = $order['requested_datetime'] !== null
            ? date('Y-m-d\TH:i', strtotime($order['requested_datetime']))
            : '';
    } else {
        $editOld['mode'] = (string) $order['mode'];
        $editOld['beam_current'] = $order['beam_current'] !== null ? rtrim(rtrim((string) $order['beam_current'], '0'), '.') : '';
        $editOld['bombardment_minutes'] = $order['bombardment_minutes'] !== null ? (string) $order['bombardment_minutes'] : '';
        $editOld['eob_activity_mci'] = $order['eob_activity_mci'] !== null ? rtrim(rtrim((string) $order['eob_activity_mci'], '0'), '.') : '';
        $editOld['eob_datetime'] = $order['eob_datetime'] !== null
            ? date('Y-m-d\TH:i', strtotime($order['eob_datetime']))
            : '';
    }
    $editOld['delivery_option_id'] = (string) $order['delivery_option_id'];
}

if ($order !== null && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = $_POST['action'] ?? '';
    $isOwn = (int) $order['customer_id'] === $myUserId;

    if ($action === 'add_comment') {
        $body = trim($_POST['comment'] ?? '');
        if ($body === '') {
            $commentError = 'Comment cannot be empty.';
        } elseif (mb_strlen($body) > 1000) {
            $commentError = 'Comment must be 1000 characters or fewer.';
        } else {
            $pdo->prepare('INSERT INTO order_public_comments (order_id, author_id, body) VALUES (?, ?, ?)')
                ->execute([$orderId, $myUserId, $body]);
            redirect('/customer/order_detail.php?id=' . $orderId);
        }
    } elseif ($action === 'cancel' && $isOwn && $order['status'] === 'pending') {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                "UPDATE orders SET status = 'canceled'
                 WHERE order_id = ? AND customer_id = ? AND status = 'pending'"
            );
            $stmt->execute([$orderId, $myUserId]);

            if ($stmt->rowCount() === 1) {
                $pdo->prepare(
                    "INSERT INTO order_audit_log (order_id, changed_by, status_from, status_to)
                     VALUES (?, ?, 'pending', 'canceled')"
                )->execute([$orderId, $myUserId]);
            }

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        redirect('/customer/order_detail.php?id=' . $orderId);
    } elseif ($action === 'edit' && $isOwn && $order['status'] === 'pending') {
        $editMode = true;

        foreach ($editOld as $key => $_) {
            $editOld[$key] = isset($_POST[$key]) ? trim((string) $_POST[$key]) : '';
        }

        $orderType = $order['order_type'];
        $activityMci = null;
        $requestedDatetimeSql = null;
        $mode = null;
        $beamCurrent = null;
        $bombardmentMinutes = null;
        $eobActivityMci = null;
        $eobDatetimeSql = null;

        if ($orderType === 'A') {
            if ($editOld['activity_mci'] === '' || !is_numeric($editOld['activity_mci']) || (float) $editOld['activity_mci'] <= 0) {
                $editFieldErrors['activity_mci'] = 'Enter a valid activity (mCi).';
            } else {
                $activityMci = (float) $editOld['activity_mci'];
            }

            if ($editOld['requested_datetime'] === '') {
                $editFieldErrors['requested_datetime'] = 'Select a requested date and time.';
            } else {
                $requestedDt = datetime_local_to_dt($editOld['requested_datetime']);
                if ($requestedDt === null) {
                    $editFieldErrors['requested_datetime'] = 'Enter a valid date and time.';
                } else {
                    $minLeadHours = (float) $order['min_lead_time_hours'];
                    $dbNow = new DateTime((string) $pdo->query('SELECT NOW()')->fetchColumn());
                    $cutoff = clone $dbNow;
                    $cutoff->modify('+' . (int) round($minLeadHours * 3600) . ' seconds');
                    if ($requestedDt < $cutoff) {
                        $editFieldErrors['requested_datetime'] = 'Requires at least ' . format_lead_hours($minLeadHours) . ' hours notice.';
                    } else {
                        $requestedDatetimeSql = $requestedDt->format('Y-m-d H:i:00');
                    }
                }
            }
        } else { // Type B
            $mode = in_array($editOld['mode'], ['beam', 'eob'], true) ? $editOld['mode'] : null;
            if ($mode === null) {
                $editFieldErrors['mode'] = 'Select a run mode (beam or EOB).';
            } elseif ($mode === 'beam') {
                if ($editOld['beam_current'] === '' || !is_numeric($editOld['beam_current']) || (float) $editOld['beam_current'] <= 0) {
                    $editFieldErrors['beam_current'] = 'Enter a valid beam current.';
                } else {
                    $beamCurrent = (float) $editOld['beam_current'];
                }
                if ($editOld['bombardment_minutes'] === '' || !ctype_digit($editOld['bombardment_minutes']) || (int) $editOld['bombardment_minutes'] <= 0) {
                    $editFieldErrors['bombardment_minutes'] = 'Enter a valid bombardment time in minutes.';
                } else {
                    $bombardmentMinutes = (int) $editOld['bombardment_minutes'];
                }
            } else { // eob
                if ($editOld['eob_activity_mci'] === '' || !is_numeric($editOld['eob_activity_mci']) || (float) $editOld['eob_activity_mci'] <= 0) {
                    $editFieldErrors['eob_activity_mci'] = 'Enter a valid EOB activity.';
                } else {
                    $eobActivityMci = (float) $editOld['eob_activity_mci'];
                }
                if ($editOld['eob_datetime'] === '') {
                    $editFieldErrors['eob_datetime'] = 'Select an EOB date and time.';
                } else {
                    $eobDt = datetime_local_to_dt($editOld['eob_datetime']);
                    if ($eobDt === null) {
                        $editFieldErrors['eob_datetime'] = 'Enter a valid date and time.';
                    } else {
                        $eobDatetimeSql = $eobDt->format('Y-m-d H:i:00');
                    }
                }
            }
        }

        $deliveryOptionId = ctype_digit($editOld['delivery_option_id']) ? (int) $editOld['delivery_option_id'] : 0;
        if ($deliveryOptionId <= 0) {
            $editFieldErrors['delivery_option_id'] = 'Select a delivery method.';
        } else {
            $stmt = $pdo->prepare('SELECT 1 FROM compound_delivery_options WHERE compound_id = ? AND delivery_option_id = ?');
            $stmt->execute([$order['compound_id'], $deliveryOptionId]);
            if (!$stmt->fetchColumn()) {
                $editFieldErrors['delivery_option_id'] = 'Select a valid delivery method for this compound.';
            }
        }

        if (!$editFieldErrors) {
            $pdo->beginTransaction();
            try {
                // No order_audit_log row: only status changes are audited
                // (per CLAUDE.md), not field edits. last_modified_at is set
                // explicitly rather than relying on MySQL's ON UPDATE
                // CURRENT_TIMESTAMP, since that only fires when a column's
                // value actually changes -- a save that doesn't change
                // delivery_option_id should still bump it.
                $stmt = $pdo->prepare(
                    "UPDATE orders SET delivery_option_id = ?, last_modified_at = NOW()
                     WHERE order_id = ? AND customer_id = ? AND status = 'pending'"
                );
                $stmt->execute([$deliveryOptionId, $orderId, $myUserId]);

                if ($orderType === 'A') {
                    $pdo->prepare('UPDATE order_type_a_details SET activity_mci = ?, requested_datetime = ? WHERE order_id = ?')
                        ->execute([$activityMci, $requestedDatetimeSql, $orderId]);
                } else {
                    $pdo->prepare(
                        'UPDATE order_type_b_details SET mode = ?, beam_current = ?, bombardment_minutes = ?, eob_activity_mci = ?, eob_datetime = ? WHERE order_id = ?'
                    )->execute([$mode, $beamCurrent, $bombardmentMinutes, $eobActivityMci, $eobDatetimeSql, $orderId]);
                }

                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }

            redirect('/customer/order_detail.php?id=' . $orderId);
        }
    }
}

$auditLog = [];
$comments = [];

if ($order !== null) {
    // Same watermark dashboard.php writes for "updated since you looked" --
    // viewing an order's detail counts as having looked, same as viewing
    // the dashboard does. It's a single lab-wide timestamp, not a
    // per-order one, so this resets the whole lab's watermark, not just
    // this order's.
    $_SESSION['last_viewed_orders'] = $pdo->query('SELECT NOW()')->fetchColumn();

    $stmt = $pdo->prepare(
        'SELECT oal.status_from, oal.status_to, oal.changed_at, oal.changed_by,
                cust.first_name, cust.last_name, u.username
         FROM order_audit_log oal
         JOIN users u ON u.user_id = oal.changed_by
         LEFT JOIN customers cust ON cust.user_id = oal.changed_by
         WHERE oal.order_id = ?
         ORDER BY oal.changed_at ASC, oal.log_id ASC'
    );
    $stmt->execute([$orderId]);
    $auditLog = $stmt->fetchAll();
    foreach ($auditLog as &$auditEntry) {
        $auditEntry['changed_by_name'] = customer_display_name(
            $auditEntry['first_name'],
            $auditEntry['last_name'],
            $auditEntry['username']
        );
    }
    unset($auditEntry);

    // Role is read off which of customers/staff/admins the author_id
    // appears in (same convention as auth.php's determine_role()), shown
    // alongside the name since this is a single shared thread -- customer
    // and staff/admin posts are interleaved, not split into sections.
    $stmt = $pdo->prepare(
        "SELECT opc.comment_id, opc.author_id, opc.body, opc.created_at,
                cust.first_name, cust.last_name, u.username,
                CASE
                    WHEN cust.user_id IS NOT NULL THEN 'Customer'
                    WHEN st.user_id IS NOT NULL THEN 'Staff'
                    WHEN adm.user_id IS NOT NULL THEN 'Admin'
                    ELSE 'Unknown'
                END AS author_role
         FROM order_public_comments opc
         JOIN users u ON u.user_id = opc.author_id
         LEFT JOIN customers cust ON cust.user_id = opc.author_id
         LEFT JOIN staff st ON st.user_id = opc.author_id
         LEFT JOIN admins adm ON adm.user_id = opc.author_id
         WHERE opc.order_id = ?
         ORDER BY opc.created_at ASC, opc.comment_id ASC"
    );
    $stmt->execute([$orderId]);
    $comments = $stmt->fetchAll();
    foreach ($comments as &$comment) {
        $comment['author_name'] = customer_display_name(
            $comment['first_name'],
            $comment['last_name'],
            $comment['username']
        );
    }
    unset($comment);
}

$isOwn = $order !== null && (int) $order['customer_id'] === $myUserId;
$canEdit = $isOwn && $order !== null && $order['status'] === 'pending';
$placedBanner = isset($_GET['placed']) && $_GET['placed'] === '1';

$pageTitle = $order !== null ? ('Order #' . $orderId) : 'Order not found';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include __DIR__ . '/../../src/partials/head.php'; ?>
</head>
<body>
    <div class="app-shell">
        <?php include __DIR__ . '/../../src/partials/layout_customer.php'; ?>
        <main class="app-main">
            <?php if ($order === null): ?>
                <?php http_response_code(404); ?>
                <div class="page-header">
                    <div>
                        <h1>Order not found</h1>
                    </div>
                </div>
                <div class="card">
                    <p class="muted">This order doesn't exist or isn't visible to your lab.</p>
                    <a href="dashboard.php" class="btn btn--secondary">Back to dashboard</a>
                </div>
            <?php else: ?>
                <div class="page-header">
                    <div>
                        <a href="dashboard.php" class="page-header__back mb-4">&larr; Back to dashboard</a>
                        <span class="badge badge--<?= e($order['status']) ?> page-header__status"><?= e($order['status']) ?></span>
                        <h1>Order #<?= $orderId ?></h1>
                    </div>
                    <?php if ($canEdit): ?>
                        <div class="page-header__actions">
                            <form method="post" onsubmit="return confirm('Cancel this order?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="cancel">
                                <button type="submit" class="btn btn--danger">Cancel order</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($placedBanner): ?>
                    <div class="alert alert--success">Order placed &mdash; pending review by staff.</div>
                <?php endif; ?>

                <div class="card customer-order-form-card <?= $editMode ? 'is-editing' : '' ?>" id="order-details-card">
                    <div class="flex-between mb-4">
                        <span class="card__title mb-0">Order Details</span>
                        <?php if ($canEdit): ?>
                            <button type="button" id="edit-toggle" class="btn btn--secondary btn--sm" <?= $editMode ? 'hidden' : '' ?>>Edit</button>
                        <?php endif; ?>
                    </div>
                    <div class="detail-list">
                        <div class="detail-list__row">
                            <span class="detail-list__label">Compound</span>
                            <span class="detail-list__value"><?= e($order['compound_name']) ?></span>
                        </div>
                        <div class="detail-list__row">
                            <span class="detail-list__label">Isotope</span>
                            <span class="detail-list__value"><?= e($order['isotope_name']) ?></span>
                        </div>
                        <div class="detail-list__row">
                            <span class="detail-list__label">Order type</span>
                            <span class="detail-list__value"><?= $order['order_type'] === 'A' ? 'Dose order' : 'Cyclotron order' ?></span>
                        </div>

                        <div id="details-view" <?= $editMode ? 'hidden' : '' ?>>
                            <?php if ($order['order_type'] === 'A'): ?>
                                <div class="detail-list__row">
                                    <span class="detail-list__label">Activity</span>
                                    <span class="detail-list__value"><?= e(rtrim(rtrim((string) $order['activity_mci'], '0'), '.')) ?> mCi</span>
                                </div>
                                <div class="detail-list__row">
                                    <span class="detail-list__label">Requested</span>
                                    <span class="detail-list__value"><?= e(date('M j, Y g:ia', strtotime($order['requested_datetime']))) ?></span>
                                </div>
                            <?php elseif ($order['mode'] === 'beam'): ?>
                                <div class="detail-list__row">
                                    <span class="detail-list__label">Beam current</span>
                                    <span class="detail-list__value"><?= e(rtrim(rtrim((string) $order['beam_current'], '0'), '.')) ?></span>
                                </div>
                                <div class="detail-list__row">
                                    <span class="detail-list__label">Bombardment</span>
                                    <span class="detail-list__value"><?= (int) $order['bombardment_minutes'] ?> minutes</span>
                                </div>
                            <?php else: ?>
                                <div class="detail-list__row">
                                    <span class="detail-list__label">EOB activity</span>
                                    <span class="detail-list__value"><?= e(rtrim(rtrim((string) $order['eob_activity_mci'], '0'), '.')) ?> mCi</span>
                                </div>
                                <div class="detail-list__row">
                                    <span class="detail-list__label">EOB date &amp; time</span>
                                    <span class="detail-list__value"><?= e(date('M j, Y g:ia', strtotime($order['eob_datetime']))) ?></span>
                                </div>
                            <?php endif; ?>
                            <div class="detail-list__row">
                                <span class="detail-list__label">Delivery</span>
                                <span class="detail-list__value"><?= e($order['delivery_name']) ?></span>
                            </div>
                        </div>

                        <div class="detail-list__row">
                            <span class="detail-list__label">Placed by</span>
                            <span class="detail-list__value"><?= $isOwn ? '<strong>You</strong>' : e(customer_display_name($order['first_name'], $order['last_name'], $order['username'])) ?></span>
                        </div>
                        <div class="detail-list__row">
                            <span class="detail-list__label">Placed on</span>
                            <span class="detail-list__value"><?= e(date('M j, Y g:ia', strtotime($order['created_at']))) ?></span>
                        </div>
                    </div>

                    <?php if ($canEdit): ?>
                        <form method="post" id="edit-form" class="form-section" <?= $editMode ? '' : 'hidden' ?>>
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="edit">

                            <?php if ($order['order_type'] === 'A'): ?>
                                <div class="field-row">
                                    <div class="field">
                                        <label for="edit_activity_mci">Activity (mCi)</label>
                                        <input type="number" step="0.01" min="0" id="edit_activity_mci" name="activity_mci" value="<?= e($editOld['activity_mci']) ?>">
                                        <?= field_error($editFieldErrors, 'activity_mci') ?>
                                    </div>
                                    <div class="field">
                                        <label for="edit_requested_datetime">Requested date &amp; time</label>
                                        <input type="datetime-local" id="edit_requested_datetime" name="requested_datetime" value="<?= e($editOld['requested_datetime']) ?>">
                                        <span class="field-hint">Requires at least <?= e(format_lead_hours((float) $order['min_lead_time_hours'])) ?> hours notice.</span>
                                        <?= field_error($editFieldErrors, 'requested_datetime') ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="radio-card-group">
                                    <label class="radio-card" for="edit_mode_beam">
                                        <input type="radio" id="edit_mode_beam" name="mode" value="beam" <?= $editOld['mode'] === 'beam' ? 'checked' : '' ?>>
                                        <span class="radio-card__title">Beam</span>
                                        <span class="radio-card__desc">Specify beam current and bombardment time.</span>
                                    </label>
                                    <label class="radio-card" for="edit_mode_eob">
                                        <input type="radio" id="edit_mode_eob" name="mode" value="eob" <?= $editOld['mode'] === 'eob' ? 'checked' : '' ?>>
                                        <span class="radio-card__title">End of Bombardment (EOB)</span>
                                        <span class="radio-card__desc">Specify EOB activity and datetime.</span>
                                    </label>
                                </div>
                                <?= field_error($editFieldErrors, 'mode') ?>

                                <div class="field-row" id="edit-beam-fields" <?= $editOld['mode'] === 'beam' ? '' : 'hidden' ?>>
                                    <div class="field">
                                        <label for="edit_beam_current">Beam current</label>
                                        <input type="number" step="0.01" min="0" id="edit_beam_current" name="beam_current" value="<?= e($editOld['beam_current']) ?>">
                                        <?= field_error($editFieldErrors, 'beam_current') ?>
                                    </div>
                                    <div class="field">
                                        <label for="edit_bombardment_minutes">Bombardment (minutes)</label>
                                        <input type="number" step="1" min="0" id="edit_bombardment_minutes" name="bombardment_minutes" value="<?= e($editOld['bombardment_minutes']) ?>">
                                        <?= field_error($editFieldErrors, 'bombardment_minutes') ?>
                                    </div>
                                </div>

                                <div class="field-row" id="edit-eob-fields" <?= $editOld['mode'] === 'eob' ? '' : 'hidden' ?>>
                                    <div class="field">
                                        <label for="edit_eob_activity_mci">EOB activity (mCi)</label>
                                        <input type="number" step="0.01" min="0" id="edit_eob_activity_mci" name="eob_activity_mci" value="<?= e($editOld['eob_activity_mci']) ?>">
                                        <?= field_error($editFieldErrors, 'eob_activity_mci') ?>
                                    </div>
                                    <div class="field">
                                        <label for="edit_eob_datetime">EOB date &amp; time</label>
                                        <input type="datetime-local" id="edit_eob_datetime" name="eob_datetime" value="<?= e($editOld['eob_datetime']) ?>">
                                        <?= field_error($editFieldErrors, 'eob_datetime') ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="field">
                                <label for="edit_delivery_option_id">Delivery method</label>
                                <select id="edit_delivery_option_id" name="delivery_option_id">
                                    <?php foreach ($deliveryOptionsForCompound as $d): ?>
                                        <option value="<?= (int) $d['delivery_option_id'] ?>" <?= $editOld['delivery_option_id'] === (string) $d['delivery_option_id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?= field_error($editFieldErrors, 'delivery_option_id') ?>
                            </div>

                            <div class="flex gap-2 mb-0">
                                <button type="submit" class="btn btn--primary btn--sm">Save</button>
                                <button type="button" id="edit-cancel" class="btn btn--secondary btn--sm">Discard</button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>

                <div class="card customer-order-form-card">
                    <span class="card__title">Status History</span>
                    <?php if (!$auditLog): ?>
                        <p class="muted text-sm">No status history yet.</p>
                    <?php else: ?>
                        <ul class="status-timeline">
                            <?php foreach ($auditLog as $index => $entry): ?>
                                <?php
                                $entryIsOwn = (int) $entry['changed_by'] === $myUserId;
                                $isCurrent = $index === count($auditLog) - 1;
                                ?>
                                <li class="status-timeline__item <?= $isCurrent ? 'status-timeline__item--current' : '' ?>">
                                    <span class="status-timeline__label"><?= e(describe_status_transition($entry['status_from'], $entry['status_to'])) ?></span>
                                    <span class="status-timeline__meta"><?= $entryIsOwn ? 'You' : e($entry['changed_by_name']) ?> &middot; <?= e(date('M j, Y g:ia', strtotime($entry['changed_at']))) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>

                <div class="card customer-order-form-card">
                    <span class="card__title">Comments</span>
                    <div class="form-section">
                        <?php if (!$comments): ?>
                            <p class="muted text-sm">No comments yet.</p>
                        <?php else: ?>
                            <ul class="comment-list">
                                <?php foreach ($comments as $comment): ?>
                                    <?php $commentIsOwn = (int) $comment['author_id'] === $myUserId; ?>
                                    <li class="comment-item">
                                        <div class="comment-item__meta">
                                            <span class="comment-item__author"><?= $commentIsOwn ? 'You' : e($comment['author_name'] . ' (' . $comment['author_role'] . ')') ?></span>
                                            <span class="comment-item__timestamp"><?= e(date('M j, Y g:ia', strtotime($comment['created_at']))) ?></span>
                                        </div>
                                        <p class="comment-item__body"><?= e($comment['body']) ?></p>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>

                    <form method="post" class="form-section">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="add_comment">
                        <div class="field">
                            <label for="comment">Add a comment</label>
                            <textarea id="comment" name="comment" maxlength="1000"></textarea>
                            <?php if ($commentError !== ''): ?>
                                <span class="field-error"><?= e($commentError) ?></span>
                            <?php endif; ?>
                        </div>
                        <button type="submit" class="btn btn--secondary">Post comment</button>
                    </form>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
<script src="/assets/js/script.js" defer></script>
<?php if ($canEdit): ?>
<script>
(function () {
    var editToggle = document.getElementById('edit-toggle');
    var editCancel = document.getElementById('edit-cancel');
    var editForm = document.getElementById('edit-form');
    var detailsView = document.getElementById('details-view');
    var detailsCard = document.getElementById('order-details-card');
    if (!editToggle || !editForm || !detailsView) return;

    function enterEditMode() {
        detailsView.hidden = true;
        editForm.hidden = false;
        editToggle.hidden = true;
        if (detailsCard) detailsCard.classList.add('is-editing');

        // Move focus into the form so keyboard/screen-reader users land
        // right where they need to type, instead of on a button that's
        // just vanished.
        var firstField = editForm.querySelector('input:not([type="hidden"]), select, textarea');
        if (firstField) firstField.focus();
    }

    function exitEditMode() {
        editForm.reset();
        detailsView.hidden = false;
        editForm.hidden = true;
        editToggle.hidden = false;
        if (detailsCard) detailsCard.classList.remove('is-editing');
        editToggle.focus();
        updateModeFields();
    }

    editToggle.addEventListener('click', enterEditMode);
    if (editCancel) {
        editCancel.addEventListener('click', exitEditMode);
    }

    var beamFields = document.getElementById('edit-beam-fields');
    var eobFields = document.getElementById('edit-eob-fields');
    var radioCards = Array.prototype.slice.call(document.querySelectorAll('#edit-form .radio-card'));

    function updateModeFields() {
        if (!beamFields || !eobFields) return;
        var checked = document.querySelector('#edit-form input[name="mode"]:checked');
        var mode = checked ? checked.value : '';
        beamFields.hidden = mode !== 'beam';
        eobFields.hidden = mode !== 'eob';

        radioCards.forEach(function (card) {
            var input = card.querySelector('input[name="mode"]');
            card.classList.toggle('radio-card--selected', !!(input && input.checked));
        });
    }

    document.querySelectorAll('#edit-form input[name="mode"]').forEach(function (radio) {
        radio.addEventListener('change', updateModeFields);
    });

    updateModeFields();
})();
</script>
<?php endif; ?>
</html>
