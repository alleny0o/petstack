# PETCOM Optimization / Code-Quality Audit (Phase 0)

**Date:** 2026-07-21
**Scope:** Full static quality pass over the codebase — every PHP file under `public/` and `src/`, all 17 CSS files, `script.js`, `sql/schema.sql`/`seed.sql`, `tools/`, and the project docs (`CLAUDE.md`). This is an **optimization/DRY/consistency audit, not a security pass** — security is covered by `docs/SECURITY_AUDIT.md` (all four of its fix batches are committed); the few security-adjacent observations found here are quarantined in §8.
**Method:** Read-only. Code reading, grep sweeps (including dynamic-prefix checks before declaring anything orphaned), byte-level comparison of suspected duplicate blocks, and line-level verification of every High-priority citation. Per the project verification policy, no live HTTP/DB testing was performed.
**Priority scale:** High/Medium/Low mean *worth fixing* (maintainability payoff vs. effort/risk) — not severity.

## Summary — overall codebase health

Unusually clean for a hand-rolled no-framework app. **Zero unused PHP functions** (all 18 helpers, 9 auth functions, and 26 page-local functions have verified call sites), **zero commented-out old implementations**, **zero stragglers from any removed feature** (categories, NRC contacts, compounds/isotopes/delivery_options, catalog access, order comment tables, cost — all fully gone from code, CSS, JS, and SQL). `src/db.php` is fully resolved post-security-pass — nothing left to do there.

The real debt is concentrated in three places:

1. **List-page machinery duplicated across 11 pages** — the query-string builder, pagination math, LIKE-escaping, and the ~45-line pagination footer are byte-identical copies on every list page (~400+ extractable lines), with one confirmed behavioral drift already (§5.1).
2. **Layout scope leakage** — the known include-scope issue is mitigated by naming discipline, but two pages now *depend* on the leakage, and the isset guard protects only one of four overwritable variables (§1).
3. **~18 orphaned CSS classes**, concentrated in 7 whole rule-blocks (§3.2).

One real (if small) UI bug was found along the way: the collapsed-sidebar flyout hardcodes the "Accounts" title for all three admin submenus (§6.2). The audit also found the project docs have drifted from the code in several places — per CLAUDE.md's own "fix the file first" rule, those doc fixes are Batch 1 (§7).

---

## 1. Layout variable leakage (brief item 1)

**Status: mitigation holding; root-cause fix is moderate, not big.** All three layouts are plain `include`s executed mid-page, so everything they assign lands in the including page's scope. Pages currently dodge collisions by naming discipline (`$deliveryLocations` at `public/customer/lab_delivery_locations.php:148`, `$productUsersList` at `public/customer/lab_product_users.php:179–187` — the latter with an in-code warning documenting the past collision bug).

### 1.1 Reserved variable reference (the single lookup table this brief asked for)

Any page including these layouts must treat the following names as taken:

| Layout | Reserved names | Notes |
|---|---|---|
| `layout_customer.php` | `$accountStmt`, `$accountRow`, `$accountName`, `$accountInitials`, `$currentPage`, `$labId`, `$labLookupStmt`, `$newOrderFormData`, `$nuclides`, `$products`, `$locations`, `$productUsers` | `$labId` is an optional caller input (looked up only if unset — lines 31–35); `$nuclides` is the **only** guard key (line 37): if a page sets `$products`/`$locations`/`$productUsers` but not `$nuclides`, the layout silently overwrites them |
| `layout_staff.php` | `$accountStmt`, `$accountRow`, `$accountName`, `$accountInitials`, `$currentPage` | Also reads `$_GET['profile_updated']`/`['profile_error']` and echoes toasts (lines 12–19) — side effects beyond markup |
| `layout_admin.php` | staff's five, plus `$accountsChildPages`, `$accountsSectionActive`, `$catalogChildPages`, `$catalogSectionActive`, `$directoryChildPages`, `$directorySectionActive` | Same `$_GET` toast block duplicated verbatim (lines 15–19) |
| `head.php` | expects `$pageTitle` from caller | Used unguarded at line 6 — a page that forgets it gets a PHP notice + broken title (all 24 current pages set it) |

