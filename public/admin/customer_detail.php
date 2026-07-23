<?php
require __DIR__ . '/../../src/helpers.php';
bootstrap_session();
require __DIR__ . '/../../src/auth.php';
require_role('admin');

$pdo = get_db();

/**
 * Single-use temp password: doesn't need to satisfy the full strength
 * policy (validate_password_strength()) since it's never kept -- the
 * account is forced to change it on first login. Same helper as
 * registrations.php's approve action; not shared out to
 * src/helpers.php for two call sites.
 */
function generate_temp_password(): string
{
    return substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes(16))), 0, 16);
}

function fetch_customer(PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT u.user_id, u.username, u.active, u.created_at,
                u.first_name, u.last_name, u.phone, c.lab_id, c.supervising_pi_id,
                c.registration_status,
                l.institute_id, l.lab_name, i.name AS institute_name, p.pi_name
         FROM customers c
         JOIN users u ON u.user_id = c.user_id
         LEFT JOIN labs l ON l.lab_id = c.lab_id
         LEFT JOIN institutes i ON i.institute_id = l.institute_id
         LEFT JOIN pis p ON p.pi_id = c.supervising_pi_id
         WHERE c.user_id = ?'
    );
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    return $row !== false ? $row : null;
}

$userId = isset($_GET['id']) && ctype_digit((string) $_GET['id']) ? (int) $_GET['id'] : 0;
$customer = $userId > 0 ? fetch_customer($pdo, $userId) : null;

$flash = null;
$fieldErrors = [];
$tempPasswordReveal = null;

$editOld = [
    'first_name'         => '',
    'last_name'          => '',
    'phone'              => '',
    'institute_id'       => '',
    'lab_id'             => '',
    'supervising_pi_id'  => '',
];

function reset_edit_old(array $customer): array
{
    return [
        'first_name'         => $customer['first_name'],
        'last_name'          => $customer['last_name'],
        'phone'              => $customer['phone'] ?? '',
        'institute_id'       => $customer['institute_id'] !== null ? (string) $customer['institute_id'] : '',
        'lab_id'             => $customer['lab_id'] !== null ? (string) $customer['lab_id'] : '',
        'supervising_pi_id'  => $customer['supervising_pi_id'] !== null ? (string) $customer['supervising_pi_id'] : '',
    ];
}

if ($customer !== null) {
    $editOld = reset_edit_old($customer);
}

