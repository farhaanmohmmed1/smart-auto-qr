<?php
require_once '../config/config.php';

// Already logged in? Redirect to dashboard
if (isAdmin()) {
    redirect('dashboard.php');
}

$error   = '';
$timeout = isset($_GET['msg']) && $_GET['msg'] === 'timeout';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Token Validation ✅
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        $error = 'Session expired. Please try again.';
    } else {
        $username  = trim($_POST['username'] ?? '');
        $password  = trim($_POST['password'] ?? '');
        $ipAddress = getClientIP();
        
        // Check rate limiting ✅
        $lockoutMsg = checkLoginAttempts($username, $ipAddress);
        
        if ($lockoutMsg) {
            $error = $lockoutMsg;
        } elseif (empty($username) || empty($password)) {
            $error = 'Please enter both username and password.';
            recordLoginAttempt($username, $ipAddress, false);
        } else {
            $stmt = $pdo->prepare("SELECT id, username, password_hash, full_name, role FROM admins WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
            $admin = $stmt->fetch();

            if ($admin && password_verify($password, $admin['password_hash'])) {
                // ✅ Successful login
                recordLoginAttempt($username, $ipAddress, true);  // Record success
                
                session_regenerate_id(true);
                $_SESSION['admin_id']       = $admin['id'];
                $_SESSION['admin_user']     = $admin['username'];
                $_SESSION['admin_name']     = $admin['full_name'];
                $_SESSION['admin_role']     = $admin['role'];
                $_SESSION['last_activity']  = time();

                // Update last login
                $pdo->prepare("UPDATE admins SET last_login = NOW() WHERE id = ?")->execute([$admin['id']]);

                redirect('dashboard.php');
            } else {
                // ❌ Failed login
                recordLoginAttempt($username, $ipAddress, false);
                $error = 'Invalid username or password.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Login | <?= APP_NAME ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    :root {
      --primary: #1a237e;
      --primary-light: #3949ab;
      --danger: #d32f2f;
      --bg: #0d1117;
      --card: #161b22;
      --border: rgba(255,255,255,0.08);
      --text: #e6edf3;
      --muted: #7d8590;
    }
    * { margin:0; padding:0; box-sizing:border-box; }
    body {
      font-family:'Inter',sans-serif;
      background: var(--bg);
      color: var(--text);
      min-height:100vh;
      display:flex;
      align-items:center;
      justify-content:center;
      padding:20px;
      background-image: radial-gradient(ellipse at top, #1a237e22 0%, transparent 50%);
    }
    .login-wrap {
      width:100%;
      max-width:420px;
    }
    .brand {
      text-align:center;
      margin-bottom:32px;
    }
    .brand-icon {
      width:64px;height:64px;
      background:var(--primary);
      border-radius:16px;
      display:flex;
      align-items:center;
      justify-content:center;
      font-size:28px;
      margin:0 auto 16px;
    }
    .brand h1 { font-size:1.4rem; font-weight:700; margin-bottom:4px; }
    .brand p  { color:var(--muted); font-size:0.85rem; }

    .card {
      background:var(--card);
      border:1px solid var(--border);
      border-radius:16px;
      padding:32px;
    }
    .alert {
      padding:12px 16px;
      border-radius:8px;
      font-size:0.875rem;
      margin-bottom:20px;
    }
    .alert-danger  { background:rgba(211,47,47,0.15); border:1px solid rgba(211,47,47,0.3); color:#ef5350; }
    .alert-warning { background:rgba(255,152,0,0.1);  border:1px solid rgba(255,152,0,0.3);  color:#ffa726; }

    .form-group { margin-bottom:20px; }
    label { display:block; font-size:0.8rem; font-weight:600; color:var(--muted); text-transform:uppercase; letter-spacing:0.5px; margin-bottom:8px; }
    input[type=text], input[type=password] {
      width:100%;
      padding:12px 16px;
      background:#0d1117;
      border:1px solid var(--border);
      border-radius:10px;
      color:var(--text);
      font-size:1rem;
      font-family:'Inter',sans-serif;
      outline:none;
      transition:border-color 0.2s;
    }
    input:focus { border-color:var(--primary-light); }

    .btn-login {
      width:100%;
      padding:14px;
      background:var(--primary);
      color:#fff;
      border:none;
      border-radius:10px;
      font-size:1rem;
      font-weight:700;
      cursor:pointer;
      transition:background 0.2s, transform 0.1s;
      margin-top:8px;
      font-family:'Inter',sans-serif;
    }
    .btn-login:hover  { background:var(--primary-light); }
    .btn-login:active { transform:scale(0.98); }

    .footer-note { text-align:center; margin-top:20px; font-size:0.78rem; color:var(--muted); }
    .gov-badge {
      display:flex;
      align-items:center;
      justify-content:center;
      gap:8px;
      margin-top:24px;
      padding:12px;
      background:rgba(255,255,255,0.02);
      border:1px solid var(--border);
      border-radius:10px;
      font-size:0.75rem;
      color:var(--muted);
    }
  </style>
</head>
<body>
<div class="login-wrap">
  <div class="brand">
    <div class="brand-icon">🚔</div>
    <h1><?= APP_NAME ?></h1>
    <p>Police Administration Portal</p>
  </div>

  <div class="card">
    <?php if ($timeout): ?>
      <div class="alert alert-warning">⏱ Session timed out. Please login again.</div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="alert alert-danger">⚠️ <?= e($error) ?></div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRFToken()) ?>">
      
      <div class="form-group">
        <label>Username</label>
        <input type="text" name="username" required placeholder="Enter admin username"
               value="<?= e($_POST['username'] ?? '') ?>" autofocus>
      </div>
      <div class="form-group">
        <label>Password</label>
        <input type="password" name="password" required placeholder="••••••••">
      </div>
      <button type="submit" class="btn-login">🔐 Secure Login</button>
    </form>

    <div class="footer-note">🛡️ Use credentials created during setup.php</div>
  </div>

  <div class="gov-badge">
    🛡️ Authorized Personnel Only &nbsp;|&nbsp; All access is logged
  </div>
</div>
</body>
</html>
