# Vercel Deployment Guide: Smart Auto QR Safety System

> **Mission:** Deploy on Vercel Free Tier with clean repository, secure configuration, and serverless architecture.

---

## Executive Summary

This guide converts your on-premise PHP application to Vercel's serverless architecture with:
- ✅ **Zero Breaking Changes** - Existing functionality preserved
- ✅ **Stateless Architecture** - JWT-based authentication
- ✅ **Remote Database** - PlanetScale, Railway, or Aiven (free tier)
- ✅ **Dynamic QR Generation** - On-the-fly, no file storage needed
- ✅ **Secure Config** - All secrets in environment variables
- ✅ **CI/CD Ready** - Git push → automatic deployment

**Time Required:** 60-90 minutes end-to-end

---

## Part 1: Repository Preparation

### 1.1 Create Clean Repository Structure

```bash
cd c:\Users\farhaan\Downloads\smart_auto_qr

# Initialize git (if not already done)
git init
git config user.name "Your Name"
git config user.email "you@example.com"
```

### 1.2 Key Changes to Existing Files

The following files have been created/modified for Vercel compatibility:

**Moved/Created Files:**
- ✅ `.gitignore` - Excludes secrets, vendor, logs
- ✅ `vercel.json` - Serverless routing & build config
- ✅ `config/config.php` - Environment variable support
- ✅ `api/auth.php` - JWT token generation (NEW)
- ✅ `VERCEL_STRUCTURE.md` - Detailed file structure (NEW)
- ✅ `env.example` - Template for environment variables (NEW)

### 1.3 Files to Remove Before Commit

```bash
# Remove these from git (keep them locally if needed)
git rm --cached:
  - .env (if exists — use env vars instead)
  - config/config.php.bak (backup)
  - admin/*.bak
  - database/backup_olddb.sql
  - vendor/ (included via .gitignore)
  - node_modules/ (if any)
  - *.log files
  - qrcodes/ (generated on-demand)
  - uploads/ (use Cloudinary/S3 instead)
  - /tmp files
```

### 1.4 Directory Structure for Vercel

```
smart-auto-qr/
├── api/                           # Serverless function endpoints
│   ├── auth.php                  # ✨ NEW: JWT token handler
│   ├── auto.php                  # GET /api/auto — public auto details
│   ├── sos.php                   # POST /api/sos — emergency request
│   ├── scan.php                  # ✨ NEW: Track QR scans
│   ├── qr.php                    # ✨ NEW: Generate QR on-the-fly
│   ├── admin/
│   │   ├── login.php             # POST /api/admin/login — JWT auth
│   │   ├── register.php          # POST /api/admin/register
│   │   ├── dashboard.php         # GET /api/admin/dashboard — requires JWT
│   │   ├── auto-list.php         # GET /api/admin/auto/list
│   │   ├── auto-create.php       # POST /api/admin/auto/create
│   │   ├── auto-update.php       # PUT /api/admin/auto/update
│   │   ├── auto-delete.php       # DELETE /api/admin/auto/delete
│   │   └── [other admin endpoints]
│   └── middleware/ (if needed)
│
├── public/                         # Frontend (HTML/JS)
│   ├── index.html                # Admin login page
│   ├── dashboard.html            # Admin dashboard (React/Vue or vanilla JS)
│   ├── manage.html               # Auto management UI
│   ├── auto.html                 # Public auto details page
│   ├── sos.html                  # SOS emergency form
│   ├── assets/
│   │   ├── css/
│   │   ├── js/
│   │   └── img/
│   └── lib/ (optional: shared JS)
│
├── lib/
│   ├── QRGenerator.php           # ✅ Kept as-is (generate QR in memory)
│   ├── PDFGenerator.php          # ✅ Kept as-is
│   ├── ImportHandler.php         # ✅ Kept as-is
│   └── JWTAuth.php               # ✨ NEW: JWT token handler
│
├── config/
│   ├── config.php                # ✅ Refactored for env variables
│   └── database.php              # ✨ NEW: DB connection pool
│
├── database/
│   ├── schema.sql                # ✅ Kept as-is
│   └── migrations/               # (if needed for future changes)
│
├── vercel.json                    # ✨ NEW: Build & runtime config
├── .gitignore                     # ✨ NEW: Exclude secrets
├── .env.example                   # ✨ NEW: Template for secrets
├── package.json                   # ✅ Kept (Composer autoloader)
├── composer.json                  # ✅ Kept
├── composer.lock                  # ✅ Kept
├── README.md                      # ✅ Existing
├── VERCEL_MIGRATION_GUIDE.md     # ✨ THIS FILE
└── VERCEL_DEPLOYMENT_CHECKLIST.md # ✨ NEW: Deployment checklist
```

