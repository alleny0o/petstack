<?php
require __DIR__ . '/../../src/helpers.php';
bootstrap_session();
require __DIR__ . '/../../src/auth.php';
require_role('customer');

$pdo = get_db();
$myUserId = (int) $_SESSION['user_id'];

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

$fieldErrors = [];
$old = [
    'isotope_id'          => '',
    'compound_id'         => '',
    'activity_mci'        => '',
    'requested_datetime'  => '',
    'mode'                => '',
    'beam_current'        => '',
    'bombardment_minutes' => '',
    'eob_activity_mci'    => '',
    'eob_datetime'        => '',
    'delivery_option_id'  => '',
    'comment'             => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    foreach ($old as $key => $_) {
        $old[$key] = isset($_POST[$key]) ? trim((string) $_POST[$key]) : '';
    }

    // ---- Isotope ----
    $isotopeId = ctype_digit($old['isotope_id']) ? (int) $old['isotope_id'] : 0;
    if ($isotopeId <= 0) {
        $fieldErrors['isotope_id'] = 'Select an isotope.';
    } else {
        $stmt = $pdo->prepare('SELECT 1 FROM isotopes WHERE isotope_id = ? AND active = 1');
        $stmt->execute([$isotopeId]);
        if (!$stmt->fetchColumn()) {
            $fieldErrors['isotope_id'] = 'Select a valid isotope.';
        }
    }

    // ---- Compound: must be active AND linked to the chosen isotope ----
    $compoundId = ctype_digit($old['compound_id']) ? (int) $old['compound_id'] : 0;
    $compound = null;
    if ($compoundId <= 0) {
        $fieldErrors['compound_id'] = 'Select a compound.';
    } elseif (!isset($fieldErrors['isotope_id'])) {
        $stmt = $pdo->prepare(
            'SELECT cm.compound_id, cm.order_type, cm.min_lead_time_hours, cm.standard_cost
             FROM compounds cm
             JOIN compound_isotopes ci ON ci.compound_id = cm.compound_id
             WHERE cm.compound_id = ? AND ci.isotope_id = ? AND cm.active = 1
             LIMIT 1'
        );
        $stmt->execute([$compoundId, $isotopeId]);
        $compound = $stmt->fetch();
        if (!$compound) {
            $fieldErrors['compound_id'] = 'Select a valid compound for the chosen isotope.';
        }
    }

    $orderType = $compound['order_type'] ?? null;

    $activityMci = null;
    $requestedDatetimeSql = null;
    $mode = null;
    $beamCurrent = null;
    $bombardmentMinutes = null;
    $eobActivityMci = null;
    $eobDatetimeSql = null;

    if ($orderType === 'A') {
        if ($old['activity_mci'] === '' || !is_numeric($old['activity_mci']) || (float) $old['activity_mci'] <= 0) {
            $fieldErrors['activity_mci'] = 'Enter a valid activity (mCi).';
        } else {
            $activityMci = (float) $old['activity_mci'];
        }

        if ($old['requested_datetime'] === '') {
            $fieldErrors['requested_datetime'] = 'Select a requested date and time.';
        } else {
            $requestedDt = datetime_local_to_dt($old['requested_datetime']);
            if ($requestedDt === null) {
                $fieldErrors['requested_datetime'] = 'Enter a valid date and time.';
            } else {
                $minLeadHours = (float) $compound['min_lead_time_hours'];
                // Sourced from MySQL's NOW() rather than PHP's time(), since
                // the app server and DB server can run in different
                // timezones (see dashboard.php's "last viewed" comparison
                // for the same reasoning).
                $dbNow = new DateTime((string) $pdo->query('SELECT NOW()')->fetchColumn());
                $cutoff = clone $dbNow;
                $cutoff->modify('+' . (int) round($minLeadHours * 3600) . ' seconds');
                if ($requestedDt < $cutoff) {
                    $fieldErrors['requested_datetime'] = 'Requires at least ' . format_lead_hours($minLeadHours) . ' hours notice.';
                } else {
                    $requestedDatetimeSql = $requestedDt->format('Y-m-d H:i:00');
                }
            }
        }
    } elseif ($orderType === 'B') {
        $mode = in_array($old['mode'], ['beam', 'eob'], true) ? $old['mode'] : null;
        if ($mode === null) {
            $fieldErrors['mode'] = 'Select a run mode (beam or EOB).';
        } elseif ($mode === 'beam') {
            if ($old['beam_current'] === '' || !is_numeric($old['beam_current']) || (float) $old['beam_current'] <= 0) {
                $fieldErrors['beam_current'] = 'Enter a valid beam current.';
            } else {
                $beamCurrent = (float) $old['beam_current'];
            }
            if ($old['bombardment_minutes'] === '' || !ctype_digit($old['bombardment_minutes']) || (int) $old['bombardment_minutes'] <= 0) {
                $fieldErrors['bombardment_minutes'] = 'Enter a valid bombardment time in minutes.';
            } else {
                $bombardmentMinutes = (int) $old['bombardment_minutes'];
            }
        } else { // eob
            if ($old['eob_activity_mci'] === '' || !is_numeric($old['eob_activity_mci']) || (float) $old['eob_activity_mci'] <= 0) {
                $fieldErrors['eob_activity_mci'] = 'Enter a valid EOB activity.';
            } else {
                $eobActivityMci = (float) $old['eob_activity_mci'];
            }
            if ($old['eob_datetime'] === '') {
                $fieldErrors['eob_datetime'] = 'Select an EOB date and time.';
            } else {
                $eobDt = datetime_local_to_dt($old['eob_datetime']);
                if ($eobDt === null) {
                    $fieldErrors['eob_datetime'] = 'Enter a valid date and time.';
                } else {
                    $eobDatetimeSql = $eobDt->format('Y-m-d H:i:00');
                }
            }
        }
    }

    // ---- Delivery option: must be in the compound's allowed list ----
    $deliveryOptionId = ctype_digit($old['delivery_option_id']) ? (int) $old['delivery_option_id'] : 0;
    if ($deliveryOptionId <= 0) {
        $fieldErrors['delivery_option_id'] = 'Select a delivery method.';
    } elseif ($compound !== null) {
        $stmt = $pdo->prepare('SELECT 1 FROM compound_delivery_options WHERE compound_id = ? AND delivery_option_id = ?');
        $stmt->execute([$compoundId, $deliveryOptionId]);
        if (!$stmt->fetchColumn()) {
            $fieldErrors['delivery_option_id'] = 'Select a valid delivery method for the chosen compound.';
        }
    }

    // ---- Comment (optional) ----
    $comment = $old['comment'];
    if (mb_strlen($comment) > 1000) {
        $fieldErrors['comment'] = 'Comment must be 1000 characters or fewer.';
    }

    if (!$fieldErrors && $compound !== null) {
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                "INSERT INTO orders (customer_id, compound_id, isotope_id, delivery_option_id, status, cost_snapshot, created_by)
                 VALUES (?, ?, ?, ?, 'pending', ?, ?)"
            )->execute([$myUserId, $compoundId, $isotopeId, $deliveryOptionId, $compound['standard_cost'], $myUserId]);
            $orderId = (int) $pdo->lastInsertId();

            if ($orderType === 'A') {
                $pdo->prepare('INSERT INTO order_type_a_details (order_id, activity_mci, requested_datetime) VALUES (?, ?, ?)')
                    ->execute([$orderId, $activityMci, $requestedDatetimeSql]);
            } else {
                $pdo->prepare(
                    'INSERT INTO order_type_b_details (order_id, mode, beam_current, bombardment_minutes, eob_activity_mci, eob_datetime)
                     VALUES (?, ?, ?, ?, ?, ?)'
                )->execute([$orderId, $mode, $beamCurrent, $bombardmentMinutes, $eobActivityMci, $eobDatetimeSql]);
            }

            if ($comment !== '') {
                $pdo->prepare('INSERT INTO order_public_comments (order_id, author_id, body) VALUES (?, ?, ?)')
                    ->execute([$orderId, $myUserId, $comment]);
            }

            $pdo->prepare("INSERT INTO order_audit_log (order_id, changed_by, status_from, status_to) VALUES (?, ?, NULL, 'pending')")
                ->execute([$orderId, $myUserId]);

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        redirect('/customer/order_detail.php?id=' . $orderId . '&placed=1');
    }
}

