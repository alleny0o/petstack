<?php
// TODO(auth): read the logged-in customer instead of this placeholder.
$accountName = 'Jane Doe';
$accountInitials = implode('', array_map(
    fn($w) => mb_substr($w, 0, 1),
    array_slice(explode(' ', $accountName), 0, 2)
));
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
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
          <a href="/customer/dashboard.php" class="menu-link <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="3" y="3" width="7" height="7"></rect>
              <rect x="14" y="3" width="7" height="7"></rect>
              <rect x="14" y="14" width="7" height="7"></rect>
              <rect x="3" y="14" width="7" height="7"></rect>
            </svg>
            <span class="menu-label"><span class="menu-label__text">Dashboard</span></span>
          </a>
        </li>

        <li class="menu-item">
          <a href="/customer/catalog.php" class="menu-link <?= $currentPage === 'catalog' ? 'active' : '' ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="10" cy="10" r="7"></circle>
              <line x1="21" y1="21" x2="15" y2="15"></line>
            </svg>
            <span class="menu-label"><span class="menu-label__text">Catalog</span></span>
          </a>
        </li>

      </ul>
    </nav>
  </div>

  <!-- Sidebar Footer -->
  <div class="sidebar-footer">
    <a href="/customer/account.php" class="sidebar-account">
      <div class="account-avatar"><?= htmlspecialchars($accountInitials) ?></div>
      <span class="account-name"><?= htmlspecialchars($accountName) ?></span>
    </a>

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
