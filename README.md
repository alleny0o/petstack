# PETStack

Web-based ordering system for the NIH Clinical Center PET Department.
PHP + MariaDB. Replaces the legacy HSS system — real access control,
error-checking, audit trail. Internal network only, no cloud dependencies.

---

## Requirements

PHP 8.x + MariaDB. Locally: **MAMP** (Mac) or **XAMPP** (Windows) — both
bundle Apache + PHP + a database, app runs the same either way.

> XAMPP ships MariaDB by default — already matches the server. MAMP ships
> MySQL by default; swap it for MariaDB if you want to match exactly.

---

## Setup

1. Clone into your stack's web area.
2. Create the `petstack` database, load `sql/schema.sql` then `sql/seed.sql`.
3. Copy `src/config.sample.php` → `src/config.php`, fill in your values
   (table below). Gitignored — everyone makes their own.
4. Run `tools/set_temp_passwords.php` once (sets temp passwords for seeded accounts).
5. Point Apache's document root at `public/`. (PHP's built-in server works
   for quick checks but ignores `.htaccess` — use Apache to test HTTPS/error pages.)
6. Log in at `/login.php` with a seeded account — forced password reset on first login.

---

## Config values

|                          | MAMP    | XAMPP   | NIH server (RHEL) |
|--------------------------|---------|---------|--------------------|
| DB port                  | `8889`  | `3306`  | `3306`             |
| DB root password         | `root`  | (blank) | IT provides        |
| `REQUIRE_SECURE_COOKIES` | `false` | `false` | `true`             |

`false` locally (plain HTTP). **Must** be `true` on the server (real HTTPS)
or sessions break — don't forget to flip it at deploy time.

---

## Deploying to the NIH server (RHEL)

**Not done yet** — this is what we know, not a tested runbook.

Confirmed with IT (Charles): Apache + PHP + MariaDB on RHEL. Beyond that,
nothing's actually been deployed — treat the rest as a starting point.

Should carry over without code changes: `src/` lives outside the web root,
paths resolve via `__DIR__`, so in theory only the config table above
changes per environment. Confirm once we have server access.

Known gotchas:
- **PHP version** — built on 8.x, RHEL 8 ships 7.4 (or 8.0/8.1 via module
  streams). Nothing bleeding-edge, but unconfirmed on the actual target.
- Apache root → `public/`, TLS cert installed, `petstack` DB + schema loaded.

Update this section with what actually worked once it's real.

---

## Project layout

`docs/STRUCTURE.md` — folders + the rules that keep it secure/portable.
`docs/SCHEMA.md` — the database, in plain English.