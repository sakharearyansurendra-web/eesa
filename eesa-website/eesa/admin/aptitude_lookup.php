<?php
require_once __DIR__ . '/../config.php';
$pageTitle = 'Aptitude Results';

$err = null; $msg = null;

// Session keys used to carry state through the flow:
//  aptitude_regno     -> reg_no currently verified
//  aptitude_verified  -> bool, true once reg_no + member_id match

// ---- Step 1: verify reg_no + member_id ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_identity'])) {
    csrf_check();
    $reg = trim($_POST['reg_no'] ?? '');
    $memberId = trim($_POST['member_id'] ?? '');

    if (!$reg || !$memberId) {
        $err = 'Enter both your registration number and member ID.';
    } else {
        $stmt = $pdo->prepare('SELECT DISTINCT reg_no FROM aptitude_results WHERE reg_no = ? AND member_id = ? LIMIT 1');
        $stmt->execute([$reg, $memberId]);
        $row = $stmt->fetch();

        if (!$row) {
            $err = 'Registration number and member ID do not match our records.';
        } else {
            $_SESSION['aptitude_regno'] = $reg;
            $_SESSION['aptitude_verified'] = true;
            $msg = 'Verified! Select a test date below to view your result.';
        }
    }
}

// ---- Step 2: view a specific result (after verified) ----
$selectedResult = null;
if (!empty($_SESSION['aptitude_verified']) && !empty($_GET['test_id'])) {
    $reg = $_SESSION['aptitude_regno'];
    $testId = (int)$_GET['test_id'];
    $stmt = $pdo->prepare('SELECT r.*, t.test_name, t.test_date, t.question_paper_file
                            FROM aptitude_results r JOIN aptitude_tests t ON t.id = r.aptitude_test_id
                            WHERE r.aptitude_test_id = ? AND r.reg_no = ? LIMIT 1');
    $stmt->execute([$testId, $reg]);
    $selectedResult = $stmt->fetch();
}

// Dates available for this reg_no, once verified
$availableTests = [];
if (!empty($_SESSION['aptitude_verified'])) {
    $reg = $_SESSION['aptitude_regno'];
    $stmt = $pdo->prepare('SELECT t.id, t.test_name, t.test_date FROM aptitude_results r
                            JOIN aptitude_tests t ON t.id = r.aptitude_test_id
                            WHERE r.reg_no = ? ORDER BY t.test_date DESC');
    $stmt->execute([$reg]);
    $availableTests = $stmt->fetchAll();
}

require __DIR__ . '/../includes/header.php';
?>
<section class="section">
  <div class="container">
    <div style="max-width:520px;margin:0 auto">
      <div class="eyebrow">Secure lookup</div>
      <h1>Aptitude Results</h1>
      <p class="muted">Enter your registration number and member ID to view your results.</p>

      <?php if ($msg): ?><div class="alert alert-ok"><?= h($msg) ?></div><?php endif; ?>
      <?php if ($err): ?><div class="alert alert-err"><?= h($err) ?></div><?php endif; ?>

      <div class="form-card">
        <?php if (empty($_SESSION['aptitude_verified'])): ?>
          <form method="POST" class="stack">
            <?= csrf_field() ?>
            <div class="field">
              <label>Registration No.</label>
              <input name="reg_no" placeholder="e.g. 22UEE045" value="<?= h($_POST['reg_no'] ?? '') ?>" required>
            </div>
            <div class="field">
              <label>Member ID</label>
              <input name="member_id" placeholder="Your member ID" required>
            </div>
            <button class="btn btn-primary" type="submit" name="verify_identity">View Results</button>
          </form>

        <?php else: ?>
          <p class="muted">Registration No: <strong class="mono"><?= h($_SESSION['aptitude_regno']) ?></strong>
            &nbsp;<a href="?logout_lookup=1" class="mono" style="color:var(--copper-lt)">(not you?)</a></p>

          <?php if (!$availableTests): ?>
            <p class="muted">No results have been published for your registration number yet.</p>
          <?php else: ?>
            <form method="GET" class="stack">
              <div class="field">
                <label>Select Test Date</label>
                <select name="test_id" onchange="this.form.submit()">
                  <option value="">Choose a test</option>
                  <?php foreach ($availableTests as $t): ?>
                    <option value="<?= (int)$t['id'] ?>" <?= (isset($_GET['test_id']) && (int)$_GET['test_id']===(int)$t['id'])?'selected':'' ?>>
                      <?= h($t['test_name']) ?> — <?= h(date('d M Y', strtotime($t['test_date']))) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </form>
          <?php endif; ?>

          <?php if ($selectedResult): ?>
            <div class="card" style="margin-top:16px">
              <h3><?= h($selectedResult['test_name']) ?></h3>
              <p class="mono muted" style="font-size:13px"><?= h(date('d M Y', strtotime($selectedResult['test_date']))) ?></p>
              <p>Score: <strong><?= h($selectedResult['score']) ?></strong></p>
              <p>Status: <?= h($selectedResult['status']) ?></p>
              <?php if ($selectedResult['remarks']): ?><p class="muted"><?= h($selectedResult['remarks']) ?></p><?php endif; ?>
              <?php if ($selectedResult['question_paper_file']): ?>
                <a class="btn btn-outline btn-sm" target="_blank"
                   href="<?= BASE_URL ?>/uploads/qp/<?= h($selectedResult['question_paper_file']) ?>">View Question Paper</a>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>
<?php
if (isset($_GET['logout_lookup'])) {
    unset($_SESSION['aptitude_regno'], $_SESSION['aptitude_verified']);
    echo '<script>location.replace("' . BASE_URL . '/pages/aptitude.php");</script>';
}
require __DIR__ . '/../includes/footer.php';
?>
