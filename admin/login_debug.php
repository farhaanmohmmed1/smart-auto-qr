<?php
require_once '../config/config.php';

echo "<h1>Login Debug</h1>";

// Check recent login attempts
$attempts = $pdo->query("SELECT * FROM login_attempts ORDER BY attempt_time DESC LIMIT 10")->fetchAll();

echo "<h2>Recent Login Attempts:</h2>";
if (empty($attempts)) {
    echo "❌ No login attempts recorded!";
} else {
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>Username</th><th>IP</th><th>Success</th><th>Time</th></tr>";
    foreach ($attempts as $attempt) {
        $status = $attempt['success'] ? '✅ YES' : '❌ NO';
        echo "<tr><td>{$attempt['username']}</td><td>{$attempt['ip_address']}</td><td>{$status}</td><td>{$attempt['attempt_time']}</td></tr>";
    }
    echo "</table>";
}

echo "<h2>Admin Users in Database:</h2>";
$admins = $pdo->query("SELECT id, username, full_name, role, last_login FROM admins")->fetchAll();
if (empty($admins)) {
    echo "❌ No admin users!";
} else {
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>ID</th><th>Username</th><th>Name</th><th>Role</th><th>Last Login</th></tr>";
    foreach ($admins as $admin) {
        echo "<tr><td>{$admin['id']}</td><td>{$admin['username']}</td><td>{$admin['full_name']}</td><td>{$admin['role']}</td><td>{$admin['last_login'] ?? 'Never'}</td></tr>";
    }
    echo "</table>";
}

echo "<h2>Session Info:</h2>";
echo "Session ID: " . session_id() . "<br>";
echo "Session Status: " . (session_status() === PHP_SESSION_ACTIVE ? "ACTIVE" : "INACTIVE") . "<br>";
echo "Session Save Path: " . ini_get('session.save_path') . "<br>";
echo "Session Cookie HttpOnly: " . ini_get('session.cookie_httponly') . "<br>";
?>
