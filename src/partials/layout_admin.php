<?php
$accountStmt = get_db()->prepare('SELECT first_name, last_name FROM users WHERE user_id = ?');
$accountStmt->execute([(int) $_SESSION['user_id']]);
$accountRow = $accountStmt->fetch();
$accountName = $accountRow['first_name'] . ' ' . $accountRow['last_name'];
$accountInitials = implode('', array_map(
    fn($w) => mb_substr($w, 0, 1),
    array_slice(explode(' ', $accountName), 0, 2)
));
$currentPage = basename($_SERVER['PHP_SELF'], '.php');

// The profile-edit modal below always redirects back here, tagging the
// outcome via a query flag (no session-flash mechanism in this app —
// mirrors the ?placed=1 pattern in customer/order_detail.php).
if (($_GET['profile_updated'] ?? null) === '1') {
    echo toast_flash('success', 'Profile updated.');
} elseif (($_GET['profile_error'] ?? null) === '1') {
    echo toast_flash('error', 'First and last name are required.');
}

// The three account-workflow pages now live under one expandable
// "Accounts" parent — expand it server-side when we're already on one
// of them, so the correct state renders on first paint with no JS.
$accountsChildPages = ['registrations', 'customers', 'customer_detail', 'accounts', 'account_detail', 'account_create'];
$accountsSectionActive = in_array($currentPage, $accountsChildPages, true);
?>
<!-- App topbar: always present (see layout/sidebar.css). The
     hamburger button inside it is the only mobile-specific part. -->
<div class="app-topbar u-mobile-only">
  <button class="hamburger-toggle" type="button" aria-label="Open menu">
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
      <line x1="3" y1="6" x2="21" y2="6"></line>
      <line x1="3" y1="12" x2="21" y2="12"></line>
      <line x1="3" y1="18" x2="21" y2="18"></line>
    </svg>
  </button>
</div>

<!-- Backdrop: only shown while the mobile sidebar is open -->
<div class="sidebar-backdrop"></div>

