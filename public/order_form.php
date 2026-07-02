<?php
session_start();
require __DIR__ . '/../src/demo_orders.php';

/**
 * New Order form.
 *
 * Isotope-first per the business rules: pick an isotope, compounds filter
 * to match, and the chosen compound decides everything downstream — order
 * type (A dose / B cyclotron), minimum lead time, allowed delivery options.
 *
 * Persistence is the $_SESSION demo store (src/demo_orders.php) until the
 * real database lands. TODO(db): swap demo_* calls for PDO + add CSRF +
 * require_role('customer').
 */

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isotope  = $_POST['isotope']  ?? '';
    $compound = $_POST['compound'] ?? '';
    $delivery = $_POST['delivery'] ?? '';
    $catalogEntry = demo_catalog_find($isotope, $compound);

    if ($catalogEntry === null) {
        $error = 'Pick a valid isotope and compound.';
    } elseif (!in_array($delivery, $catalogEntry['delivery'], true)) {
        $error = 'That delivery option is not available for ' . htmlspecialchars($compound) . '.';
    } else {
        $order = [
            'compound' => $compound,
            'isotope'  => $isotope,
            'type'     => $catalogEntry['type'],
            'requested' => null, 'activity' => null,
            'b_mode' => null, 'b_current' => null, 'b_time' => null,
            'b_activity' => null, 'b_datetime' => null,
            'delivery' => $delivery,
            'comment'  => trim($_POST['comment'] ?? ''),
        ];

        $minTime = time() + $catalogEntry['leadHours'] * 3600;

        if ($catalogEntry['type'] === 'A') {
            $order['activity']  = $_POST['a_activity'] ?? '';
            $order['requested'] = str_replace('T', ' ', $_POST['a_datetime'] ?? '');
            if ($order['activity'] === '' || $order['requested'] === '') {
                $error = 'Type A orders need an activity and a requested date & time.';
            } elseif (strtotime($order['requested']) < $minTime) {
                $error = $compound . ' needs at least ' . $catalogEntry['leadHours'] . ' hours of lead time.';
            }
        } else {
            $order['b_mode'] = $_POST['b_mode'] ?? '';
            if ($order['b_mode'] === 'beam') {
                $order['b_current'] = $_POST['b_current'] ?? '';
                $order['b_time']    = $_POST['b_time'] ?? '';
                if ($order['b_current'] === '' || $order['b_time'] === '') {
                    $error = 'Beam-current orders need both current and time.';
                }
            } elseif ($order['b_mode'] === 'eob') {
                $order['b_activity'] = $_POST['b_activity'] ?? '';
                $order['b_datetime'] = str_replace('T', ' ', $_POST['b_datetime'] ?? '');
                if ($order['b_activity'] === '' || $order['b_datetime'] === '') {
                    $error = 'EOB orders need an activity and a date & time.';
                } elseif (strtotime($order['b_datetime']) < $minTime) {
                    $error = $compound . ' needs at least ' . $catalogEntry['leadHours'] . ' hours of lead time.';
                }
            } else {
                $error = 'Pick beam current or EOB activity.';
            }
        }

        if ($error === '') {
            $id = demo_order_add($order);
            header('Location: order_detail.php?id=' . $id . '&placed=1');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php $pageTitle = 'New Order'; $roleCss = 'customer';
    include '../src/partials/head.php'; ?>
</head>

<body>

    <div class="app-shell">
        <?php include '../src/partials/layout_customer.php'; ?>

        <main class="app-main">

            <div>
                <h1 class="mb-0">New Order</h1>
                <span class="text-sm muted">[INST] &middot; [LAB]</span>
            </div>

            <form method="post" action="order_form.php" class="order-form">

                <?php if ($error): ?>
                    <div class="alert alert--error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <div class="card">

                    <div class="form-section">
                        <span class="form-section__title">Compound</span>

                        <div class="field">
                            <label for="isotope">Isotope</label>
                            <select id="isotope" name="isotope" required>
                                <option value="">Select isotope…</option>
                                <option value="F-18">F-18</option>
                                <option value="C-11">C-11</option>
                                <option value="O-15">O-15</option>
                            </select>
                        </div>

                        <div class="field mb-0">
                            <label for="compound">Compound</label>
                            <select id="compound" name="compound" required disabled>
                                <option value="">Select isotope first…</option>
                            </select>
                            <div class="compound-meta" id="compound-meta" hidden></div>
                        </div>
                    </div>

                    <div class="form-section" id="section-details" hidden>
                        <span class="form-section__title">Order details</span>

                        <!-- Type A (dose): activity + requested date/time -->
                        <div id="type-a" hidden>
                            <div class="field-row">
                                <div class="field mb-0">
                                    <label for="a-activity">Activity (mCi)</label>
                                    <input type="number" id="a-activity" name="a_activity" min="0" step="any">
                                </div>
                                <div class="field mb-0">
                                    <label for="a-datetime">Requested date &amp; time</label>
                                    <input type="datetime-local" id="a-datetime" name="a_datetime">
                                </div>
                            </div>
                        </div>

                        <!-- Type B (cyclotron): beam-current×time OR EOB activity, mutually exclusive -->
                        <div id="type-b" hidden>
                            <div class="radio-option">
                                <input type="radio" name="b_mode" value="beam" id="b-mode-beam" checked>
                                <div class="radio-option__body">
                                    <label class="radio-option__title" for="b-mode-beam">Beam current &times; time</label>
                                    <div class="field-row">
                                        <div class="field mb-0">
                                            <label for="b-current">Current (&micro;A)</label>
                                            <input type="number" id="b-current" name="b_current" min="0" step="any">
                                        </div>
                                        <div class="field mb-0">
                                            <label for="b-time">Time (min)</label>
                                            <input type="number" id="b-time" name="b_time" min="0" step="any">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="radio-option">
                                <input type="radio" name="b_mode" value="eob" id="b-mode-eob">
                                <div class="radio-option__body">
                                    <label class="radio-option__title" for="b-mode-eob">EOB activity</label>
                                    <div class="field-row">
                                        <div class="field mb-0">
                                            <label for="b-activity">Activity (mCi)</label>
                                            <input type="number" id="b-activity" name="b_activity" min="0" step="any" disabled>
                                        </div>
                                        <div class="field mb-0">
                                            <label for="b-datetime">EOB date &amp; time</label>
                                            <input type="datetime-local" id="b-datetime" name="b_datetime" disabled>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-section" id="section-delivery" hidden>
                        <span class="form-section__title">Delivery &amp; notes</span>

                        <div class="field">
                            <label for="delivery">Delivery</label>
                            <select id="delivery" name="delivery" required disabled>
                                <option value="">Select delivery…</option>
                            </select>
                        </div>

                        <div class="field">
                            <label for="comment">Comment <span class="muted">(optional)</span></label>
                            <textarea id="comment" name="comment" placeholder="Anything staff should know about this order…"></textarea>
                        </div>

                        <div class="flex gap-3">
                            <button type="submit" class="btn btn--primary">Place Order</button>
                            <a href="customer_home.php" class="btn btn--secondary">Cancel</a>
                        </div>
                    </div>

                </div>

            </form>

        </main>
    </div>

</body>

<script src="assets/js/script.js" defer></script>
<script>
    // Catalog rendered from src/demo_orders.php — single source of truth
    // shared with the server-side validation above. TODO(db): this stays
    // exactly the same once the array comes from MariaDB.
    const COMPOUNDS = <?= json_encode(demo_catalog()) ?>;

    const TYPE_BLURBS = {
        A: '<strong>Type A — dose order.</strong> Request an activity at a specific date & time.',
        B: '<strong>Type B — cyclotron order.</strong> Specify beam current &times; time, or an EOB activity.',
    };

    const isotopeEl  = document.getElementById('isotope');
    const compoundEl = document.getElementById('compound');
    const metaEl     = document.getElementById('compound-meta');
    const deliveryEl = document.getElementById('delivery');
    const typeA      = document.getElementById('type-a');
    const typeB      = document.getElementById('type-b');
    const sections   = [document.getElementById('section-details'), document.getElementById('section-delivery')];

    function fillSelect(el, placeholder, options) {
        el.innerHTML = '<option value="">' + placeholder + '</option>' +
            options.map(o => '<option value="' + o + '">' + o + '</option>').join('');
    }

    // Earliest allowed datetime-local value: now + the compound's lead time.
    function minDatetime(leadHours) {
        const d = new Date(Date.now() + leadHours * 3600e3);
        d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
        return d.toISOString().slice(0, 16);
    }

    function hideDownstream() {
        metaEl.hidden = true;
        sections.forEach(s => s.hidden = true);
        typeA.hidden = true;
        typeB.hidden = true;
        deliveryEl.disabled = true;
        fillSelect(deliveryEl, 'Select delivery…', []);
    }

    isotopeEl.addEventListener('change', () => {
        const list = COMPOUNDS[isotopeEl.value] || [];
        compoundEl.disabled = !isotopeEl.value;
        fillSelect(compoundEl, isotopeEl.value ? 'Select compound…' : 'Select isotope first…',
            list.map(c => c.name));
        hideDownstream();
    });

    compoundEl.addEventListener('change', () => {
        const c = (COMPOUNDS[isotopeEl.value] || []).find(x => x.name === compoundEl.value);
        if (!c) { hideDownstream(); return; }

        metaEl.innerHTML = TYPE_BLURBS[c.type] + ' Minimum lead time: ' + c.leadHours + ' h.';
        metaEl.hidden = false;

        sections.forEach(s => s.hidden = false);
        typeA.hidden = c.type !== 'A';
        typeB.hidden = c.type !== 'B';

        const min = minDatetime(c.leadHours);
        document.getElementById('a-datetime').min = min;
        document.getElementById('b-datetime').min = min;

        deliveryEl.disabled = false;
        fillSelect(deliveryEl, 'Select delivery…', c.delivery);
    });

    // Type B: the two modes are mutually exclusive — disable the inactive
    // pair so its values never submit.
    document.querySelectorAll('input[name="b_mode"]').forEach(radio => {
        radio.addEventListener('change', () => {
            const beam = radio.value === 'beam';
            document.getElementById('b-current').disabled  = !beam;
            document.getElementById('b-time').disabled     = !beam;
            document.getElementById('b-activity').disabled = beam;
            document.getElementById('b-datetime').disabled = beam;
        });
    });
</script>

</html>
