const MOBILE_BREAKPOINT = '(max-width: 768px)';

function isMobileViewport() {
  return window.matchMedia(MOBILE_BREAKPOINT).matches;
}


// ===== Sidebar collapse toggle (desktop/tablet, >768px) =========
// Collapses/expands the sidebar to an icon rail by flipping
// data-sidebar="collapsed" on <html>. CSS reacts to that attribute
// (see style.css section 8). State is persisted in localStorage so
// it survives page reloads.
// Using an <html> attribute (not a class on .sidebar) means the
// pre-paint snippet in <head> can apply it before .sidebar even
// exists in the DOM — no flash of the wrong state on load.

const SIDEBAR_STORAGE_KEY = 'petstack:sidebar';

function setSidebarState(collapsed) {
  if (collapsed) {
    document.documentElement.dataset.sidebar = 'collapsed';
  } else {
    delete document.documentElement.dataset.sidebar;
  }
  localStorage.setItem(SIDEBAR_STORAGE_KEY, collapsed ? 'collapsed' : 'expanded');
}


// ===== Sidebar mobile off-canvas toggle (≤768px) =================
// Opens/closes the off-canvas sidebar by flipping
// data-sidebar-mobile="open" on <html>. This is a separate
// attribute from the desktop "collapsed" state above — they're
// independent, not two ends of the same spectrum. Not persisted:
// the mobile sidebar always starts closed on a fresh page load.

function setSidebarMobileOpen(open) {
  if (open) {
    document.documentElement.dataset.sidebarMobile = 'open';
  } else {
    delete document.documentElement.dataset.sidebarMobile;
  }
}

function isSidebarMobileOpen() {
  return document.documentElement.dataset.sidebarMobile === 'open';
}


// ===== Shared chevron toggle (.sidebar-toggle) ====================
// Behavior depends on viewport: on mobile it opens/closes the
// off-canvas panel; on desktop/tablet it collapses/expands the
// icon rail. Same physical button, different job per breakpoint.

function initSidebarToggle() {
  const toggleBtn = document.querySelector('.sidebar-toggle');
  if (!toggleBtn) return;

  toggleBtn.addEventListener('click', () => {
    if (isMobileViewport()) {
      setSidebarMobileOpen(!isSidebarMobileOpen());
    } else {
      const isCollapsed = document.documentElement.dataset.sidebar === 'collapsed';
      setSidebarState(!isCollapsed);
    }
  });
}

function initHamburgerToggle() {
  const hamburgerBtn = document.querySelector('.hamburger-toggle');
  if (!hamburgerBtn) return;

  hamburgerBtn.addEventListener('click', () => {
    setSidebarMobileOpen(true);
  });
}

function initSidebarBackdrop() {
  const backdrop = document.querySelector('.sidebar-backdrop');
  if (!backdrop) return;

  backdrop.addEventListener('click', () => {
    setSidebarMobileOpen(false);
  });
}

// Close on Escape, and auto-close if the viewport grows past the
// mobile breakpoint while the panel happens to be open (e.g. a
// tablet rotated or a window resized/un-maximized mid-session).
function initSidebarMobileSafety() {
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && isSidebarMobileOpen()) {
      setSidebarMobileOpen(false);
    }
  });

  window.addEventListener('resize', () => {
    if (!isMobileViewport() && isSidebarMobileOpen()) {
      setSidebarMobileOpen(false);
    }
  });
}


// ===== Dark mode toggle ==========================================
// Flips data-theme="dark" on <html>. CSS reads that attribute in
// the [data-theme="dark"] token block (see style.css section 3).
// State is persisted the same way as the sidebar.

const THEME_STORAGE_KEY = 'petstack:theme';

function initThemeToggle() {
  const themeBtn = document.querySelector('.theme-toggle');
  if (!themeBtn) return;

  themeBtn.addEventListener('click', () => {
    const isDark = document.documentElement.dataset.theme === 'dark';
    setTheme(isDark ? 'light' : 'dark');
  });
}

function setTheme(theme) {
  if (theme === 'dark') {
    document.documentElement.dataset.theme = 'dark';
  } else {
    delete document.documentElement.dataset.theme;
  }
  localStorage.setItem(THEME_STORAGE_KEY, theme);
}


document.addEventListener('DOMContentLoaded', () => {
  initSidebarToggle();
  initHamburgerToggle();
  initSidebarBackdrop();
  initSidebarMobileSafety();
  initThemeToggle();
});