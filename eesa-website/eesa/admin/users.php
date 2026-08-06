<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/mailer.php';
// Secretary and President get limited access to this page (their own
// verification queue only); everything else on this page (role changes,
// deletions, final account creation) stays super_admin only, enforced
// per-action below, not just at the page level.
require_role(['super_admin', 'secretary', 'president']);
$pageTitle = 'User Access Management';
$activeSection = 'users';
$msg = null; $err = null;
$isSuperAdmin = has_role(['super_admin']);
$isSecretary = has_role(['secretary']);
$isPresident = has_role(['president']);

function random_password() {
    return substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789'), 0, 10);
}

function approval_draft_text($fullName, $username, $tempPass, $memberId = null) {
    $memberLine = $memberId ? "Member ID: $memberId\n" : '';
    return "Hi $fullName,\n\n"
         . "Your EESA account has been approved. Here are your login details:\n\n"
         . "Username: $username\n"
         . "Temporary Password: $tempPass\n"
         . $memberLine . "\n"
         . "Please log in and change your password as soon as possible.\n\n"
         . "— EESA Team";
}

$draft = null;

// ---- Stage 1: Secretary (or super_admin) verifies a pending request ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['stage1_verify'])) {
    csrf_check();
    if (!($isSecretary || $isSuperAdmin)) { $err = 'Not authorized for this action.'; }
    else {
        $id = (int)$_POST['user_id'];
        $stmt = $pdo->prepare('UPDATE users SET status = ?, verifier1_by = ?, verifier1_at = NOW() WHERE id = ? AND status = ?');
        $stmt->execute(['verifier1_approved', current_user()['id'], $id, 'pending']);
        audit($pdo, 'stage1_verify', "user #$id");
        $msg = 'Verified at Stage 1 — moved on to President for Stage 2.';
    }
}

// ---- Stage 2: President (or super_admin) verifies a stage-1-approved request ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['stage2_verify'])) {
    csrf_check();
    if (!($isPresident || $isSuperAdmin)) { $err = 'Not authorized for this action.'; }
    else {
        $id = (int)$_POST['user_id'];
        $stmt = $pdo->prepare('UPDATE users SET status = ?, verifier2_by = ?, verifier2_at = NOW() WHERE id = ? AND status = ?');
        $stmt->execute(['verifier2_approved', current_user()['id'], $id, 'verifier1_approved']);
        audit($pdo, 'stage2_verify', "user #$id");
        $msg = 'Verified at Stage 2 — ready for Super Admin final approval.';
    }
}

// ---- Reject at any stage (Secretary/President can reject their own stage; super_admin can reject any) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_user'])) {
    csrf_check();
    $id = (int)$_POST['user_id'];
    $stmt = $pdo->prepare('SELECT status FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $curStatus = $stmt->fetchColumn();
    $allowed = $isSuperAdmin
        || ($isSecretary && $curStatus === 'pending')
        || ($isPresident && $curStatus === 'verifier1_approved');
    if (!$allowed) { $err = 'Not authorized to reject this request at its current stage.'; }
    else {
        $pdo->prepare("UPDATE users SET status='rejected' WHERE id=?")->execute([$id]);
        audit($pdo, 'reject_user', "user #$id at stage $curStatus");
        $msg = 'Request rejected.';
    }
}

// ---- Final approval: super_admin only, from ANY stage (can skip ahead) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_user'])) {
    csrf_check();
    if (!$isSuperAdmin) {
        $err = 'Only Super Admin can finalize an account.';
    } else {
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
                $member_id = $target['member_id'] ?: generate_member_id($id);
                $pdo->prepare("UPDATE users SET username=?, password_hash=?, role=?, status='approved', approved_at=NOW(), approved_by=?, member_id=? WHERE id=?")
                    ->execute([$username, password_hash($tempPass, PASSWORD_DEFAULT), $role, current_user()['id'], $member_id, $id]);
                $autoSent = mail_approval($target['email'], $target['full_name'], $username, $tempPass, $member_id);
                audit($pdo, 'approve_user', "user #$id as $role (member ID $member_id)");
                $draft = [
                    'to'      => $target['email'],
                    'subject' => 'Welcome to EESA — Your Login Details',
                    'body'    => approval_draft_text($target['full_name'], $username, $tempPass, $member_id),
                    'auto_sent' => $autoSent,
                ];
                $msg = $autoSent
                    ? "Approved. Username \"$username\" and Member ID \"$member_id\" were generated, and an email was sent automatically to " . h($target['email']) . ". A draft is also ready below."
                    : "Approved. Username \"$username\" and Member ID \"$member_id\" were generated, but automatic sending failed — use the draft below to send the credentials yourself.";
            }
        }
    }
}

