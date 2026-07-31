# EESA Website — Setup Guide

A clean rebuild of the EESA site: PHP 8 + MySQL, no framework, organized into
`pages/` (public), `admin/` (role-gated back office), and `includes/` (shared
code). The old single-file version is gone — this is a real project structure.

## 1. Requirements
- PHP 8.1+ with the `pdo_mysql` extension
- MySQL / MariaDB
- Any web server (Apache/Nginx) or just `php -S localhost:8000` for local testing

## 2. Database
```
mysql -u root -p -e "CREATE DATABASE eesa_db CHARACTER SET utf8mb4"
mysql -u root -p eesa_db < db_schema.sql
```
This creates all tables and seeds one `super_admin` account (`superadmin` /
placeholder hash). **Before going live**, generate a real password hash and
update it:
```php
<?php echo password_hash('YourStrongPassword', PASSWORD_DEFAULT);
```
Then `UPDATE users SET password_hash='<hash>' WHERE username='superadmin';`

## 3. Configure
Edit `config.php`:
- `DB_HOST` / `DB_NAME` / `DB_USER` / `DB_PASS`
- `SMTP_*` constants (used by `includes/mailer.php` for OTP + approval emails)
- `COLLEGE_DOMAIN` (used to build `regno@sggs.ac.in` for aptitude OTPs)
- `BASE_URL` if the site isn't hosted at the domain root

## 4. Mail delivery
The mailer ships using PHP's built-in `mail()` so it runs out of the box, but
most hosts need real SMTP for OTPs to land reliably. Swap `send_mail()` in
`includes/mailer.php` for PHPMailer:
```
composer require phpmailer/phpmailer
```
and point it at `SMTP_HOST/PORT/USER/PASS` from `config.php`.

## 5. Folder permissions
`uploads/activities`, `uploads/gallery`, `uploads/team`, `uploads/qp` must be
writable by the web server user (`chmod -R 755 uploads`).

## 6. How access works
- **Public pages** (`pages/*.php`) need no login.
- **Admin login lives at `/login.php`** — it is deliberately not linked
  anywhere in the public menu (see the comment in `includes/header.php`).
  Bookmark it directly.
- Roles, enforced on every admin page via `require_role()`:
  - `super_admin` — everything, plus user approval/roles and the audit log
  - `admin` — announcements, activities, gallery, team, department, contact inbox
  - `aptitude_manager` — only the aptitude results section
  - `member` — approved joiners with no back-office access
- **Join flow**: `/pages/join.php` requires email OTP verification, then
  creates a `pending` user with a ticket ID. A `super_admin` approves it from
  `/admin/users.php`, assigning a username/role — the system generates a
  temp password and emails it.

## 7. What's admin-managed vs automatic
- Activities/Announcements/Gallery/Team/Department/Aptitude: fully admin-authored.
- Announcement status (`upcoming` / `ongoing` / `completed`) is computed live
  from `event_datetime` / `registration_close`, in IST — no cron needed.
- Aptitude results: admins/aptitude managers upload a CSV per test
  (`reg_no,score,status,remarks`) plus an optional question-paper PDF.
  Students verify via OTP sent to `regno@sggs.ac.in` before viewing anything.

## 8. Extending
- The activity/announcement editor fields are currently plain textareas
  storing HTML. Swap in a WYSIWYG (TinyMCE/Quill) if admins want rich text
  without hand-writing HTML — the DB and rendering already expect HTML.
- The shareable "poster card" on activity pages downloads as a PNG via
  html2canvas (CDN-loaded) — no server-side image generation needed.
