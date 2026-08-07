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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_upload_certs'])) {
    csrf_check();
    if (empty($_FILES['certs_csv']['tmp_name'])) {
        $err = 'Choose a CSV file.';
    } else {
        $fh = fopen($_FILES['certs_csv']['tmp_name'], 'r');
        $row = 0; $issued = 0; $skipped = 0; $skippedRows = [];
        while (($cols = fgetcsv($fh)) !== false) {
            $row++;
            if ($row === 1 && stripos($cols[0], 'member') !== false) continue; // optional header
            [$memberId, $title, $issuedBy, $issueDate] = array_map('trim', array_pad($cols, 4, ''));
            if (!$memberId || !$title) { $skipped++; $skippedRows[] = "Row $row (missing data)"; continue; }

            $u = $pdo->prepare('SELECT id, full_name FROM users WHERE member_id = ? LIMIT 1');
            $u->execute([$memberId]);
            $target = $u->fetch();
            if (!$target) { $skipped++; $skippedRows[] = "Row $row (member ID $memberId not found)"; continue; }

            $certNo = generate_certificate_no();
            $pdo->prepare('INSERT INTO certificates (certificate_no, user_id, member_id, full_name, title, issued_by, issue_date, created_by)
                            VALUES (?,?,?,?,?,?,?,?)')
                ->execute([$certNo, $target['id'], $memberId, $target['full_name'], $title, $issuedBy ?: 'EESA', $issueDate ?: date('Y-m-d'), current_user()['id']]);
            $issued++;
        }
        fclose($fh);
        audit($pdo, 'bulk_issue_certificates', "issued:$issued skipped:$skipped");
        $msg = "Bulk upload processed — issued $issued certificate(s)"
             . ($skipped ? ", skipped $skipped (" . implode('; ', array_slice($skippedRows, 0, 5)) . (count($skippedRows) > 5 ? '…' : '') . ")" : '.');
    }
}

// ---- Single-row toggle (active <-> revoked) — status is read fresh, so no
// hidden field is needed; a click always flips whatever the row's current
// status actually is. ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_revoke'])) {
    csrf_check();
    $id = (int)$_POST['toggle_revoke'];
    $cur = $pdo->prepare('SELECT status FROM certificates WHERE id=?');
    $cur->execute([$id]);
    $curStatus = $cur->fetchColumn();
    if ($curStatus !== false) {
        $newStatus = $curStatus === 'active' ? 'revoked' : 'active';
        $pdo->prepare('UPDATE certificates SET status=? WHERE id=?')->execute([$newStatus, $id]);
        audit($pdo, 'toggle_certificate_status', "#$id -> $newStatus");
        $msg = 'Status updated.';
    }
}

// ---- Single-row delete ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_cert'])) {
    csrf_check();
    $id = (int)$_POST['delete_cert'];
    $stmt = $pdo->prepare('SELECT certificate_no FROM certificates WHERE id=?');
    $stmt->execute([$id]);
    $certNo = $stmt->fetchColumn();
    $pdo->prepare('DELETE FROM certificates WHERE id=?')->execute([$id]);
    audit($pdo, 'delete_certificate', $certNo ?: "#$id");
    $msg = 'Certificate deleted.';
}

// ---- Bulk actions: activate / revoke / delete a checked set ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_bulk'])) {
    csrf_check();
    $ids = array_values(array_filter(array_map('intval', $_POST['cert_ids'] ?? [])));
    $action = $_POST['bulk_action'] ?? '';

    if (!$ids) {
        $err = 'Select at least one certificate first.';
    } elseif (!in_array($action, ['activate', 'revoke', 'delete'], true)) {
        $err = 'Choose a bulk action.';
    } else {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        if ($action === 'delete') {
            $pdo->prepare("DELETE FROM certificates WHERE id IN ($placeholders)")->execute($ids);
            audit($pdo, 'bulk_delete_certificates', implode(',', $ids));
            $msg = count($ids) . ' certificate(s) deleted.';
        } else {
            $newStatus = $action === 'activate' ? 'active' : 'revoked';
            $pdo->prepare("UPDATE certificates SET status=? WHERE id IN ($placeholders)")
                ->execute(array_merge([$newStatus], $ids));
            audit($pdo, 'bulk_' . $action . '_certificates', implode(',', $ids));
            $msg = count($ids) . ' certificate(s) ' . ($action === 'activate' ? 'activated' : 'revoked') . '.';
        }
    }
}

