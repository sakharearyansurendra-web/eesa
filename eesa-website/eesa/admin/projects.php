<?php
require_once __DIR__ . '/../config.php';
require_role(CONTENT_ADMIN_ROLES);
$pageTitle = 'Manage Projects';
$activeSection = 'projects';
$msg = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_project'])) {
    csrf_check();
    $title = trim($_POST['title']);
    $summary = trim($_POST['summary']);
    $desc = $_POST['description'];
    $status = $_POST['status'] === 'completed' ? 'completed' : 'ongoing';
    $cover = save_upload('cover_image', 'projects');
    $slug = slugify($title) . '-' . substr(md5(microtime()), 0, 5);
    $maxOrder = (int)$pdo->query('SELECT COALESCE(MAX(sort_order),0) FROM projects')->fetchColumn();
    $pdo->prepare('INSERT INTO projects (title, slug, summary, description, cover_image, status, sort_order, created_by) VALUES (?,?,?,?,?,?,?,?)')
        ->execute([$title, $slug, $summary, $desc, $cover, $status, $maxOrder + 1, current_user()['id']]);
    audit($pdo, 'create_project', $title);
    $msg = 'Project created.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_project'])) {
    csrf_check();
    $id = (int)$_POST['id'];
    $pdo->prepare('DELETE FROM projects WHERE id=?')->execute([$id]);
    audit($pdo, 'delete_project', "#$id");
    $msg = 'Project deleted.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['move_project'])) {
    csrf_check();
    $id = (int)$_POST['id'];
    $dir = $_POST['direction'] === 'up' ? 'up' : 'down';
    $cur = $pdo->prepare('SELECT sort_order FROM projects WHERE id=?');
    $cur->execute([$id]);
    $curOrder = $cur->fetchColumn();
    $cmp = $dir === 'up' ? '<' : '>';
    $ord = $dir === 'up' ? 'DESC' : 'ASC';
    $nStmt = $pdo->prepare("SELECT id, sort_order FROM projects WHERE sort_order $cmp ? ORDER BY sort_order $ord LIMIT 1");
    $nStmt->execute([$curOrder]);
    $neighbor = $nStmt->fetch();
    if ($neighbor) {
        $pdo->prepare('UPDATE projects SET sort_order=? WHERE id=?')->execute([$neighbor['sort_order'], $id]);
        $pdo->prepare('UPDATE projects SET sort_order=? WHERE id=?')->execute([$curOrder, $neighbor['id']]);
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_featured'])) {
    csrf_check();
    $id = (int)$_POST['id'];
    $cur = $pdo->prepare('SELECT featured_home FROM projects WHERE id=?');
    $cur->execute([$id]);
    $curVal = (int)$cur->fetchColumn();
    $new = $curVal ? 0 : 1;
    $pdo->prepare('UPDATE projects SET featured_home=? WHERE id=?')->execute([$new, $id]);
    audit($pdo, 'toggle_project_featured', "#$id -> " . ($new ? 'featured' : 'unfeatured'));
    $msg = $new ? 'Project will now show on the homepage.' : 'Project removed from the homepage.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_credit'])) {
    csrf_check();
    $projectId = (int)$_POST['project_id'];
    $name = trim($_POST['credit_name']);
    $role = trim($_POST['credit_role']);
    $userId = (int)($_POST['credit_user_id'] ?? 0) ?: null;
    if ($name) {
        $stmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order),0) FROM project_credits WHERE project_id=?');
        $stmt->execute([$projectId]);
        $maxOrder = (int)$stmt->fetchColumn();
        $pdo->prepare('INSERT INTO project_credits (project_id, user_id, name, role_in_project, sort_order) VALUES (?,?,?,?,?)')
            ->execute([$projectId, $userId, $name, $role, $maxOrder + 1]);
    }
    $msg = 'Credit added.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_credit'])) {
    csrf_check();
    $pdo->prepare('DELETE FROM project_credits WHERE id=?')->execute([(int)$_POST['id']]);
    $msg = 'Credit removed.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['move_credit'])) {
    csrf_check();
    $id = (int)$_POST['id'];
    $dir = $_POST['direction'] === 'up' ? 'up' : 'down';
    $cur = $pdo->prepare('SELECT project_id, sort_order FROM project_credits WHERE id=?');
    $cur->execute([$id]);
    $row = $cur->fetch();
    if ($row) {
        $cmp = $dir === 'up' ? '<' : '>';
        $ord = $dir === 'up' ? 'DESC' : 'ASC';
        $nStmt = $pdo->prepare("SELECT id, sort_order FROM project_credits WHERE project_id=? AND sort_order $cmp ? ORDER BY sort_order $ord LIMIT 1");
        $nStmt->execute([$row['project_id'], $row['sort_order']]);
        $neighbor = $nStmt->fetch();
        if ($neighbor) {
            $pdo->prepare('UPDATE project_credits SET sort_order=? WHERE id=?')->execute([$neighbor['sort_order'], $id]);
            $pdo->prepare('UPDATE project_credits SET sort_order=? WHERE id=?')->execute([$row['sort_order'], $neighbor['id']]);
        }
    }
}

$projects = $pdo->query('SELECT * FROM projects ORDER BY sort_order')->fetchAll();
$linkableUsers = $pdo->query("SELECT id, full_name, member_id FROM users WHERE status='approved' ORDER BY full_name")->fetchAll();
require __DIR__ . '/layout_header.php';
?>
<h1>Projects</h1>
<?php if ($msg): ?><div class="alert alert-ok"><?= h($msg) ?></div><?php endif; ?>

