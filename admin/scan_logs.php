<?php
require_once '../config/config.php';
requireAdmin();

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 30;
$offset  = ($page - 1) * $perPage;

$total = $pdo->query("SELECT COUNT(*) FROM scan_logs")->fetchColumn();
$totalPages = max(1, ceil($total / $perPage));

$stmt = $pdo->prepare("
    SELECT sc.*, a.driver_name, a.status as auto_status
    FROM scan_logs sc
    LEFT JOIN autos a ON sc.auto_number = a.auto_number
    ORDER BY sc.scanned_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute();
$logs = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Scan Logs | <?= APP_NAME ?></title>
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
        <h1 class="page-title">📲 QR Scan Logs</h1>
        <p class="page-sub"><?= number_format($total) ?> total scans recorded</p>
      </div>
    </div>

    <div class="card">
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th>Scanned At</th>
              <th>Auto No.</th>
              <th>Driver</th>
              <th>IP Address</th>
              <th>User Agent</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($logs)): ?>
            <tr><td colspan="6" class="empty-row">No scan logs yet.</td></tr>
            <?php else: foreach ($logs as $i => $log): ?>
            <tr>
              <td style="color:var(--muted);"><?= $offset + $i + 1 ?></td>
              <td><?= date('d/m/Y H:i:s', strtotime($log['scanned_at'])) ?></td>
              <td><span class="badge badge-auto"><?= e($log['auto_number']) ?></span></td>
              <td><?= e($log['driver_name'] ?? '—') ?></td>
              <td style="font-family:monospace;font-size:0.82rem;"><?= e($log['ip_address'] ?? '—') ?></td>
              <td style="font-size:0.75rem;color:var(--muted);max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                <?= e($log['user_agent'] ?? '—') ?>
              </td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
      <?php if ($totalPages > 1): ?>
      <div class="pagination">
        <?php for ($p = max(1,$page-3); $p <= min($totalPages,$page+3); $p++): ?>
          <a href="?page=<?= $p ?>" class="page-link <?= $p===$page?'active':'' ?>"><?= $p ?></a>
        <?php endfor; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<script src="assets/js/admin.js"></script>
</body>
</html>
