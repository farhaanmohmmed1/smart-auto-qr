# API Migration Guide: Session-Based to Serverless

This guide shows how to convert each existing endpoint from session-based authentication to JWT-based serverless functions.

---

## Overview of Changes

### Session-Based (Old - ML350)
```php
<?php
require_once '../config/config.php';
requireAdmin();  // Requires PHP session to be active

$data = [];
$result = $pdo->query("SELECT * FROM autos");
// ...
?>
<html>
  <form method="POST">
    <!-- Server-rendered HTML -->
  </form>
</html>
```

### JWT-Based (New - Vercel)
```php
<?php
require_once '../config/config.php';
require_once '../lib/JWTAuth.php';

setCORSHeaders();

$admin = JWTAuth::requireAuth(['admin', 'superadmin']);  // JWT required

$data = [];
$result = $pdo->query("SELECT * FROM autos");
// ...

sendJSON($data);  // JSON API response
```

**Key Differences:**
1. **Authentication:** Sessions → JWT tokens
2. **Responses:** HTML → JSON
3. **Endpoints:** Page routes → API endpoints
4. **Headers:** Form POST → JSON requests
5. **CORS:** Added for frontend/backend separation

---

## Migration Pattern

### Step 1: Add Headers

**All API files must start with:**
```php
<?php
require_once '../config/config.php';
require_once '../lib/JWTAuth.php';

setCORSHeaders();  // ← CRITICAL for Vercel

header('Content-Type: application/json');  // ← JSON responses
```

### Step 2: Replace Sessions with JWT

**Old:**
```php
requireAdmin();  // Requires $_SESSION['admin_id']
$adminId = $_SESSION['admin_id'];
$adminRole = $_SESSION['admin_role'];
```

**New:**
```php
$payload = JWTAuth::requireAuth();  // Requires JWT token
$adminId = $payload['sub'];
$adminRole = $payload['role'];
```

### Step 3: Return JSON Instead of HTML

**Old:**
```php
?>
<html><body>
  <h1>Dashboard</h1>
  <p>Active Autos: <?= $count ?></p>
</body></html>
```

**New:**
```php
sendJSON([
    'active_autos' => $count,
    'message' => 'Dashboard data'
]);
```

### Step 4: Update Error Handling

**Old:**
```php
if ($error) {
    $_SESSION['error'] = $error;
    redirect('login.php');
}
```

**New:**
```php
if ($error) {
    error('Invalid credentials', 401);  // Sends JSON + exits
}
```

---

## Convert: admin/login.php → api/admin/login.php

### Before (Server-rendered form)
```php
<?php
require_once '../config/config.php';

if (isAdmin()) {
    redirect('dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Username and password required';
    } else {
        $stmt = $pdo->prepare("SELECT id, password_hash, role FROM admins WHERE username = ?");
        $stmt->execute([$username]);
        $admin = $stmt->fetch();
        
        if ($admin && password_verify($password, $admin['password_hash'])) {
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_role'] = $admin['role'];
            redirect('dashboard.php');
        } else {
            $error = 'Invalid credentials';
        }
    }
}
?>
<!DOCTYPE html>
<html>
<body>
  <form method="POST">
    <input type="text" name="username" required>
    <input type="password" name="password" required>
    <button type="submit">Login</button>
  </form>
  <?php if (!empty($error)): ?>
    <p class="error"><?= e($error) ?></p>
  <?php endif ?>
</body>
</html>
```

