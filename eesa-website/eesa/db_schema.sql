-- =====================================================================
-- EESA Website Database Schema
-- Run once against an empty database (e.g. `mysql -u root -p eesa_db < db_schema.sql`)
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------
-- USERS & ACCESS LEVELS
-- role determines what an approved user can do. Roles map to EESA's real
-- organizational hierarchy (see the EESA Constitution) with three
-- permission tiers underneath:
--   TIER 1 (super_admin)        -> everything, incl. user approval/roles and audit log
--   TIER 2 (content management) -> president, secretary, csd, media_head, prm, admin
--   TIER 3 (results only)       -> aptitude_manager
--   TIER 4 (no back-office)     -> treasurer, joint_coordinator, member
-- status now tracks a multi-stage verification pipeline for join requests:
--   pending -> verifier1_approved (Secretary) -> verifier2_approved (President)
--   -> approved (Super Admin finalizes: assigns username + role)
-- Super Admin can also finalize directly from any stage, skipping ahead.
-- Rejection can happen at any stage.
-- Profile fields (phone/address/bio) are self-editable via pages/account.php.
-- ---------------------------------------------------------------
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(120) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  username VARCHAR(50) UNIQUE DEFAULT NULL,       -- assigned on final approval
  password_hash VARCHAR(255) DEFAULT NULL,          -- set on final approval
  role ENUM('super_admin','admin','president','secretary','treasurer','csd','media_head','prm','joint_coordinator','aptitude_manager','member') NOT NULL DEFAULT 'member',
  status ENUM('pending','verifier1_approved','verifier2_approved','approved','rejected','suspended') NOT NULL DEFAULT 'pending',
  ticket_id VARCHAR(20) UNIQUE DEFAULT NULL,        -- shown to applicant at join time
  email_verified TINYINT(1) NOT NULL DEFAULT 0,
  branch_year VARCHAR(30) DEFAULT NULL,
  phone VARCHAR(20) DEFAULT NULL,
  address VARCHAR(255) DEFAULT NULL,
  bio VARCHAR(500) DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  verifier1_by INT DEFAULT NULL,
  verifier1_at DATETIME DEFAULT NULL,
  verifier2_by INT DEFAULT NULL,
  verifier2_at DATETIME DEFAULT NULL,
  approved_at DATETIME DEFAULT NULL,
  approved_by INT DEFAULT NULL,
  FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (verifier1_by) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (verifier2_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Team-only communication channel — visible/postable only by approved
-- non-"member" roles (joint_coordinator and above). Plain members and the
-- public never see this.
CREATE TABLE team_messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  message TEXT NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Self-service username change requests — password changes are self-service
-- and instant (see pages/account.php), but username changes always go
-- through admin review/approval here before taking effect.
CREATE TABLE username_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  current_username VARCHAR(50) NOT NULL,
  requested_username VARCHAR(50) NOT NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  resolved_at DATETIME DEFAULT NULL,
  resolved_by INT DEFAULT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Email verification / join OTPs and general purpose OTPs (aptitude lookup too)
CREATE TABLE otp_codes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  purpose ENUM('join_verify','aptitude_lookup','password_reset') NOT NULL,
  email VARCHAR(150) NOT NULL,
  reference VARCHAR(100) DEFAULT NULL,   -- e.g. reg_no for aptitude lookups
  otp_hash VARCHAR(255) NOT NULL,
  expires_at DATETIME NOT NULL,
  consumed TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ---------------------------------------------------------------
-- ACTIVITIES (blog-style posts): webinar / seminar / workshop / wall magazine / celebration / event ...
-- ---------------------------------------------------------------
CREATE TABLE activity_categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(40) NOT NULL UNIQUE,
  label VARCHAR(60) NOT NULL
);
INSERT INTO activity_categories (slug, label) VALUES
 ('webinar','Webinar'), ('seminar','Seminar'), ('workshop','Workshop'),
 ('wall_magazine','Wall Magazine'), ('celebration','Celebration'), ('event','Event');