$isotopes = $pdo->query('SELECT isotope_id, isotope_name FROM isotopes WHERE active = 1 ORDER BY isotope_name')->fetchAll();

$stmt = $pdo->query(
    "SELECT cm.compound_id, cm.name, cm.order_type, cm.min_lead_time_hours,
            GROUP_CONCAT(ci.isotope_id ORDER BY ci.isotope_id SEPARATOR ' ') AS isotope_ids
     FROM compounds cm
     JOIN compound_isotopes ci ON ci.compound_id = cm.compound_id
     JOIN isotopes iso ON iso.isotope_id = ci.isotope_id AND iso.active = 1
     WHERE cm.active = 1
     GROUP BY cm.compound_id, cm.name, cm.order_type, cm.min_lead_time_hours
     ORDER BY cm.name"
);
$compounds = $stmt->fetchAll();

$stmt = $pdo->query(
    "SELECT d.delivery_option_id, d.name,
            GROUP_CONCAT(cdo.compound_id ORDER BY cdo.compound_id SEPARATOR ' ') AS compound_ids
     FROM delivery_options d
     JOIN compound_delivery_options cdo ON cdo.delivery_option_id = d.delivery_option_id
     JOIN compounds cm ON cm.compound_id = cdo.compound_id AND cm.active = 1
     GROUP BY d.delivery_option_id, d.name
     ORDER BY d.name"
);
$deliveryOptions = $stmt->fetchAll();

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
                    <h1>New Order</h1>
                </div>
            </div>

            <div class="card customer-order-form-card">
                <?php if ($fieldErrors): ?>
                    <div class="alert alert--error">Please correct the errors below and resubmit.</div>
                <?php endif; ?>

                <form method="post" novalidate id="order-form">
                    <?= csrf_field() ?>

                    <div class="form-section">
                        <span class="form-section__title">Isotope &amp; Compound</span>

                        <div class="field">
                            <label for="isotope_id">Isotope</label>
                            <select id="isotope_id" name="isotope_id" required>
                                <option value="">Select isotope&hellip;</option>
                                <?php foreach ($isotopes as $iso): ?>
                                    <option value="<?= (int) $iso['isotope_id'] ?>" <?= $old['isotope_id'] === (string) $iso['isotope_id'] ? 'selected' : '' ?>><?= e($iso['isotope_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?= field_error($fieldErrors, 'isotope_id') ?>
                        </div>

                        <div class="field mb-0">
                            <label for="compound_id">Compound</label>
                            <select id="compound_id" name="compound_id" required>
                                <option value="">Select isotope first&hellip;</option>
                                <?php foreach ($compounds as $c): ?>
                                    <option
                                        value="<?= (int) $c['compound_id'] ?>"
                                        data-isotope-ids="<?= e($c['isotope_ids']) ?>"
                                        data-order-type="<?= e($c['order_type']) ?>"
                                        data-min-lead-hours="<?= e($c['min_lead_time_hours']) ?>"
                                        <?= $old['compound_id'] === (string) $c['compound_id'] ? 'selected' : '' ?>
                                    ><?= e($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?= field_error($fieldErrors, 'compound_id') ?>
                        </div>
                    </div>

                    <div id="step-2" hidden>
                        <div class="form-section" id="type-a-section" hidden>
                            <span class="form-section__title">Dose Details</span>
                            <div class="field-row">
                                <div class="field">
                                    <label for="activity_mci">Activity (mCi)</label>
                                    <input type="number" step="0.01" min="0" id="activity_mci" name="activity_mci" value="<?= e($old['activity_mci']) ?>">
                                    <?= field_error($fieldErrors, 'activity_mci') ?>
                                </div>
                                <div class="field">
                                    <label for="requested_datetime">Requested date &amp; time</label>
                                    <input type="datetime-local" id="requested_datetime" name="requested_datetime" value="<?= e($old['requested_datetime']) ?>">
                                    <span class="field-hint" id="lead-time-hint"></span>
                                    <?= field_error($fieldErrors, 'requested_datetime') ?>
                                </div>
                            </div>
                        </div>

                        <div class="form-section" id="type-b-section" hidden>
                            <span class="form-section__title">Cyclotron Run Details</span>

                            <div class="radio-card-group">
                                <label class="radio-card" for="mode_beam">
                                    <input type="radio" id="mode_beam" name="mode" value="beam" <?= $old['mode'] === 'beam' ? 'checked' : '' ?>>
                                    <span class="radio-card__title">Beam</span>
                                    <span class="radio-card__desc">Specify beam current and bombardment time.</span>
                                </label>
                                <label class="radio-card" for="mode_eob">
                                    <input type="radio" id="mode_eob" name="mode" value="eob" <?= $old['mode'] === 'eob' ? 'checked' : '' ?>>
                                    <span class="radio-card__title">End of Bombardment (EOB)</span>
                                    <span class="radio-card__desc">Specify EOB activity and datetime.</span>
                                </label>
                            </div>
                            <?= field_error($fieldErrors, 'mode') ?>

                            <div class="field-row" id="beam-fields" hidden>
                                <div class="field">
                                    <label for="beam_current">Beam current</label>
                                    <input type="number" step="0.01" min="0" id="beam_current" name="beam_current" value="<?= e($old['beam_current']) ?>">
                                    <?= field_error($fieldErrors, 'beam_current') ?>
                                </div>
                                <div class="field">
                                    <label for="bombardment_minutes">Bombardment (minutes)</label>
                                    <input type="number" step="1" min="0" id="bombardment_minutes" name="bombardment_minutes" value="<?= e($old['bombardment_minutes']) ?>">
                                    <?= field_error($fieldErrors, 'bombardment_minutes') ?>
                                </div>
                            </div>

                            <div class="field-row" id="eob-fields" hidden>
                                <div class="field">
                                    <label for="eob_activity_mci">EOB activity (mCi)</label>
                                    <input type="number" step="0.01" min="0" id="eob_activity_mci" name="eob_activity_mci" value="<?= e($old['eob_activity_mci']) ?>">
                                    <?= field_error($fieldErrors, 'eob_activity_mci') ?>
                                </div>
                                <div class="field">
                                    <label for="eob_datetime">EOB date &amp; time</label>
                                    <input type="datetime-local" id="eob_datetime" name="eob_datetime" value="<?= e($old['eob_datetime']) ?>">
                                    <?= field_error($fieldErrors, 'eob_datetime') ?>
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <span class="form-section__title">Delivery</span>

                            <div class="field">
                                <label for="delivery_option_id">Delivery method</label>
                                <select id="delivery_option_id" name="delivery_option_id" required>
                                    <option value="">Select compound first&hellip;</option>
                                    <?php foreach ($deliveryOptions as $d): ?>
                                        <option
                                            value="<?= (int) $d['delivery_option_id'] ?>"
                                            data-compound-ids="<?= e($d['compound_ids']) ?>"
                                            <?= $old['delivery_option_id'] === (string) $d['delivery_option_id'] ? 'selected' : '' ?>
                                        ><?= e($d['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?= field_error($fieldErrors, 'delivery_option_id') ?>
                            </div>

                            <div class="field mb-4">
                                <label for="comment">Comment (optional)</label>
                                <textarea id="comment" name="comment" maxlength="1000"><?= e($old['comment']) ?></textarea>
                                <?= field_error($fieldErrors, 'comment') ?>
                            </div>
                        </div>

                        <button type="submit" class="btn btn--primary">Place order</button>
                    </div>
                </form>
            </div>
        </main>
    </div>
</body>
<script src="/assets/js/script.js" defer></script>
<script>
(function () {
    var isotopeSelect = document.getElementById('isotope_id');
    var compoundSelect = document.getElementById('compound_id');
    var deliverySelect = document.getElementById('delivery_option_id');
    var step2 = document.getElementById('step-2');
    var typeASection = document.getElementById('type-a-section');
    var typeBSection = document.getElementById('type-b-section');
    var beamFields = document.getElementById('beam-fields');
    var eobFields = document.getElementById('eob-fields');
    var leadTimeHint = document.getElementById('lead-time-hint');
    if (!isotopeSelect || !compoundSelect) return;

    var compoundOptions = Array.prototype.slice.call(compoundSelect.querySelectorAll('option[data-isotope-ids]'));
    var deliveryOptions = Array.prototype.slice.call(deliverySelect.querySelectorAll('option[data-compound-ids]'));
    var radioCards = Array.prototype.slice.call(document.querySelectorAll('.radio-card'));

    function filterDelivery() {
        var compoundId = compoundSelect.value;
        deliveryOptions.forEach(function (opt) {
            var ids = opt.dataset.compoundIds.split(' ');
            var matches = ids.indexOf(compoundId) !== -1;
            opt.hidden = !matches;
            opt.disabled = !matches;
        });
        if (deliverySelect.selectedOptions[0] && deliverySelect.selectedOptions[0].hidden) {
            deliverySelect.value = '';
        }
        deliverySelect.disabled = !compoundId;
    }

    function updateCompound() {
        var selected = compoundSelect.selectedOptions[0];
        var compoundId = compoundSelect.value;

        if (!compoundId || !selected || selected.hidden) {
            step2.hidden = true;
            filterDelivery();
            return;
        }

        step2.hidden = false;

        var orderType = selected.dataset.orderType;
        if (orderType === 'A') {
            typeASection.hidden = false;
            typeBSection.hidden = true;

            var minLeadHours = parseFloat(selected.dataset.minLeadHours);
            var hoursText = (minLeadHours % 1 === 0) ? minLeadHours.toFixed(0) : String(minLeadHours);
            leadTimeHint.textContent = 'Requires at least ' + hoursText + ' hours notice.';
        } else {
            typeASection.hidden = true;
            typeBSection.hidden = false;
        }

        filterDelivery();
    }

    function filterCompounds() {
        var isotopeId = isotopeSelect.value;
        compoundOptions.forEach(function (opt) {
            var ids = opt.dataset.isotopeIds.split(' ');
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

    function updateMode() {
        var checked = document.querySelector('input[name="mode"]:checked');
        var mode = checked ? checked.value : '';
        beamFields.hidden = mode !== 'beam';
        eobFields.hidden = mode !== 'eob';

        radioCards.forEach(function (card) {
            var input = card.querySelector('input[name="mode"]');
            var isSelected = !!(input && input.checked);
            card.classList.toggle('radio-card--selected', isSelected);
        });
    }

    isotopeSelect.addEventListener('change', filterCompounds);
    compoundSelect.addEventListener('change', updateCompound);
    document.querySelectorAll('input[name="mode"]').forEach(function (radio) {
        radio.addEventListener('change', updateMode);
    });

    filterCompounds();
    updateMode();
})();
</script>
</html>
