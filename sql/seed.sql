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

-- ---- Institutes (6 of the ~27 real NIH institutes/centers) ----
INSERT INTO institutes (name, shorthand_name, active) VALUES
  ('Clinical Center', 'CC', 1),
  ('National Cancer Institute', 'NCI', 1),
  ('National Institute of Mental Health', 'NIMH', 1),
  ('National Heart, Lung, and Blood Institute', 'NHLBI', 1),
  ('National Institute on Aging', 'NIA', 1),
  ('National Institute of Neurological Disorders and Stroke', 'NINDS', 1);

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

-- ---- Categories (2) ----
INSERT INTO categories (category_name) VALUES
  ('Radiopharmacy'),
  ('Cyclotron');

-- ---- Isotopes (5) ----
INSERT INTO isotopes (isotope_name, active) VALUES
  ('C-11', 1),
  ('N-13', 1),
  ('O-15', 1),
  ('F-18', 1),
  ('Ga-68', 1);

-- ---- Delivery options (3) ----
INSERT INTO delivery_options (name) VALUES
  ('Will-call'),
  ('Direct-to-lab'),
  ('Through pharmacy');

-- ---- Compounds (4): 2 Radiopharmacy/Type A, 2 Cyclotron/Type B ----
INSERT INTO compounds (category_id, name, order_type, standard_cost, min_lead_time_hours, active) VALUES
  (1, 'FDG', 'A', 450.00, 24.0, 1),
  (1, 'Ammonia N-13', 'A', 300.00, 4.0, 1),
  (2, 'C-11 Target Run', 'B', 850.00, 48.0, 1),
  (2, 'O-15 Water Production', 'B', 500.00, 12.0, 1);

-- ---- compound_isotopes ----
INSERT INTO compound_isotopes (compound_id, isotope_id) VALUES
  (1, 4), -- FDG <- F-18
  (2, 2), -- Ammonia N-13 <- N-13
  (3, 1), -- C-11 Target Run <- C-11
  (4, 3); -- O-15 Water Production <- O-15

-- ---- compound_delivery_options ----
INSERT INTO compound_delivery_options (compound_id, delivery_option_id) VALUES
  (1, 1), (1, 2),  -- FDG: Will-call, Direct-to-lab
  (2, 2),          -- Ammonia N-13: Direct-to-lab
  (3, 1),          -- C-11 Target Run: Will-call
  (4, 1), (4, 3);  -- O-15 Water Production: Will-call, Through pharmacy

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

-- ---- Admin (1) ----
INSERT INTO admins (user_id) VALUES (1);

-- ---- Staff (2, one per category) ----
INSERT INTO staff (user_id, category_id) VALUES
  (2, 1), -- maria.santos -> Radiopharmacy
  (3, 2); -- james.oconnor -> Cyclotron

-- ---- Customers (4, all approved) ----
INSERT INTO customers (user_id, first_name, last_name, lab_id, supervising_pi_id, registration_status, approved_by, approved_at) VALUES
  (4, 'Alice', 'Carter', 1, 1, 'approved', 1, '2026-06-01 09:00:00'), -- NCI / Molecular Imaging Lab / Dr. Carter
  (5, 'Brian', 'Kim',    2, 2, 'approved', 1, '2026-06-02 09:00:00'), -- NIMH / Neuroimaging Lab / Dr. Ellison
  (6, 'Deepa', 'Patel',  3, 2, 'approved', 1, '2026-06-03 09:00:00'), -- NINDS / Cerebrovascular Imaging Lab / Dr. Ellison
  (7, 'Evan',  'Feng',   1, 1, 'approved', 1, '2026-06-04 09:00:00'); -- NCI / Molecular Imaging Lab / Dr. Carter (lab-mate of Alice)


-- ============================================================
-- Orders (test data)
-- Mostly in Molecular Imaging Lab (lab 1: Alice + Evan) since
-- that's the lab used to develop/verify the customer dashboard.
-- One order in a different lab (lab 2) to prove lab isolation.
-- ============================================================

