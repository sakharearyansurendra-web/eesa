<?php
require_once __DIR__ . '/../config.php';
$pageTitle = 'Our Team';

$teams = $pdo->query('
    SELECT full_name, position, year_of_study, email, personal_email, profile_picture, linkedin_url, github_url, instagram_url 
    FROM users 
    WHERE role IN ("admin", "secretary", "president", "treasurer", "csd", "media_head", "prm", "joint_coordinator", "member")
      AND status = "approved"
    ORDER BY id ASC
')->fetchAll();

require __DIR__ . '/../includes/header.php';
?>
<section class="section">
  <div class="container">
    <div class="eyebrow">EESA Leadership & Committee</div>
    <h1>Our Team</h1>
    <p class="muted" style="max-width:600px;margin-bottom:32px">
      Meet the executive committee and members driving the Electrical Engineering Students Association.
    </p>

    <div class="grid grid-3">
      <?php if (empty($teams)): ?>
        <p class="muted">No team members found.</p>
      <?php else: ?>
        <?php foreach ($teams as $m): ?>
          <div class="card card-member">
            <div style="display:flex;align-items:center;gap:16px;margin-bottom:16px">
              <img 
                src="<?= $m['profile_picture'] ? BASE_URL . '/uploads/profiles/' . h($m['profile_picture']) : BASE_URL . '/assets/default-avatar.png' ?>" 
                class="member-avatar" 
                style="width:72px;height:72px;border-radius:50%;object-fit:cover;border:2px solid var(--border-color, #e2e8f0)" 
                alt="<?= h($m['full_name']) ?>"
              >
              <div>
                <h3 style="margin:0 0 4px 0"><?= h($m['full_name']) ?></h3>
                <span class="badge" style="background:var(--accent-dim, #e0f2fe);color:var(--accent-color, #0284c7);padding:2px 8px;border-radius:4px;font-size:12px;font-weight:600">
                  <?= h($m['position'] ?? 'Team Member') ?>
                </span>
              </div>
            </div>

            <p class="muted" style="font-size:14px;margin-bottom:8px">
              <strong>Year/Branch:</strong> <?= h($m['year_of_study'] ?? 'N/A') ?>
            </p>

            <div style="font-size:14px;display:flex;flex-direction:column;gap:4px;margin-bottom:16px">
              <div>
                <span class="muted">Official:</span> 
                <a href="mailto:<?= h($m['email']) ?>"><?= h($m['email']) ?></a>
              </div>
              <?php if ($m['personal_email']): ?>
                <div>
                  <span class="muted">Personal:</span> 
                  <a href="mailto:<?= h($m['personal_email']) ?>"><?= h($m['personal_email']) ?></a>
                </div>
              <?php endif; ?>
            </div>

            <?php if ($m['linkedin_url'] || $m['github_url'] || $m['instagram_url']): ?>
              <div class="social-links" style="display:flex;gap:12px;padding-top:12px;border-top:1px solid var(--border-color, #f1f5f9)">
                <?php if ($m['linkedin_url']): ?>
                  <a href="<?= h($m['linkedin_url']) ?>" target="_blank" rel="noopener">LinkedIn</a>
                <?php endif; ?>
                <?php if ($m['github_url']): ?>
                  <a href="<?= h($m['github_url']) ?>" target="_blank" rel="noopener">GitHub</a>
                <?php endif; ?>
                <?php if ($m['instagram_url']): ?>
                  <a href="<?= h($m['instagram_url']) ?>" target="_blank" rel="noopener">Instagram</a>
                <?php endif; ?>
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
