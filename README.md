# TeachTech LMS 🎓

A secure, OTP-based Learning Management System built with PHP, MySQL, and vanilla JavaScript.

This project demonstrates secure authentication, safe file handling, and clean architecture without using heavy frameworks.

---

## 🚀 Features

- 🔐 OTP-based passwordless authentication  
- 🧠 Brute-force protection with account lockout  
- 📂 Secure file uploads with MIME validation  
- 📥 Controlled file downloads via router  
- 🛡 CSRF protection (POST-only actions)  
- 🧪 PHPUnit test suite  
- 🔄 GitHub Actions CI pipeline  
- 🎨 Clean responsive UI (Vanilla CSS + JS)  

---

## 🏗 Project Structure

```
teachtech/
├── core/                # Bootstrap, DB connection, helpers
├── public/              # CSS and JS
├── database/            # SQL schema
├── docs/                # Setup documentation
├── tests/               # PHPUnit tests
├── .github/workflows/   # CI configuration
├── asset/               # Runtime uploaded files (not committed)
├── composer.json
├── composer.lock
└── README.md
```

---

## ⚙️ Installation

### 1️⃣ Clone Repository

```bash
git clone https://github.com/vedant091t/teachtech-lms.git
cd teachtech-lms
```

### 2️⃣ Install Dependencies

```bash
composer install
```

### 3️⃣ Setup Environment

```bash
copy .env.example .env   # Windows
```

Edit `.env` with your database and SMTP credentials.

### 4️⃣ Setup Database

```bash
mysql -u root -p < database/schema.sql
```

### 5️⃣ Run Development Server

```bash
composer serve
```

---

## 🔐 Security Highlights

- No passwords stored  
- OTP hashed using bcrypt  
- Account lockout after 5 failed attempts  
- CSRF tokens verified using `hash_equals()`  
- All queries use prepared statements  
- Direct file access blocked via `.htaccess`  

---

## 🧪 Running Tests

```bash
vendor/bin/phpunit --testdox
```

---

## 🛠 Tech Stack

- PHP 7.4+  
- MySQL  
- PHPMailer  
- PHPUnit  
- GitHub Actions  
- Vanilla CSS + JavaScript  

---

## 📌 License

MIT License  

---

**Author:** Vedant Tandel