`new_order_modal.php` is the one collision-safe partial — its `$old`/`$fieldErrors` are closure-wrapped (lines 27–43).

### 1.2 Findings

- **[High] Two pages depend on leakage in the outbound direction.** `public/customer/dashboard.php:82,216–217` reads `$accountRow` *after* the include (acknowledged in the comment at line 71), and `public/customer/order_detail.php:384–500` consumes `$nuclides`/`$products`/`$locations`/`$productUsers` from the layout for its edit form. This turns an accidental leak into an undocumented API. *Approach:* fold into the root-cause fix below.
- **[Medium] Root-cause fix — recommended as its own batch.** Data-gathering inside the layouts is small: one account query per layout, plus (customer only) a conditional lab lookup and a call to the already-existing `get_new_order_form_data()` (helpers.php). Extract a `layout_account_data(int $userId): array` helper, have each layout populate a **single prefixed array** (e.g. `$petcomLayout`) instead of ~12 loose names, keep the isset guard on the array key. Touch points: 3 layouts + ~5 pages (the two dependents above, plus the four pages pre-setting `$labId`). No schema/JS impact.
- **[Medium] Staff/admin layout duplication.** `layout_staff.php:12–19` and `layout_admin.php:15–19` duplicate the toast-flash block verbatim, and both layouts' entire sidebar-footer/profile-modal/script sections (~lines 87–154 staff vs 181–248 admin) are near-identical ~70-line copies. *Approach:* shared `_sidebar_footer.php` sub-partial.
- **[Low] Escaping inconsistency in partials.** helpers defines `e()` (with `ENT_QUOTES`), and `new_order_form.php` uses it, but all three layouts + `head.php` use raw `htmlspecialchars()` exclusively (19 uses, without explicit `ENT_QUOTES`). *Approach:* standardize partials on `e()` (see §8 for the security framing — hardening consistency, not a vulnerability).

## 2. src/db.php (brief item 2)

**Status: closed — nothing to do.** 23 lines: config `require_once`, `get_db()` with static memoization, DSN built entirely from `DB_HOST`/`DB_PORT`/`DB_NAME` constants + `charset=utf8mb4`, and the three standard PDO options including `EMULATE_PREPARES => false`. No commented-out old code, no hardcoded values, no stale comments. The absence of a local try/catch is correct — the global `set_exception_handler` (helpers.php) is the designated backstop. The security pass fully resolved this file.

## 3. Dead code sweep (brief item 3)

### 3.1 PHP / JS / features — spotless

- **Removed-feature stragglers: zero.** Grepped (case-insensitive, code+CSS+JS+SQL): `staff_categories`, NRC contact fields, `compound*`, `isotope*`, `delivery_option*`, `institute_catalog_access`, `order_public_comments`, `order_internal_notes`, `cost_snapshot`, `special_instructions`, `past_orders`, `account_create`, `is_phone_in` — all clean. Remaining mentions are benign historical comments (`admin/accounts.php:65,390`, `sql/schema.sql:124–125`).
- **Commented-out implementations: none.** Every `/* */` block and `//` run is genuine documentation.
- **Unused functions: none**, in `src/` or page-local (verified per-function by grep). The four `generate_temp_password()` copies and eight `wireModalDirtyTracking()` copies are the documented deliberate duplications — see §4.4 for their sync status.
- **[Low] One dead data attribute:** `data-delivery-method` is emitted on product `<option>`s at `src/partials/new_order_form.php:72` and `public/customer/order_detail.php:446` but never read — the cascade reads only `requiresLocation`/`deliveryLabel`/`nuclideId` (script.js:688–745). (`admin/products.php` uses the differently-named, actually-read `data-product-delivery-method`.) *Approach:* delete the attribute at both sites.
- **[Low] Dead session state:** `$_SESSION['role_id']` is assigned at `src/auth.php:66` and never read anywhere. *Approach:* delete the assignment.
- **[Low] Dead-in-practice server error path in `new_order_form.php`.** The `$old`/`$fieldErrors` repopulation plumbing (lines 16–30, 46–186) is unreachable: the modal is the only includer and always passes pristine-empty values (its own comments admit this); real errors arrive via the AJAX `renderFieldErrors()` (lines 277–294), which duplicates `field_error()`'s markup. *Approach:* either strip the partial to pristine-only or add one comment declaring the server path the canonical markup reference — pick one.

