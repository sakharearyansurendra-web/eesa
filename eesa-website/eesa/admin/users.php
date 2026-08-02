<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/mailer.php';
require_role(['super_admin']);
$pageTitle = 'Users & Access';
$activeSection = 'users';
$msg = null; $err = null;

function random_password() {
    return substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789'), 0, 10);
}

// Approve a pending request -> assign username/role, generate password, email it
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_user'])) {
    csrf_check();
    $id = (int)$_POST['user_id'];
    $username = trim($_POST['username'] ?? '');
    $role = $_POST['role'] ?? 'member';
    if (!in_array($role, ['admin','aptitude_manager','member'], true)) $role = 'member'; // super_admin never granted here
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $target = $stmt->fetch();
    if (!$target) {
        $err = 'User not found.';
    } elseif (!$username) {
        $err = 'Choose a username.';
    } else {
        $exists = $pdo->prepare('SELECT id FROM users WHERE username = ? AND id != ?');
        $exists->execute([$username, $id]);
        if ($exists->fetch()) {
            $err = 'That username is already taken.';
        } else {
            $tempPass = random_password();
            $pdo->prepare("UPDATE users SET username=?, password_hash=?, role=?, status='approved', approved_at=NOW(), approved_by=? WHERE id=?")
                ->execute([$username, password_hash($tempPass, PASSWORD_DEFAULT), $role, current_user()['id'], $id]);
            mail_approval($target['email'], $target['full_name'], $username, $tempPass);
            audit($pdo, 'approve_user', "user #$id as $role");
            $msg = "Approved. Username \"$username\" and a temporary password were emailed to " . h($target['email']) . ".";
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_user'])) {
    csrf_check();
    $id = (int)$_POST['user_id'];
    $pdo->prepare("UPDATE users SET status='rejected' WHERE id=?")->execute([$id]);
    audit($pdo, 'reject_user', "user #$id");
    $msg = 'Request rejected.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_role'])) {
    csrf_check();
    $id = (int)$_POST['user_id'];
    $role = $_POST['role'] ?? 'member';
    if ($id === current_user()['id']) {
        $err = "You can't change your own role.";
    } elseif (!in_array($role, ['admin','aptitude_manager','member','super_admin'], true)) {
        $err = 'Invalid role.';
    } else {
        $pdo->prepare('UPDATE users SET role=? WHERE id=?')->execute([$role, $id]);
        audit($pdo, 'change_role', "user #$id -> $role");
        $msg = 'Role updated.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_suspend'])) {
    csrf_check();
    $id = (int)$_POST['user_id'];
    $newStatus = $_POST['new_status'] === 'approved' ? 'approved' : 'suspended';
    if ($id !== current_user()['id']) {
        $pdo->prepare('UPDATE users SET status=? WHERE id=?')->execute([$newStatus, $id]);
        audit($pdo, 'toggle_suspend', "user #$id -> $newStatus");
    }
    $msg = 'Status updated.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    csrf_check();
    $id = (int)$_POST['user_id'];
    if ($id !== current_user()['id']) {
        $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$id]);
        audit($pdo, 'delete_user', "user #$id");
    }
    $msg = 'User deleted.';
}

$pending = $pdo->query("SELECT * FROM users WHERE status='pending' ORDER BY created_at DESC")->fetchAll();
$approved = $pdo->query("SELECT * FROM users WHERE status IN ('approved','suspended') ORDER BY role, full_name")->fetchAll();

require __DIR__ . '/layout_header.php';
?>
<h1>Users Access Management</h1>
<p class="muted">This is the privilege center: approve membership requests, assign access levels, and suspend or remove accounts.
Roles: <span class="mono">super_admin</span> (full control) · <span class="mono">admin</span> (content) ·
<span class="mono">aptitude_manager</span> (results only) · <span class="mono">member</span> (no back-office access).</p>

