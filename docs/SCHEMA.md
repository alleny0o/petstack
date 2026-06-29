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
- **B-details** = beam-current x time OR EOB activity + date/time. (Type B = cyclotron)
- Order IDs always increment, never reused.
- `cost_snapshot` is copied onto the order when placed, so old reports stay
  correct if a compound's cost changes later.

---

## The menu side

```
CATEGORY                          (e.g. Radiopharmacy, Cyclotron)
  └─ COMPOUND                     (each compound belongs to one category)
```

`compound` also stores: standard cost, minimum lead time, order type (A/B),
active/inactive flag.

---

## The pairing tables (just lists of pairs)

These four are the same idea: neither side can "store" the other, so a
little table in the middle lists which goes with which.

```
LAB_PIS              =  (lab, pi) pairs
                        a lab can have many PIs, a PI can cover many labs

USER_CATEGORIES      =  (user, category) pairs
                        controls who is allowed to process what  <- important

COMPOUND_ISOTOPES    =  (compound, isotope) pairs
                        which isotopes a compound is allowed to use

COMPOUND_DELIVERY    =  (compound, delivery_option) pairs
                        which delivery options a compound allows
```

---

## Identity tables

```
CUSTOMERS   : tied into the org side (lab, institute, PI). Self-register, admin approves.
USERS       : staff. Not tied to a lab. Linked to categories they can process.
ADMINS      : super-users. Everything a user does, plus all config + reports.
```

All three hold: login, password hash, must-change-password flag,
failed-login count, lockout timestamp, active flag.

`customer_registration_requests`: pending sign-ups waiting for admin
approval (status: pending / approved / rejected + reason). Becomes a real
customer row once approved.

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
13. user_categories
14. customers
15. customer_registration_requests
16. orders
17. order_type_a_details
18. order_type_b_details
19. order_public_comments
20. order_internal_notes
21. order_audit_log