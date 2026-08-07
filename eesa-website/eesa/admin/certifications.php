<?php
require_once __DIR__ . '/../config.php';
require_role(CONTENT_ADMIN_ROLES);
$pageTitle = 'Certifications';
$activeSection = 'certifications';
$msg = null; $err = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['issue_cert'])) {
    csrf_check();
    $userId = (int)$_POST['user_id'];
    $title = trim($_POST['title']);
    $issuedBy = trim($_POST['issued_by']);
    $issueDate = $_POST['issue_date'];

    $u = $pdo->prepare('SELECT full_name, member_id FROM users WHERE id=? LIMIT 1');
    $u->execute([$userId]);
    $target = $u->fetch();

    if (!$target || !$target['member_id']) {
        $err = 'Select a member who already has a Member ID generated (see Users & Access).';
    } elseif (!$title || !$issuedBy || !$issueDate) {
        $err = 'Fill all fields.';
    } else {
        $certNo = generate_certificate_no();
        $pdo->prepare('INSERT INTO certificates (certificate_no, user_id, member_id, full_name, title, issued_by, issue_date, created_by)
                        VALUES (?,?,?,?,?,?,?,?)')
            ->execute([$certNo, $userId, $target['member_id'], $target['full_name'], $title, $issuedBy, $issueDate, current_user()['id']]);
        audit($pdo, 'issue_certificate', "$certNo -> {$target['full_name']}");
        $msg = "Certificate issued: $certNo";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_revoke'])) {
    csrf_check();
    $id = (int)$_POST['id'];
    $newStatus = $_POST['new_status'] === 'active' ? 'active' : 'revoked';
    $pdo->prepare('UPDATE certificates SET status=? WHERE id=?')->execute([$newStatus, $id]);
    audit($pdo, 'toggle_certificate_status', "#$id -> $newStatus");
    $msg = 'Status updated.';
}

$certs = $pdo->query('SELECT * FROM certificates ORDER BY created_at DESC')->fetchAll();
$members = $pdo->query("SELECT id, full_name, member_id FROM users WHERE status='approved' AND member_id IS NOT NULL ORDER BY full_name")->fetchAll();
require __DIR__ . '/layout_header.php';
?>
<h1>Certifications</h1>
<?php if ($msg): ?><div class="alert alert-ok"><?= h($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-err"><?= h($err) ?></div><?php endif; ?>

<div class="card form-card" style="max-width:560px">
  <h3>Issue a Certificate</h3>
  <p class="muted" style="font-size:13px">Certificate No. is generated automatically. Only members with a Member ID already assigned can be selected.</p>
  <form method="POST" class="stack">
    <?= csrf_field() ?>
    <div class="field"><label>Member</label>
      <select name="user_id" required>
        <option value="">Select member</option>
        <?php foreach ($members as $m): ?>
          <option value="<?= (int)$m['id'] ?>"><?= h($m['full_name']) ?> — <?= h($m['member_id']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field"><label>Certificate Title</label><input name="title" placeholder="e.g. Completion of Basic Electronics Workshop" required></div>
    <div class="field"><label>Issued By</label><input name="issued_by" placeholder="e.g. EESA, Dept. of Electrical Engineering" required></div>
    <div class="field"><label>Date Issued</label><input type="date" name="issue_date" required></div>
    <button class="btn btn-primary" type="submit" name="issue_cert">Issue Certificate</button>
  </form>
</div>

<h2 style="margin-top:32px">All Certificates</h2>
<table class="admin-table">
  <tr><th>Cert No.</th><th>Name</th><th>Member ID</th><th>Title</th><th>Issued</th><th>Status</th><th></th></tr>
  <?php foreach ($certs as $c): ?>
    <tr>
      <td class="mono"><?= h($c['certificate_no']) ?></td>
      <td><?= h($c['full_name']) ?></td>
      <td class="mono"><?= h($c['member_id']) ?></td>
      <td><?= h($c['title']) ?></td>
      <td class="mono" style="font-size:12px"><?= h(date('d M Y', strtotime($c['issue_date']))) ?></td>
      <td><span class="pill pill-<?= $c['status']==='active'?'approved':'rejected' ?>"><?= h($c['status']) ?></span></td>
      <td style="white-space:nowrap">
        <a class="btn btn-outline btn-sm" href="<?= BASE_URL ?>/pages/verify_certificate.php?cert=<?= h($c['certificate_no']) ?>" target="_blank">View</a>
        <form method="POST" style="display:inline">
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
          <input type="hidden" name="new_status" value="<?= $c['status']==='active'?'revoked':'active' ?>">
          <button class="btn btn-danger btn-sm" type="submit" name="toggle_revoke"><?= $c['status']==='active'?'Revoke':'Reactivate' ?></button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
</table>
<?php require __DIR__ . '/layout_footer.php'; ?>
