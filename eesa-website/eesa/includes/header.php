<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= isset($pageTitle) ? h($pageTitle) . ' — EESA' : 'EESA — Electrical Engineering Student Association' ?></title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>

<header class="site">
  <div class="nav-row">
    <a href="<?= BASE_URL ?>/index.php" class="brand">
      <?php $logoPath = __DIR__ . '/../assets/img/logo.png'; ?>
      <?php if (file_exists($logoPath)): ?>
        <img src="<?= BASE_URL ?>/assets/img/logo.png" alt="EESA logo" style="width:38px;height:38px;border-radius:8px;object-fit:cover">
      <?php else: ?>
        <span class="brand-mark">EE</span>
      <?php endif; ?>
      <span>
        <span class="brand-name">EESA</span>
        <span class="brand-tag">by students, for students</span>
      </span>
    </a>
    <button class="burger" id="burger" aria-label="Open menu" aria-expanded="false">
      <span></span><span></span><span></span>
    </button>
  </div>
  <div class="trace"></div>
</header>

<div class="nav-overlay" id="navOverlay"></div>
<nav class="side-menu" id="sideMenu" aria-label="Site navigation">
  <a href="<?= BASE_URL ?>/index.php">Home</a>
  <a href="<?= BASE_URL ?>/pages/announcements.php">Announcements</a>
  <a href="<?= BASE_URL ?>/pages/activities.php">Activities</a>
  <a href="<?= BASE_URL ?>/pages/team.php">Team</a>
  <a href="<?= BASE_URL ?>/pages/department.php">Department</a>
  <a href="<?= BASE_URL ?>/pages/gallery.php">Gallery</a>
  <a href="<?= BASE_URL ?>/pages/aptitude.php">Aptitude Results</a>
  <a href="<?= BASE_URL ?>/pages/contact.php">Contact</a>
  <?php if (is_logged_in()): ?>
    <a href="<?= BASE_URL ?>/pages/account.php">My Account</a>

    <a href="<?= BASE_URL ?>/admin/dashboard.php">Dashboard</a>
    <a href="<?= BASE_URL ?>/logout.php">Logout (<?= h(current_user()['username']) ?>)</a>
  <?php else: ?>
    <a href="<?= BASE_URL ?>/login.php">Login</a>
    <div class="side-cta">
      <a href="<?= BASE_URL ?>/pages/join.php" class="btn btn-primary" style="flex:1;text-align:center;border-bottom:none;">Join EESA</a>
    </div>
  <?php endif; ?>
</nav>

<main>