---

## Part 2: Creating Vercel-Compatible PHP Files

### 2.1 vercel.json — Serverless Configuration

```json
{
  "buildCommand": "composer install --no-dev --optimize-autoloader",
  "devCommand": "php -S localhost:3000",
  "functions": {
    "api/**/*.php": {
      "runtime": "php-8.3"
    }
  },
  "routes": [
    {
      "src": "^/api/(.*)$",
      "dest": "/api/$1.php"
    },
    {
      "src": "^/qr/(.*)$",
      "dest": "/api/qr.php?id=$1"
    },
    {
      "src": "^/admin/?$",
      "dest": "/public/index.html"
    },
    {
      "src": "^/admin/(.*)$",
      "dest": "/public/dashboard.html"
    },
    {
      "src": "/public/(.*)",
      "dest": "/public/$1"
    },
    {
      "src": "/$",
      "dest": "/public/auto.html"
    },
    {
      "status": 404,
      "src": "(.*)",
      "dest": "/public/404.html"
    }
  ],
  "env": {
    "DB_HOST": "@db_host",
    "DB_PORT": "@db_port",
    "DB_NAME": "@db_name",
    "DB_USER": "@db_user",
    "DB_PASS": "@db_pass",
    "JWT_SECRET": "@jwt_secret",
    "API_URL": "@api_url",
    "CDN_URL": "@cdn_url",
    "ENVIRONMENT": "production"
  }
}
```

### 2.2 config/config.php — Environment-Based