if ($customer !== null && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = $_POST['action'] ?? '';

    if ($action === 'edit') {
        foreach ($editOld as $key => $_) {
            $editOld[$key] = trim((string) ($_POST[$key] ?? ''));
        }

        if ($editOld['first_name'] === '') {
            $fieldErrors['first_name'] = 'First name is required.';
        } elseif (mb_strlen($editOld['first_name']) > 100) {
            $fieldErrors['first_name'] = 'First name must be 100 characters or fewer.';
        }
        if ($editOld['last_name'] === '') {
            $fieldErrors['last_name'] = 'Last name is required.';
        } elseif (mb_strlen($editOld['last_name']) > 100) {
            $fieldErrors['last_name'] = 'Last name must be 100 characters or fewer.';
        }
        if ($editOld['phone'] === '') {
            $fieldErrors['phone'] = 'Phone is required.';
        } elseif (!preg_match('/^[0-9()+.\-\s]+$/', $editOld['phone']) || !preg_match('/[0-9]/', $editOld['phone'])) {
            $fieldErrors['phone'] = 'Phone must contain only digits, spaces, dashes, parentheses, and an optional leading +.';
        } elseif (mb_strlen($editOld['phone']) > 20) {
            $fieldErrors['phone'] = 'Phone must be 20 characters or fewer.';
        }
        if ($editOld['lab_id'] === '' || !ctype_digit($editOld['lab_id'])) {
            $fieldErrors['lab_id'] = 'Select a lab.';
        }
        if ($editOld['supervising_pi_id'] === '' || !ctype_digit($editOld['supervising_pi_id'])) {
            $fieldErrors['supervising_pi_id'] = 'Select a supervising PI.';
        }

        // Settled rule (formerly deferred to the Directory pass): KEEPING
        // the customer's current lab+PI pair always saves -- an unrelated
        // name/phone edit must never be blocked by a since-deactivated
        // lab/PI or a since-removed lab_pis pairing. CHANGING either side
        // re-validates: a changed lab/PI must exist and be active, and the
        // resulting pair must exist in lab_pis. The dropdowns below
        // already offer only active options plus the current value.
        if (!$fieldErrors) {
            $currentLabId = $customer['lab_id'] !== null ? (int) $customer['lab_id'] : 0;
            $currentPiId = $customer['supervising_pi_id'] !== null ? (int) $customer['supervising_pi_id'] : 0;
            $newLabId = (int) $editOld['lab_id'];
            $newPiId = (int) $editOld['supervising_pi_id'];

            if ($newLabId !== $currentLabId) {
                $stmt = $pdo->prepare('SELECT 1 FROM labs WHERE lab_id = ? AND active = 1');
                $stmt->execute([$newLabId]);
                if (!$stmt->fetchColumn()) {
                    $fieldErrors['lab_id'] = 'Select an active lab.';
                }
            }
            if (!$fieldErrors && $newPiId !== $currentPiId) {
                $stmt = $pdo->prepare('SELECT 1 FROM pis WHERE pi_id = ? AND active = 1');
                $stmt->execute([$newPiId]);
                if (!$stmt->fetchColumn()) {
                    $fieldErrors['supervising_pi_id'] = 'Select an active supervising PI.';
                }
            }
            if (!$fieldErrors && ($newLabId !== $currentLabId || $newPiId !== $currentPiId)) {
                $stmt = $pdo->prepare('SELECT 1 FROM lab_pis WHERE pi_id = ? AND lab_id = ?');
                $stmt->execute([$newPiId, $newLabId]);
                if (!$stmt->fetchColumn()) {
                    $fieldErrors['supervising_pi_id'] = 'Select a valid supervising PI for the chosen lab.';
                }
            }
        }

        if ($fieldErrors && request_wants_json()) {
            json_response(['ok' => false, 'errors' => $fieldErrors], 422);
        }

        if (!$fieldErrors) {
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE users SET first_name = ?, last_name = ?, phone = ? WHERE user_id = ?')
                ->execute([$editOld['first_name'], $editOld['last_name'], $editOld['phone'], $userId]);
            $pdo->prepare(
                'UPDATE customers
                 SET lab_id = ?, supervising_pi_id = ?
                 WHERE user_id = ?'
            )->execute([
                (int) $editOld['lab_id'],
                (int) $editOld['supervising_pi_id'],
                $userId,
            ]);
            $pdo->commit();

            $customer = fetch_customer($pdo, $userId);
            $editOld = reset_edit_old($customer);
            $flash = ['type' => 'success', 'message' => 'Customer updated.'];
            // No redirect target -- same self-rendering shape as
            // account_detail.php's Edit Profile form.
            if (request_wants_json()) {
                json_response(['ok' => true, 'message' => $flash['message']]);
            }
        }
    } elseif ($action === 'toggle_active') {
        // No business-rule blocks here (unlike account_detail.php's
        // last-admin protection) -- a customer toggle always succeeds.
        $newActive = $customer['active'] ? 0 : 1;
        $pdo->prepare('UPDATE users SET active = ? WHERE user_id = ?')->execute([$newActive, $userId]);

        // PRG like every other converted action on this page -- no secret
        // to preserve here, so a plain arrival flag is enough (unlike
        // reset_password's session flash).
        $dest = '/admin/customer_detail.php?id=' . $userId . '&' . ($newActive ? 'reactivated=1' : 'deactivated=1');
        if (request_wants_json()) {
            json_response(['ok' => true, 'redirect' => $dest]);
        }
        redirect($dest);
    } elseif ($action === 'reset_password') {
        $tempPassword = generate_temp_password();
        $tempHash = password_hash($tempPassword, PASSWORD_BCRYPT);

        // Archive the outgoing hash so the pre-reset password still
        // counts toward the last-5 reuse check on the forced change.
        // The temp itself needs no history row: is_password_reused()
        // already rejects it via the current users.password_hash.
        $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE user_id = ?');
        $stmt->execute([$userId]);
        $outgoingHash = (string) $stmt->fetchColumn();

        // Also clears any lockout: login checks locked_until before the
        // password, so a fresh temp would otherwise stay unusable for
        // up to 15 minutes after a fumbled-old-password lockout.
        $pdo->prepare('UPDATE users SET password_hash = ?, must_change_password = 1, failed_login_count = 0, locked_until = NULL WHERE user_id = ?')
            ->execute([$tempHash, $userId]);

        record_password_history($pdo, $userId, $outgoingHash);

        // PRG like every other converted action on this page. The temp
        // password can't safely round-trip through a redirect URL, so
        // the reveal is session-flashed instead -- plaintext lives
        // server-side only, read-once with a short TTL, consumed by
        // the ?reset=1 arrival below. Same one-time-secret pattern as
        // accounts.php's New Account modal, distinct session key so
        // the two flashes can never collide.
        $_SESSION['password_reset_reveal'] = [
            'user_id'      => $userId,
            'tempPassword' => $tempPassword,
            'at'           => time(),
        ];

        $dest = '/admin/customer_detail.php?id=' . $userId . '&reset=1';
        if (request_wants_json()) {
            json_response(['ok' => true, 'redirect' => $dest]);
        }
        redirect($dest);
    }
}

