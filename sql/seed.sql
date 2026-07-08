-- ============================================================
-- PETCOM — seed.sql
-- Minimal test data. Load after schema.sql, into an empty
-- `petcom` database (relies on AUTO_INCREMENT starting at 1).
--
-- password_hash values below are placeholders, not real bcrypt
-- hashes — run tools/set_temp_passwords.php once to replace them
-- with real temp-password hashes for these seeded accounts.
--
-- Institutes here ARE the NIH institutes/centers (this is an
-- NIH Clinical Center PET Department system — all customers are
-- NIH-internal; there is no "outside institution" concept).
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
  (2, 'Molecular Imaging Lab', 'Bldg 10', 'B1D43', 1),   -- NCI
  (3, 'Neuroimaging Lab', 'Bldg 10', '2C401', 1),        -- NIMH
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

-- ---- Users (6): 1 admin, 2 staff, 3 customers ----
INSERT INTO users (username, password_hash, must_change_password, active) VALUES
  ('admin1',       'PLACEHOLDER_HASH_SET_BY_TOOLS_SET_TEMP_PASSWORDS', 1, 1), -- 1: admin
  ('staff.rad',    'PLACEHOLDER_HASH_SET_BY_TOOLS_SET_TEMP_PASSWORDS', 1, 1), -- 2: staff, Radiopharmacy
  ('staff.cyc',    'PLACEHOLDER_HASH_SET_BY_TOOLS_SET_TEMP_PASSWORDS', 1, 1), -- 3: staff, Cyclotron
  ('cust.acarter', 'PLACEHOLDER_HASH_SET_BY_TOOLS_SET_TEMP_PASSWORDS', 1, 1), -- 4: customer
  ('cust.bkim',    'PLACEHOLDER_HASH_SET_BY_TOOLS_SET_TEMP_PASSWORDS', 1, 1), -- 5: customer
  ('cust.dpatel',  'PLACEHOLDER_HASH_SET_BY_TOOLS_SET_TEMP_PASSWORDS', 1, 1); -- 6: customer

-- ---- Admin (1) ----
INSERT INTO admins (user_id) VALUES (1);

-- ---- Staff (2, one per category) ----
INSERT INTO staff (user_id, category_id) VALUES
  (2, 1), -- staff.rad -> Radiopharmacy
  (3, 2); -- staff.cyc -> Cyclotron

-- ---- Customers (3, all approved) ----
INSERT INTO customers (user_id, institute_id, lab_id, supervising_pi_id, registration_status, approved_by, approved_at) VALUES
  (4, 2, 1, 1, 'approved', 1, '2026-06-01 09:00:00'), -- cust.acarter: NCI / Molecular Imaging Lab / Dr. Carter
  (5, 3, 2, 2, 'approved', 1, '2026-06-02 09:00:00'), -- cust.bkim: NIMH / Neuroimaging Lab / Dr. Ellison
  (6, 6, 3, 2, 'approved', 1, '2026-06-03 09:00:00'); -- cust.dpatel: NINDS / Cerebrovascular Imaging Lab / Dr. Ellison