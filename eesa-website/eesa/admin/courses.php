<?php
require_once __DIR__ . '/../config.php';
require_role(CONTENT_ADMIN_ROLES);
$pageTitle = 'Manage Courses';
$activeSection = 'courses';
$msg = null; $err = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_subject'])) {
    csrf_check();
    $title = trim($_POST['title']);
    $desc = trim($_POST['description']);
    $slug = slugify($title) . '-' . substr(md5(microtime()), 0, 5);
    $maxOrder = (int)$pdo->query('SELECT COALESCE(MAX(sort_order),0) FROM course_subjects')->fetchColumn();
    $pdo->prepare('INSERT INTO course_subjects (title, slug, description, sort_order) VALUES (?,?,?,?)')
        ->execute([$title, $slug, $desc, $maxOrder + 1]);
    $msg = 'Subject created.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_subject'])) {
    csrf_check();
    $pdo->prepare('DELETE FROM course_subjects WHERE id=?')->execute([(int)$_POST['id']]);
    $msg = 'Subject deleted.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_video'])) {
    csrf_check();
    $subjectId = (int)$_POST['subject_id'];
    $title = trim($_POST['video_title']);
    $ytId = youtube_id_from_url(trim($_POST['youtube_url']));
    if (!$ytId) {
        $err = 'Could not read a valid YouTube link or ID.';
    } else {
        $stmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order),0) FROM course_videos WHERE subject_id=?');
        $stmt->execute([$subjectId]);
        $maxOrder = (int)$stmt->fetchColumn();
        $pdo->prepare('INSERT INTO course_videos (subject_id, title, youtube_id, sort_order) VALUES (?,?,?,?)')
            ->execute([$subjectId, $title, $ytId, $maxOrder + 1]);
        $msg = 'Video added.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_video'])) {
    csrf_check();
    $pdo->prepare('DELETE FROM course_videos WHERE id=?')->execute([(int)$_POST['id']]);
    $msg = 'Video removed.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['move_video'])) {
    csrf_check();
    $id = (int)$_POST['id'];
    $dir = $_POST['direction'] === 'up' ? 'up' : 'down';
    $cur = $pdo->prepare('SELECT subject_id, sort_order FROM course_videos WHERE id=?');
    $cur->execute([$id]);
    $row = $cur->fetch();
    if ($row) {
        $cmp = $dir === 'up' ? '<' : '>';
        $ord = $dir === 'up' ? 'DESC' : 'ASC';
        $nStmt = $pdo->prepare("SELECT id, sort_order FROM course_videos WHERE subject_id=? AND sort_order $cmp ? ORDER BY sort_order $ord LIMIT 1");
        $nStmt->execute([$row['subject_id'], $row['sort_order']]);
        $neighbor = $nStmt->fetch();
        if ($neighbor) {
            $pdo->prepare('UPDATE course_videos SET sort_order=? WHERE id=?')->execute([$neighbor['sort_order'], $id]);
            $pdo->prepare('UPDATE course_videos SET sort_order=? WHERE id=?')->execute([$row['sort_order'], $neighbor['id']]);
        }
    }
}

$subjects = $pdo->query('SELECT * FROM course_subjects ORDER BY sort_order')->fetchAll();
require __DIR__ . '/layout_header.php';
?>
<h1>Courses</h1>
<?php if ($msg): ?><div class="alert alert-ok"><?= h($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-err"><?= h($err) ?></div><?php endif; ?>

<div class="card form-card" style="max-width:520px">
  <h3>New Subject</h3>
  <form method="POST" class="stack">
    <?= csrf_field() ?>
    <div class="field"><label>Subject Title</label><input name="title" required></div>
    <div class="field"><label>Description</label><textarea name="description"></textarea></div>
    <button class="btn btn-primary" type="submit" name="create_subject">Create Subject</button>
  </form>
</div>

<?php foreach ($subjects as $s):
    $vidStmt = $pdo->prepare('SELECT * FROM course_videos WHERE subject_id=? ORDER BY sort_order');
    $vidStmt->execute([$s['id']]);
    $videos = $vidStmt->fetchAll();
?>
  <div class="card" style="margin:20px 0">
    <div style="display:flex;justify-content:space-between;align-items:center">
      <h3><?= h($s['title']) ?></h3>
      <form method="POST" onsubmit="return confirm('Delete this subject and all its videos?')">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
        <button class="btn btn-danger btn-sm" type="submit" name="delete_subject">Delete Subject</button>
      </form>
    </div>
    <table class="admin-table">
      <?php foreach ($videos as $v): ?>
        <tr>
          <td style="width:110px"><img src="https://img.youtube.com/vi/<?= h($v['youtube_id']) ?>/mqdefault.jpg" style="width:100px;border-radius:6px;display:block"></td>
          <td><?= h($v['title']) ?><br><span class="muted mono" style="font-size:11px"><?= h($v['youtube_id']) ?></span></td>
          <td style="white-space:nowrap">
            <form method="POST" style="display:inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$v['id'] ?>"><input type="hidden" name="direction" value="up">
              <button class="btn btn-outline btn-sm" type="submit" name="move_video">&uarr;</button></form>
            <form method="POST" style="display:inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$v['id'] ?>"><input type="hidden" name="direction" value="down">
              <button class="btn btn-outline btn-sm" type="submit" name="move_video">&darr;</button></form>
            <form method="POST" style="display:inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$v['id'] ?>">
              <button class="btn btn-danger btn-sm" type="submit" name="delete_video">Remove</button></form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$videos): ?><tr><td class="muted">No videos yet.</td></tr><?php endif; ?>
    </table>
    <form method="POST" style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end">
      <?= csrf_field() ?><input type="hidden" name="subject_id" value="<?= (int)$s['id'] ?>">
      <div class="field" style="margin-bottom:0"><input name="video_title" placeholder="Video title" required></div>
      <div class="field" style="margin-bottom:0"><input name="youtube_url" placeholder="YouTube URL or ID" required style="width:280px"></div>
      <button class="btn btn-outline btn-sm" type="submit" name="add_video">Add Video</button>
    </form>
  </div>
<?php endforeach; ?>
<?php require __DIR__ . '/layout_footer.php'; ?>
