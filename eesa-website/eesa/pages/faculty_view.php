<?php
require_once __DIR__ . '/../config.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM dept_faculty WHERE id=? LIMIT 1');
$stmt->execute([$id]);
$faculty = $stmt->fetch();
if (!$faculty) { http_response_code(404); die('Faculty profile not found.'); }

$serviceStmt = $pdo->prepare('SELECT * FROM dept_faculty_service WHERE faculty_id=? ORDER BY sort_order');
$serviceStmt->execute([$id]);
$service = $serviceStmt->fetchAll();

$papersStmt = $pdo->prepare('SELECT * FROM dept_faculty_papers WHERE faculty_id=? ORDER BY sort_order');
$papersStmt->execute([$id]);
$papers = $papersStmt->fetchAll();

$pageTitle = $faculty['full_name'];
require __DIR__ . '/../includes/header.php';
?>
<section class="section">
  <div class="container" style="max-width:760px">
    <div style="display:flex;gap:24px;align-items:flex-start;flex-wrap:wrap">
      <?php if ($faculty['photo']): ?>
        <img src="<?= BASE_URL ?>/uploads/dept/<?= h($faculty['photo']) ?>" style="width:150px;height:150px;object-fit:cover;border-radius:14px">
      <?php endif; ?>
      <div style="flex:1;min-width:220px">
        <div class="eyebrow"><?= h($faculty['designation']) ?></div>
        <h1><?= h($faculty['full_name']) ?></h1>
        <?php if ($faculty['email']): ?><p class="muted mono" style="font-size:13px"><?= h($faculty['email']) ?></p><?php endif; ?>
        <?php if ($faculty['resume_file']): ?>
          <a class="btn btn-outline btn-sm" style="margin-top:8px" target="_blank"
             href="<?= BASE_URL ?>/uploads/dept/<?= h($faculty['resume_file']) ?>">Download Resume</a>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($faculty['bio']): ?>
      <div class="post-body" style="margin-top:24px"><?= nl2br(h($faculty['bio'])) ?></div>
    <?php endif; ?>

    <?php if ($service): ?>
      <h3 style="margin-top:30px">Serving Years</h3>
      <table class="admin-table">
        <?php foreach ($service as $s): ?>
          <tr>
            <td><?= h($s['position']) ?></td>
            <td class="mono"><?= h($s['start_year']) ?> – <?= h($s['end_year'] ?: 'Present') ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
    <?php endif; ?>

    <?php if ($papers): ?>
      <h3 style="margin-top:30px">Papers Published</h3>
      <?php foreach ($papers as $p): ?>
        <div class="card" style="margin-bottom:10px">
          <p style="margin:0"><strong><?= h($p['title']) ?></strong><?php if ($p['published_year']): ?> <span class="muted mono" style="font-size:12px">(<?= h($p['published_year']) ?>)</span><?php endif; ?></p>
          <?php if ($p['link']): ?><a href="<?= h($p['link']) ?>" target="_blank" style="color:var(--copper-lt);font-size:13px">View paper &rarr;</a><?php endif; ?>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
