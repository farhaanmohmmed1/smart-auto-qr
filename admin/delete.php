<?php
require_once '../config/config.php';
require_once '../lib/QRGenerator.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('manage.php');
}

$id = (int)($_POST['id'] ?? 0);
if (!$id) redirect('manage.php');

$stmt = $pdo->prepare("SELECT auto_number FROM autos WHERE id=?");
$stmt->execute([$id]);
$auto = $stmt->fetch();

if ($auto) {
    QRGenerator::delete($auto['auto_number']);
    $pdo->prepare("DELETE FROM autos WHERE id=?")->execute([$id]);
}

redirect('manage.php?flash=deleted');
