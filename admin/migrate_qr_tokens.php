<?php
/**
 * MIGRATION: Generate qr_token for existing autos
 * 
 * Run this once to populate qr_token for all autos without one
 * URL: /admin/migrate_qr_tokens.php
 */

require_once '../config/config.php';
requireAdmin();

$migrated = 0;
$failed = 0;
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get all autos without qr_token
        $stmt = $pdo->prepare("SELECT id, auto_number FROM autos WHERE qr_token IS NULL OR qr_token = ''");
        $stmt->execute();
        $autos = $stmt->fetchAll();
        
        foreach ($autos as $auto) {
            try {
                // Generate secure token
                $qrToken = bin2hex(random_bytes(32));
                
                // Update auto with token
                $upd = $pdo->prepare("UPDATE autos SET qr_token = ? WHERE id = ?");
                $upd->execute([$qrToken, $auto['id']]);
                
                if ($upd->rowCount() > 0) {
                    $migrated++;
                } else {
                    $failed++;
                }
            } catch (Exception $e) {
                $failed++;
                error_log("Migration failed for auto {$auto['auto_number']}: " . $e->getMessage());
            }
        }
        
        $message = "✅ Migration complete! Generated tokens for <strong>{$migrated}</strong> autos.";
        if ($failed > 0) {
            $message .= " <span style='color:orange'>⚠️ {$failed} failed.</span>";
        }
        
    } catch (Exception $e) {
        $message = "❌ Migration failed: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>QR Token Migration | <?= APP_NAME ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
<?php include 'partials/sidebar.php'; ?>
<div class="main-wrapper">
  <?php include 'partials/topbar.php'; ?>
  <div class="page-content">
    <div class="page-header">
      <h1 class="page-title">🔐 QR Token Migration</h1>
      <p class="page-sub">Generate secure tokens for existing autos (one-time operation)</p>
    </div>

    <?php if ($message): ?>
      <div class="alert alert-info"><?= $message ?></div>
    <?php endif; ?>

    <div class="card">
      <div class="card-body">
        <h3>⚠️ About This Migration</h3>
        <p>This process will:</p>
        <ul style="margin:16px 0; padding-left:20px;">
          <li>✅ Generate secure 64-character tokens for each auto without one</li>
          <li>✅ Update QR codes to use tokens instead of guessable auto numbers</li>
          <li>✅ Prevent users from manually changing URLs to access other autos</li>
          <li>⏱️ Take a few seconds depending on number of autos</li>
        </ul>

        <?php 
        // Check how many autos need migration
        $check = $pdo->prepare("SELECT COUNT(*) as cnt FROM autos WHERE qr_token IS NULL OR qr_token = ''");
        $check->execute();
        $needsMigration = $check->fetch()['cnt'];
        ?>

        <div class="alert alert-warning">
          <strong><?= $needsMigration ?></strong> auto(s) need token generation
        </div>

        <?php if ($needsMigration > 0): ?>
          <form method="POST">
            <button type="submit" class="btn btn-primary" style="margin-top:16px;">
              🚀 Run Migration
            </button>
          </form>
        <?php else: ?>
          <div class="alert alert-success" style="margin-top:16px;">
            ✅ All autos already have tokens!
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<style>
  .alert {
    padding: 12px 16px;
    border-radius: 6px;
    margin: 16px 0;
    border-left: 4px solid;
  }
  .alert-info {
    background: #e3f2fd;
    border-left-color: #2196f3;
    color: #1565c0;
  }
  .alert-warning {
    background: #fff3e0;
    border-left-color: #ff9800;
    color: #e65100;
  }
  .alert-success {
    background: #e8f5e9;
    border-left-color: #4caf50;
    color: #2e7d32;
  }
</style>
</body>
</html>
