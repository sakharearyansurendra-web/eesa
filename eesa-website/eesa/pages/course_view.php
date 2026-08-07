<?php
require_once __DIR__ . '/../config.php';
$slug = $_GET['slug'] ?? '';
$stmt = $pdo->prepare('SELECT * FROM course_subjects WHERE slug=? LIMIT 1');
$stmt->execute([$slug]);
$subject = $stmt->fetch();
if (!$subject) { http_response_code(404); die('Subject not found.'); }

$vidStmt = $pdo->prepare('SELECT * FROM course_videos WHERE subject_id=? ORDER BY sort_order');
$vidStmt->execute([$subject['id']]);
$videos = $vidStmt->fetchAll();

$pageTitle = $subject['title'];
require __DIR__ . '/../includes/header.php';
?>
<section class="section">
  <div class="container">
    <div class="eyebrow"><a href="<?= BASE_URL ?>/pages/courses.php" class="muted">&larr; Courses</a></div>
    <h1><?= h($subject['title']) ?></h1>
    <p class="muted" style="max-width:600px"><?= h($subject['description']) ?></p>

    <div class="grid grid-3" style="margin-top:20px">
      <?php if (!$videos): ?><p class="muted">No videos in this subject yet.</p><?php endif; ?>
      <?php foreach ($videos as $v): ?>
        <div class="card video-card" data-yt="<?= h($v['youtube_id']) ?>" data-title="<?= h($v['title']) ?>" style="cursor:pointer">
          <div style="position:relative;border-radius:8px;overflow:hidden;aspect-ratio:16/9;background:var(--navy-3);margin-bottom:12px">
            <img src="https://img.youtube.com/vi/<?= h($v['youtube_id']) ?>/hqdefault.jpg" style="width:100%;height:100%;object-fit:cover">
            <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center">
              <div style="width:56px;height:56px;border-radius:50%;background:rgba(0,0,0,0.55);display:flex;align-items:center;justify-content:center;border:1px solid rgba(255,255,255,0.3)">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="#fff"><path d="M8 5v14l11-7z"/></svg>
              </div>
            </div>
          </div>
          <h3 style="font-size:15px"><?= h($v['title']) ?></h3>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<div id="videoModal" style="display:none;position:fixed;inset:0;background:rgba(3,5,10,0.92);z-index:200;align-items:center;justify-content:center;padding:20px">
  <div style="width:100%;max-width:920px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
      <h3 id="modalTitle" style="margin:0;color:#fff"></h3>
      <button id="closeModal" class="btn btn-outline btn-sm" type="button">Close ✕</button>
    </div>
    <div style="position:relative;aspect-ratio:16/9;background:#000;border-radius:10px;overflow:hidden">
      <iframe id="modalFrame" style="width:100%;height:100%;border:0" allow="autoplay; encrypted-media" allowfullscreen></iframe>
    </div>
  </div>
</div>

<script>
document.querySelectorAll('.video-card').forEach(card => {
  card.addEventListener('click', () => {
    document.getElementById('modalFrame').src = 'https://www.youtube.com/embed/' + card.dataset.yt + '?autoplay=1&rel=0';
    document.getElementById('modalTitle').textContent = card.dataset.title;
    document.getElementById('videoModal').style.display = 'flex';
  });
});
document.getElementById('closeModal').addEventListener('click', closeVideoModal);
document.getElementById('videoModal').addEventListener('click', e => { if (e.target.id === 'videoModal') closeVideoModal(); });
function closeVideoModal() {
  document.getElementById('modalFrame').src = '';
  document.getElementById('videoModal').style.display = 'none';
}
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
