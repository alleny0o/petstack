# PETCOM Security Audit

**Date:** 2026-07-21
**Scope:** Full static review of the codebase — all 29 PHP files under `public/`, everything under `src/` and `tools/`, `public/assets/js/script.js`. All 184 PDO call sites, every POST handler and form, and every page's role guard were individually reviewed. Read-only pass: no code was changed. Per the project verification policy, no live HTTP/DB testing was performed — findings are from code reading, grep sweeps, and diff-level analysis only.

## Summary

| Severity | Count | Findings |
|---|---|---|
| Critical | 0 | — |
| High | 2 | Config-loading wiring makes production config a no-op (3.1); hardcoded root DB credentials (3.2) |
| Medium | 5 | bfcache after logout (3.3, known); no global exception handler (3.4); CSV formula injection (4.1); unauthenticated registration-status oracle (5.2); PHP 8-only functions on the PHP 7.4 target (5.1) |
| Low | 10 | See sections below |
| Info | 6 | See sections below |

**Positive results up front:** no SQL injection anywhere (all dynamic query building is whitelist + bound-parameter based); CSRF coverage is complete and correctly ordered on every mutating handler; every route enforces its role server-side; no IDOR — ownership/lab scoping is enforced inside the SQL itself, not just in page-level gates; no file uploads or user-controlled file paths exist.

---

## 1. SQL Injection Surface

**Result: clean. No injection found.** Every statement is either static SQL with bound values, or a dynamic builder whose *structure* comes only from hardcoded literals/whitelists with all *values* bound.

Positive confirmations for the dynamic-query areas specifically requested:

