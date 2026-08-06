<?php
/**
 * config.php
 * Central configuration: DB connection, session, timezone.
 * Edit the CONFIG block below for your server.
 */

// ---------------- CONFIG ----------------
// Every value below reads from an environment variable first (set these in
// Render's dashboard) and falls back to a local default for XAMPP/localhost
// testing. Never commit real production secrets into this file.
function env_or($key, $default) {
    $v = getenv($key);
    return ($v !== false && $v !== '') ? $v : $default;
}

define('DB_HOST', env_or('DB_HOST', 'localhost'));
define('DB_NAME', env_or('DB_NAME', 'eesa_db'));
define('DB_USER', env_or('DB_USER', 'eesa_user'));
define('DB_PASS', env_or('DB_PASS', 'change_me'));
define('DB_PORT', env_or('DB_PORT', '3306'));
// Some managed MySQL providers (Aiven, PlanetScale, etc.) require SSL.
// Set DB_SSL_CA to the absolute path of a CA cert file to enable it;
// leave unset for local/XAMPP MySQL, which normally has no SSL configured.
define('DB_SSL_CA', env_or('DB_SSL_CA', ''));
// Set DB_SSL_VERIFY=false to keep the connection encrypted but skip strict
// certificate-chain validation — useful when mysqlnd is picky about a
// provider's cert chain. Fine for testing; leave true for production once
// it's confirmed working.
define('DB_SSL_VERIFY', strtolower(env_or('DB_SSL_VERIFY', 'true')) !== 'false');

// Site
define('SITE_NAME', 'EESA');
define('SITE_TAGLINE', 'By the Students, For the Students');
define('COLLEGE_DOMAIN', env_or('COLLEGE_DOMAIN', 'sggs.ac.in'));   // reg_no@sggs.ac.in for OTP mail
define('CONTACT_EMAIL_EESA', env_or('CONTACT_EMAIL_EESA', 'eesa@sggs.ac.in'));
define('CONTACT_EMAIL_HOD', env_or('CONTACT_EMAIL_HOD', 'head.ee@sggs.ac.in'));

// Mail (used by includes/mailer.php). Render blocks plain PHP mail() sending
// (no local MTA + most outbound SMTP ports throttled), so SMTP is required
// in production — see the Render deployment guide in README.md.
define('SMTP_HOST', env_or('SMTP_HOST', 'smtp.yourprovider.com'));
define('SMTP_PORT', (int)env_or('SMTP_PORT', '587'));
define('SMTP_USER', env_or('SMTP_USER', 'noreply@sggs.ac.in'));
define('SMTP_PASS', env_or('SMTP_PASS', 'change_me'));
define('SMTP_FROM', env_or('SMTP_FROM', 'noreply@sggs.ac.in'));
define('SMTP_FROM_NAME', env_or('SMTP_FROM_NAME', 'EESA SGGS'));

// Base path (used to build links/uploads). Leave '' if hosted at domain root.
define('BASE_URL', env_or('BASE_URL', ''));

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
    $pdoOptions = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    // Enable SSL when a CA cert path is provided (required by Aiven and
    // similar managed MySQL hosts).
    if (DB_SSL_CA !== '') {
        $pdoOptions[PDO::MYSQL_ATTR_SSL_CA] = DB_SSL_CA;
        $pdoOptions[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = DB_SSL_VERIFY;
    }
$pdo = new PDO(
        "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        $pdoOptions
    );
    // Force this connection's session timezone to IST too, so MySQL's
    // NOW()/CURRENT_TIMESTAMP agree with PHP's date_default_timezone_set()
    // above — otherwise created_at/approved_at/audit_log timestamps can be
    // off by the host's UTC offset even though everything displayed on the
    // site claims to be IST. Using a fixed offset instead of 'Asia/Kolkata'
    // since not every MySQL host has the named-timezone tables loaded.
    $pdo->exec("SET time_zone = '+05:30'");
} catch (Exception $e) {
    http_response_code(500);
    $certInfo = DB_SSL_CA !== ''
        ? ' | DB_SSL_CA=' . DB_SSL_CA . ' (exists: ' . (file_exists(DB_SSL_CA) ? 'yes' : 'NO — file not found') . ')'
        : ' | DB_SSL_CA not set';
    die('Database connection failed. Check config.php credentials. (' . htmlspecialchars($e->getMessage()) . ')' . htmlspecialchars($certInfo));
}

require_once __DIR__ . '/includes/functions.php';
