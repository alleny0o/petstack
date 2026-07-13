<?php
require __DIR__ . '/../../src/helpers.php';
bootstrap_session();
require __DIR__ . '/../../src/auth.php';
require_role('customer');

$pdo = get_db();
$myUserId = (int) $_SESSION['user_id'];

$stmt = $pdo->prepare(
    'SELECT c.lab_id, l.institute_id
     FROM customers c
     LEFT JOIN labs l ON l.lab_id = c.lab_id
     WHERE c.user_id = ?'
);
$stmt->execute([$myUserId]);
$myScope = $stmt->fetch();
$labId = $myScope['lab_id'] !== null ? (int) $myScope['lab_id'] : null;
$instituteId = $myScope['institute_id'] !== null ? (int) $myScope['institute_id'] : null;

// Main order-form field state. Also doubles as the set of fields mirrored
// into the two "+ Add new ..." modals below, so a customer's in-progress
// order survives a same-page round trip to create a location/product user.
$old = [
    'institute_compound_id' => '',
    'delivery_option_id'    => '',
    'activity_mci'          => '',
    'requested_date'        => '',
    'requested_time'        => '',
    'delivery_location_id'  => '',
    'product_user_id'       => '',
    'special_instructions'  => '',
];
$fieldErrors = [];

$modalOld = ['location_name' => '', 'building' => '', 'room' => '', 'first_name' => '', 'last_name' => ''];
$modalErrors = [];
$modalRetry = ''; // 'add_location' | 'add_product_user' | '' -- which modal to reopen on redisplay
$successToast = null; // [type, message]

