<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/mailer.php';
$pageTitle = 'Verify Certificate';

$cert = null; $notFound = false;
$certNo = trim($_GET['cert'] ?? $_POST['cert'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lookup'])) {
    csrf_check();
    $certNo = trim($_POST['cert'] ?? '');
}

if ($certNo !== '') {
    $stmt = $pdo->prepare('SELECT * FROM certificates WHERE certificate_no = ? LIMIT 1');
    $stmt->execute([$certNo]);
    $cert = $stmt->fetch();
    if (!$cert) $notFound = true;
}

$reportMsg = null; $reportErr = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['report_issue'])) {
    csrf_check();
    $reportCert = trim($_POST['report_cert'] ?? '');
    $name = trim($_POST['reporter_name'] ?? '');
    $email = trim($_POST['reporter_email'] ?? '');
    $message = trim($_POST['message'] ?? '');
    if (!$reportCert || !$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $reportErr = 'Enter the certificate number and a valid email.';
    } else {
        $pdo->prepare('INSERT INTO certificate_verification_reports (certificate_no, reporter_name, reporter_email, message) VALUES (?,?,?,?)')
            ->execute([$reportCert, $name, $email, $message]);
        send_mail(CONTACT_EMAIL_EESA, "Certificate verification issue reported — $reportCert",
            "<p><b>Certificate No:</b> " . h($reportCert) . "</p><p><b>From:</b> " . h($name) . " (" . h($email) . ")</p><p>" . nl2br(h($message)) . "</p>");
        $reportMsg = 'Thanks — your report has been submitted for review.';
    }
}

require __DIR__ . '/../includes/header.php';
?>
<section class="section">
  <div class="container">
    <div style="max-width:560px;margin:0 auto">
      <div class="eyebrow">Certificate authenticity</div>
      <h1>Verify a Certificate</h1>
      <p class="muted">Enter the certificate number printed on the document to confirm it was issued by EESA.</p>

      <div class="form-card">
        <form method="POST" class="stack">
          <?= csrf_field() ?>
          <div class="field"><label>Certificate Number</label><input name="cert" value="<?= h($certNo) ?>" placeholder="EESA-CERT-2026-XXXXXX" required></div>
          <button class="btn btn-primary" type="submit" name="lookup">Verify</button>
        </form>
      </div>

      <?php if ($cert): ?>
        <div class="card" style="margin-top:20px;<?= $cert['status']==='active' ? 'border-color:var(--ok)' : 'border-color:var(--danger)' ?>">
          <?php if ($cert['status'] === 'active'): ?>
            <div class="alert alert-ok" style="margin-bottom:14px">✓ Verified — this certificate is authentic and active.</div>
          <?php else: ?>
            <div class="alert alert-err" style="margin-bottom:14px">✕ This certificate has been revoked.</div>
          <?php endif; ?>
          <p class="muted mono" style="font-size:12px">Certificate No: <?= h($cert['certificate_no']) ?></p>
          <h3 style="margin-bottom:4px"><?= h($cert['title']) ?></h3>
          <p>Issued to: <strong><?= h($cert['full_name']) ?></strong></p>
          <p class="mono muted" style="font-size:13px">Member ID: <?= h($cert['member_id']) ?></p>
          <p class="muted" style="font-size:13px">Issued by <?= h($cert['issued_by']) ?> on <?= h(date('d M Y', strtotime($cert['issue_date']))) ?></p>
          <a class="btn btn-outline btn-sm" style="margin-top:10px" target="_blank"
             href="<?= BASE_URL ?>/pages/certificate_print.php?cert=<?= h($cert['certificate_no']) ?>">Download Authenticity Copy</a>
        </div>
      <?php elseif ($notFound): ?>
        <div class="alert alert-err" style="margin-top:20px">No certificate found with that number. If you believe this certificate is genuine, report it below.</div>
      <?php endif; ?>

      <div class="form-card" style="margin-top:24px">
        <h3>Report a Verification Issue</h3>
        <p class="muted" style="font-size:13px">If a certificate number doesn't verify but you believe it should, let us know and we'll review it.</p>
        <?php if ($reportMsg): ?><div class="alert alert-ok"><?= h($reportMsg) ?></div><?php endif; ?>
        <?php if ($reportErr): ?><div class="alert alert-err"><?= h($reportErr) ?></div><?php endif; ?>
        <?php if (!$reportMsg): ?>
        <form method="POST" class="stack">
          <?= csrf_field() ?>
          <div class="field"><label>Certificate Number</label><input name="report_cert" value="<?= h($certNo) ?>" required></div>
          <div class="field"><label>Your Name</label><input name="reporter_name"></div>
          <div class="field"><label>Your Email</label><input type="email" name="reporter_email" required></div>
          <div class="field"><label>Message</label><textarea name="message" placeholder="Describe the issue"></textarea></div>
          <button class="btn btn-outline" type="submit" name="report_issue">Submit Report</button>
        </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
