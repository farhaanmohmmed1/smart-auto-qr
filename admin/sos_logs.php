<?php
require_once '../config/config.php';
requireAdmin();

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset  = ($page - 1) * $perPage;
$status  = $_GET['status'] ?? '';

$where  = ['1=1'];
$params = [];
if ($status) { $where[] = "sl.status=?"; $params[] = $status; }
$whereStr = implode(' AND ', $where);

$total = $pdo->prepare("SELECT COUNT(*) FROM sos_logs sl WHERE $whereStr");
$total->execute($params);
$total = $total->fetchColumn();
$totalPages = max(1, ceil($total / $perPage));

$stmt = $pdo->prepare("
    SELECT sl.*, a.driver_name, a.phone, a.reg_number
    FROM sos_logs sl
    LEFT JOIN autos a ON sl.auto_number = a.auto_number
    WHERE $whereStr
    ORDER BY sl.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $sid  = (int)$_POST['sos_id'];
    $nst  = in_array($_POST['new_status'],['pending','dispatched','resolved','false_alarm']) ? $_POST['new_status'] : 'resolved';
    $pdo->prepare("UPDATE sos_logs SET status=? WHERE id=?")->execute([$nst, $sid]);
    redirect('sos_logs.php?flash=updated');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>SOS Alerts | <?= APP_NAME ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
<?php include 'partials/sidebar.php'; ?>
<div class="main-wrapper">
  <?php include 'partials/topbar.php'; ?>
  <div class="page-content">
    <div class="page-header">
      <h1 class="page-title">🚨 SOS Alert Logs</h1>
      <form method="GET" style="display:flex;gap:8px;">
        <select name="status" onchange="this.form.submit()">
          <option value="">All Status</option>
          <option value="pending"    <?= $status==='pending'    ?'selected':'' ?>>Pending</option>
          <option value="dispatched" <?= $status==='dispatched' ?'selected':'' ?>>Dispatched</option>
          <option value="resolved"   <?= $status==='resolved'   ?'selected':'' ?>>Resolved</option>
          <option value="false_alarm"<?= $status==='false_alarm'?'selected':'' ?>>False Alarm</option>
        </select>
      </form>
    </div>

    <?php if (isset($_GET['flash'])): ?>
    <div class="alert alert-success">✅ Status updated successfully.</div>
    <?php endif; ?>

    <div class="card">
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th>Timestamp</th>
              <th>Auto No.</th>
              <th>Driver</th>
              <th>Phone</th>
              <th>Location</th>
              <th>Status</th>
              <th>Update Status</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($logs)): ?>
            <tr><td colspan="8" class="empty-row">No SOS logs found.</td></tr>
            <?php else: foreach ($logs as $i => $log): ?>
            <tr>
              <td style="color:var(--muted);"><?= $offset + $i + 1 ?></td>
              <td><?= date('d/m/Y H:i:s', strtotime($log['created_at'])) ?></td>
              <td><span class="badge badge-auto"><?= e($log['auto_number']) ?></span></td>
              <td><?= e($log['driver_name'] ?? '—') ?></td>
              <td>
                <?php if ($log['phone']): ?>
                  <a href="tel:<?= e($log['phone']) ?>"><?= e($log['phone']) ?></a>
                <?php else: ?>—<?php endif; ?>
              </td>
              <td>
                <?php if ($log['latitude'] && $log['longitude']): ?>
                  <a href="https://maps.google.com/?q=<?= $log['latitude'] ?>,<?= $log['longitude'] ?>" target="_blank" class="map-link">
                    📍 <?= number_format($log['latitude'], 4) ?>, <?= number_format($log['longitude'], 4) ?>
                  </a>
                <?php else: ?><span style="color:var(--muted);">No GPS</span><?php endif; ?>
              </td>
              <td><span class="pill pill-<?= $log['status'] ?>"><?= strtoupper($log['status']) ?></span></td>
              <td>
                <form method="POST" style="display:flex;gap:4px;align-items:center;">
                  <input type="hidden" name="sos_id" value="<?= $log['id'] ?>">
                  <select name="new_status" style="padding:4px 8px;font-size:0.78rem;">
                    <option value="pending"    <?= $log['status']==='pending'    ?'selected':'' ?>>Pending</option>
                    <option value="dispatched" <?= $log['status']==='dispatched' ?'selected':'' ?>>Dispatched</option>
                    <option value="resolved"   <?= $log['status']==='resolved'   ?'selected':'' ?>>Resolved</option>
                    <option value="false_alarm"<?= $log['status']==='false_alarm'?'selected':'' ?>>False Alarm</option>
                  </select>
                  <button name="update_status" class="btn btn-xs btn-primary">✔</button>
                </form>
              </td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>

      <?php if ($totalPages > 1): ?>
      <div class="pagination">
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
          <a href="?page=<?= $p ?>&status=<?= $status ?>" class="page-link <?= $p===$page?'active':'' ?>"><?= $p ?></a>
        <?php endfor; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<script src="assets/js/admin.js"></script>
</body>
</html>