### 3.2 Orphaned CSS — the real cleanup target (~18 classes)

Verified zero references including dynamic construction patterns (`badge--<?= $x ?>`, `'toast toast--' + type`, etc. were all checked and are **not** orphaned).

**[Medium] Whole orphaned rule-blocks (delete outright):**

| Block | Location |
|---|---|
| `.status-timeline` component (5 classes: `__item`, `__item--current`, `__label`, `__meta`) | `components/order-page.css:239–268` |
| `.table-empty` + `.table-empty .btn` (empty states use feedback.css instead) | `components/tables.css:55–64` |
| `.quick-actions` | `components/dashboard.css:160–166` |
| `.dash-grid--even` + `.dash-grid--fill` (its comment cites "staff dashboard", which no longer uses it — `staff/dashboard.php:116`) | `components/dashboard.css:91–104` + responsive override ~line 122 |
| `.page-header-flex` | `components/page-structure.css:79–84` |
| `.card.is-editing` (no template or script ever adds `is-editing`) | `components/page-structure.css:101–107` |

**[Low] Single orphaned variants/utilities:** `.dot--success` (dashboard.css:76), `.spinner--lg` (feedback.css:17–21), `.stat-tile__value--text` (dashboard.css:62–65), `.label-optional` (forms.css:126–130), `.form-section__suffix` (forms.css:147–152), `.flex-between` (utilities.css:36–40), `.mt-0` (utilities.css:11–13).

**[Low — confirm intent first]** `.app-header`/`.app-footer` (`layout/shell.css:50–54, 68–71`) — never emitted by any template (the header grid-area is occupied by `.app-topbar`); shell.css's own comments describe them as optional per-page partials that don't exist. Looks like deliberate forward-provisioning of the grid contract — confirm before deleting.

### 3.3 Stale comments and housekeeping

- **[High — docs batch]** `sql/schema.sql:148` — comment on `customers` says "registration_status lives directly here — no separate requests table", directly contradicting `customer_registration_requests` at line 173 of the same file and the CLAUDE.md business rule. *Approach:* fix the comment.
- **[Low]** Stale docblocks: `helpers.php:414–416` ("nothing calls this yet" — `can_edit_order_notes()` now has 11 call sites); `helpers.php:167` (`customer_display_name()` references the removed comment-thread feature); `auth.php:77` (cites "Phase A.2", a build-phase label meaningless to future readers); `helpers.php:1–4` (file docblock describes a quarter of what the file now holds).
- **[Low]** `customers.registration_status` (schema.sql:156 + index) — documented-vestigial, but note it **is** still written (`admin/registrations.php:53`, seed) and displayed (`admin/customer_detail.php:280`); since a customers row only exists post-approval, the value is always `'approved'` in practice. Removal is a schema decision, not mechanical cleanup — leave unless/until a schema pass happens. (`password_history` and `lockout_events` were verified in active use.)
- **[Low]** Housekeeping: `.gitignore` references a `logs/` dir (with `!logs/.gitkeep`) that doesn't exist; `sql/er-diagram.png` should be confirmed current post-catalog-restructure or marked stale; root `.DS_Store` exists (gitignored, harmless).

## 4. DRY violations (brief item 4)

