-- Copy of workCentral UK staff for hosting where workcentral_uk is not reachable (Hostinger).
-- Filled only by POST /sync/staff from the workCentral server. Leave STAFF_DB empty in .env to use it.
USE uk_expenses;
CREATE TABLE IF NOT EXISTS staff (
  id          VARCHAR(64)  NOT NULL PRIMARY KEY,
  name        VARCHAR(160) NOT NULL,
  role        VARCHAR(32)  NOT NULL DEFAULT 'staff',
  title       VARCHAR(160) NOT NULL DEFAULT '',
  department  VARCHAR(160) NULL,
  avatar_url  TEXT         NULL,
  kc_handle   VARCHAR(160) NULL,
  email       VARCHAR(255) NULL,
  active      TINYINT(1)   NOT NULL DEFAULT 1,
  synced_at   DATETIME     NOT NULL,
  KEY idx_staff_handle (kc_handle)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
