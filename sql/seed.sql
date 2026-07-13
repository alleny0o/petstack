-- ============================================================
-- PETCOM — seed.sql
-- Minimal test data. Load after schema.sql, into an empty
-- `petcom` database (relies on AUTO_INCREMENT starting at 1).
--
-- password_hash values are placeholders — run
-- tools/set_temp_passwords.php once to replace with real hashes.
--
-- Institutes here ARE the NIH institutes/centers (NIH Clinical
-- Center PET Department system — all customers are NIH-internal).
--
-- customers does not store institute_id directly — always
-- derived via lab_id -> labs.institute_id.
--
-- customer name is first_name + last_name (no middle_initial —
-- removed as unnecessary complexity with no real requirement
-- behind it).
--
-- Usernames follow the real convention: NIH email address.
-- ============================================================

-- ---- Institutes (all 27 real NIH institutes/centers) ----
-- ids 1-6 are the original seed set (referenced by lab_id below via
-- their institute_id, so their order/ids are left unchanged); ids 7-27
-- are the rest of the real NIH ICs, appended rather than interleaved.
INSERT INTO institutes (name, shorthand_name, active) VALUES
  ('Clinical Center', 'CC', 1),
  ('National Cancer Institute', 'NCI', 1),
  ('National Institute of Mental Health', 'NIMH', 1),
  ('National Heart, Lung, and Blood Institute', 'NHLBI', 1),
  ('National Institute on Aging', 'NIA', 1),
  ('National Institute of Neurological Disorders and Stroke', 'NINDS', 1),
  ('National Eye Institute', 'NEI', 1),
  ('National Human Genome Research Institute', 'NHGRI', 1),
  ('National Institute on Alcohol Abuse and Alcoholism', 'NIAAA', 1),
  ('National Institute of Allergy and Infectious Diseases', 'NIAID', 1),
  ('National Institute of Arthritis and Musculoskeletal and Skin Diseases', 'NIAMS', 1),
  ('National Institute of Biomedical Imaging and Bioengineering', 'NIBIB', 1),
  ('Eunice Kennedy Shriver National Institute of Child Health and Human Development', 'NICHD', 1),
  ('National Institute on Deafness and Other Communication Disorders', 'NIDCD', 1),
  ('National Institute of Dental and Craniofacial Research', 'NIDCR', 1),
  ('National Institute of Diabetes and Digestive and Kidney Diseases', 'NIDDK', 1),
  ('National Institute on Drug Abuse', 'NIDA', 1),
  ('National Institute of Environmental Health Sciences', 'NIEHS', 1),
  ('National Institute of General Medical Sciences', 'NIGMS', 1),
  ('National Institute on Minority Health and Health Disparities', 'NIMHD', 1),
  ('National Institute of Nursing Research', 'NINR', 1),
  ('National Library of Medicine', 'NLM', 1),
  ('National Center for Advancing Translational Sciences', 'NCATS', 1),
  ('National Center for Complementary and Integrative Health', 'NCCIH', 1),
  ('Center for Information Technology', 'CIT', 1),
  ('Center for Scientific Review', 'CSR', 1),
  ('Fogarty International Center', 'FIC', 1);

-- ---- Labs (3) ----
INSERT INTO labs (institute_id, lab_name, building, room, active) VALUES
  (2, 'Molecular Imaging Lab', 'Bldg 10', 'B1D43', 1),      -- NCI
  (3, 'Neuroimaging Lab', 'Bldg 10', '2C401', 1),           -- NIMH
  (6, 'Cerebrovascular Imaging Lab', 'Bldg 10', 'C107', 1); -- NINDS

-- ---- PIs (2) ----
INSERT INTO pis (pi_name, email, phone, active) VALUES
  ('Dr. Susan Carter', 'susan.carter@nih.gov', '301-555-0101', 1),
  ('Dr. Mark Ellison', 'mark.ellison@nih.gov', '301-555-0199', 1);

