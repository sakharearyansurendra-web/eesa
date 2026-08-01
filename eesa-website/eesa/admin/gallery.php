<?php
require_once __DIR__ . '/../config.php';
require_role(['super_admin','admin']);
$pageTitle = 'Manage Gallery';
$activeSection = 'gallery';
$msg = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_event'])) {
    csrf_check();
    $name = trim($_POST['event_name']);
    $date = $_POST['event_date'];
    $cover = save_upload('cover_image', 'gallery');
    $pdo->prepare('INSERT INTO gallery_events (event_name, event_date, cover_image) VALUES (?,?,?)')
        ->execute([$name, $date, $cover]);
    $eventId = $pdo->lastInsertId();
    if (!empty($_FILES['photos']['name'][0])) {
        foreach ($_FILES['photos']['name'] as $i => $n) {
            if ($_FILES['photos']['error'][$i] !== UPLOAD_ERR_OK) continue;
            $ext = strtolower(pathinfo($n, PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg','jpeg','png','webp'], true)) continue;
            $fname = bin2hex(random_bytes(12)) . '.' . $ext;
            move_uploaded_file($_FILES['photos']['tmp_name'][$i], __DIR__ . '/../uploads/gallery/' . $fname);
            $pdo->prepare('INSERT INTO gallery_photos (gallery_event_id, filename, sort_order) VALUES (?,?,?)')->execute([$eventId, $fname, $i]);
        }
    }
    audit($pdo, 'create_gallery_event', $name);
    $msg = 'Gallery event created.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_photos'])) {
    csrf_check();
    $eventId = (int)$_POST['event_id'];
    if (!empty($_FILES['more_photos']['name'][0])) {
        foreach ($_FILES['more_photos']['name'] as $i => $n) {
            if ($_FILES['more_photos']['error'][$i] !== UPLOAD_ERR_OK) continue;
            $ext = strtolower(pathinfo($n, PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg','jpeg','png','webp'], true)) continue;
            $fname = bin2hex(random_bytes(12)) . '.' . $ext;
            move_uploaded_file($_FILES['more_photos']['tmp_name'][$i], __DIR__ . '/../uploads/gallery/' . $fname);
            $pdo->prepare('INSERT INTO gallery_photos (gallery_event_id, filename) VALUES (?,?)')->execute([$eventId, $fname]);
        }
    }
    $msg = 'Photos added.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_event'])) {
    csrf_check();
    $id = (int)$_POST['id'];
    $pdo->prepare('DELETE FROM gallery_events WHERE id=?')->execute([$id]);
    audit($pdo, 'delete_gallery_event', "#$id");
    $msg = 'Deleted.';
}

$events = $pdo->query('SELECT * FROM gallery_events ORDER BY event_date DESC')->fetchAll();
require __DIR__ . '/layout_header.php';
?>
<h1>Gallery</h1>
<?php if ($msg): ?><div class="alert alert-ok"><?= h($msg) ?></div><?php endif; ?>

<div class="card form-card" style="max-width:640px">
  <h3>New Gallery Event</h3>
  <form method="POST" enctype="multipart/form-data" class="stack">
    <?= csrf_field() ?>
    <div class="field"><label>Event Name</label><input name="event_name" required></div>
    <div class="field"><label>Event Date</label><input type="date" name="event_date" required></div>
    <div class="field"><label>Cover Image</label><input type="file" name="cover_image" accept="image/*"></div>
    <div class="field"><label>Photos</label><input type="file" name="photos[]" accept="image/*" multiple></div>
    <button class="btn btn-primary" type="submit" name="create_event">Create Event</button>
  </form>
</div>

<h2 style="margin-top:32px">All Events</h2>
<?php foreach ($events as $e): ?>
  <div class="card" style="margin-bottom:14px">
    <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:12px">
      <div>
        <h3><?= h($e['event_name']) ?></h3>
        <p class="mono muted"><?= h(date('d M Y', strtotime($e['event_date']))) ?></p>
      </div>
      <div style="display:flex;gap:8px;align-items:flex-start">
        <a class="btn btn-outline btn-sm" href="<?= BASE_URL ?>/pages/gallery_view.php?id=<?= (int)$e['id'] ?>" target="_blank">View</a>
        <form method="POST" onsubmit="return confirm('Delete this whole event and its photos?')">
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$e['id'] ?>">
          <button class="btn btn-danger btn-sm" type="submit" name="delete_event">Delete</button>
        </form>
      </div>
    </div>
    <form method="POST" enctype="multipart/form-data" style="margin-top:10px;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
      <?= csrf_field() ?><input type="hidden" name="event_id" value="<?= (int)$e['id'] ?>">
      <input type="file" name="more_photos[]" accept="image/*" multiple>
      <button class="btn btn-outline btn-sm" type="submit" name="add_photos">Add Photos</button>
    </form>
  </div>
<?php endforeach; ?>
<?php require __DIR__ . '/layout_footer.php'; ?>
