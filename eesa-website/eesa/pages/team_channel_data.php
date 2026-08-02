<?php
require_once __DIR__ . '/../config.php';
require_role(TEAM_CHANNEL_ROLES);
header('Content-Type: application/json');

$me = current_user();
$action = $_REQUEST['action'] ?? 'list';

function out($data) { echo json_encode($data); exit; }

if ($action === 'post' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $text = trim($_POST['message'] ?? '');
    if ($text === '') out(['ok' => false, 'error' => 'Empty message']);
    $pdo->prepare('INSERT INTO team_messages (user_id, message) VALUES (?, ?)')
        ->execute([$me['id'], $text]);
    out(['ok' => true]);
}

if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)($_POST['message_id'] ?? 0);
    if (has_role(['super_admin'])) {
        $pdo->prepare('DELETE FROM team_messages WHERE id = ?')->execute([$id]);
    } else {
        $pdo->prepare('DELETE FROM team_messages WHERE id = ? AND user_id = ?')->execute([$id, $me['id']]);
    }
    out(['ok' => true]);
}

// default: list messages (oldest first, for chat rendering)
$since = (int)($_GET['since_id'] ?? 0);
if ($since > 0) {
    $stmt = $pdo->prepare(
        'SELECT tm.*, u.full_name, u.role FROM team_messages tm
         JOIN users u ON u.id = tm.user_id
         WHERE tm.id > ? ORDER BY tm.id ASC LIMIT 200'
    );
    $stmt->execute([$since]);
} else {
    $stmt = $pdo->query(
        'SELECT tm.*, u.full_name, u.role FROM team_messages tm
         JOIN users u ON u.id = tm.user_id
         ORDER BY tm.id DESC LIMIT 100'
    );
}
$rows = $stmt->fetchAll();
if ($since === 0) $rows = array_reverse($rows); // chronological order

$out = array_map(function ($m) use ($me) {
    return [
        'id' => (int)$m['id'],
        'user_id' => (int)$m['user_id'],
        'full_name' => $m['full_name'],
        'role' => role_label($m['role']),
        'message' => $m['message'],
        'time' => time_ago($m['created_at']),
        'mine' => ((int)$m['user_id'] === (int)$me['id']),
        'can_delete' => ((int)$m['user_id'] === (int)$me['id']) || has_role(['super_admin']),
    ];
}, $rows);

out(['ok' => true, 'messages' => $out]);
