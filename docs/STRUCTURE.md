# PETStack: Folder Structure

Vanilla PHP + PDO + MySQL. Apache points at `public/`. Everything else
sits above it and can't be reached from a browser.

> **Decided:** `public/` is flat (no role subfolders). Access is gated in
> code on each page, not by folder. See "The three rules" below.

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

That's the whole project at a glance. The two that matter are
`public/` (the pages) and `src/` (the brains). Here's what's in each.

---

## public/ : the pages

```
public/
├── .htaccess         Apache rules (HTTPS, error pages, file blocking)
├── index.php         front door, routes you by role
├── assets/           style.css, app.js
│
├── login · logout · register · reg_status
├── account · change_password
│
├── my_orders · new_order · view_order      (customer)
├── queue · process_order · phone_order     (staff)
│
├── manage_*          (admin: customers, users, compounds, isotopes...)
├── reports
└── 404 · 403 · 500   (error pages)
```

One file = one page = one URL. The folder *is* the site map.

---

## src/ : the brains (not web-reachable)

```
src/
├── config.php          real DB password   (gitignored)
├── config.sample.php   template teammates copy from
├── db.php              the PDO connection
├── auth.php            login + require_role() + session guard
├── helpers.php         csrf, escaping, redirects
└── partials/           header.php, footer.php
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