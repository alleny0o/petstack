const MOBILE_BREAKPOINT = '(max-width: 768px)';

function isMobileViewport() {
  return window.matchMedia(MOBILE_BREAKPOINT).matches;
}


// ===== Sidebar collapse toggle (desktop/tablet, >768px) =========
// Collapses/expands the sidebar to an icon rail by flipping
// data-sidebar="collapsed" on <html>. CSS reacts to that attribute
// (see layout.css section 8). State is persisted in localStorage so
// it survives page reloads.
// Using an <html> attribute (not a class on .sidebar) means the
// pre-paint snippet in <head> can apply it before .sidebar even
// exists in the DOM — no flash of the wrong state on load.

const SIDEBAR_STORAGE_KEY = 'petcom:sidebar';

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


document.addEventListener('DOMContentLoaded', () => {
  initSidebarToggle();
  initHamburgerToggle();
  initSidebarBackdrop();
  initSidebarMobileSafety();
});


// ===== Toasts =====================================================
// Transient feedback, bottom-right. Usage from any page or script:
//   showToast('success', 'Account created.')
// Types: success | error | warning | info. Server-side flashes emit
// this via toast_flash() in src/helpers.php. Removal uses timers,
// not transitionend, so prefers-reduced-motion (which disables all
// transitions) can't strand a toast in the DOM.

const TOAST_DURATION_MS = 4000;
const TOAST_MAX_VISIBLE = 3;

function ensureToastRegion() {
  let region = document.querySelector('.toast-region');
  if (!region) {
    region = document.createElement('div');
    region.className = 'toast-region';
    document.body.appendChild(region);
  }
  return region;
}

function dismissToast(toast) {
  if (toast.dataset.leaving === 'true') return;
  toast.dataset.leaving = 'true';
  toast.classList.add('toast--leaving');
  setTimeout(() => toast.remove(), 220);
}

function showToast(type, message, options = {}) {
  const region = ensureToastRegion();

  // Oldest toast makes room once the stack is full
  const visible = region.querySelectorAll('.toast:not(.toast--leaving)');
  if (visible.length >= TOAST_MAX_VISIBLE) {
    dismissToast(visible[0]);
  }

  const toast = document.createElement('div');
  toast.className = 'toast toast--' + type;
  // Errors interrupt screen readers; everything else waits its turn
  toast.setAttribute('role', type === 'error' ? 'alert' : 'status');

  const dot = document.createElement('span');
  dot.className = 'toast__dot';

  const msg = document.createElement('div');
  msg.className = 'toast__msg';
  msg.textContent = message;

  const close = document.createElement('button');
  close.type = 'button';
  close.className = 'toast__close';
  close.setAttribute('aria-label', 'Dismiss notification');
  close.innerHTML = '&times;';
  close.addEventListener('click', () => dismissToast(toast));

  toast.append(dot, msg, close);
  region.appendChild(toast);

  // Auto-dismiss with pause-on-hover: the remaining time is tracked
  // across pointer enter/leave instead of one fixed timeout.
  let remaining = options.duration || TOAST_DURATION_MS;
  let startedAt = Date.now();
  let timer = setTimeout(() => dismissToast(toast), remaining);

  toast.addEventListener('mouseenter', () => {
    clearTimeout(timer);
    remaining -= Date.now() - startedAt;
  });
  toast.addEventListener('mouseleave', () => {
    startedAt = Date.now();
    timer = setTimeout(() => dismissToast(toast), Math.max(remaining, 600));
  });

  return toast;
}

window.showToast = showToast;


// ===== Modals =====================================================
// Two entry points share the open/close/focus-trap machinery:
//  1. petcomOpenModal(overlayEl) — opens a modal already in the page
//     markup (e.g. the reject-with-reason form on registrations.php).
//  2. Declarative form confirms — any <form data-confirm="…"> gets
//     its submit intercepted and routed through a built-on-the-fly
//     confirm dialog; confirming re-submits the form natively, so
//     POST semantics are untouched. Optional attributes:
//       data-confirm-title="Deactivate account"
//       data-confirm-verb="Deactivate"   (confirm button label)
//       data-confirm-danger              (solid red confirm button)

const MODAL_FOCUSABLE =
  'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled])';

let activeModal = null; // { overlay, opener, keydownHandler, temporary }

function petcomCloseModal() {
  if (!activeModal) return;
  const { overlay, opener, keydownHandler, temporary } = activeModal;
  activeModal = null;

  document.removeEventListener('keydown', keydownHandler, true);
  delete document.documentElement.dataset.modalOpen;

  if (temporary) {
    overlay.remove();
  } else {
    overlay.hidden = true;
  }
  if (opener && document.contains(opener)) {
    opener.focus();
  }
}

function petcomOpenModal(overlay, options = {}) {
  if (activeModal) petcomCloseModal();

  overlay.hidden = false;
  document.documentElement.dataset.modalOpen = 'true';

  const keydownHandler = (e) => {
    if (e.key === 'Escape') {
      e.preventDefault();
      petcomCloseModal();
      return;
    }
    if (e.key !== 'Tab') return;

    // Keep Tab cycling inside the dialog
    const focusables = overlay.querySelectorAll(MODAL_FOCUSABLE);
    if (!focusables.length) return;
    const first = focusables[0];
    const last = focusables[focusables.length - 1];
    if (e.shiftKey && document.activeElement === first) {
      e.preventDefault();
      last.focus();
    } else if (!e.shiftKey && document.activeElement === last) {
      e.preventDefault();
      first.focus();
    }
  };
  document.addEventListener('keydown', keydownHandler, true);

  // Close on backdrop click — mousedown must start on the backdrop
  // itself so a drag-select ending outside the card doesn't close it.
  if (overlay.dataset.modalWired !== 'true') {
    overlay.dataset.modalWired = 'true';
    overlay.addEventListener('mousedown', (e) => {
      if (e.target === overlay) petcomCloseModal();
    });
    overlay.querySelectorAll('[data-modal-close]').forEach((el) => {
      el.addEventListener('click', () => petcomCloseModal());
    });
  }

  activeModal = {
    overlay,
    opener: options.opener || document.activeElement,
    keydownHandler,
    temporary: options.temporary === true,
  };

  const focusTarget =
    overlay.querySelector('[data-modal-focus]') ||
    overlay.querySelector(MODAL_FOCUSABLE);
  if (focusTarget) focusTarget.focus();
}

