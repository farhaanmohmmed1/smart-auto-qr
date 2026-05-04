<?php
require_once '../config/config.php';
require_once '../lib/QRGenerator.php';
requireAdmin();

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Token Validation ✅
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = '❌ CSRF token invalid. Please try again.';
    } else {
        // Sanitize inputs
        $auto_number     = strtoupper(trim($_POST['auto_number']   ?? ''));
        $reg_number      = strtoupper(trim($_POST['reg_number']    ?? ''));
        $driver_name     = trim($_POST['driver_name']  ?? '');
        $phone           = preg_replace('/\D/', '', trim($_POST['phone'] ?? ''));
        $license_number  = strtoupper(trim($_POST['license_number'] ?? ''));
        $permit_number   = strtoupper(trim($_POST['permit_number']  ?? ''));
        $area            = trim($_POST['area']  ?? '');
        $stand           = trim($_POST['stand'] ?? '');
        $security_detail = in_array($_POST['security_detail'] ?? 'safe', ['safe','caution','danger']) ? $_POST['security_detail'] : 'safe';

        // Validate - ONLY auto_number and driver_name are required
        $errors = [];
        if (!$auto_number)    $errors[] = 'Auto Number is required (2-50 characters).';
        if (!$driver_name)    $errors[] = 'Driver Name is required (3-100 characters).';
        
        // Validate lengths
        if ($auto_number && (strlen($auto_number) < 2 || strlen($auto_number) > 50)) {
            $errors[] = 'Auto Number must be 2-50 characters.';
        }
        if ($driver_name && (strlen($driver_name) < 3 || strlen($driver_name) > 100)) {
            $errors[] = 'Driver Name must be 3-100 characters.';
        }
        
        // Validate optional fields if provided
        if ($phone && strlen($phone) < 10) $errors[] = 'Phone must be 10-12 digits if provided.';

        // Check for duplicate auto_number only
        if (!$errors) {
            $dup = $pdo->prepare("SELECT id FROM autos WHERE auto_number=?");
            $dup->execute([$auto_number]);
            if ($dup->fetch()) {
                $errors[] = 'This Auto Number already exists.';
            }
        }

        if ($errors) {
            $error = implode('<br>', $errors);
        } else {
            try {
                // Generate secure token for QR code
                $qrToken = bin2hex(random_bytes(32));
                
                // Convert empty strings to NULL for optional fields
                $safeFields = [
                    'reg_number' => $reg_number ?: '',
                    'driver_name' => $driver_name,
                    'phone' => $phone ?: '',
                    'license_number' => $license_number ?: '',
                    'permit_number' => $permit_number ?: '',
                    'area' => $area ?: '',
                    'stand' => $stand ?: '',
                    'security_detail' => $security_detail,
                ];
                
                // Insert record
                $stmt = $pdo->prepare("INSERT INTO autos 
                    (auto_number, reg_number, driver_name, phone, license_number, permit_number, area, stand, security_detail, qr_token, status)
                    VALUES (?,?,?,?,?,?,?,?,?,?,'active')");
                $stmt->execute([$auto_number, $safeFields['reg_number'], $safeFields['driver_name'], $safeFields['phone'], $safeFields['license_number'], $safeFields['permit_number'], $safeFields['area'], $safeFields['stand'], $safeFields['security_detail'], $qrToken]);

                // Generate QR code
                $qrPath = QRGenerator::generate($auto_number);
                
                if ($qrPath) {
                    // Update with QR path
                    $pdo->prepare("UPDATE autos SET qr_path=? WHERE auto_number=?")->execute([$qrPath, $auto_number]);
                    
                    // LOCK QR CODE: Once assigned, never regenerate
                    QRGenerator::lockQRCode($auto_number, $qrPath);
                    
                    $success = "✅ Auto <strong>{$auto_number}</strong> registered! QR code generated and locked.";
                } else {
                    $success = "⚠️ Auto <strong>{$auto_number}</strong> registered, but QR generation failed.";
                }

                // Clear form
                $auto_number = $reg_number = $driver_name = $phone = $license_number = $permit_number = $area = $stand = $security_detail = '';

            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Register Auto | <?= APP_NAME ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
<?php include 'partials/sidebar.php'; ?>
<div class="main-wrapper">
  <?php include 'partials/topbar.php'; ?>
  <div class="page-content">
    <div class="page-header">
      <div>
        <h1 class="page-title">Register New Auto</h1>
        <p class="page-sub">Add a new auto-rickshaw to the system and generate QR code</p>
      </div>
      <a href="manage.php" class="btn btn-outline">← All Autos</a>
    </div>

    <?php if ($success): ?>
      <div class="alert alert-success">✅ <?= $success ?>
        <br><br>
        <a href="manage.php" class="btn btn-sm btn-success">View All Autos</a>
        <a href="register.php" class="btn btn-sm btn-outline" style="margin-left:8px;">Register Another</a>
      </div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="alert alert-danger">⚠️ <?= $error ?></div>
    <?php endif; ?>

    <div class="card">
      <div class="card-header">
        <h2 class="card-title">🚖 Auto & Driver Details</h2>
      </div>
      <div class="card-body">
        <form method="POST" id="registerForm" novalidate>
          <!-- CSRF Token Protection ✅ -->
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRFToken()) ?>">
          
          <div class="form-grid">

            <div class="form-group">
              <label>Auto Number <span style="color:#ef5350">*</span></label>
              <input type="text" name="auto_number" id="auto_number"
                     value="<?= e($auto_number ?? '') ?>"
                     placeholder="e.g. AUTO-001" required
                     oninput="this.value=this.value.toUpperCase()">
              <div class="form-hint">Unique identifier. Will be used in QR code URL.</div>
            </div>

            <div class="form-group">
              <label>Vehicle Registration Number</label>
              <input type="text" name="reg_number"
                     value="<?= e($reg_number ?? '') ?>"
                     placeholder="e.g. TS09EA1234"
                     oninput="this.value=this.value.toUpperCase()">
              <div class="form-hint">Optional - Vehicle registration number</div>
            </div>

            <div class="form-group">
              <label>Driver Full Name <span style="color:#ef5350">*</span></label>
              <input type="text" name="driver_name"
                     value="<?= e($driver_name ?? '') ?>"
                     placeholder="e.g. Rajesh Kumar" required>
            </div>

            <div class="form-group">
              <label>Phone Number</label>
              <input type="tel" name="phone"
                     value="<?= e($phone ?? '') ?>"
                     placeholder="e.g. 9876543210"
                     maxlength="12" pattern="[0-9]{10,12}">
              <div class="form-hint">Optional - 10-12 digit phone number</div>
            </div>

            <div class="form-group">
              <label>Driving License Number</label>
              <input type="text" name="license_number"
                     value="<?= e($license_number ?? '') ?>"
                     placeholder="e.g. TS14DL20200001"
                     oninput="this.value=this.value.toUpperCase()">
              <div class="form-hint">Optional - Driver license ID</div>
            </div>

            <div class="form-group">
              <label>Permit Number</label>
              <input type="text" name="permit_number"
                     value="<?= e($permit_number ?? '') ?>"
                     placeholder="e.g. HYD/PERMIT/2024/001"
                     oninput="this.value=this.value.toUpperCase()">
              <div class="form-hint">Optional - Operating permit ID</div>
            </div>

            <div class="form-group">
              <label>Area / Zone</label>
              <input type="text" name="area"
                     value="<?= e($area ?? '') ?>"
                     placeholder="e.g. Ameerpet, Kukatpally">
            </div>

            <div class="form-group">
              <label>Auto Stand</label>
              <input type="text" name="stand"
                     value="<?= e($stand ?? '') ?>"
                     placeholder="e.g. KPHB Colony Stand">
            </div>

            <div class="form-group">
              <label>Safety Status</label>
              <select name="security_detail">
                <option value="safe" <?= ($security_detail ?? 'safe')==='safe'    ?'selected':'' ?>>✅ Safe</option>
                <option value="caution" <?= ($security_detail ?? 'safe')==='caution' ?'selected':'' ?>>⚠️ Caution</option>
                <option value="danger"  <?= ($security_detail ?? 'safe')==='danger'  ?'selected':'' ?>>🚫 Danger</option>
              </select>
              <div class="form-hint">Default: Safe</div>
            </div>

          </div><!-- /form-grid -->

          <div style="margin-top:28px;display:flex;gap:12px;flex-wrap:wrap;">
            <button type="submit" class="btn btn-primary" id="submitBtn">
              ✅ Register & Generate QR
            </button>
            <button type="reset" class="btn btn-outline">Clear Form</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Info card -->
    <div class="card" style="margin-top:20px;border-color:rgba(63,81,181,0.3);">
      <div class="card-body">
        <div style="display:flex;gap:16px;align-items:flex-start;flex-wrap:wrap;">
          <div style="flex:1;min-width:200px;">
            <h3 style="font-size:0.9rem;margin-bottom:8px;color:#9fa8da;">🔗 How the QR System Works</h3>
            <p style="font-size:0.82rem;color:var(--muted);line-height:1.6;">
              When an auto is registered, a unique URL is generated:<br>
              <code style="font-size:0.78rem;background:rgba(255,255,255,0.05);padding:2px 6px;border-radius:4px;">/public/auto.php?id=AUTO_NUMBER</code><br><br>
              This URL is encoded into a QR code and printed on the auto sticker.
              When a passenger scans it, they see the driver's verified details.
            </p>
          </div>
          <div style="flex:1;min-width:200px;">
            <h3 style="font-size:0.9rem;margin-bottom:8px;color:#9fa8da;">🚨 SOS Feature</h3>
            <p style="font-size:0.82rem;color:var(--muted);line-height:1.6;">
              The public page includes an SOS button that captures GPS location
              and redirects to WhatsApp with a pre-filled emergency message
              including the auto number and Google Maps link.
            </p>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>

<script>
document.getElementById('registerForm').addEventListener('submit', function(e) {
  const btn = document.getElementById('submitBtn');
  btn.textContent = '⏳ Registering...';
  btn.disabled = true;
});
</script>
<script src="assets/js/admin.js"></script>
</body>
</html>
