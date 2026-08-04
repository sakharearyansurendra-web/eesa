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
    <table class="table">
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
<?php require __DIR__ . '/../includes/footer.php'; ?>
