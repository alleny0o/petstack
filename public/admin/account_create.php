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
 * registrations.php / customer_detail.php / account_detail.php; not
 * shared out to src/helpers.php.
 */
function generate_temp_password(): string
{
    return substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes(16))), 0, 16);
}

/**
 * The Administration category exists solely as a cosmetic placeholder
 * for staff.category_id on admin accounts (NOT NULL constraint, but
 * admins bypass category restrictions in the app) -- an Admin account's
 * category is always this, looked up by name rather than a hardcoded id.
 */
function administration_category_id(PDO $pdo): int
{
    return (int) $pdo->query("SELECT category_id FROM categories WHERE category_name = 'Administration'")->fetchColumn();
}

$fieldErrors = [];
$successReveal = null;

$old = [
    'email'       => '',
    'role'        => 'staff',
    'category_id' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $old['email'] = trim($_POST['email'] ?? '');
    $old['role'] = ($_POST['role'] ?? '') === 'admin' ? 'admin' : 'staff';
    $old['category_id'] = trim((string) ($_POST['category_id'] ?? ''));

    if ($old['email'] === '' || !filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $fieldErrors['email'] = 'A valid email is required.';
    } elseif (!preg_match('/@nih\.gov$/i', $old['email'])) {
        $fieldErrors['email'] = 'Email must be an @nih.gov address.';
    }

    $categoryId = null;
    if ($old['role'] === 'admin') {
        $categoryId = administration_category_id($pdo);
    } else {
        if ($old['category_id'] === '' || !ctype_digit($old['category_id'])) {
            $fieldErrors['category_id'] = 'Select a category.';
        } elseif ((int) $old['category_id'] === administration_category_id($pdo)) {
            $fieldErrors['category_id'] = 'Administration is reserved for admin accounts.';
        } else {
            $stmt = $pdo->prepare('SELECT 1 FROM categories WHERE category_id = ?');
            $stmt->execute([$old['category_id']]);
            if (!$stmt->fetchColumn()) {
                $fieldErrors['category_id'] = 'Select a valid category.';
            } else {
                $categoryId = (int) $old['category_id'];
            }
        }
    }

    if (!$fieldErrors) {
        // Pre-check, same convention as register.php -- the transaction's
        // catch block below is the race-condition backstop, same as
        // registrations.php's approve action.
        $stmt = $pdo->prepare('SELECT 1 FROM users WHERE username = ?');
        $stmt->execute([$old['email']]);
        if ($stmt->fetchColumn()) {
            $fieldErrors['email'] = 'An account already exists for this email.';
        }
    }

    if (!$fieldErrors) {
        $pdo->beginTransaction();
        try {
            $tempPassword = generate_temp_password();
            $tempHash = password_hash($tempPassword, PASSWORD_BCRYPT);

            $pdo->prepare(
                'INSERT INTO users (username, password_hash, must_change_password, active) VALUES (?, ?, 1, 1)'
            )->execute([$old['email'], $tempHash]);
            $newUserId = (int) $pdo->lastInsertId();

            $pdo->prepare('INSERT INTO staff (user_id, category_id) VALUES (?, ?)')
                ->execute([$newUserId, $categoryId]);

            if ($old['role'] === 'admin') {
                $pdo->prepare('INSERT INTO admins (user_id) VALUES (?)')->execute([$newUserId]);
            }

            // No password_history seeding: the temp can't be reused as
            // the "new" password anyway (is_password_reused() checks the
            // current users.password_hash), and history holds outgoing
            // hashes only.

            $pdo->commit();

            $successReveal = [
                'user_id'      => $newUserId,
                'email'        => $old['email'],
                'role'         => $old['role'],
                'tempPassword' => $tempPassword,
            ];

            $old = ['email' => '', 'role' => 'staff', 'category_id' => ''];
        } catch (PDOException $e) {
            $pdo->rollBack();
            $fieldErrors['email'] = 'Could not create the account. An account for this email may already exist.';
        }
    }
}

// Category dropdown excludes Administration -- reserved for the cosmetic
// admin case, assigned automatically server-side and never user-picked.
$categories = $pdo->query(
    "SELECT category_id, category_name FROM categories WHERE category_name != 'Administration' ORDER BY category_name"
)->fetchAll();