// ---- Everything below: super_admin only ----
if ($isSuperAdmin) {
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
}

// Queues, scoped by role
$stage1Queue = ($isSecretary || $isSuperAdmin)
    ? $pdo->prepare("SELECT * FROM users WHERE status = ? ORDER BY created_at DESC")
    : null;
if ($stage1Queue) { $stage1Queue->execute(['pending']); $stage1Queue = $stage1Queue->fetchAll(); } else { $stage1Queue = []; }

$stage2Queue = ($isPresident || $isSuperAdmin)
    ? $pdo->prepare("SELECT * FROM users WHERE status = ? ORDER BY created_at DESC")
    : null;
if ($stage2Queue) { $stage2Queue->execute(['verifier1_approved']); $stage2Queue = $stage2Queue->fetchAll(); } else { $stage2Queue = []; }

$finalQueue = [];
$approved = [];
if ($isSuperAdmin) {
    $finalQueue = $pdo->prepare("SELECT * FROM users WHERE status = ? ORDER BY created_at DESC");
    $finalQueue->execute(['verifier2_approved']);
    $finalQueue = $finalQueue->fetchAll();

    $approved = $pdo->query("SELECT * FROM users WHERE status IN ('approved','suspended') ORDER BY role, full_name")->fetchAll();
}

// ---- CSV export of all accounts (super_admin only) — opens cleanly in Excel ----
if ($isSuperAdmin && isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="eesa-accounts-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Full Name', 'Username', 'Member ID', 'Email', 'Role', 'Status', 'Branch & Year', 'Approved At']);
    foreach ($approved as $a) {
        fputcsv($out, [
            $a['full_name'],
            $a['username'],
            $a['member_id'] ?: '',
            $a['email'],
            role_label($a['role']),
            $a['status'],
            $a['branch_year'],
            $a['approved_at'] ? date('d M Y, h:i A', strtotime($a['approved_at'])) : '',
        ]);
    }
    fclose($out);
    exit;
}

require __DIR__ . '/layout_header.php';
?>
<h2>User Access Management</h2>
<p class="muted">Join requests move through a verification pipeline before an account is created:
</p>    
<p class="muted">
<strong>Secretary</strong> (Stage 1) &rarr; <strong>President</strong> (Stage 2) &rarr; <strong>Super Admin</strong>
(final — assigns username &amp; role). </p>

<div class="grid grid-4" style="margin:20px 0">
  <?php if ($isSecretary || $isSuperAdmin): ?>
    <div class="card">
      <div class="meta">Stage 1 — Secretary Review</div>
      <h2 style="margin:0"><?= count($stage1Queue) ?></h2>
    </div>
  <?php endif; ?>
  <?php if ($isPresident || $isSuperAdmin): ?>
    <div class="card">
      <div class="meta">Stage 2 — President Review</div>
      <h2 style="margin:0"><?= count($stage2Queue) ?></h2>
    </div>
  <?php endif; ?>
  <?php if ($isSuperAdmin): ?>
    <div class="card">
      <div class="meta">Final — Your Approval</div>
      <h2 style="margin:0"><?= count($finalQueue) ?></h2>
    </div>
  <?php endif; ?>
</div>

<?php if ($msg): ?><div class="alert alert-ok"><?= h($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-err"><?= h($err) ?></div><?php endif; ?>

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
  </div>
<?php endif; ?>

<?php if ($isSecretary || $isSuperAdmin): ?>
<h3 style="margin-top:28px">Stage 1 — Secretary Review (<?= count($stage1Queue) ?>)</h3>
<?php if (!$stage1Queue): ?><p class="muted">Nothing waiting at Stage 1.</p><?php endif; ?>
<?php foreach ($stage1Queue as $p): ?>
  <div class="card" style="margin-bottom:12px">
    <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:12px">
      <div>
        <h3 style="margin-bottom:2px"><?= h($p['full_name']) ?></h3>
        <p class="muted" style="margin:0;font-size:13px"><?= h($p['email']) ?> · <?= h($p['branch_year']) ?></p>
        <p class="mono muted" style="font-size:12px">Ticket: <?= h($p['ticket_id']) ?> · Applied <?= h(time_ago($p['created_at'])) ?></p>
      </div>
      <form method="POST" style="display:flex;gap:8px;align-items:center">
        <?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int)$p['id'] ?>">
        <button class="btn btn-primary btn-sm" type="submit" name="stage1_verify">Verify (Stage 1)</button>
        <button class="btn btn-danger btn-sm" type="submit" name="reject_user" onclick="return confirm('Reject this request?')">Reject</button>
      </form>
    </div>
  </div>