if ($labId !== null && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = $_POST['action'] ?? 'place_order';

    if ($action === 'add_location' || $action === 'add_product_user') {
        // Pull the mirrored main-form values (written by JS on modal open)
        // so the order-in-progress survives this round trip untouched.
        foreach ($old as $key => $_) {
            $old[$key] = isset($_POST['mirror_' . $key]) ? trim((string) $_POST['mirror_' . $key]) : '';
        }

        if ($action === 'add_location') {
            $modalOld['location_name'] = trim($_POST['location_name'] ?? '');
            $modalOld['building'] = trim($_POST['building'] ?? '');
            $modalOld['room'] = trim($_POST['room'] ?? '');

            if ($modalOld['location_name'] === '') {
                $modalErrors['location_name'] = 'Location name is required.';
            } elseif (mb_strlen($modalOld['location_name']) > 100) {
                $modalErrors['location_name'] = 'Location name must be 100 characters or fewer.';
            }
            if (mb_strlen($modalOld['building']) > 50) {
                $modalErrors['building'] = 'Building must be 50 characters or fewer.';
            }
            if (mb_strlen($modalOld['room']) > 20) {
                $modalErrors['room'] = 'Room must be 20 characters or fewer.';
            }

            if (!$modalErrors) {
                $pdo->prepare(
                    'INSERT INTO delivery_locations (lab_id, location_name, building, room, active) VALUES (?, ?, ?, ?, 1)'
                )->execute([
                    $labId,
                    $modalOld['location_name'],
                    $modalOld['building'] !== '' ? $modalOld['building'] : null,
                    $modalOld['room'] !== '' ? $modalOld['room'] : null,
                ]);
                $old['delivery_location_id'] = (string) $pdo->lastInsertId();
                $successToast = ['success', 'Location added.'];
            } else {
                $modalRetry = 'add_location';
            }
        } else { // add_product_user
            $modalOld['first_name'] = trim($_POST['first_name'] ?? '');
            $modalOld['last_name'] = trim($_POST['last_name'] ?? '');

            if ($modalOld['first_name'] === '') {
                $modalErrors['first_name'] = 'First name is required.';
            } elseif (mb_strlen($modalOld['first_name']) > 100) {
                $modalErrors['first_name'] = 'First name must be 100 characters or fewer.';
            }
            if ($modalOld['last_name'] === '') {
                $modalErrors['last_name'] = 'Last name is required.';
            } elseif (mb_strlen($modalOld['last_name']) > 100) {
                $modalErrors['last_name'] = 'Last name must be 100 characters or fewer.';
            }

            if (!$modalErrors) {
                $pdo->prepare(
                    'INSERT INTO product_users (lab_id, first_name, last_name, active) VALUES (?, ?, ?, 1)'
                )->execute([$labId, $modalOld['first_name'], $modalOld['last_name']]);
                $old['product_user_id'] = (string) $pdo->lastInsertId();
                $successToast = ['success', 'Product user added.'];
            } else {
                $modalRetry = 'add_product_user';
            }
        }
    } elseif ($action === 'place_order') {
        foreach ($old as $key => $_) {
            $old[$key] = isset($_POST[$key]) ? trim((string) $_POST[$key]) : '';
        }

        // ---- Compound: must be an active, priced compound on THIS
        // customer's institute list (institute_compounds), not just any
        // compound in the master list. ----
        $instituteCompoundId = ctype_digit($old['institute_compound_id']) ? (int) $old['institute_compound_id'] : 0;
        $compoundId = null;
        $standardCost = null;
        if ($instituteCompoundId <= 0) {
            $fieldErrors['institute_compound_id'] = 'Select a compound.';
        } else {
            $stmt = $pdo->prepare(
                'SELECT cm.compound_id, cm.standard_cost
                 FROM institute_compounds icx
                 JOIN compounds cm ON cm.compound_id = icx.compound_id
                 WHERE icx.institute_compound_id = ? AND icx.institute_id = ?
                   AND cm.active = 1 AND cm.standard_cost IS NOT NULL
                 LIMIT 1'
            );
            $stmt->execute([$instituteCompoundId, $instituteId]);
            $compoundRow = $stmt->fetch();
            if (!$compoundRow) {
                $fieldErrors['institute_compound_id'] = 'Select a valid compound.';
            } else {
                $compoundId = (int) $compoundRow['compound_id'];
                $standardCost = $compoundRow['standard_cost'];
            }
        }

        // ---- Delivery option: must be one of THIS compound's allowed
        // methods, not any delivery option in the system. ----
        $deliveryOptionId = ctype_digit($old['delivery_option_id']) ? (int) $old['delivery_option_id'] : 0;
        if ($deliveryOptionId <= 0) {
            $fieldErrors['delivery_option_id'] = 'Select a delivery method.';
        } elseif ($compoundId !== null) {
            $stmt = $pdo->prepare(
                'SELECT 1 FROM compound_delivery_options cdo
                 JOIN delivery_options do ON do.delivery_option_id = cdo.delivery_option_id
                 WHERE cdo.compound_id = ? AND cdo.delivery_option_id = ? AND do.active = 1'
            );
            $stmt->execute([$compoundId, $deliveryOptionId]);
            if (!$stmt->fetchColumn()) {
                $fieldErrors['delivery_option_id'] = 'Select a valid delivery method for the chosen compound.';
            }
        }

        // ---- Activity ----
        if ($old['activity_mci'] === '' || !is_numeric($old['activity_mci']) || (float) $old['activity_mci'] <= 0) {
            $fieldErrors['activity_mci'] = 'Enter a valid activity (mCi).';
        }

        // ---- Requested date & time: 24-hour HH:MM only (no AM/PM), must
        // be strictly in the future. Sourced from MySQL's NOW() rather
        // than PHP's time(), since the app server and DB server can run
        // in different timezones. ----
        $requestedDatetimeSql = null;
        if ($old['requested_date'] === '' || $old['requested_time'] === '') {
            $fieldErrors['requested_datetime'] = 'Select a requested date and time.';
        } elseif (!preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $old['requested_time'])) {
            $fieldErrors['requested_datetime'] = 'Enter time as 24-hour HH:MM.';
        } else {
            $requestedDt = DateTime::createFromFormat('Y-m-d H:i', $old['requested_date'] . ' ' . $old['requested_time']);
            $parseErrors = DateTime::getLastErrors();
            if ($requestedDt === false || ($parseErrors && ($parseErrors['warning_count'] > 0 || $parseErrors['error_count'] > 0))) {
                $fieldErrors['requested_datetime'] = 'Enter a valid date and time.';
            } else {
                $dbNow = new DateTime((string) $pdo->query('SELECT NOW()')->fetchColumn());
                if ($requestedDt <= $dbNow) {
                    $fieldErrors['requested_datetime'] = 'Requested date and time must be in the future.';
                } else {
                    $requestedDatetimeSql = $requestedDt->format('Y-m-d H:i:00');
                }
            }
        }

        // ---- Delivery location: must belong to this customer's lab ----
        $deliveryLocationId = ctype_digit($old['delivery_location_id']) ? (int) $old['delivery_location_id'] : 0;
        if ($deliveryLocationId <= 0) {
            $fieldErrors['delivery_location_id'] = 'Select a delivery location.';
        } else {
            $stmt = $pdo->prepare('SELECT 1 FROM delivery_locations WHERE location_id = ? AND lab_id = ? AND active = 1');
            $stmt->execute([$deliveryLocationId, $labId]);
            if (!$stmt->fetchColumn()) {
                $fieldErrors['delivery_location_id'] = 'Select a valid delivery location.';
            }
        }

        // ---- Product user: must belong to this customer's lab ----
        $productUserId = ctype_digit($old['product_user_id']) ? (int) $old['product_user_id'] : 0;
        if ($productUserId <= 0) {
            $fieldErrors['product_user_id'] = 'Select a product user.';
        } else {
            $stmt = $pdo->prepare('SELECT 1 FROM product_users WHERE product_user_id = ? AND lab_id = ? AND active = 1');
            $stmt->execute([$productUserId, $labId]);
            if (!$stmt->fetchColumn()) {
                $fieldErrors['product_user_id'] = 'Select a valid product user.';
            }
        }

        // ---- Special instructions (optional) ----
        $specialInstructions = $old['special_instructions'];
        if (mb_strlen($specialInstructions) > 1000) {
            $fieldErrors['special_instructions'] = 'Special instructions must be 1000 characters or fewer.';
        }

        if (!$fieldErrors) {
            $pdo->beginTransaction();
            try {
                $pdo->prepare(
                    "INSERT INTO orders (order_type, customer_id, status, cost_snapshot) VALUES ('A', ?, 'pending', ?)"
                )->execute([$myUserId, $standardCost]);
                $orderId = (int) $pdo->lastInsertId();

                $pdo->prepare(
                    'INSERT INTO order_type_a_details
                        (order_id, institute_compound_id, delivery_option_id, activity_mci, requested_datetime, delivery_location_id, product_user_id, special_instructions)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                )->execute([
                    $orderId,
                    $instituteCompoundId,
                    $deliveryOptionId,
                    (float) $old['activity_mci'],
                    $requestedDatetimeSql,
                    $deliveryLocationId,
                    $productUserId,
                    $specialInstructions !== '' ? $specialInstructions : null,
                ]);

                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }

            redirect('/customer/new_order.php?placed=' . $orderId);
        }
    }
}

