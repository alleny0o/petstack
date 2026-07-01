# PETStack: Folder Structure

Vanilla PHP + PDO + MariaDB. Apache points at `public/`. Everything else
sits above it and can't be reached from a browser.

> **Decided:** `public/` is flat for pages (no role subfolders). Access is
> gated in code on each page, not by folder. Static assets (CSS, JS, icons)
> live in their own subfolders so the page files stay easy to scan.

```
petstack/
├── public/      <- the only web-reachable folder
├── src/         <- app logic + DB password (never servable)
├── logs/        <- error log
├── sql/         <- schema + seed data
├── tools/       <- one-off scripts
├── docs/        <- README, schema notes
├── .gitignore
└── PLAN.md
```

The two that matter are `public/` (the pages + assets) and `src/` (the
brains). Here's what's in each.

---

## public/ : pages and static assets

```
public/
├── .htaccess         Apache rules (HTTPS, error pages, file blocking)
├── index.php         front door, routes you by role
│
├── login · logout · register · reg_status · account · change_password
├── customer_home · customer_past_orders · customer_catalog · ...  (customer)
├── queue · process_order · ...                                    (staff)
├── manage_*                                                       (admin)
├── reports
├── 404 · 403 · 500   (error pages)
│
├── assets/
│   ├── css/style.css
│   └── js/script.js
│
└── favicons/
```

Pages stay flat at the top so they're easy to scan — one file, one page,
one URL. Exact page names will keep shifting as we build; the categories
above are the stable part. CSS/JS live in `assets/`, icons in `favicons/`.


---

## src/ : the brains (not web-reachable)

```
src/
├── config.php          real DB password   (gitignored)
├── config.sample.php   template teammates copy from
├── db.php              the PDO connection
├── auth.php            login + require_role() + session guard
├── helpers.php         csrf, escaping, redirects
└── partials/
    ├── head.php              <head> contents: title, the dark-mode/sidebar
    │                         pre-paint script, stylesheet link. Every page
    │                         sets $pageTitle then includes this.
    └── sidebar_customer.php  Sidebar nav for the customer role — also
                              contains the mobile topbar (hamburger) and
                              the off-canvas backdrop, since those need to
                              be present on every page the sidebar is on.
                              Role-specific siblings (sidebar_admin.php,
                              etc.) get added the same way once those
                              pages start.
```

There's no separate `header.php`/`footer.php` right now. Each page wraps
its own `<div class="app-shell">` around the sidebar include and `<main>`
directly — see any `customer_*.php` page for the pattern. `.app-header`
and `.app-footer` exist as optional CSS components a page can opt into,
but aren't wired up as automatic partials yet.

---

## The three rules

1. **Only `public/` is servable.** The DB password lives in `src/`,
   above the doc root, unreachable by URL even if Apache misbehaves.

2. **Every protected page gates itself on line two:**
   ```php
   require __DIR__ . '/../src/auth.php';
   require_role('user', 'admin');
   ```
   Run `grep -n require_role public/*.php` for the full permission map.
   (Pre-login pages like `login.php`/`register.php` are exempt.)

3. **Always `__DIR__` in require paths.** Relative paths break when
   it moves to RHEL.

---

## Asset paths

Because assets live in subfolders, links point at:

```
/assets/css/style.css
/assets/js/script.js
/favicons/favicon.ico   (and the other icon files)
```