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
      dashboard.php
      new_order.php        (POST-only JSON endpoint — no standalone page fallback)
      order_detail.php
      orders.php            (lab order list — status tabs with counts (the
                             primary status filter, counts respect the other
                             active filters) + search/fulfillment/date
                             range/pagination; Product User column shows the
                             order's attached product user, falling back to
                             the placing customer when none is attached;
                             replaces the old past_orders.php mock)
      lab_delivery_locations.php
      lab_product_users.php
    staff/
      dashboard.php         (stat tiles: pending/accepted/total, each linking
                             into the corresponding Order Queue tab; "Due
                             Today & Upcoming" table (soonest-due pending/
                             accepted orders, tiered urgency dot: red =
                             overdue, amber = due today, none = upcoming) +
                             a side "Waiting for Acceptance"/"Open Orders
                             by Lab" two-card stack — pending orders sorted
                             by created_at age (the staleness signal the
                             due-date table can't show), then pending/
                             accepted counts grouped by lab (a different
                             vantage than every order-level list above);
                             replaced the old "In Progress"/"Recent
                             Activity" stack)
      orders.php             (Order Queue — pure triage list: status tabs
                             with counts (the primary status filter) +
                             search/fulfillment/date range/pagination, NOT
                             lab-scoped like the customer equivalent. Each
                             row shows a read-only Status/Chargeable stacked
                             cell, but no actions — every row links to
                             order_detail.php)
      order_detail.php       (staff order detail — not lab-scoped; all
                             lifecycle actions (accept/return/complete/
                             cancel/reopen, via transition_order_status())
                             and the chargeable toggle live here, not in the
                             queue table; also the order's full order_audit_log
                             history)
    admin/
      dashboard.php
      registrations.php
      customers.php          (status tabs (Active/Inactive) + search/
                             institute/lab filter/pagination, same
                             convention as orders.php)
      customer_detail.php
      accounts.php           (unified staff+admin list; status tabs
                             (Active/Inactive) + search/role filter/
                             pagination, same convention as orders.php;
                             New Account opens as a modal here — replaces
                             the old account_create.php page, which has
                             been deleted)
      account_detail.php   (view/edit profile/deactivate/reset password)
      nuclides.php          (full-convention list — status tabs
                             All/Active/Inactive + name search/pagination;
                             create + edit (free rename) modals; toggle
                             active with a blast-radius confirm that states
                             how many active products become unavailable)
      products.php          (full-convention list — status tabs
                             All/Active/Unavailable/Inactive on the derived
                             effective-availability status + name search/
                             nuclide filter/fulfillment filter/pagination;
                             create + edit modals and toggle active; edit
                             locks nuclide + fulfillment once any order
                             references the product — see Catalog section)
      institutes.php        (full-convention list — status tabs
                             All/Active/Inactive + name search/pagination;
                             create + edit (free rename + shorthand) modals;
                             toggle active with a blast-radius confirm
                             stating how many active labs become
                             unavailable to new registrations)
      labs.php              (full-convention list — status tabs
                             All/Active/Unavailable/Inactive on the derived
                             institute→lab effective availability + lab-name
                             search/institute filter/pagination; create +
                             edit modals incl. the lab's PI roster
                             checkboxes (the only lab_pis management UI);
                             institute freely editable — see Identity
                             section)
      pis.php               (full-convention list — status tabs
                             All/Active/Inactive + name search/pagination;
                             create + edit modals (name + optional
                             email/phone); toggle active; lab pairings are
                             managed from labs.php, not here)
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
          order-page.css        (baseline for the customer order form)
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
                          toast_flash, field_error/field_class,
                          validate_order_input(), asset_url() —
                          filemtime cache-buster for CSS/JS links)
    partials/
      head.php
      layout_customer.php
      layout_staff.php
      layout_admin.php
      new_order_form.php
      new_order_modal.php

  sql/
    schema.sql          (see Database section below)
    seed.sql            (test data)

  tools/
    set_temp_passwords.php (one-time setup for seeded accounts)