Nothing relevant is already extracted — `src/helpers.php` has no query-string, pagination, or LIKE helpers today. Eleven pages share the list-page convention: `staff/orders.php`, `customer/orders.php`, `customer/lab_product_users.php`, `customer/lab_delivery_locations.php`, `admin/{customers,accounts,nuclides,products,institutes,labs,pis}.php`. (`registrations.php` deliberately has none of it — small pending-only queue; not a violation.)

### 4.1 The 11-page list machinery — the headline extraction

- **[High] `*_query()` query-string builder — 11 byte-identical copies** (name aside): `institutes.php:39`, `nuclides.php:43`, `labs.php:42`, `products.php:50`, `pis.php:37`, `accounts.php:32`, `customers.php:31`, `staff/orders.php:53`, `customer/orders.php:179`, `lab_product_users.php:55`, `lab_delivery_locations.php:52`. *Approach:* one `build_query(array $overrides = []): string` in helpers.php.
- **[High] Pagination math — 11 copies, identical logic** (ctype_digit page parse, `in_array` size clamp against a per-page const — all eleven are `[10, 20, 50, 100]` — totalPages/clamp/offset/range; only the default size differs deliberately: 10 customer/staff, 20 admin). Representative: `customer/orders.php:40–45,150–152,190–191`; `nuclides.php:25–27,198–200`. *Approach:* `paginate(int $total, int $page, int $pageSize): array` + one shared options const.
- **[High] GET canonicalization — 3 drifted variants** (the one place real divergence already happened — see §5.1 for the concrete bug). Full variant (7 pages, incl. post-clamp `$_GET['page']` re-sync), partial (customers/accounts/staff-orders: status + page_size only), minimal (`customer/orders.php`: page_size + dates but **not** status). *Approach:* fold canonicalization into the shared builder so there's only one variant.
- **[High] `.table-pagination` footer HTML — 11 near-identical ~45-line blocks** (~400 lines; structure and a11y attributes consistent, only hidden-field lists/id prefixes differ). Representative: `customer/orders.php:375–440`, `admin/customers.php:233–273`, `staff/orders.php:349–391`. *Approach:* `src/partials/table_pagination.php` taking the query-builder + hidden-filter map.
- **[Medium] Status-tab count queries — ~9 copies in 3 structural variants**, each variant necessary (orders GROUP BY status; boolean GROUP BY active; derived-CASE on products/labs). Only the "build filter-WHERE without status, count, then extend" scaffolding is shareable. *Approach:* extract the scaffolding only, keep the per-shape SQL.

### 4.2 Other multi-copy patterns

- **[Medium] LIKE-escape — 11 identical copies, zero drift.** `str_replace(['\\','%','_'], …)` + `LIKE ? ESCAPE '\\'` (sites: customers.php:48, accounts.php:153, nuclides.php:158, institutes.php:161, pis.php:174, products.php:220, labs.php:290, staff/orders.php:94, customer/orders.php:97, lab_product_users.php:201, lab_delivery_locations.php:167; all 20 `LIKE ?` usages in the codebase carry the ESCAPE clause). Security-adjacent code that must stay in lockstep — currently in sync, but that's 11 places to keep that way. *Approach:* `like_contains(string $q): string` in helpers.php returning the wrapped `%…%` pattern.
- **[Medium] Customer lab_id lookup — 7 identical copies** (`customer/dashboard.php:12`, `lab_delivery_locations.php:15`, `lab_product_users.php:16`, `orders.php:15`, `order_detail.php:13`, `new_order.php:34`, + the guarded fallback in `layout_customer.php:32`). *Approach:* `current_customer_lab_id(PDO $pdo, int $userId): int`.
- **[Medium] PRG arrival-flag machinery — 7 PHP + 9 JS copies.** The `$justCreated…`/`unset` capture block ×7 (identical) and the `history.replaceState` URL-cleanup script ×9 (7 byte-identical; the 2 detail pages differ only in flag lists, as expected). *Approach:* `consume_arrival_flags(array $flags)` helper + move the JS into script.js keyed off a data attribute. (The stale documentation contradicting this whole convention is §5.2.)
- **[Low] `cancelled → canceled` badge-class ternary — 5 copies** with the identical explanatory comment each time (`staff/orders.php:317`, `customer/orders.php:341`, `staff/order_detail.php:242`, `customer/order_detail.php:311`, `customer/dashboard.php:170`). *Approach:* either a `status_badge_class()` one-liner or a `.badge--cancelled` alias in badges.css (the alias is the smaller diff).

