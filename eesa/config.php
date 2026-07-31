<?php
/**
 * config.php
 * Central configuration: DB connection, session, timezone.
 * Edit the CONFIG block below for your server.
 */

// ---------------- CONFIG ----------------
define('DB_HOST', 'localhost');
define('DB_NAME', 'eesa_db');
define('DB_USER', 'eesa_user');
define('DB_PASS', 'change_me');

// Site
define('SITE_NAME', 'EESA');
define('SITE_TAGLINE', 'By the Students, For the Students');
define('COLLEGE_DOMAIN', 'sggs.ac.in');           // used to build reg_no@sggs.ac.in for OTP mail
define('CONTACT_EMAIL_EESA', 'eesa@sggs.ac.in');
define('CONTACT_EMAIL_HOD', 'head.ee@sggs.ac.in');

// Mail (used by includes/mailer.php). Fill in real SMTP creds before going live.
define('SMTP_HOST', 'smtp.yourprovider.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'noreply@sggs.ac.in');
define('SMTP_PASS', 'change_me');
define('SMTP_FROM', 'noreply@sggs.ac.in');
define('SMTP_FROM_NAME', 'EESA SGGS');

// Base path (used to build links/uploads). Leave '' if hosted at domain root.
define('BASE_URL', '');

// ---------------- TIMEZONE ----------------
date_default_timezone_set('Asia/Kolkata'); // IST everywhere in the app

// ---------------- SESSION ----------------
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ---------------- DB ----------------
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (Exception $e) {
    http_response_code(500);
    die('Database connection failed. Check config.php credentials. (' . htmlspecialchars($e->getMessage()) . ')');
}

require_once __DIR__ . '/includes/functions.php';
