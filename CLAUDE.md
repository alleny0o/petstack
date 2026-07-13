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
      dashboard.php
      registrations.php
      customers.php
      customer_detail.php
      accounts.php         (D.2: unified staff+admin list)
      account_detail.php   (D.2: view/edit category/deactivate/reset password)
      account_create.php   (D.2: create a staff or admin account)
    assets/
      css/
        style.css        (tokens + base/reset + typography + accessibility)
        layout/
          shell.css       (app-shell grid, header/main/footer bindings)
          sidebar.css     (sidebar: collapse, submenu, flyout, mobile off-canvas)
        components/
          auth.css
          page-structure.css   (page header, cards, detail-list)
          forms.css
          buttons.css
          tables.css
          alerts.css            (incl. temp-password banner)
          badges.css
          utilities.css
          toasts.css
          modals.css
          feedback.css          (spinners, empty states)
          dashboard.css         (stat tiles, panel grid, masonry)
          radio-cards.css
          order-page.css        (baseline for future order-page work)
      js/
        script.js        (single file, no bundler — sidebar collapse +
                          mobile off-canvas toggle, toasts, confirm modals,
                          form-submit loading, copy-to-clipboard; one
                          DOMContentLoaded init block)

  src/                 # Above web root — never servable by URL
    config.php          (DB credentials, gitignored)
    config.sample.php   (template)
    db.php              (PDO connection)
    auth.php            (login, require_role(), session guard)
    helpers.php          (session bootstrap, CSRF, escaping, redirects,
                          toast_flash, field_error/field_class)
    partials/
      head.php
      layout_customer.php
      layout_staff.php
      layout_admin.php

  sql/
    schema.sql          (see docs/SCHEMA.md)
    seed.sql            (test data)

  docs/
    SCHEMA.md            (full DB schema: tables, build status, FK structure)
    STYLEGUIDE.md         (CSS file structure + UI feedback conventions)

  tools/
    set_temp_passwords.php (one-time setup for seeded accounts)
```

`docs/` holds reference material split out of this file — see `docs/SCHEMA.md` and `docs/STYLEGUIDE.md` below. `DEPLOY.md` is still expected to show up there as part of the deployment-polish phase; not yet written.

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

See `docs/SCHEMA.md` for the full schema (tables, build status, FK-safe build order, per-table notes).

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

See `docs/STYLEGUIDE.md` for file structure and UI feedback conventions.

## Git Workflow

Branch → PR → merge. Never push directly to `main`.

## Deployment Target

- **RHEL 8** (PHP 7.4, MariaDB 10.11)
- **No root access.** Hand off as a package: schema file + app files + config template + deployment doc.
- **HTTPS with self-signed cert locally; real cert on RHEL (handed off by IT).**
- **No external CDN.** All assets (CSS, JS, icons) inlined or local.

## Build Phases

PETCOM is built in lettered phases (A–K); the detailed phase/sub-phase plan is
tracked outside this file, not here — this section is intentionally just a
high-level status marker so it doesn't need editing every time a sub-phase
ships.

- **A** — Role/schema foundation — complete
- **B** — Core auth hardening — complete
- **C** — Self-registration + admin approval — complete
- **D** — Account management — in progress (D.1 customer management complete,
  D.2 staff/admin management complete, D.3 institute/lab/PI CRUD not started —
  deprioritized indefinitely, current manual seeding is sufficient for now)
- **E** — Dashboards + staff/admin toggle — in progress (admin dashboard
  complete, S/A toggle complete; staff/customer dashboard polish deferred
  until Phase G ships, since dashboards need real order data to be meaningful
  rather than empty shells)
- **F** — Catalog/menu schema + seed data (isotopes, compounds, delivery
  options, institute custom lists) — not started, next up
- **G** — Order core — not started (G.1 delivery locations + product users
  CRUD, customer-managed; G.2 orders/order_type_a_details/order_type_b_details
  schema; G.3 Type A order form)
- **H** — Staff order processing UI — not started
- **I** — Audit logging — not started (moved up from its original
  end-of-plan position; real order status changes should be logged as soon as
  they exist, since admin/staff both operate on sensitive order data)
- **J** — Type B (cyclotron) order form — not started
- **K** — Admin catalog config UI + optional order-form preview (stretch) —
  not started

**Current status: A–C complete. D and E in progress. F next.**

## Verification Policy

Claude Code must NOT start background servers, spin up scratch/temp MySQL 
instances, or run live HTTP verification (curl, PHP built-in server, etc.) 
as part of any task, even for 'verification' purposes. This includes 
resetting temp passwords or modifying any database, scratch or otherwise, 
without explicit instruction.

Verification must be limited to: php -l (syntax check), static code 
review/diffs, and grep-based checks (e.g. confirming no leftover references 
after a rename). The user will handle all live browser-based testing 
themselves, manually, in their own MAMP environment. This is a firm rule, 
not a suggestion — do not deviate from it even if it seems more thorough 
to test live.

---

**Before building anything:** This file is the source of truth. If something in code contradicts it, fix the file first, then the code.