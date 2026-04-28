<?php
require_once '../config/config.php';
requireAdmin();

// ── Stats ────────────────────────────────────────────────────
$totalAutos   = $pdo->query("SELECT COUNT(*) FROM autos")->fetchColumn();
$activeAutos  = $pdo->query("SELECT COUNT(*) FROM autos WHERE status='active'")->fetchColumn();
$totalScans   = $pdo->query("SELECT COUNT(*) FROM scan_logs")->fetchColumn();
$todayScans   = $pdo->query("SELECT COUNT(*) FROM scan_logs WHERE DATE(scanned_at)=CURDATE()")->fetchColumn();
$totalSOS     = $pdo->query("SELECT COUNT(*) FROM sos_logs")->fetchColumn();
$pendingSOS   = $pdo->query("SELECT COUNT(*) FROM sos_logs WHERE status='pending'")->fetchColumn();

// ── Recent SOS Logs ──────────────────────────────────────────
$recentSOS = $pdo->query("
    SELECT sl.*, a.driver_name, a.reg_number
    FROM sos_logs sl
    LEFT JOIN autos a ON sl.auto_number = a.auto_number
    ORDER BY sl.created_at DESC LIMIT 8
")->fetchAll();

// ── Recent Scans ─────────────────────────────────────────────
$recentScans = $pdo->query("
    SELECT sc.*, a.driver_name
    FROM scan_logs sc
    LEFT JOIN autos a ON sc.auto_number = a.auto_number
    ORDER BY sc.scanned_at DESC LIMIT 8
")->fetchAll();

// ── Handle SOS Status Update ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resolve_sos'])) {
    $sosId  = (int)$_POST['sos_id'];
    $status = in_array($_POST['new_status'], ['dispatched','resolved','false_alarm']) ? $_POST['new_status'] : 'resolved';
    $pdo->prepare("UPDATE sos_logs SET status=? WHERE id=?")->execute([$status, $sosId]);
    redirect('dashboard.php');
}

// ── Scans by day (last 7 days) for chart ─────────────────────
$scanChart = $pdo->query("
    SELECT DATE(scanned_at) as day, COUNT(*) as cnt
    FROM scan_logs
    WHERE scanned_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(scanned_at)
    ORDER BY day ASC
")->fetchAll();

$chartLabels = json_encode(array_column($scanChart, 'day'));
$chartData   = json_encode(array_column($scanChart, 'cnt'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Dashboard | <?= APP_NAME ?></title>
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
        <h1 class="page-title">Dashboard</h1>
        <p class="page-sub">City-wide auto-rickshaw safety overview</p>
      </div>
      <a href="register.php" class="btn btn-primary">+ Register New Auto</a>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(26,35,126,0.15);color:#3f51b5;">🚖</div>
        <div>
          <div class="stat-val"><?= number_format($totalAutos) ?></div>
          <div class="stat-label">Total Autos</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(27,94,32,0.15);color:#2e7d32;">✅</div>
        <div>
          <div class="stat-val"><?= number_format($activeAutos) ?></div>
          <div class="stat-label">Active Autos</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(0,96,100,0.15);color:#006064;">📲</div>
        <div>
          <div class="stat-val"><?= number_format($totalScans) ?></div>
          <div class="stat-label">Total QR Scans</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(245,127,23,0.15);color:#e65100;">📱</div>
        <div>
          <div class="stat-val"><?= number_format($todayScans) ?></div>
          <div class="stat-label">Scans Today</div>
        </div>
      </div>
      <div class="stat-card" style="border-color:#d32f2f33;">
        <div class="stat-icon" style="background:rgba(211,47,47,0.15);color:#d32f2f;">🚨</div>
        <div>
          <div class="stat-val" style="color:#d32f2f;"><?= number_format($pendingSOS) ?></div>
          <div class="stat-label">Pending SOS</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:rgba(74,20,140,0.15);color:#7b1fa2;">⚡</div>
        <div>
          <div class="stat-val"><?= number_format($totalSOS) ?></div>
          <div class="stat-label">Total SOS Logs</div>
        </div>
      </div>
    </div>

    <div class="grid-2">
      <!-- SOS Alerts -->
      <div class="card">
        <div class="card-header">
          <h2 class="card-title">🚨 Recent SOS Alerts</h2>
          <a href="sos_logs.php" class="btn btn-sm btn-outline">View All</a>
        </div>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Time</th>
                <th>Auto No.</th>
                <th>Driver</th>
                <th>Location</th>
                <th>Status</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($recentSOS)): ?>
              <tr><td colspan="6" class="empty-row">No SOS alerts. City is safe ✅</td></tr>
              <?php else: foreach ($recentSOS as $sos): ?>
              <tr>
                <td><?= date('d/m H:i', strtotime($sos['created_at'])) ?></td>
                <td><span class="badge badge-auto"><?= e($sos['auto_number']) ?></span></td>
                <td><?= e($sos['driver_name'] ?? '—') ?></td>
                <td>
                  <?php if ($sos['latitude'] && $sos['longitude']): ?>
                    <a href="https://maps.google.com/?q=<?= $sos['latitude'] ?>,<?= $sos['longitude'] ?>" target="_blank" class="map-link">📍 Map</a>
                  <?php else: ?>—<?php endif; ?>
                </td>
                <td><span class="pill pill-<?= $sos['status'] ?>"><?= strtoupper($sos['status']) ?></span></td>
                <td>
                  <?php if ($sos['status'] === 'pending'): ?>
                  <form method="POST" style="display:inline;">
                    <input type="hidden" name="sos_id" value="<?= $sos['id'] ?>">
                    <input type="hidden" name="new_status" value="dispatched">
                    <button name="resolve_sos" class="btn btn-sm btn-danger">Dispatch</button>
                  </form>
                  <?php else: ?>
                  <span style="color:#555;font-size:0.8rem;"><?= ucfirst($sos['status']) ?></span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Recent Scans -->
      <div class="card">
        <div class="card-header">
          <h2 class="card-title">📲 Recent Scans</h2>
          <a href="scan_logs.php" class="btn btn-sm btn-outline">View All</a>
        </div>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Time</th>
                <th>Auto No.</th>
                <th>Driver</th>
                <th>IP</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($recentScans)): ?>
              <tr><td colspan="4" class="empty-row">No scans recorded yet.</td></tr>
              <?php else: foreach ($recentScans as $sc): ?>
              <tr>
                <td><?= date('d/m H:i', strtotime($sc['scanned_at'])) ?></td>
                <td><span class="badge badge-auto"><?= e($sc['auto_number']) ?></span></td>
                <td><?= e($sc['driver_name'] ?? '—') ?></td>
                <td><?= e($sc['ip_address'] ?? '—') ?></td>
              </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Scan Activity Chart -->
    <div class="card" style="margin-top:24px;">
      <div class="card-header">
        <h2 class="card-title">📊 QR Scan Activity (Last 7 Days)</h2>
      </div>
      <div style="padding:20px;height:220px;position:relative;">
        <canvas id="scanChart"></canvas>
      </div>
    </div>

  </div><!-- /page-content -->
</div><!-- /main-wrapper -->

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const ctx = document.getElementById('scanChart');
new Chart(ctx, {
  type: 'line',
  data: {
    labels: <?= $chartLabels ?: '[]' ?>,
    datasets: [{
      label: 'Scans',
      data: <?= $chartData ?: '[]' ?>,
      borderColor: '#3f51b5',
      backgroundColor: 'rgba(63,81,181,0.1)',
      tension: 0.4,
      fill: true,
      pointBackgroundColor: '#3f51b5',
      pointRadius: 5,
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
      y: { beginAtZero: true, ticks: { color:'#7d8590', stepSize:1 }, grid: { color:'rgba(255,255,255,0.05)' } },
      x: { ticks: { color:'#7d8590' }, grid: { color:'rgba(255,255,255,0.05)' } }
    }
  }
});
</script>
<script src="assets/js/admin.js"></script>
</body>
</html>
