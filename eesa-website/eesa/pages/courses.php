<?php
require_once __DIR__ . '/../config.php';
$pageTitle = 'Courses';
$subjects = $pdo->query('SELECT s.*, (SELECT COUNT(*) FROM course_videos v WHERE v.subject_id=s.id) AS video_count FROM course_subjects s ORDER BY sort_order')->fetchAll();
require __DIR__ . '/../includes/header.php';
?>
<section class="section">
  <div class="container">
    <div class="eyebrow">Learn</div>
    <h1>Courses</h1>
    <p class="muted" style="max-width:600px">Subject-wise video playlists curated by EESA.</p>
    <div class="grid grid-3">
      <?php if (!$subjects): ?><p class="muted">No subjects added yet.</p><?php endif; ?>
      <?php foreach ($subjects as $s): ?>
        <a class="card-link" href="<?= BASE_URL ?>/pages/course_view.php?slug=<?= h($s['slug']) ?>">
          <div class="card">
            <h3><?= h($s['title']) ?></h3>
            <p><?= h($s['description']) ?></p>
            <div class="meta"><?= (int)$s['video_count'] ?> video(s)</div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
