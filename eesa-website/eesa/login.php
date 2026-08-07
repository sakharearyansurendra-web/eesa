<?php
require_once __DIR__ . '/config.php';
$pageTitle = 'Login';
$err = null;

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
    <?php if (isset($_GET['denied'])): ?>
      <div class="alert alert-err" style="max-width:380px;margin:0 auto 14px"><?= h('You don\'t have access to that page. Sign in with an authorized account.') ?></div>
    <?php endif; ?>
    <?php if ($err): ?>
      <div class="alert alert-err" style="max-width:380px;margin:0 auto 14px"><?= h($err) ?></div>
    <?php endif; ?>

    <div class="glitch-form-wrapper">
      <form class="glitch-card" method="POST">
        <?= csrf_field() ?>
        <div class="card-header">
          <div class="card-title">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
              <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
              <path d="M14 3v4a1 1 0 0 0 1 1h4"></path>
              <path d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2z"></path>
              <path d="M12 11.5a3 3 0 0 0 -3 2.824v1.176a3 3 0 0 0 6 0v-1.176a3 3 0 0 0 -3 -2.824z"></path>
            </svg>
            <span>SECURE_DATA</span>
          </div>
          <div class="card-dots"><span></span><span></span><span></span></div>
        </div>
        <div class="card-body">
          <div class="form-group">
            <input type="text" id="username" name="username" required value="<?= h($_POST['username'] ?? '') ?>" placeholder="" autofocus>
            <label for="username" class="form-label" data-text="USERNAME">USERNAME</label>
          </div>
          <div class="form-group">
            <input type="password" id="password" name="password" required placeholder="">
            <label for="password" class="form-label" data-text="ACCESS_KEY">ACCESS_KEY</label>
          </div>
          <button data-text="INITIATE_CONNECTION" type="submit" name="login" class="submit-btn">
            <span class="btn-text">INITIATE_CONNECTION</span>
          </button>
          <p style="text-align:center;margin:-8px 0 0">
            <a href="<?= BASE_URL ?>/pages/forgot_password.php" class="mono" style="color:var(--copper-lt);font-size:12px">Forgot password?</a>
          </p>
        </div>
      </form>
    </div>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