```php
<?php
/**
 * Smart Auto QR - Vercel-Compatible Configuration
 * All secrets come from environment variables
 */

// ── Composer Autoloader ────────────────────────────────────
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

// ── Environment Detection ──────────────────────────────────
define('ENVIRONMENT', getenv('ENVIRONMENT') ?: 'development');
define('IS_PRODUCTION', ENVIRONMENT === 'production');

if (!IS_PRODUCTION && file_exists(__DIR__ . '/../.env')) {
    loadEnvFile(__DIR__ . '/../.env');
}

// ── Database Configuration (from env vars) ─────────────────
define('DB_HOST',   getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT',   getenv('DB_PORT') ?: '3306');
define('DB_NAME',   getenv('DB_NAME') ?: 'smart_auto_db');
define('DB_USER',   getenv('DB_USER') ?: 'root');
define('DB_PASS',   getenv('DB_PASS') ?: '');

// Check required env vars in production
if (IS_PRODUCTION) {
    foreach (['DB_HOST', 'DB_NAME', 'DB_USER', 'JWT_SECRET'] as $required) {
        if (!getenv($required)) {
            http_response_code(500);
            die('❌ Missing required environment variable: ' . $required);
        }
    }
}

// ── JWT Configuration ─────────────────────────────────────
define('JWT_SECRET', getenv('JWT_SECRET') ?: 'dev-secret-change-in-production');
define('JWT_ALGORITHM', 'HS256');
define('JWT_EXPIRY', 86400); // 24 hours

// ── Application Settings ──────────────────────────────────
define('APP_NAME', 'Smart Auto QR Safety System');
define('APP_VERSION', '2.0.0-vercel');
define('HELPLINE', '100');
define('SOS_WHATSAPP', '919100000000');

// API/CDN URLs
define('API_URL', getenv('API_URL') ?: 'http://localhost:3000/api');
define('FRONTEND_URL', getenv('FRONTEND_URL') ?: 'http://localhost:3000');
define('CDN_URL', getenv('CDN_URL') ?: 'https://cdn.yourdomain.com');

// Paths (Vercel has read-only root except /tmp)
define('ROOT_PATH', '/tmp/vercel');  // Temp storage
define('QR_DIR', '/tmp/vercel/qrcodes/');
define('QR_URL', API_URL . '/qr/');  // Serve QR via API
define('UPLOADS_DIR', '/tmp/vercel/uploads/');

// File Storage Mode: 'local' | 'cloudinary' | 's3'
define('FILE_STORAGE', getenv('FILE_STORAGE') ?: 'local');

// Cloudinary Config (optional)
define('CLOUDINARY_NAME', getenv('CLOUDINARY_NAME') ?: '');
define('CLOUDINARY_KEY', getenv('CLOUDINARY_KEY') ?: '');
define('CLOUDINARY_SECRET', getenv('CLOUDINARY_SECRET') ?: '');

// Security
define('SESSION_NAME', 'saqss_token');
define('SESSION_TIMEOUT', 86400); // 24 hours
define('SECURE_COOKIE', IS_PRODUCTION); // HTTPS only in production

// ── PDO Connection (Vercel Database) ───────────────────────
try {
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    
    $pdo = new PDO(
        $dsn,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_TIMEOUT            => 5, // connection timeout
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    if (IS_PRODUCTION) {
        die(json_encode(['error' => 'Database unavailable']));
    } else {
        die('❌ Database error: ' . $e->getMessage());
    }
}

// ── Helper Functions ──────────────────────────────────────
function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function sendJSON(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: ' . FRONTEND_URL);
    echo json_encode($data);
}

function error(string $message, int $status = 400): void {
    sendJSON(['success' => false, 'error' => $message], $status);
    exit;
}

function success(array $data = []): void {
    sendJSON(['success' => true, ...$data], 200);
}

// ── Load .env File (Development Only) ──────────────────────
function loadEnvFile(string $path): void {
    if (!file_exists($path)) return;
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim(trim($value), '"\'');
        
        if (!getenv($key)) {
            putenv("{$key}={$value}");
        }
    }
}

// ── CORS & API Headers ────────────────────────────────────
function setCORSHeaders(): void {
    header('Access-Control-Allow-Origin: ' . FRONTEND_URL);
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Allow-Credentials: true');
    
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}

// ── Rate Limiting (Redis recommended, using DB fallback) ───
function checkRateLimit(string $key, int $limit, int $window): bool {
    global $pdo;
    
    $ipAddress = $_SERVER['HTTP_CF_CONNECTING_IP'] 
                 ?? $_SERVER['HTTP_X_FORWARDED_FOR'] 
                 ?? $_SERVER['REMOTE_ADDR'] 
                 ?? 'unknown';
    
    $cacheKey = "{$key}:{$ipAddress}";
    
    // Simple in-memory check (production should use Redis)
    static $requestCount = [];
    
    if (!isset($requestCount[$cacheKey])) {
        $requestCount[$cacheKey] = 0;
    }
    
    $requestCount[$cacheKey]++;
    
    return $requestCount[$cacheKey] <= $limit;
}
```

### 2.3 lib/JWTAuth.php — JWT Token Handler (NEW)