-- ---- lab_pis (a lab can have multiple PIs, a PI can oversee multiple labs) ----
INSERT INTO lab_pis (lab_id, pi_id) VALUES
  (1, 1), -- Molecular Imaging Lab <- Dr. Carter
  (2, 1), -- Neuroimaging Lab <- Dr. Carter
  (2, 2), -- Neuroimaging Lab <- Dr. Ellison (lab with two PIs)
  (3, 2); -- Cerebrovascular Imaging Lab <- Dr. Ellison

-- ---- Categories (3) ----
-- Administration exists solely so the seeded admin's staff row can carry a
-- real (if cosmetic) category_id, per the NOT NULL constraint on
-- staff.category_id. The app bypasses category restrictions for admins.
INSERT INTO categories (category_name) VALUES
  ('Radiopharmacy'),
  ('Cyclotron'),
  ('Administration');

-- ---- Users (7): 1 admin, 2 staff, 4 customers ----
-- Usernames are real NIH-email-style, matching Kris's requirement
-- that username = NIH email address.
INSERT INTO users (username, password_hash, must_change_password, active) VALUES
  ('robert.nguyen@nih.gov',  'PLACEHOLDER_HASH_SET_BY_TOOLS_SET_TEMP_PASSWORDS', 1, 1), -- 1: admin
  ('maria.santos@nih.gov',   'PLACEHOLDER_HASH_SET_BY_TOOLS_SET_TEMP_PASSWORDS', 1, 1), -- 2: staff, Radiopharmacy
  ('james.oconnor@nih.gov',  'PLACEHOLDER_HASH_SET_BY_TOOLS_SET_TEMP_PASSWORDS', 1, 1), -- 3: staff, Cyclotron
  ('alice.carter@nih.gov',   'PLACEHOLDER_HASH_SET_BY_TOOLS_SET_TEMP_PASSWORDS', 1, 1), -- 4: customer
  ('brian.kim@nih.gov',      'PLACEHOLDER_HASH_SET_BY_TOOLS_SET_TEMP_PASSWORDS', 1, 1), -- 5: customer
  ('deepa.patel@nih.gov',    'PLACEHOLDER_HASH_SET_BY_TOOLS_SET_TEMP_PASSWORDS', 1, 1), -- 6: customer
  ('evan.feng@nih.gov',      'PLACEHOLDER_HASH_SET_BY_TOOLS_SET_TEMP_PASSWORDS', 1, 1); -- 7: customer (lab-mate of Alice, for lab-wide visibility testing)

-- ---- Staff (3) ----
-- Must be inserted before admins: admins.user_id now FKs to staff.user_id
-- (every admin is also staff), so the admin's staff row has to exist first.
INSERT INTO staff (user_id, first_name, last_name, category_id) VALUES
  (1, 'Robert', 'Nguyen',   3), -- robert.nguyen -> Administration (admin; category is cosmetic)
  (2, 'Maria',  'Santos',   1), -- maria.santos -> Radiopharmacy
  (3, 'James',  'O''Connor', 2); -- james.oconnor -> Cyclotron

-- ---- Admin (1) ----
-- References the staff row above.
INSERT INTO admins (user_id) VALUES (1);

-- ---- Customers (4, all approved) ----
INSERT INTO customers (user_id, first_name, last_name, lab_id, supervising_pi_id, registration_status) VALUES
  (4, 'Alice', 'Carter', 1, 1, 'approved'), -- NCI / Molecular Imaging Lab / Dr. Carter
  (5, 'Brian', 'Kim',    2, 2, 'approved'), -- NIMH / Neuroimaging Lab / Dr. Ellison
  (6, 'Deepa', 'Patel',  3, 2, 'approved'), -- NINDS / Cerebrovascular Imaging Lab / Dr. Ellison
  (7, 'Evan',  'Feng',   1, 1, 'approved'); -- NCI / Molecular Imaging Lab / Dr. Carter (lab-mate of Alice)

