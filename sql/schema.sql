-- ============================================================
-- PETCOM — schema.sql
-- Identity/role layer (Phase 1), the Phase C.1 self-registration
-- request table, the Phase F catalog/menu tables, the Phase G.1
-- delivery-location/product-user tables, and the Phase G.2 orders-core
-- tables. 25 tables. InnoDB, utf8mb4. Load into an empty `petcom`
-- database, then load seed.sql.
--
-- Build order is FK-safe, not the narrative order in CLAUDE.md:
--   institutes -> labs -> pis -> lab_pis -> delivery_locations
--   -> product_users -> categories -> users -> password_history
--   -> lockout_events -> staff -> admins -> customers
--   -> customer_registration_requests -> isotopes -> delivery_options
--   -> compounds -> compound_isotopes -> compound_delivery_options
--   -> institute_compounds -> orders -> order_type_a_details
--   -> order_type_b_details -> order_public_comments
--   -> order_internal_notes
-- (categories has to exist before staff references it, which is
-- earlier than CLAUDE.md's identity-then-menu grouping. staff has
-- to exist before admins, since every admin is also staff.
-- customer_registration_requests comes last in the Identity block
-- since it FKs into labs, pis, and users but nothing FKs into it.
-- delivery_locations/product_users are children of labs only, so they
-- build right after lab_pis, alongside the rest of the labs-dependent
-- tables, ahead of the Menu block. The Menu block comes after Identity
-- since compounds FKs into categories and institute_compounds FKs into
-- institutes. The Orders block comes last since orders FKs into
-- customers and order_type_a_details FKs into institute_compounds,
-- delivery_options, delivery_locations, and product_users -- every
-- other block has to exist first.)
-- ============================================================

SET NAMES utf8mb4;

DROP TABLE IF EXISTS order_internal_notes;
DROP TABLE IF EXISTS order_public_comments;
DROP TABLE IF EXISTS order_type_b_details;
DROP TABLE IF EXISTS order_type_a_details;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS institute_compounds;
DROP TABLE IF EXISTS compound_delivery_options;
DROP TABLE IF EXISTS compound_isotopes;
DROP TABLE IF EXISTS compounds;
DROP TABLE IF EXISTS delivery_options;
DROP TABLE IF EXISTS isotopes;
DROP TABLE IF EXISTS customer_registration_requests;
DROP TABLE IF EXISTS customers;
DROP TABLE IF EXISTS admins;
DROP TABLE IF EXISTS staff;
DROP TABLE IF EXISTS lockout_events;
DROP TABLE IF EXISTS password_history;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS lab_pis;
DROP TABLE IF EXISTS delivery_locations;
DROP TABLE IF EXISTS product_users;
DROP TABLE IF EXISTS pis;
DROP TABLE IF EXISTS labs;
DROP TABLE IF EXISTS institutes;


-- ============================================================
-- Identity
-- ============================================================

-- The five tables below (institutes, labs, pis, lab_pis,
-- categories) are provisional reference/lookup tables. Their
-- internal shape may be revised once the final order form design
-- is settled by the other team members working on that piece. The
-- identity layer (users, admins, staff, customers) is final and
-- shouldn't need to change as a result, as long as the FK
-- contract (lab_id -> labs.lab_id, category_id ->
-- categories.category_id, etc.) stays intact.

