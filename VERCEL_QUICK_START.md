# Vercel Quick Start: Copy-Paste Implementation

Fast track to get Smart Auto QR running on Vercel. Follow these examples in order.

---

## 1️⃣ Project Setup (5 minutes)

### Initialize Git Repository
```bash
cd c:\Users\farhaan\Downloads\smart_auto_qr

# Initialize git
git init
git config user.name "Your Name"
git config user.email "you@example.com"

# Add all files
git add .

# Review what's being committed
git status

# Commit
git commit -m "feat: Vercel serverless migration with JWT auth"

# Create GitHub repo at https://github.com/new
# Then push
git remote add origin https://github.com/YOUR_USERNAME/smart-auto-qr.git
git branch -M main
git push -u origin main
```

### Create Local .env File
```bash
cp .env.example .env

# Edit .env with your values:
# DB_HOST=localhost (for testing)
# JWT_SECRET=your_secret_here
# ENVIRONMENT=development
```

---

## 2️⃣ Database Setup (10 minutes)

### Create PlanetScale Database

1. Go to https://planetscale.com
2. Sign up (free account)
3. Create new database "smart-auto-qr"
4. Click "Passwords" → Create new password
5. Copy MySQL connection string

### Import Schema
```bash
# Using mysql client
mysql -h <host> -u <user> -p <database> < database/schema.sql

# Or paste into PlanetScale UI:
# Branches → Main → Browse → Run SQL → paste schema.sql
```

### Test Connection
```bash
# Verify tables created
mysql -h <host> -u <user> -p <database> -e "SHOW TABLES;"
```

---

## 3️⃣ Convert First API Endpoint (10 minutes)

### Copy-Paste: api/admin/login.php

Create file: `api/admin/login.php`

```php
<?php
require_once '../config/config.php';
require_once '../lib/JWTAuth.php';

setCORSHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Method not allowed', 405);
}

// Parse JSON input
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$username = $input['username'] ?? '';
$password = $input['password'] ?? '';

// Validate
if (empty($username) || empty($password)) {
    error('Username and password required', 400);
}

// Rate limit
if (!checkRateLimit('login:' . $username, 5, 900)) {
    error('Too many login attempts', 429);
}

// Query admin
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
    error('Invalid credentials', 401);
}

// ✅ Success - create JWT token
$token = JWTAuth::createToken($admin['id'], $admin['username'], $admin['role']);

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

### Test Locally
```bash
# Start PHP dev server
php -S localhost:3000

# Test login (in another terminal)
curl -X POST http://localhost:3000/api/admin/login \
  -H 'Content-Type: application/json' \
  -d '{"username":"admin","password":"password123"}'

