<!-- App topbar: always present (see style.css section 8). The
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

<?php $currentPage = basename($_SERVER['PHP_SELF'], '.php'); ?>
<aside class="sidebar">
  <!-- Sidebar Header -->
  <header class="sidebar-header">

    <div class="auth-card__logo"><?php include 'icon.svg'; ?></div>
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
          <a href="/staff_home.php" class="menu-link <?= in_array($currentPage, ['staff_home', 'staff_order_detail'], true) ? 'active' : '' ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="3" y="3" width="7" height="7"></rect>
              <rect x="14" y="3" width="7" height="7"></rect>
              <rect x="14" y="14" width="7" height="7"></rect>
              <rect x="3" y="14" width="7" height="7"></rect>
            </svg>
            <span class="menu-label"><span class="menu-label__text">Order Queue</span></span>
          </a>
        </li>

        <li class="menu-item">
          <a href="/staff_past_orders.php" class="menu-link <?= $currentPage === 'staff_past_orders' ? 'active' : '' ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M9 11l3 3L22 4"></path>
              <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path>
            </svg>
            <span class="menu-label"><span class="menu-label__text">Past Orders</span></span>
          </a>
        </li>

        <?php /* Cross-links between dashboards are a dev convenience until
                 auth.php lands and each role only sees its own shell. */ ?>
        <li class="menu-item">
          <a href="/customer_home.php" class="menu-link">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="back-arrow-icon">
            <line x1="19" y1="12" x2="5" y2="12"></line>
            <polyline points="12 19 5 12 12 5"></polyline>
          </svg>
            <span class="menu-label"><span class="menu-label__text">Customer Dashboard</span></span>
          </a>
        </li>

        <li class="menu-item">
          <a href="/admin_home.php" class="menu-link">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="forward-arrow-icon">
            <line x1="5" y1="12" x2="19" y2="12"></line>
            <polyline points="12 5 19 12 12 19"></polyline>
          </svg>
            <span class="menu-label"><span class="menu-label__text">Admin Dashboard</span></span>
          </a>
        </li>

      </ul>
    </nav>
  </div>

  <!-- Sidebar Footer -->
  <div class="sidebar-footer">
    <div class="sidebar-account">
      <div class="account-avatar">JD</div>
      <span class="account-name">John Doe</span>
    </div>

    <div class="sidebar-footer-actions">
      <button class="theme-toggle" type="button" aria-label="Toggle dark mode">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="5"></circle>
          <line x1="12" y1="1" x2="12" y2="3"></line>
          <line x1="12" y1="21" x2="12" y2="23"></line>
          <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
          <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
          <line x1="1" y1="12" x2="3" y2="12"></line>
          <line x1="21" y1="12" x2="23" y2="12"></line>
          <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
          <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
        </svg>
      </button>

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