window.petcomOpenModal = petcomOpenModal;
window.petcomCloseModal = petcomCloseModal;

function buildConfirmModal({ title, message, verb, danger }) {
  const overlay = document.createElement('div');
  overlay.className = 'modal-overlay';

  const modal = document.createElement('div');
  modal.className = 'modal';
  modal.setAttribute('role', 'dialog');
  modal.setAttribute('aria-modal', 'true');
  modal.setAttribute('aria-labelledby', 'petcom-confirm-title');

  const body = document.createElement('div');
  body.className = 'modal__body';

  const heading = document.createElement('h2');
  heading.className = 'modal__title';
  heading.id = 'petcom-confirm-title';
  heading.textContent = title;

  const msg = document.createElement('p');
  msg.className = 'modal__message';
  msg.textContent = message;

  body.append(heading, msg);

  const footer = document.createElement('div');
  footer.className = 'modal__footer';

  const cancelBtn = document.createElement('button');
  cancelBtn.type = 'button';
  cancelBtn.className = 'btn btn--ghost';
  cancelBtn.textContent = 'Cancel';
  cancelBtn.setAttribute('data-modal-close', '');

  const confirmBtn = document.createElement('button');
  confirmBtn.type = 'button';
  confirmBtn.className = danger ? 'btn btn--danger-solid' : 'btn btn--primary';
  confirmBtn.textContent = verb;

  footer.append(cancelBtn, confirmBtn);
  modal.append(body, footer);
  overlay.appendChild(modal);

  return { overlay, confirmBtn };
}

function initConfirmForms() {
  document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (e) => {
      if (form.dataset.confirmed === 'true') return; // user already confirmed

      e.preventDefault();
      const opener = document.activeElement;
      const { overlay, confirmBtn } = buildConfirmModal({
        title: form.dataset.confirmTitle || 'Are you sure?',
        message: form.dataset.confirm,
        verb: form.dataset.confirmVerb || 'Confirm',
        danger: form.dataset.confirmDanger !== undefined,
      });

      confirmBtn.addEventListener('click', () => {
        setButtonLoading(confirmBtn);
        form.dataset.confirmed = 'true';
        if (typeof form.requestSubmit === 'function') {
          form.requestSubmit();
        } else {
          form.submit();
        }
      });

      document.body.appendChild(overlay);
      petcomOpenModal(overlay, { opener, temporary: true });
    });
  });
}


// ===== Form loading / double-submit guard =========================
// Every submitted form marks its submit button as busy and refuses a
// second submission. Uses a class + pointer-events, NOT `disabled`,
// so no field or button value is dropped from the POST. Attached at
// the document level in the bubble phase, so per-form handlers (like
// the confirm interceptor above) run first — if they preventDefault,
// nothing here fires.

function setButtonLoading(btn) {
  if (!btn || btn.classList.contains('is-loading')) return;
  btn.classList.add('is-loading');
  btn.setAttribute('aria-busy', 'true');
  btn.insertAdjacentHTML('afterbegin', '<span class="spinner" aria-hidden="true"></span>');
}

function initFormLoadingStates() {
  document.addEventListener('submit', (e) => {
    if (e.defaultPrevented) return;
    const form = e.target;
    if (!(form instanceof HTMLFormElement)) return;

    if (form.dataset.submitting === 'true') {
      e.preventDefault(); // double-submit guard
      return;
    }
    form.dataset.submitting = 'true';

    const btn =
      e.submitter && e.submitter.classList && e.submitter.classList.contains('btn')
        ? e.submitter
        : form.querySelector('button[type="submit"], input[type="submit"]');
    if (btn && btn.tagName === 'BUTTON') {
      setButtonLoading(btn);
    }
  });
}


// ===== Copy to clipboard ==========================================
// <button data-copy-target="#some-id"> copies that element's text.
// Clipboard API needs a secure context (localhost / HTTPS — both
// true here); a text-selection fallback covers anything older.

function initCopyButtons() {
  document.querySelectorAll('[data-copy-target]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const target = document.querySelector(btn.dataset.copyTarget);
      if (!target) return;
      const text = target.textContent.trim();

      const markCopied = () => {
        const original = btn.textContent;
        btn.textContent = 'Copied';
        setTimeout(() => {
          btn.textContent = original;
        }, 1500);
      };

      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(markCopied, () => selectFallback(target));
      } else {
        selectFallback(target);
      }
    });
  });

  function selectFallback(target) {
    // Can't write to the clipboard — select the value so a manual
    // Ctrl/Cmd+C is one keystroke away.
    const range = document.createRange();
    range.selectNodeContents(target);
    const selection = window.getSelection();
    selection.removeAllRanges();
    selection.addRange(range);
  }
}


document.addEventListener('DOMContentLoaded', () => {
  initConfirmForms();
  initFormLoadingStates();
  initCopyButtons();
});
