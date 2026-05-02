<?php
require_once __DIR__ . '/config/config.php';

try {
    // Check if column exists
    $check = $pdo->query("SHOW COLUMNS FROM autos LIKE 'qr_token'");
    if ($check->rowCount() > 0) {
        echo "Column already exists\n";
    } else {
        // Add column
        $pdo->exec("ALTER TABLE autos ADD COLUMN qr_token VARCHAR(64) UNIQUE DEFAULT NULL AFTER permit_number");
        echo "Column added successfully\n";
        
        // Generate tokens for existing autos
        $stmt = $pdo->prepare("SELECT id, auto_number FROM autos WHERE qr_token IS NULL OR qr_token = ''");
        $stmt->execute();
        $autos = $stmt->fetchAll();
        
        $count = 0;
        foreach ($autos as $auto) {
            $token = bin2hex(random_bytes(32));
            $upd = $pdo->prepare("UPDATE autos SET qr_token = ? WHERE id = ?");
            if ($upd->execute([$token, $auto['id']])) {
                $count++;
            }
        }
        
        echo "Generated tokens for {$count} autos\n";
    }
    
    echo "SUCCESS: Migration complete!\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>
