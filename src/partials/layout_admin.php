<?php
// TODO(auth): read the logged-in admin instead of this placeholder.
$accountName = 'Admin User';
$accountInitials = implode('', array_map(
    fn($w) => mb_substr($w, 0, 1),
    array_slice(explode(' ', $accountName), 0, 2)
));
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!-- App topbar: always present (see layout.css section 8). The
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

        <li class="menu-item">
          <a href="/admin_registrations.php" class="menu-link <?= $currentPage === 'admin_registrations' ? 'active' : '' ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
              <circle cx="8.5" cy="7" r="4"></circle>
              <line x1="20" y1="8" x2="20" y2="14"></line>
              <line x1="23" y1="11" x2="17" y2="11"></line>
            </svg>
            <span class="menu-label"><span class="menu-label__text">Registrations</span></span>
          </a>
        </li>

        <li class="menu-item">
          <a href="/admin_customers.php" class="menu-link <?= in_array($currentPage, ['admin_customers', 'admin_customer_detail'], true) ? 'active' : '' ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
              <circle cx="12" cy="7" r="4"></circle>
            </svg>
            <span class="menu-label"><span class="menu-label__text">Customers</span></span>
          </a>
        </li>

        <li class="menu-item">
          <a href="/admin_catalog.php" class="menu-link <?= $currentPage === 'admin_catalog' ? 'active' : '' ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
              <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
              <line x1="12" y1="22.08" x2="12" y2="12"></line>
            </svg>
            <span class="menu-label"><span class="menu-label__text">Catalog Config</span></span>
          </a>
        </li>

        <li class="menu-item">
          <a href="/admin_accounts.php" class="menu-link <?= $currentPage === 'admin_accounts' ? 'active' : '' ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
              <circle cx="9" cy="7" r="4"></circle>
              <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
              <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
            </svg>
            <span class="menu-label"><span class="menu-label__text">Accounts</span></span>
          </a>
        </li>

        <li class="menu-item">
          <a href="/admin_reports.php" class="menu-link <?= $currentPage === 'admin_reports' ? 'active' : '' ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="18" y1="20" x2="18" y2="10"></line>
              <line x1="12" y1="20" x2="12" y2="4"></line>
              <line x1="6" y1="20" x2="6" y2="14"></line>
            </svg>
            <span class="menu-label"><span class="menu-label__text">Reports</span></span>
          </a>
        </li>

      </ul>
    </nav>
  </div>

  <!-- Sidebar Footer -->
  <div class="sidebar-footer">
    <div class="sidebar-account">
      <div class="account-avatar"><?= htmlspecialchars($accountInitials) ?></div>
      <span class="account-name"><?= htmlspecialchars($accountName) ?></span>
    </div>

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
