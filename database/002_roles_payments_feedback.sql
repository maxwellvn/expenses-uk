-- Roles, payment step and help/feedback inbox. Safe to run once on a database created from schema.sql v1.
USE uk_expenses;

ALTER TABLE claims
  MODIFY status ENUM('pending','changes_requested','approved','rejected','paid') NOT NULL DEFAULT 'pending',
  ADD COLUMN paid_by VARCHAR(64) NULL AFTER decided_at,
  ADD COLUMN paid_at DATETIME NULL AFTER paid_by,
  ADD COLUMN payment_ref VARCHAR(80) NULL AFTER paid_at;

ALTER TABLE claim_events
  MODIFY type ENUM('submitted','resubmitted','comment','approved','rejected','changes_requested','paid') NOT NULL;

-- Extra rights on top of "staff" (everyone active in workCentral UK). Admins named in ADMIN_KC_HANDLES are admins without a row here.
CREATE TABLE IF NOT EXISTS user_roles (
  staff_id    VARCHAR(64) NOT NULL,
  role        ENUM('approver','finance','admin') NOT NULL,
  granted_by  VARCHAR(64) NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (staff_id, role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS feedback (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  staff_id     VARCHAR(64)  NULL,
  name         VARCHAR(160) NOT NULL,
  kc_handle    VARCHAR(80)  NULL,
  topic        VARCHAR(40)  NOT NULL,
  message      TEXT         NOT NULL,
  page         VARCHAR(255) NULL,
  ip_hash      CHAR(64)     NOT NULL,
  status       ENUM('new','resolved') NOT NULL DEFAULT 'new',
  resolved_by  VARCHAR(64)  NULL,
  resolved_at  DATETIME     NULL,
  created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_feedback_status (status, created_at),
  KEY idx_feedback_ip (ip_hash, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Starting roles: Pastor Mo approves, the finance administrator pays. Admin can change these in People.
INSERT IGNORE INTO user_roles (staff_id, role, granted_by) VALUES
  ('hod-pastormo', 'approver', 'setup'),
  ('staff-chidinmauko', 'finance', 'setup');