### After (JSON API)
```php
<?php
require_once '../config/config.php';
require_once '../lib/JWTAuth.php';

setCORSHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Method not allowed', 405);
}

// Parse JSON request body
$input = json_decode(file_get_contents('php://input'), true);
$username = $input['username'] ?? '';
$password = $input['password'] ?? '';

// Validate input
if (empty($username) || empty($password)) {
    error('Username and password required', 400);
}

// Check rate limit
if (!checkRateLimit('login:' . $username, 5, 900)) {
    error('Too many login attempts. Try again later.', 429);
}

// Query database
$stmt = $pdo->prepare("
    SELECT id, username, password_hash, role, full_name 
    FROM admins 
    WHERE username = ? 
    LIMIT 1
");
$stmt->execute([$username]);
$admin = $stmt->fetch();

// Verify password
if (!$admin || !password_verify($password, $admin['password_hash'])) {
    recordLoginAttempt($username, getClientIP(), false);
    error('Invalid credentials', 401);
}

// ✅ Successful login
recordLoginAttempt($username, getClientIP(), true);

// Create JWT token (24-hour expiration)
$token = JWTAuth::createToken($admin['id'], $admin['username'], $admin['role']);

// Return token to frontend (will store in localStorage)
success([
    'token' => $token,
    'user' => [
        'id' => $admin['id'],
        'username' => $admin['username'],
        'full_name' => $admin['full_name'],
        'role' => $admin['role']
    ],
    'expires_in' => JWT_EXPIRY
]);
```

### Frontend Usage (JavaScript)
```javascript
// Login form
async function login(username, password) {
    const response = await fetch('/api/admin/login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ username, password })
    });
    
    const data = await response.json();
    
    if (data.success) {
        // Store token in browser localStorage
        localStorage.setItem('auth_token', data.token);
        localStorage.setItem('user', JSON.stringify(data.user));
        
        // Redirect to dashboard
        window.location.href = '/admin';
    } else {
        alert('Error: ' + data.error);
    }
}

// Get token for protected requests
function getAuthToken() {
    return localStorage.getItem('auth_token');
}

// Logout
function logout() {
    localStorage.removeItem('auth_token');
    localStorage.removeItem('user');
    window.location.href = '/';
}
```

---

## Convert: admin/dashboard.php → api/admin/dashboard.php

### Before (Server-rendered page)
```php
<?php
require_once '../config/config.php';
requireAdmin();

// Fetch stats
$stmt = $pdo->query("SELECT COUNT(*) as total_autos FROM autos WHERE status='active'");
$autos = $stmt->fetch()['total_autos'];

$stmt = $pdo->query("SELECT COUNT(*) as sos_today FROM sos_logs WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)");
$sos_today = $stmt->fetch()['sos_today'];
?>
<!DOCTYPE html>
<html>
<body>
  <h1>Dashboard</h1>
  <div class="stats">
    <p>Active Autos: <strong><?= $autos ?></strong></p>
    <p>SOS Today: <strong><?= $sos_today ?></strong></p>
  </div>
  <a href="manage.php">Manage Autos</a>
  <a href="logout.php">Logout</a>
</body>
</html>
```

### After (JSON API + Frontend SPA)

**api/admin/dashboard.php:**
```php
<?php
require_once '../config/config.php';
require_once '../lib/JWTAuth.php';

setCORSHeaders();

// Require admin authentication
$admin = JWTAuth::requireAuth(['admin', 'superadmin']);

// Fetch dashboard statistics
$stmt = $pdo->query("
    SELECT 
        (SELECT COUNT(*) FROM autos WHERE status='active') as active_autos,
        (SELECT COUNT(*) FROM autos WHERE status='inactive') as inactive_autos,
        (SELECT COUNT(*) FROM sos_logs WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 DAY) AND status='pending') as sos_pending,
        (SELECT COUNT(*) FROM sos_logs WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)) as sos_total,
        (SELECT COUNT(*) FROM scan_logs WHERE scanned_at > DATE_SUB(NOW(), INTERVAL 1 DAY)) as scans_today
    LIMIT 1
");
$stats = $stmt->fetch();

success([
    'user_id' => $admin['sub'],
    'role' => $admin['role'],
    'stats' => $stats,
    'timestamp' => date('Y-m-d H:i:s')
]);
```

