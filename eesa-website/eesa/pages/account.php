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

// ---- Edit profile fields (instant, self-service) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    csrf_check();
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $pdo->prepare('UPDATE users SET phone = ?, address = ?, bio = ? WHERE id = ?')
        ->execute([$phone, $address, $bio, $u['id']]);
    audit($pdo, 'update_own_profile', $u['username']);
    $msg = 'Profile updated.';
}

$myRequests = $pdo->prepare('SELECT * FROM username_requests WHERE user_id = ? ORDER BY created_at DESC LIMIT 5');
$myRequests->execute([$u['id']]);
$myRequests = $myRequests->fetchAll();

// Full profile row (phone/address/bio aren't in the session, only in the DB)
$profileStmt = $pdo->prepare('SELECT phone, address, bio FROM users WHERE id = ? LIMIT 1');
$profileStmt->execute([$u['id']]);
$profile = $profileStmt->fetch();

// Every team_year this person has been linked to as a team_member (current + past)
$myTeamsStmt = $pdo->prepare(
    'SELECT tm.*, ty.year_label, ty.id AS team_year_id
     FROM team_members tm JOIN team_years ty ON ty.id = tm.team_year_id
     WHERE tm.user_id = ? ORDER BY ty.year_label DESC'
);
$myTeamsStmt->execute([$u['id']]);
$myTeams = $myTeamsStmt->fetchAll();

// For each of those years, pull the full roster with contact info — visible
// only to fellow linked teammates of that same year, via this page.
$teamRosters = [];
foreach ($myTeams as $t) {
    $rosterStmt = $pdo->prepare(
        'SELECT tm.name, tm.designation, u.phone, u.email, u.full_name
         FROM team_members tm LEFT JOIN users u ON u.id = tm.user_id
         WHERE tm.team_year_id = ? ORDER BY tm.sort_order, tm.id'
    );
    $rosterStmt->execute([$t['team_year_id']]);
    $teamRosters[$t['team_year_id']] = $rosterStmt->fetchAll();
}

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
        <h3>Profile Details</h3>
        <p class="muted" style="font-size:13px">Visible to fellow team members on your team roster (see My Team below).</p>
        <form method="POST" class="stack">
          <?= csrf_field() ?>
          <div class="field"><label>Phone</label><input name="phone" value="<?= h($profile['phone'] ?? '') ?>"></div>
          <div class="field"><label>Address</label><input name="address" value="<?= h($profile['address'] ?? '') ?>"></div>
          <div class="field"><label>Bio</label><textarea name="bio"><?= h($profile['bio'] ?? '') ?></textarea></div>
          <button class="btn btn-primary" type="submit" name="update_profile">Save Profile</button>
        </form>
      </div>

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

      <?php if ($myTeams): ?>
        <div class="form-card" style="margin-top:24px">
          <h3>My Team</h3>
          <p class="muted" style="font-size:13px">Contact info here is only visible to you and your linked teammates — never shown on the public Team page.</p>
          <?php foreach ($myTeams as $t): ?>
            <h3 style="margin-top:18px;font-size:16px" class="mono"><?= h($t['year_label']) ?> — <?= h($t['designation']) ?></h3>
            <table class="admin-table">
              <tr><th>Name</th><th>Role</th><th>Phone</th><th>Email</th></tr>
              <?php foreach ($teamRosters[$t['team_year_id']] as $member): ?>
                <tr>
                  <td><?= h($member['full_name'] ?: $member['name']) ?></td>
                  <td class="muted"><?= h($member['designation']) ?></td>
                  <td class="mono"><?= h($member['phone'] ?: '—') ?></td>
                  <td class="mono"><?= h($member['email'] ?: '—') ?></td>
                </tr>
              <?php endforeach; ?>
            </table>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="form-card" style="margin-top:24px">
          <h3>My Team</h3>
          <p class="muted" style="font-size:13px">You're not linked to a team roster yet — an admin can link your account from Team management once you're added to a year's team.</p>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
