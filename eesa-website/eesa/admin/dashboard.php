<?php
require_once __DIR__ . '/../config.php';
require_role(ALL_ROLES);
$pageTitle = 'Dashboard';
$activeSection = 'dashboard';

$u = current_user();
$stats = [];
if (has_role(CONTENT_ADMIN_ROLES)) {
    $stats['Announcements'] = $pdo->query('SELECT COUNT(*) FROM announcements')->fetchColumn();
    $stats['Activities'] = $pdo->query('SELECT COUNT(*) FROM activities')->fetchColumn();
    $stats['Gallery Events'] = $pdo->query('SELECT COUNT(*) FROM gallery_events')->fetchColumn();
    $stats['Unread Messages'] = $pdo->query('SELECT COUNT(*) FROM contact_messages WHERE is_read = 0')->fetchColumn();
}
if (has_role(['secretary'])) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE status = ?');
    $stmt->execute(['pending']);
    $stats['Awaiting Your Review (Stage 1)'] = $stmt->fetchColumn();
}
if (has_role(['president'])) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE status = ?');
    $stmt->execute(['verifier1_approved']);
    $stats['Awaiting Your Review (Stage 2)'] = $stmt->fetchColumn();
}
if (has_role(['super_admin'])) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE status IN ('pending','verifier1_approved','verifier2_approved')");
    $stmt->execute();
    $stats['Join Requests In Pipeline'] = $stmt->fetchColumn();
}
if (has_role(APTITUDE_ROLES)) {
    $stats['Aptitude Tests'] = $pdo->query('SELECT COUNT(*) FROM aptitude_tests')->fetchColumn();
}

require __DIR__ . '/layout_header.php';
?>
<h1>Welcome, <?= h($u['full_name']) ?></h1>
<p class="muted">Role: <span class="mono"><?= h(role_label($u['role'])) ?></span></p>

<div class="grid grid-4" style="margin-top:20px">
  <?php foreach ($stats as $label => $val): ?>
    <div class="card">
      <div class="meta"><?= h($label) ?></div>
      <h2 style="margin:0"><?= (int)$val ?></h2>
    </div>
  <?php endforeach; ?>
</div>

<?php if (has_role(['super_admin', 'secretary', 'president']) && array_sum(array_intersect_key($stats, array_flip(['Awaiting Your Review (Stage 1)', 'Awaiting Your Review (Stage 2)', 'Join Requests In Pipeline']))) > 0): ?>
  <div class="alert alert-ok" style="margin-top:24px;max-width:520px">
    You have join requests waiting on your review.
    <a href="<?= BASE_URL ?>/admin/users.php" style="color:var(--copper-lt);font-weight:600">Review now &rarr;</a>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/layout_footer.php'; ?>
