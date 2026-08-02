<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/mailer.php';
require_role(['super_admin']);
$pageTitle = 'Users Access Management';
$activeSection = 'users';
$msg = null; $err = null;

function random_password() {
    return substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789'), 0, 10);
}

// Plain-text body for the mailto: draft (mail clients render mailto bodies
// as plain text, so this is deliberately separate from mail_approval()'s
// HTML template).
function approval_draft_text($fullName, $username, $tempPass) {
    return "Hi $fullName,\n\n"
         . "Your EESA account has been approved. Here are your login details:\n\n"
         . "Username: $username\n"
         . "Temporary Password: $tempPass\n\n"
         . "Please log in and change your password as soon as possible.\n\n"
         . "— EESA Team";
}

$draft = null; // populated right after a successful approval, rendered once below

// Approve a pending request -> assign username/role, generate password.
// We still attempt an automatic email via server SMTP (mail_approval), but
// ALWAYS also build a mailto: draft — since server-side SMTP can be blocked
// on some hosts (e.g. Render's free tier), the draft is the reliable path:
// it opens in the admin's own email client, pre-filled and ready to review
// before sending, so the account holder gets their credentials regardless
// of whether the server could send mail itself.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_user'])) {
    csrf_check();
    $id = (int)$_POST['user_id'];
    $username = trim($_POST['username'] ?? '');
    $role = $_POST['role'] ?? 'member';
    if (!in_array($role, ASSIGNABLE_ROLES, true)) $role = 'member';
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
            $autoSent = mail_approval($target['email'], $target['full_name'], $username, $tempPass);
            audit($pdo, 'approve_user', "user #$id as $role");
            $draft = [
                'to'      => $target['email'],
                'subject' => 'Welcome to EESA — Your Login Details',
                'body'    => approval_draft_text($target['full_name'], $username, $tempPass),
                'auto_sent' => $autoSent,
            ];
            $msg = $autoSent
                ? "Approved. Username \"$username\" was generated and an email was sent automatically to " . h($target['email']) . ". A draft is also ready below in case you'd like to send it yourself."
                : "Approved. Username \"$username\" was generated, but automatic sending failed — use the draft below to send the credentials yourself.";
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
    } elseif (!in_array($role, ALL_ROLES, true)) {
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

// Admin can also change a username directly (outside the request flow) —
// useful for typo fixes without waiting on the self-service request table.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_username'])) {
    csrf_check();
    $id = (int)$_POST['user_id'];
    $newUsername = trim($_POST['new_username'] ?? '');
    if (!$newUsername) {
        $err = 'Enter a username.';
    } else {
        $exists = $pdo->prepare('SELECT id FROM users WHERE username = ? AND id != ?');
        $exists->execute([$newUsername, $id]);
        if ($exists->fetch()) {
            $err = 'That username is already taken.';
        } else {
            $pdo->prepare('UPDATE users SET username = ? WHERE id = ?')->execute([$newUsername, $id]);
            audit($pdo, 'admin_edit_username', "user #$id -> $newUsername");
            $msg = 'Username updated.';
        }
    }
}

// Approve/reject a self-service username change request (see pages/account.php)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resolve_username_request'])) {
    csrf_check();
    $reqId = (int)$_POST['request_id'];
    $decision = $_POST['decision'] === 'approve' ? 'approved' : 'rejected';
    $stmt = $pdo->prepare('SELECT * FROM username_requests WHERE id = ? AND status = "pending" LIMIT 1');
    $stmt->execute([$reqId]);
    $reqRow = $stmt->fetch();
    if (!$reqRow) {
        $err = 'Request not found or already resolved.';
    } else {
        if ($decision === 'approved') {
            $taken = $pdo->prepare('SELECT id FROM users WHERE username = ? AND id != ?');
            $taken->execute([$reqRow['requested_username'], $reqRow['user_id']]);
            if ($taken->fetch()) {
                $err = 'That username was taken in the meantime — reject or ask the member to pick another.';
            } else {
                $pdo->prepare('UPDATE users SET username = ? WHERE id = ?')->execute([$reqRow['requested_username'], $reqRow['user_id']]);
            }
        }
        if (!$err) {
            $pdo->prepare('UPDATE username_requests SET status = ?, resolved_at = NOW(), resolved_by = ? WHERE id = ?')
                ->execute([$decision, current_user()['id'], $reqId]);
            audit($pdo, 'resolve_username_request', "#$reqId -> $decision");
            $msg = 'Request ' . $decision . '.';
        }
    }
}

$pending = $pdo->query("SELECT * FROM users WHERE status='pending' ORDER BY created_at DESC")->fetchAll();
$approved = $pdo->query("SELECT * FROM users WHERE status IN ('approved','suspended') ORDER BY role, full_name")->fetchAll();
$usernameRequests = $pdo->query("SELECT ur.*, u.full_name FROM username_requests ur JOIN users u ON u.id = ur.user_id WHERE ur.status = 'pending' ORDER BY ur.created_at DESC")->fetchAll();

