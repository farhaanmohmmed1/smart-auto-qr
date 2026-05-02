<?php
/**
 * Database Migration: Add qr_token column
 * 
 * Run this once to add the qr_token column to autos table
 * Visit: http://localhost/smart_auto_qr/migrate.php
 */

require_once './config/config.php';

$result = '';

try {
    // Check if column already exists
    $check = $pdo->prepare("
        SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_NAME = 'autos' AND COLUMN_NAME = 'qr_token' AND TABLE_SCHEMA = ?
    ");
    $check->execute([DB_NAME]);
    
    if ($check->fetch()) {
        $result = '✅ Column qr_token already exists!';
    } else {
        // Add the column
        $pdo->exec("
            ALTER TABLE autos 
            ADD COLUMN qr_token VARCHAR(64) UNIQUE DEFAULT NULL AFTER permit_number
        ");
        
        $result = '✅ Column qr_token added successfully!';
        
        // Now generate tokens for all autos without one
        $stmt = $pdo->prepare("SELECT id, auto_number FROM autos WHERE qr_token IS NULL");
        $stmt->execute();
        $autos = $stmt->fetchAll();
        
        $generated = 0;
        foreach ($autos as $auto) {
            $token = bin2hex(random_bytes(32));
            $upd = $pdo->prepare("UPDATE autos SET qr_token = ? WHERE id = ?");
            if ($upd->execute([$token, $auto['id']])) {
                $generated++;
            }
        }
        
        $result .= "<br>✅ Generated tokens for <strong>{$generated}</strong> existing autos";
    }
    
} catch (Exception $e) {
    $result = '❌ Error: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Migration | Smart Auto QR</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #f5f5f5; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; }
        h1 { color: #333; }
        .success { color: #2e7d32; padding: 15px; background: #e8f5e9; border-radius: 4px; }
        .error { color: #c62828; padding: 15px; background: #ffebee; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Database Migration</h1>
        <div class="<?= strpos($result, '❌') === 0 ? 'error' : 'success' ?>">
            <?= $result ?>
        </div>
        <p style="margin-top: 20px; color: #666;">
            ✅ Migration complete! You can now scan QR codes.
        </p>
        <a href="public/auto.php" style="display: inline-block; margin-top: 15px; padding: 10px 20px; background: #1976d2; color: white; text-decoration: none; border-radius: 4px;">
            ← Go Back
        </a>
    </div>
</body>
</html>
