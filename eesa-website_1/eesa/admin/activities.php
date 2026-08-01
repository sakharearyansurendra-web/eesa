<?php
require_once __DIR__ . '/../config.php';
require_role(['super_admin','admin']);
$pageTitle = 'Manage Activities';
$activeSection = 'activities';
$msg = null;

$categories = $pdo->query('SELECT * FROM activity_categories ORDER BY label')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_activity'])) {
    csrf_check();
    $title = trim($_POST['title']);
    $catId = (int)$_POST['category_id'];
    $summary = trim($_POST['summary']);
    $content = $_POST['content'];
    $eventDate = $_POST['event_date'] ?: null;
    $cover = save_upload('cover_image', 'activities');
    $slug = slugify($title) . '-' . substr(md5(microtime()), 0, 5);

    $pdo->prepare('INSERT INTO activities (category_id, title, slug, summary, content, cover_image, event_date, created_by)
                    VALUES (?,?,?,?,?,?,?,?)')
        ->execute([$catId, $title, $slug, $summary, $content, $cover, $eventDate, current_user()['id']]);
    $activityId = $pdo->lastInsertId();

    // Multiple event photos
    if (!empty($_FILES['photos']['name'][0])) {
        foreach ($_FILES['photos']['name'] as $i => $name) {
            if ($_FILES['photos']['error'][$i] !== UPLOAD_ERR_OK) continue;
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg','jpeg','png','webp'], true)) continue;
            $fname = bin2hex(random_bytes(12)) . '.' . $ext;
            move_uploaded_file($_FILES['photos']['tmp_name'][$i], __DIR__ . '/../uploads/activities/' . $fname);
            $pdo->prepare('INSERT INTO activity_photos (activity_id, filename, sort_order) VALUES (?,?,?)')
                ->execute([$activityId, $fname, $i]);
        }
    }
    audit($pdo, 'create_activity', $title);
    $msg = 'Activity posted.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_activity'])) {
    csrf_check();
    $id = (int)$_POST['id'];
    $pdo->prepare('DELETE FROM activities WHERE id=?')->execute([$id]);
    audit($pdo, 'delete_activity', "#$id");
    $msg = 'Deleted.';
}

$list = $pdo->query('SELECT a.*, c.label FROM activities a JOIN activity_categories c ON c.id=a.category_id ORDER BY a.created_at DESC')->fetchAll();
require __DIR__ . '/layout_header.php';
?>
<h1>Activities</h1>
<?php if ($msg): ?><div class="alert alert-ok"><?= h($msg) ?></div><?php endif; ?>

<div class="card form-card" style="max-width:640px">
  <h3>New Activity Post</h3>
  <form method="POST" enctype="multipart/form-data" class="stack">
    <?= csrf_field() ?>
    <div class="field"><label>Title</label><input name="title" required></div>
    <div class="field"><label>Category</label>
      <select name="category_id" required>
        <?php foreach ($categories as $c): ?><option value="<?= (int)$c['id'] ?>"><?= h($c['label']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="field"><label>Short Summary (for cards)</label><input name="summary" maxlength="300"></div>
    <div class="field"><label>Full Content</label><textarea name="content" style="min-height:180px" required></textarea></div>
    <div class="field"><label>Event Date</label><input type="date" name="event_date"></div>
    <div class="field"><label>Cover Image</label><input type="file" name="cover_image" accept="image/*"></div>
    <div class="field"><label>Event Photos (multiple)</label><input type="file" name="photos[]" accept="image/*" multiple></div>
    <button class="btn btn-primary" type="submit" name="save_activity">Publish</button>
  </form>
</div>

<h2 style="margin-top:32px">All Activities</h2>
<table class="admin-table">
  <tr><th>Title</th><th>Category</th><th>Date</th><th></th></tr>
  <?php foreach ($list as $a): ?>
    <tr>
      <td><?= h($a['title']) ?></td>
      <td class="mono"><?= h($a['label']) ?></td>
      <td class="mono"><?= $a['event_date'] ? h(date('d M Y', strtotime($a['event_date']))) : '—' ?></td>
      <td>
        <a class="btn btn-outline btn-sm" href="<?= BASE_URL ?>/pages/activity_view.php?slug=<?= h($a['slug']) ?>" target="_blank">View</a>
        <form method="POST" style="display:inline" onsubmit="return confirm('Delete this activity?')">
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
          <button class="btn btn-danger btn-sm" type="submit" name="delete_activity">Delete</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
</table>
<?php require __DIR__ . '/layout_footer.php'; ?>
