# PETStack: Database Schema (plain English)

How to read this: indentation means "belongs to / lives inside."
Arrows (`-->`) mean "points at" (the row stores that thing's ID).
Pairing tables are just two-column lists of "this goes with that."

---

## The spine (org to orders)

```
INSTITUTE                         (e.g. NCI, NIMH)
  └─ LAB                          (belongs to one institute)
       └─ CUSTOMER                (belongs to one lab + one institute)
            └─ places --> ORDER   (a customer places many orders)
```

A customer's institute, lab, and supervising PI are locked when the
account is created. Only an admin can change them later.

Every order is created by the customer themselves, by logging in and
placing it. There is no other way to create an order (no phone-in /
staff-on-behalf-of-customer path — cut for complexity).

---

## What an order points at

Every order stores the IDs of one of each:

```
ORDER picks:
   ├─ COMPOUND    (what's being ordered)
   ├─ ISOTOPE     (which isotope)
   └─ DELIVERY    (how it gets there)
```

---

## What an order carries

```
ORDER also has:
   ├─ A-details  OR  B-details   (one or the other, by order type)
   ├─ public comments            (customer can see these)
   ├─ internal notes             (staff only)
   └─ audit log                  (status-change history)
```

- **A-details** = activity (mCi) + requested date/time. (Type A = dose order)
- **B-details** = beam-current x time OR EOB activity + date/time, never both
  (enforced by a `CHECK` constraint on `order_type_b_details`). (Type B = cyclotron)
- No phone-in / staff-on-behalf-of-customer path. This was cut for complexity
  per `PLAN.md`; a business-rules note elsewhere describing phone-in orders is
  stale and should be reconciled by the team, not the schema.
- Order IDs always increment, never reused.
- `cost_snapshot` is copied onto the order when placed, so old reports stay
  correct if a compound's cost changes later.
- Status values: `pending`, `accepted`, `completed`, `canceled`. A returned
  order goes back to `pending` (no separate "returned" status) — the audit
  log still records that the return happened.

---

## The menu side

```
CATEGORY                          (e.g. Radiopharmacy, Cyclotron)
  └─ COMPOUND                     (each compound belongs to one category)
```

`compound` also stores: standard cost, minimum lead time, order type (A/B),
active/inactive flag. Category lives on the compound itself, not on the
compound-isotope pairing — a compound has exactly one category regardless of
which isotope it's ordered with.

---

## The pairing tables (just lists of pairs)

These four are the same idea: neither side can "store" the other, so a
little table in the middle lists which goes with which.

```
LAB_PIS              =  (lab, pi) pairs
                        a lab can have many PIs, a PI can cover many labs

STAFF_CATEGORIES     =  (staff, category) pairs
                        controls who is allowed to process what  <- important
                        (note: sql/schema.sql currently models this as a
                        single `category` column on `staff` instead)

COMPOUND_ISOTOPES    =  (compound, isotope) pairs
                        which isotopes a compound is allowed to use

COMPOUND_DELIVERY    =  (compound, delivery_option) pairs
                        which delivery options a compound allows
```

---

## Identity tables

```
CUSTOMERS   : tied into the org side (lab, institute, PI). Self-register, admin approves.
STAFF       : not tied to a lab. Linked to categories they can process.
ADMINS      : super-users. Everything staff can do, plus all config + reports.
```

All three hold: login, password hash, must-change-password flag,
failed-login count, lockout timestamp, active flag.

`customer_registration_requests`: pending sign-ups waiting for admin
approval (status: pending / approved / rejected + reason). Becomes a real
customer row once approved. Fields collected: institute, investigator
(name, email, phone, lab building + room), PI (name, email, phone), and
NRC license contact (name, phone, email — for shipping orders).

---

## Full table list (for CREATE TABLE reference)

Build in this order so foreign keys never point at a table that doesn't
exist yet:

1. institutes
2. labs
3. pis
4. lab_pis
5. categories
6. isotopes
7. delivery_options
8. compounds
9. compound_isotopes
10. compound_delivery_options
11. users
12. admins
13. staff_categories
14. customers
15. customer_registration_requests
16. orders
17. order_type_a_details
18. order_type_b_details
19. order_public_comments
20. order_internal_notes
21. order_audit_log