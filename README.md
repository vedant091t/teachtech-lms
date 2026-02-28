# TeachTech 🎓

> A secure, OTP-based Learning Management System for teachers and students.
> Built with PHP, MySQL, and vanilla JavaScript — no frameworks, clean architecture.

---

## Architecture

```
teachtech/
├── core/                  # Application kernel (bootstrap, db, helpers, layout, mailer)
├── public/
│   ├── css/app.css        # Complete design system (CSS variables → components)
│   └── js/app.js          # OTP inputs, drag-drop, toasts, animations
├── database/
│   ├── schema.sql         # v3 production-safe schema — idempotent, no DROP TABLE
│   └── schema_reset_dev.sql  # Dev-only destructive reset (NEVER run on production)
├── tests/                 # PHPUnit test suite (CSRF, OTP lockout, upload validation)
├── asset/                 # Uploaded materials — NOT directly accessible (stored at runtime, not committed to GitHub)
├── docs/
│   └── SETUP.md           # Step-by-step setup for Windows / XAMPP
├── .github/workflows/ci.yml  # GitHub Actions: lint + PHPUnit
├── login.php              # OTP login entry (60s resend cooldown, bcrypt hashed OTP)
├── register.php           # New user registration (hashed OTP stored, never plaintext)
├── verify_otp.php         # 6-digit OTP verification (lockout after 5 wrong attempts)
├── student_dashboard.php  # Browse + search + filter + paginate materials
├── teacher_dashboard.php  # Upload stats + POST-only delete
├── add_material.php       # Upload form with drag-drop zone + DB-failure cleanup
├── edit_material.php      # Edit title/desc/subject + safe file swap lifecycle
├── delete_material.php    # POST-only CSRF-protected deletion (CSRF never in URL)
├── download.php           # Secure streaming via DB id (no path traversal, download_log)
├── logout.php             # Full session + cookie destruction
├── composer.json          # Dependencies: PHPMailer, phpdotenv, PHPUnit (dev)
└── composer.lock          # Committed — reproducible installs
```

## Tech Stack

| Layer       | Technology                          |
|-------------|-------------------------------------|
| Backend     | PHP 7.4+                            |
| Database    | MySQL 5.7+ (InnoDB, utf8mb4)        |
| Email       | PHPMailer 6.x via Gmail SMTP        |
| Config      | vlucas/phpdotenv                    |
| Frontend    | HTML5, Vanilla CSS (CSS vars), Vanilla JS (ES2020) |
| Icons       | Font Awesome 6                      |
| Typography  | Google Fonts — Inter                |
| Testing     | PHPUnit 9.x                         |
| CI          | GitHub Actions                      |

## Security Model

| Feature               | Implementation                                                     |
|-----------------------|--------------------------------------------------------------------|
| No passwords          | OTP-based passwordless auth (6-digit, 10-min expiry)              |
| OTP storage           | `password_hash(PASSWORD_BCRYPT)` — plaintext never persisted       |
| OTP brute-force       | 5 wrong attempts → 15-min account lockout (DB-backed)             |
| OTP spam prevention   | 60-second resend cooldown enforced server-side                     |
| CSRF protection       | `hash_equals` token in POST **body** — never in URL/query params  |
| SQL injection         | 100% prepared statements, no string interpolation                  |
| XSS prevention        | `htmlspecialchars()` via `e()` on all output                       |
| Session security      | HTTPOnly, SameSite=Strict, session regeneration on login           |
| File upload safety    | Extension allowlist + `finfo_file()` MIME check + double-ext block |
| File access control   | `.htaccess` denies direct HTTP to `/asset/`; served via download.php |
| Path traversal        | Downloads served by DB id, filename from DB (not URL)              |
| Hardcoded secrets     | Zero — all config via `.env`                                       |

## Quick Start

### Prerequisites
- PHP ≥ 7.4 (test: `php --version`)
- MySQL 5.7+
- Composer (`composer --version`)

**Installing PHP + Composer on Windows:**
Download XAMPP → https://www.apachefriends.org/ (includes PHP, MySQL, Apache)
Download Composer → https://getcomposer.org/Composer-Setup.exe

---

### 1. Install dependencies
```bash
composer install
```

### 2. Configure environment
```bash
copy .env.example .env   # Windows
```
Edit `.env` — set DB credentials and SMTP details.

**Subdirectory deploy** (e.g. XAMPP `htdocs/teachtech`): set `APP_BASE_URL=/teachtech`

### 3. Set up database
```bash
mysql -u root -p < database/schema.sql
```
Re-running on an existing database is **safe** — uses `CREATE TABLE IF NOT EXISTS`.

### 4. Run locally
```bash
composer serve    # → http://localhost:8000
```

---

## Running Tests

```bash
vendor/bin/phpunit --testdox
```

---

## Design Decisions

**Why no passwords?**
OTP-only auth eliminates the biggest attack surface (stolen/reused passwords). For an educational LMS where teacher accounts are institutional, this is strictly simpler and more secure.

**Why no PHP framework?**
The project is small enough that a micro-architecture (`core/` bootstrap) provides clean separation without framework overhead. Demonstrates understanding of fundamentals over black-box usage.

**Why `download.php` instead of direct file links?**
Direct links expose the server file system and prevent download tracking. The router pattern lets us count downloads, check auth, and change storage backends without touching URLs.

**Why hash OTP with bcrypt?**
If the `users` table is ever leaked, plaintext OTPs would let an attacker log in as any user. Bcrypt hashing costs the attacker the same effort as cracking a password.

---

## Limitations & Future Scope

| Area              | Planned                               |
|-------------------|---------------------------------------|
| Quizzes           | MCQ quiz engine with scoring          |
| Video player      | HTML5 player with progress tracking   |
| Deployment        | Docker + Nginx + Let's Encrypt HTTPS  |
| Admin panel       | User management, content moderation   |

---

## Contributing

1. Fork → branch → commit → PR
2. Follow PSR-12 coding style
3. Every new feature needs a test (PHPUnit)
4. No secrets in code — `.env` only

---

**Author:** Vedant Tandel
**License:** MIT
