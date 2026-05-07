<div align="center">

# 🏠 Hostel Management System

### *A secure, full-stack web portal for managing girls' hostel admissions*

[![PHP](https://img.shields.io/badge/PHP-8.0%2B-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-4479A1?style=for-the-badge&logo=mysql&logoColor=white)](https://www.mysql.com/)
[![Apache](https://img.shields.io/badge/Apache-2.4%2B-D22128?style=for-the-badge&logo=apache&logoColor=white)](https://httpd.apache.org/)
[![License](https://img.shields.io/badge/License-MIT-22C55E?style=for-the-badge)](LICENSE)
[![Security](https://img.shields.io/badge/Data-AES--256--CBC-0EA5E9?style=for-the-badge&logo=letsencrypt&logoColor=white)]()

<br/>

> Built for **Smt. Rukminiyamma Girls Hostel** — a production-grade admission
> management system that handles the complete lifecycle of a hostel application:
> from student self-registration to admin review, approval, and analytics.

</div>

---

## 📌 Table of Contents

- [Features](#-features)
- [Tech Stack](#️-tech-stack)
- [Folder Structure](#-folder-structure)
- [Getting Started](#-getting-started)
  - [Prerequisites](#prerequisites)
  - [Installation](#installation)
  - [Running Locally](#running-locally)
- [Usage](#-usage)
- [Security Architecture](#-security-architecture)
- [Screenshots](#-screenshots)
- [Contributing](#-contributing)
- [License](#-license)

---

## ✨ Features

### 👩‍🎓 Student Portal
- **Account Registration** — Create a portal account with email & password (bcrypt hashed)
- **Hostel Application** — Multi-field admission form with validation (mobile, pincode, Aadhaar upload)
- **Duplicate Prevention** — Server-side duplicate checks on email and mobile numbers
- **PDF Receipt** — Generate a printable registration receipt directly from the browser
- **Password Recovery** — Self-service forgot/reset password flow via session-based verification

### 🔐 Admin Panel
- **Secure Login** — Session-based admin authentication
- **Application Review** — View all pending applications with decrypted student data
- **Approve / Reject** — One-click approval or rejection with optional admin notes
- **Distance Badge** — Automatic color-coded distance indicator (🟢 eligible / 🔴 ineligible) using Google Maps Distance Matrix API with local DB caching
- **Edit Records** — Inline edit of any student record with re-encryption on save
- **Delete Records** — Safe deletion with prepared statements
- **Export to CSV** — One-click Excel-friendly CSV export of all student data

### 📊 Approved Students Dashboard
- **Analytics Charts** — State-wise student distribution (Chart.js)
- **Filter & Search** — Filter by state, admission type, or free-text search
- **Responsive Table** — Paginated view of all approved students with key details

---

## 🛠️ Tech Stack

| Layer | Technology |
|-------|-----------|
| **Backend** | PHP 8.0+ |
| **Database** | MySQL 5.7+ via MySQLi (prepared statements) |
| **Server** | Apache 2.4 (XAMPP / WAMP / Linux) |
| **Encryption** | OpenSSL — AES-256-CBC for PII at rest |
| **Password Hashing** | PHP `password_hash()` — bcrypt |
| **Frontend** | Vanilla HTML5, CSS3 (CSS Variables), ES6 JavaScript |
| **Icons** | Font Awesome 6.4 (CDN) |
| **Charts** | Chart.js (CDN) |
| **Distance API** | Google Maps Distance Matrix API |
| **File Uploads** | PHP `move_uploaded_file()` → `/uploads` |

---

## 📁 Folder Structure

```
hostel-management-system/
│
├── 📄 admin.php                 # Admin panel — login, application review,
│                                #   approve/reject, edit, delete, CSV export
├── 📄 dashboard.php             # Approved students dashboard with charts & filters
├── 📄 login.php                 # Student portal login (AJAX-powered)
├── 📄 new-register.php          # Create a new student portal account
├── 📄 register.php              # Hostel application form (requires login)
├── 📄 edit_student.php          # Admin-only: edit an existing student record
├── 📄 forgot_password.php       # Step 1: verify email for password reset
├── 📄 reset_password.php        # Step 2: set a new password
├── 📄 send-password-reset.php   # Helper: handles reset form POST
├── 📄 db.php                    # Database connection (MySQLi)
│
├── 📂 sql/
│   └── hostel_db_schema.sql     # Full CREATE TABLE schema — import this first
│
├── 📂 docs/
│   └── SETUP.md                 # Detailed local & production setup guide
│
├── 📂 uploads/                  # Student Aadhaar document uploads
│   └── .gitkeep                 #   (excluded from git via .gitignore)
│
├── 📂 cache/                    # Google Maps API response cache (JSON)
│   └── .gitkeep                 #   (excluded from git via .gitignore)
│
├── 📄 .gitignore                # Excludes uploads/, cache/, .env, IDE files
└── 📄 README.md                 # You are here
```

---

## 🚀 Getting Started

### Prerequisites

- [XAMPP](https://www.apachefriends.org/) (or WAMP / any LAMP stack)
- PHP **8.0+** with extensions: `mysqli`, `openssl`, `curl`, `mbstring`
- MySQL **5.7+**
- A modern web browser

### Installation

**1. Clone the repository**

```bash
git clone https://github.com/YOUR_USERNAME/hostel-management-system.git
```

**2. Place in your web server root**

```bash
# XAMPP (Windows)
copy hostel-management-system C:\xampp\htdocs\hostel-management-system

# XAMPP (macOS / Linux)
cp -r hostel-management-system /opt/lampp/htdocs/
```

**3. Import the database**

- Start XAMPP → start **Apache** and **MySQL**
- Open `http://localhost/phpmyadmin`
- Create a new database named **`hostel_db`**
- Select it → **Import** tab → choose `sql/hostel_db_schema.sql` → **Go**

Or via the terminal:

```bash
mysql -u root -p hostel_db < sql/hostel_db_schema.sql
```

**4. (Optional) Configure your settings**

Open `db.php` and verify credentials:

```php
$servername = "localhost";
$username   = "root";      // your MySQL user
$password   = "";          // your MySQL password
$dbname     = "hostel_db";
```

Open `admin.php` and `dashboard.php` — update the admin credentials and encryption key:

```php
$ADMIN_USER     = "admin";                       // ← change in production
$ADMIN_PASS     = "admin123";                    // ← change in production
$ENCRYPTION_KEY = "my-super-secret-key-123456";  // ← use a long random string
```

> ⚠️ **Important:** `$ENCRYPTION_KEY` must be identical in all three files
> (`admin.php`, `dashboard.php`, `register.php`). Changing it after data has
> been stored will make existing records unreadable.

### Running Locally

1. Start XAMPP → **Apache** + **MySQL**
2. Open your browser and navigate to:

```
http://localhost/hostel-management-system/login.php
```

---

## 📖 Usage

### Student Flow

```
new-register.php  →  login.php  →  register.php  →  (await admin approval)
```

| Step | Page | Action |
|------|------|--------|
| 1 | `new-register.php` | Create an account with email & password |
| 2 | `login.php` | Log in with your credentials |
| 3 | `register.php` | Fill in the hostel application form & upload Aadhaar |
| 4 | — | Wait for admin review (pending / approved / rejected) |

> **Eligibility:** The hostel is only available to students residing **more than 100 km** from the hostel. The distance is automatically calculated from the student's pincode.

### Admin Flow

```
admin.php (login)  →  Review applications  →  Approve / Reject / Edit / Export
                   →  dashboard.php        →  View approved students & analytics
```

| Action | How |
|--------|-----|
| Login | Navigate to `admin.php` → username: `admin` / password: `admin123` (defaults) |
| Review | All pending applications appear in the main table with distance badges |
| Approve | Click **Approve** → optionally add notes → student moves to `dashboard.php` |
| Reject | Click **Reject** → optionally add notes → status updated |
| Edit | Click the **✏️ Edit** icon → update fields → save (data is re-encrypted) |
| Delete | Click **🗑️ Delete** → record is permanently removed |
| Export | Click **📥 Export CSV** → downloads an Excel-friendly UTF-8 CSV |
| Dashboard | Click **View Dashboard** → filterable, searchable approved-student view with charts |

---

## 🔒 Security Architecture

| Concern | Implementation |
|---------|---------------|
| **PII Encryption** | All student personal data (name, mobile, email, address) is encrypted with **AES-256-CBC** before database storage |
| **Password Hashing** | Student passwords use PHP `password_hash()` (bcrypt); admin passwords are compared via plain-text (upgrade recommended for production) |
| **SQL Injection** | 100% prepared statements via MySQLi — no raw string interpolation in queries |
| **Session Management** | `session_start()` on every page; session destroyed on logout |
| **Input Validation** | Server-side validation for all POST fields; `ctype_digit()` checks on numeric IDs |
| **File Uploads** | Uploaded Aadhaar images stored outside web-accessible paths with timestamped filenames |
| **API Caching** | Google Maps responses cached locally for 24 hours to prevent key abuse |

> 📖 See [`docs/SETUP.md`](docs/SETUP.md) for production hardening recommendations.

---

## 🤝 Contributing

Contributions are welcome! Please follow these steps:

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/your-feature-name`
3. Commit your changes: `git commit -m "feat: add your feature"`
4. Push to the branch: `git push origin feature/your-feature-name`
5. Open a **Pull Request**

---

## 📄 License

This project is licensed under the **MIT License**.
See the [LICENSE](LICENSE) file for details.

---

<div align="center">

Made with ❤️ for **Smt. Rukminiyamma Girls Hostel**

</div>
