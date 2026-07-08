-- ============================================================
-- PETCOM — schema.sql
-- All 20 tables. InnoDB, utf8mb4. Load into an empty `petcom`
-- database, then load seed.sql.
--
-- Build order is FK-safe, not the narrative order in CLAUDE.md:
--   institutes -> labs -> pis -> lab_pis -> categories -> isotopes
--   -> delivery_options -> compounds -> compound_isotopes
--   -> compound_delivery_options -> users -> admins -> staff
--   -> customers -> orders -> order_type_a_details
--   -> order_type_b_details -> order_public_comments
--   -> order_internal_notes -> order_audit_log
-- (categories has to exist before staff/compounds reference it,
-- which is earlier than CLAUDE.md's identity-then-menu grouping.)
-- ============================================================

SET NAMES utf8mb4;

DROP TABLE IF EXISTS order_audit_log;
DROP TABLE IF EXISTS order_internal_notes;
DROP TABLE IF EXISTS order_public_comments;
DROP TABLE IF EXISTS order_type_b_details;
DROP TABLE IF EXISTS order_type_a_details;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS customers;
DROP TABLE IF EXISTS staff;
DROP TABLE IF EXISTS admins;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS compound_delivery_options;
DROP TABLE IF EXISTS compound_isotopes;
DROP TABLE IF EXISTS compounds;
DROP TABLE IF EXISTS delivery_options;
DROP TABLE IF EXISTS isotopes;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS lab_pis;
DROP TABLE IF EXISTS pis;
DROP TABLE IF EXISTS labs;
DROP TABLE IF EXISTS institutes;


-- ============================================================
-- Identity
-- ============================================================

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

CREATE TABLE isotopes (
  isotope_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  isotope_name VARCHAR(30) NOT NULL,
  active       TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_isotopes_name (isotope_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE delivery_options (
  delivery_option_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name                VARCHAR(50) NOT NULL,
  UNIQUE KEY uq_delivery_options_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- A compound has exactly one category regardless of which isotope
-- it's ordered with, so category lives here, not on compound_isotopes.
CREATE TABLE compounds (
  compound_id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id         INT UNSIGNED NOT NULL,
  name                VARCHAR(255) NOT NULL,
  order_type          ENUM('A', 'B') NOT NULL,
  standard_cost       DECIMAL(10,2) NOT NULL,
  min_lead_time_hours DECIMAL(6,1) NOT NULL DEFAULT 0,
  active              TINYINT(1) NOT NULL DEFAULT 1,
  CONSTRAINT fk_compounds_category FOREIGN KEY (category_id) REFERENCES categories (category_id),
  KEY idx_compounds_category_id (category_id),
  KEY idx_compounds_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Usually 1:1, occasionally a compound allows multiple isotopes.
CREATE TABLE compound_isotopes (
  compound_id INT UNSIGNED NOT NULL,
  isotope_id  INT UNSIGNED NOT NULL,
  PRIMARY KEY (compound_id, isotope_id),
  CONSTRAINT fk_compound_isotopes_compound FOREIGN KEY (compound_id) REFERENCES compounds (compound_id) ON DELETE CASCADE,
  CONSTRAINT fk_compound_isotopes_isotope  FOREIGN KEY (isotope_id)  REFERENCES isotopes (isotope_id)   ON DELETE CASCADE,
  KEY idx_compound_isotopes_isotope_id (isotope_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Each compound lists its own allowed delivery methods, not a global list.
CREATE TABLE compound_delivery_options (
  compound_id         INT UNSIGNED NOT NULL,
  delivery_option_id  INT UNSIGNED NOT NULL,
  PRIMARY KEY (compound_id, delivery_option_id),
  CONSTRAINT fk_cdo_compound         FOREIGN KEY (compound_id)        REFERENCES compounds (compound_id)               ON DELETE CASCADE,
  CONSTRAINT fk_cdo_delivery_option  FOREIGN KEY (delivery_option_id) REFERENCES delivery_options (delivery_option_id) ON DELETE CASCADE,
  KEY idx_cdo_delivery_option_id (delivery_option_id)
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

CREATE TABLE admins (
  user_id INT UNSIGNED PRIMARY KEY,
  CONSTRAINT fk_admins_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One category per staff member, not a junction table.
CREATE TABLE staff (
  user_id     INT UNSIGNED PRIMARY KEY,
  category_id INT UNSIGNED NOT NULL,
  CONSTRAINT fk_staff_user     FOREIGN KEY (user_id)     REFERENCES users (user_id)         ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_staff_category FOREIGN KEY (category_id) REFERENCES categories (category_id),
  KEY idx_staff_category_id (category_id)
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
  approved_by          INT UNSIGNED NULL,
  approved_at          DATETIME NULL,
  nrc_contact_name     VARCHAR(255) NULL,
  nrc_contact_phone    VARCHAR(20) NULL,
  nrc_contact_email    VARCHAR(255) NULL,
  CONSTRAINT fk_customers_user         FOREIGN KEY (user_id)           REFERENCES users (user_id)         ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_customers_lab          FOREIGN KEY (lab_id)            REFERENCES labs (lab_id)             ON DELETE SET NULL,
  CONSTRAINT fk_customers_pi           FOREIGN KEY (supervising_pi_id) REFERENCES pis (pi_id)               ON DELETE SET NULL,
  CONSTRAINT fk_customers_approved_by  FOREIGN KEY (approved_by)       REFERENCES users (user_id)           ON DELETE SET NULL,
  KEY idx_customers_registration_status (registration_status),
  KEY idx_customers_lab_id (lab_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ============================================================
-- Orders
-- ============================================================

-- cost_snapshot is set at creation and never recalculated from
-- compounds.standard_cost — historical orders/reports stay accurate
-- even after prices change. No "returned" status: returns go back to
-- pending, and order_audit_log is what records that a return happened.
CREATE TABLE orders (
  order_id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id          INT UNSIGNED NOT NULL,
  compound_id          INT UNSIGNED NOT NULL,
  isotope_id           INT UNSIGNED NOT NULL,
  delivery_option_id   INT UNSIGNED NOT NULL,
  status               ENUM('pending', 'accepted', 'completed', 'canceled') NOT NULL DEFAULT 'pending',
  cost_snapshot        DECIMAL(10,2) NOT NULL,
  created_by           INT UNSIGNED NOT NULL,
  created_at           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  processed_by         INT UNSIGNED NULL,
  processed_at         DATETIME NULL,
  last_modified_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_orders_customer         FOREIGN KEY (customer_id)        REFERENCES customers (user_id),
  CONSTRAINT fk_orders_compound         FOREIGN KEY (compound_id)        REFERENCES compounds (compound_id),
  CONSTRAINT fk_orders_isotope          FOREIGN KEY (isotope_id)         REFERENCES isotopes (isotope_id),
  CONSTRAINT fk_orders_delivery_option  FOREIGN KEY (delivery_option_id) REFERENCES delivery_options (delivery_option_id),
  CONSTRAINT fk_orders_created_by       FOREIGN KEY (created_by)         REFERENCES users (user_id),
  CONSTRAINT fk_orders_processed_by     FOREIGN KEY (processed_by)       REFERENCES users (user_id),
  KEY idx_orders_status (status),
  KEY idx_orders_customer_id (customer_id),
  KEY idx_orders_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Dose orders. Independent of order_type_b_details — never model one
-- as parent/child of the other.
CREATE TABLE order_type_a_details (
  order_id            INT UNSIGNED PRIMARY KEY,
  activity_mci        DECIMAL(8,2) NOT NULL,
  requested_datetime  DATETIME NOT NULL,
  CONSTRAINT fk_order_type_a_order FOREIGN KEY (order_id) REFERENCES orders (order_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Cyclotron orders: either beam_current+bombardment_minutes OR
-- eob_activity_mci+eob_datetime, never both — enforced by the CHECK
-- below. Delivery destination is orders.delivery_option_id; no
-- separate destination column here.
CREATE TABLE order_type_b_details (
  order_id             INT UNSIGNED PRIMARY KEY,
  mode                 ENUM('beam', 'eob') NOT NULL,
  beam_current         DECIMAL(6,2) NULL,
  bombardment_minutes  SMALLINT UNSIGNED NULL,
  eob_activity_mci     DECIMAL(8,2) NULL,
  eob_datetime         DATETIME NULL,
  CONSTRAINT fk_order_type_b_order FOREIGN KEY (order_id) REFERENCES orders (order_id) ON DELETE CASCADE,
  CONSTRAINT chk_order_type_b_mode CHECK (
    (mode = 'beam' AND beam_current IS NOT NULL AND bombardment_minutes IS NOT NULL
       AND eob_activity_mci IS NULL AND eob_datetime IS NULL)
    OR
    (mode = 'eob' AND eob_activity_mci IS NOT NULL AND eob_datetime IS NOT NULL
       AND beam_current IS NULL AND bombardment_minutes IS NULL)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Append-only, visible to customer + staff. Posting is restricted to
-- the order's own customer (enforced in app logic, not schema) plus
-- staff/admin — viewing is lab-wide for customers.
CREATE TABLE order_public_comments (
  comment_id  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id    INT UNSIGNED NOT NULL,
  author_id   INT UNSIGNED NOT NULL,
  body        VARCHAR(1000) NOT NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_opc_order  FOREIGN KEY (order_id)  REFERENCES orders (order_id) ON DELETE CASCADE,
  CONSTRAINT fk_opc_author FOREIGN KEY (author_id) REFERENCES users (user_id),
  KEY idx_opc_order_id (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Append-only, staff-only (enforced in app logic, not schema).
CREATE TABLE order_internal_notes (
  note_id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id    INT UNSIGNED NOT NULL,
  author_id   INT UNSIGNED NOT NULL,
  body        VARCHAR(1000) NOT NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_oin_order  FOREIGN KEY (order_id)  REFERENCES orders (order_id) ON DELETE CASCADE,
  CONSTRAINT fk_oin_author FOREIGN KEY (author_id) REFERENCES users (user_id),
  KEY idx_oin_order_id (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Status changes only (pending -> accepted -> completed/canceled,
-- timestamp, who) — not field-level diffing. status_from is NULL for
-- the initial pending row written at order creation.
CREATE TABLE order_audit_log (
  log_id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id     INT UNSIGNED NOT NULL,
  changed_by   INT UNSIGNED NOT NULL,
  status_from  ENUM('pending', 'accepted', 'completed', 'canceled') NULL,
  status_to    ENUM('pending', 'accepted', 'completed', 'canceled') NOT NULL,
  changed_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_oal_order       FOREIGN KEY (order_id)   REFERENCES orders (order_id) ON DELETE CASCADE,
  CONSTRAINT fk_oal_changed_by  FOREIGN KEY (changed_by) REFERENCES users (user_id),
  KEY idx_oal_order_id (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;