<?php if ($msg): ?><div class="alert alert-ok"><?= h($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-err"><?= h($err) ?></div><?php endif; ?>

<h2 style="margin-top:28px">Pending Join Requests (<?= count($pending) ?>)</h2>
<?php if (!$pending): ?><p class="muted">No pending requests.</p><?php endif; ?>
<?php foreach ($pending as $p): ?>
  <div class="card" style="margin-bottom:14px">
    <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:12px">
      <div>
        <h3 style="margin-bottom:2px"><?= h($p['full_name']) ?></h3>
        <p class="muted" style="margin:0;font-size:13px"><?= h($p['email']) ?> · <?= h($p['branch_year']) ?></p>
        <p class="mono muted" style="font-size:12px">Ticket: <?= h($p['ticket_id']) ?> · Applied <?= h(time_ago($p['created_at'])) ?></p>
      </div>
      <form method="POST" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap">
        <?= csrf_field() ?>
        <input type="hidden" name="user_id" value="<?= (int)$p['id'] ?>">
        <div class="field" style="margin-bottom:0"><label>Username</label>
          <input name="username" placeholder="username" style="width:150px" required></div>
        <div class="field" style="margin-bottom:0"><label>Role</label>
          <select name="role" style="width:150px">
            <option value="member">Member</option>
            <option value="admin">Admin</option>
            <option value="aptitude_manager">Aptitude Manager</option>
          </select>
        </div>
        <button class="btn btn-primary btn-sm" type="submit" name="approve_user">Approve</button>
        <button class="btn btn-danger btn-sm" type="submit" name="reject_user" onclick="return confirm('Reject this request?')">Reject</button>
      </form>
    </div>
  </div>
<?php endforeach; ?>

<h2 style="margin-top:32px">All Accounts</h2>
<table class="admin-table">
  <tr><th>Name</th><th>Username</th><th>Role</th><th>Status</th><th>Actions</th></tr>
  <?php foreach ($approved as $a): ?>
    <tr>
      <td><?= h($a['full_name']) ?><br><span class="muted mono" style="font-size:12px"><?= h($a['email']) ?></span></td>
      <td class="mono"><?= h($a['username']) ?></td>
      <td>
        <form method="POST" style="display:flex;gap:6px;align-items:center">
          <?= csrf_field() ?>
          <input type="hidden" name="user_id" value="<?= (int)$a['id'] ?>">
          <select name="role" onchange="this.form.submit()" <?= $a['id']===current_user()['id']?'disabled':'' ?>>
            <?php foreach (['member','admin','aptitude_manager','super_admin'] as $r): ?>
              <option value="<?= $r ?>" <?= $a['role']===$r?'selected':'' ?>><?= $r ?></option>
            <?php endforeach; ?>
          </select>
          <input type="hidden" name="change_role" value="1">
        </form>
      </td>
      <td><span class="pill pill-<?= h($a['status']) ?>"><?= h($a['status']) ?></span></td>
      <td>
        <form method="POST" style="display:inline">
          <?= csrf_field() ?>
          <input type="hidden" name="user_id" value="<?= (int)$a['id'] ?>">
          <input type="hidden" name="new_status" value="<?= $a['status']==='approved'?'suspended':'approved' ?>">
          <button class="btn btn-outline btn-sm" type="submit" name="toggle_suspend" <?= $a['id']===current_user()['id']?'disabled':'' ?>>
            <?= $a['status']==='approved'?'Suspend':'Reactivate' ?>
          </button>
        </form>
        <form method="POST" style="display:inline" onsubmit="return confirm('Delete this account permanently?')">
          <?= csrf_field() ?>
          <input type="hidden" name="user_id" value="<?= (int)$a['id'] ?>">
          <button class="btn btn-danger btn-sm" type="submit" name="delete_user" <?= $a['id']===current_user()['id']?'disabled':'' ?>>Delete</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
</table>

<?php require __DIR__ . '/layout_footer.php'; ?>
