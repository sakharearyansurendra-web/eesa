<?php
require_once __DIR__ . '/../config.php';
if (!is_logged_in()) redirect('/login.php');
$pageTitle = 'My Account';
$u = current_user();
$msg = null; $err = null;

// ---- Change password (instant, self-service, any role) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    csrf_check();
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$u['id']]);
    $hash = $stmt->fetchColumn();

    if (!password_verify($current, $hash)) {
        $err = 'Current password is incorrect.';
    } elseif (strlen($new) < 8) {
        $err = 'New password must be at least 8 characters.';
    } elseif ($new !== $confirm) {
        $err = 'New password and confirmation do not match.';
    } else {
        $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($new, PASSWORD_DEFAULT), $u['id']]);
        audit($pdo, 'change_own_password', $u['username']);
        $msg = 'Password updated.';
    }
}

// ---- Request a username change (goes to admin for approval — never instant) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_username'])) {
    csrf_check();
    $requested = trim($_POST['requested_username'] ?? '');
    if (!$requested) {
        $err = 'Enter the username you\'d like to switch to.';
    } elseif ($requested === $u['username']) {
        $err = 'That\'s already your current username.';
    } else {
        $taken = $pdo->prepare('SELECT id FROM users WHERE username = ?');
        $taken->execute([$requested]);
        if ($taken->fetch()) {
            $err = 'That username is already taken.';
        } else {
            $pdo->prepare("INSERT INTO username_requests (user_id, current_username, requested_username) VALUES (?, ?, ?)")
                ->execute([$u['id'], $u['username'], $requested]);
            audit($pdo, 'request_username_change', "{$u['username']} -> $requested");
            $msg = 'Request submitted — an admin will review it. Your username stays the same until then.';
        }
    }
}

$myRequests = $pdo->prepare('SELECT * FROM username_requests WHERE user_id = ? ORDER BY created_at DESC LIMIT 5');
$myRequests->execute([$u['id']]);
$myRequests = $myRequests->fetchAll();

require __DIR__ . '/../includes/header.php';
?>
<section class="section">
  <div class="container">
    <div style="max-width:560px;margin:0 auto">
      <div class="eyebrow">Account</div>
      <h1>My Account</h1>
      <p class="muted">Signed in as <strong class="mono"><?= h($u['username']) ?></strong> ·
      <?= h(role_label($u['role'])) ?></p>

      <?php if ($msg): ?><div class="alert alert-ok"><?= h($msg) ?></div><?php endif; ?>
      <?php if ($err): ?><div class="alert alert-err"><?= h($err) ?></div><?php endif; ?>

      <div class="form-card" style="margin-bottom:24px">
        <h3>Change Password</h3>
        <p class="muted" style="font-size:13px">Takes effect immediately — no admin approval needed.</p>
        <form method="POST" class="stack">
          <?= csrf_field() ?>
          <div class="field"><label>Current Password</label><input type="password" name="current_password" required></div>
          <div class="field"><label>New Password</label><input type="password" name="new_password" required minlength="8"></div>
          <div class="field"><label>Confirm New Password</label><input type="password" name="confirm_password" required minlength="8"></div>
          <button class="btn btn-primary" type="submit" name="change_password">Update Password</button>
        </form>
      </div>

      <div class="form-card">
        <h3>Request Username Change</h3>
        <p class="muted" style="font-size:13px">Username changes always go through admin review before taking effect.</p>
        <form method="POST" class="stack">
          <?= csrf_field() ?>
          <div class="field"><label>New Username</label><input name="requested_username" placeholder="<?= h($u['username']) ?>" required></div>
          <button class="btn btn-outline" type="submit" name="request_username">Submit Request</button>
        </form>

        <?php if ($myRequests): ?>
          <h3 style="margin-top:22px;font-size:16px">Your Recent Requests</h3>
          <table class="admin-table">
            <tr><th>Requested</th><th>Status</th><th>Date</th></tr>
            <?php foreach ($myRequests as $r): ?>
              <tr>
                <td class="mono"><?= h($r['requested_username']) ?></td>
                <td><span class="pill pill-<?= h($r['status']) ?>"><?= h($r['status']) ?></span></td>
                <td class="muted" style="font-size:12px"><?= h(time_ago($r['created_at'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </table>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
