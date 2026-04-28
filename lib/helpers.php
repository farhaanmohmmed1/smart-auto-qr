<?php
/**
 * lib/helpers.php
 * ================
 * Application-specific helper functions
 * Extracted common patterns for DRY principle
 * 
 * Includes:
 * - QR code regeneration handler
 * - Cache invalidation logic
 */

/**
 * Regenerate QR code for an auto-rickshaw
 * 
 * Deletes old QR code files (all formats), generates fresh ones,
 * and updates database with new path.
 * 
 * Used when:
 * - Registering new auto (first QR generation)
 * - Editing auto (re-generation on update)
 * - Bulk uploading autos (batch generation)
 * 
 * @param string $autoNumber Auto number/ID (e.g., "AUTO-001", "MH-01-AB-001")
 * @param int $autoId Database primary key (autos.id)
 * @param PDO $pdo Database connection object
 * @return string|false File path if successful, false on failure
 */
function regenerateAutoQR($autoNumber, $autoId, $pdo) {
    try {
        // Step 1: Delete old QR files (PNG + SVG if they exist)
        QRGenerator::delete($autoNumber);
        
        // Step 2: Generate new QR code
        // buildQRPayload() creates: https://yourdomain.com/public/auto.php?id=AUTO-001
        $autoUrl = generateAutoURL($autoNumber);
        if (!$autoUrl) {
            error_log("QR Error: Failed to generate URL for $autoNumber");
            return false;
        }
        
        $qrPath = QRGenerator::generate($autoUrl, $autoNumber);
        
        // Step 3: Update database with new QR path
        if ($qrPath) {
            $stmt = $pdo->prepare("UPDATE autos SET qr_path=?, updated_at=NOW() WHERE id=?");
            $stmt->execute([$qrPath, $autoId]);
            
            return $qrPath;
        }
        
        error_log("QR Error: Generation returned false for $autoNumber");
        return false;
        
    } catch (Exception $e) {
        error_log("QR Exception: " . $e->getMessage());
        return false;
    }
}

/**
 * Invalidate admin panel session caches
 * 
 * Clears cached data when database changes occur.
 * Call after INSERT/UPDATE/DELETE operations to ensure
 * next page load gets fresh data.
 * 
 * Examples:
 * - After new auto registration: invalidateAdminCache(['areas_cache'])
 * - After any data change: invalidateAdminCache(['all'])
 * - Specific caches: invalidateAdminCache(['areas_cache', 'dashboard_stats'])
 * 
 * @param array $keys Specific cache keys to clear
 *                    Use ['all'] to clear all caches
 *                    Default: ['all']
 * @return void
 */
function invalidateAdminCache($keys = ['all']) {
    // Define all possible cache keys used in admin panel
    $allCacheKeys = [
        'areas_cache',           // Dropdown: distinct areas
        'dashboard_stats',       // Stats card values
        'scan_chart_cache',      // Chart: last 7 days scans
    ];
    
    // If 'all' specified, clear everything
    if (in_array('all', $keys)) {
        foreach ($allCacheKeys as $key) {
            unset($_SESSION[$key]);
            unset($_SESSION[$key . '_time']);
        }
        error_log("Cache invalidated: ALL");
    } else {
        // Clear specific keys
        foreach ($keys as $key) {
            unset($_SESSION[$key]);
            unset($_SESSION[$key . '_time']);
        }
        error_log("Cache invalidated: " . implode(', ', $keys));
    }
}

/**
 * Get cached value or fetch from callback if expired/missing
 * 
 * Generic cache wrapper for session-based caching
 * 
 * Example:
 * $areas = getSessionCache('areas', function() {
 *     global $pdo;
 *     return $pdo->query("SELECT DISTINCT area FROM autos ...")->fetchAll();
 * }, 3600);  // 1 hour cache
 * 
 * @param string $cacheKey Unique cache identifier
 * @param callable $callback Function to execute if cache miss
 * @param int $ttl Time-to-live in seconds (default: 3600 = 1 hour)
 * @return mixed Cached value or callback result
 */
function getSessionCache($cacheKey, $callback, $ttl = 3600) {
    $timeKey = $cacheKey . '_time';
    
    // Check if cache exists and is still valid
    if (isset($_SESSION[$cacheKey]) && 
        isset($_SESSION[$timeKey]) &&
        (time() - $_SESSION[$timeKey]) < $ttl) {
        
        return $_SESSION[$cacheKey];  // Cache hit
    }
    
    // Cache miss: execute callback
    $value = $callback();
    
    // Store in session
    $_SESSION[$cacheKey] = $value;
    $_SESSION[$timeKey] = time();
    
    return $value;
}

?>
