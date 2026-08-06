<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/mailer.php';
require_role(['super_admin', 'admin', 'secretary', 'president']);
$pageTitle = 'Password Reset Requests';
$activeSection = 'password_resets';
$msg = null; $err = null; $draft = null;

function random_reset_password() {
    return substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789'), 0, 10);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_reset'])) {
    csrf_check();
    $reqId = (int)$_POST['request_id'];
    $stmt = $pdo->prepare("SELECT pr.*, u.full_name, u.email, u.username AS current_username
                            FROM password_reset_requests pr
                            JOIN users u ON u.id = pr.user_id
                            WHERE pr.id = ? AND pr.status = 'pending' LIMIT 1");
    $stmt->execute([$reqId]);
    $reqRow = $stmt->fetch();
    if (!$reqRow) {
        $err = 'Request not found or already resolved.';
    } else {
        $newPass = random_reset_password();
        $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($newPass, PASSWORD_DEFAULT), $reqRow['user_id']]);
        $pdo->prepare("UPDATE password_reset_requests SET status='approved', resolved_at=NOW(), resolved_by=? WHERE id=?")
            ->execute([current_user()['id'], $reqId]);
        $autoSent = mail_password_reset($reqRow['email'], $reqRow['full_name'], $reqRow['current_username'], $newPass);
        audit($pdo, 'approve_password_reset', "user #{$reqRow['user_id']} ({$reqRow['current_username']})");

        $draft = [
            'to'      => $reqRow['email'],
            'subject' => 'EESA — Your Password Has Been Reset',
            'body'    => "Hi {$reqRow['full_name']},\n\nYour EESA account password has been reset as requested.\n\nUsername: {$reqRow['current_username']}\nNew Password: $newPass\n\nPlease log in and change your password as soon as possible.\n\n— EESA Team",
            'auto_sent' => $autoSent,
        ];
        $msg = $autoSent
            ? 'Password reset. A new password was emailed automatically to ' . h($reqRow['email']) . '. A draft is also ready below.'
            : 'Password reset, but automatic sending failed — use the draft below to send it manually.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_reset'])) {
    csrf_check();
    $reqId = (int)$_POST['request_id'];
    $pdo->prepare("UPDATE password_reset_requests SET status='rejected', resolved_at=NOW(), resolved_by=? WHERE id=? AND status='pending'")
        ->execute([current_user()['id'], $reqId]);
    audit($pdo, 'reject_password_reset', "#$reqId");
    $msg = 'Request rejected.';
}

$pending = $pdo->query("SELECT pr.*, u.full_name, u.email FROM password_reset_requests pr
                         JOIN users u ON u.id = pr.user_id
                         WHERE pr.status = 'pending' ORDER BY pr.created_at DESC")->fetchAll();
$resolved = $pdo->query("SELECT pr.*, u.full_name, r.full_name AS resolver_name
                          FROM password_reset_requests pr
                          JOIN users u ON u.id = pr.user_id
                          LEFT JOIN users r ON r.id = pr.resolved_by
                          WHERE pr.status != 'pending' ORDER BY pr.resolved_at DESC LIMIT 30")->fetchAll();

require __DIR__ . '/layout_header.php';
?>
<h1>Password Reset Requests</h1>
<p class="muted">Approving a request generates a new password and emails it to the member — the same way a new account's credentials are sent on approval.</p>

<?php if ($msg): ?><div class="alert alert-ok"><?= h($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-err"><?= h($err) ?></div><?php endif; ?>

<?php if ($draft): ?>
  <?php
    $mailtoUrl = 'mailto:' . rawurlencode($draft['to'])
        . '?subject=' . rawurlencode($draft['subject'])
        . '&body=' . rawurlencode($draft['body']);
  ?>
  <div class="card" style="max-width:560px;margin-bottom:20px;border-color:var(--copper)">
    <h3>New Password Draft — <?= $draft['auto_sent'] ? 'sent automatically, and ready to resend manually' : 'send this manually' ?></h3>
    <p class="muted mono" style="font-size:13px">To: <?= h($draft['to']) ?><br>Subject: <?= h($draft['subject']) ?></p>
    <textarea id="draftBody" readonly style="min-height:160px"><?= h($draft['body']) ?></textarea>
    <div style="display:flex;gap:10px;margin-top:10px;flex-wrap:wrap">
      <a class="btn btn-primary btn-sm" href="<?= $mailtoUrl ?>">Open in your email client</a>
      <button type="button" class="btn btn-outline btn-sm" onclick="navigator.clipboard.writeText(document.getElementById('draftBody').value); this.textContent='Copied!'">Copy message</button>
    </div>
  </div>
<?php endif; ?>

<h3>Pending (<?= count($pending) ?>)</h3>
<?php if (!$pending): ?><p class="muted">No pending password reset requests.</p><?php endif; ?>
<?php foreach ($pending as $r): ?>
  <div class="card" style="margin-bottom:12px">
    <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:12px">
      <div>
        <h3 style="margin-bottom:2px"><?= h($r['full_name']) ?> <span class="muted mono" style="font-size:12px">@<?= h($r['username']) ?></span></h3>
        <p class="muted" style="margin:0;font-size:13px"><?= h($r['email']) ?></p>
        <p class="mono muted" style="font-size:12px">Requested <?= h(time_ago($r['created_at'])) ?></p>
      </div>
      <form method="POST" style="display:flex;gap:8px;align-items:center">
        <?= csrf_field() ?><input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>">
        <button class="btn btn-primary btn-sm" type="submit" name="approve_reset" onclick="return confirm('Generate and send a new password to this member?')">Approve &amp; Reset Password</button>
        <button class="btn btn-danger btn-sm" type="submit" name="reject_reset" onclick="return confirm('Reject this request?')">Reject</button>
      </form>
    </div>
  </div>
<?php endforeach; ?>

<h3 style="margin-top:28px">Recently Resolved</h3>
<?php if (!$resolved): ?><p class="muted">No resolved requests yet.</p><?php endif; ?>
<table class="admin-table">
  <tr><th>Member</th><th>Status</th><th>Resolved</th></tr>
  <?php foreach ($resolved as $r): ?>
    <tr>
      <td><?= h($r['full_name']) ?> <span class="muted mono" style="font-size:12px">@<?= h($r['username']) ?></span></td>
      <td><span class="pill pill-<?= h($r['status']) ?>"><?= h($r['status']) ?></span></td>
      <td class="muted mono" style="font-size:12px"><?= $r['resolved_at'] ? h(time_ago($r['resolved_at'])) : '—' ?></td>
    </tr>
  <?php endforeach; ?>
</table>
<?php require __DIR__ . '/layout_footer.php'; ?>
