<?php
require_once '../config/config.php';
require_once '../lib/QRGenerator.php';
requireAdmin();

$id   = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM autos WHERE id=?");
$stmt->execute([$id]);
$auto = $stmt->fetch();
if (!$auto) redirect('manage.php');

$scansTotal = $pdo->prepare("SELECT COUNT(*) FROM scan_logs WHERE auto_number=?");
$scansTotal->execute([$auto['auto_number']]);
$scansTotal = $scansTotal->fetchColumn();

$scansToday = $pdo->prepare("SELECT COUNT(*) FROM scan_logs WHERE auto_number=? AND DATE(scanned_at)=CURDATE()");
$scansToday->execute([$auto['auto_number']]);
$scansToday = $scansToday->fetchColumn();

$sosCount = $pdo->prepare("SELECT COUNT(*) FROM sos_logs WHERE auto_number=?");
$sosCount->execute([$auto['auto_number']]);
$sosCount = $sosCount->fetchColumn();

$recentScans = $pdo->prepare("SELECT * FROM scan_logs WHERE auto_number=? ORDER BY scanned_at DESC LIMIT 10");
$recentScans->execute([$auto['auto_number']]);
$recentScans = $recentScans->fetchAll();

$recentSOS = $pdo->prepare("SELECT * FROM sos_logs WHERE auto_number=? ORDER BY created_at DESC LIMIT 5");
$recentSOS->execute([$auto['auto_number']]);
$recentSOS = $recentSOS->fetchAll();

$qrUrl  = QRGenerator::getURL($auto['auto_number']);
$autoURL = generateAutoURL($auto['auto_number']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= e($auto['auto_number']) ?> | <?= APP_NAME ?></title>
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
        <h1 class="page-title"><?= e($auto['auto_number']) ?></h1>
        <p class="page-sub">Auto detail view</p>
      </div>
      <div class="btn-group">
        <a href="edit.php?id=<?= $auto['id'] ?>"        class="btn btn-outline">✏️ Edit</a>
        <a href="download_pdf.php?id=<?= $auto['id'] ?>" class="btn btn-success" target="_blank">🖨 Print PDF</a>
        <a href="manage.php" class="btn btn-outline">← Back</a>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;">

      <!-- Left: Info + Logs -->
      <div style="display:flex;flex-direction:column;gap:20px;">

        <!-- Details -->
        <div class="card">
          <div class="card-header">
            <h2 class="card-title">🚖 Vehicle & Driver Details</h2>
            <span class="pill pill-<?= $auto['status'] ?>"><?= strtoupper($auto['status']) ?></span>
          </div>
          <div class="card-body">
            <div class="form-grid" style="row-gap:16px;">
              <?php foreach([
                'Auto Number'    => $auto['auto_number'],
                'Reg. Number'    => $auto['reg_number'],
                'Driver Name'    => $auto['driver_name'],
                'Phone'          => $auto['phone'],
                'License Number' => $auto['license_number'],
                'Permit Number'  => $auto['permit_number'],
                'Area'           => $auto['area'] ?? '—',
                'Stand'          => $auto['stand'] ?? '—',
                'Added On'       => date('d M Y, H:i', strtotime($auto['created_at'])),
              ] as $lbl => $val): ?>
              <div>
                <div style="font-size:0.72rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:5px;"><?= $lbl ?></div>
                <div style="font-size:0.95rem;font-weight:600;"><?= e($val) ?></div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <!-- Recent Scans -->
        <div class="card">
          <div class="card-header"><h2 class="card-title">📲 Recent Scans</h2></div>
          <div class="table-wrap">
            <table>
              <thead><tr><th>Time</th><th>IP</th><th>Device</th></tr></thead>
              <tbody>
                <?php if (empty($recentScans)): ?>
                  <tr><td colspan="3" class="empty-row">No scans yet.</td></tr>
                <?php else: foreach ($recentScans as $sc): ?>
                  <tr>
                    <td><?= date('d/m H:i:s', strtotime($sc['scanned_at'])) ?></td>
                    <td style="font-family:monospace;font-size:0.8rem;"><?= e($sc['ip_address'] ?? '—') ?></td>
                    <td style="font-size:0.75rem;color:var(--muted);max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e($sc['user_agent'] ?? '—') ?></td>
                  </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- SOS Logs -->
        <?php if (!empty($recentSOS)): ?>
        <div class="card" style="border-color:rgba(211,47,47,0.3);">
          <div class="card-header"><h2 class="card-title">🚨 SOS History</h2></div>
          <div class="table-wrap">
            <table>
              <thead><tr><th>Time</th><th>Location</th><th>Status</th></tr></thead>
              <tbody>
                <?php foreach ($recentSOS as $s): ?>
                <tr>
                  <td><?= date('d/m H:i', strtotime($s['created_at'])) ?></td>
                  <td>
                    <?php if ($s['latitude'] && $s['longitude']): ?>
                      <a href="https://maps.google.com/?q=<?= $s['latitude'] ?>,<?= $s['longitude'] ?>" target="_blank" class="map-link">📍 Map</a>
                    <?php else: ?>—<?php endif; ?>
                  </td>
                  <td><span class="pill pill-<?= $s['status'] ?>"><?= strtoupper($s['status']) ?></span></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
        <?php endif; ?>
      </div>

      <!-- Right: QR + Stats -->
      <div style="display:flex;flex-direction:column;gap:16px;">

        <!-- Stats -->
        <div class="card">
          <div class="card-body" style="display:flex;flex-direction:column;gap:14px;">
            <?php foreach([
              ['📲 Total Scans', $scansTotal, 'var(--text)'],
              ['📱 Today Scans', $scansToday, 'var(--accent)'],
              ['🚨 SOS Events',  $sosCount,   '#ef5350'],
            ] as [$lbl, $val, $color]): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid var(--border);">
              <span style="font-size:0.85rem;color:var(--muted);"><?= $lbl ?></span>
              <span style="font-size:1.4rem;font-weight:800;color:<?= $color ?>;"><?= $val ?></span>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- QR Code -->
        <div class="card">
          <div class="card-header"><h2 class="card-title">QR Code</h2></div>
          <div class="card-body" style="text-align:center;">
            <img src="<?= e($qrUrl) ?>" alt="QR" style="width:180px;height:180px;border-radius:8px;border:2px solid var(--border);">
            <div style="margin-top:10px;font-size:0.7rem;color:var(--muted);word-break:break-all;">
              <?= e($autoURL) ?>
            </div>
            <div style="display:flex;flex-direction:column;gap:8px;margin-top:14px;">
              <a href="download_qr.php?id=<?= $auto['id'] ?>"  class="btn btn-outline" style="width:100%;justify-content:center;">⬇ Download QR</a>
              <a href="download_pdf.php?id=<?= $auto['id'] ?>" class="btn btn-success"  style="width:100%;justify-content:center;" target="_blank">🖨 Print Sticker PDF</a>
              <a href="<?= e($autoURL) ?>"                      class="btn btn-outline" style="width:100%;justify-content:center;" target="_blank">🔗 Public View</a>
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
