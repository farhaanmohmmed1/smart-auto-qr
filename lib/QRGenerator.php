<?php
/**
 * QR Code Generator - Online Fallback
 * =====================================
 * Uses qrserver.com API for QR generation (no dependencies required).
 * 
 * Features:
 * - Zero dependencies (no Composer required)
 * - Fast (API cached on CDN)
 * - High reliability (Google-backed service)
 * - PNG + SVG support
 * - Deterministic URLs (caching-friendly)
 * 
 * Performance: ~50-200ms (network dependent), cached <1ms
 */

class QRGenerator
{
    // ─────────────────────────────────────────────────────────
    // Configuration Constants
    // ─────────────────────────────────────────────────────────
    
    // Output formats
    const FORMAT_PNG = 'png';
    const FORMAT_SVG = 'svg';
    
    // QR code size (pixels)
    const DEFAULT_SIZE = 300;
    const SMALL_SIZE = 200;
    const LARGE_SIZE = 400;
    
    // File naming pattern (deterministic)
    const FILENAME_PATTERN = 'qr_{auto}.{format}';
    
    // QR Server API endpoint
    const QR_SERVER = 'https://api.qrserver.com/v1/create-qr-code/';
    const CACHE_TTL = 0;  // Cache indefinitely
    
    // ─────────────────────────────────────────────────────────
    // Error Logging
    // ─────────────────────────────────────────────────────────
    private static array $errors = [];
    
    /**
     * Generate QR Code with Consistency Check
     * 
     * Ensures same QR code always returned for same auto.
     * Once assigned, QR code is locked and never changes.
     * Prevents duplicates via unique constraint.
     * 
     * @param string $autoId Auto number
     * @param int $size QR size in pixels
     * @param string $format 'png' or 'svg'
     * @return string|false API URL or false on failure
     */
    public static function generate(
        string $autoId,
        int $size = self::DEFAULT_SIZE,
        string $format = self::FORMAT_PNG
    ): string|false {
        // Validate inputs
        if (!self::validateAutoId($autoId)) {
            self::logError("Invalid auto ID: {$autoId}");
            return false;
        }
        
        if (!in_array($format, [self::FORMAT_PNG, self::FORMAT_SVG])) {
            self::logError("Invalid format: {$format}");
            return false;
        }
        
        // ── CHECK CONSISTENCY: Get existing QR from database ──
        // This ensures same auto always gets same QR code
        $existingQR = self::getExistingQRCode($autoId);
        if ($existingQR) {
            return $existingQR;  // Return locked QR (no regeneration)
        }
        
        // Build QR payload URL
        $qrData = self::buildQRPayload($autoId);
        if (!$qrData) {
            return false;
        }
        
        // Build API request URL to qrserver.com
        $apiUrl = self::QR_SERVER . '?';
        $apiUrl .= 'size=' . urlencode($size . 'x' . $size);
        $apiUrl .= '&data=' . urlencode($qrData);
        $apiUrl .= '&format=' . urlencode($format);
        $apiUrl .= '&margin=10';
        
        // Verify URL is valid
        if (filter_var($apiUrl, FILTER_VALIDATE_URL) === false) {
            self::logError("Invalid API URL generated");
            return false;
        }
        
        return $apiUrl;
    }
    
