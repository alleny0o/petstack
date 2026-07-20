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

-- ---- Users (7): 1 admin, 2 staff, 4 customers ----
-- Usernames are real NIH-email-style, matching Kris's requirement
-- that username = NIH email address.
-- first_name/last_name/phone live on users now (moved off staff/customers)
-- -- phone stays NULL/omitted for all 7 seeded people, matching how no
-- seeded customer set a phone value before this move either.
INSERT INTO users (username, password_hash, first_name, last_name, must_change_password, active) VALUES
  ('robert.nguyen@nih.gov',  'PLACEHOLDER_HASH_SET_BY_TOOLS_SET_TEMP_PASSWORDS', 'Robert', 'Nguyen',   1, 1), -- 1: admin
  ('maria.santos@nih.gov',   'PLACEHOLDER_HASH_SET_BY_TOOLS_SET_TEMP_PASSWORDS', 'Maria',  'Santos',   1, 1), -- 2: staff
  ('james.oconnor@nih.gov',  'PLACEHOLDER_HASH_SET_BY_TOOLS_SET_TEMP_PASSWORDS', 'James',  'O''Connor', 1, 1), -- 3: staff
  ('alice.carter@nih.gov',   'PLACEHOLDER_HASH_SET_BY_TOOLS_SET_TEMP_PASSWORDS', 'Alice',  'Carter',   1, 1), -- 4: customer
  ('brian.kim@nih.gov',      'PLACEHOLDER_HASH_SET_BY_TOOLS_SET_TEMP_PASSWORDS', 'Brian',  'Kim',      1, 1), -- 5: customer
  ('deepa.patel@nih.gov',    'PLACEHOLDER_HASH_SET_BY_TOOLS_SET_TEMP_PASSWORDS', 'Deepa',  'Patel',    1, 1), -- 6: customer
  ('evan.feng@nih.gov',      'PLACEHOLDER_HASH_SET_BY_TOOLS_SET_TEMP_PASSWORDS', 'Evan',   'Feng',     1, 1); -- 7: customer (lab-mate of Alice, for lab-wide visibility testing)

-- ---- Staff (3) ----
-- Must be inserted before admins: admins.user_id now FKs to staff.user_id
-- (every admin is also staff), so the admin's staff row has to exist first.
INSERT INTO staff (user_id) VALUES
  (1),
  (2),
  (3);

-- ---- Admin (1) ----
-- References the staff row above.
INSERT INTO admins (user_id) VALUES (1);

-- ---- Customers (4, all approved) ----
INSERT INTO customers (user_id, lab_id, supervising_pi_id, registration_status) VALUES
  (4, 1, 1, 'approved'), -- Alice Carter, NCI / Molecular Imaging Lab / Dr. Carter
  (5, 2, 2, 'approved'), -- Brian Kim, NIMH / Neuroimaging Lab / Dr. Ellison
  (6, 3, 2, 'approved'), -- Deepa Patel, NINDS / Cerebrovascular Imaging Lab / Dr. Ellison
  (7, 1, 1, 'approved'); -- Evan Feng, NCI / Molecular Imaging Lab / Dr. Carter (lab-mate of Alice)

-- ---- Nuclides (5) ----
INSERT INTO nuclides (name, active) VALUES
  ('C-11', 1),
  ('F-18', 1),
  ('Ga-68', 1),
  ('Zr-89', 1),
  ('Y-86', 1);

-- ---- Products (10) ----
-- Flat catalog rows: nuclide + name + a single fixed delivery_method.
-- Real department list from a July 2026 meeting, explicitly flagged
-- there as incomplete. Products 3 and 4 are the same name+nuclide
-- ([F18]FDG) seeded twice under two different delivery_method values --
-- this exercises the dual-row convention (two product rows for a
-- product offered more than one way) and is here for manual testing.
INSERT INTO products (nuclide_id, name, delivery_method, active) VALUES
  (1, '[C11]CO2',          'direct_delivery', 1), -- 1
  (1, '[C11]Methane',      'direct_delivery', 1), -- 2
  (2, '[F18]FDG',          'radiopharmacy',   1), -- 3
  (2, '[F18]FDG',          'pick_up',         1), -- 4: same name+nuclide as #3, different delivery_method
  (2, '[F18]F-Dopa',       'radiopharmacy',   1), -- 5
  (2, '[F18]F-Dopamine',   'radiopharmacy',   1), -- 6
  (3, '[Ga68]Ga Dotatate', 'radiopharmacy',   1), -- 7
  (4, '[Zr89]Zr Oxalate',  'pick_up',         1), -- 8
  (4, '[Zr89]Zr Chloride', 'pick_up',         1), -- 9
  (5, '[Y86]Y Solution',   'pick_up',         1); -- 10

