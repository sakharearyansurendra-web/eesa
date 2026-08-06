<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/mailer.php';
$pageTitle = 'Join EESA';

$err = null; $msg = null; $ticketId = null;

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
    $branch = trim($_POST['branch'] ?? '');
    $year = trim($_POST['year_of_study'] ?? '');

    if (!$name || !filter_var($email, FILTER_VALIDATE_EMAIL) || !$branch || !$year) {
        $err = 'Please fill all fields with a valid email.';
    } else {
        $existing = $pdo->prepare('SELECT status FROM users WHERE email = ? LIMIT 1');
        $existing->execute([$email]);
        $row = $existing->fetch();
        if ($row && $row['status'] !== 'rejected') {
            $err = 'A request with this email already exists (status: ' . h($row['status']) . ').';
        } else {
            $ticketId = generate_ticket_id();
           $branchYear = mb_substr($branch . ', ' . $year, 0, 120);
            $pdo->prepare("INSERT INTO users (full_name, email, role, status, ticket_id, email_verified, branch, year_of_study, branch_year)
                            VALUES (?, ?, 'member', 'pending', ?, 0, ?, ?, ?)")
                ->execute([$name, $email, $ticketId, $branch, $year, $branchYear]);
            $sent = mail_join_ticket($email, $name, $ticketId);
            audit($pdo, 'join_request', "$email ($ticketId)");
            $msg = $sent
                ? "Request submitted! Your ticket ID is $ticketId — we've also emailed it to you. An admin will review your request; once approved, your username and password will be emailed to you."
                : "Request submitted! Your ticket ID is $ticketId — save it for reference. We couldn't confirm the email sent, so if you don't hear back, contact " . CONTACT_EMAIL_EESA . ".";
        }
    }
}
$trackResult = null; $trackErr = null;
if (isset($_GET['ticket_id']) && trim($_GET['ticket_id']) !== '') {
    $tid = trim($_GET['ticket_id']);
    $stmt = $pdo->prepare('SELECT full_name, status, ticket_id, created_at FROM users WHERE ticket_id = ? LIMIT 1');
    $stmt->execute([$tid]);
    $trackResult = $stmt->fetch();
    if (!$trackResult) $trackErr = 'No request found with that ticket ID.';
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
<div class="field">
  <label>Branch</label>
  <select name="branch" required>
    <option value="">Select branch</option>
    <option>Electrical Engineering</option>
    <option>Electronic &amp; Telecommunication Engineering</option>
     <option>Information Technology</option>
      <option>Textile Technology</option>
      <option>Production Engineering</option>
    <option>Computer Science &amp; Engineering</option>
    <option>Mechanical Engineering</option>
    <option>Civil Engineering</option>
    <option>Chemical Engineering</option>
    <option>Instrumentation Engineering</option>
  </select>
</div>
<div class="field">
  <label>Year of Study</label>
  <select name="year_of_study" required>
    <option value="">Select year</option>
    <option>1st Year</option><option>2nd Year</option>
    <option>3rd Year</option><option>4th Year</option>
  </select>
</div>
<button class="btn btn-primary" type="submit" name="submit_join">Submit Request</button>
</form>
          </form>
        </div>
      <?php endif; ?>
<div class="form-card" style="margin-top:24px">
        <h3>Track Your Request</h3>
        <p class="muted" style="font-size:13px">Enter the ticket ID you received when you applied to check its status.</p>
        <form method="GET" class="stack">
          <div class="field"><label>Ticket ID</label><input name="ticket_id" placeholder="EESA-26-XXXXXX" value="<?= h($_GET['ticket_id'] ?? '') ?>" required></div>
          <button class="btn btn-outline" type="submit">Check Status</button>
        </form>
        <?php if ($trackErr): ?><div class="alert alert-err" style="margin-top:14px"><?= h($trackErr) ?></div><?php endif; ?>
        <?php if ($trackResult): ?>
          <?php [$label, $badgeClass] = join_status_label($trackResult['status']); ?>
          <div class="card" style="margin-top:14px">
            <p class="muted mono" style="font-size:12px">Ticket: <?= h($trackResult['ticket_id']) ?></p>
            <h3 style="margin-bottom:4px"><?= h($trackResult['full_name']) ?></h3>
            <span class="badge <?= h($badgeClass) ?>"><?= h($label) ?></span>
            <p class="muted" style="font-size:12px;margin-top:8px">Applied <?= h(time_ago($trackResult['created_at'])) ?></p>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
