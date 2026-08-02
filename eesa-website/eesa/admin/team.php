<?php
require_once __DIR__ . '/../config.php';
require_role(CONTENT_ADMIN_ROLES);
$pageTitle = 'Manage Team';
$activeSection = 'team';
$msg = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_year'])) {
    csrf_check();
    $label = trim($_POST['year_label']);
    $photo = save_upload('group_photo', 'team');
    $pdo->prepare('INSERT INTO team_years (year_label, group_photo) VALUES (?,?)')->execute([$label, $photo]);
    $msg = 'Year added.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_member'])) {
    csrf_check();
    $yearId = (int)$_POST['team_year_id'];
    $name = trim($_POST['name']);
    $desig = trim($_POST['designation']);
    $linkedin = trim($_POST['linkedin_url']);
    $photo = save_upload('photo', 'team');
    $pdo->prepare('INSERT INTO team_members (team_year_id, name, designation, photo, linkedin_url) VALUES (?,?,?,?,?)')
        ->execute([$yearId, $name, $desig, $photo, $linkedin]);
    $msg = 'Member added.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_year'])) {
    csrf_check();
    $pdo->prepare('DELETE FROM team_years WHERE id=?')->execute([(int)$_POST['id']]);
    $msg = 'Year deleted.';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_member'])) {
    csrf_check();
    $pdo->prepare('DELETE FROM team_members WHERE id=?')->execute([(int)$_POST['id']]);
    $msg = 'Member removed.';
}

$years = $pdo->query('SELECT * FROM team_years ORDER BY year_label DESC')->fetchAll();
require __DIR__ . '/layout_header.php';
?>
<h1>Team</h1>
<?php if ($msg): ?><div class="alert alert-ok"><?= h($msg) ?></div><?php endif; ?>

<div class="card form-card" style="max-width:520px">
  <h3>Add a Year</h3>
  <form method="POST" enctype="multipart/form-data" class="stack">
    <?= csrf_field() ?>
    <div class="field"><label>Year Label</label><input name="year_label" placeholder="e.g. 2025-26" required></div>
    <div class="field"><label>Group Photo</label><input type="file" name="group_photo" accept="image/*"></div>
    <button class="btn btn-primary" type="submit" name="create_year">Add Year</button>
  </form>
</div>

<?php foreach ($years as $y): ?>
  <div class="card" style="margin:20px 0">
    <div style="display:flex;justify-content:space-between;align-items:center">
      <h3><?= h($y['year_label']) ?></h3>
      <form method="POST" onsubmit="return confirm('Delete this whole year, including its members?')">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$y['id'] ?>">
        <button class="btn btn-danger btn-sm" type="submit" name="delete_year">Delete Year</button>
      </form>
    </div>
    <?php
      $mStmt = $pdo->prepare('SELECT * FROM team_members WHERE team_year_id=? ORDER BY sort_order,id');
      $mStmt->execute([$y['id']]);
      $members = $mStmt->fetchAll();
    ?>
    <table class="admin-table">
      <tr><th>Name</th><th>Designation</th><th></th></tr>
      <?php foreach ($members as $m): ?>
        <tr>
          <td><?= h($m['name']) ?></td>
          <td><?= h($m['designation']) ?></td>
          <td>
            <form method="POST" style="display:inline">
              <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
              <button class="btn btn-danger btn-sm" type="submit" name="delete_member">Remove</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
    <form method="POST" enctype="multipart/form-data" style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end">
      <?= csrf_field() ?><input type="hidden" name="team_year_id" value="<?= (int)$y['id'] ?>">
      <div class="field" style="margin-bottom:0"><input name="name" placeholder="Name" required></div>
      <div class="field" style="margin-bottom:0"><input name="designation" placeholder="Designation" required></div>
      <div class="field" style="margin-bottom:0"><input name="linkedin_url" placeholder="LinkedIn URL"></div>
      <input type="file" name="photo" accept="image/*">
      <button class="btn btn-outline btn-sm" type="submit" name="add_member">Add Member</button>
    </form>
  </div>
<?php endforeach; ?>
<?php require __DIR__ . '/layout_footer.php'; ?>
