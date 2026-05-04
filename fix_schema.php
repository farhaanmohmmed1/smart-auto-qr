<?php
/**
 * Database Schema Migration - Allow Empty Strings for Optional Fields
 * ====================================================================
 * Updates the database schema to accept empty strings for optional fields
 */

require_once 'config/config.php';

// Redirect to login if not admin
if (empty($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

echo "<h2>🔧 Updating Database Schema</h2>";

$updates = [
    "ALTER TABLE autos MODIFY COLUMN reg_number VARCHAR(30) DEFAULT ''",
    "ALTER TABLE autos MODIFY COLUMN phone VARCHAR(15) DEFAULT ''",
    "ALTER TABLE autos MODIFY COLUMN license_number VARCHAR(50) DEFAULT ''",
    "ALTER TABLE autos MODIFY COLUMN permit_number VARCHAR(50) DEFAULT ''",
    "ALTER TABLE autos MODIFY COLUMN area VARCHAR(100) DEFAULT ''",
    "ALTER TABLE autos MODIFY COLUMN stand VARCHAR(100) DEFAULT ''",
];

$success = true;

try {
    foreach ($updates as $sql) {
        $pdo->exec($sql);
        $columnName = explode('`', $sql)[1] ?? 'unknown';
        echo "✅ Updated table schema<br>";
    }
    
    echo "<p style='color: green; font-weight: bold; margin-top: 20px;'>✅ All schema updates completed successfully!</p>";
    echo "<p><strong>Optional fields can now be empty/blank in your imports.</strong></p>";
    echo "<p><a href='admin/bulk_upload.php' style='color: #2196F3;'>← Go back to Bulk Upload</a></p>";
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>❌ Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>The database schema may already be updated. Try uploading your file again.</p>";
    echo "<p><a href='admin/bulk_upload.php' style='color: #2196F3;'>← Go back to Bulk Upload</a></p>";
}
?>