CREATE TABLE activities (
  id INT AUTO_INCREMENT PRIMARY KEY,
  category_id INT NOT NULL,
  title VARCHAR(200) NOT NULL,
  slug VARCHAR(220) NOT NULL UNIQUE,
  summary VARCHAR(300) DEFAULT NULL,       -- shown on card / poster
  content MEDIUMTEXT NOT NULL,             -- full blog body (HTML from admin editor)
  cover_image VARCHAR(255) DEFAULT NULL,
  event_date DATE DEFAULT NULL,
  status ENUM('draft','published') NOT NULL DEFAULT 'published',
  created_by INT DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (category_id) REFERENCES activity_categories(id),
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE activity_photos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  activity_id INT NOT NULL,
  filename VARCHAR(255) NOT NULL,
  caption VARCHAR(200) DEFAULT NULL,
  sort_order INT DEFAULT 0,
  FOREIGN KEY (activity_id) REFERENCES activities(id) ON DELETE CASCADE
);

-- ---------------------------------------------------------------
-- ANNOUNCEMENTS (also drives upcoming-event registration + auto status)
-- status is computed at read-time from event_datetime/registration_close,
-- but we also store a cached value updated by a small cron/heartbeat.
-- ---------------------------------------------------------------
CREATE TABLE announcements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(200) NOT NULL,
  slug VARCHAR(220) NOT NULL UNIQUE,
  body MEDIUMTEXT NOT NULL,
  cover_image VARCHAR(255) DEFAULT NULL,
  event_datetime DATETIME DEFAULT NULL,        -- NULL = plain notice, no registration
  registration_open TINYINT(1) NOT NULL DEFAULT 0,
  registration_close DATETIME DEFAULT NULL,
  venue VARCHAR(150) DEFAULT NULL,
  computed_status ENUM('upcoming','ongoing','completed','notice') NOT NULL DEFAULT 'notice',
  created_by INT DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE announcement_registrations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  announcement_id INT NOT NULL,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(150) NOT NULL,
  phone VARCHAR(20) DEFAULT NULL,
  branch_year VARCHAR(40) DEFAULT NULL,
  registered_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (announcement_id) REFERENCES announcements(id) ON DELETE CASCADE,
  UNIQUE (announcement_id, email)
);

-- ---------------------------------------------------------------
-- TEAM (grouped by academic year, with a group photo per year)
-- ---------------------------------------------------------------
CREATE TABLE team_years (
  id INT AUTO_INCREMENT PRIMARY KEY,
  year_label VARCHAR(20) NOT NULL UNIQUE,     -- e.g. '2025-26'
  group_photo VARCHAR(255) DEFAULT NULL,
  sort_order INT DEFAULT 0
);

CREATE TABLE team_members (
  id INT AUTO_INCREMENT PRIMARY KEY,
  team_year_id INT NOT NULL,
  user_id INT DEFAULT NULL,                    -- links to a real account so teammates can see contact info privately
  name VARCHAR(120) NOT NULL,
  designation VARCHAR(100) NOT NULL,          -- President, Vice President, Faculty Advisor, etc.
  photo VARCHAR(255) DEFAULT NULL,
  linkedin_url VARCHAR(255) DEFAULT NULL,
  sort_order INT DEFAULT 0,
  FOREIGN KEY (team_year_id) REFERENCES team_years(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- ---------------------------------------------------------------
-- DEPARTMENT PAGE
-- ---------------------------------------------------------------
CREATE TABLE department_info (
  id INT PRIMARY KEY DEFAULT 1,
  about MEDIUMTEXT,
  hod_name VARCHAR(120),
  hod_message MEDIUMTEXT,
  hod_photo VARCHAR(255)
);
INSERT INTO department_info (id, about, hod_name, hod_message) VALUES
 (1, 'About the Department of Electrical Engineering, SGGS Institute of Engineering & Technology.', 'HOD Name', 'Welcome message from the HOD.');

CREATE TABLE department_images (
  id INT AUTO_INCREMENT PRIMARY KEY,
  filename VARCHAR(255) NOT NULL,
  caption VARCHAR(200) DEFAULT NULL,
  sort_order INT DEFAULT 0
);

CREATE TABLE staff (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  designation VARCHAR(100) NOT NULL,
  photo VARCHAR(255) DEFAULT NULL,
  email VARCHAR(150) DEFAULT NULL,
  sort_order INT DEFAULT 0
);

-- ---------------------------------------------------------------
-- GALLERY (grouped into events, each with a date)
-- ---------------------------------------------------------------
CREATE TABLE gallery_events (
  id INT AUTO_INCREMENT PRIMARY KEY,
  event_name VARCHAR(200) NOT NULL,
  event_date DATE NOT NULL,
  cover_image VARCHAR(255) DEFAULT NULL
);

CREATE TABLE gallery_photos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  gallery_event_id INT NOT NULL,
  filename VARCHAR(255) NOT NULL,
  caption VARCHAR(200) DEFAULT NULL,
  sort_order INT DEFAULT 0,
  FOREIGN KEY (gallery_event_id) REFERENCES gallery_events(id) ON DELETE CASCADE
);

-- ---------------------------------------------------------------
-- APTITUDE RESULTS (OTP-gated lookup by registration number)
-- reg_no + COLLEGE_DOMAIN forms the mail address the OTP is sent to.
-- ---------------------------------------------------------------
CREATE TABLE aptitude_tests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  test_name VARCHAR(150) NOT NULL,
  test_date DATE NOT NULL,
  question_paper_file VARCHAR(255) DEFAULT NULL
);