// ---- Catalog / lab data, loaded fresh on every render (GET or a
// redisplay after a failed/partial POST) so it always reflects any row
// just added via the inline modals above. ----
$isotopes = [];
$compounds = [];
$deliveryLocations = [];
$productUsers = [];

if ($labId !== null) {
    $stmt = $pdo->prepare(
        'SELECT DISTINCT iso.isotope_id, iso.isotope_name
         FROM isotopes iso
         JOIN compound_isotopes ci ON ci.isotope_id = iso.isotope_id
         JOIN compounds cm ON cm.compound_id = ci.compound_id
         JOIN institute_compounds icx ON icx.compound_id = cm.compound_id
         WHERE icx.institute_id = ? AND iso.active = 1 AND cm.active = 1 AND cm.standard_cost IS NOT NULL
         ORDER BY iso.isotope_name'
    );
    $stmt->execute([$instituteId]);
    $isotopes = $stmt->fetchAll();

    // institute_compound_id (not compound_id) is what order_type_a_details
    // actually FKs against -- see docs/SCHEMA.md. isotope_ids/
    // delivery_option_ids are space-separated id lists for the client-side
    // cascade filter (GROUP_CONCAT(DISTINCT ...) keeps each list correct
    // even though the two joins below cross-multiply rows per compound).
    $stmt = $pdo->prepare(
        'SELECT
            icx.institute_compound_id, cm.compound_name,
            GROUP_CONCAT(DISTINCT ci.isotope_id ORDER BY ci.isotope_id SEPARATOR " ") AS isotope_ids,
            GROUP_CONCAT(DISTINCT do.delivery_option_id ORDER BY do.delivery_option_id SEPARATOR " ") AS delivery_option_ids
         FROM institute_compounds icx
         JOIN compounds cm ON cm.compound_id = icx.compound_id
         JOIN compound_isotopes ci ON ci.compound_id = cm.compound_id
         LEFT JOIN compound_delivery_options cdo ON cdo.compound_id = cm.compound_id
         LEFT JOIN delivery_options do ON do.delivery_option_id = cdo.delivery_option_id AND do.active = 1
         WHERE icx.institute_id = ? AND cm.active = 1 AND cm.standard_cost IS NOT NULL
         GROUP BY icx.institute_compound_id, cm.compound_name
         ORDER BY cm.compound_name'
    );
    $stmt->execute([$instituteId]);
    $compounds = $stmt->fetchAll();

    $stmt = $pdo->prepare(
        'SELECT location_id, location_name, building, room
         FROM delivery_locations WHERE lab_id = ? AND active = 1 ORDER BY location_name'
    );
    $stmt->execute([$labId]);
    $deliveryLocations = $stmt->fetchAll();

    $stmt = $pdo->prepare(
        'SELECT product_user_id, first_name, last_name
         FROM product_users WHERE lab_id = ? AND active = 1 ORDER BY last_name, first_name'
    );
    $stmt->execute([$labId]);
    $productUsers = $stmt->fetchAll();
}

