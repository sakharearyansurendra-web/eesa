<?php
require_once __DIR__ . '/config.php';
$pageTitle = 'Home';

$announcements = $pdo->query(
    'SELECT * FROM announcements ORDER BY COALESCE(event_datetime, created_at) DESC LIMIT 3'
)->fetchAll();

$activities = $pdo->query(
    'SELECT a.*, c.label AS cat_label FROM activities a
     JOIN activity_categories c ON c.id = a.category_id
     WHERE a.status = "published" ORDER BY a.event_date DESC LIMIT 3'
)->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<section class="hero">
  <div class="container hero-grid">
    <div>
      <div class="eyebrow">Electrical Engineering Student Association · SGGS</div>
      <h1>Welcome to EESA</h1>
      <p class="lead"><strong>By the students, for the students.</strong> EESA is powering the next generation
      of electrical engineers &mdash; through workshops, seminars, projects and a community that learns together.</p>
      <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:22px">
        <a href="<?= BASE_URL ?>/pages/activities.php" class="btn btn-primary">Explore Activities</a>
        <a href="<?= BASE_URL ?>/pages/join.php" class="btn btn-outline">Join EESA</a>
      </div>
    </div>
    <div class="hero-visual">
      <svg width="70%" viewBox="0 0 200 200" fill="none">
        <circle cx="100" cy="100" r="60" stroke="#c9793f" stroke-opacity="0.5" stroke-width="1.5"/>
        <circle cx="100" cy="100" r="4" fill="#c9793f"/>
        <path d="M100 40V4M100 160v36M40 100H4M160 100h36" stroke="#c9793f" stroke-opacity="0.5" stroke-width="1.5"/>
        <circle cx="100" cy="4" r="3" fill="#c9793f"/><circle cx="100" cy="196" r="3" fill="#c9793f"/>
        <circle cx="4" cy="100" r="3" fill="#c9793f"/><circle cx="196" cy="100" r="3" fill="#c9793f"/>
      </svg>
    </div>
  </div>
</section>

<div class="container"><div class="trace"></div></div>

<section class="section">
  <div class="container">
    <div class="section-head">
      <h2>Latest Announcements</h2>
      <a href="<?= BASE_URL ?>/pages/announcements.php" class="btn btn-outline btn-sm">View all</a>
    </div>
    <div class="grid grid-3">
      <?php if (!$announcements): ?>
        <p class="muted">No announcements yet. Check back soon.</p>
      <?php endif; ?>
      <?php foreach ($announcements as $a):
          $status = announcement_status($a['event_datetime'], $a['registration_close']); ?>
        <a class="card-link" href="<?= BASE_URL ?>/pages/announcement_view.php?slug=<?= h($a['slug']) ?>">
          <div class="card">
            <?php if ($a['cover_image']): ?><img class="thumb" src="<?= BASE_URL ?>/uploads/activities/<?= h($a['cover_image']) ?>"><?php endif; ?>
            <div class="meta"><?= status_badge($status) ?> &nbsp; <?= h(time_ago($a['created_at'])) ?></div>
            <h3><?= h($a['title']) ?></h3>
            <p><?= h(mb_strimwidth(strip_tags($a['body']), 0, 110, '…')) ?></p>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="section-head">
      <h2>Recent Activities</h2>
      <a href="<?= BASE_URL ?>/pages/activities.php" class="btn btn-outline btn-sm">View all</a>
    </div>
    <div class="grid grid-3">
      <?php if (!$activities): ?>
        <p class="muted">No activities posted yet.</p>
      <?php endif; ?>
      <?php foreach ($activities as $act): ?>
        <a class="card-link" href="<?= BASE_URL ?>/pages/activity_view.php?slug=<?= h($act['slug']) ?>">
          <div class="card">
            <?php if ($act['cover_image']): ?><img class="thumb" src="<?= BASE_URL ?>/uploads/activities/<?= h($act['cover_image']) ?>"><?php endif; ?>
            <div class="meta"><?= h($act['cat_label']) ?> &nbsp;·&nbsp; <?= $act['event_date'] ? h(date('d M Y', strtotime($act['event_date']))) : '' ?></div>
            <h3><?= h($act['title']) ?></h3>
            <p><?= h($act['summary']) ?></p>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
