<?php
require __DIR__ . '/../src/helpers.php';
bootstrap_session();
require __DIR__ . '/../src/db.php';
require __DIR__ . '/../src/auth.php';

http_response_code(404);

if (!empty($_SESSION['user_id']) && !empty($_SESSION['role'])) {
  $homeHref = dashboard_path_for_role($_SESSION['role']);
  $homeLabel = 'Back to Dashboard';
} else {
  $homeHref = '/login.php';
  $homeLabel = 'Back to Login';
}

$pageTitle = 'Page Not Found';
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
            <img src="/favicons/android-chrome-192x192.png" alt="<?= e(app_setting('app_name')) ?>">
          </div>
          <div>
            <div class="auth-card__title"><?= e(app_setting('app_name')) ?></div>
          </div>
        </div>
      </div>
      <div class="auth-card__body">
        <div class="empty-state">
          <div class="empty-state__icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="12" r="10"></circle>
              <line x1="9" y1="9" x2="15" y2="15"></line>
              <line x1="15" y1="9" x2="9" y2="15"></line>
            </svg>
          </div>
          <div class="empty-state__title">Page not found</div>
          <p class="empty-state__hint">The page you're looking for doesn't exist or may have been moved.</p>
          <div class="empty-state__action">
            <a href="<?= e($homeHref) ?>" class="btn btn--primary"><?= e($homeLabel) ?></a>
          </div>
        </div>
      </div>
    </div>
  </div>
</body>
<script src="<?= asset_url('/assets/js/script.js') ?>" defer></script>

</html>