$placedOrderId = isset($_GET['placed']) && ctype_digit((string) $_GET['placed']) ? (int) $_GET['placed'] : 0;

$pageTitle = 'New Order';
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
            <div class="page-header">
                <div>
                    <span class="page-header__eyebrow">Orders</span>
                    <h1>New Order</h1>
                </div>
            </div>

            <?php if ($successToast !== null): ?>
                <?= toast_flash($successToast[0], $successToast[1]) ?>
            <?php endif; ?>

            <?php if ($labId === null): ?>
                <div class="card customer-order-form-card">
                    <p class="muted mb-0">Your account has no lab assigned &mdash; contact an administrator.</p>
                </div>
            <?php else: ?>

                <?php if ($placedOrderId > 0): ?>
                    <div class="alert alert--success">Order #<?= $placedOrderId ?> placed &mdash; pending review by staff.</div>
                <?php endif; ?>

                <?php if ($fieldErrors): ?>
                    <div class="alert alert--error">Please correct the errors below and resubmit.</div>
                <?php endif; ?>

                <div class="card customer-order-form-card">
                    <form method="post" novalidate id="order-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="place_order">

                        <div class="form-section">
                            <span class="form-section__title">Isotope &amp; Compound</span>

                            <div class="field">
                                <label for="isotope_id">Isotope</label>
                                <select id="isotope_id">
                                    <option value="">Select isotope&hellip;</option>
                                    <?php foreach ($isotopes as $iso): ?>
                                        <option value="<?= (int) $iso['isotope_id'] ?>"><?= e($iso['isotope_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="<?= field_class($fieldErrors, 'institute_compound_id') ?> mb-0">
                                <label for="institute_compound_id">Compound</label>
                                <select id="institute_compound_id" name="institute_compound_id" required>
                                    <option value="">Select isotope first&hellip;</option>
                                    <?php foreach ($compounds as $c): ?>
                                        <option
                                            value="<?= (int) $c['institute_compound_id'] ?>"
                                            data-isotope-ids="<?= e((string) $c['isotope_ids']) ?>"
                                            data-delivery-option-ids="<?= e((string) $c['delivery_option_ids']) ?>"
                                            <?= $old['institute_compound_id'] === (string) $c['institute_compound_id'] ? 'selected' : '' ?>
                                        ><?= e($c['compound_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?= field_error($fieldErrors, 'institute_compound_id') ?>
                            </div>
                        </div>

                        <div id="order-details" hidden>
                            <div class="form-section">
                                <span class="form-section__title">Order Details</span>

                                <div class="<?= field_class($fieldErrors, 'delivery_option_id') ?>">
                                    <label for="delivery_option_id">Delivery method</label>
                                    <select id="delivery_option_id" name="delivery_option_id" required>
                                        <option value="">Select compound first&hellip;</option>
                                        <?php
                                        $allDeliveryOptions = [];
                                        foreach ($compounds as $c) {
                                            foreach (explode(' ', (string) $c['delivery_option_ids']) as $doId) {
                                                if ($doId !== '') {
                                                    $allDeliveryOptions[(int) $doId] = true;
                                                }
                                            }
                                        }
                                        if ($allDeliveryOptions):
                                            $doStmt = $pdo->prepare(
                                                'SELECT delivery_option_id, option_name FROM delivery_options
                                                 WHERE delivery_option_id IN (' . implode(',', array_fill(0, count($allDeliveryOptions), '?')) . ')
                                                 ORDER BY option_name'
                                            );
                                            $doStmt->execute(array_keys($allDeliveryOptions));
                                            foreach ($doStmt->fetchAll() as $d): ?>
                                                <option value="<?= (int) $d['delivery_option_id'] ?>" <?= $old['delivery_option_id'] === (string) $d['delivery_option_id'] ? 'selected' : '' ?>><?= e($d['option_name']) ?></option>
                                            <?php endforeach;
                                        endif; ?>
                                    </select>
                                    <?= field_error($fieldErrors, 'delivery_option_id') ?>
                                </div>

                                <div class="<?= field_class($fieldErrors, 'activity_mci') ?>">
                                    <label for="activity_mci">Activity (mCi)</label>
                                    <input type="number" step="0.01" min="0.01" id="activity_mci" name="activity_mci" value="<?= e($old['activity_mci']) ?>" required>
                                    <?= field_error($fieldErrors, 'activity_mci') ?>
                                </div>

                                <div class="field-row">
                                    <div class="<?= field_class($fieldErrors, 'requested_datetime') ?>">
                                        <label for="requested_date">Requested date</label>
                                        <input type="date" id="requested_date" name="requested_date" value="<?= e($old['requested_date']) ?>" required>
                                    </div>
                                    <div class="<?= field_class($fieldErrors, 'requested_datetime') ?>">
                                        <label for="requested_time">Requested time</label>
                                        <input type="text" inputmode="numeric" placeholder="HH:MM" pattern="^([01][0-9]|2[0-3]):[0-5][0-9]$" maxlength="5" id="requested_time" name="requested_time" value="<?= e($old['requested_time']) ?>" required>
                                        <span class="field-hint">24-hour format, e.g. 14:30. No AM/PM.</span>
                                        <?= field_error($fieldErrors, 'requested_datetime') ?>
                                    </div>
                                </div>
                            </div>

                            <div class="form-section">
                                <span class="form-section__title">Delivery</span>

                                <div class="<?= field_class($fieldErrors, 'delivery_location_id') ?>">
                                    <label for="delivery_location_id">Delivery location</label>
                                    <select id="delivery_location_id" name="delivery_location_id" required>
                                        <option value="">Select location&hellip;</option>
                                        <?php foreach ($deliveryLocations as $loc): ?>
                                            <option value="<?= (int) $loc['location_id'] ?>" <?= $old['delivery_location_id'] === (string) $loc['location_id'] ? 'selected' : '' ?>><?= e($loc['location_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?= field_error($fieldErrors, 'delivery_location_id') ?>
                                    <button type="button" class="btn btn--secondary btn--sm field__inline-action" id="open-add-location">+ Add new location</button>
                                </div>

                                <div class="<?= field_class($fieldErrors, 'product_user_id') ?>">
                                    <label for="product_user_id">Product user</label>
                                    <select id="product_user_id" name="product_user_id" required>
                                        <option value="">Select product user&hellip;</option>
                                        <?php foreach ($productUsers as $pu): ?>
                                            <option value="<?= (int) $pu['product_user_id'] ?>" <?= $old['product_user_id'] === (string) $pu['product_user_id'] ? 'selected' : '' ?>><?= e($pu['first_name'] . ' ' . $pu['last_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?= field_error($fieldErrors, 'product_user_id') ?>
                                    <button type="button" class="btn btn--secondary btn--sm field__inline-action" id="open-add-product-user">+ Add new product user</button>
                                </div>

                                <div class="<?= field_class($fieldErrors, 'special_instructions') ?> mb-0">
                                    <label for="special_instructions">Special instructions <span class="form-section__suffix">&mdash; optional</span></label>
                                    <textarea id="special_instructions" name="special_instructions" maxlength="1000"><?= e($old['special_instructions']) ?></textarea>
                                    <?= field_error($fieldErrors, 'special_instructions') ?>
                                </div>
                            </div>

                            <div class="form-section">
                                <button type="submit" class="btn btn--primary">Place order</button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Add location modal: reuses the same modal-overlay / petcomOpenModal
                     convention as registrations.php's reject modal and the sidebar
                     profile-edit modal. Hidden mirror-* inputs carry the main form's
                     current state through this same-page round trip (no AJAX
                     anywhere in this app). -->
                <div class="modal-overlay" id="add-location-modal" hidden>
                    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="add-location-modal-title">
                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="add_location">
                            <?php foreach ($old as $key => $value): ?>
                                <input type="hidden" name="mirror_<?= e($key) ?>" id="mirror-loc-<?= e($key) ?>" value="<?= e($value) ?>">
                            <?php endforeach; ?>
                            <div class="modal__body">
                                <h2 class="modal__title" id="add-location-modal-title">Add delivery location</h2>
                                <p class="modal__message">Saved to your lab &mdash; any customer in your lab can reuse it.</p>
                                <div class="<?= field_class($modalErrors, 'location_name') ?>">
                                    <label for="new-location-name">Location name <span class="required-mark">*</span></label>
                                    <input type="text" id="new-location-name" name="location_name" value="<?= e($modalOld['location_name']) ?>" required data-modal-focus>
                                    <?= field_error($modalErrors, 'location_name') ?>
                                </div>
                                <div class="field-row">
                                    <div class="<?= field_class($modalErrors, 'building') ?>">
                                        <label for="new-location-building">Building</label>
                                        <input type="text" id="new-location-building" name="building" value="<?= e($modalOld['building']) ?>">
                                        <?= field_error($modalErrors, 'building') ?>
                                    </div>
                                    <div class="<?= field_class($modalErrors, 'room') ?> mb-0">
                                        <label for="new-location-room">Room</label>
                                        <input type="text" id="new-location-room" name="room" value="<?= e($modalOld['room']) ?>">
                                        <?= field_error($modalErrors, 'room') ?>
                                    </div>
                                </div>
                            </div>
                            <div class="modal__footer">
                                <button type="button" class="btn btn--ghost" data-modal-close>Cancel</button>
                                <button type="submit" class="btn btn--primary">Add location</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Add product user modal: same pattern as the location modal above. -->
                <div class="modal-overlay" id="add-product-user-modal" hidden>
                    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="add-product-user-modal-title">
                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="add_product_user">
                            <?php foreach ($old as $key => $value): ?>
                                <input type="hidden" name="mirror_<?= e($key) ?>" id="mirror-pu-<?= e($key) ?>" value="<?= e($value) ?>">
                            <?php endforeach; ?>
                            <div class="modal__body">
                                <h2 class="modal__title" id="add-product-user-modal-title">Add product user</h2>
                                <p class="modal__message">Saved to your lab &mdash; any customer in your lab can reuse it.</p>
                                <div class="field-row">
                                    <div class="<?= field_class($modalErrors, 'first_name') ?>">
                                        <label for="new-pu-first-name">First name <span class="required-mark">*</span></label>
                                        <input type="text" id="new-pu-first-name" name="first_name" value="<?= e($modalOld['first_name']) ?>" required data-modal-focus>
                                        <?= field_error($modalErrors, 'first_name') ?>
                                    </div>
                                    <div class="<?= field_class($modalErrors, 'last_name') ?> mb-0">
                                        <label for="new-pu-last-name">Last name <span class="required-mark">*</span></label>
                                        <input type="text" id="new-pu-last-name" name="last_name" value="<?= e($modalOld['last_name']) ?>" required>
                                        <?= field_error($modalErrors, 'last_name') ?>
                                    </div>
                                </div>
                            </div>
                            <div class="modal__footer">
                                <button type="button" class="btn btn--ghost" data-modal-close>Cancel</button>
                                <button type="submit" class="btn btn--primary">Add product user</button>
                            </div>
                        </form>
                    </div>
                </div>

            <?php endif; ?>
        </main>
    </div>
</body>
<script src="/assets/js/script.js" defer></script>
<?php if ($labId !== null): ?>
<script>
(function () {
    var isotopeSelect = document.getElementById('isotope_id');
    var compoundSelect = document.getElementById('institute_compound_id');
    var deliverySelect = document.getElementById('delivery_option_id');
    var orderDetails = document.getElementById('order-details');
    if (!isotopeSelect || !compoundSelect) return;

    var compoundOptions = Array.prototype.slice.call(compoundSelect.querySelectorAll('option[data-isotope-ids]'));
    var deliveryOptions = Array.prototype.slice.call(deliverySelect.querySelectorAll('option[value]:not([value=""])'));
    var deliveryPlaceholder = deliverySelect.querySelector('option[value=""]');

    function filterDelivery() {
        var selected = compoundSelect.selectedOptions[0];
        var allowedIds = selected ? (selected.dataset.deliveryOptionIds || '').split(' ') : [];
        deliveryOptions.forEach(function (opt) {
            var matches = allowedIds.indexOf(opt.value) !== -1;
            opt.hidden = !matches;
            opt.disabled = !matches;
        });
        if (deliverySelect.selectedOptions[0] && deliverySelect.selectedOptions[0].hidden) {
            deliverySelect.value = '';
        }
        deliverySelect.disabled = !compoundSelect.value;
        if (deliveryPlaceholder) {
            deliveryPlaceholder.textContent = compoundSelect.value ? 'Select delivery method…' : 'Select compound first…';
        }
    }

    function updateCompound() {
        var compoundId = compoundSelect.value;
        if (!compoundId) {
            orderDetails.hidden = true;
            filterDelivery();
            return;
        }
        orderDetails.hidden = false;
        filterDelivery();
    }

    function filterCompounds() {
        var isotopeId = isotopeSelect.value;
        compoundOptions.forEach(function (opt) {
            var ids = (opt.dataset.isotopeIds || '').split(' ');
            var matches = ids.indexOf(isotopeId) !== -1;
            opt.hidden = !matches;
            opt.disabled = !matches;
        });
        if (compoundSelect.selectedOptions[0] && compoundSelect.selectedOptions[0].hidden) {
            compoundSelect.value = '';
        }
        compoundSelect.disabled = !isotopeId;
        updateCompound();
    }

    isotopeSelect.addEventListener('change', filterCompounds);
    compoundSelect.addEventListener('change', updateCompound);

    // Reverse-sync: if the compound arrives pre-selected (a failed-submit
    // retry, or right after "+ Add new location/product user" round-trips
    // back here), derive the isotope from the compound's own isotope list
    // so the cascade renders already-open instead of collapsed.
    if (compoundSelect.value) {
        var preselected = compoundSelect.selectedOptions[0];
        var firstIsotopeId = (preselected.dataset.isotopeIds || '').split(' ')[0];
        if (firstIsotopeId) isotopeSelect.value = firstIsotopeId;
    }
    filterCompounds();

    // ===== "+ Add new ..." modals =====
    var MIRROR_FIELDS = ['institute_compound_id', 'delivery_option_id', 'activity_mci', 'requested_date', 'requested_time', 'delivery_location_id', 'product_user_id', 'special_instructions'];

    function mirrorFormState(prefix) {
        MIRROR_FIELDS.forEach(function (key) {
            var source = document.getElementById(key);
            var target = document.getElementById('mirror-' + prefix + '-' + key);
            if (source && target) target.value = source.value;
        });
    }

    var addLocationBtn = document.getElementById('open-add-location');
    var addLocationModal = document.getElementById('add-location-modal');
    if (addLocationBtn && addLocationModal) {
        addLocationBtn.addEventListener('click', function (e) {
            mirrorFormState('loc');
            window.petcomOpenModal(addLocationModal, { opener: e.currentTarget });
        });
    }

    var addProductUserBtn = document.getElementById('open-add-product-user');
    var addProductUserModal = document.getElementById('add-product-user-modal');
    if (addProductUserBtn && addProductUserModal) {
        addProductUserBtn.addEventListener('click', function (e) {
            mirrorFormState('pu');
            window.petcomOpenModal(addProductUserModal, { opener: e.currentTarget });
        });
    }

    <?php if ($modalRetry === 'add_location'): ?>
    window.petcomOpenModal(addLocationModal);
    <?php elseif ($modalRetry === 'add_product_user'): ?>
    window.petcomOpenModal(addProductUserModal);
    <?php endif; ?>
})();
</script>
<?php endif; ?>
</html>
