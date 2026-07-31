<?php
require_once __DIR__ . '/../config.php';
require_role(['super_admin']);
$pageTitle = 'Audit Log';
$activeSection = 'audit';

$log = $pdo->query('SELECT al.*, u.username FROM audit_log al LEFT JOIN users u ON u.id = al.user_id
                     ORDER BY al.created_at DESC LIMIT 300')->fetchAll();
require __DIR__ . '/layout_header.php';
?>
<h1>Audit Log</h1>
<p class="muted">Every privileged action taken across the site, most recent first.</p>
<table class="admin-table">
  <tr><th>When</th><th>User</th><th>Action</th><th>Details</th></tr>
  <?php foreach ($log as $l): ?>
    <tr>
      <td class="mono" style="font-size:12px"><?= h(date('d M Y, h:i A', strtotime($l['created_at']))) ?></td>
      <td class="mono"><?= h($l['username'] ?? '—') ?></td>
      <td><?= h($l['action']) ?></td>
      <td class="muted"><?= h($l['details']) ?></td>
    </tr>
  <?php endforeach; ?>
</table>
<?php require __DIR__ . '/layout_footer.php'; ?>
