# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Stack

Vanilla PHP + PDO + MariaDB. No framework, no Composer, no build step. `node_modules/lucide` is present as an icon reference but SVGs are inlined directly — there are no npm scripts to run.

## Target server (confirmed via SSH)

- **RHEL 8**
- **PHP 7.4.33** (cli, NTS)
- **MariaDB 10.11** (server version; client tool reports a different `15.1-distrib` number — that's normal, the server version is what matters)
- No root/sudo access on this server — deployment must be handed off as a self-contained package (schema file + app files + config + docs), not installed by us directly.

**Important: write PHP 7.4-compatible code only.** No PHP 8+ syntax — that means no named arguments, no enums, no `match` expressions, no constructor property promotion, no union types, no nullsafe operator (`?->`). If in doubt, target 7.4.

## Local dev setup

MAMP (Mac) or XAMPP (Windows) — both bundle Apache + PHP + a database as self-contained binaries, so local setup doesn't depend on what's already installed on your machine or your OS version.

1. Create a `petstack` database and load `sql/schema.sql`, then `sql/seed.sql`.
2. Copy `src/config.sample.php` → `src/config.php` and fill in your values (`src/config.php` is gitignored).
3. Run `tools/set_temp_passwords.php` once to set temp passwords for seeded accounts.
4. Point Apache's document root at `public/`. PHP's built-in server ignores `.htaccess` — use Apache (via MAMP/XAMPP), not `php -S`.
5. Log in at `/login.php`.

**When installing MAMP/XAMPP, select PHP 7.4.x** to match the RHEL server. Don't default to the newest bundled version.

Config ports: MAMP uses DB port `8889`; XAMPP and the NIH server use `3306`. Set `REQUIRE_SECURE_COOKIES` to `false` locally, `true` on the NIH server (HTTPS only).

MAMP's bundled database is MySQL, not MariaDB — this is fine, they're wire-compatible for standard SQL/PDO usage at the level this project uses. Just avoid reaching for anything MariaDB-10.11-specific or MySQL-8-specific, since neither is guaranteed to exist on the other.

## Architecture

### Directory layout

- `public/` — the only web-reachable folder (Apache doc root). Pages are flat (no role subfolders). Static assets in `assets/css/` and `assets/js/`.
- `src/` — above the web root; never servable by URL. Holds config, DB connection, auth, helpers, and partials.
- `sql/` — schema and seed data.
- `docs/STRUCTURE.md` — folder rules. `docs/SCHEMA.md` — database in plain English.

### src/ files

| File | Purpose |
|------|---------|
| `config.php` | DB credentials (gitignored) |
| `config.sample.php` | Template teammates copy |
| `db.php` | PDO connection (not yet created) |
| `auth.php` | Login, `require_role()`, session guard (not yet created) |
| `helpers.php` | CSRF, escaping, redirects (not yet created) |
| `partials/head.php` | `<head>` contents + pre-paint dark-mode/sidebar script |
| `partials/layout_customer.php` | Customer sidebar, mobile topbar (hamburger), backdrop |
| `partials/layout_staff.php` | Staff sidebar (same structure) |
| `partials/layout_admin.php` | Admin sidebar (same structure) |

### Page template pattern

Every page with a sidebar follows this structure:

```php
<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $pageTitle = 'Page Name'; include '../src/partials/head.php'; ?>
</head>
<body>
    <div class="app-shell">
        <?php include '../src/partials/layout_customer.php'; ?>
        <main class="app-main">
            <!-- content -->
        </main>
    </div>
</body>
<script src="assets/js/script.js" defer></script>
</html>
```

Role-specific sidebar partials (`layout_staff.php`, `layout_admin.php`) follow the same pattern as `layout_customer.php`.

### Three security rules

1. **Only `public/` is servable.** DB credentials live in `src/`.
2. **Every protected page gates itself near the top:**
   ```php
   require __DIR__ . '/../src/auth.php';
   require_role('customer'); // or 'staff', 'admin'
   ```
   Pre-login pages (`login.php`, `register.php`, `reg_status.php`) are exempt. Check the permission map with: `grep -n require_role public/*.php`
3. **Always use `__DIR__` in require paths** — relative paths break on RHEL.

### Dark mode and sidebar state

`head.php` injects a pre-paint script that reads `localStorage` and applies data attributes on `<html>` before the page renders:

- `localStorage['petstack:theme'] === 'dark'` → `document.documentElement.dataset.theme = 'dark'`
- `localStorage['petstack:sidebar'] === 'collapsed'` → `document.documentElement.dataset.sidebar = 'collapsed'`

CSS uses these attributes (`[data-theme="dark"]`, `[data-sidebar="collapsed"]`) for all theme and sidebar-collapse styling.

## Database

21 tables in MariaDB. Build order matters for foreign keys — see `docs/SCHEMA.md`. Key relationships:

- `institutes` → `labs` → `customers` → `orders`
- `orders` pick one `compound`, one `isotope`, one `delivery_option`
- `orders` have either `order_type_a_details` (dose) or `order_type_b_details` (cyclotron), never both
- Identity: one shared `users` account table (login, password hash, lockout), extended by the role tables `customers` (self-register, admin approves), `staff`, and `admins` via `user_id`
- Junction tables: `lab_pis`, `compound_isotopes`. A staff member's category is a column on `staff`, not a junction table

Status values: `pending` → `accepted` → `completed` / `canceled`. Return sends back to `pending` (audit log records it).

## Roles

| Role | Description |
|------|-------------|
| `customer` | Places and views orders for their lab |
| `staff` | Processes orders in their assigned categories only |
| `admin` | Everything + config, reports, account management |

(In the database, `users` is the shared account table all three roles extend — it is not a role name. The staff role's table is `staff`.)

## Domain / business rules

These decisions came out of a detailed requirements interview and aren't obvious from the schema alone. Treat them as intentional constraints, not things to "clean up" or simplify.

**Orders**
- Type A (finished dose, mCi, needed at a specific pickup date/time) and Type B (cyclotron target run, either beam-current×time OR EOB-activity+date/time — never both) are fully independent order types. Never model one as a parent/child of the other.
- Order IDs are a single sequential counter across both types, always incrementing, **never reused**, even for canceled orders.
- Activity entered on a Type A order means "activity in hand at pickup time." The system does **not** do decay back-calculation — that's a manual/staff task outside the software.
- Each compound has its own minimum lead time (hours), not a global one. Validate the requested date/time against the specific compound's lead time at order submission.
- **Completed orders are terminal.** They cannot be canceled or modified. Enforce this in application logic (the status-transition function), not just by hiding UI buttons.
- **Returning an order sends it back to `pending`** — there is no separate "returned" status. The audit log is what preserves the fact that a return happened.
- No per-order or per-period quantity limits. The processing staff member can adjust amounts as needed.

**Ordering flow**
- Customer picks an **isotope first**, then sees only compounds compatible with that isotope — not the reverse.
- Delivery options are **per-compound**, not global. Each compound has its own allowed subset (will-call, direct-to-lab, through pharmacy, etc.).

**Cost**
- Each compound has a standard cost (flat, per order/per compound — not multiplied by quantity).
- Cost is **snapshotted onto the order at creation time** (`orders.cost_snapshot`). If a compound's standard cost changes later, historical orders and reports must **not** be affected.
- Cost is hidden from customers everywhere, including exports. Cost/accounting reports are admin-only.

**Comments and audit**
- Two separate, append-only comment threads per order: `order_public_comments` (visible to customer and staff — used for in-system back-and-forth) and `order_internal_notes` (staff-only, customer never sees these). Neither is a single overwritable field — both are running histories.
- Audit logging is **status-change level only** (pending → accepted → completed/canceled, timestamp, who) — not field-by-field diffing. This was a deliberate simplicity tradeoff; don't expand it without checking first.
- "Order modified by staff" notification to the customer is just a visible indicator (e.g. comparing `last_modified_at` to when the customer last viewed the order) — no explicit read-acknowledgment tracking needed.

**Accounts and access**
- Customers self-register via a form; the request sits pending until an admin manually verifies and approves it. Institute/lab/PI affiliation is locked at approval and can only be changed later by an admin — never editable by the customer themselves, including at order time.
- Each customer has exactly one `supervising_pi`. If the customer is themselves a PI, their own supervisor goes in that field instead.
- Staff are granted permissions by **category** (e.g. "Radiopharmacy," "Cyclotron"), not per-item. A staff member can only process orders for compounds in their assigned categories.
- Admins are super-users: everything staff can do, plus config/account/report management.
- Deactivating a customer blocks new orders but must **never** hide their historical orders from reports. If they have pending orders when deactivated, leave those orders alone for staff to handle manually — don't auto-cancel.
- Passwords: admin can trigger a reset (generates a one-time temp password) but can **never** view or set the actual password. Temp passwords force a change + strength check on next login, then are invalidated.
- Session timeout: 15 minutes idle. Lockout: 5 failed login attempts.

**No email integration, ever**
- The application must never attempt to send email (no SMTP, no mail relay). Registration approval/rejection and password resets are relayed to customers manually by an admin using NIH's own internal email system, entirely outside this app. Don't add email-sending code even as a "nice to have."

## Git workflow

Branch → PR → merge. Never push directly to `main`.