-- ---- Delivery locations (5) ----
-- Lab-scoped, not customer-scoped -- any customer in a lab can reuse a
-- location another customer in that same lab already added. One "default"
-- location per lab matching the lab's own building/room, plus an alternate
-- destination for two of the labs.
INSERT INTO delivery_locations (lab_id, location_name, building, room, active) VALUES
  (1, 'Molecular Imaging Lab',       'Bldg 10', 'B1D43', 1), -- NCI, lab's own room
  (1, 'NCI Radiochemistry Suite',    'Bldg 10', 'B1B40', 1), -- NCI, alt destination
  (2, 'Neuroimaging Lab',            'Bldg 10', '2C401', 1), -- NIMH, lab's own room
  (2, 'NIMH cGMP Lab',               'Bldg 10', '1C660', 1), -- NIMH, alt destination
  (3, 'Cerebrovascular Imaging Lab', 'Bldg 10', 'C107',  1); -- NINDS, lab's own room

-- ---- Product users (4) ----
-- The "who the activity is actually for" entity -- not users rows, no
-- login. Lab-scoped and reusable across customers in the same lab.
INSERT INTO product_users (lab_id, first_name, last_name, active) VALUES
  (1, 'Sarah',  'Nguyen', 1), -- NCI / Molecular Imaging Lab
  (2, 'John',   'Doe',    1), -- NIMH / Neuroimaging Lab
  (2, 'Priya',  'Iyer',   1), -- NIMH / Neuroimaging Lab
  (3, 'Marcus', 'Webb',   1); -- NINDS / Cerebrovascular Imaging Lab

-- ---- Isotopes (4, all real PET isotopes) ----
INSERT INTO isotopes (isotope_name, active) VALUES
  ('F-18', 1),
  ('C-11', 1),
  ('N-13', 1),
  ('O-15', 1);

-- ---- Delivery options (2) ----
INSERT INTO delivery_options (option_name, active) VALUES
  ('Dose Delivery', 1),
  ('Target Delivery', 1);

-- ---- Compounds (6) ----
-- standard_cost set to placeholder prices, roughly matching the historical
-- cost_snapshot values already seeded on orders below -- Phase G.3 needs
-- these non-NULL since orders.cost_snapshot is NOT NULL and there's no
-- admin catalog UI yet (Phase K, stretch) to set them any other way.
-- category_id: 1 = Radiopharmacy (needs synthesis), 2 = Cyclotron (N-13
-- Ammonia and O-15 Water have half-lives of 9.97 and 2.04 minutes --
-- typically produced and delivered directly at the cyclotron, no time
-- for radiopharmacy synthesis/packaging).
INSERT INTO compounds (compound_name, category_id, standard_cost, active) VALUES
  ('Fludeoxyglucose F 18 (FDG)', 1, 475.00, 1), -- 1
  ('Sodium Fluoride F 18 (NaF)', 1, 380.00, 1), -- 2
  ('Acetate C 11',               1, 400.00, 1), -- 3
  ('Methionine C 11',            1, 430.00, 1), -- 4
  ('Ammonia N 13',               2, 320.00, 1), -- 5
  ('Water O 15',                 2, 310.00, 1); -- 6

-- ---- compound_isotopes (1:1 for all seeded compounds) ----
INSERT INTO compound_isotopes (compound_id, isotope_id) VALUES
  (1, 1), -- FDG <- F-18
  (2, 1), -- NaF <- F-18
  (3, 2), -- Acetate <- C-11
  (4, 2), -- Methionine <- C-11
  (5, 3), -- Ammonia <- N-13
  (6, 4); -- Water <- O-15

-- ---- compound_delivery_options ----
-- Ammonia gets both: short enough half-life to need direct on-site
-- delivery, but also viable as a dispensed dose depending on distance.
-- Water is Target Delivery only -- a 2-minute half-life leaves no room
-- for anything but immediate on-site use.
INSERT INTO compound_delivery_options (compound_id, delivery_option_id) VALUES
  (1, 1), -- FDG -> Dose Delivery
  (2, 1), -- NaF -> Dose Delivery
  (3, 1), -- Acetate -> Dose Delivery
  (4, 1), -- Methionine -> Dose Delivery
  (5, 1), -- Ammonia -> Dose Delivery
  (5, 2), -- Ammonia -> Target Delivery
  (6, 2); -- Water -> Target Delivery