// ---- Search + filters (GET, shared by the table view and the CSV export) ----
$q            = trim($_GET['q'] ?? '');
$statusFilter = in_array($_GET['status'] ?? '', ['active', 'revoked'], true) ? $_GET['status'] : '';
$fromDate     = $_GET['from_date'] ?? '';
$toDate       = $_GET['to_date'] ?? '';

$where = [];
$params = [];
if ($q !== '') {
    $where[] = '(full_name LIKE ? OR certificate_no LIKE ? OR member_id LIKE ? OR title LIKE ?)';
    $like = "%$q%";
    array_push($params, $like, $like, $like, $like);
}
if ($statusFilter !== '') {
    $where[] = 'status = ?';
    $params[] = $statusFilter;
}
if ($fromDate !== '') {
    $where[] = 'issue_date >= ?';
    $params[] = $fromDate;
}
if ($toDate !== '') {
    $where[] = 'issue_date <= ?';
    $params[] = $toDate;
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$stmt = $pdo->prepare("SELECT * FROM certificates $whereSql ORDER BY created_at DESC");
$stmt->execute($params);
$certs = $stmt->fetchAll();

// ---- Download all (or filtered) issued certificates as CSV — must run
// before any HTML output ----
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="eesa-certificates-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Certificate No', 'Name', 'Member ID', 'Title', 'Issued By', 'Issue Date', 'Status']);
    foreach ($certs as $c) {
        fputcsv($out, [
            $c['certificate_no'],
            $c['full_name'],
            $c['member_id'],
            $c['title'],
            $c['issued_by'],
            date('d M Y', strtotime($c['issue_date'])),
            $c['status'],
        ]);
    }
    fclose($out);
    exit;
}

$members = $pdo->query("SELECT id, full_name, member_id FROM users WHERE status='approved' AND member_id IS NOT NULL ORDER BY full_name")->fetchAll();

// Build the querystring for the "download filtered" link, minus `export`
$exportQuery = $_GET;
unset($exportQuery['export']);
$exportQuery['export'] = 'csv';

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
<div class="card form-card" style="max-width:560px;margin-top:24px">
  <h3>Bulk Upload Certificates (CSV)</h3>
  <p class="muted" style="font-size:13px">
    CSV columns: <span class="mono">member_id, title, issued_by, issue_date</span> (header row optional).
    Each row is matched to a member by their existing Member ID — unmatched rows are skipped and listed in the result message. Works just like the Aptitude CSV upload.
  </p>
  <form method="POST" enctype="multipart/form-data" class="stack">
    <?= csrf_field() ?>
    <label class="cert-dropzone" for="certsCsvInput">
      <div class="cert-dropzone-icon">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 3v12m0 0l-4-4m4 4l4-4M4 17v2a2 2 0 002 2h12a2 2 0 002-2v-2"/>
        </svg>
      </div>
      <div class="cert-dropzone-text" id="certsCsvLabel"><span>Click to choose a CSV file</span></div>
      <input id="certsCsvInput" type="file" name="certs_csv" accept=".csv" required
             onchange="document.getElementById('certsCsvLabel').innerHTML = '<span>' + (this.files[0] ? this.files[0].name : 'Click to choose a CSV file') + '</span>'">
    </label>
    <button class="btn btn-primary" type="submit" name="bulk_upload_certs" style="margin-top:14px">Upload &amp; Issue Certificates</button>
  </form>
</div>
<h2 style="margin-top:32px">All Certificates</h2>

