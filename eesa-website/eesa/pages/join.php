<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/mailer.php';
$pageTitle = 'Join EESA';

$err = null; $msg = null; $ticketId = null;

// Kept centralized here so the dropdown and the server-side validation
// always agree on what's a legal value.
const JOIN_BRANCHES = [
    'Computer Science & Engineering',
    'Information Technology',
    'Electronics & Telecommunication',
    'Mechanical Engineering',
    'Electrical Engineering',
    'Civil Engineering',
    'Chemical Engineering',
    'Production Engineering',
    'Textile Technology',
    'Instrumentation Engineering',
];
const JOIN_YEARS = ['1st Year', '2nd Year', '3rd Year', '4th Year'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_join'])) {
    csrf_check();
    $name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $memberType = $_POST['member_type'] ?? 'student'; // student | alumni | faculty

    if ($memberType === 'alumni' || $memberType === 'faculty') {
        $branch = 'NA';
        $year = $memberType === 'alumni' ? 'Alumni' : 'Faculty';
    } else {
        $branch = trim($_POST['branch'] ?? '');
        $year = trim($_POST['year_of_study'] ?? '');
        if (!in_array($branch, JOIN_BRANCHES, true)) $branch = '';
        if (!in_array($year, JOIN_YEARS, true)) $year = '';
    }

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

$postedMemberType = $_POST['member_type'] ?? 'student';
$postedBranch = $_POST['branch'] ?? '';
$postedYear = $_POST['year_of_study'] ?? '';

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
        <form class="form" method="POST" id="joinForm">
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

     <div class="type-toggle-row">
            <span>I am a</span>
            <div class="type-toggle">
              <input type="checkbox" id="isAlumni" <?= $postedMemberType === 'alumni' ? 'checked' : '' ?>>
              <label for="isAlumni" class="toggle-chip">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
                Alumni
              </label>

              <input type="checkbox" id="isFaculty" <?= $postedMemberType === 'faculty' ? 'checked' : '' ?>>
              <label for="isFaculty" class="toggle-chip">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
                Faculty
              </label>
            </div>
          </div>

          <input type="hidden" name="member_type" id="memberType" value="<?= h($postedMemberType) ?>">

          <div class="flex" id="studentFields">
            <label style="flex:1">
              <select class="input" name="branch" id="branchSelect" <?= $postedMemberType !== 'student' ? 'disabled' : 'required' ?>>
                <option value="" disabled <?= $postedBranch === '' ? 'selected' : '' ?>></option>
                <?php foreach (JOIN_BRANCHES as $b): ?>
                  <option value="<?= h($b) ?>" <?= $postedBranch === $b ? 'selected' : '' ?>><?= h($b) ?></option>
                <?php endforeach; ?>
              </select>
              <span>Branch</span>
            </label>
            <label style="flex:1">
              <select class="input" name="year_of_study" id="yearSelect" <?= $postedMemberType !== 'student' ? 'disabled' : 'required' ?>>
                <option value="" disabled <?= $postedYear === '' ? 'selected' : '' ?>></option>
                <?php foreach (JOIN_YEARS as $y): ?>
                  <option value="<?= h($y) ?>" <?= $postedYear === $y ? 'selected' : '' ?>><?= h($y) ?></option>
                <?php endforeach; ?>
              </select>
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

<script>
(function () {
  var alumniBox = document.getElementById('isAlumni');
  var facultyBox = document.getElementById('isFaculty');
  var memberType = document.getElementById('memberType');
  var branchSelect = document.getElementById('branchSelect');
  var yearSelect = document.getElementById('yearSelect');
  if (!alumniBox || !facultyBox) return; // ticket-confirmation view has none of these

  function sync() {
    var type = 'student';
    if (alumniBox.checked) type = 'alumni';
    else if (facultyBox.checked) type = 'faculty';
    memberType.value = type;

    var isStudent = type === 'student';
    branchSelect.disabled = !isStudent;
    yearSelect.disabled = !isStudent;
    branchSelect.required = isStudent;
    yearSelect.required = isStudent;
    if (!isStudent) {
      branchSelect.value = '';
      yearSelect.value = '';
    }
  }

  alumniBox.addEventListener('change', function () {
    if (alumniBox.checked) facultyBox.checked = false;
    sync();
  });
  facultyBox.addEventListener('change', function () {
    if (facultyBox.checked) alumniBox.checked = false;
    sync();
  });
  sync();
})();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
