<?php
require_once __DIR__ . '/../config.php';
$certNo = trim($_GET['cert'] ?? '');
$stmt = $pdo->prepare('SELECT * FROM certificates WHERE certificate_no = ? LIMIT 1');
$stmt->execute([$certNo]);
$cert = $stmt->fetch();
if (!$cert) { http_response_code(404); die('Certificate not found.'); }
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Certificate Authenticity — <?= h($cert['certificate_no']) ?></title>
<style>
  body{font-family: Georgia, 'Times New Roman', serif; background:#f4f4f4; margin:0; padding:40px; color:#1a1a1a;}
  .sheet{max-width:820px;margin:0 auto;background:#fff;padding:60px;border:1px solid #ccc;position:relative;}
  .border{position:absolute;inset:18px;border:2px solid #0b1220;}
  h1{font-size:28px;text-align:center;margin-bottom:6px;letter-spacing:0.04em;}
  .sub{text-align:center;color:#555;font-size:13px;letter-spacing:0.12em;text-transform:uppercase;margin-bottom:40px;}
  .row{display:flex;justify-content:space-between;margin:16px 0;font-size:15px;border-bottom:1px dotted #ccc;padding-bottom:10px;}
  .row b{color:#555;font-weight:normal;font-size:12px;text-transform:uppercase;letter-spacing:0.08em;}
  .status{text-align:center;margin-top:36px;padding:14px;font-weight:bold;letter-spacing:0.08em;}
  .status.active{color:#146c3a;background:#e8f7ee;}
  .status.revoked{color:#9c1c1c;background:#fbe9e9;}
  .footer{margin-top:40px;font-size:11px;color:#888;text-align:center;}
  .print-btn{display:block;margin:0 auto 24px;text-align:center;}
  @media print{ .print-btn{display:none;} body{background:#fff;padding:0;} .sheet{border:none;} }
</style>
</head>
<body>
  <div class="print-btn"><button onclick="window.print()" style="padding:10px 22px;font-size:14px;cursor:pointer">Print / Save as PDF</button></div>
  <div class="sheet">
    <div class="border"></div>
    <h1>Certificate Authenticity Record</h1>
    <div class="sub">Electrical Engineering Students Association</div>

    <div class="row"><b>Certificate No.</b><span><?= h($cert['certificate_no']) ?></span></div>
    <div class="row"><b>Title</b><span><?= h($cert['title']) ?></span></div>
    <div class="row"><b>Issued To</b><span><?= h($cert['full_name']) ?></span></div>
    <div class="row"><b>Member ID</b><span><?= h($cert['member_id']) ?></span></div>
    <div class="row"><b>Issued By</b><span><?= h($cert['issued_by']) ?></span></div>
    <div class="row"><b>Date Issued</b><span><?= h(date('d F Y', strtotime($cert['issue_date']))) ?></span></div>

    <div class="status <?= $cert['status'] ?>">
      <?= $cert['status'] === 'active' ? '✓ VERIFIED — ACTIVE' : '✕ REVOKED' ?>
    </div>

    <div class="footer">
      This is a system-generated authenticity record. Verify online at<br>
      <?= BASE_URL ?>/pages/verify_certificate.php?cert=<?= h($cert['certificate_no']) ?>
    </div>
  </div>
</body>
</html>