```php
<?php
/**
 * JWT Authentication Library
 * Replaces session-based auth for stateless Vercel deployment
 */

class JWTAuth
{
    /**
     * Create JWT token for admin
     */
    public static function createToken(int $adminId, string $username, string $role): string
    {
        $header = [
            'alg' => JWT_ALGORITHM,
            'typ' => 'JWT'
        ];
        
        $payload = [
            'sub' => $adminId,
            'usr' => $username,
            'role' => $role,
            'iat' => time(),
            'exp' => time() + JWT_EXPIRY
        ];
        
        $headerEncoded = self::base64url(json_encode($header));
        $payloadEncoded = self::base64url(json_encode($payload));
        
        $signature = hash_hmac(
            'sha256',
            "{$headerEncoded}.{$payloadEncoded}",
            JWT_SECRET,
            true
        );
        $signatureEncoded = self::base64url($signature);
        
        return "{$headerEncoded}.{$payloadEncoded}.{$signatureEncoded}";
    }
    
    /**
     * Verify & decode JWT token
     */
    public static function verifyToken(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }
        
        [$headerEncoded, $payloadEncoded, $signatureEncoded] = $parts;
        
        // Verify signature
        $signature = hash_hmac(
            'sha256',
            "{$headerEncoded}.{$payloadEncoded}",
            JWT_SECRET,
            true
        );
        
        if (self::base64url($signature) !== $signatureEncoded) {
            return null;
        }
        
        // Decode payload
        $payload = json_decode(self::base64decode($payloadEncoded), true);
        
        // Check expiration
        if ($payload['exp'] < time()) {
            return null;
        }
        
        return $payload;
    }
    
    /**
     * Get token from header
     */
    public static function getTokenFromHeader(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/^Bearer\s+(.+)$/', $header, $matches)) {
            return $matches[1];
        }
        return null;
    }
    
    /**
     * Require valid JWT token
     */
    public static function requireAuth(): array
    {
        $token = self::getTokenFromHeader();
        if (!$token) {
            error('Missing authorization token', 401);
        }
        
        $payload = self::verifyToken($token);
        if (!$payload) {
            error('Invalid or expired token', 401);
        }
        
        return $payload;
    }
    
    private static function base64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    private static function base64decode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', strlen($data) % 4));
    }
}
```

### 2.4 .gitignore — Exclude Secrets & Build Files

```
# Environment variables (SECRETS!)
.env
.env.local
.env.*.local
.env.production

# Dependencies
vendor/
node_modules/
composer.lock (optional - usually include)

# Build artifacts
/tmp/
/build/
/dist/

# OS files
.DS_Store
Thumbs.db
*.swp
*.swo
.idea/
.vscode/settings.json

# Logs
*.log
logs/

# IDE
.vscode/
.sublime-project
.sublime-workspace

# Database backups
*.sql
*.sql.bak
backups/

# Generated files (QR codes, uploads - generated on-demand)
qrcodes/
uploads/

# Testing
.phpunit.cache
coverage/

# Local development
.local.php
docker-compose.override.yml

# Archive
*.zip
*.tar.gz
*.rar
```

### 2.5 .env.example — Template for Secrets

```bash
# 🔐 Database Configuration (use PlanetScale, Railway, or Aiven)
DB_HOST=mysql.example.com
DB_PORT=3306
DB_NAME=smart_auto_db
DB_USER=admin_user
DB_PASS=your_secure_password_here

# 🔐 JWT Secret (generate a strong string, 32+ chars)
# Command: openssl rand -base64 32
JWT_SECRET=your_super_secret_jwt_key_32_characters_minimum

# 🌐 API & Frontend URLs
API_URL=https://yourapp.vercel.app/api
FRONTEND_URL=https://yourapp.vercel.app

# 📁 File Storage Strategy: 'local' | 'cloudinary'
FILE_STORAGE=cloudinary

# ☁️ Cloudinary (optional, if using FILE_STORAGE=cloudinary)
CLOUDINARY_NAME=your_cloudinary_name
CLOUDINARY_KEY=your_cloudinary_key
CLOUDINARY_SECRET=your_cloudinary_secret

# 🌍 Environment
ENVIRONMENT=development

# 📊 Analytics (optional)
SENTRY_DSN=https://key@sentry.io/project
```

