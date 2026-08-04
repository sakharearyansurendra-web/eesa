<?php
require_once __DIR__ . '/../config.php';
require_admin_login();

$isSuperAdmin = ($_SESSION['user_role'] ?? '') === 'super_admin';
$msg = $err = null;

// Helper to construct approval draft email text
function approval_draft_text($fullName, $username, $tempPass, $memberId) {
    return "Hello " . $fullName . ",\n\n"
        . "Welcome to the Electrical Engineering Students Association (EESA)!\n"
        . "Your membership application has been approved.\n\n"
        . "Here are your credentials:\n"
        . "Member ID: " . $memberId . "\n"
        . "Username: " . $username . "\n"
        . "Temporary Password: " . $tempPass . "\n\n"
        . "Please login at " . BASE_URL . "/login.php and update your password.\n\n"
        . "Best regards,\n"
        . "EESA Executive Board";
}

// Handle Form Actions (Approve, Reject, Reapply Toggle, Role Updates)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $targetUserId = intval($_POST['user_id'] ?? 0);

    if ($targetUserId > 0) {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$targetUserId]);
        $targetUser = $stmt->fetch();

        if ($targetUser) {
            if ($action === 'approve') {
                // Generate Member ID if not present
                $memberId = $targetUser['member_id'];
                if (!$memberId) {
                    $memberId = 'EESA-' . date('Y') . '-' . str_pad($targetUser['id'], 4, '0', STR_PAD_LEFT);
                }

                // Auto-generate username & temp password if missing
                $username = $targetUser['username'] ?? strtolower(explode(' ', trim($targetUser['full_name']))[0]) . rand(100, 999);
                $tempPassword = bin2hex(random_bytes(4));
                $passwordHash = password_hash($tempPassword, PASSWORD_DEFAULT);

                $update = $pdo->prepare('
                    UPDATE users 
                    SET status = "approved", member_id = ?, username = ?, password_hash = ?, approved_at = NOW(), approved_by = ? 
                    WHERE id = ?
                ');
                $update->execute([$memberId, $username, $passwordHash, $_SESSION['user_id'] ?? null, $targetUserId]);
                
                $draftMsg = approval_draft_text($targetUser['full_name'], $username, $tempPassword, $memberId);
                $msg = 'User approved successfully! Member ID: <strong>' . h($memberId) . '</strong>. Copy approval details:<br><pre style="background:#f4f4f5;padding:8px;margin-top:8px">' . h($draftMsg) . '</pre>';
            
            } elseif ($action === 'reject') {
                $reapply = isset($_POST['allow_reapply']) ? 1 : 0;
                $update = $pdo->prepare('UPDATE users SET status = "rejected", reapply_allowed = ? WHERE id = ?');
                $update->execute([$reapply, $targetUserId]);
                $msg = 'User application rejected.';
            
            } elseif ($action === 'toggle_reapply') {
                $reapply = intval($_POST['reapply_status'] ?? 0);
                $update = $pdo->prepare('UPDATE users SET reapply_allowed = ? WHERE id = ?');
                $update->execute([$reapply, $targetUserId]);
                $msg = 'Re-application status updated.';
            
            } elseif ($action === 'update_role' && $isSuperAdmin) {
                $newRole = $_POST['role'] ?? 'member';
                $update = $pdo->prepare('UPDATE users SET role = ? WHERE id = ?');
                $update->execute([$newRole, $targetUserId]);
                $msg = 'User role updated to ' . h($newRole) . '.';
            }
        }
    }
}

// Fetch all users with optional status filtering
$statusFilter = $_GET['status'] ?? 'all';
$sql = 'SELECT * FROM users';
if ($statusFilter !== 'all') {
    $sql .= ' WHERE status = ' . $pdo->quote($statusFilter);
}
$sql .= ' ORDER BY id DESC';
$users = $pdo->query($sql)->fetchAll();

$pageTitle = 'User Management Portal';
require __DIR__ . '/layout_header.php';
?>

<section class="section">
  <div class="container">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px">
      <h1>User Management</h1>
      <div style="display:flex;gap:8px">
        <a href="users.php?status=all" class="btn <?= $statusFilter === 'all' ? 'btn-primary' : 'btn-secondary' ?>">All</a>
        <a href="users.php?status=pending" class="btn <?= $statusFilter === 'pending' ? 'btn-primary' : 'btn-secondary' ?>">Pending</a>
        <a href="users.php?status=approved" class="btn <?= $statusFilter === 'approved' ? 'btn-primary' : 'btn-secondary' ?>">Approved</a>
        <a href="users.php?status=rejected" class="btn <?= $statusFilter === 'rejected' ? 'btn-primary' : 'btn-secondary' ?>">Rejected</a>
      </div>
    </div>

    <?php if ($msg): ?><div class="alert alert-ok"><?= $msg ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-err"><?= h($err) ?></div><?php endif; ?>

    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>Member ID</th>
            <th>Full Name</th>
            <th>Email</th>
            <th>Role</th>
            <th>Status</th>
            <th>Re-apply</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($users)): ?>
            <tr><td colspan="7">No users found.</td></tr>
          <?php else: ?>
            <?php foreach ($users as $u): ?>
              <tr>
                <td><strong><?= h($u['member_id'] ?? 'N/A') ?></strong></td>
                <td>
                  <a href="<?= BASE_URL ?>/admin/user_view.php?id=<?= $u['id'] ?>">
                    <?= h($u['full_name']) ?>
                  </a>
                </td>
                <td><?= h($u['email']) ?></td>
                <td>
                  <?php if ($isSuperAdmin): ?>
                    <form method="POST" style="display:inline">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="update_role">
                      <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                      <select name="role" onchange="this.form.submit()">
                        <?php foreach (['member','admin','president','secretary','treasurer','csd','media_head','prm','joint_coordinator','super_admin'] as $r): ?>
                          <option value="<?= $r ?>" <?= $u['role'] === $r ? 'selected' : '' ?>><?= ucfirst($r) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </form>
                  <?php else: ?>
                    <?= h(ucfirst($u['role'])) ?>
                  <?php endif; ?>
                </td>
                <td>
                  <span class="badge status-<?= h($u['status']) ?>">
                    <?= h(ucfirst($u['status'])) ?>
                  </span>
                </td>
                <td>
                  <?php if ($u['status'] === 'rejected'): ?>
                    <form method="POST" style="display:inline">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="toggle_reapply">
                      <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                      <input type="hidden" name="reapply_status" value="<?= $u['reapply_allowed'] ? 0 : 1 ?>">
                      <button type="submit" class="btn btn-sm <?= $u['reapply_allowed'] ? 'btn-ok' : 'btn-err' ?>">
                        <?= $u['reapply_allowed'] ? 'Allowed' : 'Blocked' ?>
                      </button>
                    </form>
                  <?php else: ?>
                    <span class="muted">—</span>
                  <?php endif; ?>
                </td>
                <td>
                  <div style="display:flex;gap:4px">
                    <a href="<?= BASE_URL ?>/admin/user_view.php?id=<?= $u['id'] ?>" class="btn btn-sm">View Profile</a>
                    
                    <?php if ($u['status'] === 'pending'): ?>
                      <form method="POST" style="display:inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="approve">
                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-primary">Approve</button>
                      </form>

                      <form method="POST" style="display:inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="reject">
                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Reject this application?')">Reject</button>
                      </form>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</section>

<?php require __DIR__ . '/layout_footer.php'; ?>
