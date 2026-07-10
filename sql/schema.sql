-- ============================================================
-- PETCOM — schema.sql
-- Identity/role layer (Phase 1) plus the Phase C.1 self-registration
-- request table. 12 tables. InnoDB, utf8mb4. Load into an empty
-- `petcom` database, then load seed.sql.
--
-- Build order is FK-safe, not the narrative order in CLAUDE.md:
--   institutes -> labs -> pis -> lab_pis -> categories -> users
--   -> password_history -> lockout_events -> staff -> admins
--   -> customers -> customer_registration_requests
-- (categories has to exist before staff references it, which is
-- earlier than CLAUDE.md's identity-then-menu grouping. staff has
-- to exist before admins, since every admin is also staff.
-- customer_registration_requests comes last since it FKs into labs,
-- pis, and users but nothing FKs into it.)
-- ============================================================

SET NAMES utf8mb4;

DROP TABLE IF EXISTS customer_registration_requests;
DROP TABLE IF EXISTS customers;
DROP TABLE IF EXISTS admins;
DROP TABLE IF EXISTS staff;
DROP TABLE IF EXISTS lockout_events;
DROP TABLE IF EXISTS password_history;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS lab_pis;
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