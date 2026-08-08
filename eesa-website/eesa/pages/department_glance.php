<?php
require_once __DIR__ . '/../config.php';
$pageTitle = 'Department at a Glance';

$faculty = $pdo->query('SELECT * FROM dept_faculty ORDER BY sort_order')->fetchAll();
$facilities = $pdo->query('SELECT * FROM dept_facilities ORDER BY sort_order')->fetchAll();
$equipment = $pdo->query(
    'SELECT e.*, f.name AS facility_name FROM dept_equipment e
     LEFT JOIN dept_facilities f ON f.id = e.facility_id ORDER BY e.id DESC'
)->fetchAll();

require __DIR__ . '/../includes/header.php';
?>
<section class="section">
  <div class="container">
    <div class="eyebrow">Department Overview</div>
    <h1>Department at a Glance</h1>
    <p class="muted" style="max-width:640px">Our faculty, labs, classrooms, and the equipment that powers hands-on learning.</p>
  </div>
</section>

<section class="section" style="padding-top:0">
  <div class="container">
    <div class="section-head"><h2>Faculty</h2></div>
    <div class="grid grid-4">
      <?php if (!$faculty): ?><p class="muted">Faculty profiles will appear here soon.</p><?php endif; ?>
      <?php foreach ($faculty as $f): ?>
        <a class="card-link" href="<?= BASE_URL ?>/pages/faculty_view.php?id=<?= (int)$f['id'] ?>">
          <div class="card member-card">
            <?php if ($f['photo']): ?><img src="<?= BASE_URL ?>/uploads/dept/<?= h($f['photo']) ?>"><?php else: ?>
              <div class="thumb" style="border-radius:50%;width:96px;height:96px;margin:0 auto 10px"></div>
            <?php endif; ?>
            <h3><?= h($f['full_name']) ?></h3>
            <p class="muted mono" style="font-size:12px"><?= h($f['designation']) ?></p>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php if ($facilities): ?>
<section class="section" style="padding-top:0">
  <div class="container">
    <div class="section-head"><h2>Labs &amp; Classrooms</h2></div>
    <div class="grid grid-3">
      <?php foreach ($facilities as $fac): ?>
        <div class="card">
          <?php if ($fac['photo']): ?><img class="thumb" src="<?= BASE_URL ?>/uploads/dept/<?= h($fac['photo']) ?>"><?php endif; ?>
          <div class="meta"><?= h(ucfirst($fac['type'])) ?></div>
          <h3><?= h($fac['name']) ?></h3>
          <?php if ($fac['description']): ?><p><?= h($fac['description']) ?></p><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($equipment): ?>
<section class="section" style="padding-top:0">
  <div class="container">
    <div class="section-head"><h2>Equipment &amp; Components</h2></div>
    <table class="admin-table">
      <tr><th>Name</th><th>Quantity</th><th>Manufacturer</th><th>Model No.</th><th>Location</th></tr>
      <?php foreach ($equipment as $eq): ?>
        <tr>
          <td><?= h($eq['name']) ?></td>
          <td class="mono"><?= (int)$eq['quantity'] ?></td>
          <td class="muted"><?= h($eq['manufacturer'] ?: '—') ?></td>
          <td class="muted mono" style="font-size:12px"><?= h($eq['model_no'] ?: '—') ?></td>
          <td class="muted"><?= h($eq['facility_name'] ?: '—') ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>
</section>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