<?php endforeach; ?>
<?php endif; ?>

<?php if ($isPresident || $isSuperAdmin): ?>
<h3 style="margin-top:32px">Stage 2 — President Review (<?= count($stage2Queue) ?>)</h3>
<?php if (!$stage2Queue): ?><p class="muted">Nothing waiting at Stage 2.</p><?php endif; ?>
<?php foreach ($stage2Queue as $p): ?>
  <div class="card" style="margin-bottom:12px">
    <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:12px">
      <div>
        <h3 style="margin-bottom:2px"><?= h($p['full_name']) ?></h3>
        <p class="muted" style="margin:0;font-size:13px"><?= h($p['email']) ?> · <?= h($p['branch_year']) ?></p>
        <p class="mono muted" style="font-size:12px">Verified by Secretary <?= h(time_ago($p['verifier1_at'])) ?></p>
      </div>
      <form method="POST" style="display:flex;gap:8px;align-items:center">
        <?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int)$p['id'] ?>">
        <button class="btn btn-primary btn-sm" type="submit" name="stage2_verify">Verify (Stage 2)</button>
        <button class="btn btn-danger btn-sm" type="submit" name="reject_user" onclick="return confirm('Reject this request?')">Reject</button>
      </form>
    </div>
  </div>
<?php endforeach; ?>
<?php endif; ?>

<?php if ($isSuperAdmin): ?>
<h3 style="margin-top:32px">Final Stage — Ready for Your Approval (<?= count($finalQueue) ?>)</h3>
<?php if (!$finalQueue): ?><p class="muted">Nothing waiting at the final stage.</p><?php endif; ?>
<?php foreach ($finalQueue as $p): ?>
  <div class="card" style="margin-bottom:14px">
    <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:12px">
      <div>
        <h3 style="margin-bottom:2px"><?= h($p['full_name']) ?></h3>
        <p class="muted" style="margin:0;font-size:13px"><?= h($p['email']) ?> · <?= h($p['branch_year']) ?></p>
        <p class="mono muted" style="font-size:12px">Verified by Secretary &amp; President · Ticket: <?= h($p['ticket_id']) ?></p>
      </div>
      <form method="POST" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap">
        <?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int)$p['id'] ?>">
        <div class="field" style="margin-bottom:0"><label>Username</label><input name="username" placeholder="username" style="width:150px" required></div>
        <div class="field" style="margin-bottom:0"><label>Role</label>
          <select name="role" style="width:190px">
            <?php foreach (ASSIGNABLE_ROLES as $r): ?>
              <option value="<?= h($r) ?>" <?= $r==='member'?'selected':'' ?>><?= h(role_label($r)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button class="btn btn-primary btn-sm" type="submit" name="approve_user">Approve &amp; Create Account</button>
        <button class="btn btn-danger btn-sm" type="submit" name="reject_user" onclick="return confirm('Reject this request?')">Reject</button>
      </form>
    </div>
  </div>
<?php endforeach; ?>

<div class="section-head" style="margin-top:32px;margin-bottom:0">
  <h2 style="margin:0">All Accounts</h2>
  <a class="btn btn-outline btn-sm" href="?export=csv">Download as Excel (CSV)</a>
</div>
<table class="admin-table">
  <tr><th>Name</th><th>Username</th><th>Member ID</th><th>Role</th><th>Status</th><th>Actions</th></tr>
  <?php foreach ($approved as $a): ?>
    <tr>
      <td>
        <a href="user_view.php?id=<?= (int)$a['id'] ?>" style="color:var(--copper-lt);font-weight:600"><?= h($a['full_name']) ?></a>
        <br><span class="muted mono" style="font-size:12px"><?= h($a['email']) ?></span>
      </td>
      <td>
        <form method="POST" style="display:flex;gap:6px;align-items:center">
          <?= csrf_field() ?>
          <input type="hidden" name="user_id" value="<?= (int)$a['id'] ?>">
          <input name="new_username" value="<?= h($a['username']) ?>" class="mono" style="width:120px">
          <button class="btn btn-outline btn-sm" type="submit" name="edit_username">Save</button>
        </form>
      </td>
      <td class="mono"><?= h($a['member_id'] ?: '—') ?></td>
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
<?php endif; ?>

<?php require __DIR__ . '/layout_footer.php'; ?>
