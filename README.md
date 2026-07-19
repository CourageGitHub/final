# AI-Automated Past Question Repository & Timetable Management System

## Stack
PHP 8+, MySQL 8 / MariaDB 10.4+, vanilla HTML/CSS/JavaScript. No frameworks,
no Composer dependencies required for the core system.

## Requirements
- PHP 8.0 or later, with the `pdo_mysql`, `gd`, and `fileinfo` extensions enabled
- MySQL 8.0+ or MariaDB 10.4+
- (Later, for OCR) the `tesseract-ocr` binary installed on your system
- XAMPP/WAMP/MAMP is the easiest way to get all of this on one machine

## Setup

1. **Import the database**
   ```
   mysql -u root -p -e "CREATE DATABASE pq_timetable_system CHARACTER SET utf8mb4"
   mysql -u root -p pq_timetable_system < database/schema.sql
   ```

2. **Configure the app**
   ```
   cp config/config.example.php config/config.php
   ```
   Edit `config/config.php` with your real DB username/password and generate
   an app key:
   ```
   php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
   ```

3. **Create your admin account**
   ```
   php database/seed_admin.php
   ```

4. **Run it**
   ```
   php -S localhost:8000 -t public
   ```
   Or point an Apache/Nginx vhost's document root at the `public/` folder
   (never at the project root - `config/`, `database/`, and `uploads/` must
   not be web-accessible).

5. Visit `http://localhost:8000` and log in with the admin account you just created.

## Project structure
```
config/      DB connection + app config (config.php is gitignored)
database/    schema.sql + seed script
includes/    shared PHP: auth, CSRF, validation, helpers (added next)
public/      the actual web root - everything the browser can reach
uploads/     uploaded past-question files, OUTSIDE the web root on purpose
```

