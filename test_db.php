<?php
require_once 'config/config.php';

$stmt = $pdo->query("SELECT auto_number, status FROM autos LIMIT 5");
$autos = $stmt->fetchAll();

echo "<h2>Available Autos:</h2>";
echo "<pre>";
print_r($autos);
echo "</pre>";
?>
