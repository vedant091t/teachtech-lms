# MIGRATION / Setup Guide

Safe, idempotent database initialization for TeachTech (schema v3).

---

## Fresh Installation

### Prerequisites
- PHP ≥ 7.4 with extensions: `mysqli`, `fileinfo`, `mbstring`
- MySQL 5.7+ or MariaDB 10.3+
- Composer

### Steps

```bash
# 1. Install PHP dependencies (uses committed composer.lock for reproducibility)
composer install --no-dev          # production
# composer install                 # development (includes PHPUnit)

# 2. Copy and edit environment config
copy .env.example .env             # Windows
# cp .env.example .env             # Linux/Mac

# Edit .env — set at minimum:
#   DB_HOST, DB_USER, DB_PASS, DB_NAME
#   SMTP_USER, SMTP_PASS (for OTP email)
#   APP_ENV=production (on live servers)

# 3. Import the schema (safe to run on an empty or existing database)
# PowerShell:
Get-Content database\schema.sql | mysql -u root -p
# cmd.exe / bash:
# mysql -u root -p < database/schema.sql

# 4. Verify
mysql -u root -p -e "SHOW TABLES FROM academy;"
# Expected tables: users, materials, download_log
# Expected view:   v_materials
# Expected proc:   increment_download
```

### What `schema.sql` does
- Creates the `academy` database if it does not exist.
- Creates `users`, `materials`, `download_log` tables **only if they don't already exist**.
- Runs `ALTER TABLE … ADD COLUMN IF NOT EXISTS` to add new OTP columns to an existing v2 `users` table without data loss.
- Creates or replaces the `v_materials` view.
- Creates or replaces the `increment_download` stored procedure.

### Re-running on an existing database ✅ Safe

```bash
mysql -u root -p < database/schema.sql
# → "TeachTech schema v3 applied successfully."
```

No data is lost. Existing rows are preserved. New columns are added with safe defaults.

---

## Upgrading from Schema v2

If you have an existing v2 database, re-running `schema.sql` is all you need:

```powershell
# PowerShell:
Get-Content database\schema.sql | mysql -u root -p
# cmd.exe:
# cmd /c "mysql -u root -p < database\schema.sql"
```

This adds:
- `download_log` table (new)
- `otp_hash`, `otp_attempts`, `otp_locked_until`, `otp_last_sent_at` columns to `users`

Existing users will have `otp_hash = NULL`, `otp_attempts = 0` — they will be prompted to request a new OTP on next login, which will create the hash.

---

## Dev Reset (Destroys All Data)

> ⚠️ **NEVER run on production.**

```powershell
Get-Content database\schema_reset_dev.sql | mysql -u root -p
```

This drops all tables and rebuilds from scratch — useful for local testing only.

---

## Running Tests

```bash
# After composer install (dev deps included):
vendor/bin/phpunit --testdox

# PHP syntax lint:
find . -name "*.php" -not -path "*/vendor/*" -not -path "*/legacy/*" | xargs php -l
```

---

## Smoke Checklist (after setup)

| # | Scenario | Expected |
|---|----------|----------|
| 1 | `mysql -u root -p < database/schema.sql` on empty DB | Tables created, no errors |
| 2 | Same command on existing DB | No errors, data preserved |
| 3 | Register teacher + verify OTP | Redirected to teacher dashboard |
| 4 | Upload a PDF | File in `asset/`, DB row created |
| 5 | Download as student | File streams, `download_log` row inserted |
| 6 | Delete material (teacher) | POST form submits; material gone |
| 7 | Direct GET to `asset/file.pdf` | 403 Forbidden |
| 8 | Enter wrong OTP 5× | Account locked for 15 min |
| 9 | Upload `shell.php.pdf` | Rejected ("multiple extensions") |
| 10 | `vendor/bin/phpunit` | All tests green |
