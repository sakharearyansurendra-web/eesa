<?php
require_once __DIR__ . '/../config.php';
require_role(['super_admin','admin','aptitude_manager','member']);
$pageTitle = 'Dashboard';
$activeSection = 'dashboard';

$u = current_user();
$stats = [];
if (has_role(['super_admin','admin'])) {
    $stats['Announcements'] = $pdo->query('SELECT COUNT(*) FROM announcements')->fetchColumn();
    $stats['Activities'] = $pdo->query('SELECT COUNT(*) FROM activities')->fetchColumn();
    $stats['Gallery Events'] = $pdo->query('SELECT COUNT(*) FROM gallery_events')->fetchColumn();
    $stats['Unread Messages'] = $pdo->query('SELECT COUNT(*) FROM contact_messages WHERE is_read = 0')->fetchColumn();
}
if (has_role(['super_admin'])) {
    $stats['Pending Join Requests'] = $pdo->query('SELECT COUNT(*) FROM users WHERE status=\'pending\'')->fetchColumn();
}
if (has_role(['super_admin','admin','aptitude_manager'])) {
    $stats['Aptitude Tests'] = $pdo->query('SELECT COUNT(*) FROM aptitude_tests')->fetchColumn();
}

require __DIR__ . '/layout_header.php';
?>
<h1>Welcome, <?= h($u['full_name']) ?></h1>
<p class="muted">Role: <span class="mono"><?= h($u['role']) ?></span></p>

<div class="grid grid-4" style="margin-top:20px">
  <?php foreach ($stats as $label => $val): ?>
    <div class="card">
      <div class="meta"><?= h($label) ?></div>
      <h2 style="margin:0"><?= (int)$val ?></h2>
    </div>
  <?php endforeach; ?>
</div>

<?php if (has_role(['super_admin']) && ($stats['Pending Join Requests'] ?? 0) > 0): ?>
  <div class="alert alert-ok" style="margin-top:24px;max-width:520px">
    You have <?= (int)$stats['Pending Join Requests'] ?> pending membership request(s).
    <a href="<?= BASE_URL ?>/admin/users.php" style="color:var(--copper-lt);font-weight:600">Review now &rarr;</a>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/layout_footer.php'; ?>
