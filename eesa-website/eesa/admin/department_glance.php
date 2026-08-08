<?php
require_once __DIR__ . '/../config.php';
require_role(DEPT_RESOURCE_ROLES);
$pageTitle = 'Department at a Glance';
$activeSection = 'department_glance';
$msg = null; $err = null;

// ---- Faculty roster: create / delete / reorder ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_faculty'])) {
    csrf_check();
    $name = trim($_POST['full_name']);
    $desig = trim($_POST['designation']);
    $email = trim($_POST['email']);
    $userId = (int)($_POST['user_id'] ?? 0) ?: null;
    $photo = save_upload('photo', 'dept');
    $maxOrder = (int)$pdo->query('SELECT COALESCE(MAX(sort_order),0) FROM dept_faculty')->fetchColumn();
    $pdo->prepare('INSERT INTO dept_faculty (user_id, full_name, designation, email, photo, sort_order) VALUES (?,?,?,?,?,?)')
        ->execute([$userId, $name, $desig, $email, $photo, $maxOrder + 1]);
    audit($pdo, 'add_dept_faculty', $name);
    $msg = 'Faculty member added. They (or you) can now fill in their full profile, resume, papers, and service history.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_faculty'])) {
    csrf_check();
    $id = (int)$_POST['id'];
    $pdo->prepare('DELETE FROM dept_faculty WHERE id=?')->execute([$id]);
    audit($pdo, 'delete_dept_faculty', "#$id");
    $msg = 'Faculty member removed.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['move_faculty'])) {
    csrf_check();
    $id = (int)$_POST['id'];
    $dir = $_POST['direction'] === 'up' ? 'up' : 'down';
    $cur = $pdo->prepare('SELECT sort_order FROM dept_faculty WHERE id=?');
    $cur->execute([$id]);
    $curOrder = $cur->fetchColumn();
    $cmp = $dir === 'up' ? '<' : '>';
    $ord = $dir === 'up' ? 'DESC' : 'ASC';
    $nStmt = $pdo->prepare("SELECT id, sort_order FROM dept_faculty WHERE sort_order $cmp ? ORDER BY sort_order $ord LIMIT 1");
    $nStmt->execute([$curOrder]);
    $neighbor = $nStmt->fetch();
    if ($neighbor) {
        $pdo->prepare('UPDATE dept_faculty SET sort_order=? WHERE id=?')->execute([$neighbor['sort_order'], $id]);
        $pdo->prepare('UPDATE dept_faculty SET sort_order=? WHERE id=?')->execute([$curOrder, $neighbor['id']]);
    }
}

// ---- Facilities (labs / classrooms) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_facility'])) {
    csrf_check();
    $name = trim($_POST['facility_name']);
    $type = in_array($_POST['facility_type'], ['lab','classroom','other'], true) ? $_POST['facility_type'] : 'lab';
    $desc = trim($_POST['facility_description']);
    $photo = save_upload('facility_photo', 'dept');
    $maxOrder = (int)$pdo->query('SELECT COALESCE(MAX(sort_order),0) FROM dept_facilities')->fetchColumn();
    $pdo->prepare('INSERT INTO dept_facilities (name, type, description, photo, sort_order) VALUES (?,?,?,?,?)')
        ->execute([$name, $type, $desc, $photo, $maxOrder + 1]);
    audit($pdo, 'add_dept_facility', $name);
    $msg = 'Facility added.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_facility'])) {
    csrf_check();
    $id = (int)$_POST['id'];
    $pdo->prepare('DELETE FROM dept_facilities WHERE id=?')->execute([$id]);
    audit($pdo, 'delete_dept_facility', "#$id");
    $msg = 'Facility removed. Any equipment assigned to it is now unassigned, not deleted.';
}

