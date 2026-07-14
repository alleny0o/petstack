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

**Menu (7 tables, all built):**
12. `isotopes` (built) — customer picks isotope first; compound choices are then filtered to compatible ones via `compound_isotopes`, never the reverse
13. `categories` (built) — e.g. Radiopharmacy, Cyclotron — admin-editable, referenced by both `staff.category_id` and `compounds.category_id`
14. `compounds` (built) — the master compound list, admin-controlled (add/edit/remove only by admin — see `institute_compounds` below for how institutes get their own subset). `standard_cost` is `DECIMAL(10,2) NULL` — nullable because pricing isn't finalized yet; once set, it's what the future `orders.cost_snapshot` (Phase G) copies at order-creation time, so a later price change never affects historical orders
15. `compound_isotopes` (built) — join: usually 1:1, occasionally a compound allows multiple isotopes
16. `delivery_options` (built) — e.g. Dose Delivery, Target Delivery
17. `compound_delivery_options` (built) — join: each compound lists its own allowed delivery methods, not a global list
18. `institute_compounds` (built) — join: not part of the original 6-table Menu list. Added per the requirements interview's master-vs-custom-list rule: there's one master `compounds` list, and each institute curates its own visible ordering subset — add compounds from the master list, remove from their own list — but can never edit/add/remove the master list itself. A customer's order-time compound list is derived via their lab's `institute_id` (`labs.institute_id`) joined through this table, never stored redundantly on `customers`. Its PK became a surrogate `institute_compound_id` in Phase G.2 (was a plain `(institute_id, compound_id)` composite) so `order_type_a_details` has a single column to FK against — the composite is preserved as a `UNIQUE` key, so the no-duplicate-pairing guarantee still holds

**Delivery & Product Users (2 tables, all built):**
19. `delivery_locations` (built) — customer-managed, lab-scoped destination list ("the NIMH cGMP lab"), added by the Phase G.1 requirements interview note on delivery locations. Distinct from `labs.building`/`labs.room` (a lab's own home address) — a lab can have several named delivery destinations beyond its own room. Ownership is the lab, not the individual customer, so any customer in a lab reuses locations another customer in that lab already added. CRUD UI ships with the Phase G.3 order form, not built here
20. `product_users` (built) — customer-managed, lab-scoped list of who an ordered activity is actually for (the "John Doe, chemist in her lab" entity from the requirements interview). Not a `users` row — no login, no username/password, no role. Same lab-level reuse pattern as `delivery_locations`. CRUD UI ships with G.3

**Orders (5 tables, all built):**
21. `orders` (built) — `order_type` ENUM('A','B'); `status` ENUM('pending','processing','completed','cancelled'); `cost_snapshot` captured at creation, never recalculated even if `compounds.standard_cost` later changes. `order_id` is `AUTO_INCREMENT` and never reused — orders are never deleted, only status-transitioned (enforced in app logic, not the DB). Completed orders are terminal; returns go back to `'pending'` (no separate "returned" status — `order_audit_log`, Phase I, will record that a return happened)
22. `order_type_a_details` (built) — extends `orders` via `order_id` (same shared-PK pattern as `customers`/`staff`/`admins` off `users`). `institute_compound_id` FKs to `institute_compounds` (not `compounds` directly), so it's structurally impossible to order a compound outside the customer's institute list. Also FKs to `delivery_options`, `delivery_locations`, and `product_users` (the Phase G.1 tables)
23. `order_type_b_details` (built) — intentionally minimal placeholder (`order_id`, `special_instructions` only); real cyclotron run parameters (beam current/bombardment time vs. EOB activity/datetime) await Phase J requirements
24. `order_public_comments` (built) — append-only (no `UPDATE`/`DELETE` endpoint, ever), visible to customer + staff/admin
25. `order_internal_notes` (built) — append-only, structurally identical to `order_public_comments` but a genuinely separate table (not a visibility flag on one shared table) so a query can never leak a note to a customer. Staff/admin only — the app layer never lets a customer post here, even though `author_role` technically allows it for structural parity

**Audit Log (1 table, not built yet):**
26. `order_audit_log` (status changes only — pending→processing→completed/cancelled, timestamp, who — not field-level diffing)
