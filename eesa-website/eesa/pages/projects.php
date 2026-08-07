<?php
require_once __DIR__ . '/../config.php';
$pageTitle = 'Projects';
$projects = $pdo->query('SELECT * FROM projects ORDER BY sort_order')->fetchAll();
require __DIR__ . '/../includes/header.php';
?>
<section class="section">
  <div class="container">
    <div class="eyebrow">Built by EESA</div>
    <h1>Projects</h1>
    <p class="muted" style="max-width:600px">Student-built projects — electronics, embedded systems, automation and more — credited to the teams that built them.</p>
    <div class="grid grid-3">
      <?php if (!$projects): ?><p class="muted">No projects posted yet.</p><?php endif; ?>
      <?php foreach ($projects as $p): ?>
        <a class="card-link" href="<?= BASE_URL ?>/pages/project_view.php?slug=<?= h($p['slug']) ?>">
          <div class="card">
            <?php if ($p['cover_image']): ?><img class="thumb" src="<?= BASE_URL ?>/uploads/projects/<?= h($p['cover_image']) ?>"><?php endif; ?>
            <div class="meta"><span class="badge badge-<?= $p['status']==='completed'?'completed':'ongoing' ?>"><?= h(ucfirst($p['status'])) ?></span></div>
            <h3><?= h($p['title']) ?></h3>
            <p><?= h($p['summary']) ?></p>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
