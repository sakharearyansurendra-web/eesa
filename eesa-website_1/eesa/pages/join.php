<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/mailer.php';
$pageTitle = 'Join EESA';

$OTP_TTL_MIN = 10;
$err = null; $msg = null; $ticketId = null;

// ---- Step 1: submit details, send OTP ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_join_otp'])) {
    csrf_check();
    $name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $branch = trim($_POST['branch_year'] ?? '');

    if (!$name || !filter_var($email, FILTER_VALIDATE_EMAIL) || !$branch) {
        $err = 'Please fill all fields with a valid email.';
    } else {
        $existing = $pdo->prepare('SELECT status FROM users WHERE email = ? LIMIT 1');
        $existing->execute([$email]);
        $row = $existing->fetch();
        if ($row && $row['status'] !== 'rejected') {
            $err = 'A request with this email already exists (status: ' . h($row['status']) . ').';
        } else {
            $otp = generate_otp();
            $hash = password_hash($otp, PASSWORD_DEFAULT);
            $expires = (new DateTime('+' . $OTP_TTL_MIN . ' minutes'))->format('Y-m-d H:i:s');
            $pdo->prepare("DELETE FROM otp_codes WHERE purpose = 'join_verify' AND email = ?")->execute([$email]);
            $pdo->prepare("INSERT INTO otp_codes (purpose, email, otp_hash, expires_at) VALUES ('join_verify', ?, ?, ?)")
                ->execute([$email, $hash, $expires]);
            mail_otp($email, $otp, 'EESA Membership Verification');
            $_SESSION['join_name'] = $name;
            $_SESSION['join_email'] = $email;
            $_SESSION['join_branch'] = $branch;
            $msg = "An OTP has been sent to $email. Enter it below to confirm your request.";
        }
    }
}

// ---- Step 2: verify OTP, create pending user + ticket ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_join_otp'])) {
    csrf_check();
    $email = $_SESSION['join_email'] ?? '';
    $otp = trim($_POST['otp'] ?? '');
    $stmt = $pdo->prepare("SELECT * FROM otp_codes WHERE purpose = 'join_verify' AND email = ? AND consumed = 0 ORDER BY id DESC LIMIT 1");
    $stmt->execute([$email]);
    $row = $stmt->fetch();

    if (!$email || !$row) {
        $err = 'Request a new OTP first.';
    } elseif (strtotime($row['expires_at']) < time()) {
        $err = 'OTP expired. Please request a new one.';
    } elseif (!password_verify($otp, $row['otp_hash'])) {
        $err = 'Incorrect OTP.';
    } else {
        $pdo->prepare('UPDATE otp_codes SET consumed = 1 WHERE id = ?')->execute([$row['id']]);
        $ticketId = generate_ticket_id();
        $pdo->prepare('INSERT INTO users (full_name, email, role, status, ticket_id, email_verified, branch_year)
                        VALUES (?, ?, "member", "pending", ?, 1, ?)')
            ->execute([$_SESSION['join_name'], $email, $ticketId, $_SESSION['join_branch']]);
        mail_join_ticket($email, $_SESSION['join_name'], $ticketId);
        unset($_SESSION['join_name'], $_SESSION['join_email'], $_SESSION['join_branch']);
        $msg = 'Verified! Your membership request has been submitted.';
    }
}

require __DIR__ . '/../includes/header.php';
?>
<section class="section">
  <div class="container">
    <div style="max-width:520px;margin:0 auto">
      <div class="eyebrow">Become a member</div>
      <h1>Join EESA</h1>
      <p class="muted">Verify your email, and an admin will review your request. You'll receive your username and password once approved.</p>

      <?php if ($msg): ?><div class="alert alert-ok"><?= h($msg) ?></div><?php endif; ?>
      <?php if ($err): ?><div class="alert alert-err"><?= h($err) ?></div><?php endif; ?>

      <?php if ($ticketId): ?>
        <div class="form-card">
          <h3>Your Ticket ID</h3>
          <p class="mono" style="font-size:22px;color:var(--copper-lt)"><?= h($ticketId) ?></p>
          <p class="muted">Keep this for reference. We've also emailed it to you.</p>
        </div>
      <?php else: ?>
        <div class="form-card">
          <?php if (empty($_SESSION['join_email'])): ?>
            <form method="POST" class="stack">
              <?= csrf_field() ?>
              <div class="field"><label>Full Name</label><input name="full_name" required></div>
              <div class="field"><label>Email</label><input type="email" name="email" required></div>
              <div class="field"><label>Branch &amp; Year</label><input name="branch_year" placeholder="e.g. EE, 2nd Year" required></div>
              <button class="btn btn-primary" type="submit" name="send_join_otp">Send OTP</button>
            </form>
          <?php else: ?>
            <p class="muted">OTP sent to <strong class="mono"><?= h($_SESSION['join_email']) ?></strong></p>
            <form method="POST" class="stack">
              <?= csrf_field() ?>
              <div class="field"><label>Enter OTP</label><input name="otp" maxlength="6" required></div>
              <button class="btn btn-primary" type="submit" name="verify_join_otp">Verify &amp; Submit Request</button>
            </form>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
