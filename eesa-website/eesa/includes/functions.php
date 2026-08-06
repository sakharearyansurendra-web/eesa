<?php
/**
 * includes/functions.php
 * Small helpers shared across every page. Kept framework-free on purpose.
 */

/**
 * Role tiers, matching EESA's constitution/organizational hierarchy.
 * Used everywhere access is checked, so the site's permission structure
 * stays in one place instead of being hardcoded per-page.
 */
const CONTENT_ADMIN_ROLES = ['super_admin', 'admin', 'president', 'secretary', 'csd', 'media_head', 'prm'];
const APTITUDE_ROLES = ['super_admin', 'admin', 'aptitude_manager'];
const ALL_ROLES = ['super_admin', 'admin', 'president', 'secretary', 'treasurer', 'csd', 'media_head', 'prm', 'joint_coordinator', 'aptitude_manager', 'member'];
const ASSIGNABLE_ROLES = ['member', 'joint_coordinator', 'treasurer', 'csd', 'media_head', 'prm', 'secretary', 'president', 'aptitude_manager', 'admin']; // super_admin excluded — promoted separately, never via approval/role-change forms
const TEAM_CHANNEL_ROLES = ['super_admin', 'admin', 'president', 'secretary', 'treasurer', 'csd', 'media_head', 'prm', 'joint_coordinator', 'aptitude_manager']; // every approved role except plain "member"

/** Human-readable label + badge class for a join request's pipeline stage. */
function join_status_label($status) {
    $map = [
        'pending'            => ['Stage 1 — awaiting Secretary review', 'badge-notice'],
        'verifier1_approved' => ['Stage 2 — awaiting President review', 'badge-notice'],
        'verifier2_approved' => ['Final stage — awaiting Super Admin approval', 'badge-ongoing'],
        'approved'           => ['Approved', 'badge-ongoing'],
        'rejected'           => ['Not approved', 'badge-completed'],
        'suspended'          => ['Suspended', 'badge-completed'],
    ];
    return $map[$status] ?? [$status, 'badge-notice'];
}

function role_label($role) {
    $labels = [
        'super_admin'       => 'Super Admin',
        'admin'             => 'Admin',
        'president'         => 'President',
        'secretary'         => 'Secretary',
        'treasurer'         => 'Treasurer',
        'csd'               => 'Club Service Director',
        'media_head'        => 'Media Head',
        'prm'               => 'Public Relations Manager',
        'joint_coordinator' => 'Joint Coordinator',
        'aptitude_manager'  => 'Aptitude Manager',
        'member'            => 'Member',
    ];
    return $labels[$role] ?? $role;
}

function h($s) {
    return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8');
}

function slugify($text) {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = trim($text, '-');
    $text = strtolower($text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    return $text ?: 'item-' . time();
}

function redirect($path) {
    header('Location: ' . BASE_URL . $path);
    exit;
}

function current_user() {
    return $_SESSION['user'] ?? null;
}

function is_logged_in() {
    return isset($_SESSION['user']);
}

function has_role(array $roles) {
    $u = current_user();
    return $u && in_array($u['role'], $roles, true);
}

/** Every back-office page must call this first. Hard-fails to the (hidden) login page. */
function require_role(array $roles) {
    if (!has_role($roles)) {
        redirect('/login.php?denied=1');
    }
}

function csrf_token() {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">';
}

function csrf_check() {
    if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'])) {
        http_response_code(400);
        die('Security check failed. Please go back and try again.');
    }
}

/** Compute an announcement's live status from its scheduled datetime, IST. */
function announcement_status($event_datetime, $registration_close) {
    if (!$event_datetime) return 'notice';
    $now = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
    $event = new DateTime($event_datetime, new DateTimeZone('Asia/Kolkata'));
    $end = $registration_close ? new DateTime($registration_close, new DateTimeZone('Asia/Kolkata')) : $event;
    if ($now > $end) return 'completed';
    if ($now >= $event && $now <= $end) return 'ongoing';
    return 'upcoming';
}

function status_badge($status) {
    $map = [
        'upcoming'  => ['Upcoming', 'badge-upcoming'],
        'ongoing'   => ['Ongoing', 'badge-ongoing'],
        'completed' => ['Completed', 'badge-completed'],
        'notice'    => ['Notice', 'badge-notice'],
    ];
    [$label, $class] = $map[$status] ?? ['Notice', 'badge-notice'];
    return '<span class="badge ' . $class . '">' . h($label) . '</span>';
}

function generate_ticket_id() {
    return 'EESA-' . date('y') . '-' . strtoupper(bin2hex(random_bytes(3)));
}
/** Deterministic, human-readable member ID assigned once, at final account approval. */
function generate_member_id($userId) {
    return 'EESA-' . date('Y') . '-' . str_pad($userId, 4, '0', STR_PAD_LEFT);
}

function generate_otp() {
    return (string)random_int(100000, 999999);
}

function audit(PDO $pdo, $action, $details = null) {
    $u = current_user();
    $stmt = $pdo->prepare('INSERT INTO audit_log (user_id, action, details) VALUES (?,?,?)');
    $stmt->execute([$u['id'] ?? null, $action, $details]);
}

/** Save an uploaded image safely into /uploads/<subdir>/, return filename or null. */
function save_upload($field, $subdir, array $allowed = ['jpg','jpeg','png','webp']) {
    if (empty($_FILES[$field]['tmp_name']) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) return null;
    $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) return null;
    $name = bin2hex(random_bytes(12)) . '.' . $ext;
    $dest = __DIR__ . '/../uploads/' . $subdir . '/' . $name;
    if (!is_dir(dirname($dest))) mkdir(dirname($dest), 0755, true);
    if (move_uploaded_file($_FILES[$field]['tmp_name'], $dest)) return $name;
    return null;
}

/**
 * Read an admin-editable site setting (see site_settings table / admin/settings.php).
 * Falls back to $default if the key isn't set or the table doesn't exist yet
 * (e.g. on a DB that hasn't run the migration below) — so this never breaks
 * page rendering even if settings haven't been seeded.
 */
function get_setting(PDO $pdo, $key, $default = '') {
    try {
        $stmt = $pdo->prepare('SELECT setting_value FROM site_settings WHERE setting_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        return ($val !== false && $val !== null && $val !== '') ? $val : $default;
    } catch (Exception $e) {
        return $default;
    }
}

function time_ago($datetime) {
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff/60) . 'm ago';
    if ($diff < 86400) return floor($diff/3600) . 'h ago';
    if ($diff < 604800) return floor($diff/86400) . 'd ago';
    return date('d M Y', strtotime($datetime));
}