# Response should be:
# {"success":true,"token":"eyJ...","user":{...}}
```

---

## 4️⃣ Create Login Frontend (15 minutes)

### Copy-Paste: public/index.html

Create file: `public/index.html`

```html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Auto QR - Login</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            padding: 40px;
            width: 100%;
            max-width: 400px;
        }
        h1 {
            color: #333;
            margin-bottom: 30px;
            text-align: center;
            font-size: 24px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
        }
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        input[type="text"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #667eea;
        }
        button {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }
        button:hover {
            transform: translateY(-2px);
        }
        button:active {
            transform: translateY(0);
        }
        .message {
            margin-top: 15px;
            padding: 12px;
            border-radius: 6px;
            text-align: center;
            display: none;
        }
        .message.success {
            background: #d4edda;
            color: #155724;
            display: block;
        }
        .message.error {
            background: #f8d7da;
            color: #721c24;
            display: block;
        }
        .loading {
            display: none;
            text-align: center;
            color: #667eea;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h1>🚕 Smart Auto QR</h1>
        
        <form id="loginForm">
            <div class="form-group">
                <label for="username">Username</label>
                <input 
                    type="text" 
                    id="username" 
                    name="username" 
                    required
                    placeholder="Enter your username"
                    autocomplete="off"
                >
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    required
                    placeholder="Enter your password"
                    autocomplete="off"
                >
            </div>
            
            <button type="submit" id="submitBtn">Login</button>
            
            <div class="loading" id="loading">Logging in...</div>
            <div class="message" id="message"></div>
        </form>
    </div>

    <script>
        // Configuration
        const API_URL = window.location.origin + '/api';

        // Handle form submission
        document.getElementById('loginForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;
            const submitBtn = document.getElementById('submitBtn');
            const loading = document.getElementById('loading');
            const message = document.getElementById('message');
            
            // Show loading
            submitBtn.disabled = true;
            loading.style.display = 'block';
            message.style.display = 'none';
            
            try {
                // Call API
                const response = await fetch(API_URL + '/admin/login', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ username, password })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Store token & user info
                    localStorage.setItem('auth_token', data.token);
                    localStorage.setItem('user', JSON.stringify(data.user));
                    
                    // Show success message
                    message.textContent = '✅ Login successful! Redirecting...';
                    message.classList.remove('error');
                    message.classList.add('success');
                    message.style.display = 'block';
                    
                    // Redirect to dashboard
                    setTimeout(() => {
                        window.location.href = '/dashboard.html';
                    }, 1000);
                } else {
                    // Show error
                    message.textContent = '❌ ' + (data.error || 'Login failed');
                    message.classList.remove('success');
                    message.classList.add('error');
                    message.style.display = 'block';
                }
            } catch (error) {
                message.textContent = '❌ Connection error: ' + error.message;
                message.classList.remove('success');
                message.classList.add('error');
                message.style.display = 'block';
            } finally {
                // Reset UI
                loading.style.display = 'none';
                submitBtn.disabled = false;
            }
        });

        // If already logged in, redirect to dashboard
        window.addEventListener('load', () => {
            if (localStorage.getItem('auth_token')) {
                window.location.href = '/dashboard.html';
            }
        });
    </script>
</body>
</html>
```

Test:
```bash
# Visit http://localhost:3000
# Try login with: admin / password123
```

---

## 5️⃣ Create Dashboard API (5 minutes)

### Copy-Paste: api/admin/dashboard.php

```php
<?php
require_once '../config/config.php';
require_once '../lib/JWTAuth.php';

setCORSHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    error('Method not allowed', 405);
}

// Require JWT token
$admin = JWTAuth::requireAuth();

// Fetch statistics
$stats = $pdo->query("
    SELECT 
        (SELECT COUNT(*) FROM autos WHERE status='active') as active_autos,
        (SELECT COUNT(*) FROM autos WHERE status='inactive') as inactive_autos,
        (SELECT COUNT(*) FROM sos_logs WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 DAY) AND status='pending') as sos_pending,
        (SELECT COUNT(*) FROM sos_logs WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)) as sos_today,
        (SELECT COUNT(*) FROM scan_logs WHERE scanned_at > DATE_SUB(NOW(), INTERVAL 1 DAY)) as scans_today
    LIMIT 1
")->fetch();

