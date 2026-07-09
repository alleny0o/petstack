<?php
require __DIR__ . '/../src/helpers.php';
bootstrap_session();
require __DIR__ . '/../src/db.php';
require __DIR__ . '/../src/auth.php';

if (!empty($_SESSION['user_id']) && !empty($_SESSION['role'])) {
    redirect(dashboard_path_for_role($_SESSION['role']));
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $result = attempt_login($username, $password);

    if ($result['success']) {
        redirect(dashboard_path_for_role($_SESSION['role']));
    }

    $error = $result['reason'];
}

$pageTitle = 'Log In';
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
              <div class="auth-card__subtitle">Sign In</div>
            </div>
          </div>
        </div>
        <div class="auth-card__body">

          <?php if ($error): ?>
            <div class="alert alert--error"><?= e($error) ?></div>
          <?php endif; ?>

          <form method="post" novalidate>
            <?= csrf_field() ?>

            <div class="field">
              <label for="username">Username</label>
              <input type="text" id="username" name="username" value="<?= e($username) ?>" required autofocus>
            </div>

            <div class="field">
              <label for="password">Password</label>
              <input type="password" id="password" name="password" value="" required>
            </div>

            <button type="submit" class="btn btn--primary btn--block">Log In</button>
          </form>

        </div>
        <div class="auth-card__foot">
          <div>New customer? <a href="/register.php">Register here</a></div>
          <div>Already registered? <a href="/registration_status.php">Check your status</a></div>
        </div>
      </div>
    </div>
</body>
<script src="/assets/js/script.js" defer></script>
</html>
