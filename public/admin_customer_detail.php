<?php
require __DIR__ . '/../src/helpers.php';
bootstrap_session();
require __DIR__ . '/../src/auth.php';
require_role('admin');

$pdo = get_db();

/**
 * Single-use temp password: doesn't need to satisfy the full strength
 * policy (validate_password_strength()) since it's never kept -- the
 * account is forced to change it on first login. Same helper as
 * admin_registrations.php's approve action; not shared out to
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
                c.first_name, c.last_name, c.phone, c.lab_id, c.supervising_pi_id,
                c.registration_status, c.nrc_contact_name, c.nrc_contact_phone, c.nrc_contact_email,
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

function field_error(array $fieldErrors, string $key): string
{
    if (!isset($fieldErrors[$key])) {
        return '';
    }
    return '<span class="field-error">' . e($fieldErrors[$key]) . '</span>';
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
    'nrc_contact_name'   => '',
    'nrc_contact_phone'  => '',
    'nrc_contact_email'  => '',
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
        'nrc_contact_name'   => $customer['nrc_contact_name'] ?? '',
        'nrc_contact_phone'  => $customer['nrc_contact_phone'] ?? '',
        'nrc_contact_email'  => $customer['nrc_contact_email'] ?? '',
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
        }
        if ($editOld['last_name'] === '') {
            $fieldErrors['last_name'] = 'Last name is required.';
        }
        if ($editOld['phone'] === '') {
            $fieldErrors['phone'] = 'Phone is required.';
        } elseif (!preg_match('/^[0-9()+.\-\s]+$/', $editOld['phone']) || !preg_match('/[0-9]/', $editOld['phone'])) {
            $fieldErrors['phone'] = 'Phone must contain only digits, spaces, dashes, parentheses, and an optional leading +.';
        }
        if ($editOld['lab_id'] === '' || !ctype_digit($editOld['lab_id'])) {
            $fieldErrors['lab_id'] = 'Select a lab.';
        }
        if ($editOld['supervising_pi_id'] === '' || !ctype_digit($editOld['supervising_pi_id'])) {
            $fieldErrors['supervising_pi_id'] = 'Select a supervising PI.';
        }
        if ($editOld['nrc_contact_email'] !== '' && !filter_var($editOld['nrc_contact_email'], FILTER_VALIDATE_EMAIL)) {
            $fieldErrors['nrc_contact_email'] = 'NRC contact email must be a valid email address.';
        }

        if (!$fieldErrors) {
            $stmt = $pdo->prepare('SELECT 1 FROM labs WHERE lab_id = ?');
            $stmt->execute([$editOld['lab_id']]);
            if (!$stmt->fetchColumn()) {
                $fieldErrors['lab_id'] = 'Select a valid lab.';
            }
        }
        if (!$fieldErrors) {
            // Pairing check only -- not gated on active, since the admin
            // is intentionally allowed to keep/assign an inactive lab/PI
            // here (D.3 owns what "inactive" means for labs/PIs).
            $stmt = $pdo->prepare(
                'SELECT 1 FROM pis
                 JOIN lab_pis ON lab_pis.pi_id = pis.pi_id
                 WHERE pis.pi_id = ? AND lab_pis.lab_id = ?'
            );
            $stmt->execute([$editOld['supervising_pi_id'], $editOld['lab_id']]);
            if (!$stmt->fetchColumn()) {
                $fieldErrors['supervising_pi_id'] = 'Select a valid supervising PI for the chosen lab.';
            }
        }

        if (!$fieldErrors) {
            $pdo->prepare(
                'UPDATE customers
                 SET first_name = ?, last_name = ?, phone = ?, lab_id = ?, supervising_pi_id = ?,
                     nrc_contact_name = ?, nrc_contact_phone = ?, nrc_contact_email = ?
                 WHERE user_id = ?'
            )->execute([
                $editOld['first_name'],
                $editOld['last_name'],
                $editOld['phone'],
                (int) $editOld['lab_id'],
                (int) $editOld['supervising_pi_id'],
                $editOld['nrc_contact_name'] !== '' ? $editOld['nrc_contact_name'] : null,
                $editOld['nrc_contact_phone'] !== '' ? $editOld['nrc_contact_phone'] : null,
                $editOld['nrc_contact_email'] !== '' ? $editOld['nrc_contact_email'] : null,
                $userId,
            ]);

            $customer = fetch_customer($pdo, $userId);
            $editOld = reset_edit_old($customer);
            $flash = ['type' => 'success', 'message' => 'Customer updated.'];
        }
    } elseif ($action === 'toggle_active') {
        $newActive = $customer['active'] ? 0 : 1;
        $pdo->prepare('UPDATE users SET active = ? WHERE user_id = ?')->execute([$newActive, $userId]);
        $customer = fetch_customer($pdo, $userId);
        $flash = [
            'type' => 'success',
            'message' => $newActive
                ? 'Customer reactivated.'
                : 'Customer deactivated. They have been signed out and can no longer log in.',
        ];
    } elseif ($action === 'reset_password') {
        $tempPassword = generate_temp_password();
        $tempHash = password_hash($tempPassword, PASSWORD_BCRYPT);

        $pdo->prepare('UPDATE users SET password_hash = ?, must_change_password = 1 WHERE user_id = ?')
            ->execute([$tempHash, $userId]);

        // Seeds password_history with the temp password's own hash so it
        // can't be reused as the "new" password on the forced first
        // change (is_password_reused() checks current hash + this history).
        record_password_history($pdo, $userId, $tempHash);

        $tempPasswordReveal = $tempPassword;
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
    <?php include __DIR__ . '/../src/partials/head.php'; ?>
</head>
<body>
    <div class="app-shell">
        <?php include __DIR__ . '/../src/partials/layout_admin.php'; ?>
        <main class="app-main">
            <?php if ($customer === null): ?>
                <?php http_response_code(404); ?>
                <div class="page-header">
                    <h1>Customer not found</h1>
                </div>
                <div class="card">
                    <p class="muted">This customer doesn't exist.</p>
                    <a href="/admin_customers.php" class="btn btn--secondary">Back to Customers</a>
                </div>
            <?php else: ?>
                <div class="page-header">
                    <div>
                        <a href="/admin_customers.php" class="page-header__back mb-4">&larr; Back to Customers</a>
                        <span class="badge badge--<?= $customer['active'] ? 'active' : 'inactive' ?> page-header__status"><?= $customer['active'] ? 'Active' : 'Inactive' ?></span>
                        <h1><?= e($customer['first_name'] . ' ' . $customer['last_name']) ?></h1>
                    </div>
                </div>

                <?php if ($flash): ?>
                    <div class="alert alert--<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
                <?php endif; ?>

                <?php if ($tempPasswordReveal !== null): ?>
                    <div class="temp-password-banner">
                        <div class="temp-password-banner__heading">Temporary password generated</div>
                        <div>Give this to <?= e($customer['first_name'] . ' ' . $customer['last_name']) ?> via NIH email &mdash; it will not be shown again.</div>
                        <div class="temp-password-banner__password"><?= e($tempPasswordReveal) ?></div>
                        <div class="temp-password-banner__warning">Save this now. Leaving or refreshing this page will not bring it back.</div>
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
                    <form method="post" action="/admin_customer_detail.php?id=<?= (int) $userId ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="edit">

                        <div class="field-row">
                            <div class="field">
                                <label for="first_name">First name <span class="required-mark">*</span></label>
                                <input type="text" id="first_name" name="first_name" value="<?= e($editOld['first_name']) ?>" required>
                                <?= field_error($fieldErrors, 'first_name') ?>
                            </div>
                            <div class="field">
                                <label for="last_name">Last name <span class="required-mark">*</span></label>
                                <input type="text" id="last_name" name="last_name" value="<?= e($editOld['last_name']) ?>" required>
                                <?= field_error($fieldErrors, 'last_name') ?>
                            </div>
                        </div>

                        <div class="field">
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

                            <div class="field">
                                <label for="lab_id">Lab <span class="required-mark">*</span></label>
                                <select id="lab_id" name="lab_id" required>
                                    <option value="">Select institute first&hellip;</option>
                                    <?php foreach ($labs as $lab): ?>
                                        <option value="<?= (int) $lab['lab_id'] ?>" data-institute-id="<?= (int) $lab['institute_id'] ?>" <?= (string) $lab['lab_id'] === $editOld['lab_id'] ? 'selected' : '' ?>><?= e($lab['lab_name']) ?><?= (int) $lab['active'] === 0 ? ' (inactive)' : '' ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?= field_error($fieldErrors, 'lab_id') ?>
                            </div>

                            <div class="field mb-0">
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
                            <span class="form-section__title">NRC License Contact</span>

                            <div class="field">
                                <label for="nrc_contact_name">Contact name</label>
                                <input type="text" id="nrc_contact_name" name="nrc_contact_name" value="<?= e($editOld['nrc_contact_name']) ?>">
                            </div>

                            <div class="field-row mb-0">
                                <div class="field mb-0">
                                    <label for="nrc_contact_phone">Contact phone</label>
                                    <input type="text" id="nrc_contact_phone" name="nrc_contact_phone" value="<?= e($editOld['nrc_contact_phone']) ?>">
                                </div>
                                <div class="field mb-0">
                                    <label for="nrc_contact_email">Contact email</label>
                                    <input type="email" id="nrc_contact_email" name="nrc_contact_email" value="<?= e($editOld['nrc_contact_email']) ?>">
                                    <?= field_error($fieldErrors, 'nrc_contact_email') ?>
                                </div>
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
                        <form method="post" action="/admin_customer_detail.php?id=<?= (int) $userId ?>" onsubmit="return confirm('<?= $customer['active'] ? 'Deactivate this customer? They will be signed out immediately and unable to log in.' : 'Reactivate this customer? They will be able to log in again.' ?>');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="toggle_active">
                            <button type="submit" class="btn <?= $customer['active'] ? 'btn--danger' : 'btn--secondary' ?>"><?= $customer['active'] ? 'Deactivate Customer' : 'Reactivate Customer' ?></button>
                        </form>

                        <form method="post" action="/admin_customer_detail.php?id=<?= (int) $userId ?>" onsubmit="return confirm('Generate a new temporary password for this customer? Their current password will stop working immediately.');">
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
<script src="/assets/js/script.js" defer></script>
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
<?php endif; ?>
</html>
