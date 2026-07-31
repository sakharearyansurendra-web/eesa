<?php
require_once __DIR__ . '/../config.php';
$pageTitle = 'Gallery';

$events = $pdo->query('SELECT * FROM gallery_events ORDER BY event_date DESC')->fetchAll();

require __DIR__ . '/../includes/header.php';
?>
<section class="section">
  <div class="container">
    <div class="eyebrow">Moments</div>
    <h1>Gallery</h1>
    <p class="muted" style="max-width:600px">Photos from EESA events, grouped by occasion. Open an event to see the full set.</p>

    <div class="grid grid-3">
      <?php if (!$events): ?><p class="muted">No gallery events yet.</p><?php endif; ?>
      <?php foreach ($events as $e): ?>
        <a class="card-link" href="<?= BASE_URL ?>/pages/gallery_view.php?id=<?= (int)$e['id'] ?>">
          <div class="gallery-event-cover">
            <?php if ($e['cover_image']): ?>
              <img src="<?= BASE_URL ?>/uploads/gallery/<?= h($e['cover_image']) ?>">
            <?php else: ?>
              <div style="width:100%;height:100%;background:var(--navy-3)"></div>
            <?php endif; ?>
            <div class="cap">
              <div class="mono" style="font-size:12px;color:var(--copper-lt)"><?= h(date('d M Y', strtotime($e['event_date']))) ?></div>
              <h3 style="margin:2px 0 0;font-size:17px"><?= h($e['event_name']) ?></h3>
            </div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
