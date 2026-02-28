-- ============================================================
-- TeachTech — Database Schema v3
-- Production-safe: uses CREATE TABLE IF NOT EXISTS (no DROPs).
-- Run on fresh DB:  mysql -u root -p < database/schema.sql
-- Re-running on existing DB is SAFE — tables are preserved.
-- For a full destructive reset, use: database/schema_reset_dev.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS academy CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE academy;

-- ── users ────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id               INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    username         VARCHAR(80)    NOT NULL,
    email            VARCHAR(120)   NOT NULL,
    phone            VARCHAR(20)    DEFAULT NULL,
    role             ENUM('student','teacher','admin') NOT NULL DEFAULT 'student',

    -- OTP auth (plaintext otp kept for schema compat; otp_hash is the active field)
    otp              VARCHAR(6)     DEFAULT NULL,
    otp_hash         VARCHAR(255)   DEFAULT NULL,      -- bcrypt hash of OTP (v3+)
    otp_expires_at   DATETIME       DEFAULT NULL,
    otp_attempts     TINYINT UNSIGNED NOT NULL DEFAULT 0,   -- wrong-guess counter
    otp_locked_until DATETIME       DEFAULT NULL,           -- brute-force lockout
    otp_last_sent_at DATETIME       DEFAULT NULL,           -- resend cooldown gate

    is_verified      TINYINT(1)     NOT NULL DEFAULT 0,
    created_at       TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login       DATETIME       DEFAULT NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uk_email (email),
    INDEX idx_role (role),
    INDEX idx_verified (is_verified)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Upgrade path: add new OTP columns if re-running schema on v2 database
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS otp_hash         VARCHAR(255) DEFAULT NULL AFTER otp,
    ADD COLUMN IF NOT EXISTS otp_attempts     TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER otp_expires_at,
    ADD COLUMN IF NOT EXISTS otp_locked_until DATETIME DEFAULT NULL AFTER otp_attempts,
    ADD COLUMN IF NOT EXISTS otp_last_sent_at DATETIME DEFAULT NULL AFTER otp_locked_until;


-- ── materials ────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS materials (
    id               INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    title            VARCHAR(200)    NOT NULL,
    description      TEXT            DEFAULT NULL,
    subject          VARCHAR(100)    DEFAULT NULL,
    filename         VARCHAR(255)    NOT NULL,
    file_size        INT UNSIGNED    NOT NULL DEFAULT 0,
    uploaded_by      INT UNSIGNED    NOT NULL,
    upload_date      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    download_count   INT UNSIGNED    NOT NULL DEFAULT 0,

    PRIMARY KEY (id),
    CONSTRAINT fk_materials_user FOREIGN KEY (uploaded_by) REFERENCES users (id) ON DELETE CASCADE,
    INDEX idx_uploaded_by (uploaded_by),
    INDEX idx_upload_date (upload_date),
    INDEX idx_subject (subject),
    FULLTEXT INDEX ft_search (title, description)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ── download_log ──────────────────────────────────────────────────────────────
-- Per-student download tracking used by download.php and students.php
CREATE TABLE IF NOT EXISTS download_log (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id    INT UNSIGNED    NOT NULL,
    material_id   INT UNSIGNED    NOT NULL,
    downloaded_at TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    CONSTRAINT fk_dl_student  FOREIGN KEY (student_id)  REFERENCES users (id)      ON DELETE CASCADE,
    CONSTRAINT fk_dl_material FOREIGN KEY (material_id) REFERENCES materials (id)  ON DELETE CASCADE,
    INDEX idx_dl_student  (student_id),
    INDEX idx_dl_material (material_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ── table: live_sessions ──────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS live_sessions (
    id           INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    teacher_id   INT UNSIGNED     NOT NULL,
    title        VARCHAR(200)     NOT NULL,
    subject      VARCHAR(100)     NOT NULL DEFAULT '',
    room_name    VARCHAR(100)     NOT NULL,
    status       ENUM('scheduled','live','ended') NOT NULL DEFAULT 'scheduled',
    scheduled_at DATETIME         NULL DEFAULT NULL,
    started_at   DATETIME         NULL DEFAULT NULL,
    ended_at     DATETIME         NULL DEFAULT NULL,
    created_at   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_room_name (room_name),
    CONSTRAINT fk_ls_teacher FOREIGN KEY (teacher_id) REFERENCES users (id) ON DELETE CASCADE,
    INDEX idx_ls_status  (status),
    INDEX idx_ls_teacher (teacher_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ── view: materials with teacher name ────────────────────────────────────────
CREATE OR REPLACE VIEW v_materials AS
    SELECT
        m.id,
        m.title,
        m.description,
        m.subject,
        m.filename,
        m.file_size,
        m.download_count,
        m.upload_date,
        u.id       AS teacher_id,
        u.username AS teacher_name
    FROM materials m
    JOIN users u ON m.uploaded_by = u.id;


-- ── procedure: safe download increment ───────────────────────────────────────
DROP PROCEDURE IF EXISTS increment_download;
DELIMITER //
CREATE PROCEDURE increment_download(IN p_material_id INT UNSIGNED)
BEGIN
    UPDATE materials SET download_count = download_count + 1 WHERE id = p_material_id;
END //
DELIMITER ;


SELECT 'TeachTech schema v3 applied successfully.' AS status;