-- ---- lab_delivery_locations (4) ----
-- Lab 1 gets two locations to show a lab can have more than one.
INSERT INTO lab_delivery_locations (lab_id, name, room, active) VALUES
  (1, 'Molecular Imaging Lab - Injection Suite', 'B1D43-A', 1), -- 1
  (1, 'Molecular Imaging Lab - Loading Dock', 'B1D40', 1),      -- 2
  (2, 'Neuroimaging Lab - Delivery Bay', '2C401', 1),           -- 3
  (3, 'Cerebrovascular Imaging Lab - Front Desk', 'C107', 1);   -- 4

-- ---- lab_product_users (2) ----
-- Unregistered lab members who can be the actual dose recipient on an
-- order placed by someone else in their lab. Only Tom Reyes is
-- referenced by a seeded order; Priya Nair is here for future testing.
INSERT INTO lab_product_users (lab_id, first_name, last_name, email, active) VALUES
  (1, 'Tom', 'Reyes', 'tom.reyes@nih.gov', 1),  -- 1: Molecular Imaging Lab
  (2, 'Priya', 'Nair', 'priya.nair@nih.gov', 1); -- 2: Neuroimaging Lab

-- ---- Orders (10, spanning pending/accepted/completed/cancelled + a return) ----
-- location_id is populated only for orders on direct_delivery products
-- (orders 4 and 8, both [C11] cyclotron products) -- per the current
-- business rule, only direct_delivery requires a delivery location, so
-- location_id is NULL on every other order here.
-- chargeable is listed explicitly (not left to the DB default, now 1) so
-- the seed keeps a mix of both states -- orders 2 and 6 are the "not
-- chargeable" exceptions the UI highlights.
-- requested_datetime on the still-open orders (pending/accepted: 1, 2, 3,
-- 4, 8, 10) is computed relative to CURDATE() rather than hardcoded, so
-- the staff dashboard's Due Today & Upcoming overdue/due-today/upcoming
-- tiering always has one of each to show, regardless of what day the
-- seed is (re)loaded -- a fixed literal date would silently drift into
-- "all overdue" once real time passed it. completed/cancelled orders
-- (5, 6, 7, 9) keep literal historical dates since their due date no
-- longer drives any dashboard state.
INSERT INTO orders (customer_id, product_id, location_id, product_user_id, activity_mci, requested_datetime, notes, status, cancellation_reason, chargeable, created_at, updated_at) VALUES
  (4, 3, NULL, NULL, 10.000, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 5 DAY), '09:30:00'), NULL, 'pending', NULL, 1, '2026-07-10 14:22:00', '2026-07-10 14:22:00'), -- 1: Alice / FDG (radiopharmacy) / pending / overdue
  (7, 7, NULL, NULL, 5.500, TIMESTAMP(CURDATE(), '23:30:00'), NULL, 'pending', NULL, 0, '2026-07-11 09:15:00', '2026-07-11 09:15:00'), -- 2: Evan / Ga Dotatate / pending / not chargeable / due today
  (5, 5, NULL, NULL, 8.750, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 8 DAY), '13:00:00'), NULL, 'accepted', NULL, 1, '2026-07-08 10:00:00', '2026-07-09 11:30:00'), -- 3: Brian / F-Dopa / accepted / overdue
  (6, 1, 4, NULL, 15.000, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 10 DAY), '07:45:00'), 'Beam current 40 uA, bombardment time 20 min, EOB activity approx 18 Ci. Please deliver directly to the cyclotron target line at Bldg 10 C107 loading dock.', 'accepted', NULL, 1, '2026-07-07 08:00:00', '2026-07-07 16:00:00'), -- 4: Deepa / CO2 (direct_delivery) / accepted / cyclotron notes / overdue
  (4, 8, NULL, 1, 3.200, '2026-07-05 10:00:00', NULL, 'completed', NULL, 1, '2026-06-28 09:00:00', '2026-07-05 15:00:00'), -- 5: Alice / Zr Oxalate (pick_up) / completed / product_user Tom Reyes
  (7, 3, NULL, NULL, 12.000, '2026-07-02 09:00:00', NULL, 'completed', NULL, 0, '2026-06-25 08:30:00', '2026-07-02 14:00:00'), -- 6: Evan / FDG (radiopharmacy) / completed / not chargeable
  (5, 6, NULL, NULL, 9.400, '2026-06-20 11:00:00', NULL, 'cancelled', 'Lab no longer needs this dose -- study protocol changed.', 1, '2026-06-15 09:00:00', '2026-06-16 10:00:00'), -- 7: Brian / F-Dopamine / cancelled
  (6, 2, 4, NULL, 6.800, TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 1 DAY), '08:00:00'), 'Bombardment approx 25 min at 35 uA, EOB activity approx 12 Ci. Please have ready for direct pickup at the cyclotron target station.', 'pending', NULL, 1, '2026-07-13 10:00:00', '2026-07-13 10:00:00'), -- 8: Deepa / Methane (direct_delivery) / pending / cyclotron notes / upcoming
  (4, 5, NULL, NULL, 7.500, '2026-06-10 09:00:00', NULL, 'completed', NULL, 1, '2026-06-05 08:00:00', '2026-06-10 13:00:00'), -- 9: Alice / F-Dopa / completed
  (4, 7, NULL, NULL, 4.900, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 30 DAY), '09:00:00'), NULL, 'pending', NULL, 1, '2026-05-25 08:00:00', '2026-06-02 10:00:00'); -- 10: Alice / Ga Dotatate / accepted then returned to pending / overdue, oldest waiting-for-acceptance

