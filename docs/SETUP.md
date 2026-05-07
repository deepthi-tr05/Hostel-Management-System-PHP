# 🛠️ Setup & Deployment Guide

## Local Development (XAMPP / WAMP)

### 1. Prerequisites

| Tool | Version | Download |
|------|---------|----------|
| PHP | ≥ 8.0 | https://www.php.net/downloads |
| MySQL | ≥ 5.7 | Bundled with XAMPP |
| Apache | ≥ 2.4 | Bundled with XAMPP |
| XAMPP | Latest | https://www.apachefriends.org |

> **Required PHP extensions:** `mysqli`, `openssl`, `curl`, `mbstring`

---

### 2. Clone / Copy the Project

```bash
# Clone from GitHub
git clone https://github.com/YOUR_USERNAME/hostel-management-system.git

# Or copy the folder into your XAMPP htdocs directory
cp -r hostel-management-system/ C:/xampp/htdocs/
```

---

### 3. Import the Database

1. Start **XAMPP** → Start **Apache** and **MySQL**
2. Open **phpMyAdmin** → `http://localhost/phpmyadmin`
3. Click **New** → create a database named `hostel_db`
4. Select `hostel_db` → click the **Import** tab
5. Choose `sql/hostel_db_schema.sql` → click **Go**

Or via CLI:

```bash
mysql -u root -p hostel_db < sql/hostel_db_schema.sql
```

---

### 4. Configure Database Credentials

Open `db.php` and update if your MySQL credentials differ from defaults:

```php
$servername = "localhost";
$username   = "root";        // your MySQL username
$password   = "";            // your MySQL password
$dbname     = "hostel_db";
```

> ⚠️ **Security Note:** In production, move credentials to environment variables
> or a `.env` file — never commit secrets to version control.

---

### 5. Configure Admin Credentials & Encryption Key

At the top of `admin.php` and `dashboard.php`, update:

```php
$ADMIN_USER     = "admin";                      // Change this!
$ADMIN_PASS     = "admin123";                   // Change this!
$ENCRYPTION_KEY = "my-super-secret-key-123456"; // Change this!
```

> ⚠️ All three values must match across both files.

---

### 6. (Optional) Google Maps API Key

Distance-from-hostel calculations use the **Google Maps Distance Matrix API**.

1. Get a key from [Google Cloud Console](https://console.cloud.google.com/)
2. Enable **Distance Matrix API** on your project
3. In `admin.php`, replace:

```php
$GOOGLE_API_KEY   = "YOUR_API_KEY_HERE";
$FIXED_ORIGIN_PIN = "570016";   // Hostel's pincode
```

> Without a valid key, the app falls back to distances pre-cached in the
> `distance` database table (manual entries work too).

---

### 7. Folder Permissions

The app auto-creates `uploads/` and `cache/` at runtime, but you can
pre-create them to avoid permission errors on Linux/macOS:

```bash
mkdir -p uploads cache
chmod 755 uploads cache
```

---

### 8. Run the Application

Navigate to:

```
http://localhost/hostel-management-system/
```

| URL | Description |
|-----|-------------|
| `login.php` | Student portal login |
| `new-register.php` | Create a new student account |
| `register.php` | Submit hostel application (after login) |
| `forgot_password.php` | Password recovery |
| `admin.php` | Admin panel (review applications) |
| `dashboard.php` | Approved students dashboard |

---

## Production Deployment (Linux + Apache)

```bash
# 1. Upload files to /var/www/html/hostel/
# 2. Set ownership
sudo chown -R www-data:www-data /var/www/html/hostel/

# 3. Set directory permissions
sudo chmod -R 755 /var/www/html/hostel/
sudo chmod -R 777 /var/www/html/hostel/uploads/
sudo chmod -R 777 /var/www/html/hostel/cache/

# 4. Import DB
mysql -u root -p hostel_db < sql/hostel_db_schema.sql
```

---

## Troubleshooting

| Issue | Fix |
|-------|-----|
| Blank page | Enable PHP error reporting: `ini_set('display_errors', 1);` |
| DB connection failed | Check `db.php` credentials and that MySQL service is running |
| File upload fails | Ensure `uploads/` directory exists and is writable |
| Distance shows N/A | Check Google API key or populate the `distance` table manually |
| Login loop | Confirm `session_start()` is the very first line in each PHP file |