- `public/staff/orders.php` — WHERE array built at 90–118 from literal fragments with `?` placeholders (search 93–104, fulfillment 105–108, dates 109–116); status/fulfillment strictly `in_array(..., true)`-whitelisted (20–23); dates regex-validated (24–27). ORDER BY (line 175) is selected from three hardcoded literals (144–150). `LIMIT $offset, $pageSize` (176) interpolates only server-computed ints — page size whitelisted against `[10, 20, 50, 100]` (29–30), page `ctype_digit`-checked and clamped (28, 160–162).
- `public/customer/orders.php` — same template (WHERE 78–124, list 159–170); `$labId` comes from a session-keyed DB lookup with int cast, bound as a join param (15–17, 80). ORDER BY is the literal `o.order_id DESC`.
- `public/admin/reports.php` — no dynamic SQL at all; three static lookups (13–15), form GETs to the export.
- `public/admin/export_csv.php` — filter clauses appended as static strings with named placeholders only (32–66), conditionally bound under the exact same conditions (74–78); `status`/`chargeable` whitelisted, ids `ctype_digit`→int (15–19); ORDER BY literal (66).
- Admin CRUD lists (`customers.php`, `accounts.php`, `products.php`, `labs.php`, `nuclides.php`, `institutes.php`, `pis.php`) — all follow the same safe template: LIKE with bound params, id filters `ctype_digit`→int, status tabs applied as hardcoded literal fragments chosen by whitelisted values, LIMIT from server-computed ints.
- `public/admin/labs.php` IN(...) construction (three sites: 96–98, 239–241, 374–390) — placeholders built with `array_fill(0, count(...), '?')` over id lists that are `ctype_digit`-filtered or DB-sourced ints; values bound.
- `public/customer/lab_product_users.php` / `lab_delivery_locations.php` — every query scoped by the session-derived int `$labId`, all values bound.
- `src/auth.php:215` — `"SELECT 1 FROM {$table}"` interpolates from the hardcoded role→table map at 212, never user input. Lines 179/204 concatenate `PASSWORD_HISTORY_LIMIT`, a compile-time int constant.
- `src/helpers.php` — all 16 statements static with bound values, including `transition_order_status()` (444–475).
- LIKE handling is consistently correct at all 11 search sites: `\`, `%`, `_` escaped in the user term, pattern passed as a bound parameter, `ESCAPE '\\'` declared in the SQL. No wildcard injection.

Findings:

- **[Info]** `public/customer/lab_product_users.php:238`, `public/customer/lab_delivery_locations.php:202` — leftover `[PU-DEBUG]`/`[LOC-DEBUG]` `error_log()` calls write the assembled SQL text (with interpolated LIMIT/OFFSET) to the server log on every list load; marked temporary in-code. *Fix:* remove the debug blocks. (The adjacent `print_r` of row data is a Low PII finding — see 5.7.)
- **[Info]** `src/db.php` — `PDO::ATTR_EMULATE_PREPARES` left at its default (`true`). Not exploitable here (utf8mb4 in the DSN, all values bound), but server-side prepares are the defensive default. *Fix:* add `PDO::ATTR_EMULATE_PREPARES => false` to the connection options.

---

## 2. CSRF Coverage

**Result: clean.** Every file that reads `$_POST` calls `verify_csrf()` as the **first statement** of its POST block, before any DB or session mutation, and every `method="post"` form embeds `csrf_field()`. `verify_csrf()` (`src/helpers.php:42–50`) compares with `hash_equals` against a 32-byte `random_bytes` session token.

Positive confirmations for the specifically requested surfaces:

- `public/staff/order_detail.php` — `verify_csrf()` at line 73 gates the entire POST block: accept/return/complete/reopen via `transition_order_status()` (91–118), cancel (119–137), **chargeable toggle** (138–147), staff notes (87–88). All seven action forms carry the token.
- `public/customer/order_detail.php` — verify at 81; notes edit (97), details edit (142), cancel (184) all after it; all three forms tokened.
- Admin CRUD modals — verified per file: `nuclides.php` L84, `products.php` L67, `institutes.php` L85, `labs.php` L116, `pis.php` L89, `accounts.php` L73, `account_detail.php` L51, `customer_detail.php` L73, `registrations.php` L25. Every modal/inline form emits `csrf_field()`. `admin/customers.php` is list-only (no POST) — correct.
- `public/customer/lab_delivery_locations.php` (L86), `lab_product_users.php` (L115), `login.php` (L15), `register.php` (L24), `change_password.php` (L11), `account_profile.php` (L27) — all verified.
- **AJAX:** the only JS-initiated mutation in the codebase is `src/partials/new_order_form.php:382` — `fetch(form.action, {method: 'POST', body: new FormData(form)})`, which carries the hidden `csrf_token` input from line 33; the endpoint (`public/customer/new_order.php`) verifies at line 16 before any DB work. `script.js` contains zero fetch/XHR calls. No CSRF bypass path exists.
- `public/admin/export_csv.php` / `reports.php` — the export is a GET with zero mutations (pure SELECT behind `require_role('admin')`); no CSRF needed, GET is appropriate.

Findings:

- **[Low]** `public/logout.php` (whole file) — logout fires on any GET with no CSRF token; linked as a plain `<a>` in all three layouts. A cross-site `<img src>` can forcibly log users out (nuisance-level; no data mutation). *Fix:* convert the logout link to a small POST form with `csrf_field()` + `verify_csrf()`, or accept as known-low.
- **[Info]** `public/customer/new_order.php:16` — a CSRF failure on this JSON endpoint returns the plain-text 403 die, not JSON; the client handles it defensively (`new_order_form.php:394`), so UX-only. *Fix (optional):* emit a `{ok:false}` JSON body for this endpoint.
- **[Info]** CSRF token is not rotated at login — `session_regenerate_id(true)` (`src/auth.php:62`) changes the session id but the pre-login `$_SESSION['csrf_token']` carries over. *Fix (hardening):* `unset($_SESSION['csrf_token'])` on successful login.

---

## 3. Authentication / Session Handling

### 3.1 [High] Production security config can never load — `src/helpers.php:12`

`helpers.php` does `require_once __DIR__ . '/config.sample.php';` and runs on every page **before** `db.php` pulls in `config.php`. PHP's `define()` never overwrites an existing constant, so the sample's values always win — including `REQUIRE_SECURE_COOKIES = false` (`config.sample.php:45`). Consequence: on the HTTPS RHEL deployment, session cookies will ship **without the Secure flag no matter what `config.php` says**, and every constant in the real config is dead. *Fix:* make `helpers.php` require `config.php` (or nothing, letting `db.php` load it) and never include the sample at runtime.

### 3.2 [High] Hardcoded root DB credentials — `src/db.php:23–37`

The active `get_db()` hardcodes `root`/`root` @ `127.0.0.1:8889` ("Hardcoding MAMP values directly to bypass config.php entirely"); the real config-driven implementation is commented out at lines 8–21. Credentials live in a tracked file, and the app cannot be pointed at the RHEL database at all. *Fix:* restore the `DB_HOST`/`DB_USER`/`DB_PASS` constant version and delete the hardcoded block. (Pairs with 3.1 — both halves of the config layer are currently bypassed.)

### 3.3 [Medium] bfcache serves authenticated pages after logout (known issue, confirmed by manual testing)

After logout, the browser back button restores a cached render of the last authenticated page instead of re-requesting it. Root cause: the app never sends cache-control headers (grep: no `Cache-Control` anywhere in `public/` or `src/`), so the browser's back/forward cache legitimately serves the stored render. Two-part fix:

- **(a) Server headers, one central place.** The shared logic every authenticated page runs through is `require_role()` in `src/auth.php` — each page calls it directly (line 5–10 of every guarded page) *before any output*, and the layouts (`layout_customer.php`/`layout_staff.php`/`layout_admin.php`) render afterward with no auth logic of their own. The correct insertion point is the **end of `require_role()`, after all checks pass** — alongside `$_SESSION['last_activity'] = time();` at `src/auth.php:133`:
  ```php
  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
  header('Pragma: no-cache');
  ```
  Placing it after the checks (not at the top) means the headers are only stamped on pages that actually render authenticated content; the earlier branches all `redirect()` and exit. This covers every customer/staff/admin page in one edit, including the CSV export.
- **(b) Client backstop.** `script.js` already has a `pageshow` listener (line 180), but it only re-syncs sidebar submenu state. Add a separate listener that force-reloads on a bfcache restore, which re-requests from the server and lets the (now `no-store`) response redirect to login:
  ```js
  window.addEventListener('pageshow', (e) => { if (e.persisted) window.location.reload(); });
  ```
  `no-store` alone defeats bfcache in current Chrome/Firefox/Safari; the `pageshow` reload is the cross-browser/legacy backstop.

### 3.4 [Medium] No global exception handler / `display_errors` posture — app-wide

PDO runs with `ERRMODE_EXCEPTION` (`src/db.php:31`) but there is no `set_exception_handler`, no top-level try/catch, and no `display_errors` hardening anywhere. No page echoes `$e->getMessage()` (verified — the only structured catch, `admin/registrations.php:81–84`, shows a generic message; the transaction catches rollback and rethrow), so exposure depends entirely on the server's `display_errors` setting: where it's On (MAMP default), an uncaught exception prints the message, file paths, and stack trace — and a PDO **connection** failure's trace can include the constructor args, i.e. the DB password, unless `zend.exception_ignore_args=1`. *Fix:* register a top-level exception handler that logs and renders a generic 500 page, and/or enforce `display_errors=0` + `zend.exception_ignore_args=1` in the deployment config.

### 3.5 Verified intact (no regressions)

- Session regeneration on login: `session_regenerate_id(true)` — `src/auth.php:62`.
- Password policy: 12-char minimum + letter-and-number + must-not-contain-username — `auth.php:149–166` (`PASSWORD_MIN_LENGTH = 12`, line 142).
- Reuse prevention: new password checked against the current hash plus the last 4 in `password_history` (`auth.php:172–190`), history archived and pruned on change (`196–208`); enforced by `change_password.php:37`.
- Lockout: 5 failures → 15-minute lock (`auth.php:10–12, 32–48`), with a `lockout_events` row logged at lock time (43–44).
- Session death on deactivation: `require_role()` re-checks `users.active` on **every request** and destroys the session if cleared (`auth.php:102–108`).
- Idle timeout: 15 minutes, enforced in `require_role()` (`auth.php:110–114`).
- `must_change_password` redirect loop-guard (`auth.php:128–131`); role satisfaction is one-directional (admin ⊆ staff only, `auth.php:82–89`).

### 3.6 Remaining session findings

- **[Low]** No `SameSite` attribute on the session cookie — `bootstrap_session()` (`src/helpers.php:19–26`) sets only `httponly` + `secure`. Modern browsers default to Lax, but that's a browser policy, not the app's. Also: no security headers anywhere (no `X-Frame-Options`, `X-Content-Type-Options`, CSP, or HSTS — grep confirmed). *Fix:* add `'samesite' => 'Lax'` to `session_set_cookie_params()`, and emit a baseline header set (frame-deny, nosniff) alongside the cache headers from 3.3(a) or in Apache config.
- **[Low]** Lockout message discloses account existence — `auth.php:26–29` returns "Account temporarily locked…" *before* password verification, distinguishable from the generic "Invalid username or password." *Fix:* return the generic message and keep the lockout state server-side.
- **[Low]** `logout()` (`src/auth.php:136–140`) destroys server-side state but never expires the session cookie in the browser. *Fix:* clear the cookie (`setcookie(session_name(), '', ...)`) before redirecting; pairs with the GET-logout item in §2.
- **[Info]** `change_password.php` doesn't rotate the session id after a successful change (minor hardening). Bcrypt truncates passwords at 72 bytes (`PASSWORD_BCRYPT`, `change_password.php:45`) — irrelevant at a 12-char minimum, noted for completeness.

---

## 4. File / Path Handling

**Result: no path traversal or filename injection.** There are no upload features and no user-controlled file reads/writes anywhere in the app (confirmed by sweep — matches original scope). The CSV export's output filename is fully server-generated (`pet_orders_report_ . date('Y-m-d') . .csv`, `export_csv.php:84`) — no user input reaches `Content-Disposition`. Its query building is covered (clean) in §1.

- **[Medium] 4.1 CSV formula injection — `public/admin/export_csv.php:98–116`.** `fputcsv()` writes `cancellation_reason` (free text a **customer** can author via the cancel flow) and institute/nuclide/product names verbatim. A cell beginning `=`, `+`, `-`, or `@` (e.g. `=HYPERLINK(...)`) executes as a formula when an admin opens the report in Excel. *Fix:* prefix any cell value starting with `=`, `+`, `-`, `@`, or a tab/CR with a `'` before `fputcsv()`.
- **[Low] 4.2 Date params unvalidated — `export_csv.php:13–26`.** `start_date`/`end_date` are concatenated with times and bound (so no SQLi), but skip the `^\d{4}-\d{2}-\d{2}$` regex gate every other date filter in the codebase uses; garbage input reaches MySQL as a BETWEEN operand (warnings/empty result), and a missing range hits a bare `die("Please provide a valid date range.")`. *Fix:* apply the same regex gate as `staff/orders.php:24–27` and render a proper error.

