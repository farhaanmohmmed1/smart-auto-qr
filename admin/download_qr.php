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

// Generate QR code (returns API URL)
$qrUrl = QRGenerator::generate($autoNum, 400, QRGenerator::FORMAT_PNG);

if (!$qrUrl) {
    http_response_code(500);
    exit('Failed to generate QR code.');
}

// ── Stream QR image from API to user ──────────────────────
// This allows direct download without relying on local file storage
try {
    $qrImage = @file_get_contents($qrUrl, false, stream_context_create([
        'http' => ['timeout' => 10]
    ]));
    
    if ($qrImage === false) {
        // If API fails, redirect to API URL directly
        header("Location: $qrUrl");
        exit;
    }
    
    $filename = 'QR_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $autoNum) . '.png';
    header('Content-Type: image/png');
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header('Content-Length: ' . strlen($qrImage));
    header('Cache-Control: private, no-cache, no-store, must-revalidate');
    echo $qrImage;
    exit;
} catch (Exception $e) {
    // Fallback: redirect to API
    header("Location: $qrUrl");
    exit;
}

