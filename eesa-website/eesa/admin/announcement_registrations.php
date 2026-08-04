<?php
require_once __DIR__ . '/../config.php';
require_role(CONTENT_ADMIN_ROLES);

$announcement_id = $_GET['id'] ?? null;
if ($announcement_id) {
    $stmt = $pdo->prepare('
        SELECT ar.*, a.title AS announcement_title 
        FROM announcement_registrations ar
        JOIN announcements a ON a.id = ar.announcement_id
        WHERE ar.announcement_id = ?
        ORDER BY ar.registered_at DESC
    ');
    $stmt->execute([$announcement_id]);
    $registrations = $stmt->fetchAll();
} else {
    $registrations = $pdo->query('
        SELECT ar.*, a.title AS announcement_title 
        FROM announcement_registrations ar
        JOIN announcements a ON a.id = ar.announcement_id
        ORDER BY ar.registered_at DESC
    ')->fetchAll();
}

// --- CSV export (opens cleanly in Excel) ---
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $filenameBase = $announcement_id && !empty($registrations)
        ? slugify($registrations[0]['announcement_title'])
        : 'all-events';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="registrations-' . $filenameBase . '-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel renders special characters correctly
    fputcsv($out, ['Event', 'Name', 'Email', 'Phone', 'Branch & Year', 'Registered At']);
    foreach ($registrations as $reg) {
        fputcsv($out, [
            $reg['announcement_title'],
            $reg['name'],
            $reg['email'],
            $reg['phone'],
            $reg['branch_year'],
            date('d M Y, h:i A', strtotime($reg['registered_at'])),
        ]);
    }
    fclose($out);
    exit;
}

$pageTitle = 'Announcement Registrations';
require __DIR__ . '/../includes/header.php';
?>
<section class="section">
  <div class="container">
    <h1>Event Registrations</h1>
    <?php if ($announcement_id): ?>
      <p><a href="announcements.php">&larr; Back to all announcements</a></p>
      <?php if (!empty($registrations)): ?>
        <p class="muted">Showing registrations for: <strong><?= h($registrations[0]['announcement_title']) ?></strong></p>
      <?php endif; ?>
    <?php endif; ?>

    <div style="display:flex;gap:10px;margin:16px 0">
      <a class="btn btn-primary btn-sm" href="?<?= $announcement_id ? 'id=' . (int)$announcement_id . '&' : '' ?>export=csv">
        Download as Excel (CSV)
      </a>
      <button type="button" class="btn btn-outline btn-sm" id="copyTableBtn">Copy Table</button>
    </div>
    <span id="copyStatus" class="muted" style="display:none;margin-left:4px">Copied!</span>

    <table class="table" id="regTable">
      <thead>
        <tr>
          <th>Event</th>
          <th>Name</th>
          <th>Email</th>
          <th>Phone</th>
          <th>Branch & Year</th>
          <th>Registered At</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($registrations)): ?>
          <tr><td colspan="6">No registrations found.</td></tr>
        <?php else: ?>
          <?php foreach ($registrations as $reg): ?>
            <tr onclick="location.href='registration_view.php?id=<?= (int)$reg['id'] ?>'" style="cursor:pointer">
              <td><?= h($reg['announcement_title']) ?></td>
              <td><?= h($reg['name']) ?></td>
              <td><?= h($reg['email']) ?></td>
              <td><?= h($reg['phone']) ?></td>
              <td><?= h($reg['branch_year']) ?></td>
              <td><?= h(date('d M Y, h:i A', strtotime($reg['registered_at']))) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
<style>
  #regTable { border-collapse: collapse; width: 100%; }
  #regTable th, #regTable td {
    padding: 10px 12px;
    border: 1px solid #e2e2e2;
    text-align: left;
    font-size: 14px;
  }
  #regTable thead th {
    background: #f6f6f6;
    font-weight: 600;
  }
  #regTable tbody tr:hover { background: #fafafa; }
</style>
<script>
document.getElementById('copyTableBtn').addEventListener('click', function () {
  const table = document.getElementById('regTable');
  const rows = Array.from(table.querySelectorAll('tr'));
  const text = rows.map(row =>
    Array.from(row.querySelectorAll('th,td')).map(cell => cell.textContent.trim()).join('\t')
  ).join('\n');
  navigator.clipboard.writeText(text).then(function () {
    const status = document.getElementById('copyStatus');
    status.style.display = 'inline';
    setTimeout(() => { status.style.display = 'none'; }, 1500);
  });
});
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