// ---- Equipment / components ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_equipment'])) {
    csrf_check();
    $name = trim($_POST['equip_name']);
    $qty = max(0, (int)$_POST['quantity']);
    $mfr = trim($_POST['manufacturer']);
    $model = trim($_POST['model_no']);
    $notes = trim($_POST['equip_notes']);
    $facilityId = (int)($_POST['facility_id'] ?? 0) ?: null;
    $photo = save_upload('equip_photo', 'dept');
    $pdo->prepare('INSERT INTO dept_equipment (facility_id, name, quantity, manufacturer, model_no, notes, photo) VALUES (?,?,?,?,?,?,?)')
        ->execute([$facilityId, $name, $qty, $mfr, $model, $notes, $photo]);
    audit($pdo, 'add_dept_equipment', "$name x$qty");
    $msg = 'Equipment added.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_equipment'])) {
    csrf_check();
    $id = (int)$_POST['id'];
    $pdo->prepare('DELETE FROM dept_equipment WHERE id=?')->execute([$id]);
    audit($pdo, 'delete_dept_equipment', "#$id");
    $msg = 'Equipment removed.';
}

$faculty = $pdo->query('SELECT * FROM dept_faculty ORDER BY sort_order')->fetchAll();
$facilities = $pdo->query('SELECT * FROM dept_facilities ORDER BY sort_order')->fetchAll();
$equipment = $pdo->query(
    'SELECT e.*, f.name AS facility_name FROM dept_equipment e
     LEFT JOIN dept_facilities f ON f.id = e.facility_id ORDER BY e.id DESC'
)->fetchAll();
$linkableUsers = $pdo->query("SELECT id, full_name, username FROM users WHERE status='approved' ORDER BY full_name")->fetchAll();

