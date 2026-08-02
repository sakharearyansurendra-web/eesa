<?php
require_once __DIR__ . '/../config.php';
require_role(TEAM_CHANNEL_ROLES);
$pageTitle = 'Team Channel';
$err = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_message'])) {
    csrf_check();
    $text = trim($_POST['message'] ?? '');
    if ($text !== '') {
        $pdo->prepare('INSERT INTO team_messages (user_id, message) VALUES (?, ?)')
            ->execute([current_user()['id'], $text]);
    }
    redirect('/pages/team_channel.php'); // avoid resubmission on refresh
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_message'])) {
    csrf_check();
    $id = (int)$_POST['message_id'];
    // Anyone can delete their own message; super_admin can delete any.
    if (has_role(['super_admin'])) {
        $pdo->prepare('DELETE FROM team_messages WHERE id = ?')->execute([$id]);
    } else {
        $pdo->prepare('DELETE FROM team_messages WHERE id = ? AND user_id = ?')->execute([$id, current_user()['id']]);
    }
    redirect('/pages/team_channel.php');
}

$messages = $pdo->query(
    'SELECT tm.*, u.full_name, u.role FROM team_messages tm
     JOIN users u ON u.id = tm.user_id
     ORDER BY tm.created_at DESC LIMIT 200'
)->fetchAll();

require __DIR__ . '/../includes/header.php';
?>
<section class="section">
  <div class="container">
    <div style="max-width:640px;margin:0 auto">
      <div class="eyebrow">Internal — team only</div>
      <h1>Team Channel</h1>
      <p class="muted">Visible only to approved team roles (Joint Coordinator and above) — never to plain members
      or the public. Use it for quick internal coordination.</p>

      <div class="form-card" style="margin-bottom:20px">
        <form method="POST" class="stack">
          <?= csrf_field() ?>
          <div class="field"><textarea name="message" placeholder="Write a message to the team…" required></textarea></div>
          <button class="btn btn-primary" type="submit" name="post_message">Post</button>
        </form>
      </div>

      <?php if (!$messages): ?><p class="muted">No messages yet — be the first to post.</p><?php endif; ?>
      <?php foreach ($messages as $m): ?>
        <div class="card" style="margin-bottom:10px">
          <div style="display:flex;justify-content:space-between;gap:10px;align-items:baseline">
            <strong><?= h($m['full_name']) ?></strong>
            <span class="mono muted" style="font-size:11px"><?= h(role_label($m['role'])) ?> · <?= h(time_ago($m['created_at'])) ?></span>
          </div>
          <p style="margin:6px 0 4px;color:#dfe6ef"><?= nl2br(h($m['message'])) ?></p>
          <?php if ($m['user_id'] == current_user()['id'] || has_role(['super_admin'])): ?>
            <form method="POST" style="margin-top:4px">
              <?= csrf_field() ?><input type="hidden" name="message_id" value="<?= (int)$m['id'] ?>">
              <button class="btn btn-danger btn-sm" type="submit" name="delete_message">Delete</button>
            </form>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
