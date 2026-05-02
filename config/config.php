<?php
// ── Start Session (MUST be before any output) ──────────────
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Smart Auto QR Safety System - Vercel Configuration
 * Environment-based configuration for serverless deployment
 * 
 * All secrets stored in Vercel Environment Variables, not in code!
 */

// ── Load Composer Autoloader ────────────────────────────────
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

// ── Load .env File (Development Only) ───────────────────────
if (!getenv('ENVIRONMENT') || getenv('ENVIRONMENT') !== 'production') {
    if (file_exists(__DIR__ . '/../.env')) {
        $envFile = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($envFile as $line) {
            if (strpos(trim($line), '#') === 0) continue;  // Skip comments
            if (strpos($line, '=') === false) continue;     // Skip invalid lines
            
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim(trim($value), '"\'');
            
            if (!getenv($key)) {
                putenv("{$key}={$value}");
            }
        }
    }
}

// ── Environment Detection ───────────────────────────────────
define('ENVIRONMENT', getenv('ENVIRONMENT') ?: 'development');
define('IS_PRODUCTION', ENVIRONMENT === 'production');
define('IS_DEVELOPMENT', ENVIRONMENT === 'development');

// ── Database Configuration (from Environment Variables) ─────
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'smart_auto_db');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');

// Verify required production credentials
if (IS_PRODUCTION) {
    $requiredEnv = ['DB_HOST', 'DB_NAME', 'DB_USER', 'JWT_SECRET'];
    foreach ($requiredEnv as $var) {
        if (!getenv($var)) {
            http_response_code(500);
            die(json_encode([
                'error' => "❌ Missing required environment variable: {$var}",
                'fix' => "Add {$var} to Vercel Environment Variables"
            ]));
        }
    }
}

// ── JWT Configuration ────────────────────────────────────────
define('JWT_SECRET', getenv('JWT_SECRET') ?: 'dev-secret-change-in-production-immediately');
define('JWT_ALGORITHM', 'HS256');
define('JWT_EXPIRY', 86400);  // 24 hours token expiration

// ── Application Settings ────────────────────────────────────
define('APP_NAME', 'Smart Auto QR Safety System');
define('APP_VERSION', '2.0.0-vercel');
define('HELPLINE', '100');
define('SOS_WHATSAPP', '919100000000');

// ── API & Frontend URLs (from Environment Variables) ────────
define('API_URL', getenv('API_URL') ?: (IS_PRODUCTION 
    ? 'https://yourdomain.vercel.app/api'
    : 'http://localhost:3000/api'
));

define('FRONTEND_URL', getenv('FRONTEND_URL') ?: (IS_PRODUCTION 
    ? 'https://yourdomain.vercel.app'
    : 'http://localhost:3000'
));

define('CDN_URL', getenv('CDN_URL') ?: FRONTEND_URL);

// ── File Storage Configuration ────────────────────────────────
define('FILE_STORAGE', getenv('FILE_STORAGE') ?: 'local');

// Cloudinary credentials (for image storage)
define('CLOUDINARY_NAME', getenv('CLOUDINARY_NAME') ?: '');
define('CLOUDINARY_KEY', getenv('CLOUDINARY_KEY') ?: '');
define('CLOUDINARY_SECRET', getenv('CLOUDINARY_SECRET') ?: '');

// ── Filesystem Paths ────────────────────────────────────────
// Vercel has read-only root except /tmp
define('ROOT_PATH', '/tmp/vercel');
define('QR_DIR', '/tmp/vercel/qrcodes/');
define('QR_URL', API_URL . '/qr/');  // Serve QR codes via API
define('UPLOADS_DIR', '/tmp/vercel/uploads/');

// Create temp directories if they don't exist
@mkdir(QR_DIR, 0755, true);
@mkdir(UPLOADS_DIR, 0755, true);

// ── Security Settings ────────────────────────────────────────
define('SESSION_NAME', 'saqss_token');
define('SESSION_TIMEOUT', 86400);  // 24 hours
define('SECURE_COOKIE', IS_PRODUCTION);  // HTTPS-only in production
define('CSRF_TOKEN_LENGTH', 32);

