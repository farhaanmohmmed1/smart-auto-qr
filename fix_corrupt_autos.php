<?php
require_once 'config/config.php';
require_once 'lib/QRGenerator.php';

// Find and fix any auto numbers with special characters like backticks
$stmt = $pdo->prepare("SELECT id, auto_number FROM autos WHERE auto_number LIKE '%`%'");
$stmt->execute();
$results = $stmt->fetchAll();

foreach ($results as $row) {
    echo 'Found: ' . $row['auto_number'] . ' (ID: ' . $row['id'] . ')' . PHP_EOL;
    
    $clean = str_replace("`", '', $row['auto_number']);
    $clean = trim($clean);
    echo 'Cleaned: ' . $clean . PHP_EOL;
    
    // Update auto number
    $upd = $pdo->prepare('UPDATE autos SET auto_number = ? WHERE id = ?');
    $upd->execute([$clean, $row['id']]);
    
    // Now generate QR for the fixed record
    $qrPath = QRGenerator::generate($clean);
    if ($qrPath) {
        $pdo->prepare('UPDATE autos SET qr_path = ? WHERE id = ?')
            ->execute([$qrPath, $row['id']]);
        echo '✅ Updated and QR generated' . PHP_EOL;
    } else {
        echo '❌ QR generation failed' . PHP_EOL;
    }
}

if (empty($results)) {
    echo "No corrupt auto numbers found!" . PHP_EOL;
}
