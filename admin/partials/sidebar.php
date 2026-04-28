<?php
// Determine current page for active nav state
$current = basename($_SERVER['PHP_SELF'], '.php');
?>
<nav class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <span class="brand-icon">🚔</span>
    <div>
      <div class="brand-name">Smart Auto</div>
      <div class="brand-sub">QR Safety System</div>
    </div>
  </div>

  <div class="nav-section">
    <div class="nav-label">OVERVIEW</div>
    <a href="dashboard.php" class="nav-item <?= $current==='dashboard' ? 'active':'' ?>">
      <span class="nav-icon">📊</span> Dashboard
    </a>
  </div>

  <div class="nav-section">
    <div class="nav-label">MANAGEMENT</div>
    <a href="register.php" class="nav-item <?= $current==='register' ? 'active':'' ?>">
      <span class="nav-icon">➕</span> Register Auto
    </a>
    <a href="manage.php" class="nav-item <?= $current==='manage' ? 'active':'' ?>">
      <span class="nav-icon">🚖</span> All Autos
    </a>
    <a href="bulk_upload.php" class="nav-item <?= $current==='bulk_upload' ? 'active':'' ?>">
      <span class="nav-icon">📤</span> Bulk Upload
    </a>
  </div>

  <div class="nav-section">
    <div class="nav-label">MONITORING</div>
    <a href="sos_logs.php" class="nav-item <?= $current==='sos_logs' ? 'active':'' ?>">
      <span class="nav-icon">🚨</span> SOS Alerts
      <?php
      global $pdo;
      $pCount = $pdo->query("SELECT COUNT(*) FROM sos_logs WHERE status='pending'")->fetchColumn();
      if ($pCount > 0): ?>
        <span class="nav-badge"><?= $pCount ?></span>
      <?php endif; ?>
    </a>
    <a href="scan_logs.php" class="nav-item <?= $current==='scan_logs' ? 'active':'' ?>">
      <span class="nav-icon">📲</span> Scan Logs
    </a>
  </div>

  <div class="sidebar-footer">
    <div class="admin-info">
      <div class="admin-avatar"><?= strtoupper(substr($_SESSION['admin_user'] ?? 'A', 0, 1)) ?></div>
      <div>
        <div style="font-weight:600;font-size:0.85rem;"><?= e($_SESSION['admin_name'] ?? $_SESSION['admin_user'] ?? '') ?></div>
        <div style="font-size:0.75rem;color:#7d8590;"><?= ucfirst($_SESSION['admin_role'] ?? 'admin') ?></div>
      </div>
    </div>
    <a href="logout.php" class="nav-item nav-logout">
      <span class="nav-icon">🚪</span> Logout
    </a>
  </div>
</nav>
