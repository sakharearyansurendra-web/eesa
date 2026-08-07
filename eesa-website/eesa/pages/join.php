<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/mailer.php';
$pageTitle = 'Join EESA';

$err = null; $msg = null; $ticketId = null;

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
    <div class="neon-wrapper">

      <?php if ($ticketId): ?>
        <form class="form">
          <p class="title">Request Submitted</p>
          <p class="message">Keep this ticket ID safe — you'll need it to track your request below.</p>
          <p class="mono" style="font-size:22px;color:var(--copper-lt);text-align:center;margin:8px 0"><?= h($ticketId) ?></p>
        </form>
      <?php else: ?>
        <form class="form" method="POST">
          <p class="title">Join EESA</p>
          <p class="message">Submit your details — an admin will review your request and email your login credentials once approved.</p>
          <?php if ($err): ?><div class="alert alert-err"><?= h($err) ?></div><?php endif; ?>
          <?= csrf_field() ?>
          <label>
            <input class="input" type="text" name="full_name" placeholder=" " required value="<?= h($_POST['full_name'] ?? '') ?>">
            <span>Full Name</span>
          </label>
          <label>
            <input class="input" type="email" name="email" placeholder=" " required value="<?= h($_POST['email'] ?? '') ?>">
            <span>Email</span>
          </label>
          <div class="flex">
            <label style="flex:1">
              <input class="input" type="text" name="branch" placeholder=" " required value="<?= h($_POST['branch'] ?? '') ?>">
              <span>Branch</span>
            </label>
            <label style="flex:1">
              <input class="input" type="text" name="year_of_study" placeholder=" " required value="<?= h($_POST['year_of_study'] ?? '') ?>">
              <span>Year</span>
            </label>
          </div>
          <button class="submit" type="submit" name="submit_join">Submit Request</button>
        </form>
      <?php endif; ?>

      <form class="form" method="GET">
        <p class="title">Track Request</p>
        <p class="message">Already applied? Enter your ticket ID to check its status.</p>
        <?php if ($trackErr): ?><div class="alert alert-err"><?= h($trackErr) ?></div><?php endif; ?>
        <label>
          <input class="input" type="text" name="ticket_id" placeholder=" " required value="<?= h($_GET['ticket_id'] ?? '') ?>">
          <span>Ticket ID</span>
        </label>
        <button class="submit" type="submit">Check Status</button>

        <?php if ($trackResult): ?>
          <?php [$label, $badgeClass] = join_status_label($trackResult['status']); ?>
          <div style="margin-top:6px;padding:14px;border-radius:10px;background:#2b2b2b;border:1px solid rgba(105,105,105,0.4)">
            <p style="margin:0 0 4px;font-weight:600"><?= h($trackResult['full_name']) ?></p>
            <p style="margin:0 0 4px" class="muted mono" style="font-size:12px">Ticket: <?= h($trackResult['ticket_id']) ?></p>
            <span class="badge <?= h($badgeClass) ?>"><?= h($label) ?></span>
          </div>
        <?php endif; ?>
      </form>

    </div>
  </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>

eesa-website/eesa/pages/team.php
<?php
require_once __DIR__ . '/../config.php';
$pageTitle = 'Team';

$years = $pdo->query('SELECT * FROM team_years ORDER BY sort_order DESC, year_label DESC')->fetchAll();

require __DIR__ . '/../includes/header.php';
?>
<section class="section">
  <div class="container">
    <div class="eyebrow">Since inception</div>
    <h1>Our Team, Year by Year</h1>
    <p class="muted" style="max-width:600px">Every batch that has led EESA — from the very first team to the one steering it today.</p>

    <div class="grid grid-3">
      <?php if (!$years): ?><p class="muted">Team records will appear here once added.</p><?php endif; ?>
      <?php foreach ($years as $y): ?>
        <a class="card-link" href="<?= BASE_URL ?>/pages/team_year.php?year=<?= urlencode($y['year_label']) ?>">
          <div class="card year-card">
            <?php if ($y['group_photo']): ?>
              <img src="<?= BASE_URL ?>/uploads/team/<?= h($y['group_photo']) ?>">
            <?php else: ?>
              <div class="thumb" style="display:flex;align-items:center;justify-content:center" ><span class="muted mono">No photo yet</span></div>
            <?php endif; ?>
            <h3><?= h($y['year_label']) ?></h3>
            <p class="muted">View this year's team &rarr;</p>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
