-- KingsChat notifications: the sender account's tokens, and a queue of messages to deliver.
USE uk_expenses;

CREATE TABLE IF NOT EXISTS kc_agent (
  id                TINYINT UNSIGNED NOT NULL PRIMARY KEY DEFAULT 1,
  handle            VARCHAR(160) NULL,
  access_token      TEXT         NULL,
  refresh_token     TEXT         NULL,
  access_expires_at DATETIME     NULL,
  connected_by      VARCHAR(64)  NULL,
  connected_at      DATETIME     NULL,
  pending_token     CHAR(64)     NULL,
  pending_expires   DATETIME     NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO kc_agent (id) VALUES (1);

-- KingsChat user ids looked up from handles, so each send is one API call.
CREATE TABLE IF NOT EXISTS kc_users (
  handle      VARCHAR(160) NOT NULL PRIMARY KEY,
  kc_user_id  VARCHAR(160) NOT NULL,
  resolved_at DATETIME     NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  dedupe_key    VARCHAR(120) NOT NULL,
  staff_id      VARCHAR(64)  NULL,
  kc_handle     VARCHAR(160) NOT NULL,
  body          TEXT         NOT NULL,
  status        ENUM('pending','sent','failed','skipped') NOT NULL DEFAULT 'pending',
  attempts      TINYINT UNSIGNED NOT NULL DEFAULT 0,
  last_error    VARCHAR(255) NULL,
  next_try_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  sent_at       DATETIME     NULL,
  UNIQUE KEY uq_notifications_dedupe (dedupe_key),
  KEY idx_notifications_due (status, next_try_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
