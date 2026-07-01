# PETStack: Project Plan

Radiotracer ordering system for NIH Clinical Center PET Dept. Replaces
legacy HSS system. 6-week build, 3 people.

---

## Who does what

Split by page (not frontend/backend — vanilla PHP mixes both per file).

| Person   | Owns                                          |
|----------|------------------------------------------------|
| Xiaofan  | Database, UI/visual design (mockups)           |
| Allen    | Login/accounts, admin setup, dashboard/base CSS (done) |
| Anthony  | Ordering, staff processing                     |

One person per page at a time — flag it before touching a shared file.

---

## How we work

Branch → PR → merge. Never push to `main` directly. Small PRs. `main` stays working.

---

## Build order

**Phase 1: Foundation**
- [x] Planning docs, `.gitignore`, config
- [x] Base CSS + sidebar (collapse, mobile off-canvas, dark mode) *(Allen)*
- [ ] `sql/schema.sql` *(Xiaofan)*
- [ ] `src/db.php` *(first to grab it)*

**Phase 2: Login and accounts** *(Allen)*
- [ ] `src/auth.php` — login, `require_role()`, session guard
- [ ] Login/logout, forced password change
- [ ] Self-registration + approval queue

**Phase 3: Admin setup** *(Allen)*
- [ ] Manage compounds, isotopes, categories, delivery options
- [ ] Manage institutes, labs, PIs, customers, users

**Phase 4: Ordering** *(Anthony)*
- [ ] Order form (Type A dose / Type B cyclotron), isotope-first, lead-time validation
- [ ] Order list + detail view

**Phase 5: Processing** *(Anthony)*
- [ ] Staff queue, accept/modify/complete/cancel/return
- [ ] Public comments + internal notes

**Phase 6: Reports**
- [ ] Six report types, filterable, CSV/PDF (cost = admin-only)

**Phase 7: Polish**
- [ ] Responsive pass on remaining pages, `.htaccess`/HTTPS/error pages, deploy notes

---

## Already decided

- Vanilla PHP + PDO + MariaDB, no framework/Composer
- MariaDB is RHEL's default repo — no extra setup needed
- `public/` flat, no role subfolders — access gated per-page in code
- `src/` outside web root (DB password unreachable by URL)
- Soft-delete only; cost snapshotted per order; order IDs never reused
- No email integration (admin notifies manually) — no phone-in orders
- Auth centralized in `auth.php`; pages only check `$_SESSION['role']`/`user_id`
  — keeps a future SSO swap contained to `auth.php`

Details: `docs/STRUCTURE.md`, `docs/SCHEMA.md`.

---

## Business rules checklist

**Auth**
- [ ] 15-min idle timeout · 5 failed logins → 15-min lockout
- [ ] Explicit strong-password rule (not just "industry standard")
- [ ] Temp passwords (registration + admin reset) are one-time use
- [ ] Admin can trigger a reset, never view/set the actual password

**Registration**
- [ ] Collects: institute, investigator (name/email/phone/lab), PI (name/email/phone), NRC contact
- [ ] Institute/lab/PI locked after creation — admin-only to change
- [ ] Username = NIH email (unique, no dupe check needed)
- [ ] Pending until admin approves/rejects (rejection needs a reason)
- [ ] Admin notifies manually via email — app sends none
- [ ] `reg_status` lookup by email, no password

**Ordering**
- [ ] Isotope-first — compounds filtered by isotope
- [ ] Type A/B independent, not parent/child
- [ ] Type B: beam-current×time OR EOB-activity+datetime (mutually exclusive)
- [ ] Lead time + delivery options are per-compound, not global
- [ ] Cost hidden from customers, admin-only
- [ ] No quantity limits

**Status/lifecycle**
- [ ] Customer edits/cancels own order only while `pending`
- [ ] Customer can view (not edit) all orders in their lab
- [ ] Staff limited to their assigned categories
- [ ] Return → back to `pending` (logged, not a separate status)
- [ ] Completed = terminal, enforced in logic not just UI
- [ ] Status changes logged (who/what/when), no field-diffing needed
- [ ] Public comments + internal notes are separate append-only threads
- [ ] "Modified" indicator for orders changed since last customer view

---

## Current status

Foundation mostly done (docs, config, base CSS/sidebar/dark mode). Next:
Xiaofan on schema, Allen on login then admin setup, Anthony on ordering.
Goal: login working end-to-end before ordering starts.