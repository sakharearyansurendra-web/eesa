<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/mailer.php';
$pageTitle = 'Forgot Password';
$err = null; $msg = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_reset'])) {
    csrf_check();
    $identifier = trim($_POST['identifier'] ?? '');
    if (!$identifier) {
        $err = 'Enter your username or email.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE (username = ? OR email = ? OR personal_email = ?) AND status = 'approved' LIMIT 1");
        $stmt->execute([$identifier, $identifier, $identifier]);
        $user = $stmt->fetch();

        if (!$user) {
            // Same message whether or not a match was found — don't reveal
            // account existence to an anonymous visitor.
            $msg = 'If an active account matches those details, a password reset request has been submitted for admin review.';
        } else {
            $existing = $pdo->prepare("SELECT id FROM password_reset_requests WHERE user_id = ? AND status = 'pending'");
            $existing->execute([$user['id']]);
            if ($existing->fetch()) {
                $msg = 'A password reset request for this account is already pending admin review.';
            } else {
                $pdo->prepare('INSERT INTO password_reset_requests (user_id, username) VALUES (?, ?)')
                    ->execute([$user['id'], $user['username']]);
                audit($pdo, 'password_reset_request', $user['username']);
                $msg = 'If an active account matches those details, a password reset request has been submitted for admin review. You will receive an email with a new password once it is approved.';
            }
        }
    }
}

require __DIR__ . '/../includes/header.php';
?>
<section class="section">
  <div class="container">
    <div style="max-width:480px;margin:0 auto">
      <div class="eyebrow">Account recovery</div>
      <h1>Forgot Password</h1>
      <p class="muted">Enter your username or email. An admin will review the request and, once approved, a new password will be emailed to you — just like the credentials you got when your account was first approved.</p>

      <?php if ($msg): ?><div class="alert alert-ok"><?= h($msg) ?></div><?php endif; ?>
      <?php if ($err): ?><div class="alert alert-err"><?= h($err) ?></div><?php endif; ?>

      <?php if (!$msg): ?>
      <div class="form-card">
        <form method="POST" class="stack">
          <?= csrf_field() ?>
          <div class="field"><label>Username or Email</label><input name="identifier" required></div>
          <button class="btn btn-primary" type="submit" name="submit_reset">Request Password Reset</button>
        </form>
      </div>
      <?php endif; ?>

      <p class="muted" style="margin-top:16px"><a href="<?= BASE_URL ?>/login.php" style="color:var(--copper-lt)">&larr; Back to Sign In</a></p>
    </div>
  </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
