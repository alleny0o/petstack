# CLAUDE.md

Guidance for Claude Code when working on PETCOM.

## Stack

- **PHP 7.4** (RHEL 8 compatible)
- **MySQL 8.0 / MariaDB 10.11** (wire-compatible via PDO)
- **PDO** with prepared statements (no ORM, no framework)
- **Vanilla CSS** (system fonts only, no external dependencies)
- **Vanilla JavaScript** (no framework, minimal)
- **No Composer, no npm, no external packages**

## Local Dev Setup

1. Create `petcom` database and load `sql/schema.sql`, then `sql/seed.sql`
2. Copy `src/config.sample.php` → `src/config.php` and fill in your DB credentials
3. Run `tools/set_temp_passwords.php` once to set temp passwords for seeded accounts
4. Point Apache document root at `public/`
5. Log in at `/login.php`

**MAMP on Mac:** DB port is `8889`. Set `REQUIRE_SECURE_COOKIES = false` locally, `true` on RHEL (HTTPS only).

## Directory Layout

```
petcom/
  public/              # Only web-reachable folder (Apache doc root)
    index.php
    login.php
    register.php
    logout.php
    change_password.php
    customer/
    staff/
    admin/
    assets/
      css/
        style.css      (tokens + base + typography)
        components.css (forms, buttons, cards, tables, alerts)
        layout.css      (sidebar, topbar, grid)
      js/
        script.js      (sidebar collapse + mobile off-canvas toggle)

  src/                 # Above web root — never servable by URL
    config.php          (DB credentials, gitignored)
    config.sample.php   (template)
    db.php              (PDO connection)
    auth.php            (login, require_role(), session guard)
    helpers.php          (session bootstrap, CSRF, escaping, redirects)
    partials/
      head.php
      layout_customer.php
      layout_staff.php
      layout_admin.php

  sql/
    schema.sql          (see Database section below)
    seed.sql            (test data)

  tools/
    set_temp_passwords.php (one-time setup for seeded accounts)
```

No `docs/` folder yet — `DEPLOY.md` is expected to show up as part of the deployment-polish phase; nothing currently reads a `SCHEMA.md`, and it isn't committed anywhere yet either.

## Three Security Rules

1. **Only `public/` is servable.** DB credentials live in `src/`.
2. **Every protected page gates itself near the top:**
   ```php
   require __DIR__ . '/../src/helpers.php';
   bootstrap_session();
   require __DIR__ . '/../src/auth.php';
   require_role('customer'); // or 'staff', 'admin'
   ```
   `bootstrap_session()` (in `helpers.php`) sets hardened cookie flags before
   starting the session — pages must not call a bare `session_start()`. Public
   pages (login.php, register.php) don't call `require_role()`.
3. **Always use `__DIR__` in require/include paths** — relative paths break when deployed to RHEL.

## Page Template Pattern

```php
<?php
require __DIR__ . '/../src/helpers.php';
bootstrap_session();
require __DIR__ . '/../src/auth.php';
require_role('customer');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $pageTitle = 'Page Name'; include __DIR__ . '/../src/partials/head.php'; ?>
</head>
<body>
    <div class="app-shell">
        <?php include __DIR__ . '/../src/partials/layout_customer.php'; ?>
        <main class="app-main">
            <!-- content here -->
        </main>
    </div>
</body>
<script src="/assets/js/script.js" defer></script>
</html>
```

## Database

Tables are grouped by area below; the group composition is stable even as tables
within it get built out. Tables marked **(built)** exist in `sql/schema.sql` today
— see that file for exact columns/constraints and build order (FK-safe order,
not the narrative order here). Unmarked tables are designed but not yet built.

**Identity (11 tables, all built):**
1. `institutes` (built)
2. `labs` (built)
3. `pis` (built)
4. `lab_pis` (built) — join: a lab can have multiple PIs, a PI can oversee multiple labs
5. `users` (built) — shared login table: username, password_hash, must_change_password, failed_login_count, locked_until — used by all three roles
6. `password_history` (built) — prior password hashes, for reuse prevention in `change_password.php`
7. `lockout_events` (built) — records a lockout event each time a login attempt trips the failed-attempt threshold
8. `customers` (built) — extends `users` via `user_id`; institute/lab/supervising_pi locked at approval. `registration_status` is set to `'approved'` when the row is created by the C.2 approval flow — a `customers` row only ever exists once a request is approved, so this is effectively a constant, kept because `customer/dashboard.php` displays it. The old `approved_by`/`approved_at` columns were dropped in C.2 as genuinely unused (see `customer_registration_requests.reviewed_by_admin_id`/`reviewed_at` for that bookkeeping now)
9. `customer_registration_requests` (built) — holds a public self-registration submission until an admin reviews it (see Business Rules)
10. `staff` (built) — extends `users` via `user_id`; has a single `category_id` — one category per staff member, not a junction table
11. `admins` (built) — extends `users` via `user_id`

