<?php
require_once __DIR__ . '/../config.php';
require_role(CONTENT_ADMIN_ROLES);
$pageTitle = 'Certificate Verification Reports';
$activeSection = 'certificate_reports';
$msg = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resolve_report'])) {
    csrf_check();
    $pdo->prepare("UPDATE certificate_verification_reports SET status='resolved' WHERE id=?")->execute([(int)$_POST['id']]);
    $msg = 'Marked resolved.';
}

$open = $pdo->query("SELECT * FROM certificate_verification_reports WHERE status='open' ORDER BY created_at DESC")->fetchAll();
$resolved = $pdo->query("SELECT * FROM certificate_verification_reports WHERE status='resolved' ORDER BY created_at DESC LIMIT 30")->fetchAll();
require __DIR__ . '/layout_header.php';
?>
<h1>Certificate Verification Reports</h1>
<?php if ($msg): ?><div class="alert alert-ok"><?= h($msg) ?></div><?php endif; ?>

<h3>Open (<?= count($open) ?>)</h3>
<?php if (!$open): ?><p class="muted">Nothing to review.</p><?php endif; ?>
<?php foreach ($open as $r): ?>
  <div class="card" style="margin-bottom:12px">
    <p class="mono muted" style="font-size:12px">Reported <?= h(time_ago($r['created_at'])) ?></p>
    <p><strong>Certificate:</strong> <span class="mono"><?= h($r['certificate_no']) ?></span></p>
    <p><strong>From:</strong> <?= h($r['reporter_name'] ?: '—') ?> (<?= h($r['reporter_email']) ?>)</p>
    <p><?= nl2br(h($r['message'])) ?></p>
    <form method="POST"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
      <button class="btn btn-outline btn-sm" type="submit" name="resolve_report">Mark Resolved</button></form>
  </div>
<?php endforeach; ?>

<h3 style="margin-top:28px">Recently Resolved</h3>
<table class="admin-table">
  <tr><th>Certificate</th><th>Reporter</th><th>Reported</th></tr>
  <?php foreach ($resolved as $r): ?>
    <tr><td class="mono"><?= h($r['certificate_no']) ?></td><td><?= h($r['reporter_email']) ?></td><td class="muted mono" style="font-size:12px"><?= h(time_ago($r['created_at'])) ?></td></tr>
  <?php endforeach; ?>
</table>
<?php require __DIR__ . '/layout_footer.php'; ?>