// Server half of the arrival-flag convention (see accounts.php) -- the
// client half is petcomCleanArrivalFlags() near the bottom.
$arrival = consume_arrival_flags(['reset', 'reactivated', 'deactivated']);

// Consume the flash: cleared on ANY load that finds it (read-once
// hygiene), shown only on a fresh ?reset=1 arrival for the SAME
// customer the reveal was generated for -- guards against a stale
// flash bleeding into a different customer_detail.php?id=... visit.
if (isset($_SESSION['password_reset_reveal'])) {
    $reveal = $_SESSION['password_reset_reveal'];
    unset($_SESSION['password_reset_reveal']);
    if ($arrival['reset'] && (int) $reveal['user_id'] === $userId && time() - (int) $reveal['at'] <= 60) {
        $tempPasswordReveal = $reveal['tempPassword'];
    }
}

$currentInstituteId = $customer !== null && $customer['institute_id'] !== null ? (int) $customer['institute_id'] : 0;
$currentLabId = $customer !== null && $customer['lab_id'] !== null ? (int) $customer['lab_id'] : 0;
$currentPiId = $customer !== null && $customer['supervising_pi_id'] !== null ? (int) $customer['supervising_pi_id'] : 0;

// Dropdown options include the customer's current institute/lab/PI even
// if since deactivated, so opening this form never silently drops an
// existing assignment (labeled "(inactive)" below).
$institutes = [];
$labs = [];
$pis = [];
$piLabIds = [];

if ($customer !== null) {
    $stmt = $pdo->prepare('SELECT institute_id, name, active FROM institutes WHERE active = 1 OR institute_id = ? ORDER BY name');
    $stmt->execute([$currentInstituteId]);
    $institutes = $stmt->fetchAll();

    $stmt = $pdo->prepare('SELECT lab_id, institute_id, lab_name, active FROM labs WHERE active = 1 OR lab_id = ? ORDER BY lab_name');
    $stmt->execute([$currentLabId]);
    $labs = $stmt->fetchAll();

    $stmt = $pdo->prepare('SELECT pi_id, pi_name, active FROM pis WHERE active = 1 OR pi_id = ? ORDER BY pi_name');
    $stmt->execute([$currentPiId]);
    $pis = $stmt->fetchAll();

    // Unfiltered: a lab_pis row persists as long as neither side was
    // deleted (only ON DELETE CASCADE removes it), so pairing data for an
    // inactive lab/PI the customer is currently assigned to is still here.
    $labPiMap = $pdo->query('SELECT lab_id, pi_id FROM lab_pis')->fetchAll();
    foreach ($labPiMap as $row) {
        $piLabIds[$row['pi_id']][] = $row['lab_id'];
    }
}

