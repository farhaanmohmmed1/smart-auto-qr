<?php
require_once 'config/config.php';
require_once 'lib/QRGenerator.php';

$generated = 0;
$skipped = 0;

$stmt = $pdo->prepare('SELECT id, auto_number FROM autos WHERE qr_path IS NULL OR qr_path = ""');
$stmt->execute();
$autos = $stmt->fetchAll();

foreach ($autos as $auto) {
    $qrPath = QRGenerator::generate($auto['auto_number']);
    if ($qrPath) {
        $updateStmt = $pdo->prepare('UPDATE autos SET qr_path = ? WHERE id = ?');
        $updateStmt->execute([$qrPath, $auto['id']]);
        $generated++;
        echo "✅ Generated QR for: " . $auto['auto_number'] . "\n";
    } else {
        $skipped++;
        echo "❌ Failed for: " . $auto['auto_number'] . "\n";
    }
}

echo "\n===================\n";
echo "✅ Generated: " . $generated . " QR codes\n";
if ($skipped > 0) {
    echo "⚠️  Skipped: " . $skipped . "\n";
}
echo "===================\n";
