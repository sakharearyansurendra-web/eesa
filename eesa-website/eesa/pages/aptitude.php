<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/mailer.php';
$pageTitle = 'Aptitude Results';

$OTP_TTL_MIN = 10;
$err = null; $msg = null;

// Session keys used to carry state through the 3-step flow:
//  aptitude_regno         -> reg_no currently being verified/verified
//  aptitude_verified       -> bool, true once OTP is confirmed for that reg_no

// ---- Step 1: request OTP ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_otp'])) {
    csrf_check();
    $reg = trim($_POST['reg_no'] ?? '');
    if (!$reg) {
        $err = 'Enter your registration number.';
    } else {
        $email = $reg . '@' . COLLEGE_DOMAIN;
        $otp = generate_otp();
        $hash = password_hash($otp, PASSWORD_DEFAULT);
        $expires = (new DateTime('+' . $OTP_TTL_MIN . ' minutes'))->format('Y-m-d H:i:s');
        $pdo->prepare("DELETE FROM otp_codes WHERE purpose = 'aptitude_lookup' AND reference = ?")->execute([$reg]);
        $pdo->prepare("INSERT INTO otp_codes (purpose, email, reference, otp_hash, expires_at) VALUES ('aptitude_lookup', ?, ?, ?, ?)")
            ->execute([$email, $reg, $hash, $expires]);
        $sent = mail_otp($email, $otp, 'Aptitude Result Lookup');
        if ($sent) {
            $_SESSION['aptitude_regno'] = $reg;
            $_SESSION['aptitude_verified'] = false;
            $msg = "An OTP has been sent to $email. Enter it below (valid for $OTP_TTL_MIN minutes).";
        } else {
            $err = "Couldn't send the OTP email right now. Please try again shortly, or contact " . CONTACT_EMAIL_EESA . " if this keeps happening.";
        }
    }
}

// ---- Step 2: verify OTP ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_otp'])) {
    csrf_check();
    $reg = $_SESSION['aptitude_regno'] ?? '';
    $otp = trim($_POST['otp'] ?? '');
    $stmt = $pdo->prepare("SELECT * FROM otp_codes WHERE purpose = 'aptitude_lookup' AND reference = ? AND consumed = 0 ORDER BY id DESC LIMIT 1");
    $stmt->execute([$reg]);
    $row = $stmt->fetch();
    if (!$reg || !$row) {
        $err = 'Request a new OTP first.';
    } elseif (strtotime($row['expires_at']) < time()) {
        $err = 'OTP expired. Please request a new one.';
    } elseif (!password_verify($otp, $row['otp_hash'])) {
        $err = 'Incorrect OTP.';
    } else {
        $pdo->prepare('UPDATE otp_codes SET consumed = 1 WHERE id = ?')->execute([$row['id']]);
        $_SESSION['aptitude_verified'] = true;
        $msg = 'Verified! Select a test date below to view your result.';
    }
}

