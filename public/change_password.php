<?php
require __DIR__ . '/../src/helpers.php';
bootstrap_session();
require __DIR__ . '/../src/db.php';
require __DIR__ . '/../src/auth.php';
require_role(['customer', 'staff', 'admin']);

$fieldErrors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword     = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT username, password_hash FROM users WHERE user_id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $row = $stmt->fetch();
    $username = $row['username'];
    $currentHash = $row['password_hash'];

    if (!password_verify($currentPassword, $currentHash)) {
        $fieldErrors['current_password'] = 'Current password is incorrect.';
    }

    $strengthErrors = validate_password_strength($newPassword, $username);
    if ($strengthErrors) {
        $fieldErrors['new_password'] = $strengthErrors;
    }

    if ($newPassword !== $confirmPassword) {
        $fieldErrors['confirm_password'] = 'New password and confirmation do not match.';
    }

    if (!$fieldErrors && is_password_reused($pdo, (int) $_SESSION['user_id'], $currentHash, $newPassword)) {
        $fieldErrors['new_password'] = 'New password must not match any of your last 5 passwords.';
    }

    if (!$fieldErrors) {
        record_password_history($pdo, (int) $_SESSION['user_id'], $currentHash);

        $pdo->prepare('UPDATE users SET password_hash = ?, must_change_password = 0 WHERE user_id = ?')
            ->execute([password_hash($newPassword, PASSWORD_BCRYPT), $_SESSION['user_id']]);

        $_SESSION['must_change_password'] = false;

        redirect(dashboard_path_for_role($_SESSION['role']));
    }
}

$pageTitle = 'Change Password';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include __DIR__ . '/../src/partials/head.php'; ?>
</head>
<body>
    <div class="auth-wrap">
      <div class="auth-card">
        <div class="auth-card__head">
          <div class="auth-card__brand">
            <div class="auth-card__logo">
              <img src="/favicons/android-chrome-192x192.png" alt="PETCOM">
            </div>
            <div>
              <div class="auth-card__title">PETCOM</div>
              <div class="auth-card__subtitle">Change Password</div>
            </div>
          </div>
        </div>
        <div class="auth-card__body">

          <?php if (!empty($_SESSION['must_change_password'])): ?>
            <div class="alert alert--info">You're signed in with a temporary password. Set a new one to continue.</div>
          <?php endif; ?>

          <form method="post" novalidate>
            <?= csrf_field() ?>

            <div class="<?= field_class($fieldErrors, 'current_password') ?>">
              <label for="current_password">Current password</label>
              <input type="password" id="current_password" name="current_password" required autofocus>
              <?= field_error($fieldErrors, 'current_password') ?>
            </div>

            <div class="<?= field_class($fieldErrors, 'new_password') ?>">
              <label for="new_password">New password</label>
              <input type="password" id="new_password" name="new_password" required minlength="12">
              <span class="field-hint">At least 12 characters, with a letter and a number. Must not contain your username or email.</span>
              <?= field_error($fieldErrors, 'new_password') ?>
            </div>

            <div class="<?= field_class($fieldErrors, 'confirm_password') ?>">
              <label for="confirm_password">Confirm new password</label>
              <input type="password" id="confirm_password" name="confirm_password" required>
              <?= field_error($fieldErrors, 'confirm_password') ?>
            </div>

            <button type="submit" class="btn btn--primary btn--lg btn--block">Change Password</button>
          </form>

        </div>
      </div>
    </div>
</body>
<script src="/assets/js/script.js" defer></script>
</html>
