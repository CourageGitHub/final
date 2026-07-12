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

## Status
- [x] Database schema (21 tables: users/courses/repository/timetable/AI logging/notifications/favorites)
- [x] Auth (register/login/RBAC/CSRF/rate-limited login)
- [x] AI wrapper (provider-agnostic: OpenAI or Anthropic)
- [x] Design system (blue/white, dark mode toggle, sidebar shell)
- [x] Courses admin
- [x] Past question repository: upload, OCR, admin review/approve, student search/download/favorite
- [x] AI Question Solver (quick answer / detailed / explain / similar questions, with feedback + save)
- [x] Examination timetable (admin scheduling with clash detection + student filtered view)
- [ ] AI Study Assistant chatbot + floating widget
- [ ] AI analytics dashboard (page exists as a placeholder)
- [ ] Exam Prediction Engine
- [ ] Document auto-classification (AI-suggested tags on upload)
- [ ] Profile settings page
- [ ] Landing page
- [ ] Security hardening pass
- [ ] Deployment guide

## Update your database for today's changes
No new tables were added for the timetable module (`rooms` and
`exam_timetable_entries` were already in the schema) — nothing to migrate.
If you're ever unsure your local DB matches `schema.sql`, it's always safe
to re-run it: every `CREATE TABLE` uses `IF NOT EXISTS`, so re-importing
only creates what's missing.
```
mysql -u root -p pq_timetable_system < database/schema.sql
```

## OCR requires two extra tools (optional, but needed for scanned papers to get searchable/AI-usable text)
Without these, uploads still work — they just won't have extracted text until you install them.

**Windows:**
- Tesseract: https://github.com/UB-Mannheim/tesseract/wiki (installer). Add its folder
  (e.g. `C:\Program Files\Tesseract-OCR`) to your PATH.
- Poppler (`pdftotext`/`pdftoppm`): https://github.com/oschwartz10612/poppler-windows/releases
  — extract, and add the `Library\bin` folder inside it to your PATH.
- Restart your terminal/VS Code after editing PATH, same as you did for `php`.

**Also check `php.ini`** if uploads of larger scanned PDFs fail: XAMPP's defaults
(`upload_max_filesize`, `post_max_size` — often 2M/8M) are too small for a multi-page
scan. Raise both to something like `20M` in `php.ini`, then restart Apache/your PHP server.

## Repository module notes
- Admin uploads a paper → it's stored outside the webroot → OCR (or DOCX/PDF text
  extraction) runs automatically → the text is heuristically split into numbered
  questions → admin reviews/fixes the split on `question_review.php` → Approve
  publishes it (and fires an in-app notification) → students can then find it,
  download it, favorite it, and run it through the AI Solver.
- Every upload is re-checked server-side for its real file type (never trusts
  the browser's claimed type) and hashed to block exact duplicate re-uploads.

## AI setup (required for the Solver / Study Assistant)
1. Get an API key from **either**:
   - OpenAI: https://platform.openai.com/api-keys
   - Anthropic: https://console.anthropic.com/settings/keys
2. In `config/config.php`, set the `ai` block:
   ```php
   'ai' => [
       'provider' => 'openai',            // or 'anthropic'
       'api_key'  => 'sk-...your key...',
       'model'    => 'gpt-5.4-mini',      // or 'claude-haiku-4-5-20251001' for anthropic
   ],
   ```
3. Both providers bill pay-as-you-go; a budget model like the ones above
   costs a small fraction of a cent per question solved — testing this
   heavily during development should still cost cents, not dollars.
   Check your provider's current pricing page before relying on exact figures.

## Timetable module notes
- Add rooms first (there's a quick-add form right on the timetable page),
  then courses (`/admin/courses.php`), then you can schedule exam slots.
- Clash detection runs at two levels: a friendly app-level check (room
  double-booked, or the same department+level already has an overlapping
  exam) shows a clear error before saving; a `UNIQUE` constraint in the
  database is the final backstop in case two admins save at the same instant.
- Students only see slots for courses in their own department + level.
- Scheduling a slot fires an in-app broadcast notification automatically.

## Auth module notes
- Only students can self-register at `/register.php`. Lecturer and admin
  accounts are created by an admin (that screen comes with the admin
  module next) or via `php database/seed_admin.php` for the first admin.
- Login is rate-limited: 5 failed attempts (by email OR by IP) locks
  further attempts for 15 minutes — see `too_many_failed_attempts()` in
  `includes/auth.php`.
- Every POST form includes a CSRF token (`includes/csrf.php`); every
  handler calls `csrf_verify()` before touching the database.
- `require_role('admin')` (etc.) at the top of a page is how access control
  is enforced — see `public/admin/index.php` for the pattern to copy.
