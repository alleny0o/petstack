# PETCOM CSS Style Guide

- **style.css:** System fonts (`-apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif`), reset, design tokens (colors + status text/tint pairs, spacing, radii `--radius-sm/md/lg/full`, shadows `--shadow-xs…lg`), typography, accessibility (`:focus-visible`, `prefers-reduced-motion`, `.sr-only`)
- **layout/shell.css:** App-shell grid, header/main/footer chrome bindings
- **layout/sidebar.css:** Sidebar (sticky, collapse on desktop, off-canvas on mobile), topbar, dark mode hooks
- **components/:** One file per concern — auth, page-structure (page header + cards + detail list), forms, buttons, tables, alerts (incl. temp-password banner), badges, utilities, toasts, modals, feedback (spinners + empty states), dashboard (stat tiles + masonry), radio-cards, order-page

**No role-specific CSS files.** All three roles share the same component library.

**UI feedback conventions (post-D.2 overhaul):**
- Transient success → toast via `toast_flash($type, $message)` (helpers.php); pages re-render on POST (no PRG), so the helper emits a DOMContentLoaded `showToast()` call
- Errors/warnings → inline `.alert--error/--warning` banners; per-field validation → `field_class()` on the `.field` wrapper + `field_error()` below the input
- Destructive/irreversible actions → `data-confirm` / `data-confirm-title` / `data-confirm-verb` / `data-confirm-danger` attributes on the form; script.js intercepts submit and shows a custom modal (never `window.confirm`)
- Temp-password reveals → `.temp-password-banner` with a `data-copy-target` Copy button; never a toast
- Status language: pill badges with a leading dot (`.badge--active/pending/approved/rejected/…`); role chips are square (`.badge--role-admin/staff`)
- Submit buttons get a spinner + double-submit guard automatically from script.js — no per-form wiring needed

**Dark mode:** Not implemented right now. Tokens may exist in CSS for future use but no toggle is wired up.

**Sidebar collapse (desktop only):** Pre-paint script reads `localStorage['petcom:sidebar']` and sets `data-sidebar="collapsed"` on `<html>`. CSS changes `--sidebar-width`. Mobile sidebar (off-canvas) uses `data-sidebar-mobile="open"` on `<html>` instead — a separate, independent state.
