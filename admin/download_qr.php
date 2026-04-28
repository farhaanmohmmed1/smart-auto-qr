<?php
require_once '../config/config.php';
require_once '../lib/QRGenerator.php';
requireAdmin();

$id   = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT auto_number FROM autos WHERE id=?");
$stmt->execute([$id]);
$auto = $stmt->fetch();

if (!$auto) {
    http_response_code(404);
    exit('Auto not found.');
}

$autoNum = $auto['auto_number'];
$autoUrl = generateAutoURL($autoNum);
$path    = QRGenerator::generate($autoUrl, $autoNum, 400);

if (!$path || !file_exists($path)) {
    // Send browser to the API URL
    $apiUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=400x400&data=' . urlencode($autoUrl) . '&margin=10';
    header("Location: $apiUrl");
    exit;
}

$filename = 'QR_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $autoNum) . '.png';
header('Content-Type: image/png');
header("Content-Disposition: attachment; filename=\"$filename\"");
header('Content-Length: ' . filesize($path));
readfile($path);
exit;
