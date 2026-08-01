<?php
require_once __DIR__ . '/../config.php';
require_role(['super_admin','admin']);
$pageTitle = 'Site Settings';
$activeSection = 'settings';
$msg = null;

// Every editable field on this page, keyed by its site_settings row.
$fields = [
    'hero_eyebrow'   => ['label' => 'Homepage — Eyebrow line', 'type' => 'text',
        'default' => 'Electrical Engineering Student Association · SGGS'],
    'hero_title'     => ['label' => 'Homepage — Main heading', 'type' => 'text',
        'default' => 'Welcome to EESA'],
    'hero_lead'      => ['label' => 'Homepage — Lead paragraph', 'type' => 'textarea',
        'default' => 'By the students, for the students. EESA is powering the next generation of electrical engineers — through workshops, seminars, projects and a community that learns together.'],
    'footer_tagline' => ['label' => 'Footer tagline (shown after the copyright year on every page)', 'type' => 'text',
        'default' => 'EESA — Dept. of Electrical Engineering, SGGS. By the students, for the students.'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    csrf_check();
    foreach ($fields as $key => $meta) {
        $value = trim($_POST[$key] ?? '');
        $stmt = $pdo->prepare(
            'INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = ?'
        );
        $stmt->execute([$key, $value, $value]);
    }
    audit($pdo, 'update_site_settings', implode(',', array_keys($fields)));
    $msg = 'Site settings saved. Changes are live immediately — no redeploy needed.';
}

$current = [];
foreach ($fields as $key => $meta) {
    $current[$key] = get_setting($pdo, $key, $meta['default']);
}

require __DIR__ . '/layout_header.php';
?>
<h1>Site Settings</h1>
<p class="muted">Edit site-wide text here — changes apply the moment you save, across the whole site, with no code
changes or redeploy. For the logo, replace <span class="mono">assets/img/logo.png</span> directly in your repo; for
colors, edit the CSS variables in <span class="mono">assets/css/style.css</span> — both of those still require a
commit + redeploy since they're files, not database content.</p>

<?php if ($msg): ?><div class="alert alert-ok"><?= h($msg) ?></div><?php endif; ?>

<div class="card form-card" style="max-width:640px">
  <form method="POST" class="stack">
    <?= csrf_field() ?>
    <?php foreach ($fields as $key => $meta): ?>
      <div class="field">
        <label><?= h($meta['label']) ?></label>
        <?php if ($meta['type'] === 'textarea'): ?>
          <textarea name="<?= h($key) ?>"><?= h($current[$key]) ?></textarea>
        <?php else: ?>
          <input name="<?= h($key) ?>" value="<?= h($current[$key]) ?>">
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
    <button class="btn btn-primary" type="submit" name="save_settings">Save Settings</button>
  </form>
</div>

<div class="card" style="max-width:640px;margin-top:20px">
  <h3>What else can be edited from the admin panel</h3>
  <p class="muted" style="margin-bottom:8px">Everything below already has its own dedicated admin page with full
  create/edit/delete control — this Settings page only covers homepage/footer text that didn't live in a database
  table before.</p>
  <table class="admin-table">
    <tr><td>Announcements (post, delete, registration settings)</td><td><a href="<?= BASE_URL ?>/admin/announcements.php" style="color:var(--copper-lt)">Manage →</a></td></tr>
    <tr><td>Activities / blog posts (post, delete, photos)</td><td><a href="<?= BASE_URL ?>/admin/activities.php" style="color:var(--copper-lt)">Manage →</a></td></tr>
    <tr><td>Gallery events &amp; photos</td><td><a href="<?= BASE_URL ?>/admin/gallery.php" style="color:var(--copper-lt)">Manage →</a></td></tr>
    <tr><td>Team years &amp; members</td><td><a href="<?= BASE_URL ?>/admin/team.php" style="color:var(--copper-lt)">Manage →</a></td></tr>
    <tr><td>Department page, HOD message, staff</td><td><a href="<?= BASE_URL ?>/admin/department.php" style="color:var(--copper-lt)">Manage →</a></td></tr>
    <tr><td>Aptitude tests &amp; results</td><td><a href="<?= BASE_URL ?>/admin/aptitude.php" style="color:var(--copper-lt)">Manage →</a></td></tr>
    <tr><td>Contact messages inbox</td><td><a href="<?= BASE_URL ?>/admin/messages.php" style="color:var(--copper-lt)">Manage →</a></td></tr>
    <?php if (has_role(['super_admin'])): ?>
      <tr><td>Users, roles &amp; approvals</td><td><a href="<?= BASE_URL ?>/admin/users.php" style="color:var(--copper-lt)">Manage →</a></td></tr>
    <?php endif; ?>
  </table>
</div>

<?php require __DIR__ . '/layout_footer.php'; ?>
