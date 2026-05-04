<?php
require_once '../config/config.php';
require_once '../lib/QRGenerator.php';
requireAdmin();

// ── Handle QR Lock Request ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lock_qr_id'])) {
    $lockId = (int)$_POST['lock_qr_id'];
    
    // Get the auto record
    $stmt = $pdo->prepare("SELECT auto_number, qr_path, qr_locked FROM autos WHERE id = ?");
    $stmt->execute([$lockId]);
    $auto = $stmt->fetch();
    
    if ($auto && $auto['qr_path'] && !$auto['qr_locked']) {
        // Lock the QR code
        if (QRGenerator::lockQRCode($auto['auto_number'], $auto['qr_path'])) {
            header('Location: manage.php?flash=qr_locked');
            exit;
        }
    }
}

// ── Filters & Pagination ─────────────────────────────────────
$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';
$area   = trim($_GET['area'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

$where  = ['1=1'];
$params = [];

if ($search) {
    $where[]  = "(auto_number LIKE ? OR driver_name LIKE ? OR reg_number LIKE ? OR phone LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
}
if ($status) {
    $where[]  = "status = ?";
    $params[] = $status;
}
if ($area) {
    $where[]  = "area LIKE ?";
    $params[] = "%$area%";
}

$whereStr = implode(' AND ', $where);

// Total count
$total = $pdo->prepare("SELECT COUNT(*) FROM autos WHERE $whereStr");
$total->execute($params);
$total = $total->fetchColumn();
$totalPages = max(1, ceil($total / $perPage));

// Fetch page
$stmt = $pdo->prepare("SELECT * FROM autos WHERE $whereStr ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$autos = $stmt->fetchAll();

// Flash message from redirect
$flash = $_GET['flash'] ?? '';


// Areas for filter dropdown
$areas = $pdo->query("SELECT DISTINCT area FROM autos WHERE area IS NOT NULL AND area != '' ORDER BY area")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>All Autos | <?= APP_NAME ?></title>
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
        <h1 class="page-title">All Autos</h1>
        <p class="page-sub">Total: <strong><?= number_format($total) ?></strong> records</p>
      </div>
      <div class="btn-group">
        <a href="register.php"    class="btn btn-primary">+ Register Auto</a>
        <a href="bulk_upload.php" class="btn btn-outline">📤 Bulk Upload</a>
      </div>
    </div>

    <?php if ($flash === 'deleted'): ?>
    <div class="alert alert-success">✅ Auto deleted successfully.</div>
    <?php elseif ($flash === 'updated'): ?>
    <div class="alert alert-success">✅ Auto updated successfully.</div>
    <?php elseif ($flash === 'qr_locked'): ?>
    <div class="alert alert-success">🔒 QR code locked successfully! It will never change now.</div>
    <?php endif; ?>

    <!-- Search & Filter -->
    <form method="GET" class="search-bar">
      <input type="text" name="search" placeholder="Search auto no., driver, phone, reg..." value="<?= e($search) ?>">
      <select name="status">
        <option value="">All Status</option>
        <option value="active"    <?= $status==='active'    ?'selected':'' ?>>Active</option>
        <option value="inactive"  <?= $status==='inactive'  ?'selected':'' ?>>Inactive</option>
        <option value="suspended" <?= $status==='suspended' ?'selected':'' ?>>Suspended</option>
      </select>
      <select name="area">
        <option value="">All Areas</option>
        <?php foreach ($areas as $a): ?>
          <option value="<?= e($a) ?>" <?= $area===$a?'selected':'' ?>><?= e($a) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn btn-primary">🔍 Search</button>
      <a href="manage.php" class="btn btn-outline">✕ Clear</a>
    </form>

    <!-- Table -->
    <div class="card">
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th>Auto No.</th>
              <th>Driver</th>
              <th>Phone</th>
              <th>Reg. No.</th>
              <th>Area</th>
              <th>Safety</th>
              <th>Status</th>
              <th>QR</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($autos)): ?>
              <tr><td colspan="9" class="empty-row">No autos found. <a href="register.php">Register one now →</a></td></tr>
            <?php else: foreach ($autos as $i => $a): ?>
            <tr>
              <td style="color:var(--muted);"><?= $offset + $i + 1 ?></td>
              <td><span class="badge badge-auto"><?= e($a['auto_number']) ?></span></td>
              <td>
                <div style="font-weight:600;"><?= e($a['driver_name']) ?></div>
                <div style="font-size:0.75rem;color:var(--muted);"><?= e($a['license_number']) ?></div>
              </td>
              <td>
                <a href="tel:<?= e($a['phone']) ?>" style="color:var(--text);"><?= e($a['phone']) ?></a>
              </td>
              <td><?= e($a['reg_number']) ?></td>
              <td><?= e($a['area'] ?? '—') ?></td>
              <td>
                <?php 
                  $badges = ['safe' => '✅ Safe', 'caution' => '⚠️ Caution', 'danger' => '🚫 Danger'];
                  $secBadge = $badges[$a['security_detail']] ?? '✅ Safe';
                ?>
                <span style="font-size:0.75rem;"><?= $secBadge ?></span>
              </td>
              <td><span class="pill pill-<?= $a['status'] ?>"><?= strtoupper($a['status']) ?></span></td>
              <td>
                <?php
                $qrUrl = QRGenerator::getURL($a['auto_number']);
                $qrLocked = $a['qr_locked'] ?? 0;
                ?>
                <div class="qr-preview" style="position:relative;">
                  <a href="<?= e($qrUrl) ?>" target="_blank">
                    <img src="<?= e($qrUrl) ?>" alt="QR" loading="lazy">
                  </a>
                  <?php if ($qrLocked): ?>
                    <div style="position:absolute;top:4px;right:4px;background:#27ae60;color:white;padding:2px 6px;border-radius:3px;font-size:0.7rem;font-weight:bold;">🔒</div>
                  <?php endif; ?>
                </div>
              </td>
              <td>
                <div class="btn-group" style="flex-wrap:wrap;font-size:0.75rem;">
                  <a href="view_auto.php?id=<?= $a['id'] ?>"   class="btn btn-xs btn-outline">👁</a>
                  <a href="edit.php?id=<?= $a['id'] ?>"        class="btn btn-xs btn-outline">✏️</a>
                  <a href="download_pdf.php?id=<?= $a['id'] ?>" class="btn btn-xs btn-success" target="_blank">🖨</a>
                  <a href="download_qr.php?id=<?= $a['id'] ?>" class="btn btn-xs btn-outline">⬇</a>
                  <?php if (!$qrLocked && $a['qr_path']): ?>
                    <a href="#" class="btn btn-xs btn-primary" title="Lock QR code" onclick="lockQR(<?= $a['id'] ?>, '<?= e($a['auto_number']) ?>'); return false;">🔒</a>
                  <?php endif; ?>
                  <button class="btn btn-xs btn-danger"
                          onclick="confirmDelete(<?= $a['id'] ?>, '<?= e($a['auto_number']) ?>')">🗑</button>
                </div>
              </td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <?php if ($totalPages > 1): ?>
      <div class="pagination">
        <?php if ($page > 1): ?>
          <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page-1])) ?>" class="page-link">‹ Prev</a>
        <?php endif; ?>
        <?php for ($p = max(1, $page-2); $p <= min($totalPages, $page+2); $p++): ?>
          <a href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>"
             class="page-link <?= $p===$page?'active':'' ?>"><?= $p ?></a>
        <?php endfor; ?>
        <?php if ($page < $totalPages): ?>
          <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page+1])) ?>" class="page-link">Next ›</a>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>

  </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" style="
  display:none;position:fixed;inset:0;background:rgba(0,0,0,0.7);
  z-index:200;align-items:center;justify-content:center;">
  <div style="background:var(--card);border:1px solid var(--border);border-radius:16px;padding:32px;max-width:420px;width:90%;text-align:center;">
    <div style="font-size:3rem;margin-bottom:16px;">🗑️</div>
    <h2 style="margin-bottom:8px;">Delete Auto</h2>
    <p style="color:var(--muted);margin-bottom:24px;">
      Are you sure you want to delete <strong id="deleteAutoName"></strong>?<br>
      This action cannot be undone. SOS and scan logs will be preserved.
    </p>
    <div style="display:flex;gap:12px;justify-content:center;">
      <form id="deleteForm" method="POST" action="delete.php">
        <input type="hidden" name="id" id="deleteId">
        <button type="submit" class="btn btn-danger">Yes, Delete</button>
      </form>
      <button onclick="document.getElementById('deleteModal').style.display='none'" class="btn btn-outline">Cancel</button>
    </div>
  </div>
</div>

<script>
function confirmDelete(id, name) {
  document.getElementById('deleteId').value = id;
  document.getElementById('deleteAutoName').textContent = name;
  document.getElementById('deleteModal').style.display = 'flex';
}

function lockQR(id, autoName) {
  if (confirm(`🔒 Lock QR code for ${autoName}?\n\nOnce locked, the QR code will never change, ensuring sticker validity.`)) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = window.location.href;
    form.innerHTML = `<input type="hidden" name="lock_qr_id" value="${id}">`;
    document.body.appendChild(form);
    form.submit();
  }
}
</script>
<script src="assets/js/admin.js"></script>
</body>
</html>
