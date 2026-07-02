# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Stack

Vanilla PHP 8.x + PDO + MariaDB. No framework, no Composer, no build step. `node_modules/lucide` is present as an icon reference but SVGs are inlined directly — there are no npm scripts to run.

## Local dev setup

MAMP (Mac) or XAMPP (Windows) — both bundle Apache + PHP + a database.

1. Create a `petstack` database and load `sql/schema.sql`, then `sql/seed.sql`.
2. Copy `src/config.sample.php` → `src/config.php` and fill in your values (`src/config.php` is gitignored).
3. Run `tools/set_temp_passwords.php` once to set temp passwords for seeded accounts.
4. Point Apache's document root at `public/`. PHP's built-in server ignores `.htaccess` — use Apache.
5. Log in at `/login.php`.

Config ports: MAMP uses DB port `8889`; XAMPP and NIH server use `3306`. Set `REQUIRE_SECURE_COOKIES` to `false` locally, `true` on the NIH server (HTTPS only).

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
| `partials/sidebar_customer.php` | Customer sidebar, mobile topbar (hamburger), backdrop |

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
        <?php include '../src/partials/sidebar_customer.php'; ?>
        <main class="app-main">
            <!-- content -->
        </main>
    </div>
</body>
<script src="assets/js/script.js" defer></script>
</html>
```

Role-specific sidebar partials (`sidebar_admin.php`, etc.) follow the same pattern as `sidebar_customer.php`.

### Three security rules

1. **Only `public/` is servable.** DB credentials live in `src/`.
2. **Every protected page gates itself near the top:**
   ```php
   require __DIR__ . '/../src/auth.php';
   require_role('customer'); // or 'user', 'admin'
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
- Three identity tables: `customers` (self-register, admin approves), `users` (staff), `admins`
- Junction tables: `lab_pis`, `user_categories`, `compound_isotopes`, `compound_delivery_options`

Status values: `pending` → `accepted` → `completed` / `canceled`. Return sends back to `pending` (audit log records it).

## Roles

| Role | Description |
|------|-------------|
| `customer` | Places and views orders for their lab |
| `user` | Staff; processes orders in their assigned categories only |
| `admin` | Everything + config, reports, user management |

## Git workflow

Branch → PR → merge. Never push directly to `main`.
