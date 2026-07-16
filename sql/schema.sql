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
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS compounds;
DROP TABLE IF EXISTS isotopes;


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
  is_active         TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_institutes_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE labs (
  lab_id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  institute_id  INT UNSIGNED NOT NULL,
  lab_name      VARCHAR(100) NOT NULL,
  is_active     TINYINT(1) NOT NULL DEFAULT 1,
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

-- Shared login table for all three roles. Role is determined by which
-- of admins/staff/customers a user_id appears in — no role column here.
CREATE TABLE users (
  user_id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username              VARCHAR(50) NOT NULL,
  password_hash         VARCHAR(255) NOT NULL,
  first_name            VARCHAR(100) NOT NULL,
  last_name             VARCHAR(100) NOT NULL
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
  user_id           INT UNSIGNED PRIMARY KEY,
  CONSTRAINT fk_staff_user     FOREIGN KEY (user_id)     REFERENCES users (user_id)         ON DELETE CASCADE ON UPDATE CASCADE,
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
  lab_id               INT UNSIGNED NULL,
  supervising_pi_id    INT UNSIGNED NULL,
  registration_status  ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
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
  request_id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  lab_id                  INT UNSIGNED NOT NULL,
  pi_id                   INT UNSIGNED NOT NULL,
  first_name              VARCHAR(100) NOT NULL,
  last_name               VARCHAR(100) NOT NULL,
  email                   VARCHAR(254) NOT NULL,
  phone                   VARCHAR(20) NOT NULL,
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



-- Lab-scoped, not per-customer -- multiple customers in the same lab
-- share the same delivery locations. Soft-delete via active so a
-- historical order's location_id reference survives even after the
-- location is later deactivated.
CREATE TABLE lab_delivery_locations (
  location_id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  lab_id         INT UNSIGNED NOT NULL,
  location_name  VARCHAR(100) NOT NULL,
  room           VARCHAR(20),
  active         TINYINT(1) NOT NULL DEFAULT 1,
  CONSTRAINT fk_lab_delivery_locations_lab FOREIGN KEY (lab_id) REFERENCES labs (lab_id),
  KEY idx_lab_delivery_locations_lab_id (lab_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Represents the actual person who will receive/use the dose, who may
-- not be the ordering customer (e.g. Jane Doe orders on behalf of John
-- Doe, a lab member who isn't a registered system user and has no row in
-- customers). Lab-scoped for the same reason as lab_delivery_locations
-- above. Soft-delete via active so a historical order's product_user_id
-- reference survives deactivation.
CREATE TABLE lab_product_users (
  product_user_id  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  lab_id           INT UNSIGNED NOT NULL,
  name             VARCHAR(150) NOT NULL,
  active           TINYINT(1) NOT NULL DEFAULT 1,
  CONSTRAINT fk_lab_product_users_lab FOREIGN KEY (lab_id) REFERENCES labs (lab_id),
  KEY idx_lab_product_users_lab_id (lab_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Holds a list of available nuclides. In the context of this database, a nuclide is an attribute of a product
CREATE TABLE nuclide (
  nuclide_name  VARCHAR(30) PRIMARY KEY,
  is_active     TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Holds a list of available products.
CREATE TABLE products (
  product_id                INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  nuclide_name              VARCHAR(30) NOT NULL,
  product_name              VARCHAR(255) NOT NULL,
  default_delivery_option   ENUM('direct delivery', 'pickup', 'pharmacy'),
  is_active                 TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One unified form for every order type -- no Type A/B split, no
-- separate per-type detail tables. Cyclotron-run specifics (beam
-- current, bombardment time, EOB activity, destination) are typed into
-- notes like any other order's notes, never given dedicated columns.
-- notes is the single shared, overwritable communication channel on an
-- order -- last-write-wins, no history or threading; staff/admin can
-- always edit it, a customer only on their own order. status has no
-- 'returned' value: a return is a transition back to 'pending', recorded
-- as a normal row in order_audit_log, not a status of its own. order_id
-- is a plain AUTO_INCREMENT: MySQL/MariaDB never reuses an
-- AUTO_INCREMENT value even after the owning row's status becomes
-- 'cancelled' (and this app never DELETEs orders), satisfying
-- "sequential, never reused" with no extra bookkeeping. product_user_id
-- is nullable -- NULL means the ordering customer is the recipient; a
-- row is only required when someone else is. delivery method is no
-- longer chosen per-order -- it's derived from the selected product's
-- delivery_method. location_id is nullable at the DB level -- most
-- delivery methods legitimately have no location -- but the app layer
-- treats it as required whenever the order's product has
-- delivery_method = 'direct_delivery'. That conditional requirement is
-- enforced in new_order.php's validation, not as a DB constraint -- a
-- plain NOT NULL can't be made conditional on another column's value
-- without a trigger, which is more complexity than this needs. There is
-- no cost column of any kind here -- this project does not track cost.
CREATE TABLE orders (
  order_id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_user_id       INT UNSIGNED NULL,
  product_id            INT NOT NULL,
  location_id           INT UNSIGNED NULL,
  activity_mci          DECIMAL(10,1) NULL,
  status                ENUM('pending', 'accepted', 'ready for pickup', 'completed', 'canceled', 'returned') DEFAULT 'pending' NOT NULL,
  created_at            TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  created_by            INT UNSIGNED NOT NULL,
  delivery_option       ENUM('delivery', 'pickup', 'pharmacy'),
  delivery_time         TIMESTAMP NOT NULL,
  processed_by          INT UNSIGNED NULL,
  processed_at          TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  last_modified_at      TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  last_modified_by      INT UNSIGNED NULL,
  additional_notes      VARCHAR(500) NULL,
  cancelation_notes     VARCHAR(500) NULL,
  CONSTRAINT fk_orders_compound FOREIGN KEY ('compound_id') REFERENCES compounds('compound_id'),
  CONSTRAINT fk_orders_created_by FOREIGN KEY ('created_by') REFERENCES users('user_id'),
  CONSTRAINT fk_orders_processed_by FOREIGN KEY ('processed_by') REFERENCES staff('user_id'),
  CONSTRAINT fk_orers_last_modified_by FOREIGN KEY ('last_modified_by') REFERENCES users('user_id'),
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE order_audit_log (
  audit_id                                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id                                  INT UNSIGNED NOT NULL,
  status_from                               ENUM('pending', 'accepted', 'ready for pickup', 'completed', 'cancelled', 'returned') NOT NULL,
  status_to                                 ENUM('pending', 'accepted', 'ready for pickup', 'completed', 'cancelled', 'returned') NOT NULL,
  changed_by_user_id                        INT UNSIGNED NOT NULL,
  changed_at                                TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_order_audit_log_order FOREIGN KEY (order_id) REFERENCES orders (order_id) ON DELETE CASCADE,
  CONSTRAINT fk_order_audit_log_changed_by FOREIGN KEY (changed_by_user_id) REFERENCES users (user_id),
  KEY idx_order_audit_log_order_id (order_id),
  KEY idx_order_audit_log_changed_by_user_id (changed_by_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8m