CREATE TABLE aptitude_results (
  id INT AUTO_INCREMENT PRIMARY KEY,
  aptitude_test_id INT NOT NULL,
  reg_no VARCHAR(50) NOT NULL,
  score DECIMAL(6,2) DEFAULT NULL,
  status VARCHAR(40) DEFAULT NULL,          -- Qualified / Not Qualified / Shortlisted etc.
  remarks VARCHAR(255) DEFAULT NULL,
  FOREIGN KEY (aptitude_test_id) REFERENCES aptitude_tests(id) ON DELETE CASCADE,
  UNIQUE (aptitude_test_id, reg_no)
);

-- ---------------------------------------------------------------
-- CONTACT MESSAGES
-- ---------------------------------------------------------------
CREATE TABLE contact_messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(150) NOT NULL,
  message TEXT NOT NULL,
  sent_to ENUM('eesa','hod') NOT NULL DEFAULT 'eesa',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  is_read TINYINT(1) NOT NULL DEFAULT 0
);

-- ---------------------------------------------------------------
-- AUDIT LOG (admin actions -- who deleted / approved / edited what)
-- ---------------------------------------------------------------
CREATE TABLE audit_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT DEFAULT NULL,
  action VARCHAR(100) NOT NULL,
  details VARCHAR(500) DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- ---------------------------------------------------------------
-- SITE SETTINGS (admin-editable site-wide text: hero copy, footer tagline,
-- etc.) — a simple key/value store so content changes don't require touching
-- code. Rows are seeded with the current defaults; admins edit them from
-- /admin/settings.php.
-- ---------------------------------------------------------------
CREATE TABLE site_settings (
  setting_key VARCHAR(64) PRIMARY KEY,
  setting_value MEDIUMTEXT
);
INSERT INTO site_settings (setting_key, setting_value) VALUES
 ('hero_eyebrow', 'Electrical Engineering Student Association · SGGS'),
 ('hero_title', 'Welcome to EESA'),
 ('hero_lead', 'By the students, for the students. EESA is powering the next generation of electrical engineers — through workshops, seminars, projects and a community that learns together.'),
 ('footer_tagline', 'EESA — Dept. of Electrical Engineering, SGGS. By the students, for the students.');

-- ---------------------------------------------------------------
-- Seed one super_admin so the hidden login page works out of the box.
-- Username: superadmin   Password: ChangeMe@123  (CHANGE THIS IMMEDIATELY)
-- Hash below = password_hash('ChangeMe@123', PASSWORD_DEFAULT) example; regenerate your own.
-- ---------------------------------------------------------------
INSERT INTO users (full_name, email, username, password_hash, role, status, email_verified, approved_at)
VALUES ('Super Admin', 'superadmin@sggs.ac.in', 'superadmin',
 '$2y$10$examplehashexamplehashexamplehashexamplehash1234567',
 'super_admin', 'approved', 1, NOW());
