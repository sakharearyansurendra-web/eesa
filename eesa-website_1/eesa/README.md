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

## 8. Deploying on Render

Render has no native PHP runtime and no managed MySQL, so this deploys as a
**Docker web service** (Dockerfile is already included) plus an **external
MySQL database**. Total cost can be $0 using free tiers, though free tiers
sleep/expire — fine for a demo, upgrade for a real production site.

### Step 1 — Push the project to GitHub
Render deploys from a Git repo.
```
git init
git add .
git commit -m "EESA website"
```
Create a new GitHub repo and push to it.

### Step 2 — Get a MySQL database
Render doesn't offer managed MySQL (only Postgres), so use an external one.
Easiest free options:
- **Railway** → New Project → "Provision MySQL" → copy the connection
  details from the "Connect" tab (host, port, user, password, database).
- **Aiven for MySQL** (free trial) → create a MySQL service → same idea.
- **Clever Cloud** also has a MySQL add-on with a free tier.

Whichever you pick, you'll end up with: host, port, database name, username,
password. Run the schema against it once:
```
mysql -h <host> -P <port> -u <user> -p <database> < db_schema.sql
```
(Most of these dashboards also let you run SQL directly in a web console —
paste `db_schema.sql` there if you don't have the `mysql` CLI handy.)

### Step 3 — Create the Web Service on Render
1. Render dashboard → **New +** → **Web Service**
2. Connect your GitHub repo
3. Render will detect the `Dockerfile` automatically — leave **Runtime: Docker**
4. Instance type: Free (or Starter for something that doesn't sleep)
5. If you committed `render.yaml`, Render will offer to use it as a Blueprint
   and pre-fill most of the environment variable names for you — just fill
   in the values (see Step 4).

### Step 4 — Set environment variables
In the service's **Environment** tab, add:

| Key | Value |
|---|---|
| `DB_HOST` | from your MySQL provider |
| `DB_PORT` | usually `3306` |
| `DB_NAME` | your database name |
| `DB_USER` | your database user |
| `DB_PASS` | your database password |
| `SMTP_HOST` | e.g. `smtp.gmail.com` or your provider's SMTP host |
| `SMTP_PORT` | `587` (STARTTLS) or `465` (SMTPS) |
| `SMTP_USER` | your SMTP username |
| `SMTP_PASS` | your SMTP password / app password |
| `SMTP_FROM` | the "from" address, e.g. `noreply@sggs.ac.in` |
| `SMTP_FROM_NAME` | `EESA SGGS` |
| `COLLEGE_DOMAIN` | `sggs.ac.in` |
| `CONTACT_EMAIL_EESA` | `eesa@sggs.ac.in` |
| `CONTACT_EMAIL_HOD` | `head.ee@sggs.ac.in` |

For `SMTP_*`, a Gmail account with an **App Password** works fine for
low volume (`smtp.gmail.com`, port 587, your Gmail address as `SMTP_USER`,
the 16-character app password as `SMTP_PASS`). For real production volume,
use a transactional provider (SendGrid, Mailgun, Brevo) instead.

### Step 5 — Add a persistent disk for uploads
Uploaded images/CSVs/question papers must survive redeploys. In the service's
**Disks** tab, add a disk mounted at `/var/www/html/uploads` (this is already
declared in `render.yaml` if you used the Blueprint — just confirm it's
attached).

### Step 6 — Deploy
Click **Create Web Service**. Render builds the Docker image (installs the
PDO MySQL extension and PHPMailer via Composer) and starts it. Once it's
live, open the Render-provided URL — you should see the EESA home page.

### Step 7 — Set the real admin password
The seeded `superadmin` account has a placeholder password hash. Generate a
real one and update it directly in your MySQL database:
```php
<?php echo password_hash('YourStrongPassword', PASSWORD_DEFAULT);
```
```sql
UPDATE users SET password_hash='<paste the hash>' WHERE username='superadmin';
```
Then sign in at `https://<your-render-url>/login.php`.

### Step 8 — Custom domain (optional)
Render → your service → **Settings → Custom Domains** → add your domain and
follow the DNS instructions (a CNAME to Render, or A/ALIAS depending on your
DNS provider). Update `BASE_URL` in environment variables if the site isn't
hosted at the domain root.

## 9. Extending
- The activity/announcement editor fields are currently plain textareas
  storing HTML. Swap in a WYSIWYG (TinyMCE/Quill) if admins want rich text
  without hand-writing HTML — the DB and rendering already expect HTML.
- The shareable "poster card" on activity pages downloads as a PNG via
  html2canvas (CDN-loaded) — no server-side image generation needed.
