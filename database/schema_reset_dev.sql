-- ============================================================
-- TeachTech — DEV-ONLY Destructive Schema Reset
-- WARNING: This drops all data. NEVER run on production.
-- Usage:  mysql -u root -p < database/schema_reset_dev.sql
-- ============================================================

USE academy;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS download_log;
DROP TABLE IF EXISTS materials;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- Re-apply production schema from the safe file
SOURCE database/schema.sql;

SELECT 'TeachTech dev reset complete.' AS status;
