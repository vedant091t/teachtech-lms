# CHANGELOG

All notable changes to TeachTech are documented here.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

---

## [Unreleased] — 2026-02-27

### A. Correctness & Runtime

#### Added
- **`database/download_log` table** — `download.php` (line 44) and `students.php` (line 51) write to and join this table. Without it, fresh setups crashed with SQL errors. Added indexes on `student_id` and `material_id`.
- **`database/schema_reset_dev.sql`** — dev-only destructive reset script extracted from `schema.sql`.

#### Changed
- **`database/schema.sql`** — upgraded from v2 to v3:
  - Replaced `DROP TABLE IF EXISTS` with `CREATE TABLE IF NOT EXISTS` — re-running schema on a live DB is now **safe**.
  - Added `otp_hash VARCHAR(255)`, `otp_attempts TINYINT`, `otp_locked_until DATETIME`, `otp_last_sent_at DATETIME` columns to `users` table (used by OTP hardening).
  - `ALTER TABLE … ADD COLUMN IF NOT EXISTS` upgrade path for existing v2 databases.

**Why it matters:** Without `download_log`, the app crashed on any fresh install. DROP TABLE nuked production data on schema re-run.

---

### B. Security & HTTP

#### Changed
- **`teacher_dashboard.php`** — delete button changed from `<a href="delete_material.php?id=X&csrf=TOKEN">` (GET, token in URL) to an inline `<form method="POST">` with CSRF token in the POST body.
- **`delete_material.php`** — rewrote to require `POST` method; reads `id` from `$_POST` not `$_GET`; rejects GET requests with 405. CSRF token is now never exposed in URLs, server access logs, or `Referer` headers.
- **`login.php`** — OTP is now stored as `password_hash($otp, PASSWORD_BCRYPT)` in `otp_hash` column; plaintext `otp` column set to NULL. Added 60-second resend cooldown (enforced via `otp_last_sent_at`). Lockout check runs before generating a new OTP.
- **`register.php`** — same bcrypt hash storage; `otp_attempts` and `otp_last_sent_at` initialised on INSERT.
- **`verify_otp.php`** — completely rewritten:
  - Verifies with `password_verify()` against `otp_hash`.
  - Increments `otp_attempts` on wrong guess.
  - Locks account for 15 minutes after 5 wrong attempts (sets `otp_locked_until`).
  - Lockout displayed even on GET (not just on POST).
  - Clears `otp_hash`, `otp_attempts`, `otp_locked_until`, `otp_expires_at` on success.
  - Hides the OTP form completely while account is locked.

**Why it matters:** Plaintext OTPs in a leaked DB let attackers log in instantly. GET-based CSRF was cosmetic — tokens in server logs are just as dangerous.

---

### C. File Integrity & Lifecycle

#### Added
- **`asset/.htaccess`** — denies direct HTTP access to uploaded files; works on Apache 2.2 and 2.4+. All file downloads enforced through `download.php`.

#### Changed
- **`core/helpers.php` — `handle_upload()`**:
  - Added `finfo_file()` MIME validation: actual file bytes checked against a strict `UPLOAD_MIME_MAP` constant (extension → allowed MIME types).
  - Added double-extension blocking: rejects `shell.php.pdf`, `evil.exe.docx`, etc.
  - Filename is now fully random (`time()_hex(random_bytes(6)).ext`) — no user-supplied name parts in final path.
- **`add_material.php`** — if DB INSERT fails after a successful upload, the orphaned file is now deleted from disk.
- **`edit_material.php`** — completely rewritten:
  - Fixed the **bug**: had 3 duplicate `UPDATE` statements; only the 3rd ever executed and had a subtle type-string mismatch (`ssssiii` missing `s` for string `filename`). Now has **exactly one correctly-typed UPDATE** (`bind_param('ssssiii', …)`).
  - Safe file lifecycle: new file uploaded → DB UPDATE → old file deleted. If DB fails, new file cleaned up. If validation fails before DB, uploaded file cleaned up.

**Why it matters:** Orphan files waste disk and can leak data. Double extensions like `shell.php.pdf` can execute as PHP if the server is misconfigured. Three duplicate UPDATE statements are a junior-level credibility red flag.

---

### D. Portability & Consistency

#### Added
- **`core/helpers.php` — `app_url(string $path)`** — reads `APP_BASE_URL` from `.env` (default `''`) to build portable URLs. Works at root (`/`) or subdirectory (`/teachtech`).
- **`core/bootstrap.php`** — `APP_BASE_URL` added to hard defaults.
- **`.env.example`** — `APP_BASE_URL=` key added with usage comment.

#### Changed
- **`core/layout.php`** — `$base = '/public'` replaced with `app_url('public')`. JS `<script src>` in footer likewise updated.
- **`core/helpers.php` — `auth_check()`** — redirects changed from `/login.php` (absolute, breaks subdirectory deploys) to `login.php` (relative).

#### Legacy Files (moved to `/legacy/`)
- `academy.php` — superseded by role-based dashboards
- `quiz.php` — empty 0-byte stub
- `video.php` — empty 0-byte stub
- `style1.css` — pre-refactor stylesheet (app uses `public/css/app.css`)
- `script.js` — pre-refactor JavaScript (app uses `public/js/app.js`)
- `db_config.php` — orphan config (app uses `core/db.php`)
- `config/` — obsolete stubs (app uses `core/bootstrap.php`)
- `includes/` — obsolete stubs (app uses `core/helpers.php`)
- `legacy/README.md` — explains each file's history

**Why it matters:** Absolute paths break when deploying under a subdirectory. Dead files in the root confuse reviewers and create false imports.

---

### E. Credibility & Repo

#### Added
- **`tests/bootstrap.php`** — PHPUnit test bootstrap (no DB, no HTTP, session started).
- **`tests/CsrfHelperTest.php`** — 5 tests: token format, session stability, HTML field, tampered rejection, correct match.
- **`tests/OtpLockoutTest.php`** — 8 tests: password_verify correctness, lock threshold, is_locked() time logic, correct OTP resets counter, 5 wrong attempts trigger lock.
- **`tests/UploadValidationTest.php`** — 8 tests: blocked ext, double ext, oversized, error code, exe, empty ext, size boundary, PDF magic bytes.
- **`phpunit.xml`** — PHPUnit 9.x config.
- **`.github/workflows/ci.yml`** — GitHub Actions: syntax lint + PHPUnit on push/PR.
- **`MIGRATION.md`** — safe, step-by-step fresh database setup guide.

#### Changed
- **`.gitignore`** — removed `composer.lock` exclusion. Application projects must commit the lockfile for reproducible `composer install`.
- **`README.md`** — removed false claims ("MIME check", "CSRF protection" on delete as correct). Added accurate Security Model table. Updated architecture diagram. Updated Limitations table (quiz/video now "Legacy stub").
- **`docs/SETUP.md`** — updated directory structure to match reality (removed references to `config/database.php`, `includes/csrf.php`; added `tests/`, `legacy/`).

**Why it matters:** A README that claims MIME checks but doesn't implement them, or a lockfile deliberately excluded, are immediate red flags in a code review.
