<?php
/**
 * login.php
 *
 * A single sign-in form for everyone — students and admins alike. What
 * happens after login is entirely driven by the signed-in account's stored
 * role (checked via require_role() on every back-office page): a regular
 * member sees their dashboard with no back-office access, while
 * super_admin/admin/aptitude_manager accounts see the relevant management
 * sections. There's no separate "admin login" — one form, role decides
 * everything downstream.
 */
require_once __DIR__ . '/config.php';
$pageTitle = 'Login';
$err = null;
// Only bounce an already-logged-in visitor to the dashboard if their stored
// role is still one we recognize. If it isn't (e.g. a stale session from
// before a role-list change, or any other mismatch), clear the session and
// fall through to the normal login form instead — the alternative is an
// infinite redirect loop, since dashboard.php's require_role() would just
// send them straight back here.
if (is_logged_in()) {
    if (in_array(current_user()['role'], ALL_ROLES, true)) {
        redirect('/admin/dashboard.php');
    } else {
        session_unset();
        session_regenerate_id(true);
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    csrf_check();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if (!$user || !$user['password_hash'] || !password_verify($password, $user['password_hash'])) {
        $err = 'Invalid username or password.';
    } elseif ($user['status'] !== 'approved') {
        $err = 'Your account is not active (status: ' . h($user['status']) . '). Contact an admin.';
    } else {
        // Session only ever stores role/status — every privileged action re-checks
        // has_role()/require_role() against this, so privilege is enforced per page,
        // not assumed from a single login flag.
        $_SESSION['user'] = [
            'id'       => $user['id'],
            'username' => $user['username'],
            'full_name'=> $user['full_name'],
            'role'     => $user['role'],
        ];
        audit($pdo, 'login', $username);
        redirect('/admin/dashboard.php');
    }
}
require __DIR__ . '/includes/header.php';
?>
<section class="section">
  <div class="container">
    <div style="max-width:420px;margin:60px auto">
      <div class="eyebrow">Members &amp; admins</div>
      <h1>Sign In</h1>
      <?php if (isset($_GET['denied'])): ?>
        <div class="alert alert-err">You don't have access to that page. Sign in with an authorized account.</div>
      <?php endif; ?>
      <?php if ($err): ?><div class="alert alert-err"><?= h($err) ?></div><?php endif; ?>
      <div class="form-card">
        <form method="POST" class="stack">
          <?= csrf_field() ?>
          <div class="field"><label>Username</label><input name="username" required autofocus></div>
          <div class="field"><label>Password</label><input type="password" name="password" required></div>
            
          <p style="margin:-4px 0 4px;text-align:right">
            <a href="<?= BASE_URL ?>/pages/forgot_password.php" class="mono" style="color:var(--copper-lt);font-size:13px">Forgot password?</a>
          </p>
          <button class="btn btn-primary" type="submit" name="login" style="width:100%">Sign In</button>
            <div class="field">
  <label>Password</label>
  <div style="position:relative">
    <input type="password" name="password" id="loginPassword" required style="padding-right:44px">
    <button type="button" id="togglePassword"
      style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--slate-lt);font-size:13px;font-family:var(--font-mono)">
      Show
    </button>
  </div>
</div>
        </form>
      </div>
    </div>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
