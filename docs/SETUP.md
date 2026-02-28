# TeachTech Setup Guide

Complete step-by-step guide to set up the TeachTech project locally.

## Prerequisites

- **PHP ≥ 7.4** with extensions: `mysqli`, `fileinfo`, `mbstring` (test: `php --version`)
- **MySQL 5.7+ / MariaDB 10.3+**
- **Composer** (https://getcomposer.org)

**Quickest option on Windows:** Install [XAMPP](https://www.apachefriends.org/) — includes PHP, MySQL, and Apache.

---

## Step 1: Install Dependencies

```powershell
composer install
```

This uses `composer.lock` for reproducible installs (lockfile is committed).

## Step 2: Configure Environment

```powershell
copy .env.example .env
```

Edit `.env` — set at minimum:

```env
DB_HOST=localhost
DB_USER=root
DB_PASS=your_mysql_password
DB_NAME=academy

SMTP_USER=your-email@gmail.com
SMTP_PASS=your-app-password   # https://myaccount.google.com/apppasswords

APP_ENV=development
APP_BASE_URL=                 # Leave empty for root; set /teachtech for XAMPP subdirectory
```

## Step 3: Set Up Database

```powershell
# PowerShell — pipe via Get-Content:
Get-Content database\schema.sql | mysql -u root -p

# Or wrap in cmd.exe (classic < redirect):
cmd /c "mysql -u root -p < database\schema.sql"
```

> **Safe to re-run** — uses `CREATE TABLE IF NOT EXISTS`. No data is dropped.

Verify tables were created:
```sql
SHOW TABLES FROM academy;
-- Expected: download_log, materials, users (+ view v_materials)
```

## Step 4: Ensure Writable Directories

```powershell
New-Item -ItemType Directory -Force -Path "asset", "logs"
```

## Step 5: Run the Application

```powershell
# Built-in PHP server (development):
composer serve         # → http://localhost:8000

# Or via XAMPP Apache:
# Copy project to C:\xampp\htdocs\teachtech
# Set APP_BASE_URL=/teachtech in .env
# Visit http://localhost/teachtech/register.php
```

---

## Running Tests

```powershell
vendor\bin\phpunit.bat --prepend tests\prepend.php --testdox
# Or: composer test
```

Expected: **21 tests, 30 assertions, 0 failures**.

---

## Dev Reset (Destroys All Data)

> ⚠️ Never run on production.

```powershell
mysql -u root -p < database\schema_reset_dev.sql
```

---

## Project Structure (after setup)

```
teachtech/
├── core/              # Bootstrap, DB, helpers, layout, mailer
├── public/css/        # app.css — design system
├── public/js/         # app.js — OTP inputs, drag-drop, toasts
├── database/
│   ├── schema.sql           # v3 — production-safe, idempotent
│   └── schema_reset_dev.sql # DEV ONLY — drops all tables
├── tests/             # PHPUnit tests (CSRF, OTP lockout, upload)
├── asset/             # Uploaded files — .htaccess blocks direct HTTP
├── legacy/            # Superseded files kept for history
├── .github/workflows/ # GitHub Actions CI (lint + tests)
├── composer.json      # Dependencies
├── composer.lock      # Committed for reproducible installs
├── .env.example       # Configuration template
└── MIGRATION.md       # Detailed migration / upgrade guide
```

---

## Troubleshooting

| Problem | Solution |
|---------|----------|
| MySQL connection failed | Check `DB_*` in `.env`; ensure MySQL is running |
| Email not sending | Use Gmail App Password, not regular password |
| File upload rejected | Check `UPLOAD_MAX_SIZE` in `.env` and `upload_max_filesize` in `php.ini` |
| Direct file access 403 | Expected — files served via `download.php` only |
| CSRF token mismatch | Clear browser cookies; re-login |
| OTP locked | Wait 15 minutes or use schema_reset_dev.sql in dev |

---

✅ **Setup complete! Visit `http://localhost:8000/register.php` to get started.**
