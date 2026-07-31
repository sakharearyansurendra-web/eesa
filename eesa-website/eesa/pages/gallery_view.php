<?php
require_once __DIR__ . '/../config.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM gallery_events WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$event = $stmt->fetch();
if (!$event) { http_response_code(404); die('Gallery event not found.'); }

$photosStmt = $pdo->prepare('SELECT * FROM gallery_photos WHERE gallery_event_id = ? ORDER BY sort_order, id');
$photosStmt->execute([$id]);
$photos = $photosStmt->fetchAll();

$pageTitle = $event['event_name'];
require __DIR__ . '/../includes/header.php';
?>
<section class="section">
  <div class="container">
    <div class="eyebrow"><a href="<?= BASE_URL ?>/pages/gallery.php" class="muted">&larr; Gallery</a></div>
    <h1><?= h($event['event_name']) ?></h1>
    <p class="mono muted"><?= h(date('d M Y', strtotime($event['event_date']))) ?></p>

    <div class="lightbox-grid" style="margin-top:20px">
      <?php if (!$photos): ?><p class="muted">No photos uploaded for this event yet.</p><?php endif; ?>
      <?php foreach ($photos as $p): ?>
        <img src="<?= BASE_URL ?>/uploads/gallery/<?= h($p['filename']) ?>" alt="<?= h($p['caption']) ?>">
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
