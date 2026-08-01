<?php
require_once __DIR__ . '/../config.php';
require_role(['super_admin','admin']);
$pageTitle = 'Contact Messages';
$activeSection = 'messages';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read'])) {
    csrf_check();
    $pdo->prepare('UPDATE contact_messages SET is_read=1 WHERE id=?')->execute([(int)$_POST['id']]);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_msg'])) {
    csrf_check();
    $pdo->prepare('DELETE FROM contact_messages WHERE id=?')->execute([(int)$_POST['id']]);
}

$messages = $pdo->query('SELECT * FROM contact_messages ORDER BY created_at DESC')->fetchAll();
require __DIR__ . '/layout_header.php';
?>
<h1>Contact Messages</h1>
<?php foreach ($messages as $m): ?>
  <div class="card" style="margin-bottom:12px;<?= $m['is_read']?'':'border-color:var(--copper)' ?>">
    <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:10px">
      <div>
        <h3 style="margin-bottom:2px"><?= h($m['name']) ?> <span class="muted mono" style="font-size:12px">&rarr; <?= h($m['sent_to']) ?></span></h3>
        <p class="muted mono" style="font-size:12px;margin:0"><?= h($m['email']) ?> · <?= h(time_ago($m['created_at'])) ?></p>
        <p style="margin-top:8px"><?= nl2br(h($m['message'])) ?></p>
      </div>
      <div style="display:flex;gap:8px">
        <?php if (!$m['is_read']): ?>
          <form method="POST"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
            <button class="btn btn-outline btn-sm" type="submit" name="mark_read">Mark Read</button></form>
        <?php endif; ?>
        <form method="POST" onsubmit="return confirm('Delete this message?')">
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
          <button class="btn btn-danger btn-sm" type="submit" name="delete_msg">Delete</button>
        </form>
      </div>
    </div>
  </div>
<?php endforeach; ?>
<?php if (!$messages): ?><p class="muted">No messages yet.</p><?php endif; ?>
<?php require __DIR__ . '/layout_footer.php'; ?>