```

No `docs/` folder yet — `DEPLOY.md` is expected to show up eventually as part
of deployment polish; nothing currently reads a `SCHEMA.md`, and it isn't
committed anywhere yet either.

**Note:** the whole repo is intentionally uncommitted to git for now (standing
project decision to hold off until further along) — this is a general
repo-status fact, not a signal that any particular feature described below is
unfinished or unvalidated.

## Database

See `sql/schema.sql` for exact columns/constraints — this is just a map of what
exists and where things stand, not a full spec.

**Identity — built:** `institutes`, `labs`, `pis`, `lab_pis`, `users`,
`password_history`, `lockout_events`, `customers`, `customer_registration_requests`,
`staff`, `admins`.

**Directory CRUD (admin-only: `admin/institutes.php` / `labs.php` /
`pis.php`) and what `inactive` means for these entities — all settled:**
Institute → lab availability is the same computed model as nuclide →
product: a lab is selectable on the public registration form iff
`labs.active = 1 AND institutes.active = 1`, checked live at read time
(register.php's listing and validation) — deactivating an institute never
writes to lab rows, and the admin lab list shows the amber "Unavailable"
pill (+ "Institute inactive" hint) for that derived state. `labs.active`
and `pis.active` are leaf flags: they gate ONLY new-registration selection
and *changed-to* assignments in admin customer edit — never existing
customers, their orders, or lab-scoped delivery locations/product users.
Admin customer edit (customer_detail.php) uses the keep-current-vs-changed
rule: keeping the customer's current lab+PI pair always saves (even if
since-deactivated or un-paired); changing either requires the new lab/PI
be active and the pair to exist in `lab_pis`. Registration approval copies
the request's lab/PI as-is without re-checking active (locked at request
time) — deliberate. The lab↔PI pairing (`lab_pis`) is managed exclusively
from the Lab create/edit modal's PI roster checkboxes (labeled with how
many customers each PI supervises in that lab; removals are informed, not
blocked). A lab's institute is freely editable — institute is stored once
and derived live everywhere by design, so display always reflects current
org structure (unlike product's nuclide/fulfillment lock, which protects
what was physically ordered). Uniqueness: only `institutes.name` has a DB
unique key (and the matching app pre-check); labs/PIs have none — do not
invent app-level uniqueness for them. There is no category concept anywhere: staff are not
split by processing category (any staff member can process any order),
and the former `categories`/`staff_categories` tables have been removed
entirely (confirmed by the project lead). The former NRC contact fields
(`nrc_contact_name`/`_phone`/`_email` on both `customers` and
`customer_registration_requests`, plus their registration-form and
admin-customer-edit sections) have been fully removed — confirmed
unneeded by Kris; do not re-add.

**Catalog — restructured (all decisions confirmed final by the project lead):**
`nuclides` and a single flat `products` table. Terminology renames apply
everywhere: isotope → **nuclide** (`isotope_id` → `nuclide_id`) and
compound → **product** (`compound_id` → `product_id`). The old
compound/isotope/delivery-option model — `compounds`, `isotopes`,
`delivery_options`, `compound_delivery_options`, and the compound ×
isotope variant/SKU rows — collapses entirely into `products`, whose
columns are: `nuclide_id` (FK), `name`, `delivery_method` (fixed enum:
`radiopharmacy` / `pick_up` / `direct_delivery`), `active` (boolean).
Displayed to customers as **"Fulfillment"** in the UI — this is a UI-text-only
rename; the column, ENUM values, and the `delivery_method_label()` function
name all remain `delivery_method` throughout the codebase, deliberately not
renamed to match.
Products have no category (the whole category concept was removed).
No cost/pricing field of any kind — this project does not track cost.
No `sku` or `description` columns either.
Delivery method is a fixed property of the product row, not chosen per-order;
only `direct_delivery` requires a delivery location. A product that needs
multiple delivery methods = two separate product rows (same nuclide/name,
different `delivery_method` each). Availability is **computed/effective**: a
product is orderable iff `products.active = 1 AND nuclides.active = 1`.
Deactivating a nuclide never writes to any product row (no cascade);
reactivating it makes its products orderable again automatically, with no
lost information about which products were individually deactivated. Both
availability gates live in `get_new_order_form_data()` and
`validate_order_input()` (helpers.php) — any future availability check must
apply the same two-flag rule. That two-flag rule is the ONLY product gate:
every effectively-available product is visible/orderable by every lab and
institute. There is no institute- or lab-scoped catalog access layer of any
kind (the former `institute_catalog_access` join table was removed entirely).
Catalog management is admin-only (`admin/nuclides.php`,
`admin/products.php`): full CRUD on both — create/edit modals plus
activate/deactivate. Nuclide rename is always allowed (historical orders
display the corrected name — desired). Product edit: name is always
editable, but nuclide and delivery_method lock (read-only, server-enforced)
once any order references the product row — change those by creating a new
product row and deactivating the old one, per the audit-trail rationale in
schema.sql. In the admin product list, a product with `active = 1` whose
nuclide is inactive shows an amber "Unavailable" pill (with a "Nuclide
inactive" hint) — distinct from the gray "Inactive" pill, which always
means an admin turned that product off directly.
Staff do not manage the catalog — their role is order processing only.
Still open: sync with Xiaofan/Kris to confirm the seeded nuclide/product
list is the long-term list.

**Naming collision (intentional):** "product" now has two senses in this
codebase — a catalog item (the `products` table) and a "product user" / dose
recipient (`lab_product_users`), which keeps its existing name as-is. Both
are deliberate; don't rename either to resolve the collision.

**Orders — built and confirmed against the current catalog schema:**
`orders` and `order_audit_log` (status-only, not field-level diffing).
Dropped from the prior pre-restructure design: `order_public_comments` and
`order_internal_notes` (both tables gone entirely), `orders.delivery_option_id`
(delivery method now derives from the selected product row), and
`orders.cost_snapshot` along with every other cost-related column (cost is
not tracked anywhere in this system). `orders.special_instructions` is
renamed to `orders.notes` (TEXT NULL, 500-char app-enforced limit, with a
live character counter in the UI). `orders.notes` is the ONLY communication
mechanism on an order — one single shared, overwritable text field,
last-write-wins, no history or threading. Staff/admin can always edit it; a
customer can edit it only on their own order while it is `pending`. There is
no staff-only private notes channel of any kind, now or planned. One unified
order form for every order type — no Type A/B split, no separate detail
tables. Cyclotron-run specifics (beam current, bombardment time, EOB
activity, destination) go in the free-text Notes field like any other order
note. Also built: lab-scoped (not per-customer) delivery
locations/product users with soft-delete via an `active` flag.
`lab_product_users` stores the dose recipient as `first_name`/`last_name`
(split, matching the `users`/`customers` convention — not a single combined
name) plus an `email` column. Email is collected by the CRUD UI as an
optional field (column stays nullable), validated server-side for format
and for a uniqueness constraint scoped to the lab (`lab_id` + `email`
composite unique, with a clear collision error), not global: two different
labs may each have a product user sharing an email; the same lab may not.
Per the requirements interview, customers manage their own lab's delivery
locations and product users directly (add/edit/deactivate) — this is
customer-facing functionality, not admin-only, despite most other
catalog/config-adjacent management in this app being admin-only.

**Order lifecycle — designed and implemented, backend and UI.**
`orders.status` supports four values (`pending`, `accepted`,
`completed`, `cancelled`) with these legal transitions, all enforced through
`transition_order_status()` (`src/helpers.php`) — the single validated path
for every status change, so no call site can bypass the state machine:

- **accept** (staff): `pending -> accepted`
- **return** (staff): `accepted -> pending`
- **complete** (staff): `accepted -> completed` — terminal
- **cancel** (customer, own order only, or staff, any order): `pending -> cancelled` or `accepted -> cancelled`
- **reopen** (staff): `cancelled -> pending` — shares the same `-> pending` target as return; the state machine doesn't distinguish them, only the UI copy does (see `describe_order_transition()`)

`completed` is the only truly terminal status — `cancelled` can be
reopened by staff back to `pending` (customers still cannot; the customer
transition map only ever allows `pending -> cancelled`). There is no staff
ownership/assignment concept: any staff member may accept, return,
complete, cancel, or reopen any order, consistent with the existing "any
staff, any order" model (`staff_categories` was already dropped for this
same reason). Every transition — including the pre-existing
customer-initiated cancel — writes an `order_audit_log` row atomically
alongside the status change, exactly as before; order creation's own
`status_from` NULL row is unaffected.

Two columns support the lifecycle: `orders.cancellation_reason`
(`VARCHAR(500)`, nullable at the DB level but app-enforced required
whenever a transition sets status to `cancelled`, from either the customer
or staff path — structured data tied to the cancel event, distinct from the
general-purpose `notes` field) and `orders.chargeable` (`BOOLEAN`, default
true — confirmed flip from the original false default; existing rows keep
their values — staff-only editable, freely toggleable regardless of
lifecycle state — toggling it is NOT a status transition, so it does not
write to `order_audit_log`). Because chargeable is now the default, the UI
treats "Not chargeable" as the exception worth attention (square
warning-tinted `.badge--not-chargeable` chip / non-muted text) and
"Chargeable" as the quiet state (plain or muted text — never a badge). Reopening a cancelled order clears `cancellation_reason`
back to `NULL` (a reopened order shouldn't keep displaying a stale reason)
— the cancellation event itself is unaffected and stays visible forever as
its own row in `order_audit_log`, which never gets rewritten.

Staff drives all five transitions from `staff/order_detail.php` — not the
Order Queue table, which is a pure triage list (status tabs + search/
filter/pagination, every row just a link into the detail page; it does
show a read-only Status/Chargeable stacked cell per row, but no actions).
The detail page's action buttons are gated by the order's current status,
matching the map above exactly (`cancelled` gets a single Reopen button).
Accept/return/complete/reopen use a plain confirm dialog; cancel (either
path) opens a shared reason-required modal, the same modal shell used by
the customer's own Cancel Order button on `customer/order_detail.php`.
`chargeable` is toggled from a
dedicated Billing card on the same detail page (one-click, no confirm,
per the "freely toggleable" rule above), and both the chargeable state
(page-header indicator plus a dedicated row in the Order Details card)
and the `cancellation_reason` (when set) are surfaced back to the
customer on `customer/order_detail.php`. The staff detail page also
renders the order's full `order_audit_log` history (newest first, real
staff-member names — not the customer-facing "Staff" collapse used on
the Cancellation Reason card).

**Open / deferred items:**
- "Auth log" — deferred entirely for now; not being worked on. (Not to be
  confused with the already-built `password_history`/`lockout_events` tables
  under Identity above, which are staying.)

## Business Rules (Non-Negotiable)

These came from the requirements interview. Don't simplify them.

- **No phone-in orders.** Customers place their own orders only. No `is_phone_in` field, no attestation.
- **Self-registration lands in `customer_registration_requests`, not `customers`.** A public registration submission creates a row in that separate table (`status`: pending/approved/rejected) — no `users` or `customers` row exists until an admin approves the request, at which point the account and temp password get created. `customers.registration_status`/`approved_by`/`approved_at` predate this design and are unused by the current registration flow.
- **There is one order form for all order types.** Cyclotron target requests use the same form as any other order; their run-specific details (beam current, bombardment time, EOB activity, destination) go in the Notes text field rather than dedicated structured columns or a separate detail table. Do not build or maintain a second order-detail table.
- **Order lifecycle is designed.** `pending -> accepted` (accept), `accepted -> pending` (return), `accepted -> completed` (complete, terminal), `pending|accepted -> cancelled` (cancel), and `cancelled -> pending` (reopen, staff-only) — see Database section. `completed` is the only terminal status. All transitions go through `transition_order_status()`; no call site should bypass it or invent additional transitions.
- **Cancelling an order always requires a reason.** `orders.cancellation_reason` is required (non-empty, ≤500 chars) on every cancel, whether customer- or staff-initiated. Enforced inside `transition_order_status()`, not left to individual call sites.
- **`chargeable` is independent of the lifecycle.** Staff can toggle it freely regardless of order status, and toggling it is never written to `order_audit_log` — only true status transitions are.
- **Cost is not tracked.** There is no cost/pricing field of any kind, anywhere in the system — no `cost_snapshot` on orders, no cost column on products, no cost reporting. Do not add one.
- **Nuclide first, then product.** Customer picks nuclide, then sees only that nuclide's products — not the reverse.
- **Delivery method is a fixed property of the product.** Each product row carries one `delivery_method` (radiopharmacy / pick_up / direct_delivery); it is never chosen per-order. Only `direct_delivery` requires a delivery location. A product offered via multiple methods is multiple product rows.
- **Audit log is status-only.** Not field-level diffing — just status_from, status_to, timestamp, who. Every transition, including order creation, writes an audit log entry automatically and atomically alongside the status change.
- **Notes is a single shared, overwritable field.** `orders.notes` is the only communication mechanism on an order — last-write-wins, no history, no threading. Staff/admin can always edit it; a customer can only edit it on their own order while it is pending. This replaces the former `order_public_comments` and `order_internal_notes` tables (both dropped entirely). There is no staff-only private notes channel of any kind, now or planned.
- **No per-order/per-period quantity limits.** Staff can adjust freely during processing.
- **No email from the app, ever.** Admins relay approvals/resets via NIH's internal email manually. No SMTP, no mail-sending code.
- **Session timeout: 15 minutes idle.** Lockout: 5 failed login attempts → 15-minute lockout.
- **Order IDs are sequential, never reused**, even for canceled orders.
- **Deactivating a customer never hides historical orders.** Pending orders at deactivation are left alone for staff to handle manually — never auto-canceled.
- **Admin can trigger password resets but never views or sets the actual password.** Reset generates a one-time temp password that forces a change + strength check on next login.
- **Order search must cover** ID, product, nuclide, date, and customer/lab/PI/institute.

## Roles

| Role | Access |
|------|--------|
| `customer` | Place orders, view own lab's orders, edit Notes on own pending orders, cancel own pending orders with a required reason, add/edit/deactivate their own lab's delivery locations and product users |
| `staff` | Process any order regardless of type — staff are not split by category; accept (`pending -> accepted`), return (`accepted -> pending`), complete (`accepted -> completed`, terminal), cancel (`pending`/`accepted -> cancelled`, with a required reason), and reopen (`cancelled -> pending`) any order — see Database section; edit Notes on any order; toggle `chargeable` on any order (staff-only, not a lifecycle transition) |
| `admin` | Everything staff can do, plus manage products/nuclides/customers/staff/institutes, run reports, approve registrations |

Role is determined by which table a `user_id` appears in (`customers`, `staff`, `admins`) — `users` itself has no role column.

## CSS Architecture

- **style.css:** System fonts (`-apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif`), reset, design tokens (colors + status text/tint pairs, spacing, radii `--radius-sm/md/lg/full`, shadows `--shadow-xs…lg`), typography, accessibility (`:focus-visible`, `prefers-reduced-motion`, `.sr-only`)
- **layout/shell.css:** App-shell grid, header/main/footer chrome bindings
- **layout/sidebar.css:** Sidebar (sticky, collapse on desktop, off-canvas on mobile), topbar, dark mode hooks
- **components/:** One file per concern — auth, page-structure (page header + cards + detail list + status tabs), forms, buttons, tables, alerts (incl. temp-password banner), badges, utilities, toasts, modals, feedback (spinners + empty states), dashboard (stat tiles + `dash-grid`/`dash-stack` panel layout), radio-cards, order-page

**No role-specific CSS files.** All three roles share the same component library.

**UI feedback conventions:**
- Transient success → toast via `toast_flash($type, $message)` (helpers.php); pages re-render on POST (no PRG), so the helper emits a DOMContentLoaded `showToast()` call
- Errors/warnings → inline `.alert--error/--warning` banners; per-field validation → `field_class()` on the `.field` wrapper + `field_error()` below the input
- Destructive/irreversible actions → `data-confirm` / `data-confirm-title` / `data-confirm-verb` / `data-confirm-danger` attributes on the form; script.js intercepts submit and shows a custom modal (never `window.confirm`)
- Temp-password reveals → `.temp-password-banner` with a `data-copy-target` Copy button; never a toast
- Status language: pill badges with a leading dot (`.badge--active/pending/approved/rejected/…`); square, no-dot chips are for facts rather than states (`.badge--role-admin/staff`, `.badge--not-chargeable`)
- Submit buttons get a spinner + double-submit guard automatically from script.js — no per-form wiring needed
- Military time (24-hour HH:MM) for any order date/time field is enforced as a pattern-validated text input, never a native time picker — this guarantees no AM/PM UI ever appears, including on mobile. This is a real department requirement, not a style choice.

**Dark mode:** Not implemented right now. Tokens may exist in CSS for future use but no toggle is wired up.

**Sidebar collapse (desktop only):** Pre-paint script reads `localStorage['petcom:sidebar']` and sets `data-sidebar="collapsed"` on `<html>`. CSS changes `--sidebar-width`. Mobile sidebar (off-canvas) uses `data-sidebar-mobile="open"` on `<html>` instead — a separate, independent state.

## Admin CRUD Page Conventions

New admin (and any other role's) list/create/edit pages should default to the patterns already established by `staff/orders.php`, `customer/orders.php`, `customer/lab_product_users.php`, `customer/lab_delivery_locations.php`, and `admin/accounts.php`/`customers.php` — without needing to be told each time:

- **List pages:** a `.status-tabs` strip (not a `<select>`) whenever there's a meaningful status dimension (active/inactive, pending/accepted/…), with each tab's count computed against the *other* active filters, not globally. A full `.table-pagination` footer — `__status-group` (range text + a page-size `<select>`) and `__controls` (Prev / jump-to-page mini-form / Next) — not a bare Prev/Next pair. Search/filter forms are explicit-submit (`method="get"`, hidden fields to carry the rest of the current view forward), never live-as-you-type.
- **Create/Edit:** the modal overlay convention — `.modal-overlay` > `.modal`, header X-close, dirty-tracking + discard-confirm via a `wireModalDirtyTracking()` helper (copied inline into the page's own script, same as `lab_product_users.php`/`lab_delivery_locations.php`/`accounts.php` — not shared into `script.js`), wired through `overlay.petcomBeforeClose` + `window.petcomConfirm()` — triggered from the list page itself, never a standalone create/edit page, unless a genuine constraint prevents it. The one confirmed exception: a one-time secret (a temp password) can't safely round-trip through a PRG redirect, so success still re-renders the same POST response inline rather than redirecting (`accounts.php`'s New Account modal, `registrations.php`'s approve action) — that's a redirect constraint, not a reason to skip the modal convention itself.
- **CSS reuse:** reach for the existing shared components (`.table-card`, `.status-tabs`, `.dash-grid`/`.dash-stack`, `.modal`/`.modal--wide`/`.modal--order`) before adding a page-specific variant.
- **Sidebar grouping:** the admin sidebar groups related pages into expandable
  submenus using the `$accountsChildPages`-style array + `in_array`
  active-state pattern in `layout_admin.php` — Accounts (registrations/
  customers/accounts/details), Catalog (Nuclides, Products), and Directory
  (Institutes, Labs, PIs). Submenus behave as an accordion: expanding one
  collapses any other open submenu (script.js `initSidebarSubmenus()`); the
  collapsed-rail flyout already enforced one-at-a-time separately. New admin
  pages should join an existing submenu or follow this pattern, not add flat
  top-level links.
- **DRY:** check for an existing helper/pattern before writing new logic. Known reusable pieces: `generate_temp_password()` (deliberately duplicated per-file, not shared into `helpers.php` — copy its shape, don't reinvent it), `fetch_order_audit_trail()`, `transition_order_status()`, `customer_display_name()`, `field_class()`/`field_error()`, `toast_flash()`, `csrf_field()`/`verify_csrf()`. If a page needs something that looks like one of these, it probably already exists — grep before writing.

## Git Workflow

Branch → PR → merge. Never push directly to `main`.

## Deployment Target

- **RHEL 8** (PHP 7.4, MariaDB 10.11)
- **No root access.** Hand off as a package: schema file + app files + config template + deployment doc.
- **HTTPS with self-signed cert locally; real cert on RHEL (handed off by IT).**
- **No external CDN.** All assets (CSS, JS, icons) inlined or local.

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