**public/dashboard.html:**
```html
<!DOCTYPE html>
<html>
<head>
    <title>Dashboard</title>
    <style>
        .stats { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
        .stat-card { border: 1px solid #ddd; padding: 20px; border-radius: 8px; }
        .stat-number { font-size: 32px; font-weight: bold; }
    </style>
</head>
<body>
    <h1>Dashboard</h1>
    <div class="stats" id="stats"></div>
    <button onclick="logout()">Logout</button>

    <script>
        async function loadDashboard() {
            const token = localStorage.getItem('auth_token');
            if (!token) {
                window.location.href = '/';
                return;
            }
            
            const response = await fetch('/api/admin/dashboard', {
                headers: { 'Authorization': `Bearer ${token}` }
            });
            
            if (!response.ok) {
                if (response.status === 401) {
                    localStorage.removeItem('auth_token');
                    window.location.href = '/';
                }
                return;
            }
            
            const data = await response.json();
            
            // Render stats
            const html = `
                <div class="stat-card">
                    <div>Active Autos</div>
                    <div class="stat-number">${data.stats.active_autos}</div>
                </div>
                <div class="stat-card">
                    <div>SOS Today</div>
                    <div class="stat-number">${data.stats.sos_total}</div>
                </div>
                <div class="stat-card">
                    <div>Pending SOS</div>
                    <div class="stat-number">${data.stats.sos_pending}</div>
                </div>
                <div class="stat-card">
                    <div>Scans Today</div>
                    <div class="stat-number">${data.stats.scans_today}</div>
                </div>
            `;
            
            document.getElementById('stats').innerHTML = html;
        }
        
        function logout() {
            localStorage.removeItem('auth_token');
            window.location.href = '/';
        }
        
        // Load on page load
        window.addEventListener('load', loadDashboard);
        
        // Refresh every 30 seconds
        setInterval(loadDashboard, 30000);
    </script>
</body>
</html>
```

---

## Convert: public/auto.php → api/auto.php

**Public endpoints usually stay the same,** but ensure they:
1. Add `setCORSHeaders()`
2. Return JSON only
3. No HTML output

### Current (Good for Vercel)
```php
<?php
require_once '../config/config.php';

setCORSHeaders();  // ← Add this!
header('Content-Type: application/json');

$autoId = $_GET['id'] ?? '';

if (!$autoId) {
    error('Auto ID required', 400);
}

$stmt = $pdo->prepare("
    SELECT auto_number, reg_number, driver_name, phone, area, stand, status
    FROM autos
    WHERE auto_number = ?
    LIMIT 1
");
$stmt->execute([$autoId]);
$auto = $stmt->fetch();

if (!$auto) {
    error('Auto not found', 404);
}

if ($auto['status'] !== 'active') {
    error('Auto is ' . $auto['status'], 403);
}

success([
    'auto_number' => $auto['auto_number'],
    'reg_number' => $auto['reg_number'],
    'driver_name' => $auto['driver_name'],
    'phone_masked' => '***' . substr($auto['phone'], -4),
    'area' => $auto['area'],
    'stand' => $auto['stand']
]);
```

---

## Convert: QR Code Generation Page → API Endpoint

### Before (Server-generated page with inline QR)
```php
<?php
require_once '../config/config.php';

$autoId = $_GET['id'] ?? '';
if (!$autoId) die('Auto ID required');

$stmt = $pdo->prepare("SELECT id FROM autos WHERE auto_number = ?");
$stmt->execute([$autoId]);
$auto = $stmt->fetch();

if (!$auto) die('Auto not found');

require_once '../lib/QRGenerator.php';
$qrPath = QRGenerator::generate($autoId);
?>
<!DOCTYPE html>
<html>
<body>
  <img src="<?= $qrPath ?>" alt="QR" />
  <a href="javascript:window.print()">Print</a>
</body>
</html>
```

### After (API generates QR on-the-fly)

**api/qr.php:**
```php
<?php
require_once '../config/config.php';
require_once '../lib/QRGenerator.php';

setCORSHeaders();

$autoId = $_GET['id'] ?? '';

if (!$autoId) {
    http_response_code(400);
    exit('Auto ID required');
}

// Validate auto exists
$stmt = $pdo->prepare("SELECT id FROM autos WHERE auto_number = ? LIMIT 1");
$stmt->execute([$autoId]);
if (!$stmt->fetch()) {
    http_response_code(404);
    exit('Auto not found');
}

// Generate QR on-the-fly
header('Content-Type: image/png');
header('Cache-Control: public, max-age=86400');  // Cache 24 hours
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 86400) . ' GMT');

// Output PNG directly to browser
echo QRGenerator::generateImage($autoId, 300, 'png');
exit;
```