<aside class="sidebar">
  <!-- Sidebar Header -->
  <header class="sidebar-header">

    <div class="sidebar-logo"><img src="/favicons/android-chrome-192x192.png" alt="PETCOM"></div>
    <button class="sidebar-toggle">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <polyline points="15 18 9 12 15 6"></polyline>
      </svg>
    </button>

  </header>

  <!-- Sidebar Content -->
  <div class="sidebar-content">
    <nav class="sidebar-nav">
      <ul class="menu-list">

        <li class="menu-item">
          <a href="/admin/dashboard.php" class="menu-link <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="3" y="3" width="7" height="7"></rect>
              <rect x="14" y="3" width="7" height="7"></rect>
              <rect x="14" y="14" width="7" height="7"></rect>
              <rect x="3" y="14" width="7" height="7"></rect>
            </svg>
            <span class="menu-label"><span class="menu-label__text">Dashboard</span></span>
          </a>
        </li>

        <li class="menu-item menu-item--has-submenu <?= $accountsSectionActive ? 'is-expanded' : '' ?>">
          <button type="button" class="menu-link <?= $accountsSectionActive ? 'menu-link--section-active' : '' ?>" aria-expanded="<?= $accountsSectionActive ? 'true' : 'false' ?>" aria-controls="accounts-submenu">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
              <circle cx="9" cy="7" r="4"></circle>
              <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
              <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
            </svg>
            <span class="menu-label"><span class="menu-label__text">Accounts</span></span>
            <svg class="menu-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polyline points="6 9 12 15 18 9"></polyline>
            </svg>
          </button>
          <ul class="submenu" id="accounts-submenu">
            <li>
              <a href="/admin/registrations.php" class="submenu-link <?= $currentPage === 'registrations' ? 'active' : '' ?>">Pending Registrations</a>
            </li>
            <li>
              <a href="/admin/customers.php" class="submenu-link <?= in_array($currentPage, ['customers', 'customer_detail'], true) ? 'active' : '' ?>">Customers</a>
            </li>
            <li>
              <a href="/admin/accounts.php" class="submenu-link <?= in_array($currentPage, ['accounts', 'account_detail', 'account_create'], true) ? 'active' : '' ?>">Staff &amp; Admins</a>
            </li>
          </ul>
        </li>

        <li class="menu-item">
          <a href="/admin/catalog-main.php" class="menu-link <?= $currentPage === 'catalog-main' ? 'active' : '' ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
              <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
              <line x1="12" y1="22.08" x2="12" y2="12"></line>
            </svg>
            <span class="menu-label"><span class="menu-label__text">Catalog Config</span></span>
          </a>
        </li>

        <li class="Reports">
          <a href="/admin/dashboard.php" class="menu-link <?= $currentPage === 'reports' ? 'active' : '' ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="3" y="3" width="7" height="7"></rect>
              <rect x="14" y="3" width="7" height="7"></rect>
              <rect x="14" y="14" width="7" height="7"></rect>
              <rect x="3" y="14" width="7" height="7"></rect>
            </svg>
            <span class="menu-label"><span class="menu-label__text">Reports</span></span>
          </a>
        </li>

      </ul>
    </nav>
  </div>

  <?php if (($_SESSION['role'] ?? null) === 'admin'): ?>
  <div class="sidebar-mode-toggle">
    <a href="/admin/dashboard.php" class="sidebar-mode-toggle__option is-active">Admin</a>
    <a href="/staff/dashboard.php" class="sidebar-mode-toggle__option">Staff</a>
  </div>
  <?php endif; ?>

  <!-- Sidebar Footer -->
  <div class="sidebar-footer">
    <button type="button" class="sidebar-account" id="profile-edit-trigger" aria-haspopup="dialog">
      <div class="account-avatar"><?= htmlspecialchars($accountInitials) ?></div>
      <span class="account-name"><?= htmlspecialchars($accountName) ?></span>
    </button>

    <div class="sidebar-footer-actions">
      <a href="/logout.php" class="logout-link">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
          <polyline points="16 17 21 12 16 7"></polyline>
          <line x1="21" y1="12" x2="9" y2="12"></line>
        </svg>
      </a>
    </div>
  </div>
</aside>

<!-- Profile edit modal: self-service first/last name edit, opened from
     the sidebar account block above. Separate from admin/account_detail.php,
     which edits *other* accounts -- this always targets $_SESSION['user_id']. -->
<div class="modal-overlay" id="profile-edit-modal" hidden>
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="profile-edit-modal-title">
    <form method="post" action="/account_profile.php">
      <?= csrf_field() ?>
      <input type="hidden" name="redirect_to" id="profile-redirect-to" value="">
      <div class="modal__body">
        <h2 class="modal__title" id="profile-edit-modal-title">Edit profile</h2>
        <div class="field-row">
          <div class="field">
            <label for="profile-first-name">First name <span class="required-mark">*</span></label>
            <input type="text" id="profile-first-name" name="first_name" value="<?= htmlspecialchars($accountRow['first_name']) ?>" required data-modal-focus>
          </div>
          <div class="field">
            <label for="profile-last-name">Last name <span class="required-mark">*</span></label>
            <input type="text" id="profile-last-name" name="last_name" value="<?= htmlspecialchars($accountRow['last_name']) ?>" required>
          </div>
        </div>
        <p class="field-hint mb-0">Need to update your password? <a href="/change_password.php">Change Password</a></p>
      </div>
      <div class="modal__footer">
        <button type="button" class="btn btn--ghost" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn--primary">Save</button>
      </div>
    </form>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var trigger = document.getElementById('profile-edit-trigger');
  var modal = document.getElementById('profile-edit-modal');
  var redirectInput = document.getElementById('profile-redirect-to');

  trigger.addEventListener('click', function (e) {
    redirectInput.value = window.location.pathname + window.location.search;
    window.petcomOpenModal(modal, { opener: e.currentTarget });
  });
});
</script>
