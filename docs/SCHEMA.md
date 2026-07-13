# PETCOM Database Schema

Tables are grouped by area below; the group composition is stable even as tables
within it get built out. Tables marked **(built)** exist in `sql/schema.sql` today
— see that file for exact columns/constraints and build order (FK-safe order,
not the narrative order here). Unmarked tables are designed but not yet built.

**Identity (11 tables, all built):**
1. `institutes` (built)
2. `labs` (built)
3. `pis` (built)
4. `lab_pis` (built) — join: a lab can have multiple PIs, a PI can oversee multiple labs
5. `users` (built) — shared login table: username, password_hash, must_change_password, failed_login_count, locked_until — used by all three roles
6. `password_history` (built) — prior password hashes, for reuse prevention in `change_password.php`
7. `lockout_events` (built) — records a lockout event each time a login attempt trips the failed-attempt threshold
8. `customers` (built) — extends `users` via `user_id`; institute/lab/supervising_pi locked at approval. `registration_status` is set to `'approved'` when the row is created by the C.2 approval flow — a `customers` row only ever exists once a request is approved, so this is effectively a constant, kept because `customer/dashboard.php` displays it. The old `approved_by`/`approved_at` columns were dropped in C.2 as genuinely unused (see `customer_registration_requests.reviewed_by_admin_id`/`reviewed_at` for that bookkeeping now)
9. `customer_registration_requests` (built) — holds a public self-registration submission until an admin reviews it (see Business Rules in CLAUDE.md)
10. `staff` (built) — extends `users` via `user_id`; has a single `category_id` — one category per staff member, not a junction table
11. `admins` (built) — extends `users` via `user_id`

**Menu (6 tables, 1 built):**
12. `isotopes`
13. `categories` (built) — e.g. Radiopharmacy, Cyclotron — admin-editable, referenced by both `staff.category_id` and the future `compounds.category_id`
14. `compounds`
15. `compound_isotopes` (join: usually 1:1, occasionally a compound allows multiple isotopes)
16. `delivery_options`
17. `compound_delivery_options` (join: each compound lists its own allowed delivery methods)

**Orders (6 tables, none built yet):**
18. `orders`
19. `order_type_a_details` (dose orders: activity_mci, requested_datetime)
20. `order_type_b_details` (cyclotron orders: either beam_current+bombardment_minutes OR eob_activity_mci+eob_datetime, never both)
21. `order_public_comments` (append-only, visible to customer + staff)
22. `order_internal_notes` (append-only, staff-only)
23. `order_audit_log` (status changes only — pending→accepted→completed/canceled, timestamp, who — not field-level diffing)