require __DIR__ . '/layout_header.php';
?>
<h1>Department at a Glance</h1>
<p class="muted">Manage the faculty roster, labs/classrooms, and equipment shown on the public "Department at a Glance" page.</p>
<?php if ($msg): ?><div class="alert alert-ok"><?= h($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-err"><?= h($err) ?></div><?php endif; ?>

<h2 style="margin-top:28px">Faculty Roster</h2>
<div class="card form-card" style="max-width:560px">
  <h3>Add Faculty Member</h3>
  <p class="muted" style="font-size:13px">This creates the card. Bio, resume, papers, and service history are filled in separately — by you or by the faculty member if you link their account.</p>
  <form method="POST" enctype="multipart/form-data" class="stack">
    <?= csrf_field() ?>
    <div class="field"><label>Full Name</label><input name="full_name" required></div>
    <div class="field"><label>Current Designation</label><input name="designation" placeholder="e.g. Associate Professor" required></div>
    <div class="field"><label>Email</label><input type="email" name="email"></div>
    <div class="field"><label>Link to Account (lets them edit their own profile)</label>
      <select name="user_id">
        <option value="">Not linked</option>
        <?php foreach ($linkableUsers as $lu): ?>
          <option value="<?= (int)$lu['id'] ?>"><?= h($lu['full_name']) ?> (<?= h($lu['username']) ?>)</option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field"><label>Photo</label><input type="file" name="photo" accept="image/*"></div>
    <button class="btn btn-primary" type="submit" name="add_faculty">Add Faculty Member</button>
  </form>
</div>

<table class="admin-table" style="margin-top:18px">
  <tr><th>Name</th><th>Designation</th><th>Linked Account</th><th></th></tr>
  <?php foreach ($faculty as $f): ?>
    <tr>
      <td><?= h($f['full_name']) ?></td>
      <td class="muted"><?= h($f['designation']) ?></td>
      <td class="muted mono" style="font-size:12px"><?= $f['user_id'] ? '#' . (int)$f['user_id'] : 'not linked' ?></td>
      <td style="white-space:nowrap">
        <a class="btn btn-outline btn-sm" href="<?= BASE_URL ?>/admin/faculty_edit.php?id=<?= (int)$f['id'] ?>">Edit Full Profile</a>
        <form method="POST" style="display:inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$f['id'] ?>"><input type="hidden" name="direction" value="up">
          <button class="btn btn-outline btn-sm" type="submit" name="move_faculty">&uarr;</button></form>
        <form method="POST" style="display:inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$f['id'] ?>"><input type="hidden" name="direction" value="down">
          <button class="btn btn-outline btn-sm" type="submit" name="move_faculty">&darr;</button></form>
        <form method="POST" style="display:inline" onsubmit="return confirm('Remove this faculty member and their entire profile (papers, service history, resume)?')">
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
          <button class="btn btn-danger btn-sm" type="submit" name="delete_faculty">Delete</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$faculty): ?><tr><td colspan="4" class="muted">No faculty added yet.</td></tr><?php endif; ?>
</table>

<h2 style="margin-top:36px">Labs &amp; Classrooms</h2>
<div class="card form-card" style="max-width:560px">
  <h3>Add Facility</h3>
  <form method="POST" enctype="multipart/form-data" class="stack">
    <?= csrf_field() ?>
    <div class="field"><label>Name</label><input name="facility_name" placeholder="e.g. Power Electronics Lab" required></div>
    <div class="field"><label>Type</label>
      <select name="facility_type"><option value="lab">Lab</option><option value="classroom">Classroom</option><option value="other">Other</option></select>
    </div>
    <div class="field"><label>Description</label><textarea name="facility_description"></textarea></div>
    <div class="field"><label>Photo</label><input type="file" name="facility_photo" accept="image/*"></div>
    <button class="btn btn-primary" type="submit" name="add_facility">Add Facility</button>
  </form>
</div>
<table class="admin-table" style="margin-top:18px">
  <tr><th>Name</th><th>Type</th><th></th></tr>
  <?php foreach ($facilities as $fac): ?>
    <tr>
      <td><?= h($fac['name']) ?></td>
      <td class="muted"><?= h(ucfirst($fac['type'])) ?></td>
      <td>
        <form method="POST" onsubmit="return confirm('Delete this facility? Equipment assigned to it will be unassigned, not deleted.')">
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$fac['id'] ?>">
          <button class="btn btn-danger btn-sm" type="submit" name="delete_facility">Delete</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$facilities): ?><tr><td colspan="3" class="muted">No labs/classrooms added yet.</td></tr><?php endif; ?>
</table>

<h2 style="margin-top:36px">Equipment &amp; Components</h2>
<div class="card form-card" style="max-width:560px">
  <h3>Add Equipment</h3>
  <form method="POST" enctype="multipart/form-data" class="stack">
    <?= csrf_field() ?>
    <div class="field"><label>Name</label><input name="equip_name" placeholder="e.g. Digital Storage Oscilloscope" required></div>
    <div class="field"><label>Quantity</label><input type="number" name="quantity" min="0" value="1" required></div>
    <div class="field"><label>Manufacturer</label><input name="manufacturer" placeholder="e.g. Tektronix"></div>
    <div class="field"><label>Model No.</label><input name="model_no"></div>
    <div class="field"><label>Belongs To (optional)</label>
      <select name="facility_id">
        <option value="">Not assigned to a specific room</option>
        <?php foreach ($facilities as $fac): ?>
          <option value="<?= (int)$fac['id'] ?>"><?= h($fac['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field"><label>Notes</label><input name="equip_notes"></div>
    <div class="field"><label>Photo</label><input type="file" name="equip_photo" accept="image/*"></div>
    <button class="btn btn-primary" type="submit" name="add_equipment">Add Equipment</button>
  </form>
</div>
<table class="admin-table" style="margin-top:18px">
  <tr><th>Name</th><th>Qty</th><th>Manufacturer</th><th>Model</th><th>Location</th><th></th></tr>
  <?php foreach ($equipment as $eq): ?>
    <tr>
      <td><?= h($eq['name']) ?></td>
      <td class="mono"><?= (int)$eq['quantity'] ?></td>
      <td class="muted"><?= h($eq['manufacturer'] ?: '—') ?></td>
      <td class="muted mono" style="font-size:12px"><?= h($eq['model_no'] ?: '—') ?></td>
      <td class="muted"><?= h($eq['facility_name'] ?: '—') ?></td>
      <td>
        <form method="POST" onsubmit="return confirm('Delete this equipment entry?')">
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$eq['id'] ?>">
          <button class="btn btn-danger btn-sm" type="submit" name="delete_equipment">Delete</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$equipment): ?><tr><td colspan="6" class="muted">No equipment added yet.</td></tr><?php endif; ?>
</table>
<?php require __DIR__ . '/layout_footer.php'; ?>
