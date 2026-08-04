<?php
require_once __DIR__ . '/../config.php';
require_role(CONTENT_ADMIN_ROLES);
$pageTitle = 'Manage Announcements';
$activeSection = 'announcements';
$msg = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_announcement'])) {
    csrf_check();
    $title = trim($_POST['title']);
    $body = $_POST['body'];
    $eventDt = $_POST['event_datetime'] ?: null;
    $regClose = $_POST['registration_close'] ?: null;
    $regOpen = isset($_POST['registration_open']) ? 1 : 0;
    $venue = trim($_POST['venue']);
    $cover = save_upload('cover_image', 'activities');
    $slug = slugify($title) . '-' . substr(md5(microtime()), 0, 5);

    $pdo->prepare('INSERT INTO announcements (title, slug, body, cover_image, event_datetime, registration_open, registration_close, venue, created_by)
                    VALUES (?,?,?,?,?,?,?,?,?)')
        ->execute([$title, $slug, $body, $cover, $eventDt, $regOpen, $regClose, $venue, current_user()['id']]);
    audit($pdo, 'create_announcement', $title);
    $msg = 'Announcement posted.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_announcement'])) {
    csrf_check();
    $id = (int)$_POST['id'];
    $pdo->prepare('DELETE FROM announcements WHERE id=?')->execute([$id]);
    audit($pdo, 'delete_announcement', "#$id");
    $msg = 'Deleted.';
}

$list = $pdo->query('
    SELECT a.*,
           (SELECT COUNT(*) FROM announcement_registrations ar WHERE ar.announcement_id = a.id) AS reg_count
    FROM announcements a
    ORDER BY a.created_at DESC
')->fetchAll();
require __DIR__ . '/layout_header.php';
?>
<h1>Announcements</h1>
<?php if ($msg): ?><div class="alert alert-ok"><?= h($msg) ?></div><?php endif; ?>

<div class="card form-card" style="max-width:640px">
  <h3>New Announcement</h3>
  <form method="POST" enctype="multipart/form-data" class="stack">
    <?= csrf_field() ?>
    <div class="field"><label>Title</label><input name="title" required></div>
    <div class="field"><label>Body</label><textarea name="body" required></textarea></div>
    <div class="field"><label>Cover Image</label><input type="file" name="cover_image" accept="image/*"></div>
    <div class="field"><label>Event Date &amp; Time (IST, leave blank for a plain notice)</label>
      <input type="datetime-local" name="event_datetime"></div>
    <div class="field"><label>Venue</label><input name="venue"></div>
    <div class="field">
      <label><input type="checkbox" name="registration_open" style="width:auto"> Allow registrations for this event</label>
    </div>
    <div class="field"><label>Registration Closes At (also used as "completed" cutoff)</label>
      <input type="datetime-local" name="registration_close"></div>
    <button class="btn btn-primary" type="submit" name="save_announcement">Post Announcement</button>
  </form>
</div>

<h2 style="margin-top:32px">All Announcements</h2>
<table class="admin-table">
  <tr><th>Title</th><th>Event</th><th>Status</th><th></th></tr>
  <?php foreach ($list as $a):
      $status = announcement_status($a['event_datetime'], $a['registration_close']); ?>
    <tr>
      <td><?= h($a['title']) ?></td>
      <td class="mono"><?= $a['event_datetime'] ? h(date('d M Y, h:i A', strtotime($a['event_datetime']))) : '—' ?></td>
      <td><?= status_badge($status) ?></td>
      <td>
        <a class="btn btn-outline btn-sm" href="<?= BASE_URL ?>/pages/announcement_view.php?slug=<?= h($a['slug']) ?>" target="_blank">View</a>
        <?php if ($a['registration_open']): ?>
          <a class="btn btn-outline btn-sm" href="announcement_registrations.php?id=<?= (int)$a['id'] ?>">
            Registrations (<?= (int)$a['reg_count'] ?>)
          </a>
        <?php endif; ?>
        <form method="POST" style="display:inline" onsubmit="return confirm('Delete this announcement?')">
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
          <button class="btn btn-danger btn-sm" type="submit" name="delete_announcement">Delete</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
</table>
<?php require __DIR__ . '/layout_footer.php'; ?>
