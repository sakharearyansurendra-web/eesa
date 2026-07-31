<?php
require_once __DIR__ . '/../config.php';
$pageTitle = 'Announcements';

$announcements = $pdo->query('SELECT * FROM announcements ORDER BY COALESCE(event_datetime, created_at) DESC')->fetchAll();

require __DIR__ . '/../includes/header.php';
?>
<section class="section">
  <div class="container">
    <div class="eyebrow">IST · Updated live</div>
    <h1>Announcements</h1>
    <p class="muted" style="max-width:600px">Notices and upcoming events from EESA. Events with open registration
    let you sign up directly — status updates automatically once the event starts and ends.</p>

    <div class="grid grid-3">
      <?php if (!$announcements): ?><p class="muted">No announcements yet.</p><?php endif; ?>
      <?php foreach ($announcements as $a):
          $status = announcement_status($a['event_datetime'], $a['registration_close']); ?>
        <a class="card-link" href="<?= BASE_URL ?>/pages/announcement_view.php?slug=<?= h($a['slug']) ?>">
          <div class="card">
            <?php if ($a['cover_image']): ?><img class="thumb" src="<?= BASE_URL ?>/uploads/activities/<?= h($a['cover_image']) ?>"><?php endif; ?>
            <div class="meta"><?= status_badge($status) ?>
              <?php if ($a['event_datetime']): ?> &nbsp; <?= h(date('d M Y, h:i A', strtotime($a['event_datetime']))) ?><?php endif; ?>
            </div>
            <h3><?= h($a['title']) ?></h3>
            <p><?= h(mb_strimwidth(strip_tags($a['body']), 0, 110, '…')) ?></p>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
