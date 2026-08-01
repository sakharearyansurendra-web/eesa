<?php
/**
 * login.php
 *
 * This is the "back door" for admins and approved members. It is
 * intentionally NOT linked from the public navigation (includes/header.php)
 * or anywhere else on the site — reach it by typing/bookmarking /login.php.
 * Anyone approved (super_admin, admin, aptitude_manager, member) signs in
 * here; what they can do afterwards is decided by their role, checked with
 * require_role() on every back-office page.
 */
require_once __DIR__ . '/config.php';
$pageTitle = 'Login';
$err = null;

if (is_logged_in()) redirect('/admin/dashboard.php');

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
      <div class="eyebrow">Restricted access</div>
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
          <button class="btn btn-primary" type="submit" name="login" style="width:100%">Sign In</button>
        </form>
      </div>
    </div>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