**Menu (6 tables, 1 built):**
12. `isotopes`
13. `categories` (built) — e.g. Radiopharmacy, Cyclotron — admin-editable, referenced by both `staff.category_id` and the future `compounds.category_id`
14. `compounds`
15. `compound_isotopes` (join: usually 1:1, occasionally a compound allows multiple isotopes)
16. `delivery_options`
17. `compound_delivery_options` (join: each compound lists its own allowed delivery methods)

**Orders (6 tables, none built yet):**
18. `orders`
19. `order_type_a_details` (dose orders: activity_mci, requested_datetime)
20. `order_type_b_details` (cyclotron orders: either beam_current+bombardment_minutes OR eob_activity_mci+eob_datetime, never both)
21. `order_public_comments` (append-only, visible to customer + staff)
22. `order_internal_notes` (append-only, staff-only)
23. `order_audit_log` (status changes only — pending→accepted→completed/canceled, timestamp, who — not field-level diffing)

## Business Rules (Non-Negotiable)

These came from the requirements interview. Don't simplify them.

- **No phone-in orders.** Customers place their own orders only. No `is_phone_in` field, no attestation.
- **Self-registration lands in `customer_registration_requests`, not `customers`.** A public registration submission (Phase C.1) creates a row in that separate table (`status`: pending/approved/rejected) — no `users` or `customers` row exists until an admin approves the request (Phase C.2), at which point the account and temp password get created. `customers.registration_status`/`approved_by`/`approved_at` predate this design and are unused by the current registration flow.
- **Type A and Type B are independent.** Never model one as parent/child.
- **Completed orders are terminal.** No edits, no cancels after `status = completed`.
- **Returned orders go back to `pending`.** No separate "returned" status — the audit log preserves that a return happened.
- **Cost is snapshotted.** `orders.cost_snapshot` is set at creation time. If a compound's standard cost changes later, historical orders/reports are unaffected.
- **Isotope first, then compound.** Customer picks isotope, then sees only compatible compounds — not the reverse.
- **Delivery options are per-compound.** Each compound lists its own allowed delivery methods, not a global list.
- **Audit log is status-only.** Not field-level diffing — just status_from, status_to, timestamp, who.
- **Comments are append-only threads.** Public (customer + staff) and internal (staff-only) are separate tables, never a single overwritable field.
- **No email from the app, ever.** Admins relay approvals/resets via NIH's internal email manually. No SMTP, no mail-sending code.
- **Session timeout: 15 minutes idle.** Lockout: 5 failed login attempts → 15-minute lockout.
- **Order IDs are sequential, never reused**, even for canceled orders.
- **No per-order/per-period quantity limits.**
- **Deactivating a customer never hides historical orders.** Pending orders at deactivation are left alone for staff to handle manually — never auto-canceled.
- **Admin can trigger password resets but never views or sets the actual password.** Reset generates a one-time temp password that forces a change + strength check on next login.

## Roles

| Role | Access |
|------|--------|
| `customer` | Place orders, view own lab's orders, add public comments, cancel own pending orders |
| `staff` | Process orders in their assigned category only, accept/modify/complete/cancel/return, add public comments + internal notes |
| `admin` | Everything staff can do, plus manage compounds/categories/isotopes/delivery options/customers/staff/institutes, run reports, approve registrations |

Role is determined by which table a `user_id` appears in (`customers`, `staff`, `admins`) — `users` itself has no role column.

## CSS Architecture

- **style.css:** System fonts (`-apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif`), reset, color tokens, typography
- **components.css:** Forms, buttons, cards, tables, alerts, badges, utilities, responsive breakpoints, accessibility
- **layout.css:** Sidebar (sticky, collapse on desktop, off-canvas on mobile), topbar, grid layout, dark mode hooks

**No role-specific CSS files.** All three roles share the same component library.

**Dark mode:** Not implemented right now. Tokens may exist in CSS for future use but no toggle is wired up.

**Sidebar collapse (desktop only):** Pre-paint script reads `localStorage['petcom:sidebar']` and sets `data-sidebar="collapsed"` on `<html>`. CSS changes `--sidebar-width`. Mobile sidebar (off-canvas) uses `data-sidebar-mobile="open"` on `<html>` instead — a separate, independent state.

## Git Workflow

Branch → PR → merge. Never push directly to `main`.

## Deployment Target

- **RHEL 8** (PHP 7.4, MariaDB 10.11)
- **No root access.** Hand off as a package: schema file + app files + config template + deployment doc.
- **HTTPS with self-signed cert locally; real cert on RHEL (handed off by IT).**
- **No external CDN.** All assets (CSS, JS, icons) inlined or local.

## Build Phases

PETCOM is built in lettered phases (A–F); the detailed phase/sub-phase plan is
tracked outside this file, not here — this section is intentionally just a
high-level status marker so it doesn't need editing every time a sub-phase
ships. Current status: **A and B are complete. C is in progress. D, E, and F
have not started.**

---

**Before building anything:** This file is the source of truth. If something in code contradicts it, fix the file first, then the code.