<?php
require_once __DIR__ . '/../config.php';
$pageTitle = 'Department';

$dept = $pdo->query('SELECT * FROM department_info WHERE id = 1')->fetch();
$images = $pdo->query('SELECT * FROM department_images ORDER BY sort_order, id')->fetchAll();
$staff = $pdo->query('SELECT * FROM staff ORDER BY sort_order, id')->fetchAll();

require __DIR__ . '/../includes/header.php';
?>
<section class="section">
  <div class="container">
    <div class="eyebrow">SGGS Institute of Engineering &amp; Technology</div>
    <h1>Department of Electrical Engineering</h1>
    <div class="post-body"><?= $dept['about'] ?? '' ?></div>
  </div>
</section>

<?php if ($images): ?>
<section class="section" style="padding-top:0">
  <div class="container">
    <h2>Department in Pictures</h2>
    <div class="grid grid-4">
      <?php foreach ($images as $img): ?>
        <div class="card">
          <img class="thumb" src="<?= BASE_URL ?>/uploads/gallery/<?= h($img['filename']) ?>">
          <?php if ($img['caption']): ?><p class="muted" style="font-size:13px"><?= h($img['caption']) ?></p><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="section" style="padding-top:0">
  <div class="container">
    <h2>Message from the HOD</h2>
    <div class="card" style="display:flex;gap:24px;align-items:flex-start;flex-wrap:wrap">
      <?php if (!empty($dept['hod_photo'])): ?>
        <img src="<?= BASE_URL ?>/uploads/team/<?= h($dept['hod_photo']) ?>" style="width:140px;height:140px;object-fit:cover;border-radius:12px">
      <?php endif; ?>
      <div style="flex:1;min-width:220px">
        <h3><?= h($dept['hod_name'] ?? 'Head of Department') ?></h3>
        <p class="muted mono" style="font-size:12px">Head of Department, Electrical Engineering</p>
        <p><?= h($dept['hod_message'] ?? '') ?></p>
      </div>
    </div>
  </div>
</section>

<?php if ($staff): ?>
<section class="section" style="padding-top:0">
  <div class="container">
    <h2>Faculty &amp; Staff</h2>
    <div class="grid grid-4">
      <?php foreach ($staff as $s): ?>
        <div class="card member-card">
          <?php if ($s['photo']): ?><img src="<?= BASE_URL ?>/uploads/team/<?= h($s['photo']) ?>"><?php endif; ?>
          <h3><?= h($s['name']) ?></h3>
          <p class="muted mono" style="font-size:12px"><?= h($s['designation']) ?></p>
          <?php if ($s['email']): ?><p class="muted" style="font-size:13px"><?= h($s['email']) ?></p><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
