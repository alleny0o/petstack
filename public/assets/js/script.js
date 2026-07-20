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
    closeSidebarFlyout(); // un-collapsing kills the flyout's reason to exist
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
    // The collapsed-rail flyout is desktop/tablet-only (see
    // toggleSidebarFlyout) — a stale position: fixed panel positioned via
    // a desktop getBoundingClientRect shouldn't be left floating once the
    // viewport shrinks into the mobile off-canvas layout.
    if (isMobileViewport()) {
      closeSidebarFlyout();
    }
  });
}


// ===== Sidebar submenu expand/collapse (Accounts/Catalog/Directory) ==
// Click toggles .is-expanded on the parent <li>, which drives the
// chevron rotation and the submenu's open/close animation entirely
// through CSS (.submenu-wrapper's grid-template-rows 0fr -> 1fr, see
// sidebar.css) — no JS measurement of the submenu's height is needed.
// No persistence — initial state is rendered server-side (see
// layout_admin.php).
//
// Exception: when the sidebar is icon-rail collapsed (desktop/tablet
// only — mobile off-canvas always uses the inline behavior above), the
// inline submenu is display:none (layout.css), so the click opens a
// floating flyout instead. Same toggle button, different target
// depending on collapsed state.

// Single place that mutates a submenu's open/closed state, used by the
// toggle click, the accordion close-others sweep, and the bfcache
// re-sync handler below, so all three stay in sync (class + aria).
function setSubmenuExpanded(item, expand) {
  const toggleBtn = item.querySelector(':scope > .menu-link');
  item.classList.toggle('is-expanded', expand);
  if (toggleBtn) toggleBtn.setAttribute('aria-expanded', expand ? 'true' : 'false');
}

function initSidebarSubmenus() {
  // The PHP template renders aria-expanded assuming the inline submenu
  // is what would open (correct when the sidebar starts expanded), but
  // the collapsed state is restored from localStorage pre-paint — if
  // that's the state on load, nothing is actually open yet. (The
  // .is-expanded class itself is left alone: the CSS grid animation
  // handles an SSR-expanded submenu correctly with no JS involvement, and
  // the collapsed-rail media query hides .submenu-wrapper outright
  // regardless of that class.)
  if (document.documentElement.dataset.sidebar === 'collapsed' && !isMobileViewport()) {
    document.querySelectorAll('.menu-item--has-submenu > .menu-link').forEach((toggleBtn) => {
      toggleBtn.setAttribute('aria-expanded', 'false');
    });
  }

  document.querySelectorAll('.menu-item--has-submenu > .menu-link').forEach((toggleBtn) => {
    toggleBtn.addEventListener('click', () => {
      const collapsedDesktop =
        document.documentElement.dataset.sidebar === 'collapsed' && !isMobileViewport();
      if (collapsedDesktop) {
        toggleSidebarFlyout(toggleBtn);
        return;
      }
      const item = toggleBtn.closest('.menu-item--has-submenu');
      const expand = !item.classList.contains('is-expanded');
      setSubmenuExpanded(item, expand);
      // Accordion: expanding one submenu collapses any other open one
      // (the collapsed-rail flyout path already enforces this separately
      // via closeSidebarFlyout()).
      if (expand) {
        document.querySelectorAll('.menu-item--has-submenu.is-expanded').forEach((other) => {
          if (other !== item) setSubmenuExpanded(other, false);
        });
      }
    });
  });

  // A clicked submenu-link's destination is always one of its own
  // submenu's children, so a full page navigation always lands back on
  // that same submenu, server-rendered expanded again — there's nothing
  // to fix up here. The one real stale-state case is a bfcache "Back"
  // restore, which skips that fresh server render entirely; handled
  // below by re-syncing every submenu against its own .active link
  // instead, the same rule PHP used to decide is-expanded in the first
  // place.
  window.addEventListener('pageshow', (e) => {
    if (!e.persisted) return;
    document.querySelectorAll('.menu-item--has-submenu').forEach((item) => {
      setSubmenuExpanded(item, !!item.querySelector('.submenu-link.active'));
    });
  });
}


// ===== Sidebar collapsed-rail flyout ==============================
// Floating panel for reaching submenu links while the sidebar is an
// icon rail. Appended to <body> (not .sidebar-content, which clips via
// overflow:hidden) and positioned next to the clicked icon.

let activeFlyout = null; // { panel, toggleBtn, defaultAriaControls, outsideClickHandler, keydownHandler, scrollHandler }

function closeSidebarFlyout() {
  if (!activeFlyout) return;
  const { panel, toggleBtn, defaultAriaControls, outsideClickHandler, keydownHandler, scrollHandler } =
    activeFlyout;
  activeFlyout = null;

  document.removeEventListener('mousedown', outsideClickHandler, true);
  document.removeEventListener('keydown', keydownHandler, true);
  const sidebarContent = document.querySelector('.sidebar-content');
  if (sidebarContent) sidebarContent.removeEventListener('scroll', scrollHandler);

  panel.remove();
  toggleBtn.setAttribute('aria-expanded', 'false');
  toggleBtn.setAttribute('aria-controls', defaultAriaControls);
  toggleBtn.focus();
}

