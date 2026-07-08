<?php
require __DIR__ . '/../src/helpers.php';
bootstrap_session();
require __DIR__ . '/../src/db.php';
require __DIR__ . '/../src/auth.php';

if (!empty($_SESSION['user_id']) && !empty($_SESSION['role'])) {
    redirect(dashboard_path_for_role($_SESSION['role']));
}

$errors = [];
$submitted = false;
$old = [
    'institute_id'      => '',
    'lab_id'            => '',
    'first_name'        => '',
    'last_name'         => '',
    'email'             => '',
    'phone'             => '',
    'pi_id'             => '',
    'nrc_contact_name'  => '',
    'nrc_contact_phone' => '',
    'nrc_contact_email' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $old['institute_id']      = $_POST['institute_id'] ?? '';
    $old['lab_id']            = $_POST['lab_id'] ?? '';
    $old['first_name']        = trim($_POST['first_name'] ?? '');
    $old['last_name']         = trim($_POST['last_name'] ?? '');
    $old['email']             = trim($_POST['email'] ?? '');
    $old['phone']             = trim($_POST['phone'] ?? '');
    $old['pi_id']             = $_POST['pi_id'] ?? '';
    $old['nrc_contact_name']  = trim($_POST['nrc_contact_name'] ?? '');
    $old['nrc_contact_phone'] = trim($_POST['nrc_contact_phone'] ?? '');
    $old['nrc_contact_email'] = trim($_POST['nrc_contact_email'] ?? '');

    if ($old['institute_id'] === '') {
        $errors[] = 'Institute is required.';
    }
    if ($old['lab_id'] === '') {
        $errors[] = 'Lab is required.';
    }
    if ($old['first_name'] === '') {
        $errors[] = 'First name is required.';
    }
    if ($old['last_name'] === '') {
        $errors[] = 'Last name is required.';
    }
    if ($old['email'] === '' || !filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid email is required.';
    } elseif (!preg_match('/@nih\.gov$/i', $old['email'])) {
        $errors[] = 'Email must be an @nih.gov address.';
    }
    if ($old['phone'] === '') {
        $errors[] = 'Phone is required.';
    } elseif (!preg_match('/^[0-9()+.\-\s]+$/', $old['phone']) || !preg_match('/[0-9]/', $old['phone'])) {
        $errors[] = 'Phone must contain only digits, spaces, dashes, parentheses, and an optional leading +.';
    }
    if ($old['pi_id'] === '') {
        $errors[] = 'Supervising PI is required.';
    }
    // NRC contact fields are only relevant for shipping orders (per the
    // original requirements interview), so they're optional at
    // registration time — collected now for convenience, not required.
    if ($old['nrc_contact_email'] !== '' && !filter_var($old['nrc_contact_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'NRC contact email must be a valid email address.';
    }

    $pdo = get_db();

    if (!$errors) {
        $stmt = $pdo->prepare('SELECT 1 FROM institutes WHERE institute_id = ? AND active = 1');
        $stmt->execute([$old['institute_id']]);
        if (!$stmt->fetchColumn()) {
            $errors[] = 'Select a valid institute.';
        }
    }
    if (!$errors) {
        $stmt = $pdo->prepare('SELECT 1 FROM labs WHERE lab_id = ? AND institute_id = ? AND active = 1');
        $stmt->execute([$old['lab_id'], $old['institute_id']]);
        if (!$stmt->fetchColumn()) {
            $errors[] = 'Select a valid lab for the chosen institute.';
        }
    }
    if (!$errors) {
        $stmt = $pdo->prepare('SELECT 1 FROM pis WHERE pi_id = ? AND active = 1');
        $stmt->execute([$old['pi_id']]);
        if (!$stmt->fetchColumn()) {
            $errors[] = 'Select a valid supervising PI.';
        }
    }
    if (!$errors) {
        $stmt = $pdo->prepare('SELECT 1 FROM users WHERE username = ?');
        $stmt->execute([$old['email']]);
        if ($stmt->fetchColumn()) {
            $errors[] = 'An account with this email already exists.';
        }
    }

    if (!$errors) {
        // Unusable placeholder: a bcrypt hash of a random string that's
        // discarded immediately. password_verify() can never match it,
        // so this account can't log in until an admin approves the
        // registration and issues a temp password (CLAUDE.md: no email
        // from the app, admins relay resets manually).
        $placeholderHash = password_hash(bin2hex(random_bytes(32)), PASSWORD_BCRYPT);

        $pdo->beginTransaction();
        try {
            $pdo->prepare('INSERT INTO users (username, password_hash, must_change_password, active) VALUES (?, ?, 1, 1)')
                ->execute([$old['email'], $placeholderHash]);
            $userId = (int) $pdo->lastInsertId();

            $pdo->prepare(
                'INSERT INTO customers
                    (user_id, first_name, last_name, phone, lab_id, supervising_pi_id,
                     registration_status, nrc_contact_name, nrc_contact_phone, nrc_contact_email)
                 VALUES (?, ?, ?, ?, ?, ?, \'pending\', ?, ?, ?)'
            )->execute([
                $userId,
                $old['first_name'],
                $old['last_name'],
                $old['phone'],
                $old['lab_id'],
                $old['pi_id'],
                $old['nrc_contact_name'] !== '' ? $old['nrc_contact_name'] : null,
                $old['nrc_contact_phone'] !== '' ? $old['nrc_contact_phone'] : null,
                $old['nrc_contact_email'] !== '' ? $old['nrc_contact_email'] : null,
            ]);

            $pdo->commit();
            $submitted = true;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}

$pdo = get_db();
$institutes = $pdo->query('SELECT institute_id, name FROM institutes WHERE active = 1 ORDER BY name')->fetchAll();
$labs = $pdo->query('SELECT lab_id, institute_id, lab_name FROM labs WHERE active = 1 ORDER BY lab_name')->fetchAll();
$pis = $pdo->query('SELECT pi_id, pi_name FROM pis WHERE active = 1 ORDER BY pi_name')->fetchAll();

$pageTitle = 'Register';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include __DIR__ . '/../src/partials/head.php'; ?>
</head>
<body>
    <div class="auth-wrap">
      <div class="auth-card auth-card--wide">
        <div class="auth-card__head">
          <div class="auth-card__brand">
            <div class="auth-card__logo">
              <img src="/favicons/android-chrome-192x192.png" alt="PETCOM">
            </div>
            <div>
              <div class="auth-card__title">PETCOM</div>
              <div class="auth-card__subtitle">Customer Registration</div>
            </div>
          </div>
        </div>
        <div class="auth-card__body">

          <?php if ($submitted): ?>
            <div class="alert alert--success">Registration submitted. An administrator will review your request and contact you.</div>
          <?php else: ?>

            <?php if ($errors): ?>
              <div class="alert alert--error">
                <?php foreach ($errors as $error): ?>
                  <div><?= e($error) ?></div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

            <form method="post" novalidate>
              <?= csrf_field() ?>

              <div class="form-section">
                <span class="form-section__title">Institute &amp; Lab</span>

                <div class="field">
                  <label for="institute_id">Institute <span class="required-mark">*</span></label>
                  <select id="institute_id" name="institute_id" required>
                    <option value="">Select institute…</option>
                    <?php foreach ($institutes as $institute): ?>
                      <option value="<?= (int) $institute['institute_id'] ?>" <?= (string) $institute['institute_id'] === $old['institute_id'] ? 'selected' : '' ?>><?= e($institute['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="field mb-0">
                  <label for="lab_id">Lab <span class="required-mark">*</span></label>
                  <select id="lab_id" name="lab_id" required>
                    <option value="">Select institute first…</option>
                    <?php foreach ($labs as $lab): ?>
                      <option value="<?= (int) $lab['lab_id'] ?>" data-institute-id="<?= (int) $lab['institute_id'] ?>" <?= (string) $lab['lab_id'] === $old['lab_id'] ? 'selected' : '' ?>><?= e($lab['lab_name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>

              <div class="form-section">
                <span class="form-section__title">Investigator</span>

                <div class="field-row">
                  <div class="field">
                    <label for="first_name">First name <span class="required-mark">*</span></label>
                    <input type="text" id="first_name" name="first_name" value="<?= e($old['first_name']) ?>" required>
                  </div>
                  <div class="field">
                    <label for="last_name">Last name <span class="required-mark">*</span></label>
                    <input type="text" id="last_name" name="last_name" value="<?= e($old['last_name']) ?>" required>
                  </div>
                </div>

                <div class="field-row">
                  <div class="field">
                    <label for="email">Email <span class="required-mark">*</span></label>
                    <input type="email" id="email" name="email" value="<?= e($old['email']) ?>" required>
                    <span class="field-hint">This becomes your username.</span>
                  </div>
                  <div class="field">
                    <label for="phone">Phone <span class="required-mark">*</span></label>
                    <input type="text" id="phone" name="phone" value="<?= e($old['phone']) ?>" required>
                  </div>
                </div>

                <div class="field mb-0">
                  <label for="pi_id">Supervising PI <span class="required-mark">*</span></label>
                  <select id="pi_id" name="pi_id" required>
                    <option value="">Select PI…</option>
                    <?php foreach ($pis as $pi): ?>
                      <option value="<?= (int) $pi['pi_id'] ?>" <?= (string) $pi['pi_id'] === $old['pi_id'] ? 'selected' : '' ?>><?= e($pi['pi_name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <span class="field-hint">Don't see your PI? Contact an admin.</span>
                </div>
              </div>

              <div class="form-section">
                <span class="form-section__title">NRC License Contact <span class="muted" style="text-transform:none; letter-spacing:0; font-weight:400;">— shipping orders only, optional</span></span>

                <div class="field">
                  <label for="nrc_contact_name">Contact name</label>
                  <input type="text" id="nrc_contact_name" name="nrc_contact_name" value="<?= e($old['nrc_contact_name']) ?>">
                </div>

                <div class="field-row mb-0">
                  <div class="field mb-0">
                    <label for="nrc_contact_phone">Contact phone</label>
                    <input type="text" id="nrc_contact_phone" name="nrc_contact_phone" value="<?= e($old['nrc_contact_phone']) ?>">
                  </div>
                  <div class="field mb-0">
                    <label for="nrc_contact_email">Contact email</label>
                    <input type="email" id="nrc_contact_email" name="nrc_contact_email" value="<?= e($old['nrc_contact_email']) ?>">
                  </div>
                </div>
              </div>

              <button type="submit" class="btn btn--primary btn--block">Submit Registration</button>
            </form>

          <?php endif; ?>

        </div>
        <div class="auth-card__foot">
          Already have an account? <a href="/login.php">Log in</a>
        </div>
      </div>
    </div>
</body>
<script src="/assets/js/script.js" defer></script>
<script>
(function () {
  var instituteSelect = document.getElementById('institute_id');
  var labSelect = document.getElementById('lab_id');
  if (!instituteSelect || !labSelect) return;

  var labOptions = Array.prototype.slice.call(labSelect.querySelectorAll('option[data-institute-id]'));

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
  }

  instituteSelect.addEventListener('change', filterLabs);
  filterLabs();
})();
</script>
</html>