success([
    'user' => $admin,
    'stats' => $stats,
    'timestamp' => date('Y-m-d H:i:s')
]);
```

---

## 6️⃣ Create Dashboard Frontend (10 minutes)

### Copy-Paste: public/dashboard.html

```html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Smart Auto QR</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f5f7fa;
            color: #333;
        }
        .navbar {
            background: white;
            padding: 20px 40px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .navbar h1 { font-size: 20px; color: #667eea; }
        .navbar button {
            background: #e74c3c;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
        }
        .container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        .stat-card {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-left: 4px solid #667eea;
        }
        .stat-label { color: #999; font-size: 14px; margin-bottom: 10px; }
        .stat-value { font-size: 32px; font-weight: bold; color: #333; }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>📊 Dashboard</h1>
        <button onclick="logout()">Logout</button>
    </div>

    <div class="container">
        <div id="error" class="error" style="display:none;"></div>
        
        <div class="stats-grid" id="stats">
            <div class="stat-card">
                <div class="stat-label">Loading...</div>
            </div>
        </div>
    </div>

    <script>
        const API_URL = window.location.origin + '/api';

        async function loadDashboard() {
            const token = localStorage.getItem('auth_token');
            
            if (!token) {
                window.location.href = '/';
                return;
            }

            try {
                const response = await fetch(API_URL + '/admin/dashboard', {
                    headers: {
                        'Authorization': `Bearer ${token}`
                    }
                });

                if (!response.ok) {
                    if (response.status === 401) {
                        localStorage.removeItem('auth_token');
                        window.location.href = '/';
                        return;
                    }
                    throw new Error('Failed to load dashboard');
                }

                const data = await response.json();
                
                if (!data.success) {
                    throw new Error(data.error || 'Failed to load');
                }

                displayStats(data.stats);
            } catch (error) {
                document.getElementById('error').textContent = '❌ ' + error.message;
                document.getElementById('error').style.display = 'block';
            }
        }

        function displayStats(stats) {
            const html = `
                <div class="stat-card">
                    <div class="stat-label">🚕 Active Autos</div>
                    <div class="stat-value">${stats.active_autos}</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">🚨 SOS Today</div>
                    <div class="stat-value">${stats.sos_today}</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">⏳ Pending SOS</div>
                    <div class="stat-value">${stats.sos_pending}</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">📱 Scans Today</div>
                    <div class="stat-value">${stats.scans_today}</div>
                </div>
            `;
            document.getElementById('stats').innerHTML = html;
        }

        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                localStorage.removeItem('auth_token');
                localStorage.removeItem('user');
                window.location.href = '/';
            }
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

## 7️⃣ Test Locally (5 minutes)

```bash
# Start dev server
php -S localhost:3000

# Open browser
# http://localhost:3000

# Test login:
# Username: admin
# Password: password (or whatever you have)
```

---

## 8️⃣ Deploy to Vercel (5 minutes)

### Push to GitHub
```bash
git add .
git commit -m "feat: Add login and dashboard endpoints"
git push origin main
```

### Connect to Vercel
1. Go to https://vercel.com
2. Log in with GitHub
3. Click "New Project"
4. Select your smart-auto-qr repository
5. Vercel will auto-detect settings
6. Click "Deploy"

### Set Environment Variables
1. After deployment, go to Settings
2. Click "Environment Variables"
3. Add these variables:

```
ENVIRONMENT         production
DB_HOST             mysql.planetscale.com
DB_PORT             3306
DB_NAME             smart_auto_db
DB_USER             your_user
DB_PASS             your_password
JWT_SECRET          (generate with: openssl rand -base64 32)
API_URL             https://yourproject.vercel.app/api
FRONTEND_URL        https://yourproject.vercel.app
```

4. Redeploy (Deployments → Redeploy)

---

## 9️⃣ Verify Deployment

```bash
# Test API
curl https://yourproject.vercel.app/api/admin/login \
  -X POST \
  -H 'Content-Type: application/json' \
  -d '{"username":"admin","password":"password"}'

# Expected response:
# {"success":true,"token":"eyJ..."}

# Visit login page
# https://yourproject.vercel.app
```

---

## 🔟 Troubleshooting

| Problem | Solution |
|---------|----------|
| 502 Bad Gateway | Check Vercel function logs, look for PHP errors |
| CORS error | Ensure `setCORSHeaders()` called early in API file |
| 401 Unauthorized | Check JWT token in localStorage & Authorization header |
| Database connection failed | Verify `DB_HOST`, `DB_USER`, `DB_PASS` in Vercel env vars |
| Function timeout | Optimize database queries, reduce operations |

---

##  Checklist

- [ ] Repo pushed to GitHub
- [ ] Vercel project created
- [ ] Environment variables set
- [ ] Database schema imported
- [ ] Login API working
- [ ] Frontend login page loads
- [ ] Admin can login and get JWT token
- [ ] Dashboard API returning data
- [ ] Able to view stats on dashboard

---

**Next Steps:**
1. Migrate remaining endpoints using [API_MIGRATION_GUIDE.md](API_MIGRATION_GUIDE.md)
2. Build out admin UI for auto management
3. Add SOS & QR endpoints
4. Set up error tracking (Sentry optional)
5. Configure custom domain

**Full References:**
- [VERCEL_MIGRATION_GUIDE.md](VERCEL_MIGRATION_GUIDE.md) - Comprehensive guide
- [API_MIGRATION_GUIDE.md](API_MIGRATION_GUIDE.md) - Each endpoint migration
- [VERCEL_DEPLOYMENT_CHECKLIST.md](VERCEL_DEPLOYMENT_CHECKLIST.md) - Pre-deployment verification