function buildSidebarFlyout(item) {
  const panel = document.createElement('div');
  panel.className = 'sidebar-flyout';
  panel.id = 'accounts-flyout';
  panel.setAttribute('role', 'menu');

  const heading = document.createElement('div');
  heading.className = 'sidebar-flyout__title';
  heading.textContent = 'Accounts';
  panel.appendChild(heading);

  const list = document.createElement('ul');
  list.className = 'sidebar-flyout__list';
  item.querySelectorAll('.submenu-link').forEach((link) => {
    const li = document.createElement('li');
    li.appendChild(link.cloneNode(true));
    list.appendChild(li);
  });
  panel.appendChild(list);

  return panel;
}

function toggleSidebarFlyout(toggleBtn) {
  if (activeFlyout && activeFlyout.toggleBtn === toggleBtn) {
    closeSidebarFlyout();
    return;
  }
  closeSidebarFlyout(); // only one flyout open at a time

  const item = toggleBtn.closest('.menu-item--has-submenu');
  const panel = buildSidebarFlyout(item);
  document.body.appendChild(panel);

  const rect = toggleBtn.getBoundingClientRect();
  panel.style.top = `${rect.top}px`;
  panel.style.left = `${rect.right + 8}px`;

  const defaultAriaControls = toggleBtn.getAttribute('aria-controls');
  toggleBtn.setAttribute('aria-expanded', 'true');
  toggleBtn.setAttribute('aria-controls', panel.id);

  const outsideClickHandler = (e) => {
    if (!panel.contains(e.target) && !toggleBtn.contains(e.target)) {
      closeSidebarFlyout();
    }
  };
  const keydownHandler = (e) => {
    if (e.key === 'Escape') closeSidebarFlyout();
  };
  const scrollHandler = () => closeSidebarFlyout();

  document.addEventListener('mousedown', outsideClickHandler, true);
  document.addEventListener('keydown', keydownHandler, true);
  const sidebarContent = document.querySelector('.sidebar-content');
  if (sidebarContent) sidebarContent.addEventListener('scroll', scrollHandler);

  panel.querySelectorAll('a').forEach((link) => {
    link.addEventListener('click', () => closeSidebarFlyout());
  });

  activeFlyout = { panel, toggleBtn, defaultAriaControls, outsideClickHandler, keydownHandler, scrollHandler };
}

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

