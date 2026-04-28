<?php
/**
 * API: Get Auto Details by ID (Public — no auth)
 * GET /api/auto.php?id=AUTO_NUMBER
 * ✅ SECURITY: Rate limited, validated input, no information leakage
 */

require_once '../config/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache, no-store, must-revalidate');

$ipAddress = getClientIP();
$endpoint  = '/api/auto.php';

// ── RATE LIMITING ──────────────────────────────────────────
// Max 30 requests per minute per IP (reasonable for passengers scanning)
$rateLimitError = checkAPIRateLimit($endpoint, $ipAddress, maxRequests: 30, windowMinutes: 1);
if ($rateLimitError) {
    http_response_code(429);  // Too Many Requests
    echo json_encode(['success' => false, 'message' => $rateLimitError]);
    exit;
}
recordAPIRequest($endpoint, $ipAddress);

// ── INPUT VALIDATION ──────────────────────────────────────
$autoId = strtoupper(trim($_GET['id'] ?? ''));

if (!$autoId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Auto ID required']);
    exit;
}

// Validate format (alphanumeric + hyphens only, max 50 chars)
if (!preg_match('/^[A-Z0-9-]{1,50}$/', $autoId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid Auto ID format']);
    exit;
}

// ── QUERY DATABASE ────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT auto_number, reg_number, driver_name, phone,
           license_number, permit_number, area, stand, status
    FROM autos
    WHERE auto_number = ?
    LIMIT 1
");
$stmt->execute([$autoId]);
$auto = $stmt->fetch();

if (!$auto) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Auto not found']);
    exit;
}

if ($auto['status'] !== 'active') {
    http_response_code(403);
    echo json_encode(['success' => false, 'status' => $auto['status'], 'message' => 'Auto is ' . $auto['status']]);
    exit;
}

// ✅ SECURE RESPONSE - Only return necessary public info
// Don't leak exact phone numbers in API responses
$phoneDisplay = substr($auto['phone'], 0, -4) . 'XXXX';  // Show first 6 digits only

echo json_encode([
    'success' => true,
    'data'    => [
        'auto_number'    => $auto['auto_number'],
        'reg_number'     => $auto['reg_number'],
        'driver_name'    => $auto['driver_name'],
        'phone_masked'   => $phoneDisplay,  // Masked phone
        'area'           => $auto['area'] ?? '',
        'stand'          => $auto['stand'] ?? '',
        // Removed: license_number, permit_number (too sensitive for public API)
    ]
], JSON_UNESCAPED_SLASHES);