<!-- Search & filter bar -->
<form method="GET" class="card" style="margin-bottom:16px;display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
  <div class="field" style="margin-bottom:0;flex:1;min-width:220px">
    <label>Search</label>
    <input type="text" name="q" value="<?= h($q) ?>" placeholder="Name, cert no, member ID, or title">
  </div>
  <div class="field" style="margin-bottom:0">
    <label>Status</label>
    <select name="status">
      <option value="">All</option>
      <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
      <option value="revoked" <?= $statusFilter === 'revoked' ? 'selected' : '' ?>>Revoked</option>
    </select>
  </div>
  <div class="field" style="margin-bottom:0">
    <label>Issued From</label>
    <input type="date" name="from_date" value="<?= h($fromDate) ?>">
  </div>
  <div class="field" style="margin-bottom:0">
    <label>Issued To</label>
    <input type="date" name="to_date" value="<?= h($toDate) ?>">
  </div>
  <button class="btn btn-outline btn-sm" type="submit">Filter</button>
  <?php if ($q !== '' || $statusFilter !== '' || $fromDate !== '' || $toDate !== ''): ?>
    <a class="btn btn-outline btn-sm" href="certifications.php">Clear</a>
  <?php endif; ?>
  <a class="btn btn-primary btn-sm" href="?<?= h(http_build_query($exportQuery)) ?>">
    Download <?= ($q !== '' || $statusFilter !== '' || $fromDate !== '' || $toDate !== '') ? 'Filtered' : 'All' ?> (CSV)
  </a>
</form>

<form method="POST" id="bulkForm">
  <?= csrf_field() ?>
  <div style="display:flex;gap:10px;align-items:center;margin-bottom:10px;flex-wrap:wrap">
    <select name="bulk_action" style="width:200px">
      <option value="">Bulk action…</option>
      <option value="activate">Activate selected</option>
      <option value="revoke">Revoke selected</option>
      <option value="delete">Delete selected</option>
    </select>
    <button class="btn btn-outline btn-sm" type="submit" name="apply_bulk"
            onclick="return confirmBulk(this.form)">Apply</button>
    <span class="muted mono" style="font-size:12px"><?= count($certs) ?> shown</span>
  </div>

  <table class="admin-table">
    <tr>
      <th style="width:28px"><input type="checkbox" id="selectAll"></th>
      <th>Cert No.</th><th>Name</th><th>Member ID</th><th>Title</th><th>Issued</th><th>Status</th><th></th>
    </tr>
    <?php if (!$certs): ?>
      <tr><td colspan="8" class="muted">No certificates match your filters.</td></tr>
    <?php endif; ?>
    <?php foreach ($certs as $c): ?>
      <tr>
        <td><input type="checkbox" name="cert_ids[]" value="<?= (int)$c['id'] ?>" class="rowCheck"></td>
        <td class="mono"><?= h($c['certificate_no']) ?></td>
        <td><?= h($c['full_name']) ?></td>
        <td class="mono"><?= h($c['member_id']) ?></td>
        <td><?= h($c['title']) ?></td>
        <td class="mono" style="font-size:12px"><?= h(date('d M Y', strtotime($c['issue_date']))) ?></td>
        <td><span class="pill pill-<?= $c['status'] === 'active' ? 'approved' : 'rejected' ?>"><?= h($c['status']) ?></span></td>
        <td style="white-space:nowrap">
          <a class="btn btn-outline btn-sm" href="<?= BASE_URL ?>/pages/verify_certificate.php?cert=<?= h($c['certificate_no']) ?>" target="_blank">View</a>
          <button class="btn btn-danger btn-sm" type="submit" name="toggle_revoke" value="<?= (int)$c['id'] ?>">
            <?= $c['status'] === 'active' ? 'Revoke' : 'Reactivate' ?>
          </button>
          <button class="btn btn-danger btn-sm" type="submit" name="delete_cert" value="<?= (int)$c['id'] ?>"
                  onclick="return confirm('Permanently delete certificate <?= h(addslashes($c['certificate_no'])) ?>?');">
            Delete
          </button>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
</form>

<script>
document.getElementById('selectAll').addEventListener('change', function () {
  document.querySelectorAll('.rowCheck').forEach(cb => cb.checked = this.checked);
});
function confirmBulk(form) {
  const action = form.bulk_action.value;
  const count = form.querySelectorAll('.rowCheck:checked').length;
  if (!action) { alert('Choose a bulk action first.'); return false; }
  if (!count) { alert('Select at least one certificate.'); return false; }
  const verb = action === 'delete' ? 'permanently delete' : action;
  return confirm(`Are you sure you want to ${verb} ${count} certificate(s)?`);
}
</script>
<?php require __DIR__ . '/layout_footer.php'; ?>
