<?php
// If someone visits /public/ without an id, show a generic info page
$id = $_GET['id'] ?? '';
if ($id) {
    header('Location: auto.php?id=' . urlencode($id));
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Smart Auto QR Safety System</title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="page error-page">
  <div class="error-wrap">
    <div class="error-icon">🚖</div>
    <h1>Smart Auto QR Safety System</h1>
    <p style="margin-top:12px;">Scan an auto-rickshaw QR code sticker to verify driver details and access emergency features.</p>
    <p style="margin-top:16px;font-size:0.8rem;color:#555;">
      This system is operated by the Police Department.<br>
      For emergencies, call <strong>100</strong>.
    </p>
  </div>
</div>
</body>
</html>
