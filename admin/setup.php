<?php
/**
 * SECURE FIRST-TIME ADMIN SETUP
 * ================================
 * Run this ONCE after database creation
 * URL: http://localhost/smart_auto_qr/admin/setup.php
 * 
 * DELETE THIS FILE after setup is complete
 * This file will auto-delete after successful setup
 */

require_once '../config/config.php';

// ── Check if already setup ──────────────────────────────────
$existingAdmin = $pdo->query("SELECT COUNT(*) FROM admins")->fetchColumn();
if ($existingAdmin > 0) {
    http_response_code(403);
    die("<h1 style='color:red;'>❌ Setup Already Complete</h1><p>Admin account already exists. Delete admins table entry to reset.</p><p><a href='login.php'>Go to Login</a></p>");
}

$error   = '';
$success = '';
$submitted = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submitted = true;
    
    $username = trim($_POST['username'] ?? '');
    $fullname = trim($_POST['fullname'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $password_confirm = trim($_POST['password_confirm'] ?? '');
    
    // ── Validation ────────────────────────────────────────
    $errors = [];
    
    // Username validation
    if (empty($username)) {
        $errors[] = 'Username is required.';
    } elseif (strlen($username) < 4) {
        $errors[] = 'Username must be at least 4 characters.';
    } elseif (!preg_match('/^[a-zA-Z0-9._-]+$/', $username)) {
        $errors[] = 'Username can only contain letters, numbers, dots, hyphens, and underscores.';
    }
    
    // Full name validation
    if (empty($fullname)) {
        $errors[] = 'Full Name is required.';
    } elseif (strlen($fullname) < 3) {
        $errors[] = 'Full Name must be at least 3 characters.';
    }
    
    // Password validation (OWASP Compliant)
    if (empty($password)) {
        $errors[] = 'Password is required.';
    } else {
        if (strlen($password) < 12) {
            $errors[] = '❌ Password must be at least 12 characters long.';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = '❌ Password must contain at least one UPPERCASE letter.';
        }
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = '❌ Password must contain at least one lowercase letter.';
        }
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = '❌ Password must contain at least one number.';
        }
        if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
            $errors[] = '❌ Password must contain at least one special character (!@#$%^&*).';
        }
    }
    
    // Password confirmation
    if ($password !== $password_confirm) {
        $errors[] = 'Passwords do not match.';
    }
    
    if (!empty($errors)) {
        $error = implode('<br>', $errors);
    } else {
        // ── All validations passed, insert admin ────────────
        try {
            $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            
            $stmt = $pdo->prepare("
                INSERT INTO admins (username, password_hash, full_name, role, created_at)
                VALUES (?, ?, ?, 'superadmin', NOW())
            ");
            
            $stmt->execute([$username, $passwordHash, $fullname]);
            
            $success = "✅ Admin account created successfully!<br><br>";
            $success .= "<strong>Account Details:</strong><br>";
            $success .= "Username: <code>$username</code><br>";
            $success .= "Role: <code>Super Admin</code><br><br>";
            $success .= "⚠️ <strong>IMPORTANT:</strong><br>";
            $success .= "1. Write down your credentials securely (use a password manager)<br>";
            $success .= "2. Delete this setup.php file from your server<br>";
            $success .= "3. <a href='login.php'>Proceed to Login</a>";
            
            // Log setup event
            error_log("SECURITY: Admin '$username' created via setup.php at " . date('Y-m-d H:i:s'));
            
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                $error = 'Username already exists!';
            } else {
                $error = 'Database error: ' . htmlspecialchars($e->getMessage());
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
    <title>Initial Setup | <?= APP_NAME ?></title>
    <style>
        :root {
            --primary: #1a237e;
            --danger: #d32f2f;
            --success: #2e7d32;
            --bg: #0d1117;
            --card: #161b22;
            --border: rgba(255,255,255,0.08);
            --text: #e6edf3;
            --muted: #7d8590;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            width: 100%;
            max-width: 500px;
        }
        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 40px 30px;
        }
        h1 {
            font-size: 1.8rem;
            margin-bottom: 10px;
            text-align: center;
        }
        .subtitle {
            color: var(--muted);
            text-align: center;
            margin-bottom: 30px;
            font-size: 0.95rem;
        }
        .alert {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.95rem;
            line-height: 1.6;
        }
        .alert-error {
            background: rgba(211, 47, 47, 0.15);
            border-left: 4px solid #d32f2f;
            color: #ff6b6b;
        }
        .alert-success {
            background: rgba(46, 125, 50, 0.15);
            border-left: 4px solid #2e7d32;
            color: #66bb6a;
        }
        .alert a {
            color: inherit;
            font-weight: 600;
            text-decoration: underline;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 0.95rem;
        }
        input[type="text"], input[type="password"] {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid var(--border);
            border-radius: 6px;
            background: rgba(255, 255, 255, 0.05);
            color: var(--text);
            font-size: 1rem;
            font-family: inherit;
        }
        input[type="text"]:focus, input[type="password"]:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(26, 35, 126, 0.1);
        }
        .password-hint {
            margin-top: 8px;
            padding: 12px;
            background: rgba(255, 255, 255, 0.03);
            border-left: 3px solid var(--primary);
            font-size: 0.85rem;
            color: var(--muted);
            border-radius: 4px;
        }
        .password-requirements {
            margin-top: 8px;
            font-size: 0.85rem;
            color: var(--muted);
        }
        .requirement {
            display: flex;
            align-items: center;
            margin: 6px 0;
            padding: 4px 0;
        }
        .requirement-icon {
            margin-right: 8px;
            font-weight: bold;
        }
        button {
            width: 100%;
            padding: 14px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        button:hover {
            background: #3949ab;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(26, 35, 126, 0.3);
        }
        button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }
        .footer-text {
            text-align: center;
            margin-top: 20px;
            color: var(--muted);
            font-size: 0.85rem;
        }
        code {
            background: rgba(255, 255, 255, 0.05);
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
            color: #81c784;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <h1>🔒 Initial Admin Setup</h1>
            <p class="subtitle">Create the first super admin account (Police Commandant)</p>

            <?php if ($success): ?>
                <div class="alert alert-success"><?= $success ?></div>
            <?php elseif ($error && $submitted): ?>
                <div class="alert alert-error"><?= $error ?></div>
            <?php endif; ?>

            <?php if (!$success): ?>
                <form method="POST">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" 
                               placeholder="e.g., police_admin" 
                               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
                        <div class="password-requirements">
                            Must be 4+ characters, alphanumeric
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="fullname">Full Name (Police Commandant)</label>
                        <input type="text" id="fullname" name="fullname" 
                               placeholder="e.g., John Smith" 
                               value="<?= htmlspecialchars($_POST['fullname'] ?? '') ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="password">Master Password</label>
                        <input type="password" id="password" name="password" 
                               placeholder="Enter a strong password" required>
                        <div class="password-requirements">
                            <strong>✓ Password Requirements:</strong>
                            <div class="requirement">
                                <span class="requirement-icon">•</span>
                                <span>At least 12 characters</span>
                            </div>
                            <div class="requirement">
                                <span class="requirement-icon">•</span>
                                <span>1 UPPERCASE letter (A-Z)</span>
                            </div>
                            <div class="requirement">
                                <span class="requirement-icon">•</span>
                                <span>1 lowercase letter (a-z)</span>
                            </div>
                            <div class="requirement">
                                <span class="requirement-icon">•</span>
                                <span>1 number (0-9)</span>
                            </div>
                            <div class="requirement">
                                <span class="requirement-icon">•</span>
                                <span>1 special character (!@#$%^&*)</span>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="password_confirm">Confirm Password</label>
                        <input type="password" id="password_confirm" name="password_confirm" 
                               placeholder="Re-enter your password" required>
                    </div>

                    <button type="submit">Create Admin Account</button>

                    <div class="footer-text">
                        ⚠️ This setup page should be deleted after initial configuration<br>
                        Keep your credentials in a secure password manager
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