CREATE TABLE institutes (
  institute_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name           VARCHAR(255) NOT NULL,
  shorthand_name VARCHAR(10),
  active         TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_institutes_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE labs (
  lab_id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  institute_id  INT UNSIGNED NOT NULL,
  lab_name      VARCHAR(100) NOT NULL,
  building      VARCHAR(50),
  room          VARCHAR(20),
  active        TINYINT(1) NOT NULL DEFAULT 1,
  CONSTRAINT fk_labs_institute FOREIGN KEY (institute_id) REFERENCES institutes (institute_id),
  KEY idx_labs_institute_id (institute_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE pis (
  pi_id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  pi_name  VARCHAR(100) NOT NULL,
  email    VARCHAR(254),
  phone    VARCHAR(20),
  active   TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Join table: a lab can have multiple PIs, a PI can oversee multiple labs.
CREATE TABLE lab_pis (
  lab_id  INT UNSIGNED NOT NULL,
  pi_id   INT UNSIGNED NOT NULL,
  PRIMARY KEY (lab_id, pi_id),
  CONSTRAINT fk_lab_pis_lab FOREIGN KEY (lab_id) REFERENCES labs (lab_id) ON DELETE CASCADE,
  CONSTRAINT fk_lab_pis_pi  FOREIGN KEY (pi_id)  REFERENCES pis (pi_id)   ON DELETE CASCADE,
  KEY idx_lab_pis_pi_id (pi_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Categories built here (not with the rest of the menu tables below)
-- because staff.category_id needs it to already exist.
CREATE TABLE categories (
  category_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_name VARCHAR(50) NOT NULL,
  UNIQUE KEY uq_categories_name (category_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Shared login table for all three roles. Role is determined by which
-- of admins/staff/customers a user_id appears in — no role column here.
CREATE TABLE users (
  user_id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username              VARCHAR(50) NOT NULL,
  password_hash         VARCHAR(255) NOT NULL,
  must_change_password  TINYINT(1) NOT NULL DEFAULT 1,
  failed_login_count    TINYINT UNSIGNED NOT NULL DEFAULT 0,
  locked_until          DATETIME NULL,
  active                TINYINT(1) NOT NULL DEFAULT 1,
  created_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_users_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Stores the outgoing password hash each time a user changes their
-- password, so change_password.php can block reuse of the last 5
-- passwords (current users.password_hash + the 4 rows kept here).
-- Pruned to the 4 most recent rows per user on every insert — no
-- reason to retain old hashes past what the policy needs.
CREATE TABLE password_history (
  history_id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id       INT UNSIGNED NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  changed_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_password_history_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE ON UPDATE CASCADE,
  KEY idx_password_history_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Records a lockout event each time a login attempt pushes
-- failed_login_count past FAILED_LOGIN_LOCKOUT_THRESHOLD and
-- locked_until gets set. Narrower than the Phase F audit log system —
-- just who/when/how many attempts. No admin UI to view these yet.
CREATE TABLE lockout_events (
  lockout_id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id          INT UNSIGNED NOT NULL,
  failed_attempts  TINYINT UNSIGNED NOT NULL,
  locked_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_lockout_events_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE ON UPDATE CASCADE,
  KEY idx_lockout_events_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One category per staff member, not a junction table.
CREATE TABLE staff (
  user_id     INT UNSIGNED PRIMARY KEY,
  first_name  VARCHAR(100) NOT NULL,
  last_name   VARCHAR(100) NOT NULL,
  category_id INT UNSIGNED NOT NULL,
  CONSTRAINT fk_staff_user     FOREIGN KEY (user_id)     REFERENCES users (user_id)         ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_staff_category FOREIGN KEY (category_id) REFERENCES categories (category_id),
  KEY idx_staff_category_id (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Every admin is also staff (admin subset-of staff) — enforced here via FK
-- to staff, not straight to users, so an admin row can't exist without a
-- matching staff row. category_id on that staff row stays NOT NULL (no
-- sentinel); the app layer bypasses category restrictions for admins.
CREATE TABLE admins (
  user_id INT UNSIGNED PRIMARY KEY,
  CONSTRAINT fk_admins_user FOREIGN KEY (user_id) REFERENCES staff (user_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Institute is NOT stored here — always derived via
-- lab_id -> labs.institute_id, since a lab belongs to exactly one
-- institute and storing it twice risks the two facts disagreeing.
-- Lab/supervising PI are locked at approval time.
-- registration_status lives directly here — no separate requests table.
CREATE TABLE customers (
  user_id              INT UNSIGNED PRIMARY KEY,
  first_name           VARCHAR(100) NOT NULL,
  last_name            VARCHAR(100) NOT NULL,
  phone                VARCHAR(20) NULL,
  lab_id               INT UNSIGNED NULL,
  supervising_pi_id    INT UNSIGNED NULL,
  registration_status  ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
  nrc_contact_name     VARCHAR(255) NULL,
  nrc_contact_phone    VARCHAR(20) NULL,
  nrc_contact_email    VARCHAR(255) NULL,
  CONSTRAINT fk_customers_user         FOREIGN KEY (user_id)           REFERENCES users (user_id)         ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_customers_lab          FOREIGN KEY (lab_id)            REFERENCES labs (lab_id)             ON DELETE SET NULL,
  CONSTRAINT fk_customers_pi           FOREIGN KEY (supervising_pi_id) REFERENCES pis (pi_id)               ON DELETE SET NULL,
  KEY idx_customers_registration_status (registration_status),
  KEY idx_customers_lab_id (lab_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Holds a self-registration submission (Phase C.1) until an admin
-- approves or rejects it (Phase C.2). No users/customers row exists for
-- a request until it's approved — this table is fully separate from the
-- identity tables, not a staging area with FKs into them. lab_id/pi_id
-- reference the dropdowns the registrant picked from; email becomes the
-- eventual username. No DB-level uniqueness on email: MySQL/MariaDB
-- can't express "unique while status='pending'" as a plain index, and a
-- rejected request is allowed to be resubmitted — so duplicate
-- prevention is enforced at the app layer (see register.php).
CREATE TABLE customer_registration_requests (
  request_id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  lab_id                 INT UNSIGNED NOT NULL,
  pi_id                  INT UNSIGNED NOT NULL,
  first_name             VARCHAR(100) NOT NULL,
  last_name              VARCHAR(100) NOT NULL,
  email                   VARCHAR(254) NOT NULL,
  phone                   VARCHAR(20) NOT NULL,
  nrc_contact_name        VARCHAR(255) NULL,
  nrc_contact_phone       VARCHAR(20) NULL,
  nrc_contact_email       VARCHAR(255) NULL,
  status                  ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
  rejection_reason        VARCHAR(500) NULL,
  reviewed_by_admin_id    INT UNSIGNED NULL,
  reviewed_at             DATETIME NULL,
  submitted_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_reg_requests_lab      FOREIGN KEY (lab_id)               REFERENCES labs (lab_id),
  CONSTRAINT fk_reg_requests_pi       FOREIGN KEY (pi_id)                REFERENCES pis (pi_id),
  CONSTRAINT fk_reg_requests_reviewer FOREIGN KEY (reviewed_by_admin_id) REFERENCES users (user_id) ON DELETE SET NULL,
  KEY idx_reg_requests_status (status),
  KEY idx_reg_requests_email (email),
  KEY idx_reg_requests_lab_id (lab_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ============================================================
-- Menu (Phase F)
-- ============================================================

-- Isotope-first ordering: the customer picks an isotope here, then sees
-- only compounds compatible with it (never the reverse) -- see
-- compound_isotopes below.
CREATE TABLE isotopes (
  isotope_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  isotope_name VARCHAR(20) NOT NULL,
  active       TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_isotopes_name (isotope_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Delivery methods are attached per-compound via compound_delivery_options
-- below, never offered as a single global list.
CREATE TABLE delivery_options (
  delivery_option_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  option_name         VARCHAR(50) NOT NULL,
  active               TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_delivery_options_name (option_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- The master compound list -- admin-controlled (add/edit/remove); each
-- institute's visible ordering list is a curated subset of this list, never
-- an independently edited copy (see institute_compounds below).
-- category_id determines which staff category processes orders for this
-- compound (staff.category_id). standard_cost is nullable since pricing
-- isn't finalized yet; once set, it's what orders.cost_snapshot (Phase G)
-- copies at order-creation time -- a later change here never touches
-- historical orders.
CREATE TABLE compounds (
  compound_id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  compound_name  VARCHAR(150) NOT NULL,
  category_id    INT UNSIGNED NOT NULL,
  standard_cost  DECIMAL(10,2) NULL,
  active         TINYINT(1) NOT NULL DEFAULT 1,
  CONSTRAINT fk_compounds_category FOREIGN KEY (category_id) REFERENCES categories (category_id),
  UNIQUE KEY uq_compounds_name (compound_name),
  KEY idx_compounds_category_id (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Join: usually 1:1 (a compound is made from one isotope), occasionally a
-- compound allows multiple isotopes -- modeled as a proper join table
-- rather than a single FK column on compounds for that reason.
CREATE TABLE compound_isotopes (
  compound_id INT UNSIGNED NOT NULL,
  isotope_id  INT UNSIGNED NOT NULL,
  PRIMARY KEY (compound_id, isotope_id),
  CONSTRAINT fk_compound_isotopes_compound FOREIGN KEY (compound_id) REFERENCES compounds (compound_id) ON DELETE CASCADE,
  CONSTRAINT fk_compound_isotopes_isotope  FOREIGN KEY (isotope_id)  REFERENCES isotopes (isotope_id)   ON DELETE CASCADE,
  KEY idx_compound_isotopes_isotope_id (isotope_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Join: each compound lists its own allowed delivery methods.
CREATE TABLE compound_delivery_options (
  compound_id         INT UNSIGNED NOT NULL,
  delivery_option_id  INT UNSIGNED NOT NULL,
  PRIMARY KEY (compound_id, delivery_option_id),
  CONSTRAINT fk_compound_delivery_options_compound FOREIGN KEY (compound_id)        REFERENCES compounds (compound_id)               ON DELETE CASCADE,
  CONSTRAINT fk_compound_delivery_options_option    FOREIGN KEY (delivery_option_id) REFERENCES delivery_options (delivery_option_id) ON DELETE CASCADE,
  KEY idx_compound_delivery_options_option_id (delivery_option_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Join: "this institute has this compound in its visible ordering list."
-- Institutes can add compounds from the master list to their own list and
-- remove compounds from their own list, but never edit/add/remove the
-- master compounds list itself -- that stays admin-only (see compounds
-- above). A customer's visible compound list at order time is derived via
-- their lab's institute_id (labs.institute_id) joined through this table,
-- never stored redundantly on customers.
-- Surrogate PK (Phase G.2) rather than a plain (institute_id, compound_id)
-- composite -- order_type_a_details.institute_compound_id needs a single
-- column to FK against. The composite is preserved as a UNIQUE key so the
-- original no-duplicate-pairing guarantee still holds.
CREATE TABLE institute_compounds (
  institute_compound_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  institute_id           INT UNSIGNED NOT NULL,
  compound_id            INT UNSIGNED NOT NULL,
  CONSTRAINT fk_institute_compounds_institute FOREIGN KEY (institute_id) REFERENCES institutes (institute_id) ON DELETE CASCADE,
  CONSTRAINT fk_institute_compounds_compound  FOREIGN KEY (compound_id)  REFERENCES compounds (compound_id)   ON DELETE CASCADE,
  UNIQUE KEY uq_institute_compounds_institute_compound (institute_id, compound_id),
  KEY idx_institute_compounds_compound_id (compound_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ============================================================
-- Delivery & Product Users (Phase G.1)
-- ============================================================

-- Customer-managed, lab-scoped: any customer in the lab can reuse a
-- location another customer in that lab already added (ownership is the
-- lab, not the individual customer -- matches customers.lab_id). Distinct
-- from labs.building/labs.room (the lab's own home address) -- a lab can
-- have several named delivery destinations beyond its own room. CRUD UI
-- ships with the Phase G.3 order form, not built here.
CREATE TABLE delivery_locations (
  location_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  lab_id        INT UNSIGNED NOT NULL,
  location_name VARCHAR(100) NOT NULL,
  building      VARCHAR(50) NULL,
  room          VARCHAR(20) NULL,
  active        TINYINT(1) NOT NULL DEFAULT 1,
  CONSTRAINT fk_delivery_locations_lab FOREIGN KEY (lab_id) REFERENCES labs (lab_id) ON DELETE CASCADE,
  KEY idx_delivery_locations_lab_id (lab_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- The "John Doe, chemist in her lab" entity from the requirements
-- interview -- who the ordered activity is actually for. Not a users row:
-- no login, no username/password, no role. Customer-managed and
-- lab-scoped, same reuse pattern as delivery_locations above.
CREATE TABLE product_users (
  product_user_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  lab_id          INT UNSIGNED NOT NULL,
  first_name      VARCHAR(100) NOT NULL,
  last_name       VARCHAR(100) NOT NULL,
  active          TINYINT(1) NOT NULL DEFAULT 1,
  CONSTRAINT fk_product_users_lab FOREIGN KEY (lab_id) REFERENCES labs (lab_id) ON DELETE CASCADE,
  KEY idx_product_users_lab_id (lab_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ============================================================
-- Orders (Phase G.2)
-- ============================================================

-- order_id is AUTO_INCREMENT and never reused -- orders are never
-- deleted, only status-transitioned (enforced in app logic, not the DB;
-- there is deliberately no DELETE-worthy business case for this table).
-- Completed orders are terminal -- no edits/cancels once status =
-- 'completed'. Returns go back to 'pending', no separate 'returned'
-- status (order_audit_log, Phase I, will record that a return happened).
-- cost_snapshot is captured here at creation time and never
-- recalculated -- a later change to compounds.standard_cost never
-- touches historical orders.
CREATE TABLE orders (
  order_id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_type    ENUM('A', 'B') NOT NULL,
  customer_id   INT UNSIGNED NOT NULL,
  status        ENUM('pending', 'processing', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
  cost_snapshot DECIMAL(10,2) NOT NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_orders_customer FOREIGN KEY (customer_id) REFERENCES customers (user_id),
  KEY idx_orders_customer_id (customer_id),
  KEY idx_orders_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Extends orders via order_id (same shared-PK pattern as
-- customers/staff/admins off users.user_id). institute_compound_id FKs to
-- institute_compounds (not compounds directly), so it's structurally
-- impossible to order a compound outside the customer's institute list.
CREATE TABLE order_type_a_details (
  order_id              INT UNSIGNED PRIMARY KEY,
  institute_compound_id INT UNSIGNED NOT NULL,
  delivery_option_id    INT UNSIGNED NOT NULL,
  activity_mci          DECIMAL(10,2) NOT NULL,
  requested_datetime    DATETIME NOT NULL,
  delivery_location_id  INT UNSIGNED NOT NULL,
  product_user_id       INT UNSIGNED NOT NULL,
  special_instructions  TEXT NULL,
  CONSTRAINT fk_order_type_a_details_order              FOREIGN KEY (order_id)              REFERENCES orders (order_id)                         ON DELETE CASCADE,
  CONSTRAINT fk_order_type_a_details_institute_compound  FOREIGN KEY (institute_compound_id) REFERENCES institute_compounds (institute_compound_id),
  CONSTRAINT fk_order_type_a_details_delivery_option     FOREIGN KEY (delivery_option_id)    REFERENCES delivery_options (delivery_option_id),
  CONSTRAINT fk_order_type_a_details_delivery_location   FOREIGN KEY (delivery_location_id)  REFERENCES delivery_locations (location_id),
  CONSTRAINT fk_order_type_a_details_product_user        FOREIGN KEY (product_user_id)       REFERENCES product_users (product_user_id),
  KEY idx_order_type_a_details_institute_compound_id (institute_compound_id),
  KEY idx_order_type_a_details_delivery_option_id (delivery_option_id),
  KEY idx_order_type_a_details_delivery_location_id (delivery_location_id),
  KEY idx_order_type_a_details_product_user_id (product_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Intentionally minimal -- real cyclotron run parameters (beam current,
-- bombardment time vs. EOB activity/datetime, etc.) are not yet scoped.
-- Do not add fields here speculatively; wait for Phase J requirements.
CREATE TABLE order_type_b_details (
  order_id              INT UNSIGNED PRIMARY KEY,
  special_instructions  TEXT NULL,
  CONSTRAINT fk_order_type_b_details_order FOREIGN KEY (order_id) REFERENCES orders (order_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Append-only: no UPDATE or DELETE endpoint, ever. Visible to customer +
-- staff/admin.
CREATE TABLE order_public_comments (
  comment_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id     INT UNSIGNED NOT NULL,
  author_id    INT UNSIGNED NOT NULL,
  author_role  ENUM('staff', 'admin', 'customer') NOT NULL,
  comment_text TEXT NOT NULL,
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_order_public_comments_order  FOREIGN KEY (order_id)  REFERENCES orders (order_id) ON DELETE CASCADE,
  CONSTRAINT fk_order_public_comments_author FOREIGN KEY (author_id) REFERENCES users (user_id),
  KEY idx_order_public_comments_order_id (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Append-only, same as order_public_comments -- separate table (not a
-- visibility flag on one shared table) so a query can never accidentally
-- leak an internal note to a customer. author_role includes 'customer'
-- only for structural parity with order_public_comments; the app layer
-- never lets a customer post here.
CREATE TABLE order_internal_notes (
  note_id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id     INT UNSIGNED NOT NULL,
  author_id    INT UNSIGNED NOT NULL,
  author_role  ENUM('staff', 'admin', 'customer') NOT NULL,
  note_text    TEXT NOT NULL,
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_order_internal_notes_order  FOREIGN KEY (order_id)  REFERENCES orders (order_id) ON DELETE CASCADE,
  CONSTRAINT fk_order_internal_notes_author FOREIGN KEY (author_id) REFERENCES users (user_id),
  KEY idx_order_internal_notes_order_id (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;