**Frontend usage:**
```html
<!-- Method 1: Direct img tag -->
<img src="/api/qr?id=AUTO-001" alt="QR Code" />

<!-- Method 2: Fetch with JavaScript -->
<script>
async function displayQR() {
    const response = await fetch('/api/qr?id=AUTO-001');
    const blob = await response.blob();
    const url = URL.createObjectURL(blob);
    document.getElementById('qr').src = url;
}
</script>
<img id="qr" />
```

---

## Migration Checklist by Endpoint

For each existing endpoint:

### Protected Endpoints (require login)
- [ ] Add `require_once '../lib/JWTAuth.php';`
- [ ] Replace `requireAdmin()` with `$admin = JWTAuth::requireAuth();`
- [ ] Return JSON via `success([$data])`
- [ ] Handle errors via `error($message, $code)`

### Public Endpoints (no auth)
- [ ] Add `setCORSHeaders()` at top
- [ ] Add `header('Content-Type: application/json');`
- [ ] Return JSON via `sendJSON([$data])`
- [ ] Handle errors via `error($message, $code)`

### File Serving (QR, PDF, etc.)
- [ ] Generate on-the-fly instead of saving
- [ ] Or use external service (Cloudinary, S3)
- [ ] Set appropriate `Content-Type` header
- [ ] Add `Cache-Control` header

### Redirects & Sessions
- [ ] Remove all `redirect()` calls
- [ ] Remove all `$_SESSION` usage
- [ ] Use JWT tokens instead of sessions
- [ ] Return JSON for all responses

---

## Testing Each Endpoint

### Test with cURL

```bash
# Public endpoint
curl https://localhost:3000/api/auto?id=AUTO-001

# Login
TOKEN=$(curl -X POST https://localhost:3000/api/admin/login \
  -H 'Content-Type: application/json' \
  -d '{"username":"admin","password":"pass"}' \
  | jq -r '.token')

# Protected endpoint
curl https://localhost:3000/api/admin/dashboard \
  -H "Authorization: Bearer $TOKEN"

# File (QR)
curl https://localhost:3000/api/qr?id=AUTO-001 --output qr.png
```

### Test with JavaScript

```javascript
// Fetch API (modern browsers)
const response = await fetch('/api/admin/dashboard', {
    headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json'
    }
});

const data = await response.json();
console.log(data);
```

---

## Common Pitfalls

| Problem | Solution |
|---------|----------|
| "CORS error" | Call `setCORSHeaders()` at start of every API file |
| "Unexpected token" | Ensure `Content-Type: application/json` header set |
| "Undefined keys" | Parse JSON: `$input = json_decode(..., true);` |
| "Token expired" | Check `JWT_EXPIRY` is set high enough, refresh logic |
| "401 unauthorized" | Ensure token in `Authorization: Bearer <token>` format |
| "Functions timeout" | Optimize queries, reduce operations, increase timeout |
| "Can't write to disk" | Use on-the-fly generation, not file storage |

---

## File Structure After Migration

```
api/
  admin/
    login.php          (✨ NEW - was: admin/login.php)
    register.php       (✨ NEW - was: admin/register.php)
    dashboard.php      (✨ NEW - was: admin/dashboard.php)
    auto-list.php      (✨ NEW - API, was admin/manage.php)
    auto-create.php    (✨ NEW - POST, was upload form)
    auto-update.php    (✨ NEW - PUT, was edit.php)
    auto-delete.php    (✨ NEW - DELETE, was delete.php)
  
  auto.php             (✅ UPDATED - adds CORS)
  sos.php              (✅ UPDATED - adds CORS)
  qr.php               (✨ NEW - generates QR on-the-fly)
  scan.php             (✨ NEW - log QR scans)

public/
  index.html           (✨ NEW - login page, SPA)
  dashboard.html       (✨ NEW - admin dashboard)
  auto.html            (✨ NEW - public auto details)
  sos.html             (✨ NEW - SOS form)
  assets/
    js/
      api.js           (✨ NEW - shared API client)
      auth.js          (✨ NEW - JWT management)
```

---

All endpoints migrated to JWT+Serverless? See [VERCEL_MIGRATION_GUIDE.md](VERCEL_MIGRATION_GUIDE.md) for deployment.
