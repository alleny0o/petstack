# PETStack: Folder Structure

Vanilla PHP + PDO + MySQL. Apache points at `public/`. Everything else
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
├── login · logout · register · reg_status
├── account · change_password
│
├── my_orders · new_order · view_order      (customer)
├── queue · process_order · phone_order     (staff)
│
├── manage_*          (admin: customers, users, compounds, isotopes...)
├── reports
├── 404 · 403 · 500   (error pages)
│
├── assets/
│   ├── css/
│   │   └── style.css
│   └── js/
│       └── app.js
│
└── favicons/
    ├── favicon.ico
    ├── favicon-16x16.png · favicon-32x32.png
    ├── apple-touch-icon.png
    ├── android-chrome-192x192.png · android-chrome-512x512.png
    └── site.webmanifest
```

Pages stay flat at the top so they're easy to scan. CSS/JS go in
`assets/`, icons in `favicons/`. One file = one page = one URL.

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
    ├── header.php      opening HTML, <head>, nav, favicon + CSS links
    └── footer.php      closing HTML
```

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

3. **Always `__DIR__` in require paths.** Relative paths break when
   it moves to RHEL.

---

## Asset paths

Because assets live in subfolders, links in `header.php` point at:

```
/assets/css/style.css
/assets/js/app.js
/favicons/favicon.ico   (and the other icon files)
```