<div class="card form-card" style="max-width:640px">
  <h3>New Project</h3>
  <form method="POST" enctype="multipart/form-data" class="stack">
    <?= csrf_field() ?>
    <div class="field"><label>Title</label><input name="title" required></div>
    <div class="field"><label>Short Summary</label><input name="summary" maxlength="300"></div>
    <div class="field"><label>Full Description</label><textarea name="description" style="min-height:160px" required></textarea></div>
    <div class="field"><label>Status</label>
      <select name="status"><option value="ongoing">Ongoing</option><option value="completed">Completed</option></select>
    </div>
    <div class="field"><label>Cover Image</label><input type="file" name="cover_image" accept="image/*"></div>
    <button class="btn btn-primary" type="submit" name="create_project">Create Project</button>
  </form>
</div>

<h2 style="margin-top:32px">All Projects</h2>
<?php foreach ($projects as $p):
    $creditsStmt = $pdo->prepare('SELECT * FROM project_credits WHERE project_id=? ORDER BY sort_order');
    $creditsStmt->execute([$p['id']]);
    $credits = $creditsStmt->fetchAll();
?>
  <div class="card" style="margin-bottom:18px">
    <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:12px">
      <div>
          <div style="display:flex;gap:8px;align-items:flex-start">
  <?php if ($p['featured_home']): ?>
    <span class="badge badge-upcoming" style="align-self:center">On Homepage</span>
  <?php endif; ?>
  <form method="POST"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="direction" value="up">
    <button class="btn btn-outline btn-sm" type="submit" name="move_project">&uarr;</button></form>
  <form method="POST"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="direction" value="down">
    <button class="btn btn-outline btn-sm" type="submit" name="move_project">&darr;</button></form>
  <form method="POST"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
    <button class="btn btn-outline btn-sm" type="submit" name="toggle_featured"><?= $p['featured_home'] ? 'Unfeature' : 'Feature on Home' ?></button></form>
  <a class="btn btn-outline btn-sm" href="<?= BASE_URL ?>/pages/project_view.php?slug=<?= h($p['slug']) ?>" target="_blank">View</a>
  <form method="POST" onsubmit="return confirm('Delete this project?')">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
    <button class="btn btn-danger btn-sm" type="submit" name="delete_project">Delete</button>
  </form>
</div>
        <h3><?= h($p['title']) ?> <span class="badge badge-<?= $p['status']==='completed'?'completed':'ongoing' ?>"><?= h(ucfirst($p['status'])) ?></span></h3>
        <p class="muted mono" style="font-size:12px">/<?= h($p['slug']) ?></p>
      </div>
      <div style="display:flex;gap:8px;align-items:flex-start">
        <form method="POST"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="direction" value="up">
          <button class="btn btn-outline btn-sm" type="submit" name="move_project">&uarr;</button></form>
        <form method="POST"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="direction" value="down">
          <button class="btn btn-outline btn-sm" type="submit" name="move_project">&darr;</button></form>
        <a class="btn btn-outline btn-sm" href="<?= BASE_URL ?>/pages/project_view.php?slug=<?= h($p['slug']) ?>" target="_blank">View</a>
        <form method="POST" onsubmit="return confirm('Delete this project?')">
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
          <button class="btn btn-danger btn-sm" type="submit" name="delete_project">Delete</button>
        </form>
      </div>
    </div>

    <h4 style="margin-top:16px;font-size:14px">Team / Credits</h4>
    <table class="admin-table">
      <?php foreach ($credits as $c): ?>
        <tr>
          <td><?= h($c['name']) ?><?php if ($c['user_id']): ?> <span class="muted mono" style="font-size:11px">(linked account)</span><?php endif; ?></td>
          <td class="muted"><?= h($c['role_in_project'] ?: '—') ?></td>
          <td style="white-space:nowrap">
            <form method="POST" style="display:inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><input type="hidden" name="direction" value="up">
              <button class="btn btn-outline btn-sm" type="submit" name="move_credit">&uarr;</button></form>
            <form method="POST" style="display:inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><input type="hidden" name="direction" value="down">
              <button class="btn btn-outline btn-sm" type="submit" name="move_credit">&darr;</button></form>
            <form method="POST" style="display:inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
              <button class="btn btn-danger btn-sm" type="submit" name="delete_credit">Remove</button></form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$credits): ?><tr><td class="muted">No team members credited yet.</td></tr><?php endif; ?>
    </table>
    <form method="POST" style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end">
      <?= csrf_field() ?><input type="hidden" name="project_id" value="<?= (int)$p['id'] ?>">
      <div class="field" style="margin-bottom:0"><input name="credit_name" placeholder="Name" required></div>
      <div class="field" style="margin-bottom:0"><input name="credit_role" placeholder="Role (optional)"></div>
      <div class="field" style="margin-bottom:0">
        <select name="credit_user_id">
          <option value="">Link to account (optional)</option>
          <?php foreach ($linkableUsers as $lu): ?>
            <option value="<?= (int)$lu['id'] ?>"><?= h($lu['full_name']) ?><?= $lu['member_id'] ? ' — '.h($lu['member_id']) : '' ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button class="btn btn-outline btn-sm" type="submit" name="add_credit">Add</button>
    </form>
  </div>
<?php endforeach; ?>
<?php require __DIR__ . '/layout_footer.php'; ?>
