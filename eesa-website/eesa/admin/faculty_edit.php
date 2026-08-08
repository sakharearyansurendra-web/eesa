<?php
require_once __DIR__ . '/../config.php';
if (!is_logged_in()) redirect('/login.php');
$pageTitle = 'Edit Faculty Profile';
$msg = null; $err = null;

$isManager = has_role(DEPT_RESOURCE_ROLES);
$facultyId = (int)($_GET['id'] ?? 0);

if ($facultyId) {
    $stmt = $pdo->prepare('SELECT * FROM dept_faculty WHERE id=? LIMIT 1');
    $stmt->execute([$facultyId]);
    $faculty = $stmt->fetch();
} else {
    // No id given — try to find the profile linked to the logged-in account.
    $stmt = $pdo->prepare('SELECT * FROM dept_faculty WHERE user_id=? LIMIT 1');
    $stmt->execute([current_user()['id']]);
    $faculty = $stmt->fetch();
}

$isOwner = $faculty && $faculty['user_id'] && (int)$faculty['user_id'] === (int)current_user()['id'];

if (!$faculty) {
    if ($isManager) {
        // Let a manager pick from the roster instead of a dead end.
        $roster = $pdo->query('SELECT id, full_name FROM dept_faculty ORDER BY full_name')->fetchAll();
        require __DIR__ . '/layout_header.php';
        echo '<h1>Edit Faculty Profile</h1><p class="muted">No profile selected. Choose one:</p><div class="card" style="max-width:420px"><table class="admin-table">';
        foreach ($roster as $r) {
            echo '<tr><td>' . h($r['full_name']) . '</td><td><a class="btn btn-outline btn-sm" href="faculty_edit.php?id=' . (int)$r['id'] . '">Edit</a></td></tr>';
        }
        if (!$roster) echo '<tr><td class="muted">No faculty in the roster yet — add one from Department at a Glance.</td></tr>';
        echo '</table></div>';
        require __DIR__ . '/layout_footer.php';
        exit;
    }
    require __DIR__ . '/layout_header.php';
    echo '<h1>Edit Faculty Profile</h1><p class="muted">No faculty profile is linked to your account yet. Ask an admin to link it from Department at a Glance.</p>';
    require __DIR__ . '/layout_footer.php';
    exit;
}

if (!$isManager && !$isOwner) {
    http_response_code(403);
    die('You do not have permission to edit this profile.');
}

// ---- Update core profile fields ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    csrf_check();
    $name = trim($_POST['full_name']);
    $desig = trim($_POST['designation']);
    $email = trim($_POST['email']);
    $bio = $_POST['bio'];
    $photo = save_upload('photo', 'dept') ?: null;
    $resume = save_upload('resume', 'dept', ['pdf']) ?: null;

    $sql = 'UPDATE dept_faculty SET full_name=?, designation=?, email=?, bio=?';
    $params = [$name, $desig, $email, $bio];
    if ($photo) { $sql .= ', photo=?'; $params[] = $photo; }
    if ($resume) { $sql .= ', resume_file=?'; $params[] = $resume; }
    $sql .= ' WHERE id=?';
    $params[] = $faculty['id'];
    $pdo->prepare($sql)->execute($params);
    audit($pdo, 'update_faculty_profile', $faculty['full_name']);
    $msg = 'Profile updated.';

    $stmt = $pdo->prepare('SELECT * FROM dept_faculty WHERE id=?');
    $stmt->execute([$faculty['id']]);
    $faculty = $stmt->fetch();
}

// ---- Service / serving-years history ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_service'])) {
    csrf_check();
    $position = trim($_POST['position']);
    $startYear = trim($_POST['start_year']);
    $endYear = trim($_POST['end_year']) ?: null;
    if ($position && $startYear) {
        $maxOrder = (int)$pdo->query('SELECT COALESCE(MAX(sort_order),0) FROM dept_faculty_service WHERE faculty_id=' . (int)$faculty['id'])->fetchColumn();
        $pdo->prepare('INSERT INTO dept_faculty_service (faculty_id, position, start_year, end_year, sort_order) VALUES (?,?,?,?,?)')
            ->execute([$faculty['id'], $position, $startYear, $endYear, $maxOrder + 1]);
        $msg = 'Service entry added.';
    } else {
        $err = 'Position and start year are required.';
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_service'])) {
    csrf_check();
    $pdo->prepare('DELETE FROM dept_faculty_service WHERE id=? AND faculty_id=?')->execute([(int)$_POST['id'], $faculty['id']]);
    $msg = 'Service entry removed.';
}

// ---- Papers published ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_paper'])) {
    csrf_check();
    $title = trim($_POST['paper_title']);
    $link = trim($_POST['paper_link']);
    $year = trim($_POST['paper_year']);
    if ($title) {
        $maxOrder = (int)$pdo->query('SELECT COALESCE(MAX(sort_order),0) FROM dept_faculty_papers WHERE faculty_id=' . (int)$faculty['id'])->fetchColumn();
        $pdo->prepare('INSERT INTO dept_faculty_papers (faculty_id, title, link, published_year, sort_order) VALUES (?,?,?,?,?)')
            ->execute([$faculty['id'], $title, $link ?: null, $year ?: null, $maxOrder + 1]);
        $msg = 'Paper added.';
    } else {
        $err = 'Paper title is required.';
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_paper'])) {
    csrf_check();
    $pdo->prepare('DELETE FROM dept_faculty_papers WHERE id=? AND faculty_id=?')->execute([(int)$_POST['id'], $faculty['id']]);
    $msg = 'Paper removed.';
}

