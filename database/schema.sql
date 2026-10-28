-- UK expense claims portal.
-- Staff identities live in workcentral_uk.users (read-only); staff_id / actor_id / decided_by hold users.id from there.
-- Money is stored in pence (integer) to avoid float rounding.

CREATE DATABASE IF NOT EXISTS uk_expenses
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE uk_expenses;

CREATE TABLE IF NOT EXISTS claims (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  staff_id      VARCHAR(64)  NOT NULL,
  title         VARCHAR(160) NOT NULL,
  notes         TEXT         NULL,
  total_pence   INT UNSIGNED NOT NULL DEFAULT 0,
  currency      CHAR(3)      NOT NULL DEFAULT 'GBP',
  status        ENUM('pending','changes_requested','approved','rejected') NOT NULL DEFAULT 'pending',
  submitted_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  decided_by    VARCHAR(64)  NULL,
  decided_at    DATETIME     NULL,
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_claims_staff_status (staff_id, status),
  KEY idx_claims_status_submitted (status, submitted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS claim_items (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  claim_id      INT UNSIGNED NOT NULL,
  position      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  spent_on      DATE         NOT NULL,
  category      VARCHAR(40)  NOT NULL,
  description   VARCHAR(255) NOT NULL,
  amount_pence  INT UNSIGNED NOT NULL,
  KEY idx_items_claim (claim_id, position),
  CONSTRAINT fk_items_claim FOREIGN KEY (claim_id) REFERENCES claims (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Proof of expense (receipts, invoices). Files sit in storage/receipts under stored_name, never web-served directly.
CREATE TABLE IF NOT EXISTS claim_files (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  claim_id      INT UNSIGNED NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  stored_name   VARCHAR(48)  NOT NULL,
  mime          VARCHAR(80)  NOT NULL,
  size_bytes    INT UNSIGNED NOT NULL,
  uploaded_by   VARCHAR(64)  NOT NULL,
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_files_stored (stored_name),
  KEY idx_files_claim (claim_id),
  CONSTRAINT fk_files_claim FOREIGN KEY (claim_id) REFERENCES claims (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Audit trail and conversation: every submission, comment and HOD decision.
CREATE TABLE IF NOT EXISTS claim_events (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  claim_id      INT UNSIGNED NOT NULL,
  actor_id      VARCHAR(64)  NOT NULL,
  type          ENUM('submitted','resubmitted','comment','approved','rejected','changes_requested') NOT NULL,
  body          TEXT         NULL,
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_events_claim (claim_id, created_at),
  CONSTRAINT fk_events_claim FOREIGN KEY (claim_id) REFERENCES claims (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