---

## 5. General

### Server-side role enforcement — verified, all routes

Every page under `public/` calls `bootstrap_session()` then `require_role()` before any output or query. Full table:

| Page(s) | Guard |
|---|---|
| `admin/` — dashboard, registrations, customers, customer_detail, accounts, account_detail, nuclides, products, institutes, labs, pis, reports, **export_csv** | `require_role('admin')` at line 5 of each |
| `staff/` — dashboard, orders, order_detail | `require_role('staff')` at line 5 (admin satisfies via `role_satisfies()`) |
| `customer/` — dashboard, orders, order_detail, new_order, lab_delivery_locations, lab_product_users | `require_role('customer')` at line 5 (new_order additionally POST-only + CSRF at L16) |
| `change_password.php` | `require_role(['customer','staff','admin'])` (L6) |
| `account_profile.php` | `require_role(['staff','admin'])` (L9) — customer self-edit deliberately removed |
| `index.php`, `login.php`, `logout.php`, `register.php`, `registration_status.php` | intentionally public (login/register/status redirect authed users away) |

No admin or staff page is missing a guard or has the wrong role. `src/` and `tools/` sit above the Apache doc root and are unreachable by URL (no include/symlink from `public/` — verified by grep).

**Object-level authorization (IDOR) — verified, enforced in SQL, not just UI:** `customer/order_detail.php` folds lab scope into the fetch join (`AND c.lab_id = ?` from the session user's own row, L40/48); the notes UPDATE repeats `AND customer_id = ?` (L97); the details edit locks and re-checks `AND customer_id = ? AND status='pending'` (L138–146); `transition_order_status()` independently re-enforces customer ownership inside the transaction (`src/helpers.php:444`) and its customer map only allows `pending → cancelled`; both lab CRUD pages repeat `AND lab_id = ?` in every UPDATE; `new_order.php` inserts `customer_id` from the session and `validate_order_input()` verifies location/product-user against the session lab (`helpers.php:296–299, 313–316`). Lab-mates seeing each other's orders is the documented "view own lab's orders" permission, not a hole.

### Findings

- **[Medium] 5.1 PHP 8-only functions on the PHP 7.4 target — `public/account_profile.php:20,30`.** `str_starts_with()` and `str_contains()` don't exist in PHP 7.4 and no polyfill is defined anywhere in the repo (these are the only two uses). Every staff/admin profile save will fatal on the RHEL 8 deployment — an availability bug whose error rendering is then governed by the 3.4 gap. *Fix:* replace with `strpos($candidate, '//') === 0` / `strpos($target, '?') !== false`.
- **[Medium] 5.2 Unauthenticated registration-status oracle — `public/registration_status.php:26–34, 72`.** Anyone can submit arbitrary emails and read that email's latest registration status **and the admin's free-text `rejection_reason`**, with no rate limit and no proof of email ownership; an "approved" result effectively confirms an account exists. *Fix:* rate-limit per session/IP and genericize the response (keep detailed reasons for the manual NIH-email channel). **Batch 4 update:** the `rejection_reason` disclosure is fixed — the column is no longer selected and the rejected-status message is now generic. Rate limiting was evaluated and **deliberately deferred**: this app is internal and badge-gated (not public-internet-facing), and the remaining disclosure (confirming a request exists in `pending`/`rejected`/`approved` state for a submitted email) was judged not to warrant new schema/infrastructure at this severity. Revisit if the app's exposure model ever changes.
- **[Low] 5.3 Email enumeration on registration — `public/register.php:98,105`.** "A registration for this email is already pending" / "An account already exists for this email" are returned to unauthenticated visitors (deliberate per in-code comments). Also no rate limit on request creation → unbounded admin-queue spam for distinct emails. *Fix:* document as accepted risk for the intranet context, or genericize + rate-limit. **Batch 4 update:** reviewed with the project lead and kept as-is — the distinct messages have real self-service UX value (a genuine user can tell whether to wait for review or just log in) and this is judged an acceptable trade-off for an internal intranet app. Formally accepted risk, not an oversight.
- **[Low] 5.4 Open-redirect backslash bypass — `public/account_profile.php:20`.** The redirect guard rejects `//` but `/\evil.com` passes (starts with `/`, not `//`), and browsers normalize `\`→`/`, yielding a protocol-relative redirect. Requires a token-bearing POST, so practical risk is small. *Fix:* also reject a `/\` prefix — fold into the 5.1 rewrite.
- **[Low] 5.5 `public/.DS_Store` inside the doc root** — servable; discloses directory layout. *Fix:* delete, gitignore, and block dotfiles in Apache config.
- **[Low] 5.6 Debug PII to the error log — `customer/lab_delivery_locations.php:202–205`, `customer/lab_product_users.php:238–241`.** The `[LOC-DEBUG]`/`[PU-DEBUG]` blocks `print_r` full rows (including product-user name/email) plus SQL into the server error log on every list load; marked temporary in-code. *Fix:* remove.
- **[Info] 5.7 `tools/set_temp_passwords.php:13`** hardcodes the shared temp password `TempPass123!` in the repo. CLI-only and not web-reachable (verified), fine for local seeding — rotate/regenerate before any shared deployment.

**Verified clean:** no `var_dump`, `phpinfo`, or `getMessage()` echoes anywhere; the two `die()` messages ("Invalid CSRF token.", the export's date message) leak no internals; the inline one-time temp-password renders on `admin/registrations.php` / `admin/accounts.php` are the documented admin-only convention (deliberate PRG exception), not a leak.

---

## Suggested fix batches

Small, independently reviewable batches for the follow-up sessions:

1. **Config + bfcache (the two Highs and the headline known issue):** rewire `helpers.php` to `config.php`, restore config-driven `get_db()`, add cache-control (+ baseline security) headers at the end of `require_role()`, add the `pageshow` reload backstop in `script.js`.
2. **Error handling + session hardening:** global exception handler / display_errors posture, `SameSite` cookie flag, generic lockout message, cookie-clearing (and POST-ified) logout.
3. **Export polish:** CSV formula-escape, date regex gate + proper error on `export_csv.php`, remove debug logging blocks, delete `.DS_Store`.
4. **Public-surface + compat:** `registration_status.php` oracle (rate limit + genericize), `register.php` enumeration decision, `account_profile.php` PHP 7.4 rewrite + backslash-redirect fix, `EMULATE_PREPARES => false`.
