<?php
require_once '../config/config.php';
require_once '../lib/QRGenerator.php';
requireAdmin();

$id   = (int)($_GET['id'] ?? 0);
$auto = $id ? $pdo->prepare("SELECT * FROM autos WHERE id=?")->execute([$id]) ? null : null : null;
$stmt = $pdo->prepare("SELECT * FROM autos WHERE id=?");
$stmt->execute([$id]);
$auto = $stmt->fetch();

if (!$auto) {
    redirect('manage.php');
}

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reg_number     = strtoupper(trim($_POST['reg_number']    ?? ''));
    $driver_name    = trim($_POST['driver_name']  ?? '');
    $phone          = preg_replace('/\D/', '', trim($_POST['phone'] ?? ''));
    $license_number = strtoupper(trim($_POST['license_number'] ?? ''));
    $permit_number  = strtoupper(trim($_POST['permit_number']  ?? ''));
    $area           = trim($_POST['area']  ?? '');
    $stand          = trim($_POST['stand'] ?? '');
    $status         = in_array($_POST['status'], ['active','inactive','suspended']) ? $_POST['status'] : 'active';

    $errors = [];
    if (!$reg_number)     $errors[] = 'Registration Number required.';
    if (!$driver_name)    $errors[] = 'Driver Name required.';
    if (!$phone || strlen($phone) < 10) $errors[] = 'Valid 10-digit phone required.';
    if (!$license_number) $errors[] = 'License Number required.';
    if (!$permit_number)  $errors[] = 'Permit Number required.';

    if ($errors) {
        $error = implode('<br>', $errors);
    } else {
        $stmt = $pdo->prepare("UPDATE autos SET
            reg_number=?, driver_name=?, phone=?, license_number=?,
            permit_number=?, area=?, stand=?, status=?
            WHERE id=?");
        $stmt->execute([$reg_number, $driver_name, $phone, $license_number, $permit_number, $area, $stand, $status, $id]);

        // Re-generate QR if driver name changed
        QRGenerator::delete($auto['auto_number']);
        $autoUrl = generateAutoURL($auto['auto_number']);
        $qrPath  = QRGenerator::generate($autoUrl, $auto['auto_number']);
        if ($qrPath) {
            $pdo->prepare("UPDATE autos SET qr_path=? WHERE id=?")->execute([$qrPath, $id]);
        }

        redirect("manage.php?flash=updated");
    }
    // Refresh $auto for repopulation
    $auto = array_merge($auto, compact('reg_number','driver_name','phone','license_number','permit_number','area','stand','status'));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Edit Auto | <?= APP_NAME ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
<?php include 'partials/sidebar.php'; ?>
<div class="main-wrapper">
  <?php include 'partials/topbar.php'; ?>
  <div class="page-content">
    <div class="page-header">
      <div>
        <h1 class="page-title">Edit Auto — <?= e($auto['auto_number']) ?></h1>
        <p class="page-sub">Modify auto and driver details. QR code will be regenerated.</p>
      </div>
      <a href="manage.php" class="btn btn-outline">← Back</a>
    </div>

    <?php if ($error):   ?><div class="alert alert-danger">⚠️ <?= $error ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success">✅ <?= $success ?></div><?php endif; ?>

    <div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;flex-wrap:wrap;">
      <div class="card">
        <div class="card-header"><h2 class="card-title">Driver & Vehicle Details</h2></div>
        <div class="card-body">
          <form method="POST">
            <div class="form-grid">
              <div class="form-group">
                <label>Auto Number (read-only)</label>
                <input type="text" value="<?= e($auto['auto_number']) ?>" disabled style="opacity:0.5;">
              </div>
              <div class="form-group">
                <label>Reg. Number *</label>
                <input type="text" name="reg_number" value="<?= e($auto['reg_number']) ?>" required oninput="this.value=this.value.toUpperCase()">
              </div>
              <div class="form-group">
                <label>Driver Full Name *</label>
                <input type="text" name="driver_name" value="<?= e($auto['driver_name']) ?>" required>
              </div>
              <div class="form-group">
                <label>Phone Number *</label>
                <input type="tel" name="phone" value="<?= e($auto['phone']) ?>" required maxlength="10">
              </div>
              <div class="form-group">
                <label>License Number *</label>
                <input type="text" name="license_number" value="<?= e($auto['license_number']) ?>" required oninput="this.value=this.value.toUpperCase()">
              </div>
              <div class="form-group">
                <label>Permit Number *</label>
                <input type="text" name="permit_number" value="<?= e($auto['permit_number']) ?>" required oninput="this.value=this.value.toUpperCase()">
              </div>
              <div class="form-group">
                <label>Area / Zone</label>
                <input type="text" name="area" value="<?= e($auto['area'] ?? '') ?>">
              </div>
              <div class="form-group">
                <label>Stand</label>
                <input type="text" name="stand" value="<?= e($auto['stand'] ?? '') ?>">
              </div>
              <div class="form-group">
                <label>Status</label>
                <select name="status">
                  <option value="active"    <?= $auto['status']==='active'    ?'selected':'' ?>>Active</option>
                  <option value="inactive"  <?= $auto['status']==='inactive'  ?'selected':'' ?>>Inactive</option>
                  <option value="suspended" <?= $auto['status']==='suspended' ?'selected':'' ?>>Suspended</option>
                </select>
              </div>
            </div>
            <div style="margin-top:24px;display:flex;gap:12px;">
              <button type="submit" class="btn btn-primary">💾 Save Changes</button>
              <a href="manage.php" class="btn btn-outline">Cancel</a>
            </div>
          </form>
        </div>
      </div>

      <!-- QR Preview -->
      <div style="display:flex;flex-direction:column;gap:16px;">
        <div class="card">
          <div class="card-header"><h2 class="card-title">QR Code</h2></div>
          <div class="card-body" style="text-align:center;">
            <?php $qrUrl = QRGenerator::getQRImageURL($auto['auto_number']); ?>
            <img src="<?= e($qrUrl) ?>" alt="QR" style="width:160px;height:160px;border-radius:8px;border:2px solid var(--border);">
            <div style="margin-top:12px;font-size:0.78rem;color:var(--muted);word-break:break-all;">
              <?= e(generateAutoURL($auto['auto_number'])) ?>
            </div>
            <div style="display:flex;gap:8px;justify-content:center;margin-top:14px;flex-wrap:wrap;">
              <a href="download_qr.php?id=<?= $auto['id'] ?>"  class="btn btn-sm btn-outline">⬇ Download QR</a>
              <a href="download_pdf.php?id=<?= $auto['id'] ?>" class="btn btn-sm btn-success" target="_blank">🖨 Print PDF</a>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="card-header"><h2 class="card-title">Quick Info</h2></div>
          <div class="card-body" style="font-size:0.83rem;color:var(--muted);line-height:1.8;">
            <div>Added: <strong style="color:var(--text);"><?= date('d M Y', strtotime($auto['created_at'])) ?></strong></div>
            <div>Scans:
              <?php
              $scans = $pdo->prepare("SELECT COUNT(*) FROM scan_logs WHERE auto_number=?");
              $scans->execute([$auto['auto_number']]);
              echo '<strong style="color:var(--text);">' . $scans->fetchColumn() . '</strong>';
              ?>
            </div>
            <div>SOS Events:
              <?php
              $sos = $pdo->prepare("SELECT COUNT(*) FROM sos_logs WHERE auto_number=?");
              $sos->execute([$auto['auto_number']]);
              echo '<strong style="color:#ef5350;">' . $sos->fetchColumn() . '</strong>';
              ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="assets/js/admin.js"></script>
</body>
</html>
