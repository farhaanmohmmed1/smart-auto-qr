<?php
/**
 * JWT (JSON Web Token) Authentication Library
 * 
 * Replaces session-based authentication for stateless Vercel deployment.
 * Tokens are stateless and don't require server-side session storage.
 * 
 * Usage:
 *   $token = JWTAuth::createToken($adminId, $username, $role);
 *   $payload = JWTAuth::verifyToken($token);
 *   $payload = JWTAuth::requireAuth();  // Require valid token or exit
 */

class JWTAuth
{
    /**
     * Create JWT token for admin
     * 
     * @param int $adminId Admin user ID
     * @param string $username Admin username
     * @param string $role Admin role ('admin' or 'superadmin')
     * @return string JWT token (can be stored in localStorage on frontend)
     */
    public static function createToken(int $adminId, string $username, string $role): string
    {
        // JWT Header
        $header = [
            'alg' => JWT_ALGORITHM,  // HS256
            'typ' => 'JWT'
        ];
        
        // JWT Payload
        $payload = [
            'sub' => $adminId,                      // Subject (user ID)
            'usr' => $username,                     // Username
            'role' => $role,                        // Role
            'iat' => time(),                        // Issued At
            'exp' => time() + JWT_EXPIRY            // Expiration (24 hours)
        ];
        
        // Encode header & payload
        $headerEncoded = self::base64UrlEncode(json_encode($header));
        $payloadEncoded = self::base64UrlEncode(json_encode($payload));
        
        // Create signature
        $signature = hash_hmac(
            'sha256',
            "{$headerEncoded}.{$payloadEncoded}",
            JWT_SECRET,
            true  // binary output
        );
        $signatureEncoded = self::base64UrlEncode($signature);
        
        // Return complete JWT
        return "{$headerEncoded}.{$payloadEncoded}.{$signatureEncoded}";
    }
    
    /**
     * Verify and decode JWT token
     * 
     * @param string $token JWT token from Authorization header
     * @return array|null Token payload on success, null on failure
     */
    public static function verifyToken(string $token): ?array
    {
        // Check token format (must have 3 parts separated by dots)
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }
        
        [$headerEncoded, $payloadEncoded, $signatureEncoded] = $parts;
        
        // Verify signature
        $expectedSignature = hash_hmac(
            'sha256',
            "{$headerEncoded}.{$payloadEncoded}",
            JWT_SECRET,
            true  // binary output
        );
        
        // Use hash_equals to prevent timing attacks
        if (!hash_equals(
            self::base64UrlEncode($expectedSignature),
            $signatureEncoded
        )) {
            return null;  // INVALID SIGNATURE
        }
        
        // Decode and parse payload
        $payload = json_decode(self::base64UrlDecode($payloadEncoded), true);
        
        if (!$payload || !is_array($payload)) {
            return null;  // INVALID PAYLOAD
        }
        
        // Check expiration
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return null;  // TOKEN EXPIRED
        }
        
        return $payload;  // ✅ VALID TOKEN
    }
    
    /**
     * Get JWT token from Authorization header
     * 
     * Expected format: Authorization: Bearer <token>
     * 
     * @return string|null JWT token or null if not found
     */
    public static function getTokenFromHeader(): ?string
    {
        // Check "Authorization: Bearer <token>" header
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        
        if (preg_match('/^Bearer\s+(.+)$/', $authHeader, $matches)) {
            return $matches[1];
        }
        
        // Also check custom header
        $customToken = $_SERVER['HTTP_X_AUTH_TOKEN'] ?? '';
        if ($customToken) {
            return $customToken;
        }
        
        // Check Authorization query parameter (fallback, less secure)
        return $_GET['token'] ?? null;
    }
    
    /**
     * Require valid JWT token (authorize middleware)
     * 
     * Call this at the start of protected API endpoints.
     * Returns payload if valid, or sends error response if invalid.
     * 
     * Example:
     *   $payload = JWTAuth::requireAuth();
     *   $userId = $payload['sub'];
     *   $role = $payload['role'];
     * 
     * @param string[] $requiredRoles Optional: Only allow these roles
     * @return array Token payload
     * @throws Exit on invalid token (sends JSON error response)
     */
    public static function requireAuth(array $requiredRoles = []): array
    {
        // Get token from header/parameter
        $token = self::getTokenFromHeader();
        if (!$token) {
            error('Missing authorization token', 401);
        }
        
        // Verify token
        $payload = self::verifyToken($token);
        if (!$payload) {
            error('Invalid or expired token', 401);
        }
        
        // Check role if specified
        if (!empty($requiredRoles)) {
            $userRole = $payload['role'] ?? '';
            if (!in_array($userRole, $requiredRoles)) {
                error('Insufficient permissions', 403);
            }
        }
        
        return $payload;
    }
    
    /**
     * Refresh token (extend expiration)
     * 
     * Verifies the current token and issues a new one with extended expiration.
     * Useful for sliding window sessions.
     * 
     * @param string $token Current token
     * @return string|null New token or null if refresh failed
     */
    public static function refreshToken(string $token): ?string
    {
        $payload = self::verifyToken($token);
        if (!$payload) {
            return null;
        }
        
        // Create new token with same data but new expiration
        return self::createToken(
            $payload['sub'],
            $payload['usr'],
            $payload['role']
        );
    }
    
    /**
     * Revoke token (simple approach without blacklist)
     * 
     * Note: JWT tokens cannot truly be revoked without a blacklist/database.
     * For simple use cases, just expect the user to clear localStorage.
     * For production, consider implementing a token blacklist in Redis or DB.
     * 
     * @return bool Always true (frontend should clear token)
     */
    public static function revokeToken(): bool
    {
        // In a simple setup, user clears localStorage on frontend
        // For production: Add token to blacklist in Redis/Database
        return true;
    }
    
    // ── PRIVATE HELPER METHODS ──────────────────────────────
    
    /**
     * Base64 URL-safe encode
     * 
     * Standard base64 uses +, /, = which are problematic in URLs.
     * URL-safe base64 replaces: + → -, / → _, removes =
     */
    private static function base64UrlEncode(string $data): string
    {
        $b64 = base64_encode($data);
        // Replace URL-unsafe characters
        return rtrim(strtr($b64, '+/', '-_'), '=');
    }
    
    /**
     * Base64 URL-safe decode
     */
    private static function base64UrlDecode(string $data): string
    {
        // Add padding if needed
        $padlen = strlen($data) % 4;
        if ($padlen) {
            $data .= str_repeat('=', 4 - $padlen);
        }
        
        // Restore URL-unsafe characters
        return base64_decode(strtr($data, '-_', '+/'));
    }
}

/**
 * Helper: Get current authenticated admin (from token)
 * 
 * Usage in API endpoints:
 *   $admin = JWTAuth::getCurrentAdmin();
 *   if (!$admin) {
 *       error('Not authenticated', 401);
 *   }
 */
function getCurrentAdminFromToken(): ?array
{
    $payload = JWTAuth::verifyToken(JWTAuth::getTokenFromHeader() ?? '');
    return $payload;
}