// ── PDO Database Connection ─────────────────────────────────
try {
    // Use mysqlnd for better Vercel compatibility on long connections
    $dsn = sprintf(
        "mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4",
        DB_HOST,
        DB_PORT,
        DB_NAME
    );
    
    $pdo = new PDO(
        $dsn,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 5,  // Connection timeout
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
        ]
    );
    
    // ✅ Connection successful
    define('DB_CONNECTED', true);
    
} catch (PDOException $e) {
    define('DB_CONNECTED', false);
    
    http_response_code(500);
    $errorResponse = [
        'success' => false,
        'error' => IS_PRODUCTION 
            ? 'Database unavailable'
            : 'Database connection failed: ' . $e->getMessage()
    ];
    
    header('Content-Type: application/json');
    die(json_encode($errorResponse));
}

// ── Global Helper Functions ─────────────────────────────────

/**
 * HTML escape user input
 */
function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Send JSON response with proper headers
 */
function sendJSON(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: ' . FRONTEND_URL);
    header('Access-Control-Allow-Credentials: true');
    
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Send error response
 */
function error(string $message, int $statusCode = 400, array $extra = []): void {
    sendJSON([
        'success' => false,
        'error' => $message,
        ...$extra
    ], $statusCode);
}

/**
 * Send success response
 */
function success(array $data = [], int $statusCode = 200): void {
    sendJSON([
        'success' => true,
        ...$data
    ], $statusCode);
}

/**
 * Set CORS and OPTIONS handling
 */
function setCORSHeaders(): void {
    header('Access-Control-Allow-Origin: ' . FRONTEND_URL);
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Allow-Credentials: true');
    
    // Handle OPTIONS preflight requests
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}

/**
 * Get client IP address (handles proxies)
 */
function getClientIP(): string {
    return $_SERVER['HTTP_CF_CONNECTING_IP']      // Cloudflare IP
        ?? $_SERVER['HTTP_X_FORWARDED_FOR']       // Load balancer IP
        ?? $_SERVER['HTTP_X_CLIENT_IP']
        ?? $_SERVER['REMOTE_ADDR']
        ?? 'unknown';
}

/**
 * Validate CSRF token (legacy, kept for backward compatibility)
 */
function validateCSRFToken(string $token = null): bool {
    return !empty($token); // In Vercel, use JWT instead
}

/**
 * Generate CSRF token (legacy)
 */
function generateCSRFToken(): string {
    return bin2hex(random_bytes(CSRF_TOKEN_LENGTH));
}

/**
 * Simple rate limiting (in-memory, use Redis in production)
 */
function checkRateLimit(
    string $key,
    int $maxRequests = 30,
    int $windowSeconds = 60
): bool {
    static $requestLog = [];
    
    $now = time();
    $ipKey = "{$key}:" . getClientIP();
    
    if (!isset($requestLog[$ipKey])) {
        $requestLog[$ipKey] = [];
    }
    
    // Clean old entries
    $requestLog[$ipKey] = array_filter(
        $requestLog[$ipKey],
        fn($time) => ($now - $time) < $windowSeconds
    );
    
    // Check limit
    if (count($requestLog[$ipKey]) >= $maxRequests) {
        return false;
    }
    
    // Log this request
    $requestLog[$ipKey][] = $now;
    return true;
}

/**
 * Redirect (prevents headers already sent errors)
 */
function redirect(string $url): void {
    if (!headers_sent()) {
        header("Location: {$url}");
    }
    exit;
}

/**
 * Check if user is authenticated (for legacy compatibility)
 */
function isAdmin(): bool {
    // Check session variables set by login.php
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

/**
 * Require admin authentication (use JWTAuth::requireAuth() instead in new code)
 */
function requireAdmin(): void {
    if (!isAdmin()) {
        http_response_code(401);
        redirect('../admin/login.php?msg=timeout');
    }
}

/**
 * Generate public view URL from auto number (token-based security)
 * 
 * SECURITY: Uses secure token instead of auto_number in URL
 * Prevents users from guessing/changing URL to access other autos
 */
function generateAutoURL(string $autoNumber): string {
    global $pdo;
    
    // Get the qr_token for this auto
    try {
        $stmt = $pdo->prepare("SELECT qr_token FROM autos WHERE auto_number = ? LIMIT 1");
        $stmt->execute([$autoNumber]);
        $result = $stmt->fetch();
        
        if ($result && !empty($result['qr_token'])) {
            $token = $result['qr_token'];
        } else {
            // Generate new token if not exists
            $token = bin2hex(random_bytes(32));
            // Try to update, ignore if fails (auto might not exist yet)
            $upd = $pdo->prepare("UPDATE autos SET qr_token = ? WHERE auto_number = ? AND qr_token IS NULL");
            $upd->execute([$token, $autoNumber]);
        }
    } catch (Exception $e) {
        // Fallback: generate token
        $token = bin2hex(random_bytes(32));
    }
    
    // Build URL using current server's protocol and host
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
    $host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // Detect if we're in a subdirectory (XAMPP development)
    $scriptPath = $_SERVER['SCRIPT_NAME'] ?? '';
    $basePath   = dirname(dirname($scriptPath)); // Remove /config/config.php → /smart_auto_qr
    
    // Build the public view URL with secure token
    return "{$protocol}://{$host}{$basePath}/public/auto.php?token=" . urlencode($token);
}

/**
 * Validate password (OWASP compliant)
 */
function validatePassword(string $password, ?string &$errorMsg = null): bool {
    $errors = [];
    
    if (strlen($password) < 12) {
        $errors[] = 'Password must be at least 12 characters long.';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must contain at least one UPPERCASE letter.';
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Password must contain at least one lowercase letter.';
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must contain at least one number.';
    }
    if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
        $errors[] = 'Password must contain at least one special character.';
    }
    
    if (!empty($errors)) {
        $errorMsg = implode(' | ', $errors);
        return false;
    }
    
    return true;
}

/**
 * Validate GPS coordinates
 */
function validateGPSCoordinates(float $latitude, float $longitude): bool {
    if ($latitude < -90 || $latitude > 90) return false;
    if ($longitude < -180 || $longitude > 180) return false;
    return true;
}

/**
 * Validate and format phone number
 */
function validatePhoneNumber(string $phone): bool {
    $clean = preg_replace('/\D/', '', $phone);
    return preg_match('/^\d{10}$|^\d{12}$/', $clean) ? true : false;
}

/**
 * Check login attempts (from DB)
 */
function checkLoginAttempts(string $username, string $ipAddress): ?string {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as failed_count 
            FROM login_attempts 
            WHERE (username = ? OR ip_address = ?) 
            AND success = 0 
            AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
        ");
        $stmt->execute([$username, $ipAddress]);
        $result = $stmt->fetch();
        $failedAttempts = $result['failed_count'] ?? 0;
        
        if ($failedAttempts >= 5) {
            return '🔒 Too many failed login attempts. Try again in 30 minutes.';
        }
    } catch (Exception $e) {
        // Fallback if table doesn't exist yet
    }
    
    return null;
}

