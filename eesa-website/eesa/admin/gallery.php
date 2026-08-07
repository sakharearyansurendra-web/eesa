<?php
require_once __DIR__ . '/../config.php';
require_role(CONTENT_ADMIN_ROLES);
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

// ---- Remove a single photo entirely ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_photo'])) {
    csrf_check();
    $id = (int)$_POST['photo_id'];
    $stmt = $pdo->prepare('SELECT filename FROM gallery_photos WHERE id=?');
    $stmt->execute([$id]);
    $fname = $stmt->fetchColumn();
    $pdo->prepare('DELETE FROM gallery_photos WHERE id=?')->execute([$id]);
    if ($fname) {
        $path = __DIR__ . '/../uploads/gallery/' . $fname;
        if (is_file($path)) unlink($path);
    }
    $msg = 'Photo removed.';
}

// ---- Toggle whether a photo appears in the homepage slideshow ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_home_feature'])) {
    csrf_check();
    $id = (int)$_POST['photo_id'];
    $cur = $pdo->prepare('SELECT featured_home FROM gallery_photos WHERE id=?');
    $cur->execute([$id]);
    $curVal = (int)$cur->fetchColumn();
    if ($curVal) {
        $pdo->prepare('UPDATE gallery_photos SET featured_home=0, home_sort_order=0 WHERE id=?')->execute([$id]);
        $msg = 'Removed from homepage slideshow.';
    } else {
        $maxOrder = (int)$pdo->query('SELECT COALESCE(MAX(home_sort_order),0) FROM gallery_photos WHERE featured_home=1')->fetchColumn();
        $pdo->prepare('UPDATE gallery_photos SET featured_home=1, home_sort_order=? WHERE id=?')->execute([$maxOrder + 1, $id]);
        $msg = 'Added to homepage slideshow.';
    }
    audit($pdo, 'toggle_home_feature', "photo #$id");
}

// ---- Reorder a featured photo's position in the slideshow ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['move_home_photo'])) {
    csrf_check();
    $id = (int)$_POST['photo_id'];
    $dir = $_POST['direction'] === 'up' ? 'up' : 'down';
    $cur = $pdo->prepare('SELECT home_sort_order FROM gallery_photos WHERE id=? AND featured_home=1');
    $cur->execute([$id]);
    $curOrder = $cur->fetchColumn();
    if ($curOrder !== false) {
        $cmp = $dir === 'up' ? '<' : '>';
        $ord = $dir === 'up' ? 'DESC' : 'ASC';
        $nStmt = $pdo->prepare("SELECT id, home_sort_order FROM gallery_photos WHERE featured_home=1 AND home_sort_order $cmp ? ORDER BY home_sort_order $ord LIMIT 1");
        $nStmt->execute([$curOrder]);
        $neighbor = $nStmt->fetch();
        if ($neighbor) {
            $pdo->prepare('UPDATE gallery_photos SET home_sort_order=? WHERE id=?')->execute([$neighbor['home_sort_order'], $id]);
            $pdo->prepare('UPDATE gallery_photos SET home_sort_order=? WHERE id=?')->execute([$curOrder, $neighbor['id']]);
        }
    }
}

$events = $pdo->query('SELECT * FROM gallery_events ORDER BY event_date DESC')->fetchAll();

$featuredPhotos = $pdo->query(
    'SELECT gp.*, ge.event_name FROM gallery_photos gp
     JOIN gallery_events ge ON ge.id = gp.gallery_event_id
     WHERE gp.featured_home = 1 ORDER BY gp.home_sort_order, gp.id'
)->fetchAll();

require __DIR__ . '/layout_header.php';
?>
<h1>Gallery</h1>
<?php if ($msg): ?><div class="alert alert-ok"><?= h($msg) ?></div><?php endif; ?>

<div class="card" style="margin-bottom:24px">
  <h3>Homepage Slideshow (<?= count($featuredPhotos) ?> selected)</h3>
  <p class="muted" style="font-size:13px">Only these photos appear in the homepage slideshow, in this order, changing every 10 seconds. Toggle "Feature on Homepage" on any photo below, from any event, to add it here.</p>
  <?php if (!$featuredPhotos): ?>
    <p class="muted">Nothing selected yet — the slideshow stays hidden on the homepage until you feature at least one photo.</p>
  <?php else: ?>
    <div class="grid grid-4">
      <?php foreach ($featuredPhotos as $fp): ?>
        <div>
          <img class="thumb" src="<?= BASE_URL ?>/uploads/gallery/<?= h($fp['filename']) ?>">
          <p class="muted mono" style="font-size:11px;margin:4px 0"><?= h($fp['event_name']) ?></p>
          <div style="display:flex;gap:6px">
            <form method="POST" style="display:inline"><?= csrf_field() ?><input type="hidden" name="photo_id" value="<?= (int)$fp['id'] ?>"><input type="hidden" name="direction" value="up">
              <button class="btn btn-outline btn-sm" type="submit" name="move_home_photo">&uarr;</button></form>
            <form method="POST" style="display:inline"><?= csrf_field() ?><input type="hidden" name="photo_id" value="<?= (int)$fp['id'] ?>"><input type="hidden" name="direction" value="down">
              <button class="btn btn-outline btn-sm" type="submit" name="move_home_photo">&darr;</button></form>
            <form method="POST" style="display:inline"><?= csrf_field() ?><input type="hidden" name="photo_id" value="<?= (int)$fp['id'] ?>">
              <button class="btn btn-danger btn-sm" type="submit" name="toggle_home_feature">Remove</button></form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

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
<?php foreach ($events as $e):
    $photosStmt = $pdo->prepare('SELECT * FROM gallery_photos WHERE gallery_event_id=? ORDER BY sort_order, id');
    $photosStmt->execute([$e['id']]);
    $photos = $photosStmt->fetchAll();
?>
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

    <?php if ($photos): ?>
      <div class="grid grid-4" style="margin-top:14px">
        <?php foreach ($photos as $p): ?>
          <div>
            <img class="thumb" src="<?= BASE_URL ?>/uploads/gallery/<?= h($p['filename']) ?>">
            <div style="display:flex;gap:6px;margin-top:4px;flex-wrap:wrap">
              <form method="POST" style="display:inline">
                <?= csrf_field() ?><input type="hidden" name="photo_id" value="<?= (int)$p['id'] ?>">
                <button class="btn <?= $p['featured_home'] ? 'btn-primary' : 'btn-outline' ?> btn-sm" type="submit" name="toggle_home_feature">
                  <?= $p['featured_home'] ? '✓ On Homepage' : 'Feature on Homepage' ?>
                </button>
              </form>
              <form method="POST" style="display:inline" onsubmit="return confirm('Remove this photo?')">
                <?= csrf_field() ?><input type="hidden" name="photo_id" value="<?= (int)$p['id'] ?>">
                <button class="btn btn-danger btn-sm" type="submit" name="delete_photo">Remove</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p class="muted" style="margin-top:10px">No photos in this event yet.</p>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" style="margin-top:14px;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
      <?= csrf_field() ?><input type="hidden" name="event_id" value="<?= (int)$e['id'] ?>">
      <input type="file" name="more_photos[]" accept="image/*" multiple>
      <button class="btn btn-outline btn-sm" type="submit" name="add_photos">Add Photos</button>
    </form>
  </div>
<?php endforeach; ?>
<?php require __DIR__ . '/layout_footer.php'; ?>