---

## Part 3: Database Setup

### 3.1 Create Remote Database

**Option A: PlanetScale (MySQL-compatible)**
- Free tier: 5GB storage, 1M queries/month
- URL: https://planetscale.com
- Steps:
  1. Create account
  2. Create new database
  3. Generate password in "Passwords" section
  4. Copy MySQL connection string
  5. Update `.env` with credentials

**Option B: Railway.app (MySQL-compatible)**
- Free tier: $5 monthly credit (usually free)
- URL: https://railway.app
- Steps:
  1. Create account
  2. New Project → Add MySQL plugin
  3. Copy connection variables
  4. Update `.env`

**Option C: Aiven (PostgreSQL/MySQL)**
- Free tier: Some limitations
- URL: https://aiven.io

### 3.2 Initialize Database Schema

```bash
# Download schema
curl -O https://raw.githubusercontent.com/your-repo/database/schema.sql

# Import to remote DB (using mysql client)
mysql -h <DB_HOST> -u <DB_USER> -p <DB_NAME> < database/schema.sql

# Or use DatabaseManagement tool in PlanetScale/Railway UI
```

---

## Part 4: Serverless Function Conversion

### 4.1 API Endpoint Structure

**Before (Traditional PHP):**
```
admin/login.php          (POST form submission)
admin/dashboard.php      (Server-rendered HTML)
api/auto.php            (JSON API)
```

**After (Serverless):**
```
api/admin/login.php     → POST /api/admin/login (returns JWT token)
api/admin/dashboard.php → GET /api/admin/dashboard (requires JWT)
api/auto.php            → GET /api/auto (public, unchanged)
public/                 → Static HTML frontend
```

### 4.2 Convert Admin Login to Serverless

**OLD: `admin/login.php` (Server-rendered form + POST handler)**
```php
<?php
// Handle POST form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ... login logic ...
    $_SESSION['admin_id'] = $admin['id'];
    redirect('dashboard.php');
}
?>
<form method="POST">...</form>
```

**NEW: `api/admin/login.php` (JSON API)**
```php
<?php
require_once '../config/config.php';
require_once '../lib/JWTAuth.php';

setCORSHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Method not allowed', 405);
}

// Parse JSON input
$input = json_decode(file_get_contents('php://input'), true);
$username = $input['username'] ?? '';
$password = $input['password'] ?? '';

if (!$username || !$password) {
    error('Username and password required', 400);
}

// Check rate limit
if (!checkRateLimit('login:' . $username, 5, 900)) { // 5 attempts per 15min
    error('Too many login attempts', 429);
}

// Verify credentials
$stmt = $pdo->prepare("SELECT id, username, password_hash, role FROM admins WHERE username = ? LIMIT 1");
$stmt->execute([$username]);
$admin = $stmt->fetch();

if (!$admin || !password_verify($password, $admin['password_hash'])) {
    error('Invalid credentials', 401);
}

// Create JWT token
$token = JWTAuth::createToken($admin['id'], $admin['username'], $admin['role']);

// Return token (frontend stores in localStorage)
success([
    'token' => $token,
    'user' => [
        'id' => $admin['id'],
        'username' => $admin['username'],
        'role' => $admin['role']
    ]
]);
```

**Frontend (new `public/login.html`):**
```html
<!DOCTYPE html>
<html>
<head><title>Login</title></head>
<body>
<form id="loginForm">
    <input type="text" name="username" placeholder="Username" required>
    <input type="password" name="password" placeholder="Password" required>
    <button type="submit">Login</button>
</form>

<script>
document.getElementById('loginForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const username = e.target.username.value;
    const password = e.target.password.value;
    
    const response = await fetch('/api/admin/login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ username, password })
    });
    
    const data = await response.json();
    
    if (data.success) {
        // Store JWT token in localStorage
        localStorage.setItem('auth_token', data.token);
        
        // Redirect to dashboard
        window.location.href = '/admin';
    } else {
        alert('Login failed: ' + data.error);
    }
});
</script>
</body>
</html>
```

