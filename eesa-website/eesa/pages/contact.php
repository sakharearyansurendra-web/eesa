<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/mailer.php';
$pageTitle = 'Contact';

$sent = false; $err = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_contact'])) {
    csrf_check();
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $to = ($_POST['recipient'] ?? 'eesa') === 'hod' ? 'hod' : 'eesa';

    if (!$name || !filter_var($email, FILTER_VALIDATE_EMAIL) || !$message) {
        $err = 'Please fill all fields with a valid email.';
    } else {
        $pdo->prepare('INSERT INTO contact_messages (name, email, message, sent_to) VALUES (?,?,?,?)')
            ->execute([$name, $email, $message, $to]);
        $destEmail = $to === 'hod' ? CONTACT_EMAIL_HOD : CONTACT_EMAIL_EESA;
        send_mail($destEmail, "New contact message from $name",
            "<p><b>From:</b> " . h($name) . " (" . h($email) . ")</p><p>" . nl2br(h($message)) . "</p>");
        $sent = true;
    }
}

require __DIR__ . '/../includes/header.php';
?>
<section class="section">
  <div class="container">
    <div style="max-width:560px;margin:0 auto">
      <div class="eyebrow">We'd love to hear from you</div>
      <h1>Contact Us</h1>
      <p class="muted">Write to us directly, or use the form below to reach the EESA team or the Head of Department.</p>
      <p class="mono" style="font-size:14px">
        <?= h(CONTACT_EMAIL_EESA) ?><br><?= h(CONTACT_EMAIL_HOD) ?>
      </p>

      <div class="form-card" style="margin-top:20px">
        <?php if ($sent): ?>
          <div class="alert alert-ok">Thanks — your message has been sent.</div>
        <?php else: ?>
          <?php if ($err): ?><div class="alert alert-err"><?= h($err) ?></div><?php endif; ?>
          <form method="POST" class="stack">
            <?= csrf_field() ?>
            <div class="field"><label>Your Name</label><input name="name" required></div>
            <div class="field"><label>Your Email</label><input type="email" name="email" required></div>
            <div class="field">
              <label>Send To</label>
              <select name="recipient">
                <option value="eesa">EESA (<?= h(CONTACT_EMAIL_EESA) ?>)</option>
                <option value="hod">Head of Department (<?= h(CONTACT_EMAIL_HOD) ?>)</option>
              </select>
            </div>
            <div class="field"><label>Message</label><textarea name="message" required></textarea></div>
            <button class="btn btn-primary" type="submit" name="send_contact">Send Message</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
