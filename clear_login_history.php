<?php
/**
 * Clear Login Attempts History
 * Run once after database setup to clean old login records
 */
require_once 'config/config.php';

// Clear login attempts
$count = $pdo->query("SELECT COUNT(*) FROM login_attempts")->fetchColumn();
$pdo->exec("TRUNCATE TABLE login_attempts");

echo "✅ Cleared " . number_format($count) . " login attempt records\n";