// ---- Step 3: view a specific result (after verified) ----
$selectedResult = null; $selectedTest = null;
if (!empty($_SESSION['aptitude_verified']) && !empty($_GET['test_id'])) {
    $reg = $_SESSION['aptitude_regno'];
    $testId = (int)$_GET['test_id'];
    $stmt = $pdo->prepare('SELECT r.*, t.test_name, t.test_date, t.question_paper_file
                            FROM aptitude_results r JOIN aptitude_tests t ON t.id = r.aptitude_test_id
                            WHERE r.aptitude_test_id = ? AND r.reg_no = ? LIMIT 1');
    $stmt->execute([$testId, $reg]);
    $selectedResult = $stmt->fetch();
}

// Dates available for this reg_no, once verified
$availableTests = [];
if (!empty($_SESSION['aptitude_verified'])) {
    $reg = $_SESSION['aptitude_regno'];
    $stmt = $pdo->prepare('SELECT t.id, t.test_name, t.test_date FROM aptitude_results r
                            JOIN aptitude_tests t ON t.id = r.aptitude_test_id
                            WHERE r.reg_no = ? ORDER BY t.test_date DESC');
    $stmt->execute([$reg]);
    $availableTests = $stmt->fetchAll();
}

require __DIR__ . '/../includes/header.php';
?>
<section class="section">
  <div class="container">
    <div style="max-width:520px;margin:0 auto">
      <div class="eyebrow">Secure lookup</div>
      <h1>Aptitude Results</h1>
      <p class="muted">Verify your registration number with a one-time password sent to your SGGS email before viewing results.</p>

      <?php if ($msg): ?><div class="alert alert-ok"><?= h($msg) ?></div><?php endif; ?>
      <?php if ($err): ?><div class="alert alert-err"><?= h($err) ?></div><?php endif; ?>

      <div class="form-card">
        <?php if (empty($_SESSION['aptitude_verified'])): ?>
          <form method="POST" class="stack">
            <?= csrf_field() ?>
            <div class="field">
              <label>Registration No.</label>
              <input name="reg_no" placeholder="e.g. 22UEE045" value="<?= h($_SESSION['aptitude_regno'] ?? '') ?>" required>
            </div>
            <button class="btn btn-primary" type="submit" name="request_otp">Get OTP</button>
          </form>

          <?php if (!empty($_SESSION['aptitude_regno'])): ?>
            <form method="POST" class="stack" style="margin-top:18px">
              <?= csrf_field() ?>
              <div class="field"><label>Enter OTP</label><input name="otp" maxlength="6" required></div>
              <button class="btn btn-outline" type="submit" name="verify_otp">Verify OTP</button>
            </form>
          <?php endif; ?>

        <?php else: ?>
          <p class="muted">Registration No: <strong class="mono"><?= h($_SESSION['aptitude_regno']) ?></strong>
            &nbsp;<a href="?logout_lookup=1" class="mono" style="color:var(--copper-lt)">(not you?)</a></p>

          <?php if (!$availableTests): ?>
            <p class="muted">No results have been published for your registration number yet.</p>
          <?php else: ?>
            <form method="GET" class="stack">
              <div class="field">
                <label>Select Test Date</label>
                <select name="test_id" onchange="this.form.submit()">
                  <option value="">Choose a test</option>
                  <?php foreach ($availableTests as $t): ?>
                    <option value="<?= (int)$t['id'] ?>" <?= (isset($_GET['test_id']) && (int)$_GET['test_id']===(int)$t['id'])?'selected':'' ?>>
                      <?= h($t['test_name']) ?> — <?= h(date('d M Y', strtotime($t['test_date']))) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </form>
          <?php endif; ?>

          <?php if ($selectedResult): ?>
            <div class="card" style="margin-top:16px">
              <h3><?= h($selectedResult['test_name']) ?></h3>
              <p class="mono muted" style="font-size:13px"><?= h(date('d M Y', strtotime($selectedResult['test_date']))) ?></p>
              <p>Score: <strong><?= h($selectedResult['score']) ?></strong></p>
              <p>Status: <?= h($selectedResult['status']) ?></p>
              <?php if ($selectedResult['remarks']): ?><p class="muted"><?= h($selectedResult['remarks']) ?></p><?php endif; ?>
              <?php if ($selectedResult['question_paper_file']): ?>
                <a class="btn btn-outline btn-sm" target="_blank"
                   href="<?= BASE_URL ?>/uploads/qp/<?= h($selectedResult['question_paper_file']) ?>">View Question Paper</a>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>
<?php
if (isset($_GET['logout_lookup'])) {
    unset($_SESSION['aptitude_regno'], $_SESSION['aptitude_verified']);
    echo '<script>location.replace("' . BASE_URL . '/pages/aptitude.php");</script>';
}
require __DIR__ . '/../includes/footer.php';
?>
