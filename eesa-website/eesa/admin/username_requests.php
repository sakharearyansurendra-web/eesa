<?php
require_once __DIR__ . '/../config.php';
require_role(['super_admin']);
$pageTitle = 'Username Change Requests';
$activeSection = 'username_requests';
$msg = null; $err = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resolve_username_request'])) {
    csrf_check();
    $reqId = (int)$_POST['request_id'];
    $decision = $_POST['decision'] === 'approve' ? 'approved' : 'rejected';
    $stmt = $pdo->prepare('SELECT * FROM username_requests WHERE id = ? AND status = ? LIMIT 1');
    $stmt->execute([$reqId, 'pending']);
    $reqRow = $stmt->fetch();
    if (!$reqRow) {
        $err = 'Request not found or already resolved.';
    } else {
        if ($decision === 'approved') {
            $taken = $pdo->prepare('SELECT id FROM users WHERE username = ? AND id != ?');
            $taken->execute([$reqRow['requested_username'], $reqRow['user_id']]);
            if ($taken->fetch()) {
                $err = 'That username was taken in the meantime — reject or ask the member to pick another.';
            } else {
                $pdo->prepare('UPDATE users SET username = ? WHERE id = ?')->execute([$reqRow['requested_username'], $reqRow['user_id']]);
            }
        }
        if (!$err) {
            $pdo->prepare('UPDATE username_requests SET status = ?, resolved_at = NOW(), resolved_by = ? WHERE id = ?')
                ->execute([$decision, current_user()['id'], $reqId]);
            audit($pdo, 'resolve_username_request', "#$reqId -> $decision");
            $msg = 'Request ' . $decision . '.';
        }
    }
}

$pending = $pdo->query("SELECT ur.*, u.full_name
                         FROM username_requests ur JOIN users u ON u.id = ur.user_id
                         WHERE ur.status = 'pending' ORDER BY ur.created_at DESC")->fetchAll();

$resolved = $pdo->query("SELECT ur.*, u.full_name, r.full_name AS resolver_name
                          FROM username_requests ur
                          JOIN users u ON u.id = ur.user_id
                          LEFT JOIN users r ON r.id = ur.resolved_by
                          WHERE ur.status != 'pending' ORDER BY ur.resolved_at DESC LIMIT 30")->fetchAll();

require __DIR__ . '/layout_header.php';
?>
<h1>Username Change Requests</h1>
<p class="muted">Members request a new username from My Account — nothing changes until approved here.</p>

<?php if ($msg): ?><div class="alert alert-ok"><?= h($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-err"><?= h($err) ?></div><?php endif; ?>

<h3>Pending (<?= count($pending) ?>)</h3>
<?php if (!$pending): ?><p class="muted">No pending username change requests.</p><?php endif; ?>
<?php foreach ($pending as $r): ?>
  <div class="card" style="margin-bottom:12px">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
      <div>
        <strong><?= h($r['full_name']) ?></strong>
        <p class="mono muted" style="font-size:13px;margin:2px 0 0"><?= h($r['current_username']) ?> &rarr; <?= h($r['requested_username']) ?></p>
        <p class="muted" style="font-size:12px">Requested <?= h(time_ago($r['created_at'])) ?></p>
      </div>
      <form method="POST" style="display:flex;gap:8px">
        <?= csrf_field() ?>
        <input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>">
        <button class="btn btn-primary btn-sm" type="submit" name="resolve_username_request" onclick="this.form.decision.value='approve'">Approve</button>
        <button class="btn btn-danger btn-sm" type="submit" name="resolve_username_request" onclick="this.form.decision.value='reject'">Reject</button>
        <input type="hidden" name="decision" value="approve">
      </form>
    </div>
  </div>
<?php endforeach; ?>

<h3 style="margin-top:28px">Recently Resolved</h3>
<?php if (!$resolved): ?><p class="muted">No resolved requests yet.</p><?php endif; ?>
<table class="admin-table">
  <tr><th>Member</th><th>Change</th><th>Status</th><th>Approved By</th><th>Resolved</th></tr>
  <?php foreach ($resolved as $r): ?>
    <tr>
      <td><?= h($r['full_name']) ?></td>
      <td class="mono"><?= h($r['current_username']) ?> &rarr; <?= h($r['requested_username']) ?></td>
      <td><span class="pill pill-<?= h($r['status']) ?>"><?= h($r['status']) ?></span></td>
      <td class="muted"><?= h($r['resolver_name'] ?? '—') ?></td>
      <td class="muted mono" style="font-size:12px"><?= $r['resolved_at'] ? h(time_ago($r['resolved_at'])) : '—' ?></td>
    </tr>
  <?php endforeach; ?>
</table>
<?php require __DIR__ . '/layout_footer.php'; ?>