$pageTitle = $customer !== null ? ($customer['first_name'] . ' ' . $customer['last_name']) : 'Customer not found';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include __DIR__ . '/../../src/partials/head.php'; ?>
</head>
<body>
    <div class="app-shell">
        <?php include __DIR__ . '/../../src/partials/layout_admin.php'; ?>
        <main class="app-main">
            <?php if ($customer === null): ?>
                <?php http_response_code(404); ?>
                <div class="page-header">
                    <h1>Customer not found</h1>
                </div>
                <div class="card">
                    <p class="muted">This customer doesn't exist.</p>
                    <a href="/admin/customers.php" class="btn btn--secondary">Back to Customers</a>
                </div>
            <?php else: ?>
                <div class="page-header">
                    <div>
                        <a href="/admin/customers.php" class="page-header__back mb-4">&larr; Back to Customers</a>
                        <span class="badge badge--<?= $customer['active'] ? 'active' : 'inactive' ?> page-header__status"><?= $customer['active'] ? 'Active' : 'Inactive' ?></span>
                        <h1><?= e($customer['first_name'] . ' ' . $customer['last_name']) ?></h1>
                    </div>
                </div>

                <?php if ($flash && $flash['type'] === 'success'): ?>
                    <?= toast_flash('success', $flash['message']) ?>
                <?php elseif ($flash): ?>
                    <div class="alert alert--<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
                <?php endif; ?>
                <?= $arrival['reactivated'] ? toast_flash('success', 'Customer reactivated.') : '' ?>
                <?= $arrival['deactivated'] ? toast_flash('success', 'Customer deactivated. They have been signed out and can no longer log in.') : '' ?>

                <?php if ($tempPasswordReveal !== null): ?>
                    <div class="temp-password-banner">
                        <div class="temp-password-banner__heading">Temporary password generated</div>
                        <div>Give this to <?= e($customer['first_name'] . ' ' . $customer['last_name']) ?> via NIH email &mdash; it will not be shown again.</div>
                        <div class="temp-password-banner__row">
                            <span class="temp-password-banner__password" id="temp-password-value"><?= e($tempPasswordReveal) ?></span>
                            <button type="button" class="btn btn--secondary btn--sm" data-copy-target="#temp-password-value">Copy</button>
                        </div>
                        <div class="temp-password-banner__warning">Copy it now &mdash; this password will not be shown again.</div>
                        <div class="mt-2">Missed it? Use Reset Password below to generate a new one.</div>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <span class="card__title">Account</span>
                    <div class="detail-list">
                        <div class="detail-list__row">
                            <span class="detail-list__label">Email (username)</span>
                            <span class="detail-list__value"><?= e($customer['username']) ?></span>
                        </div>
                        <div class="detail-list__row">
                            <span class="detail-list__label">Registered</span>
                            <span class="detail-list__value"><?= e(date('M j, Y g:i A', strtotime($customer['created_at']))) ?></span>
                        </div>
                        <div class="detail-list__row">
                            <span class="detail-list__label">Registration status</span>
                            <span class="detail-list__value"><?= e(ucfirst($customer['registration_status'])) ?></span>
                        </div>
                        <div class="detail-list__row">
                            <span class="detail-list__label">Account status</span>
                            <span class="detail-list__value"><?= $customer['active'] ? 'Active' : 'Inactive' ?></span>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <span class="card__title">Edit Details</span>
                    <form method="post" action="/admin/customer_detail.php?id=<?= (int) $userId ?>" id="edit-customer-form" novalidate data-ajax-submit>
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="edit">
                        <div class="alert alert--error" data-error-banner-for="edit-customer-form" <?= $fieldErrors ? '' : 'hidden' ?>>Please correct the errors below and resubmit.</div>

                        <div class="field-row">
                            <div class="<?= field_class($fieldErrors, 'first_name') ?>">
                                <label for="first_name">First name <span class="required-mark">*</span></label>
                                <input type="text" id="first_name" name="first_name" value="<?= e($editOld['first_name']) ?>" required>
                                <?= field_error($fieldErrors, 'first_name') ?>
                            </div>
                            <div class="<?= field_class($fieldErrors, 'last_name') ?>">
                                <label for="last_name">Last name <span class="required-mark">*</span></label>
                                <input type="text" id="last_name" name="last_name" value="<?= e($editOld['last_name']) ?>" required>
                                <?= field_error($fieldErrors, 'last_name') ?>
                            </div>
                        </div>

                        <div class="<?= field_class($fieldErrors, 'phone') ?>">
                            <label for="phone">Phone <span class="required-mark">*</span></label>
                            <input type="text" id="phone" name="phone" value="<?= e($editOld['phone']) ?>" required>
                            <?= field_error($fieldErrors, 'phone') ?>
                        </div>

                        <div class="form-section">
                            <span class="form-section__title">Institute &amp; Lab</span>

                            <div class="field">
                                <label for="institute_id">Institute</label>
                                <select id="institute_id" name="institute_id">
                                    <option value="">Select institute&hellip;</option>
                                    <?php foreach ($institutes as $institute): ?>
                                        <option value="<?= (int) $institute['institute_id'] ?>" <?= (string) $institute['institute_id'] === $editOld['institute_id'] ? 'selected' : '' ?>><?= e($institute['name']) ?><?= (int) $institute['active'] === 0 ? ' (inactive)' : '' ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="<?= field_class($fieldErrors, 'lab_id') ?>">
                                <label for="lab_id">Lab <span class="required-mark">*</span></label>
                                <select id="lab_id" name="lab_id" required>
                                    <option value="">Select institute first&hellip;</option>
                                    <?php foreach ($labs as $lab): ?>
                                        <option value="<?= (int) $lab['lab_id'] ?>" data-institute-id="<?= (int) $lab['institute_id'] ?>" <?= (string) $lab['lab_id'] === $editOld['lab_id'] ? 'selected' : '' ?>><?= e($lab['lab_name']) ?><?= (int) $lab['active'] === 0 ? ' (inactive)' : '' ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?= field_error($fieldErrors, 'lab_id') ?>
                            </div>

                            <div class="<?= field_class($fieldErrors, 'supervising_pi_id', 'field mb-0') ?>">
                                <label for="supervising_pi_id">Supervising PI <span class="required-mark">*</span></label>
                                <select id="supervising_pi_id" name="supervising_pi_id" required>
                                    <option value="">Select lab first&hellip;</option>
                                    <?php foreach ($pis as $pi): ?>
                                        <option value="<?= (int) $pi['pi_id'] ?>" data-lab-ids="<?= e(implode(' ', $piLabIds[$pi['pi_id']] ?? [])) ?>" <?= (string) $pi['pi_id'] === $editOld['supervising_pi_id'] ? 'selected' : '' ?>><?= e($pi['pi_name']) ?><?= (int) $pi['active'] === 0 ? ' (inactive)' : '' ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?= field_error($fieldErrors, 'supervising_pi_id') ?>
                            </div>
                        </div>

                        <div class="form-section">
                            <button type="submit" class="btn btn--primary">Save Changes</button>
                        </div>
                    </form>
                </div>

                <div class="card">
                    <span class="card__title">Account Actions</span>
                    <div class="flex gap-3">
                        <?php if ($customer['active']): ?>
                            <form method="post" action="/admin/customer_detail.php?id=<?= (int) $userId ?>" id="toggle-active-form" novalidate data-ajax-submit
                                  data-confirm="Deactivate <?= e($customer['first_name'] . ' ' . $customer['last_name']) ?>? They will be signed out immediately and unable to log in. Their order history stays intact."
                                  data-confirm-title="Deactivate customer"
                                  data-confirm-verb="Deactivate"
                                  data-confirm-danger>
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="toggle_active">
                                <button type="submit" class="btn btn--danger">Deactivate Customer</button>
                            </form>
                        <?php else: ?>
                            <form method="post" action="/admin/customer_detail.php?id=<?= (int) $userId ?>" id="toggle-active-form" novalidate data-ajax-submit
                                  data-confirm="Reactivate <?= e($customer['first_name'] . ' ' . $customer['last_name']) ?>? They will be able to log in again."
                                  data-confirm-title="Reactivate customer"
                                  data-confirm-verb="Reactivate">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="toggle_active">
                                <button type="submit" class="btn btn--secondary">Reactivate Customer</button>
                            </form>
                        <?php endif; ?>

                        <form method="post" action="/admin/customer_detail.php?id=<?= (int) $userId ?>" id="reset-password-form" novalidate data-ajax-submit
                              data-confirm="Generate a new temporary password for <?= e($customer['first_name'] . ' ' . $customer['last_name']) ?>? Their current password will stop working immediately."
                              data-confirm-title="Reset password"
                              data-confirm-verb="Reset password"
                              data-confirm-danger>
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="reset_password">
                            <button type="submit" class="btn btn--secondary">Reset Password</button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