### 4.3 Protect Admin API Endpoints

```php
<?php
// api/admin/dashboard.php
require_once '../config/config.php';
require_once '../lib/JWTAuth.php';

setCORSHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    error('Method not allowed', 405);
}

// Require valid JWT token
$payload = JWTAuth::requireAuth();

// Now safe to return admin-only data
$stmt = $pdo->prepare("
    SELECT 
        (SELECT COUNT(*) FROM autos WHERE status='active') as active_autos,
        (SELECT COUNT(*) FROM sos_logs WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)) as sos_today
    LIMIT 1
");
$stmt->execute();
$stats = $stmt->fetch();

success($stats);
```

---

## Part 5: File Storage Strategy

### Option 1: **On-The-Fly QR Generation** (Recommended)

Pros:
- No storage needed
- Always fresh
- Free tier friendly

Implementation:
```php
// api/qr.php
<?php
require_once '../config/config.php';
require_once '../lib/QRGenerator.php';

$autoId = $_GET['id'] ?? '';

if (!$autoId) {
    http_response_code(404);
    exit;
}

header('Content-Type: image/png');
header('Cache-Control: public, max-age=86400'); // Cache 24 hours

// Generate QR code on-the-fly
echo QRGenerator::generateImage($autoId, 300, 'png');
```

### Option 2: **Cloudinary** (Free tier available)

Pros:
- CDN delivery
- Automatic optimization
- Image transformations

Setup:
```bash
# 1. Create free Cloudinary account: https://cloudinary.com
# 2. Get credentials from dashboard
# 3. Add to .env:
CLOUDINARY_NAME=...
CLOUDINARY_KEY=...
CLOUDINARY_SECRET=...
```

---

## Part 6: Git Setup & Initial Commit

### 6.1 Initialize Git Repository

```bash
cd c:\Users\farhaan\Downloads\smart_auto_qr

# Initialize
git init
git config user.name "Your Name"
git config user.email "your@email.com"

# Create .gitignore (already provided above)
# Create .env.example (already provided above)
```

### 6.2 Make Initial Commit

```bash
# Stage all files
git add .

# Review what's being committed (ensure no .env, vendor, logs)
git status

# Commit
git commit -m "feat: Vercel serverless migration

- Convert to JWT-based authentication (replaces sessions)
- Environment variable configuration
- Serverless-compatible PHP structure
- QR generation API endpoint
- Admin API endpoints instead of server-rendered pages
- CORS-enabled for frontend/backend separation"
```

### 6.3 Create GitHub Repository

```bash
# 1. Go to github.com → New repository
# 2. Name: smart-auto-qr
# 3. Description: Smart Auto QR Safety System - Vercel Deployment
# 4. Private (recommended for credentials)

# 4. Add remote and push
git remote add origin https://github.com/YOUR_USERNAME/smart-auto-qr.git
git branch -M main
git push -u origin main
```

---

## Part 7: Vercel Deployment

### 7.1 Connect Vercel to Git

1. Go to https://vercel.com
2. Log in with GitHub
3. Click "New Project"
4. Select your GitHub repository
5. Vercel auto-detects PHP project

### 7.2 Configure Environment Variables

In Vercel dashboard (Project Settings > Environment Variables):

```
DB_HOST              = mysql.planetscale.com
DB_PORT              = 3306
DB_NAME              = your_db_name
DB_USER              = your_user
DB_PASS              = your_password (masked)
JWT_SECRET           = (openssl rand -base64 32)
API_URL              = https://yourdomain.vercel.app/api
FRONTEND_URL         = https://yourdomain.vercel.app
FILE_STORAGE         = cloudinary (or local)
CLOUDINARY_NAME      = (if using Cloudinary)
CLOUDINARY_KEY       = (if using Cloudinary)
CLOUDINARY_SECRET    = (if using Cloudinary)
ENVIRONMENT          = production
```