$pageTitle = 'New Account';
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
            <div class="page-header">
                <div>
                    <a href="/admin/accounts.php" class="page-header__back mb-4">&larr; Back to Accounts</a>
                    <h1>New Account</h1>
                </div>
            </div>

            <?php if ($successReveal !== null): ?>
                <div class="temp-password-banner">
                    <div class="temp-password-banner__heading"><?= $successReveal['role'] === 'admin' ? 'Admin' : 'Staff' ?> account created for <?= e($successReveal['email']) ?></div>
                    <div>Relay this temporary password via NIH email &mdash; it will not be shown again.</div>
                    <div class="temp-password-banner__row">
                        <span class="temp-password-banner__password" id="temp-password-value"><?= e($successReveal['tempPassword']) ?></span>
                        <button type="button" class="btn btn--secondary btn--sm" data-copy-target="#temp-password-value">Copy</button>
                    </div>
                    <div class="temp-password-banner__warning">Save this now. Leaving or refreshing this page will not bring it back.</div>
                    <div class="mt-2"><a href="/admin/account_detail.php?id=<?= (int) $successReveal['user_id'] ?>">View the new account &rarr;</a></div>
                </div>
            <?php endif; ?>

            <div class="card">
                <span class="card__title">Account Details</span>
                <form method="post" action="/admin/account_create.php">
                    <?= csrf_field() ?>

                    <div class="<?= field_class($fieldErrors, 'email') ?>">
                        <label for="email">Email <span class="required-mark">*</span></label>
                        <input type="email" id="email" name="email" value="<?= e($old['email']) ?>" required>
                        <span class="field-hint">Must be an @nih.gov address &mdash; it becomes their username.</span>
                        <?= field_error($fieldErrors, 'email') ?>
                    </div>

                    <div class="field">
                        <span class="form-section__title">Role <span class="required-mark">*</span></span>
                        <div class="radio-card-group">
                            <label class="radio-card">
                                <input type="radio" name="role" value="staff" id="role_staff" <?= $old['role'] === 'staff' ? 'checked' : '' ?>>
                                <span class="radio-card__title">Staff</span>
                                <span class="radio-card__desc">Processes orders in one assigned category</span>
                            </label>
                            <label class="radio-card">
                                <input type="radio" name="role" value="admin" id="role_admin" <?= $old['role'] === 'admin' ? 'checked' : '' ?>>
                                <span class="radio-card__title">Admin</span>
                                <span class="radio-card__desc">Everything staff can do, plus management &amp; approvals</span>
                            </label>
                        </div>
                    </div>

                    <div class="<?= field_class($fieldErrors, 'category_id', 'field mb-0') ?>" id="category_field">
                        <label for="category_id">Category <span class="required-mark">*</span></label>
                        <select id="category_id" name="category_id">
                            <option value="">Select category&hellip;</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= (int) $category['category_id'] ?>" <?= (string) $category['category_id'] === $old['category_id'] ? 'selected' : '' ?>><?= e($category['category_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?= field_error($fieldErrors, 'category_id') ?>
                    </div>

                    <div class="form-section">
                        <button type="submit" class="btn btn--primary">Create Account</button>
                    </div>
                </form>
            </div>
        </main>
    </div>
</body>
<script src="/assets/js/script.js" defer></script>
<script>
(function () {
  var staffRadio = document.getElementById('role_staff');
  var adminRadio = document.getElementById('role_admin');
  var categoryField = document.getElementById('category_field');
  var categorySelect = document.getElementById('category_id');
  if (!staffRadio || !adminRadio || !categoryField || !categorySelect) return;

  function updateCategoryField() {
    var isAdmin = adminRadio.checked;
    categoryField.hidden = isAdmin;
    categorySelect.required = !isAdmin;
    categorySelect.disabled = isAdmin;
  }

  staffRadio.addEventListener('change', updateCategoryField);
  adminRadio.addEventListener('change', updateCategoryField);
  updateCategoryField();
})();
</script>
</html>
