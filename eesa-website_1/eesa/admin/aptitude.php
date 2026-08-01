<?php
require_once __DIR__ . '/../config.php';
require_role(['super_admin','admin','aptitude_manager']);
$pageTitle = 'Aptitude Results';
$activeSection = 'aptitude';
$msg = null; $err = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_test'])) {
    csrf_check();
    $name = trim($_POST['test_name']);
    $date = $_POST['test_date'];
    $qp = save_upload('question_paper', 'qp', ['pdf']);
    $pdo->prepare('INSERT INTO aptitude_tests (test_name, test_date, question_paper_file) VALUES (?,?,?)')
        ->execute([$name, $date, $qp]);
    audit($pdo, 'create_aptitude_test', $name);
    $msg = 'Test created.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_csv'])) {
    csrf_check();
    $testId = (int)$_POST['aptitude_test_id'];
    if (empty($_FILES['results_csv']['tmp_name'])) {
        $err = 'Choose a CSV file.';
    } else {
        $fh = fopen($_FILES['results_csv']['tmp_name'], 'r');
        $row = 0; $ins = 0; $upd = 0;
        while (($cols = fgetcsv($fh)) !== false) {
            $row++;
            if ($row === 1 && stripos($cols[0], 'reg') !== false) continue; // skip header
            [$reg, $score, $status, $remarks] = array_map('trim', array_pad($cols, 4, ''));
            if (!$reg) continue;
            $exists = $pdo->prepare('SELECT id FROM aptitude_results WHERE aptitude_test_id=? AND reg_no=?');
            $exists->execute([$testId, $reg]);
            $id = $exists->fetchColumn();
            if ($id) {
                $pdo->prepare('UPDATE aptitude_results SET score=?, status=?, remarks=? WHERE id=?')
                    ->execute([$score, $status, $remarks, $id]); $upd++;
            } else {
                $pdo->prepare('INSERT INTO aptitude_results (aptitude_test_id, reg_no, score, status, remarks) VALUES (?,?,?,?,?)')
                    ->execute([$testId, $reg, $score, $status, $remarks]); $ins++;
            }
        }
        fclose($fh);
        audit($pdo, 'upload_aptitude_csv', "test #$testId inserted:$ins updated:$upd");
        $msg = "CSV processed — inserted $ins, updated $upd.";
    }
}

$tests = $pdo->query('SELECT t.*, (SELECT COUNT(*) FROM aptitude_results r WHERE r.aptitude_test_id=t.id) AS result_count
                       FROM aptitude_tests t ORDER BY test_date DESC')->fetchAll();
require __DIR__ . '/layout_header.php';
?>
<h1>Aptitude Results</h1>
<?php if ($msg): ?><div class="alert alert-ok"><?= h($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-err"><?= h($err) ?></div><?php endif; ?>

<div class="card form-card" style="max-width:520px">
  <h3>Create Test</h3>
  <form method="POST" enctype="multipart/form-data" class="stack">
    <?= csrf_field() ?>
    <div class="field"><label>Test Name</label><input name="test_name" required></div>
    <div class="field"><label>Test Date</label><input type="date" name="test_date" required></div>
    <div class="field"><label>Question Paper (PDF)</label><input type="file" name="question_paper" accept=".pdf"></div>
    <button class="btn btn-primary" type="submit" name="create_test">Create</button>
  </form>
</div>

<h2 style="margin-top:32px">Tests &amp; Results</h2>
<?php foreach ($tests as $t): ?>
  <div class="card" style="margin-bottom:16px">
    <h3><?= h($t['test_name']) ?> <span class="muted mono" style="font-size:13px">— <?= h(date('d M Y', strtotime($t['test_date']))) ?></span></h3>
    <p class="muted"><?= (int)$t['result_count'] ?> result(s) uploaded.
      <?php if ($t['question_paper_file']): ?> · <a href="<?= BASE_URL ?>/uploads/qp/<?= h($t['question_paper_file']) ?>" target="_blank" style="color:var(--copper-lt)">Question Paper</a><?php endif; ?></p>
    <form method="POST" enctype="multipart/form-data" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
      <?= csrf_field() ?><input type="hidden" name="aptitude_test_id" value="<?= (int)$t['id'] ?>">
      <input type="file" name="results_csv" accept=".csv" required>
      <button class="btn btn-outline btn-sm" type="submit" name="upload_csv">Upload CSV</button>
    </form>
    <p class="muted mono" style="font-size:12px;margin-top:6px">CSV columns: reg_no, score, status, remarks (header row optional)</p>
  </div>
<?php endforeach; ?>
<?php require __DIR__ . '/layout_footer.php'; ?>