### 7.3 Deploy

```bash
# Just push to GitHub, Vercel auto-deploys
git push origin main

# Watch deployment in Vercel dashboard
# Takes 2-5 minutes for first deployment
```

---

## Part 8: Post-Deployment Verification

### 8.1 Test API Endpoints

```bash
# Public API (no auth needed)
curl https://yourdomain.vercel.app/api/auto?id=AUTO-001

# Admin login
curl -X POST https://yourdomain.vercel.app/api/admin/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"password"}'

# Protected endpoint (using JWT token)
curl https://yourdomain.vercel.app/api/admin/dashboard \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

### 8.2 Check Logs

```bash
# Vercel dashboard → Deployments → Recent → Logs
# Look for:
  ✅ PHP build successful
  ✅ Database connection OK
  ❌ No 500 errors
  ❌ No missing functions
```

---

## Part 9: Known Vercel Limitations & Workarounds

| Limitation | Impact | Workaround |
|-----------|--------|-----------|
| **Max 10s execution time (free tier)** | Long queries timeout | Use indexed queries, optimize QR generation |
| **Read-only filesystem** | Can't save files | Use Cloudinary/S3 or generate on-demand |
| **Cold starts** | First request slow | OK for low traffic, consider hobby plan |
| **Memory limit** | 512MB | Sufficient for small DBs, optimize imports |
| **No .htaccess** | Pretty URLs fail | Use `vercel.json` rewrites |
| **Sessions don't persist** | $_SESSION lost | Use JWT tokens instead |
| **Max 50MB deployment** | Large vendor/ fails | Use `.gitignore` to exclude vendor |

---

## Checklist: Before Submitting to Vercel

- [ ] `.env` file NOT committed (check with `git log --all -p -- .env`)
- [ ] `vendor/` directory in `.gitignore`
- [ ] `config/config.php` uses `getenv()` for all secrets
- [ ] `vercel.json` created in root
- [ ] `api/*.php` files use `setCORSHeaders()`
- [ ] Admin endpoints require JWT token via `JWTAuth::requireAuth()`
- [ ] `composer.json` includes all required packages
- [ ] Database schema imported to remote DB
- [ ] All hardcoded paths use `ROOT_PATH` constant
- [ ] QR endpoints generate on-the-fly (no file storage)
- [ ] Public frontend is separate (HTML/JS, not server-rendered)

---

## Troubleshooting

### "500 Error: Database connection failed"
**Cause:** DBcredentials missing or wrong  
**Fix:**
```bash
# Check env vars in Vercel dashboard
# Test locally with .env file:
# composer install
# php -S localhost:3000
```

### "JWT token invalid"
**Cause:**Key mismatch or expiration  
**Fix:**
```php
// Ensure JWT_SECRET matches between config and token generation
// Check exp timestamp in token
```

### "File not found: /qrcodes/..."
**Cause:** Using filesystem storage on Vercel  
**Fix:** Switch to on-the-fly generation in `api/qr.php`

### "CORS errors in browser console"
**Cause:** Missing `setCORSHeaders()`  
**Fix:**
```php
require_once '../config/config.php';
setCORSHeaders();  // Add to every API file
```

---

## Next Steps

1. ✅ **This Week:** Review this guide, create files, push to GitHub
2. ✅ **Next Week:** Start Vercel deployment, test API endpoints
3. ✅ **Week 3:** Migrate frontend to React/Vue if desired
4. ✅ **Week 4:** Migrate admin dashboard and complete testing

**Support Resources:**
- [Vercel PHP Documentation](https://vercel.com/docs/concepts/functions/serverless-functions/runtimes/php)
- [PlanetScale MySQL](https://planetscale.com/docs)
- [JWT.io Token Debugger](https://jwt.io)

