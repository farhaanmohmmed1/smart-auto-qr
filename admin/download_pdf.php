<?php
require_once '../config/config.php';
require_once '../lib/QRGenerator.php';
require_once '../lib/PDFGenerator.php';
requireAdmin();

$id   = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM autos WHERE id=?");
$stmt->execute([$id]);
$auto = $stmt->fetch();

if (!$auto) {
    http_response_code(404);
    exit('Auto not found.');
}

// Get QR as base64 for embedding in PDF
$autoUrl  = generateAutoURL($auto['auto_number']);
$qrPath   = QRGenerator::generate($autoUrl, $auto['auto_number'], 400);
$qrBase64 = $qrPath && file_exists($qrPath)
    ? 'data:image/png;base64,' . base64_encode(file_get_contents($qrPath))
    : 'https://api.qrserver.com/v1/create-qr-code/?size=400x400&data=' . urlencode($autoUrl);

PDFGenerator::renderPrintPage($auto, $qrBase64);
exit;
