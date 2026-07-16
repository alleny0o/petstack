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
INSERT INTO staff (user_id, category_id) VALUES
  (1, 3), -- robert.nguyen -> Administration (admin; category is cosmetic)
  (2, 1), -- maria.santos -> Radiopharmacy
  (3, 2); -- james.oconnor -> Cyclotron

-- ---- Admin (1) ----
-- References the staff row above.
INSERT INTO admins (user_id) VALUES (1);

-- ---- Customers (4, all approved) ----
INSERT INTO customers (user_id, first_name, last_name, lab_id, supervising_pi_id, registration_status) VALUES
  (4, 'Alice', 'Carter', 1, 1, 'approved'), -- NCI / Molecular Imaging Lab / Dr. Carter
  (5, 'Brian', 'Kim',    2, 2, 'approved'), -- NIMH / Neuroimaging Lab / Dr. Ellison
  (6, 'Deepa', 'Patel',  3, 2, 'approved'), -- NINDS / Cerebrovascular Imaging Lab / Dr. Ellison
  (7, 'Evan',  'Feng',   1, 1, 'approved'); -- NCI / Molecular Imaging Lab / Dr. Carter (lab-mate of Alice)



-- ---- Orders ----
INSERT INTO orders (
  customer_id, compound_id, product_user_id, product_id, location_id, 
  activity_mci, status, created_at, created_by, delivery_option, 
  delivery_time, processed_by, processed_at, cost_snapshot, 
  last_modified_by, special_instructions, cancelation_notes
) VALUES 
-- Row 1: Completed delivery order
(
  101, 5001, 201, 301, 10, 
  150.5, 'completed', '2026-07-10 09:00:00', 101, 'delivery', 
  '2026-07-10 14:00:00', 99, '2026-07-10 14:15:00', 125.50, 
  99, 'Leave at the front desk, ring bell.', NULL
),

-- Row 2: Pending pharmacy pickup
(
  102, 5002, NULL, 302, 12, 
  NULL, 'pending', '2026-07-16 08:30:00', 102, 'pharmacy', 
  '2026-07-17 12:00:00', NULL, NULL, 45.00, 
  102, NULL, NULL
),

-- Row 3: Canceled order with notes
(
  103, 5001, 203, 303, NULL, 
  75.0, 'canceled', '2026-07-15 11:00:00', 103, 'pickup', 
  '2026-07-16 10:00:00', 99, '2026-07-15 11:30:00', 89.99, 
  99, 'Needs refrigeration', 'Customer called to cancel due to travel plans.'
),

-- Row 4: Ready for pickup order
(
  104, 5003, 204, 301, 15, 
  320.0, 'ready for pickup', '2026-07-16 06:00:00', 104, 'pickup', 
  '2026-07-16 16:00:00', 98, '2026-07-16 08:00:00', 210.00, 
  98, 'Signature required on pickup.', NULL
),

-- Row 5: Accepted order, direct delivery option
(
  105, 5004, NULL, 305, 10, 
  NULL, 'accepted', '2026-07-16 10:15:00', 105, 'delivery', 
  '2026-07-17 09:00:00', 98, '2026-07-16 10:45:00', 15.25, 
  98, 'Call upon arrival.', NULL
);

-- Lab Product users --
INSERT INTO lab_product_users (
  product_user_id, lab_id, name, active
) VALUES 
-- Row 1: Active user (referenced in Order Row 1)
(201, 10, 'Dr. Aris Thorne', 1),

-- Row 2: Active user
(202, 10, 'Jane Doe', 1),

-- Row 3: Inactive user (referenced in Order Row 3)
(203, 11, 'Sarah Jenkins', 0),

-- Row 4: Active user (referenced in Order Row 4)
(204, 12, 'Dr. Robert Chen', 1),

-- Row 5: Active user
(205, 12, 'Emily Rodriguez', 1);

INSERT INTO products (
  product_name,
  nuclide_name,
  name
) VALUES
(5002, 'Fluorine-18', 'Compound1'),
(5009, 'Technetium-99m', 'Compound2'),
(5011, 'Iodine-131', 'Compound3'),
(5005, 'Lutetium-177', 'Compound4'),
(5022, 'Carbon-11', 'Compound5');

INSERT INTO nuclides (
  nuclide_name,
  active
  ) VALUES
  ('Fluorine-18', 1),
  ('Technetium-99m', 1),
  ('Iodine-131', 1),
  ('Lutetium-177', 1),
  ('Carbon-11', 1);