function petcomCloseModal(force = false) {
  if (!activeModal) return;
  // Opt-in veto hook: an overlay may carry a petcomBeforeClose callback
  // (set by its own page script — only the new-order modal does today);
  // returning false aborts the close. Every close path (Esc, backdrop,
  // X, footer Cancel) funnels through here, so one hook covers them
  // all. Callers that pass force=true (e.g. after a confirmed discard)
  // skip the hook. Modals that never set the callback behave exactly
  // as before.
  if (
    !force &&
    typeof activeModal.overlay.petcomBeforeClose === 'function' &&
    activeModal.overlay.petcomBeforeClose() === false
  ) {
    return;
  }
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
  if (activeModal) {
    petcomCloseModal();
    // A petcomBeforeClose hook may have vetoed that close — never open
    // a second modal on top of one that refused to leave.
    if (activeModal) return;
  }

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

// Promise-based confirm dialog that can STACK on top of an open modal.
// Deliberately does NOT go through petcomOpenModal — the modal system is
// strictly single-modal (opening force-closes activeModal), which would
// kill the host modal underneath. Instead this reuses buildConfirmModal's
// DOM and runs its own tiny lifecycle: appended to <body> above the
// standard overlay (.modal-overlay--stacked, modals.css), window-level
// CAPTURE keydown so it wins over the host modal's document-level capture
// handler (window capture fires first), stopPropagation so Esc/Tab never
// reach the host modal's close/focus-trap logic. Esc, backdrop, and
// Cancel resolve false; the confirm button resolves true. Also works with
// no host modal open — it is fully self-contained.
function petcomConfirm({ title, message, verb, danger }) {
  return new Promise((resolve) => {
    const { overlay, confirmBtn } = buildConfirmModal({ title, message, verb, danger });
    overlay.classList.add('modal-overlay--stacked');
    const cancelBtn = overlay.querySelector('[data-modal-close]');
    const previouslyFocused = document.activeElement;

    const settle = (result) => {
      window.removeEventListener('keydown', keydownHandler, true);
      overlay.remove();
      if (previouslyFocused && document.contains(previouslyFocused)) {
        previouslyFocused.focus();
      }
      resolve(result);
    };

    const keydownHandler = (e) => {
      if (e.key === 'Escape') {
        e.preventDefault();
        e.stopPropagation();
        settle(false);
        return;
      }
      if (e.key === 'Tab') {
        // Mini focus trap over the dialog's two buttons. Trapping Tab
        // here (not just Esc) matters: the host modal's own trap is
        // still listening and would otherwise yank focus back into the
        // modal underneath. Two focusables means forward and backward
        // Tab both just swap between them.
        e.preventDefault();
        e.stopPropagation();
        (document.activeElement === confirmBtn ? cancelBtn : confirmBtn).focus();
      }
    };
    window.addEventListener('keydown', keydownHandler, true);

    // Same backdrop semantics as petcomOpenModal: the mousedown must
    // START on the backdrop, so a drag-select ending outside the card
    // doesn't dismiss it.
    overlay.addEventListener('mousedown', (e) => {
      if (e.target === overlay) settle(false);
    });
    cancelBtn.addEventListener('click', () => settle(false));
    confirmBtn.addEventListener('click', () => settle(true));

    document.body.appendChild(overlay);
    confirmBtn.focus();
  });
}

window.petcomConfirm = petcomConfirm;

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

// Inverse of setButtonLoading, for AJAX submits that stay on the page
// after a failure (a native full-page POST never needs this — the
// response replaces the document). Both are exposed on window because
// the AJAX order form's inline script (new_order_form.php) manages its
// own loading state: initFormLoadingStates() below skips any submit
// that was preventDefault-ed.
function clearButtonLoading(btn) {
  if (!btn || !btn.classList.contains('is-loading')) return;
  btn.classList.remove('is-loading');
  btn.removeAttribute('aria-busy');
  const spinner = btn.querySelector('.spinner');
  if (spinner) spinner.remove();
}

window.petcomSetButtonLoading = setButtonLoading;
window.petcomClearButtonLoading = clearButtonLoading;

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


// ===== Order form cascade (nuclide → product → location) ==========
// Shared behavior behind both renders of the customer order fields:
// the new-order modal (src/partials/new_order_form.php) and the
// pending-order edit form (customer/order_detail.php). Filters the
// product select to the chosen nuclide, toggles the delivery-location
// section per the selected product's fixed delivery method, and keeps
// the fulfillment hint in sync. Attaches its own change listeners on
// the two selects (target phase — they always run before any
// form-level delegated listeners) and runs once immediately so the
// initial paint settles: empty in the modal, pre-populated with the
// order's current values in edit mode (the selected product survives
// the first filter because its data-nuclide-id matches the preselected
// nuclide). Returns { refresh } so a caller can re-derive the cascade
// after form.reset().

function petcomInitOrderCascade({ nuclideSelect, productSelect, locationField, locationSelect, deliveryHint }) {
  const productOptions = Array.from(productSelect.querySelectorAll('option[data-nuclide-id]'));

  function updateLocationRequirement() {
    const selected = productSelect.selectedOptions[0];
    const requiresLocation = !!selected && selected.dataset.requiresLocation === '1';
    // Hidden entirely — not shown-as-optional — when the selected
    // product's fixed delivery method doesn't call for a location.
    // Disabled as well as hidden so a stale pick is excluded from
    // both checkValidity() and the POST, while surviving a toggle
    // away and back.
    locationField.hidden = !requiresLocation;
    locationSelect.disabled = !requiresLocation;
    locationSelect.required = requiresLocation;
  }

  function updateDeliveryHint() {
    const selected = productSelect.selectedOptions[0];
    // data-delivery-label is rendered server-side from the one PHP
    // enum->display mapping, so it never gets re-implemented here.
    if (!selected || !selected.dataset.deliveryLabel) {
      deliveryHint.hidden = true;
      deliveryHint.textContent = '';
      return;
    }
    deliveryHint.textContent = 'Fulfillment: ' + selected.dataset.deliveryLabel;
    deliveryHint.hidden = false;
  }

  function filterProducts() {
    const nuclideId = nuclideSelect.value;
    productOptions.forEach((opt) => {
      // Exact match: each flat product row has exactly one nuclide.
      const matches = opt.dataset.nuclideId === nuclideId;
      opt.hidden = !matches;
      opt.disabled = !matches;
    });
    if (productSelect.selectedOptions[0] && productSelect.selectedOptions[0].hidden) {
      productSelect.value = '';
    }
    productSelect.disabled = !nuclideId;
    updateLocationRequirement();
    updateDeliveryHint();
  }

  function onProductChange() {
    updateLocationRequirement();
    updateDeliveryHint();
  }

  nuclideSelect.addEventListener('change', filterProducts);
  productSelect.addEventListener('change', onProductChange);

  filterProducts();

  return { refresh: filterProducts };
}

window.petcomInitOrderCascade = petcomInitOrderCascade;


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


// ===== Init (single entry point — order matters: sidebar first so its
// pre-paint collapsed/submenu state is wired up before anything else
// touches the DOM, then the page-wide confirm/form/copy/dashboard
// behaviors) ========================================================

document.addEventListener('DOMContentLoaded', () => {
  initSidebarToggle();
  initHamburgerToggle();
  initSidebarBackdrop();
  initSidebarMobileSafety();
  initSidebarSubmenus();

  initConfirmForms();
  initFormLoadingStates();
  initCopyButtons();
});
