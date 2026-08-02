<?php
require_once __DIR__ . '/../config.php';
require_role(CONTENT_ADMIN_ROLES);
$pageTitle = 'Manage Department';
$activeSection = 'department';
$msg = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_info'])) {
    csrf_check();
    $about = $_POST['about'];
    $hodName = trim($_POST['hod_name']);
    $hodMsg = $_POST['hod_message'];
    $hodPhoto = save_upload('hod_photo', 'team') ?: null;
    if ($hodPhoto) {
        $pdo->prepare('UPDATE department_info SET about=?, hod_name=?, hod_message=?, hod_photo=? WHERE id=1')
            ->execute([$about, $hodName, $hodMsg, $hodPhoto]);
    } else {
        $pdo->prepare('UPDATE department_info SET about=?, hod_name=?, hod_message=? WHERE id=1')
            ->execute([$about, $hodName, $hodMsg]);
    }
    $msg = 'Department info updated.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_image'])) {
    csrf_check();
    $f = save_upload('image', 'gallery');
    if ($f) $pdo->prepare('INSERT INTO department_images (filename, caption) VALUES (?,?)')->execute([$f, trim($_POST['caption'])]);
    $msg = 'Image added.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_staff'])) {
    csrf_check();
    $photo = save_upload('photo', 'team');
    $pdo->prepare('INSERT INTO staff (name, designation, photo, email) VALUES (?,?,?,?)')
        ->execute([trim($_POST['name']), trim($_POST['designation']), $photo, trim($_POST['email'])]);
    $msg = 'Staff member added.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_image'])) {
    csrf_check(); $pdo->prepare('DELETE FROM department_images WHERE id=?')->execute([(int)$_POST['id']]); $msg='Image removed.';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_staff'])) {
    csrf_check(); $pdo->prepare('DELETE FROM staff WHERE id=?')->execute([(int)$_POST['id']]); $msg='Staff removed.';
}

$dept = $pdo->query('SELECT * FROM department_info WHERE id=1')->fetch();
$images = $pdo->query('SELECT * FROM department_images ORDER BY id DESC')->fetchAll();
$staff = $pdo->query('SELECT * FROM staff ORDER BY id DESC')->fetchAll();
require __DIR__ . '/layout_header.php';
?>
<h1>Department</h1>
<?php if ($msg): ?><div class="alert alert-ok"><?= h($msg) ?></div><?php endif; ?>

<div class="card form-card" style="max-width:640px">
  <h3>About &amp; HOD Message</h3>
  <form method="POST" enctype="multipart/form-data" class="stack">
    <?= csrf_field() ?>
    <div class="field"><label>About the Department</label><textarea name="about"><?= h($dept['about']) ?></textarea></div>
    <div class="field"><label>HOD Name</label><input name="hod_name" value="<?= h($dept['hod_name']) ?>"></div>
    <div class="field"><label>HOD Message</label><textarea name="hod_message"><?= h($dept['hod_message']) ?></textarea></div>
    <div class="field"><label>HOD Photo</label><input type="file" name="hod_photo" accept="image/*"></div>
    <button class="btn btn-primary" type="submit" name="save_info">Save</button>
  </form>
</div>

<div class="card form-card" style="max-width:520px;margin-top:24px">
  <h3>Add Department Image</h3>
  <form method="POST" enctype="multipart/form-data" class="stack">
    <?= csrf_field() ?>
    <div class="field"><label>Image</label><input type="file" name="image" accept="image/*" required></div>
    <div class="field"><label>Caption</label><input name="caption"></div>
    <button class="btn btn-outline" type="submit" name="add_image">Add</button>
  </form>
  <div class="grid grid-4" style="margin-top:16px">
    <?php foreach ($images as $img): ?>
      <div>
        <img class="thumb" src="<?= BASE_URL ?>/uploads/gallery/<?= h($img['filename']) ?>">
        <form method="POST"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$img['id'] ?>">
          <button class="btn btn-danger btn-sm" type="submit" name="delete_image" style="width:100%;margin-top:4px">Remove</button>
        </form>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="card form-card" style="max-width:520px;margin-top:24px">
  <h3>Faculty &amp; Staff</h3>
  <form method="POST" enctype="multipart/form-data" class="stack">
    <?= csrf_field() ?>
    <div class="field"><label>Name</label><input name="name" required></div>
    <div class="field"><label>Designation</label><input name="designation" required></div>
    <div class="field"><label>Email</label><input name="email"></div>
    <div class="field"><label>Photo</label><input type="file" name="photo" accept="image/*"></div>
    <button class="btn btn-outline" type="submit" name="add_staff">Add Staff</button>
  </form>
  <table class="admin-table" style="margin-top:12px">
    <?php foreach ($staff as $s): ?>
      <tr>
        <td><?= h($s['name']) ?></td><td><?= h($s['designation']) ?></td>
        <td><form method="POST"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
          <button class="btn btn-danger btn-sm" type="submit" name="delete_staff">Remove</button></form></td>
      </tr>
    <?php endforeach; ?>
  </table>
</div>
<?php require __DIR__ . '/layout_footer.php'; ?>