require __DIR__ . '/layout_header.php';
?>
<h1>Users Access Management</h1>
<p class="muted">This is the privilege center: approve membership requests, assign access levels, and suspend or
remove accounts. Roles follow EESA's constitution — <span class="mono">super_admin</span> (full control) down
through <span class="mono">president</span>, <span class="mono">secretary</span>, <span class="mono">treasurer</span>,
<span class="mono">csd</span>, <span class="mono">media_head</span>, <span class="mono">prm</span>,
<span class="mono">joint_coordinator</span>, <span class="mono">aptitude_manager</span>, to
<span class="mono">member</span> (no back-office access).</p>

<?php if ($msg): ?><div class="alert alert-ok"><?= h($msg) ?></div><?php endif; ?>

<?php if ($draft): ?>
  <?php
    $mailtoUrl = 'mailto:' . rawurlencode($draft['to'])
        . '?subject=' . rawurlencode($draft['subject'])
        . '&body=' . rawurlencode($draft['body']);
  ?>
  <div class="card" style="max-width:560px;margin-bottom:20px;border-color:var(--copper)">
    <h3>Credentials Draft — <?= $draft['auto_sent'] ? 'sent automatically, and ready to resend manually' : 'send this manually' ?></h3>
    <p class="muted mono" style="font-size:13px">To: <?= h($draft['to']) ?><br>Subject: <?= h($draft['subject']) ?></p>
    <textarea id="draftBody" readonly style="min-height:160px"><?= h($draft['body']) ?></textarea>
    <div style="display:flex;gap:10px;margin-top:10px;flex-wrap:wrap">
      <a class="btn btn-primary btn-sm" href="<?= $mailtoUrl ?>">Open in your email client</a>
      <button type="button" class="btn btn-outline btn-sm" onclick="navigator.clipboard.writeText(document.getElementById('draftBody').value); this.textContent='Copied!'">Copy message</button>
    </div>
    <p class="muted" style="font-size:12px;margin-top:10px">"Open in your email client" pre-fills a new message in
    whatever app is set as your device's default mail handler (Outlook, Gmail desktop app, Mail, etc.) — review it
    there, then hit send yourself.</p>
  </div>
<?php endif; ?>
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
          <select name="role" style="width:190px">
            <?php foreach (ASSIGNABLE_ROLES as $r): ?>
              <option value="<?= h($r) ?>" <?= $r==='member'?'selected':'' ?>><?= h(role_label($r)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button class="btn btn-primary btn-sm" type="submit" name="approve_user">Approve</button>
        <button class="btn btn-danger btn-sm" type="submit" name="reject_user" onclick="return confirm('Reject this request?')">Reject</button>
      </form>
    </div>
  </div>
<?php endforeach; ?>

<h2 style="margin-top:32px">Pending Username Change Requests (<?= count($usernameRequests) ?>)</h2>
<?php if (!$usernameRequests): ?><p class="muted">No pending username change requests.</p><?php endif; ?>
<?php foreach ($usernameRequests as $r): ?>
  <div class="card" style="margin-bottom:12px">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
      <div>
        <strong><?= h($r['full_name']) ?></strong>
        <p class="mono muted" style="font-size:13px;margin:2px 0 0"><?= h($r['current_username']) ?> &rarr; <?= h($r['requested_username']) ?></p>
        <p class="muted" style="font-size:12px">Requested <?= h(time_ago($r['created_at'])) ?></p>
      </div>
      <form method="POST" style="display:flex;gap:8px">
        <?= csrf_field() ?>
        <input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>">
        <button class="btn btn-primary btn-sm" type="submit" name="resolve_username_request" value="1" onclick="this.form.decision.value='approve'">Approve</button>
        <button class="btn btn-danger btn-sm" type="submit" name="resolve_username_request" value="1" onclick="this.form.decision.value='reject'">Reject</button>
        <input type="hidden" name="decision" value="approve">
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
      <td>
        <form method="POST" style="display:flex;gap:6px;align-items:center">
          <?= csrf_field() ?>
          <input type="hidden" name="user_id" value="<?= (int)$a['id'] ?>">
          <input name="new_username" value="<?= h($a['username']) ?>" class="mono" style="width:120px">
          <button class="btn btn-outline btn-sm" type="submit" name="edit_username">Save</button>
        </form>
      </td>
      <td>
        <form method="POST" style="display:flex;gap:6px;align-items:center">
          <?= csrf_field() ?>
          <input type="hidden" name="user_id" value="<?= (int)$a['id'] ?>">
          <select name="role" onchange="this.form.submit()" <?= $a['id']===current_user()['id']?'disabled':'' ?>>
            <?php foreach (ALL_ROLES as $r): ?>
              <option value="<?= h($r) ?>" <?= $a['role']===$r?'selected':'' ?>><?= h(role_label($r)) ?></option>
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
