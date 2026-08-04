<?php
require_once __DIR__ . '/../config.php';
require_role(CONTENT_ADMIN_ROLES); // Ensure administrative privileges

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('
    SELECT ar.*, a.title AS announcement_title, a.slug AS announcement_slug
    FROM announcement_registrations ar
    JOIN announcements a ON a.id = ar.announcement_id
    WHERE ar.id = ?
    LIMIT 1
');
$stmt->execute([$id]);
$reg = $stmt->fetch();
if (!$reg) { http_response_code(404); die('Registration not found.'); }

$pageTitle = 'Registration Details';
require __DIR__ . '/../includes/header.php';
?>
<section class="section">
  <div class="container">
    <h1>Registration Details</h1>
    <p><a href="announcement_registrations.php?id=<?= (int)$reg['announcement_id'] ?>">&larr; Back to registrations for this event</a></p>

    <div class="card form-card" style="max-width:480px">
      <div class="field"><label>Event</label>
        <p><a href="<?= BASE_URL ?>/pages/announcement_view.php?slug=<?= h($reg['announcement_slug']) ?>" target="_blank"><?= h($reg['announcement_title']) ?></a></p>
      </div>
      <div class="field"><label>Full Name</label><p><?= h($reg['name']) ?></p></div>
      <div class="field"><label>Email</label><p><?= h($reg['email']) ?></p></div>
      <div class="field"><label>Phone</label><p><?= h($reg['phone']) ?></p></div>
      <div class="field"><label>Branch &amp; Year</label><p><?= h($reg['branch_year'] ?: '—') ?></p></div>
      <div class="field"><label>Registered At</label><p><?= h(date('d M Y, h:i A', strtotime($reg['created_at']))) ?></p></div>
    </div>
  </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