### 4.3 Deliberate duplications — sync status (verified, not violations)

- `generate_temp_password()` ×4 — **byte-identical** (only doc comments vary). In sync.
- `wireModalDirtyTracking()` ×8 — **byte-identical by hash.** In sync.
- Companion `snapshotForm()` ×8 — 6 identical + **2 deliberate documented forks** (`labs.php`: checkbox-state snapshot for the PI roster; `products.php`: skips disabled elements for the lock-mirror hidden inputs). **The finding is the hazard, not the duplication:** a future fix to the base copy has 16 sites to touch, and a naive copy-paste re-sync over labs/products would silently break their forks. *Approach:* add a warning note where the convention is documented (CLAUDE.md's DRY bullet).

## 5. Inconsistent patterns (brief item 5)

### 5.1 [High] Real drift: customer orders list never canonicalizes `status`

`staff/orders.php:34` does `$_GET['status'] = $queueStatus;` after whitelisting; `customer/orders.php` whitelists into `$status` (lines 26–27) but never writes it back, so `?status=garbage` is stripped from every link on the staff queue but carried forward by every builder-generated Prev/Next/tab link on the customer page. Escaped, so cosmetic — but the two "same shape" pages behave differently. *Approach:* one-line fix now; permanently closed by the §4.1 canonicalization extraction.

### 5.2 [High] Stale "no PRG" documentation vs. 9+ pages of actual PRG

`helpers.php:116–120` (`toast_flash` docblock: "this app doesn't redirect-after-POST") and CLAUDE.md's UI-feedback section both describe a no-PRG convention, but PRG-with-arrival-flags is now the norm on 7 CRUD pages + both order-detail pages (verified: `nuclides.php:101`, `pis.php:113`, `institutes.php:103`, `products.php:118`, `labs.php:168`, `lab_product_users.php:135`, `lab_delivery_locations.php:105`, `customer/order_detail.php:103/159/189`, `staff/order_detail.php`). Non-PRG survives only in the documented temp-password exception. The stale comment will mislead the next page author. Per CLAUDE.md's own "fix the file first" rule this is Batch 1 material.

### 5.3 [Medium] Date validation — two techniques, one semantic question

- The regex `^\d{4}-\d{2}-\d{2}$` is identical at all 5 filter sites (`staff/orders.php:24,26`, `customer/orders.php:36,38`, `export_csv.php:21`), but `validate_order_input()` (helpers.php:299) uses the stricter `DateTime::createFromFormat` round-trip — the regex sites accept `2026-02-31` and pass it to MySQL. *Approach:* [Low] `valid_ymd()` helper used everywhere.
- **Semantic inconsistency worth confirming:** `export_csv.php` filters its date range on `o.created_at` (line 49) while both order lists filter the same-looking UI concept on `o.requested_datetime` (`customer/orders.php:113–121`). If intended (report = when placed, lists = when needed), one comment at each site settles it; if not, it's a reporting bug.
- Failure convention differs: lists silently ignore invalid dates (documented as deliberate); `export_csv.php:22–25` hard-`die()`s with a 400. Arguably fine for an export endpoint — normalize only if touched.

### 5.4 Lower-priority inconsistencies

- **[Medium] Empty-state / "Clear filters" divergence:** the two orders lists use a 3-state empty state and a Clear-filters link that *preserves* the active status tab; every admin CRUD page + both customer lab pages use a 2-state version and a bare-URL link that *wipes* tab and page size (9 pages). Same interaction, different outcome — needs a UX decision, then mechanical alignment.
- **[Low] Arrival-flag rendering — 3 shapes of the same idea:** boolean elseif chain (CRUD pages, e.g. `nuclides.php:253–260`), generalized message map + loop (`staff/order_detail.php:217–226` — the best shape), literal `$_GET` elseif (`customer/order_detail.php:282–288`). Converge on the map form when consolidating §4.2's arrival-flag machinery.
- **[Low] Filter param naming drift:** `institute_id`/`lab_id` (customers.php) vs `institute` (labs.php, export_csv.php) vs `nuclide` (products.php, export_csv.php); parse style also differs (raw string + ctype-check at WHERE-build vs int-cast-0 sentinel at parse). Renaming churns bookmarked URLs — document the preferred form for *new* pages instead of renaming.
- **[Low] `accounts.php` `$role` not whitelisted at parse** (line 13 raw passthrough, branch-checked only at 158–162) — the one filter param that skips the parse-time `in_array` convention every `$status` param follows. Escaped on output; align for consistency.
- **[Low] Time-format split:** 16 sites use 24-hour `M j, Y H:i` (all order surfaces) vs 5 sites 12-hour `g:i A` (admin identity/registration timestamps: `admin/dashboard.php:165,223`, `account_detail.php:230`, `customer_detail.php:276`, `registrations.php:196`). The military-time rule covers order fields, and the split is consistent in practice — but undocumented; one CLAUDE.md sentence settles it.
- **[Low] `reports.php`/`export_csv.php` style outliers** (newest code): uppercase `method="GET"` + relative form action; named `:param` + `bindParam` vs positional `?` everywhere else; numbered step-comments; `die()` error strings vs field-error convention; the products dropdown omits the "(inactive)" suffix institutes/nuclides get (query doesn't select `active`). Normalize when next touched.

## 6. script.js organization (the new question for this pass)

**Recommendation: keep the single file.** Revisit only if it grows past ~1,200–1,500 lines or a fourth page-specific concern lands.

### 6.1 The evidence

849 lines, loaded identically (via `asset_url()` + `defer`) by **24 pages that each emit their own script tag** — there is no central JS include (head.php centralizes all 17 CSS links but not the JS). Contents by share: sidebar cluster ~31%, modal system ~29%, toasts 9%, order cascade 9%, form-loading 8%, copy 4%, reports 4%, glue 3%. The sidebar cluster (collapse/off-canvas/submenu/flyout) is one coupled organism, and the modal system is cross-coupled to form-loading (`initConfirmForms` calls `setButtonLoading`) — so any split leaves a ~700-line core regardless. Only the order cascade (74 lines, 2 consumers) and `initReportsForm` (32 lines, 1 page) are cleanly severable: a split buys ~106 lines of transfer on a single-digit-KB-gzipped file served on a LAN, while adding manually-ordered script tags to maintain across 24 pages with no bundler to absorb the drift. The 8-global `window.*` contract (`showToast`, `petcomOpenModal/CloseModal/Confirm`, `petcomSet/ClearButtonLoading`, `petcomInitOrderCascade`) is consumed by inline scripts in 15 PHP files — keeping one file keeps that contract in one place. Every `init*` no-ops gracefully when its hooks are absent, so cross-role dead weight is ~100 unexecuted lines — trivial.

### 6.2 What IS worth fixing in/around the file

- **[High] Bug: flyout title/id hardcoded to "Accounts".** `buildSidebarFlyout` hardcodes `panel.id = 'accounts-flyout'` and `heading.textContent = 'Accounts'` (script.js:216, 221), but `layout_admin.php` has three submenus (Accounts, Catalog, Directory). Opening the Catalog or Directory flyout in collapsed-rail mode shows the heading "Accounts" and mislabels `aria-controls`. *Approach:* read the clicked item's `.menu-label__text` and derive the id from it.
- **[Medium] Dead handler:** the submenu bfcache re-sync (`pageshow`, script.js:180–185) can never matter — the global bfcache backstop (lines 833–835) force-reloads on every persisted restore first. *Approach:* delete the submenu handler (the global reload is the security-pass behavior and stays).
- **[Medium] The 24 duplicated script-tag lines are the actual maintenance smell**, not the file's size. *Approach:* emit the tag once per layout (3 files) + keep it on the 4 standalone auth pages, mirroring how head.php centralizes CSS.
- **[Info]** Two parallel confirm implementations (`initConfirmForms` vs `petcomConfirm`'s stacked lifecycle) share only `buildConfirmModal` — divergence is documented and justified (stacking); no action, just the file's most intricate corner.

## 7. Project-docs drift (CLAUDE.md — Batch 1 per its own "fix the file first" rule)

- Says the repo is "intentionally uncommitted to git" — there is full git history and an origin with PRs.
- Directory layout omits `admin/reports.php`, `admin/export_csv.php`, `public/account_profile.php`, `public/registration_status.php`, and the `docs/` folder ("No docs/ folder yet" — `docs/SECURITY_AUDIT.md` exists).
- Claims `customers.approved_by`/`approved_at` exist alongside `registration_status` — those two columns do not exist in schema.sql.
- UI-feedback section still documents the no-PRG convention contradicted by §5.2.
- Worth adding while in there: the reserved-layout-variables table (§1.1), the snapshotForm fork hazard (§4.3), and the time-format convention (§5.4).

## 8. Security side-notes (kept separate per brief — not quality findings)

Nothing new of substance surfaced. Two hardening-consistency notes, both cosmetic:

- Layouts/head.php use raw `htmlspecialchars()` without explicit `ENT_QUOTES` (19 uses) where the rest of the app uses `e()` — no injectable sink found (values are DB-sourced names), but standardizing on `e()` (§1.2) removes the class of question.
- `accounts.php` `$role` filter param reaches the query-string builder unwhitelisted (§5.4) — output-escaped, no injection path (it's never interpolated into SQL structure), alignment-only.

---

## Recommended fix batches

Small, independently reviewable, one commit each, ordered docs → bugs → deletions → extractions (rising risk/size):

1. **Docs-first (CLAUDE.md's own rule):** all §7 CLAUDE.md corrections; stale comments/docblocks — `helpers.php:118` no-PRG claim, `sql/schema.sql:148`, `auth.php:77` "Phase A.2", `helpers.php:167`, `helpers.php:414`, helpers file docblock. Zero code behavior change.
2. **Small real bugs + dead code (no behavior risk):** flyout title bug (§6.2), dead submenu pageshow handler, dead `data-delivery-method` ×2, dead `$_SESSION['role_id']`, `customer/orders.php` status canonicalization one-liner (§5.1).
3. **Dead CSS removal:** everything in §3.2 (settle the `.app-header`/`.app-footer` intent question in review). Pure deletions, one scannable diff.
4. **List-machinery extraction (the big one):** `build_query()` + `paginate()` + shared canonicalization + `table_pagination.php` partial, applied across the 11 pages (§4.1). Largest payoff; do it as one pattern-change reviewed page-by-page.
5. **Secondary consolidation:** `like_contains()`, `current_customer_lab_id()`, `consume_arrival_flags()` + JS moved into script.js, badge-class alias (§4.2); optionally the §5.4 alignments while touching those files.
6. **Layout scope fix:** `$petcomLayout` single-array refactor + `_sidebar_footer.php` sub-partial + `e()` standardization + script-tag centralization (§1.2, §6.2). Touches every page's chrome — do last, verify by diff + `php -l`.

Deliberately **not** proposed: splitting script.js (§6), removing `customers.registration_status` (schema decision, §3.3), renaming filter params (URL churn, §5.4), un-duplicating `generate_temp_password()`/`wireModalDirtyTracking()` (documented deliberate, §4.3).
