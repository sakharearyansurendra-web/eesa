<?php
require_once __DIR__ . '/../config.php';
require_admin_login();

$userId = $_GET['id'] ?? null;
if (!$userId) { die('User ID required.'); }

$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch();
if (!$user) { die('User not found.'); }

$isSuperAdmin = ($_SESSION['user_role'] ?? '') === 'super_admin';
$msg = $err = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    csrf_check();
    
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $username = trim($_POST['username']);
    $personal_email = trim($_POST['personal_email']);
    $phone = trim($_POST['phone']);
    $linkedin_url = trim($_POST['linkedin_url']);
    $github_url = trim($_POST['github_url']);
    $instagram_url = trim($_POST['instagram_url']);
    $position = trim($_POST['position']);
    $year_of_study = trim($_POST['year_of_study']);
    $reapply_allowed = isset($_POST['reapply_allowed']) ? 1 : 0;

    // Generate Member ID if needed
    $member_id = $user['member_id'];
if (!$member_id && isset($_POST['assign_member_id'])) {
    $member_id = generate_member_id($user['id']);
}

    $password_sql = '';
    $params = [$full_name, $email, $username, $personal_email, $phone, $linkedin_url, $github_url, $instagram_url, $position, $year_of_study, $member_id, $reapply_allowed];

    // Super Admin password overwrite
    if ($isSuperAdmin && !empty($_POST['new_password'])) {
        $password_sql = ', password_hash = ?';
        $params[] = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
    }

    $params[] = $userId;

    $sql = "UPDATE users SET full_name=?, email=?, username=?, personal_email=?, phone=?, linkedin_url=?, github_url=?, instagram_url=?, position=?, year_of_study=?, member_id=?, reapply_allowed=? $password_sql WHERE id=?";
    $stmt = $pdo->prepare($sql);
    
    try {
        if ($stmt->execute($params)) {
            $msg = 'User profile updated successfully.';
            $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
        } else {
            $err = 'Failed to update user profile.';
        }
    } catch (PDOException $e) {
        $err = 'Database error: ' . $e->getMessage();
    }
}

$pageTitle = 'Manage Profile - ' . h($user['full_name']);
require __DIR__ . '/../includes/header.php';
?>
<section class="section">
  <div class="container" style="max-width:720px">
    <h1>Manage User: <?= h($user['full_name']) ?></h1>
    
    <?php if ($msg): ?><div class="alert alert-ok"><?= h($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-err"><?= h($err) ?></div><?php endif; ?>

    <form method="POST" class="stack">
      <?= csrf_field() ?>
      <div class="field">
        <label>Member ID (Visible to Admins / President / Secretary / Super Admin)</label>
        <input type="text" value="<?= h($user['member_id'] ?? 'Not Generated') ?>" readonly>
        <?php if (!$user['member_id']): ?>
          <label><input type="checkbox" name="assign_member_id" value="1"> Generate Member ID now</label>
        <?php endif; ?>
      </div>

      <div class="field"><label>Full Name</label><input name="full_name" value="<?= h($user['full_name']) ?>" required></div>
      <div class="field"><label>Username</label><input name="username" value="<?= h($user['username']) ?>"></div>
      <div class="field"><label>Primary Email</label><input type="email" name="email" value="<?= h($user['email']) ?>" required></div>
      <div class="field"><label>Personal Email</label><input type="email" name="personal_email" value="<?= h($user['personal_email']) ?>"></div>
      <div class="field"><label>Phone</label><input name="phone" value="<?= h($user['phone']) ?>"></div>
      <div class="field"><label>Position / Role Title</label><input name="position" value="<?= h($user['position']) ?>"></div>
      <div class="field"><label>Year of Study / Branch</label><input name="year_of_study" value="<?= h($user['year_of_study']) ?>"></div>
      
      <div class="field">
        <label><input type="checkbox" name="reapply_allowed" value="1" <?= $user['reapply_allowed'] ? 'checked' : '' ?>> Allow user to re-apply if rejected</label>
      </div>

      <h4>Social Profiles</h4>
      <div class="field"><label>LinkedIn URL</label><input name="linkedin_url" value="<?= h($user['linkedin_url']) ?>"></div>
      <div class="field"><label>GitHub URL</label><input name="github_url" value="<?= h($user['github_url']) ?>"></div>
      <div class="field"><label>Instagram URL</label><input name="instagram_url" value="<?= h($user['instagram_url']) ?>"></div>

      <?php if ($isSuperAdmin): ?>
        <hr>
        <h4>Super Admin Credentials Overwrite</h4>
        <div class="field">
          <label>Overwrite Password</label>
          <input type="password" name="new_password" placeholder="Enter new password to overwrite">
        </div>
      <?php endif; ?>

      <button type="submit" name="update_user" class="btn btn-primary">Save Profile Changes</button>
    </form>
  </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