INSERT INTO orders (order_id, customer_id, compound_id, isotope_id, delivery_option_id, status, cost_snapshot, created_by, created_at, processed_by, processed_at, last_modified_at) VALUES
  (1, 4, 1, 4, 1, 'pending',   450.00, 4, '2026-07-05 10:00:00', NULL, NULL,                  '2026-07-05 10:00:00'), -- Alice: FDG, pending, recent
  (2, 4, 2, 2, 2, 'pending',   300.00, 4, '2026-06-20 08:00:00', NULL, NULL,                  '2026-06-20 08:00:00'), -- Alice: Ammonia N-13, >48h old -> stale
  (3, 4, 3, 1, 1, 'accepted',  850.00, 4, '2026-07-01 09:00:00', 3,    '2026-07-02 11:00:00',  '2026-07-02 11:00:00'), -- Alice: C-11 Target Run, beam mode
  (4, 4, 4, 3, 1, 'completed', 500.00, 4, '2026-06-15 09:00:00', 3,    '2026-06-16 09:00:00',  '2026-06-16 09:00:00'), -- Alice: O-15 Water Production, eob mode, completed
  (5, 4, 1, 4, 2, 'canceled',  450.00, 4, '2026-06-10 09:00:00', NULL, NULL,                  '2026-06-11 09:00:00'), -- Alice: FDG, canceled
  (6, 7, 1, 4, 1, 'pending',   450.00, 7, '2026-07-06 14:00:00', NULL, NULL,                  '2026-07-06 14:00:00'), -- Evan (lab-mate): FDG, pending, recent
  (7, 7, 3, 1, 1, 'accepted',  850.00, 7, '2026-07-03 09:00:00', 3,    '2026-07-04 08:00:00',  '2026-07-04 08:00:00'), -- Evan (lab-mate): C-11 Target Run, eob mode
  (8, 5, 2, 2, 2, 'pending',   300.00, 5, '2026-07-05 11:00:00', NULL, NULL,                  '2026-07-05 11:00:00'); -- Brian, different lab (2) -- must NOT show for Alice/Evan

INSERT INTO order_type_a_details (order_id, activity_mci, requested_datetime) VALUES
  (1, 10.00, '2026-07-10 09:00:00'),
  (2, 8.00,  '2026-06-25 09:00:00'),
  (5, 10.00, '2026-06-12 09:00:00'),
  (6, 10.00, '2026-07-09 09:00:00'),
  (8, 8.00,  '2026-07-08 09:00:00');

INSERT INTO order_type_b_details (order_id, mode, beam_current, bombardment_minutes, eob_activity_mci, eob_datetime) VALUES
  (3, 'beam', 35.00, 60, NULL, NULL),
  (4, 'eob',  NULL,  NULL, 120.00, '2026-06-16 08:00:00'),
  (7, 'eob',  NULL,  NULL, 200.00, '2026-07-04 06:00:00');

INSERT INTO order_audit_log (order_id, changed_by, status_from, status_to, changed_at) VALUES
  (1, 4, NULL,        'pending',   '2026-07-05 10:00:00'),
  (2, 4, NULL,        'pending',   '2026-06-20 08:00:00'),
  (3, 4, NULL,        'pending',   '2026-07-01 09:00:00'),
  (3, 3, 'pending',   'accepted',  '2026-07-02 11:00:00'),
  (4, 4, NULL,        'pending',   '2026-06-15 09:00:00'),
  (4, 3, 'pending',   'accepted',  '2026-06-15 15:00:00'),
  (4, 3, 'accepted',  'completed', '2026-06-16 09:00:00'),
  (5, 4, NULL,        'pending',   '2026-06-10 09:00:00'),
  (5, 4, 'pending',   'canceled',  '2026-06-11 09:00:00'),
  (6, 7, NULL,        'pending',   '2026-07-06 14:00:00'),
  (7, 7, NULL,        'pending',   '2026-07-03 09:00:00'),
  (7, 3, 'pending',   'accepted',  '2026-07-04 08:00:00'),
  (8, 5, NULL,        'pending',   '2026-07-05 11:00:00');