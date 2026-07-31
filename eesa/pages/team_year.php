<?php
require_once __DIR__ . '/../config.php';

$yearLabel = $_GET['year'] ?? '';
$stmt = $pdo->prepare('SELECT * FROM team_years WHERE year_label = ? LIMIT 1');
$stmt->execute([$yearLabel]);
$year = $stmt->fetch();
if (!$year) { http_response_code(404); die('Team year not found.'); }

$membersStmt = $pdo->prepare('SELECT * FROM team_members WHERE team_year_id = ? ORDER BY sort_order, id');
$membersStmt->execute([$year['id']]);
$members = $membersStmt->fetchAll();

$pageTitle = 'Team ' . $year['year_label'];
require __DIR__ . '/../includes/header.php';
?>
<section class="section">
  <div class="container">
    <div class="eyebrow"><a href="<?= BASE_URL ?>/pages/team.php" class="muted">&larr; All Years</a></div>
    <h1>EESA Team, <?= h($year['year_label']) ?></h1>

    <?php if ($year['group_photo']): ?>
      <div class="post-hero"><img src="<?= BASE_URL ?>/uploads/team/<?= h($year['group_photo']) ?>" style="width:100%;height:100%;object-fit:cover"></div>
    <?php endif; ?>

    <div class="grid grid-4" style="margin-top:20px">
      <?php foreach ($members as $m): ?>
        <div class="card member-card">
          <?php if ($m['photo']): ?>
            <img src="<?= BASE_URL ?>/uploads/team/<?= h($m['photo']) ?>">
          <?php endif; ?>
          <h3><?= h($m['name']) ?></h3>
          <p class="muted mono" style="font-size:12px"><?= h($m['designation']) ?></p>
          <?php if ($m['linkedin_url']): ?>
            <a class="btn btn-outline btn-sm" target="_blank" rel="noopener" href="<?= h($m['linkedin_url']) ?>">LinkedIn</a>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
