# PETStack

A web-based ordering system for the NIH Clinical Center PET Department.
Built with PHP and MySQL.

Replaces the legacy HSS PET Ordering System with robust error-checking,
real access control, and an audit trail. Runs entirely on an internal
network, with no external or cloud dependencies.

---

## Requirements

PHP 8.x and MySQL. Locally that comes from an all-in-one stack:

- **MAMP** (Mac)
- **XAMPP** (Windows)

Both bundle Apache + PHP + a database together. The app runs identically
under either; only a couple of config values differ (see below).

> **Note:** the database is MySQL. XAMPP ships MariaDB by default (it calls
> the command `mysql`). That's fine for this app, but if you want your local
> engine to match the server exactly, point XAMPP at MySQL.

---

## Setup

1. **Clone the repo** into your stack's web area.

2. **Create the database and load the schema:**
   - Make a database named `petstack`.
   - Load `sql/schema.sql`, then `sql/seed.sql` (sample data + test accounts).

3. **Make your config file:**
   - Copy `src/config.sample.php` to `src/config.php`.
   - Fill in the values for your stack (table below).
   - `config.php` is gitignored on purpose; it holds your DB password and
     is never committed. Everyone makes their own.

4. **Set real passwords:**
   - Run `tools/set_temp_passwords.php` once. It sets working temp passwords
     for the seeded accounts (the seed file can't ship real password hashes).

5. **Serve it:**
   - Point your stack's Apache document root at the `public/` folder.
   - For quick page testing you can also run PHP's built-in server, but note
     it ignores `.htaccess`, so HTTPS redirect / error pages / file-blocking
     won't apply. Use Apache to test those.

6. **Log in** at `/login.php` with a seeded account. You'll be forced to set
   a new password on first login.

---

## Config values: fill the slot for your stack

`config.php` needs a few values that differ per environment. Set yours to match:

|                       | MAMP (Mac) | XAMPP (Windows) | NIH server (RHEL) |
|-----------------------|------------|-----------------|-------------------|
| MySQL port            | `8889`     | `3306`          | `3306`            |
| MySQL root password   | `root`     | (blank)         | IT provides       |
| `REQUIRE_SECURE_COOKIES` | `false` | `false`         | `true`            |

The idea: the README tells you *which slot to fill*, not one person's answer.
That's why config is per-person and not in git; each stack fills the slots
differently, and that's fine.

`REQUIRE_SECURE_COOKIES` is `false` locally because you're on plain HTTP. It
**must** be `true` on the server, which has real HTTPS; otherwise sessions
behave incorrectly. Don't forget to flip it at deploy time.

---

## Deploying to the NIH server (RHEL)

Stack confirmed with IT (Charles): PHP on Apache, backed by a database,
all on Red Hat Linux. The team's decision is to use **MySQL** as the engine.

The app is built to move without code changes. Because `src/` lives outside
the web root and all paths are resolved with `__DIR__`, the folder can be
dropped anywhere and just needs Apache aimed at `public/`.

What changes on our side: the three config values above (port, password,
secure cookies). That's it.

What the server needs:
- Apache (`httpd`), PHP, and MySQL.
- Apache document root pointed at this project's `public/` folder.
- The PHP module and PDO MySQL extension enabled.
- The internal TLS certificate installed (the app expects HTTPS).
- The `petstack` database created and `sql/schema.sql` loaded.

> **MySQL on RHEL:** RHEL's default repos provide MariaDB. **MySQL comes from
> a separate MySQL repo** that has to be added at install time. Since we chose
> MySQL specifically, make sure whoever installs it adds the MySQL repo rather
> than the default MariaDB package.

**PHP version checkpoint:** built and tested against PHP 8.x. RHEL 8 ships
PHP 7.4 (or 8.0/8.1 via module streams). Nothing here uses bleeding-edge
syntax, so this should be fine; just confirm there are no syntax issues on
the target PHP version as a known checkpoint, not a surprise.

---

## Project layout

See `docs/STRUCTURE.md` for the folder structure and the three rules that
keep it secure and portable. See `docs/SCHEMA.md` for the database in plain
English.