<?php
// Include AFTER require_role() has already run on the calling page.
$u = current_user();
$activeSection = $activeSection ?? '';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= isset($pageTitle) ? h($pageTitle) . ' — Admin — EESA' : 'Admin — EESA' ?></title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="admin-shell">
  <aside class="admin-side">
    <div class="brand" style="margin-bottom:22px">
      <?php $logoPath = __DIR__ . '/../assets/img/logo.png'; ?>
      <?php if (file_exists($logoPath)): ?>
        <img src="<?= BASE_URL ?>/assets/img/logo.png" alt="EESA logo" style="width:32px;height:32px;border-radius:8px;object-fit:cover">
      <?php else: ?>
        <span class="brand-mark">EE</span>
      <?php endif; ?>
      <span class="brand-name" style="font-size:15px">Admin</span>
    </div>
    <a href="<?= BASE_URL ?>/admin/dashboard.php" class="<?= $activeSection==='dashboard'?'active':'' ?>">Dashboard</a>

    <?php if (has_role(CONTENT_ADMIN_ROLES)): ?>
      <a href="<?= BASE_URL ?>/admin/settings.php" class="<?= $activeSection==='settings'?'active':'' ?>">Site Settings</a>
      <a href="<?= BASE_URL ?>/admin/announcements.php" class="<?= $activeSection==='announcements'?'active':'' ?>">Announcements</a>
      <a href="<?= BASE_URL ?>/admin/activities.php" class="<?= $activeSection==='activities'?'active':'' ?>">Activities</a>
      <a href="<?= BASE_URL ?>/admin/gallery.php" class="<?= $activeSection==='gallery'?'active':'' ?>">Gallery</a>
      <a href="<?= BASE_URL ?>/admin/team.php" class="<?= $activeSection==='team'?'active':'' ?>">Team</a>
      <a href="<?= BASE_URL ?>/admin/department.php" class="<?= $activeSection==='department'?'active':'' ?>">Department</a>
      <a href="<?= BASE_URL ?>/admin/messages.php" class="<?= $activeSection==='messages'?'active':'' ?>">Contact Messages</a>
    <?php endif; ?>

    <?php if (has_role(APTITUDE_ROLES)): ?>
      <a href="<?= BASE_URL ?>/admin/aptitude.php" class="<?= $activeSection==='aptitude'?'active':'' ?>">Aptitude Results</a>
    <?php endif; ?>

<?php if (has_role(['super_admin', 'secretary', 'president'])): ?>
      <a href="<?= BASE_URL ?>/admin/users.php" class="<?= $activeSection==='users'?'active':'' ?>">Users &amp; Access</a>
    <?php endif; ?>
    <?php if (has_role(['super_admin'])): ?>
      <a href="<?= BASE_URL ?>/admin/username_requests.php" class="<?= $activeSection==='username_requests'?'active':'' ?>">Username Requests</a>
    <?php endif; ?>
    <?php if (has_role(['super_admin', 'admin', 'secretary', 'president'])): ?>
      <a href="<?= BASE_URL ?>/admin/password_resets.php" class="<?= $activeSection==='password_resets'?'active':'' ?>">Password Resets</a>
    <?php endif; ?>
    <?php if (has_role(['super_admin'])): ?>
      <a href="<?= BASE_URL ?>/admin/audit.php" class="<?= $activeSection==='audit'?'active':'' ?>">Audit Log</a>
    <?php endif; ?>

    <div style="margin-top:24px;border-top:1px solid rgba(255,255,255,0.08);padding-top:14px">
      <div class="mono muted" style="font-size:12px;padding:0 12px 8px"><?= h($u['full_name']) ?> · <?= h(role_label($u['role'])) ?></div>
      <a href="<?= BASE_URL ?>/pages/account.php">My Account</a>
      <a href="<?= BASE_URL ?>/index.php">View Site</a>
      <a href="<?= BASE_URL ?>/logout.php">Logout</a>
    </div>
  </aside>
  <main class="admin-main">
