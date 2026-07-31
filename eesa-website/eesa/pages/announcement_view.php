<?php
require_once __DIR__ . '/../config.php';

$slug = $_GET['slug'] ?? '';
$stmt = $pdo->prepare('SELECT * FROM announcements WHERE slug = ? LIMIT 1');
$stmt->execute([$slug]);
$a = $stmt->fetch();
if (!$a) { http_response_code(404); die('Announcement not found.'); }

$status = announcement_status($a['event_datetime'], $a['registration_close']);
$pageTitle = $a['title'];
$regMsg = null; $regErr = null;

// Handle registration submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    csrf_check();
    if (!$a['registration_open'] || $status === 'completed') {
        $regErr = 'Registration is closed for this event.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $branch = trim($_POST['branch_year'] ?? '');
        if (!$name || !filter_var($email, FILTER_VALIDATE_EMAIL) || !$phone) {
            $regErr = 'Please fill all required fields with a valid email.';
        } else {
            try {
                $ins = $pdo->prepare(
                    'INSERT INTO announcement_registrations (announcement_id, name, email, phone, branch_year) VALUES (?,?,?,?,?)'
                );
                $ins->execute([$a['id'], $name, $email, $phone, $branch]);
                $regMsg = 'You are registered! We will contact you with further details.';
            } catch (PDOException $e) {
                $regErr = 'You have already registered with this email.';
            }
        }
    }
}

require __DIR__ . '/../includes/header.php';
?>
<section class="section">
  <div class="container">
    <div style="max-width:720px;margin:0 auto">
      <div class="eyebrow"><?= status_badge($status) ?></div>
      <h1><?= h($a['title']) ?></h1>
      <?php if ($a['event_datetime']): ?>
        <p class="mono muted"><?= h(date('d M Y, h:i A', strtotime($a['event_datetime']))) ?> IST
          <?= $a['venue'] ? ' · ' . h($a['venue']) : '' ?></p>
      <?php endif; ?>

      <?php if ($a['cover_image']): ?>
        <div class="post-hero"><img src="<?= BASE_URL ?>/uploads/activities/<?= h($a['cover_image']) ?>" style="width:100%;height:100%;object-fit:cover"></div>
      <?php endif; ?>

      <div class="post-body"><?= $a['body'] ?></div>

      <?php if ($a['registration_open']): ?>
        <div class="form-card" style="margin-top:32px;max-width:480px">
          <h3>Register for this Event</h3>
          <?php if ($status === 'completed'): ?>
            <p class="muted">Registration is closed — this event has been completed.</p>
          <?php else: ?>
            <?php if ($regMsg): ?><div class="alert alert-ok"><?= h($regMsg) ?></div><?php endif; ?>
            <?php if ($regErr): ?><div class="alert alert-err"><?= h($regErr) ?></div><?php endif; ?>
            <?php if (!$regMsg): ?>
            <form method="POST" class="stack">
              <?= csrf_field() ?>
              <div class="field"><label>Full Name</label><input name="name" required></div>
              <div class="field"><label>Email</label><input type="email" name="email" required></div>
              <div class="field"><label>Phone</label><input name="phone" required></div>
              <div class="field"><label>Branch &amp; Year</label><input name="branch_year" placeholder="e.g. EE, 3rd Year"></div>
              <button class="btn btn-primary" type="submit" name="register">Register</button>
            </form>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
