<?php
require_once __DIR__ . '/../config.php';
$pageTitle = 'Activities';

$categories = $pdo->query('SELECT * FROM activity_categories ORDER BY id')->fetchAll();
$activities = $pdo->query(
    'SELECT a.*, c.slug AS cat_slug, c.label AS cat_label FROM activities a
     JOIN activity_categories c ON c.id = a.category_id
     WHERE a.status = \'published\' ORDER BY a.event_date DESC'
)->fetchAll();

require __DIR__ . '/../includes/header.php';
?>
<section class="section">
  <div class="container">
    <div class="eyebrow">Blog</div>
    <h1>Activities</h1>
    <p class="muted" style="max-width:600px">Webinars, seminars, workshops, wall magazines, celebrations and events
    conducted by EESA — posted by the team as they happen.</p>

    <div class="chip-row">
      <span class="chip active" data-filter="all">All</span>
      <?php foreach ($categories as $c): ?>
        <span class="chip" data-filter="<?= h($c['slug']) ?>"><?= h($c['label']) ?></span>
      <?php endforeach; ?>
    </div>

    <div class="grid grid-3">
      <?php if (!$activities): ?><p class="muted">No activities posted yet — check back soon.</p><?php endif; ?>
      <?php foreach ($activities as $act): ?>
        <div data-category="<?= h($act['cat_slug']) ?>">
          <a class="card-link" href="<?= BASE_URL ?>/pages/activity_view.php?slug=<?= h($act['slug']) ?>">
            <div class="card">
              <?php if ($act['cover_image']): ?><img class="thumb" src="<?= BASE_URL ?>/uploads/activities/<?= h($act['cover_image']) ?>"><?php endif; ?>
              <div class="meta"><?= h($act['cat_label']) ?> &nbsp;·&nbsp; <?= $act['event_date'] ? h(date('d M Y', strtotime($act['event_date']))) : '' ?></div>
              <h3><?= h($act['title']) ?></h3>
              <p><?= h($act['summary']) ?></p>
            </div>
          </a>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
