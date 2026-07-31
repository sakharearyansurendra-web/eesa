<?php
require_once __DIR__ . '/../config.php';

$slug = $_GET['slug'] ?? '';
$stmt = $pdo->prepare(
    'SELECT a.*, c.label AS cat_label FROM activities a
     JOIN activity_categories c ON c.id = a.category_id
     WHERE a.slug = ? AND a.status = "published" LIMIT 1'
);
$stmt->execute([$slug]);
$act = $stmt->fetch();
if (!$act) { http_response_code(404); $pageTitle = 'Not found'; require __DIR__ . '/../includes/header.php'; echo '<div class="container section"><h2>Activity not found</h2></div>'; require __DIR__ . '/../includes/footer.php'; exit; }

$photos = $pdo->prepare('SELECT * FROM activity_photos WHERE activity_id = ? ORDER BY sort_order, id');
$photos->execute([$act['id']]);
$photos = $photos->fetchAll();

$pageTitle = $act['title'];
$shareUrl = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

require __DIR__ . '/../includes/header.php';
?>
<section class="section">
  <div class="container" style="max-width:900px">
    <div class="post-hero">
      <?php if ($act['cover_image']): ?><img src="<?= BASE_URL ?>/uploads/activities/<?= h($act['cover_image']) ?>" style="width:100%;height:100%;object-fit:cover"><?php endif; ?>
    </div>

    <div class="meta"><?= h($act['cat_label']) ?> &nbsp;·&nbsp; <?= $act['event_date'] ? h(date('d M Y', strtotime($act['event_date']))) : '' ?></div>
    <h1><?= h($act['title']) ?></h1>
    <p class="muted"><?= h($act['summary']) ?></p>

    <div class="post-body">
      <?= $act['content'] /* trusted HTML authored by admins via the dashboard editor */ ?>
    </div>

    <?php if ($photos): ?>
      <h3 style="margin-top:30px">Photos</h3>
      <div class="post-gallery">
        <?php foreach ($photos as $p): ?>
          <img src="<?= BASE_URL ?>/uploads/activities/<?= h($p['filename']) ?>" alt="<?= h($p['caption']) ?>">
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="share-row">
      <a class="btn btn-outline btn-sm" target="_blank" href="https://www.linkedin.com/sharing/share-offsite/?url=<?= urlencode($shareUrl) ?>">Share on LinkedIn</a>
      <a class="btn btn-outline btn-sm" target="_blank" href="https://api.whatsapp.com/send?text=<?= urlencode($act['title'] . ' — ' . $shareUrl) ?>">Share on WhatsApp</a>
      <button class="btn btn-primary btn-sm" onclick="downloadPoster('posterCard','<?= h(slugify($act['title'])) ?>')">Download Poster for Instagram/LinkedIn</button>
    </div>

    <!-- Shareable poster card (Spotify-style), hidden from normal reading flow, captured to PNG on demand -->
    <div style="margin-top:34px">
      <div class="eyebrow" style="margin-bottom:10px">Poster preview</div>
      <div class="poster-card" id="posterCard">
        <?php if ($act['cover_image']): ?>
          <img class="poster-img" src="<?= BASE_URL ?>/uploads/activities/<?= h($act['cover_image']) ?>">
        <?php else: ?>
          <div class="poster-img"></div>
        <?php endif; ?>
        <div class="poster-cat"><?= h($act['cat_label']) ?></div>
        <h3><?= h($act['title']) ?></h3>
        <div class="poster-date"><?= $act['event_date'] ? h(date('d M Y', strtotime($act['event_date']))) : '' ?></div>
        <div class="poster-brand">
          <span class="brand-mark" style="width:26px;height:26px;font-size:10px">EE</span>
          <span class="mono muted" style="font-size:11px">EESA · by students, for students</span>
        </div>
      </div>
    </div>
  </div>
</section>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
