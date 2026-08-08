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
const CONTENT_ADMIN_ROLES = ['super_admin', 'admin', 'president', 'secretary', 'csd', 'media_head', 'prm', 'hod', 'faculty_coordinator'];
const APTITUDE_ROLES = ['super_admin', 'admin', 'aptitude_manager'];
const ALL_ROLES = ['super_admin', 'admin', 'president', 'secretary', 'treasurer', 'csd', 'media_head', 'prm', 'joint_coordinator', 'aptitude_manager', 'hod', 'faculty_coordinator', 'member', 'alumni'];
const ASSIGNABLE_ROLES = ['member', 'alumni', 'joint_coordinator', 'treasurer', 'csd', 'media_head', 'prm', 'secretary', 'president', 'aptitude_manager', 'admin', 'hod', 'faculty_coordinator']; // super_admin excluded — promoted separately, never via approval/role-change forms
// Department resource management (labs, classrooms, equipment, and the
// faculty roster itself) — deliberately narrower than CONTENT_ADMIN_ROLES.
// Faculty members can still edit their OWN profile content regardless of
// role — that check happens in admin/faculty_edit.php, not here.
const DEPT_RESOURCE_ROLES = ['super_admin', 'admin', 'secretary', 'president'];
// Read-only access to the Users & Access accounts list + CSV export.
// Everyone here can VIEW and DOWNLOAD the account roster; none of them
// (except super_admin, checked separately) can edit roles, suspend,
// delete, or approve/reject anything on that page.
const ACCOUNTS_VIEW_ROLES = ['super_admin', 'admin', 'secretary', 'president', 'hod', 'faculty_coordinator'];

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
        'super_admin'         => 'Super Admin',
        'admin'                => 'Admin',
        'president'            => 'President',
        'secretary'            => 'Secretary',
        'treasurer'            => 'Treasurer',
        'csd'                  => 'Club Service Director',
        'media_head'           => 'Media Head',
        'prm'                  => 'Public Relations Manager',
        'joint_coordinator'    => 'Joint Coordinator',
        'aptitude_manager'     => 'Aptitude Manager',
        'hod'                  => 'Head of Department',
        'faculty_coordinator'  => 'Faculty Coordinator',
        'member'               => 'Member',
        'alumni'               => 'Alumni',
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
    global $pdo;
    if (!isset($_SESSION['user'])) return null;

    // Defend against a stale session referencing a deleted account (e.g.
    // after a bulk user cleanup) — confirm the id still exists, once per
    // request, and drop the session if it doesn't.
    static $checked = false;
    if (!$checked) {
        $checked = true;
        $stmt = $pdo->prepare('SELECT id FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$_SESSION['user']['id']]);
        if (!$stmt->fetch()) {
            unset($_SESSION['user']);
            return null;
        }
    }
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
/** Alias used by pages (like admin/user_view.php) that want the same
 *  read/edit access as the Users & Access roster. */
function require_admin_login() {
    require_role(ACCOUNTS_VIEW_ROLES);
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
/**
 * Member ID format: EESA-<startYY>-<endYY>-<CODE>
 *   startYY/endYY = the two years of the academic term the ID was
 *   generated in (term assumed to start in June/July, IST) — e.g. an
 *   approval in Aug 2026 -> "26-27"; one in March 2027 (still same
 *   session) -> also "26-27"; one in July 2027 -> "27-28".
 *   CODE = 5 random alphanumeric characters (not sequential), so IDs
 *   don't reveal approval order or headcount.
 */
function generate_member_id($userId) {
    global $pdo;

    $now = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
    $y = (int)$now->format('Y');
    $m = (int)$now->format('n');
    // Academic term starts in June (month 6). Before that, we're still
    // in the term that started the previous June.
    $startY = $m >= 6 ? $y : $y - 1;
    $endY = $startY + 1;
    $termPart = substr((string)$startY, 2, 2) . '-' . substr((string)$endY, 2, 2);

    // Unambiguous alphanumeric set — no 0/O or 1/I confusion.
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    do {
        $code = '';
        for ($i = 0; $i < 5; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        $candidate = "EESA-$termPart-$code";
        $stmt = $pdo->prepare('SELECT id FROM users WHERE member_id = ? LIMIT 1');
        $stmt->execute([$candidate]);
    } while ($stmt->fetch()); // extremely unlikely, but guarantees uniqueness

    return $candidate;
}

function generate_otp() {
    return (string)random_int(100000, 999999);
}

function audit(PDO $pdo, $action, $details = null) {
    $u = current_user();
    $userId = $u['id'] ?? null;
    try {
        $stmt = $pdo->prepare('INSERT INTO audit_log (user_id, action, details) VALUES (?,?,?)');
        $stmt->execute([$userId, $action, $details]);
    } catch (PDOException $e) {
        // Stale session referencing a user that's since been deleted
        // (e.g. after a bulk account cleanup) — log the action without
        // the dangling reference instead of taking the whole page down.
        if ((int)$e->getCode() === 23000) {
            $stmt = $pdo->prepare('INSERT INTO audit_log (user_id, action, details) VALUES (NULL,?,?)');
            $stmt->execute([$action, $details]);
        } else {
            throw $e;
        }
    }
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
function generate_certificate_no() {
    global $pdo;
    $year = date('Y');
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    do {
        $code = '';
        for ($i = 0; $i < 6; $i++) $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        $candidate = "EESA-CERT-$year-$code";
        $stmt = $pdo->prepare('SELECT id FROM certificates WHERE certificate_no = ? LIMIT 1');
        $stmt->execute([$candidate]);
    } while ($stmt->fetch());
    return $candidate;
}

/** Accepts a full YouTube URL (watch/embed/shorts/youtu.be) or a bare 11-char video ID. */
function youtube_id_from_url($url) {
    $url = trim($url);
    if (preg_match('~^[a-zA-Z0-9_-]{11}$~', $url)) return $url;
    if (preg_match('~(?:youtu\.be/|youtube\.com/(?:watch\?v=|embed/|shorts/))([a-zA-Z0-9_-]{11})~', $url, $m)) {
        return $m[1];
    }
    return null;
}
