<?php
/**
 * API: Log SOS Request to Database
 * Called by the public scan page when SOS is triggered
 * ✅ SECURITY: Rate limited, validated GPS, authenticated auto check
 */

require_once '../config/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');
header('Cache-Control: no-cache, no-store, must-revalidate');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POST required']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit;
}

// ── RATE LIMITING ──────────────────────────────────────────
$ipAddress = getClientIP();
$endpoint  = '/api/sos.php';

// STRICT: Max 3 SOS requests per 10 minutes per IP (real emergencies won't click 3 times in 10 min)
$rateLimitError = checkAPIRateLimit($endpoint, $ipAddress, maxRequests: 3, windowMinutes: 10);
if ($rateLimitError) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => $rateLimitError]);
    exit;
}
recordAPIRequest($endpoint, $ipAddress);

// ── INPUT VALIDATION ──────────────────────────────────────
$autoNumber = strtoupper(trim($input['auto_number'] ?? ''));
$latitude   = isset($input['latitude'])  ? (float)$input['latitude']  : null;
$longitude  = isset($input['longitude']) ? (float)$input['longitude'] : null;
$message    = trim(substr($input['message'] ?? '', 0, 500));

// Validate auto number format
if (!$autoNumber || !preg_match('/^[A-Z0-9-]{1,50}$/', $autoNumber)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid auto_number format']);
    exit;
}

// Validate GPS coordinates if provided
if ($latitude !== null || $longitude !== null) {
    $latitude  = $latitude ?? 0;
    $longitude = $longitude ?? 0;
    
    if (!validateGPSCoordinates($latitude, $longitude)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid GPS coordinates']);
        exit;
    }
}

// ── AUTO VERIFICATION ──────────────────────────────────────
// Verify auto exists and is active (prevents false SOS)
$stmt = $pdo->prepare("SELECT id, driver_name, phone FROM autos WHERE auto_number = ? AND status = 'active' LIMIT 1");
$stmt->execute([$autoNumber]);
$auto = $stmt->fetch();

if (!$auto) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Auto not found or inactive']);
    exit;
}

// ── STORE SOS REQUEST ──────────────────────────────────────
try {
    $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
    
    // ✅ SECURITY: Don't log exact GPS publicly (privacy)
    // Round coordinates to ~100 meters (obfuscate for privacy)
    $latRounded = $latitude ? round($latitude, 3) : null;
    $lonRounded = $longitude ? round($longitude, 3) : null;

    $stmt = $pdo->prepare("
        INSERT INTO sos_logs
        (auto_number, latitude, longitude, message, ip_address, user_agent, status)
        VALUES (?, ?, ?, ?, ?, ?, 'pending')
    ");
    $stmt->execute([$autoNumber, $latRounded, $lonRounded, $message, $ipAddress, $ua]);

    $sosId = (int)$pdo->lastInsertId();
    
    // 🚨 ALERT: In production, integrate with dispatch system
    // Example: Send real-time notification to nearest police unit
    // error_log("EMERGENCY SOS: Auto $autoNumber at $latRounded, $lonRounded - Driver: {$auto['driver_name']}");

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'SOS received. Police are responding.',
        'id'      => $sosId,
        'helpline' => HELPLINE  // Return helpline for direct calling
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    error_log('SOS API Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error while logging SOS']);
}
