<?php
/**
 * Generate Missing QR Codes
 * Generates QR codes for any auto records that don't have qr_path set
 * Run once after bulk import if QR codes weren't generated
 */
require_once 'config/config.php';
require_once 'lib/QRGenerator.php';
requireAdmin();

$generated = 0;
$skipped = 0;
$errors = [];

// Find all autos without QR codes
$stmt = $pdo->prepare("SELECT id, auto_number FROM autos WHERE qr_path IS NULL OR qr_path = ''");
$stmt->execute();
$autos = $stmt->fetchAll();

foreach ($autos as $auto) {
    $qrPath = QRGenerator::generate($auto['auto_number']);
    
    if ($qrPath) {
        $updateStmt = $pdo->prepare("UPDATE autos SET qr_path = ? WHERE id = ?");
        $updateStmt->execute([$qrPath, $auto['id']]);
        $generated++;
    } else {
        $skipped++;
        $errors[] = "Failed to generate QR for: {$auto['auto_number']}";
    }
}

// Output results
?>
<!DOCTYPE html>
<html>
<head>
    <title>Generate Missing QR Codes</title>
    <style>
        body { font-family: Arial; margin: 20px; }
        .success { color: green; padding: 15px; background: #f0fff0; border-radius: 8px; margin: 10px 0; }
        .error { color: #d32f2f; padding: 15px; background: #fff0f0; border-radius: 8px; margin: 10px 0; }
        .result { font-size: 18px; font-weight: bold; margin: 20px 0; }
    </style>
</head>
<body>
    <h1>🎯 QR Code Generation Report</h1>
    
    <div class="success">
        <strong>✅ Successfully Generated:</strong> <?= $generated ?> QR codes
    </div>
    
    <?php if ($skipped > 0): ?>
    <div class="error">
        <strong>⚠️ Skipped/Failed:</strong> <?= $skipped ?> records
        <?php if (!empty($errors)): ?>
            <ul>
                <?php foreach ($errors as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <div class="result">
        ✅ Done! Generated <?= $generated ?> QR codes.
    </div>
    
    <p><a href="admin/manage.php">← Back to Manage Autos</a></p>
</body>
</html>
