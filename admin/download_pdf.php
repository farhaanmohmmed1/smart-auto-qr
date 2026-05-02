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
$qrUrl = QRGenerator::generate($auto['auto_number'], 400);

if (!$qrUrl) {
    http_response_code(500);
    exit('Failed to generate QR code.');
}

// Fetch QR image from API and convert to base64
try {
    $qrImage = @file_get_contents($qrUrl, false, stream_context_create([
        'http' => ['timeout' => 10]
    ]));
    
    $qrBase64 = $qrImage !== false 
        ? 'data:image/png;base64,' . base64_encode($qrImage)
        : $qrUrl;  // Fallback to URL if fetch fails
} catch (Exception $e) {
    $qrBase64 = $qrUrl;  // Fallback to URL if exception
}

PDFGenerator::renderPrintPage($auto, $qrBase64);
exit;
