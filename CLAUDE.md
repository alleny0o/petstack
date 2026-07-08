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
    login.php
    customer/
    staff/
    admin/
    assets/
      css/
        style.css      (tokens + base + typography)
        components.css (forms, buttons, cards, tables, alerts)
        layout.css      (sidebar, topbar, grid)
      js/
        script.js      (sidebar toggle, theme toggle, form validation)

  src/                 # Above web root — never servable by URL
    config.php          (DB credentials, gitignored)
    config.sample.php   (template)
    db.php              (PDO connection)
    auth.php            (login, require_role(), session guard)
    helpers.php          (CSRF, escaping, redirects)
    partials/
      head.php
      layout_customer.php
      layout_staff.php
      layout_admin.php

  sql/
    schema.sql          (all 20 tables)
    seed.sql            (test data)

  tools/
    set_temp_passwords.php (one-time setup for seeded accounts)

  docs/
    SCHEMA.md            (table descriptions in plain English)
    DEPLOY.md            (RHEL 8 deployment steps)
```

## Three Security Rules

1. **Only `public/` is servable.** DB credentials live in `src/`.
2. **Every protected page gates itself near the top:**
   ```php
   session_start();
   require __DIR__ . '/../src/auth.php';
   require_role('customer'); // or 'staff', 'admin'
   ```
   Public pages (login.php, register.php) don't call `require_role()`.
3. **Always use `__DIR__` in require paths** — relative paths break when deployed to RHEL.

## Page Template Pattern

```php
<?php
session_start();
require __DIR__ . '/../src/auth.php';
require_role('customer');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $pageTitle = 'Page Name'; include '../src/partials/head.php'; ?>
</head>
<body>
    <div class="app-shell">
        <?php include '../src/partials/layout_customer.php'; ?>
        <main class="app-main">
            <!-- content here -->
        </main>
    </div>
</body>
<script src="/assets/js/script.js" defer></script>
</html>
```

## Database — 20 Tables

Built in this order (foreign keys matter):

**Identity (8 tables):**
1. `institutes`
2. `labs`
3. `pis`
4. `lab_pis` (join: a lab can have multiple PIs, a PI can oversee multiple labs)
5. `users` (shared login table: username, password_hash, must_change_password, failed_login_count, locked_until — used by all three roles)
6. `customers` (extends `users` via `user_id`; institute/lab/supervising_pi locked at approval)
7. `staff` (extends `users` via `user_id`; has a single `category_id` — one category per staff member, not a junction table)
8. `admins` (extends `users` via `user_id`)

**Menu (6 tables):**
9. `isotopes`
10. `categories` (e.g. Radiopharmacy, Cyclotron — real table, admin-editable, referenced by both `staff.category_id` and `compounds.category_id`)
11. `compounds`
12. `compound_isotopes` (join: usually 1:1, occasionally a compound allows multiple isotopes)
13. `delivery_options`
14. `compound_delivery_options` (join: each compound lists its own allowed delivery methods)

**Orders (6 tables):**
15. `orders`
16. `order_type_a_details` (dose orders: activity_mci, requested_datetime)
17. `order_type_b_details` (cyclotron orders: either beam_current+bombardment_minutes OR eob_activity_mci+eob_datetime, never both)
18. `order_public_comments` (append-only, visible to customer + staff)
19. `order_internal_notes` (append-only, staff-only)
20. `order_audit_log` (status changes only — pending→accepted→completed/canceled, timestamp, who — not field-level diffing)

See `sql/schema.sql` for exact columns and constraints.

## Business Rules (Non-Negotiable)

These came from the requirements interview. Don't simplify them.

- **No phone-in orders.** Customers place their own orders only. No `is_phone_in` field, no attestation.
- **No separate registration-requests table.** Registration status lives directly on `customers` (`registration_status`: pending/approved/rejected).
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

## Phase 1 (Current — Building Now)

Build and test, in order:
1. `sql/schema.sql` — all 20 tables
2. `sql/seed.sql` — minimal test data (2 institutes, 3 labs, 2 PIs, 1 admin, 2 staff in different categories, 3 customers, a handful of compounds/isotopes/delivery options)
3. `src/config.sample.php`, `src/db.php` — PDO connection
4. `src/auth.php` — login (checks `users` + role tables), `require_role()`, session timeout (15 min), lockout (5 attempts)
5. `src/helpers.php` — CSRF tokens, HTML escaping, redirects
6. `public/login.php` — login form, redirects to correct role dashboard
7. Forced password change on first login (`must_change_password` flag), strong password validation
8. `tools/set_temp_passwords.php` — one-time bcrypt hash setup for seeded accounts

**No mock data. No stub pages beyond what's needed to prove login works end-to-end.**

## Phases 2–6 (Future — Not Yet Started)

2. Customer order form (Type A & B, isotope-first filtering, lead-time validation)
3. Staff processing queue + accept/modify/complete/cancel/return actions
4. Admin panels (compounds, categories, isotopes, delivery options, institutes, labs, PIs, staff, customer approval)
5. Reports (order history, cost/accounting, audit trail, pending orders, user activity, compound usage — CSV export)
6. Polish (mobile CSS pass, HTTPS self-signed cert, RHEL deployment docs)

---

**Before building anything:** This file is the source of truth. If something in code contradicts it, fix the file first, then the code.