$serviceStmt = $pdo->prepare('SELECT * FROM dept_faculty_service WHERE faculty_id=? ORDER BY sort_order');
$serviceStmt->execute([$faculty['id']]);
$service = $serviceStmt->fetchAll();

$papersStmt = $pdo->prepare('SELECT * FROM dept_faculty_papers WHERE faculty_id=? ORDER BY sort_order');
$papersStmt->execute([$faculty['id']]);
$papers = $papersStmt->fetchAll();

require __DIR__ . '/layout_header.php';
?>
<h1>Edit Faculty Profile — <?= h($faculty['full_name']) ?></h1>
<p class="muted"><a href="<?= BASE_URL ?>/pages/faculty_view.php?id=<?= (int)$faculty['id'] ?>" target="_blank" style="color:var(--copper-lt)">View public profile &rarr;</a></p>
<?php if ($msg): ?><div class="alert alert-ok"><?= h($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-err"><?= h($err) ?></div><?php endif; ?>

<div class="card form-card" style="max-width:640px">
  <h3>Profile Details</h3>
  <form method="POST" enctype="multipart/form-data" class="stack">
    <?= csrf_field() ?>
    <div class="field"><label>Full Name</label><input name="full_name" value="<?= h($faculty['full_name']) ?>" required></div>
    <div class="field"><label>Current Designation</label><input name="designation" value="<?= h($faculty['designation']) ?>" required></div>
    <div class="field"><label>Email</label><input type="email" name="email" value="<?= h($faculty['email']) ?>"></div>
    <div class="field"><label>Bio</label><textarea name="bio" style="min-height:140px"><?= h($faculty['bio']) ?></textarea></div>
    <div class="field"><label>Profile Photo <?= $faculty['photo'] ? '(current photo will be replaced if you upload a new one)' : '' ?></label>
      <input type="file" name="photo" accept="image/*"></div>
    <div class="field"><label>Resume (PDF) <?= $faculty['resume_file'] ? '— currently: <a href="' . BASE_URL . '/uploads/dept/' . h($faculty['resume_file']) . '" target="_blank" style="color:var(--copper-lt)">view current</a>' : '' ?></label>
      <input type="file" name="resume" accept=".pdf"></div>
    <button class="btn btn-primary" type="submit" name="update_profile">Save Profile</button>
  </form>
</div>

<div class="card form-card" style="max-width:560px;margin-top:22px">
  <h3>Serving Years</h3>
  <p class="muted" style="font-size:13px">e.g. 2023–2027 as Associate Professor. Leave "End Year" blank if still serving.</p>
  <form method="POST" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end">
    <?= csrf_field() ?>
    <div class="field" style="margin-bottom:0"><label>Position</label><input name="position" placeholder="Professor, Lab Assistant, etc." required></div>
    <div class="field" style="margin-bottom:0"><label>Start Year</label><input name="start_year" placeholder="2023" maxlength="4" required style="width:90px"></div>
    <div class="field" style="margin-bottom:0"><label>End Year</label><input name="end_year" placeholder="2027 or blank" maxlength="4" style="width:110px"></div>
    <button class="btn btn-outline btn-sm" type="submit" name="add_service">Add</button>
  </form>
  <table class="admin-table" style="margin-top:12px">
    <?php foreach ($service as $s): ?>
      <tr>
        <td><?= h($s['position']) ?></td>
        <td class="mono"><?= h($s['start_year']) ?> – <?= h($s['end_year'] ?: 'Present') ?></td>
        <td><form method="POST"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
          <button class="btn btn-danger btn-sm" type="submit" name="delete_service">Remove</button></form></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$service): ?><tr><td class="muted">No service history added yet.</td></tr><?php endif; ?>
  </table>
</div>

<div class="card form-card" style="max-width:560px;margin-top:22px">
  <h3>Papers Published</h3>
  <form method="POST" class="stack">
    <?= csrf_field() ?>
    <div class="field"><label>Title</label><input name="paper_title" required></div>
    <div class="field"><label>Link (DOI, journal page, PDF, etc.)</label><input name="paper_link" placeholder="https://..."></div>
    <div class="field"><label>Year</label><input name="paper_year" maxlength="4" style="width:100px"></div>
    <button class="btn btn-outline btn-sm" type="submit" name="add_paper">Add Paper</button>
  </form>
  <table class="admin-table" style="margin-top:12px">
    <?php foreach ($papers as $p): ?>
      <tr>
        <td><?= h($p['title']) ?><?php if ($p['link']): ?> <a href="<?= h($p['link']) ?>" target="_blank" style="color:var(--copper-lt);font-size:12px">(link)</a><?php endif; ?></td>
        <td class="mono muted"><?= h($p['published_year'] ?: '—') ?></td>
        <td><form method="POST"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
          <button class="btn btn-danger btn-sm" type="submit" name="delete_paper">Remove</button></form></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$papers): ?><tr><td class="muted">No papers added yet.</td></tr><?php endif; ?>
  </table>
</div>
<?php require __DIR__ . '/layout_footer.php'; ?>