<?php if ($customer !== null): ?>
<script>
(function () {
  var instituteSelect = document.getElementById('institute_id');
  var labSelect = document.getElementById('lab_id');
  var piSelect = document.getElementById('supervising_pi_id');
  if (!instituteSelect || !labSelect || !piSelect) return;

  var labOptions = Array.prototype.slice.call(labSelect.querySelectorAll('option[data-institute-id]'));
  var piOptions = Array.prototype.slice.call(piSelect.querySelectorAll('option[data-lab-ids]'));

  function filterLabs() {
    var instituteId = instituteSelect.value;
    labOptions.forEach(function (opt) {
      var matches = opt.dataset.instituteId === instituteId;
      opt.hidden = !matches;
      opt.disabled = !matches;
    });
    if (labSelect.selectedOptions[0] && labSelect.selectedOptions[0].hidden) {
      labSelect.value = '';
    }
    labSelect.disabled = !instituteId;
    filterPis();
  }

  function filterPis() {
    var labId = labSelect.value;
    piOptions.forEach(function (opt) {
      var labIds = opt.dataset.labIds ? opt.dataset.labIds.split(' ') : [];
      var matches = labIds.indexOf(labId) !== -1;
      opt.hidden = !matches;
      opt.disabled = !matches;
    });
    if (piSelect.selectedOptions[0] && piSelect.selectedOptions[0].hidden) {
      piSelect.value = '';
    }
    piSelect.disabled = !labId;
  }

  instituteSelect.addEventListener('change', filterLabs);
  labSelect.addEventListener('change', filterPis);
  filterLabs();
})();
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  window.petcomCleanArrivalFlags(['reset', 'reactivated', 'deactivated']);
});
</script>
<?php endif; ?>
</html>
