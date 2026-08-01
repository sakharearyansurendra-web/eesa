<?php
require_once __DIR__ . '/../config.php';
$pageTitle = 'Team';

$years = $pdo->query('SELECT * FROM team_years ORDER BY sort_order DESC, year_label DESC')->fetchAll();

require __DIR__ . '/../includes/header.php';
?>
<section class="section">
  <div class="container">
    <div class="eyebrow">Since inception</div>
    <h1>Our Team, Year by Year</h1>
    <p class="muted" style="max-width:600px">Every batch that has led EESA — from the very first team to the one steering it today.</p>

    <div class="grid grid-3">
      <?php if (!$years): ?><p class="muted">Team records will appear here once added.</p><?php endif; ?>
      <?php foreach ($years as $y): ?>
        <a class="card-link" href="<?= BASE_URL ?>/pages/team_year.php?year=<?= urlencode($y['year_label']) ?>">
          <div class="card year-card">
            <?php if ($y['group_photo']): ?>
              <img src="<?= BASE_URL ?>/uploads/team/<?= h($y['group_photo']) ?>">
            <?php else: ?>
              <div class="thumb" style="display:flex;align-items:center;justify-content:center" ><span class="muted mono">No photo yet</span></div>
            <?php endif; ?>
            <h3><?= h($y['year_label']) ?></h3>
            <p class="muted">View this year's team &rarr;</p>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