-- ---- order_audit_log (21 rows) ----
-- Every order has at least its creation row (status_from NULL). Orders
-- 3/4/7 get a second row for their one transition; 5/6/9 get a third for
-- completion; order 10 gets a third row demonstrating the return rule:
-- accepted -> pending (the only legal source status for a return), with
-- no separate 'returned' status value used. completed is terminal, so
-- unlike the pre-lifecycle-design placeholder this replaced, a return
-- can never happen after completion.
INSERT INTO order_audit_log (order_id, status_from, status_to, changed_by_user_id, changed_at) VALUES
  (1, NULL, 'pending', 4, '2026-07-10 14:22:00'),
  (2, NULL, 'pending', 7, '2026-07-11 09:15:00'),
  (3, NULL, 'pending', 5, '2026-07-08 10:00:00'),
  (3, 'pending', 'accepted', 2, '2026-07-09 11:30:00'),
  (4, NULL, 'pending', 6, '2026-07-07 08:00:00'),
  (4, 'pending', 'accepted', 3, '2026-07-07 16:00:00'),
  (5, NULL, 'pending', 4, '2026-06-28 09:00:00'),
  (5, 'pending', 'accepted', 2, '2026-06-29 10:00:00'),
  (5, 'accepted', 'completed', 2, '2026-07-05 15:00:00'),
  (6, NULL, 'pending', 7, '2026-06-25 08:30:00'),
  (6, 'pending', 'accepted', 2, '2026-06-26 09:00:00'),
  (6, 'accepted', 'completed', 2, '2026-07-02 14:00:00'),
  (7, NULL, 'pending', 5, '2026-06-15 09:00:00'),
  (7, 'pending', 'cancelled', 5, '2026-06-16 10:00:00'),
  (8, NULL, 'pending', 6, '2026-07-13 10:00:00'),
  (9, NULL, 'pending', 4, '2026-06-05 08:00:00'),
  (9, 'pending', 'accepted', 2, '2026-06-06 09:00:00'),
  (9, 'accepted', 'completed', 2, '2026-06-10 13:00:00'),
  (10, NULL, 'pending', 4, '2026-05-25 08:00:00'),
  (10, 'pending', 'accepted', 2, '2026-05-26 09:00:00'),
  (10, 'accepted', 'pending', 2, '2026-06-02 10:00:00');