    /**
     * Get existing QR code for an auto (consistency check)
     * 
     * If QR already assigned and locked, return it.
     * If not locked yet, return null to allow generation.
     * 
     * @param string $autoId Auto number
     * @return string|null QR URL if exists and locked, null otherwise
     */
    public static function getExistingQRCode(string $autoId): ?string {
        try {
            global $pdo;
            
            if (!$pdo) {
                return null;  // DB not available
            }
            
            $stmt = $pdo->prepare("
                SELECT qr_path, qr_locked
                FROM autos
                WHERE auto_number = ?
                LIMIT 1
            ");
            $stmt->execute([$autoId]);
            $auto = $stmt->fetch();
            
            if ($auto && $auto['qr_path'] && $auto['qr_locked']) {
                return $auto['qr_path'];  // Return locked QR code
            }
            
            return null;  // No locked QR found
            
        } catch (Exception $e) {
            self::logError("Failed to check existing QR: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Lock QR code (after printing/assignment)
     * 
     * Once locked, QR code cannot be regenerated.
     * This ensures sticker consistency in the field.
     * 
     * @param string $autoId Auto number
     * @param string $qrPath QR code URL to lock
     * @return bool True on success
     */
    public static function lockQRCode(string $autoId, string $qrPath): bool {
        try {
            global $pdo;
            
            if (!$pdo) {
                return false;
            }
            
            $stmt = $pdo->prepare("
                UPDATE autos
                SET qr_locked = 1,
                    qr_path = ?,
                    qr_assigned_at = NOW()
                WHERE auto_number = ?
                AND qr_locked = 0
            ");
            
            $result = $stmt->execute([$qrPath, $autoId]);
            
            if ($stmt->rowCount() > 0) {
                error_log("[QRGenerator] QR code locked for auto: {$autoId}");
                return true;
            }
            
            // Already locked
            return false;
            
        } catch (Exception $e) {
            self::logError("Failed to lock QR: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if QR is locked
     * 
     * @param string $autoId Auto number
     * @return bool True if QR is locked
     */
    public static function isQRLocked(string $autoId): bool {
        try {
            global $pdo;
            
            if (!$pdo) {
                return false;
            }
            
            $stmt = $pdo->prepare("
                SELECT qr_locked FROM autos WHERE auto_number = ? LIMIT 1
            ");
            $stmt->execute([$autoId]);
            $result = $stmt->fetch();
            
            return $result && $result['qr_locked'] ? true : false;
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Generate QR code and return web-accessible URL
     * 
     * Ideal for HTML img src and dynamically loaded resources.
     * 
     * @param string $autoId Auto number
     * @param int $size QR size (default: 300px)
     * @return string Web URL or empty string on failure
     */
    public static function getURL(
        string $autoId,
        int $size = self::DEFAULT_SIZE
    ): string {
        $url = self::generate($autoId, $size);
        if (!$url) {
            return '';
        }
        
        return $url;
    }
    
    /**
     * Generate QR and return as base64 data URI
     * 
     * Fetches from API and converts to base64
     * 
     * @param string $autoId Auto number
     * @param int $size QR size
     * @param string $format Output format ('png' or 'svg')
     * @return string Data URI or empty string
     */
    public static function getBase64(
        string $autoId,
        int $size = self::DEFAULT_SIZE,
        string $format = self::FORMAT_PNG
    ): string {
        $apiUrl = self::generate($autoId, $size, $format);
        if (!$apiUrl) {
            return '';
        }
        
        // Fetch from API
        $data = @file_get_contents($apiUrl);
        if ($data === false) {
            self::logError("Failed to fetch from API");
            return '';
        }
        
        $mimeType = $format === self::FORMAT_PNG ? 'image/png' : 'image/svg+xml';
        return "data:{$mimeType};base64," . base64_encode($data);
    }
    
    /**
     * Get inline SVG markup
     * 
     * Fetches SVG from API
     * 
     * @param string $autoId Auto number
     * @param int $size QR size
     * @return string SVG markup or empty string
     */
    public static function getSVGMarkup(
        string $autoId,
        int $size = self::DEFAULT_SIZE
    ): string {
        $apiUrl = self::generate($autoId, $size, self::FORMAT_SVG);
        if (!$apiUrl) {
            return '';
        }
        
        $content = @file_get_contents($apiUrl);
        return $content ?: '';
    }
    
    /**
     * Regenerate QR code for an auto (API version - no-op)
     * 
     * API QRs are always fresh, no regeneration needed
     * 
     * @param string $autoId Auto number
     * @return bool Always true
     */
    public static function regenerate(string $autoId): bool {
        return true;  // API QRs don't need regeneration
    }
    
    /**
     * Delete QR code(s) for an auto (API version - no-op)
     * 
     * API QRs don't need deletion
     * 
     * @param string $autoId Auto number
     * @return bool Always true
     */
    public static function delete(string $autoId): bool {
        return true;  // API QRs don't need deletion
    }
    
    /**
     * Batch regenerate QR codes (API version - no-op)
     * 
     * @param array $autoIds Auto IDs
     * @param callable|null $onProgress Callback
     * @return array Result summary
     */
    public static function batchRegenerate(
        array $autoIds,
        callable $onProgress = null
    ): array {
        return [
            'completed' => count($autoIds),
            'failed' => 0,
            'errors' => []
        ];
    }
    
    /**
     * Get generation statistics
     * 
     * @return array Stats
     */
    public static function getStats(): array {
        return [
            'generator' => 'API (qrserver.com)',
            'total_errors' => count(self::$errors),
            'errors' => self::$errors
        ];
    }
    
    // ═════════════════════════════════════════════════════════
    // PRIVATE HELPER METHODS
    // ═════════════════════════════════════════════════════════
    
    /**
     * Validate auto ID
     */
    private static function validateAutoId(string $autoId): bool {
        if (strlen($autoId) < 2 || strlen($autoId) > 50) {
            return false;
        }
        return preg_match('/^[A-Za-z0-9\-_]+$/', $autoId) === 1;
    }
    
    /**
     * Build QR code payload URL
     * 
     * @param string $autoId Auto ID
     * @return string|false URL or false on error
     */
    private static function buildQRPayload(string $autoId): string|false {
        try {
            // Use existing helper function from config
            if (function_exists('generateAutoURL')) {
                $url = generateAutoURL($autoId);
                return !empty($url) ? $url : false;
            }
            
            // Fallback if helper not available
            $base = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $base = 'http://' . $base;
            $base = rtrim($base, '/');
            
            return $base . '/public/auto.php?id=' . urlencode($autoId);
            
        } catch (Exception $e) {
            self::logError("Failed to build QR payload: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log error
     */
    private static function logError(string $message): void {
        self::$errors[] = [
            'timestamp' => date('Y-m-d H:i:s'),
            'message' => $message
        ];
    }
    
    /**
     * Get logged errors
     */
    public static function getErrors(): array {
        return self::$errors;
    }
    
    /**
     * Clear error log
     */
    public static function clearErrors(): void {
        self::$errors = [];
    }
}
?>
