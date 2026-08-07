<?php
require_once __DIR__ . '/../config.php';
$slug = $_GET['slug'] ?? '';
$stmt = $pdo->prepare('SELECT * FROM projects WHERE slug=? LIMIT 1');
$stmt->execute([$slug]);
$p = $stmt->fetch();
if (!$p) { http_response_code(404); die('Project not found.'); }

$creditsStmt = $pdo->prepare(
    'SELECT pc.*, u.member_id AS linked_member_id FROM project_credits pc
     LEFT JOIN users u ON u.id = pc.user_id
     WHERE pc.project_id=? ORDER BY pc.sort_order'
);
$creditsStmt->execute([$p['id']]);
$credits = $creditsStmt->fetchAll();

$pageTitle = $p['title'];
require __DIR__ . '/../includes/header.php';
?>
<section class="section">
  <div class="container" style="max-width:840px">
    <?php if ($p['cover_image']): ?>
      <div class="post-hero"><img src="<?= BASE_URL ?>/uploads/projects/<?= h($p['cover_image']) ?>" style="width:100%;height:100%;object-fit:cover"></div>
    <?php endif; ?>
    <div class="meta"><span class="badge badge-<?= $p['status']==='completed'?'completed':'ongoing' ?>"><?= h(ucfirst($p['status'])) ?></span></div>
    <h1><?= h($p['title']) ?></h1>
    <p class="muted"><?= h($p['summary']) ?></p>
    <div class="post-body"><?= $p['description'] ?></div>

    <h3 style="margin-top:30px">Team &amp; Credits</h3>
    <div class="grid grid-3">
      <?php foreach ($credits as $c): ?>
        <div class="card">
          <h3 style="margin-bottom:2px"><?= h($c['name']) ?></h3>
          <?php if ($c['role_in_project']): ?><p class="muted mono" style="font-size:12px;margin-bottom:6px"><?= h($c['role_in_project']) ?></p><?php endif; ?>
          <?php if ($c['linked_member_id']): ?><p class="mono" style="font-size:12px;color:var(--copper-lt)">Member ID: <?= h($c['linked_member_id']) ?></p><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