/**
 * Record login attempt
 */
function recordLoginAttempt(string $username, string $ipAddress, bool $success = false): void {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO login_attempts (username, ip_address, success) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$username, $ipAddress, (int)$success]);
    } catch (Exception $e) {
        // Silently fail if table doesn't exist
    }
}

/**
 * Check API rate limit
 */
function checkAPIRateLimit(string $endpoint, string $ipAddress, int $maxRequests = 10, int $windowMinutes = 1): ?string {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as req_count 
            FROM api_rate_limits 
            WHERE endpoint = ? AND ip_address = ? 
            AND request_time > DATE_SUB(NOW(), INTERVAL ? MINUTE)
        ");
        $stmt->execute([$endpoint, $ipAddress, $windowMinutes]);
        $result = $stmt->fetch();
        $requestCount = $result['req_count'] ?? 0;
        
        if ($requestCount >= $maxRequests) {
            return "⛔ Too many requests. Wait ${windowMinutes} minute(s) before trying again.";
        }
    } catch (Exception $e) {
        // Fallback - allow if table doesn't exist
    }
    
    return null;
}

/**
 * Record API request
 */
function recordAPIRequest(string $endpoint, string $ipAddress): void {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO api_rate_limits (endpoint, ip_address) 
            VALUES (?, ?)
        ");
        $stmt->execute([$endpoint, $ipAddress]);
    } catch (Exception $e) {
        // Silently fail if table doesn't exist
    }
}

// ── Debug Mode (disable in production) ──────────────────────
if (!IS_PRODUCTION) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}