-- ---- institute_compounds ----
-- Only the 3 institutes with seeded labs get a custom list (the other 24
-- institutes/centers have no labs yet, so no ordering customers to serve).
-- NINDS's Cerebrovascular Imaging Lab gets O-15 Water (cerebral blood flow)
-- and N-13 Ammonia (perfusion) -- realistic fits for that lab's actual
-- research use.
INSERT INTO institute_compounds (institute_id, compound_id) VALUES
  (2, 1), (2, 2), (2, 3), (2, 4), -- NCI: FDG, NaF, Acetate, Methionine
  (3, 1), (3, 4),                 -- NIMH: FDG, Methionine
  (6, 1), (6, 5), (6, 6);         -- NINDS: FDG, Ammonia, Water
-- institute_compound_id lands in insertion order: 1-4 NCI (FDG/NaF/
-- Acetate/Methionine), 5-6 NIMH (FDG/Methionine), 7-9 NINDS (FDG/Ammonia/
-- Water) -- referenced by institute_compound_id below.

-- ---- Orders (5) ----
-- Covers both order types and all four statuses. Delivery options are
-- cross-checked against compound_delivery_options above so nothing here
-- references an invalid compound/delivery-option combination.
INSERT INTO orders (order_type, customer_id, status, cost_snapshot) VALUES
  ('A', 4, 'pending',    450.00), -- 1: Alice Carter (NCI) -- FDG
  ('A', 7, 'processing', 380.00), -- 2: Evan Feng (NCI) -- NaF
  ('A', 5, 'completed',  475.00), -- 3: Brian Kim (NIMH) -- FDG, for John Doe, delivered to the NIMH cGMP Lab (the requirements-interview example)
  ('A', 6, 'cancelled',  310.00), -- 4: Deepa Patel (NINDS) -- O-15 Water
  ('B', 6, 'pending',    600.00); -- 5: Deepa Patel (NINDS) -- cyclotron run, placeholder details

-- ---- order_type_a_details (4) ----
INSERT INTO order_type_a_details (order_id, institute_compound_id, delivery_option_id, activity_mci, requested_datetime, delivery_location_id, product_user_id, special_instructions) VALUES
  (1, 1, 1, 10.00, '2026-07-20 09:00:00', 1, 1, NULL),                                          -- Alice: FDG -> Molecular Imaging Lab, for Sarah Nguyen
  (2, 2, 1, 8.50,  '2026-07-21 10:30:00', 2, 1, NULL),                                           -- Evan: NaF -> NCI Radiochemistry Suite, for Sarah Nguyen
  (3, 5, 1, 12.00, '2026-07-15 08:00:00', 4, 2, 'Deliver to Jane Doe; for use by John Doe.'),    -- Brian: FDG -> NIMH cGMP Lab, for John Doe
  (4, 9, 2, 5.00,  '2026-07-18 07:30:00', 5, 4, 'Short half-life -- confirm delivery window day-of.'); -- Deepa: O-15 Water -> Cerebrovascular Imaging Lab, for Marcus Webb

-- ---- order_type_b_details (1) ----
-- Placeholder table -- see schema.sql comment. No real cyclotron run
-- parameters until Phase J.
INSERT INTO order_type_b_details (order_id, special_instructions) VALUES
  (5, 'Standard bombardment run -- confirm beam time with cyclotron operator.');

-- ---- order_public_comments (4) ----
INSERT INTO order_public_comments (order_id, author_id, author_role, comment_text) VALUES
  (1, 4, 'customer', 'Please confirm the delivery window is still 9am.'),
  (2, 2, 'staff',    'Dose is prepared and on track for pickup.'),
  (3, 2, 'staff',    'Delivered and confirmed received by John Doe.'),
  (5, 6, 'customer', 'Let me know if additional target material is needed.');

-- ---- order_internal_notes (2) ----
-- Staff/admin only, never customer-visible.
INSERT INTO order_internal_notes (order_id, author_id, author_role, note_text) VALUES
  (2, 1, 'admin', 'Customer requested rush processing -- approved given lab history.'),
  (4, 3, 'staff', 'Order cancelled per customer request -- O-15 half-life made the original window infeasible.');