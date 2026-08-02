<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/mailer.php';
$pageTitle = 'Join EESA';

$err = null; $msg = null; $ticketId = null;
$trackResult = null; $trackErr = null;

// Single-step request: no OTP. The person submits their details, we create
// a pending account and email them a ticket ID right away for reference.
// Nothing is auto-approved — an admin reviews the request in
// /admin/users.php and, on approval, the system generates a username +
// temporary password and emails those credentials (see mail_approval() in
// includes/mailer.php). Account creation only ever happens through that
// admin approval step, never automatically.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_join'])) {
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
            $ticketId = generate_ticket_id();
            $pdo->prepare("INSERT INTO users (full_name, email, role, status, ticket_id, email_verified, branch_year)
                            VALUES (?, ?, 'member', 'pending', ?, 0, ?)")
                ->execute([$name, $email, $ticketId, $branch]);
            $sent = mail_join_ticket($email, $name, $ticketId);
            audit($pdo, 'join_request', "$email ($ticketId)");
            if (!$sent) {
                // The request is saved either way — admins can still see and
                // approve it from /admin/users.php — but let the applicant
                // know the confirmation email itself may not have landed.
                $msg = "Request submitted! Your ticket ID is $ticketId — save it for reference. "
                     . "We couldn't confirm the email sent, so if you don't hear back, contact " . CONTACT_EMAIL_EESA . ".";
            } else {
                $msg = "Request submitted! Your ticket ID is $ticketId — we've also emailed it to you. "
                     . "An admin will review your request; once approved, your username and password will be emailed to you.";
            }
        }
    }
}

// Track an existing request by ticket ID or email — no login needed, since
// the applicant doesn't have an account yet.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['track_request'])) {
    csrf_check();
    $lookup = trim($_POST['lookup'] ?? '');
    if (!$lookup) {
        $trackErr = 'Enter your ticket ID or the email you applied with.';
    } else {
        $stmt = $pdo->prepare('SELECT full_name, status, ticket_id, created_at FROM users WHERE ticket_id = ? OR email = ? LIMIT 1');
        $stmt->execute([$lookup, $lookup]);
        $trackResult = $stmt->fetch();
        if (!$trackResult) {
            $trackErr = 'No request found with that ticket ID or email.';
        }
    }
}

require __DIR__ . '/../includes/header.php';
?>
<section class="section">
  <div class="container">
    <div style="max-width:520px;margin:0 auto">
      <div class="eyebrow">Become a member</div>
      <h1>Join EESA</h1>
      <p class="muted">Submit your details below. An admin will review your request — once approved, you'll receive
      your username and password by email, ready to sign in at the same login page as everyone else.</p>

      <?php if ($msg): ?><div class="alert alert-ok"><?= h($msg) ?></div><?php endif; ?>
      <?php if ($err): ?><div class="alert alert-err"><?= h($err) ?></div><?php endif; ?>

      <?php if ($ticketId): ?>
        <div class="form-card">
          <h3>Your Ticket ID</h3>
          <p class="mono" style="font-size:22px;color:var(--copper-lt)"><?= h($ticketId) ?></p>
          <p class="muted">Keep this for reference in case you need to follow up.</p>
        </div>
      <?php else: ?>
        <div class="form-card">
          <form method="POST" class="stack">
            <?= csrf_field() ?>
            <div class="field"><label>Full Name</label><input name="full_name" required></div>
            <div class="field"><label>Email</label><input type="email" name="email" required></div>
            <div class="field"><label>Branch &amp; Year</label><input name="branch_year" placeholder="e.g. EE, 2nd Year" required></div>
            <button class="btn btn-primary" type="submit" name="submit_join">Submit Request</button>
          </form>
        </div>
      <?php endif; ?>

      <div class="form-card" style="margin-top:28px">
        <h3>Track Your Request</h3>
        <p class="muted" style="font-size:13px">Already applied? Check your status with your ticket ID or the email you used.</p>
        <?php if ($trackErr): ?><div class="alert alert-err"><?= h($trackErr) ?></div><?php endif; ?>
        <?php if ($trackResult): ?>
          <div class="card" style="margin-top:10px">
            <p class="muted" style="margin:0">Applicant: <strong><?= h($trackResult['full_name']) ?></strong></p>
            <p class="mono muted" style="font-size:13px;margin:4px 0">Ticket: <?= h($trackResult['ticket_id']) ?> · Applied <?= h(time_ago($trackResult['created_at'])) ?></p>
            <p style="margin-top:8px">Status:
              <?php
                $statusLabel = ['pending' => 'Pending review', 'approved' => 'Approved', 'rejected' => 'Not approved'];
                $statusClass = ['pending' => 'badge-notice', 'approved' => 'badge-ongoing', 'rejected' => 'badge-completed'];
              ?>
              <span class="badge <?= $statusClass[$trackResult['status']] ?? 'badge-notice' ?>"><?= h($statusLabel[$trackResult['status']] ?? $trackResult['status']) ?></span>
            </p>
            <?php if ($trackResult['status'] === 'approved'): ?>
              <p class="muted" style="font-size:13px;margin-top:8px">Your username and password were emailed to you — check your inbox, then sign in from the Login link in the menu.</p>
            <?php elseif ($trackResult['status'] === 'pending'): ?>
              <p class="muted" style="font-size:13px;margin-top:8px">Still waiting on an admin to review this. No action needed on your end.</p>
            <?php endif; ?>
          </div>
        <?php endif; ?>
        <form method="POST" class="stack" style="margin-top:12px">
          <?= csrf_field() ?>
          <div class="field"><label>Ticket ID or Email</label><input name="lookup" placeholder="EESA-26-XXXXXX or your email" required></div>
          <button class="btn btn-outline" type="submit" name="track_request">Check Status</button>
        </form>
      </div>
    </div>
  </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
