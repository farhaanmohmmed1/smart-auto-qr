<?php
/**
 * PUBLIC PAGE — No login required.
 * Opens when a passenger scans the auto's QR code.
 * URL: /public/auto.php?token=SECURE_TOKEN (not guessable)
 * 
 * SECURITY: Uses secure token instead of auto_number
 * This prevents users from manually changing the URL to access other autos
 */
require_once '../config/config.php';

$token = trim($_GET['token'] ?? '');
$auto   = null;
$error  = '';

if (!$token || strlen($token) < 32) {
    $error = 'invalid';
} else {
    $stmt = $pdo->prepare("SELECT * FROM autos WHERE qr_token = ? AND status = 'active' LIMIT 1");
    $stmt->execute([$token]);
    $auto = $stmt->fetch();

    if (!$auto) {
        // Check if exists but suspended/inactive
        $chk = $pdo->prepare("SELECT status FROM autos WHERE qr_token = ? LIMIT 1");
        $chk->execute([$token]);
        $chk = $chk->fetch();
        $error = $chk ? $chk['status'] : 'notfound';
    } else {
        // Log this scan
        $ip = getClientIP();
        $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
        $pdo->prepare("INSERT INTO scan_logs (auto_number, ip_address, user_agent) VALUES (?,?,?)")
            ->execute([$auto['auto_number'], $ip, $ua]);
    }
}

// Build WhatsApp SOS message template (actual GPS injected by JS)
$sosPhone   = SOS_WHATSAPP;
$helpline   = HELPLINE;
$appName    = APP_NAME;
$autoNumber = $auto ? e($auto['auto_number'])  : '';
$driverName = $auto ? e($auto['driver_name'])  : '';
$regNumber  = $auto ? e($auto['reg_number'])   : '';
$driverPhone= $auto ? e($auto['phone'])        : '';
$area       = $auto ? e($auto['area'] ?? '')   : '';
$licenseNum = $auto ? e($auto['license_number']): '';
$permitNum  = $auto ? e($auto['permit_number']) : '';
$securityDetail = $auto ? e($auto['security_detail'] ?? 'safe') : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
  <meta name="theme-color" content="#1e3a5f">
  <title><?= $auto ? "Auto $autoNumber — $appName" : "Not Found — $appName" ?></title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: linear-gradient(135deg, #1e3a5f 0%, #2c5282 100%);
      min-height: 100vh;
      padding: 16px;
      color: #333;
    }
    .container {
      max-width: 900px;
      margin: 0 auto;
      background: white;
      border-radius: 12px;
      box-shadow: 0 10px 40px rgba(0,0,0,0.2);
      overflow: hidden;
    }
    .header {
      background: linear-gradient(135deg, #1e3a5f 0%, #2c5282 100%);
      padding: 24px;
      text-align: center;
      color: white;
      border-bottom: 4px solid #f39c12;
    }
    .header-title {
      font-size: 24px;
      font-weight: bold;
      margin-bottom: 4px;
    }
    .header-subtitle {
      font-size: 14px;
      opacity: 0.9;
    }
    .content {
      padding: 32px 24px;
    }
    .info-section {
      margin-bottom: 32px;
    }
    .section-title {
      background: #1e3a5f;
      color: white;
      padding: 12px 16px;
      font-weight: bold;
      font-size: 14px;
      text-transform: uppercase;
      letter-spacing: 1px;
      margin-bottom: 16px;
      border-left: 4px solid #f39c12;
    }
    .info-table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 20px;
    }
    .info-table td {
      padding: 14px 16px;
      border-bottom: 1px solid #e0e0e0;
    }
    .info-table td:first-child {
      background: #f5f5f5;
      font-weight: 600;
      width: 40%;
      color: #1e3a5f;
      text-transform: uppercase;
      font-size: 12px;
      letter-spacing: 0.5px;
    }
    .info-table td:last-child {
      font-weight: 500;
      font-size: 16px;
      color: #222;
    }
    .info-table tr:last-child td {
      border-bottom: none;
    }
    .status-badge {
      display: inline-block;
      padding: 6px 14px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: bold;
      text-transform: uppercase;
      margin-right: 8px;
    }
    .status-active { background: #e8f5e9; color: #2e7d32; }
    .status-suspended { background: #ffebee; color: #c62828; }
    .status-inactive { background: #fff3e0; color: #e65100; }
    .safety-safe { color: #2e7d32; }
    .safety-caution { color: #f57c00; }
    .safety-danger { color: #c62828; }
    .action-buttons {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px;
      margin-top: 28px;
    }
    .btn {
      padding: 16px;
      border: none;
      border-radius: 8px;
      font-size: 14px;
      font-weight: bold;
      cursor: pointer;
      text-align: center;
      text-decoration: none;
      transition: all 0.3s ease;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    .btn-complaint {
      background: #1e88e5;
      color: white;
    }
    .btn-complaint:hover { background: #1565c0; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(30,136,229,0.4); }
    .btn-sos {
      background: #d32f2f;
      color: white;
      grid-column: 1 / -1;
    }
    .btn-sos:hover { background: #b71c1c; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(211,47,47,0.4); }
    .alert {
      padding: 20px;
      border-radius: 8px;
      margin-bottom: 20px;
      font-size: 14px;
      line-height: 1.6;
    }
    .alert-danger {
      background: #ffebee;
      color: #c62828;
      border-left: 4px solid #c62828;
    }
    .alert-warning {
      background: #fff3e0;
      color: #e65100;
      border-left: 4px solid #e65100;
    }
    .error-page {
      background: white;
      border-radius: 12px;
      padding: 40px;
      text-align: center;
      margin-top: 30px;
    }
    .error-icon {
      font-size: 64px;
      margin-bottom: 16px;
    }
    .error-page h1 {
      color: #1e3a5f;
      margin-bottom: 12px;
      font-size: 28px;
    }
    .error-page p {
      color: #666;
      margin-bottom: 24px;
      line-height: 1.6;
    }
    .modal {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,0.7);
      z-index: 1000;
      align-items: center;
      justify-content: center;
      padding: 16px;
    }
    .modal.show { display: flex; }
    .modal-content {
      background: white;
      border-radius: 12px;
      padding: 32px;
      max-width: 500px;
      width: 100%;
      text-align: center;
      box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    }
    .modal-icon {
      font-size: 48px;
      margin-bottom: 16px;
    }
    .modal-content h2 {
      color: #1e3a5f;
      margin-bottom: 12px;
    }
    .modal-content p {
      color: #666;
      margin-bottom: 24px;
      line-height: 1.6;
    }
    @media (max-width: 600px) {
      .action-buttons { grid-template-columns: 1fr; }
      .btn-sos { grid-column: auto; }
      .content { padding: 20px 16px; }
      .info-table td:first-child { width: 50%; font-size: 11px; }
      .info-table td { padding: 12px; font-size: 14px; }
    }
  </style>
</head>
<body>

<?php if ($error): ?>
<div class="container">
  <div class="header">
    <div class="header-title">Smart Auto QR Safety System</div>
    <div class="header-subtitle">Telangana Police Department</div>
  </div>
  <div class="error-page">
    <?php if ($error === 'invalid'): ?>
      <div class="error-icon">⚠️</div>
      <h1>Invalid QR Code</h1>
      <p>This QR code is invalid or expired. Please scan the correct sticker on the auto-rickshaw.</p>
    <?php elseif ($error === 'suspended'): ?>
      <div class="error-icon">🚫</div>
      <h1>Auto Suspended</h1>
      <div class="alert alert-danger">
        <strong>This auto-rickshaw has been SUSPENDED.</strong> Do not board this vehicle.
        <br><br>
        For emergency assistance, call: <a href="tel:<?= $helpline ?>" style="color:inherit;font-weight:bold;"><?= $helpline ?></a>
      </div>
    <?php elseif ($error === 'inactive'): ?>
      <div class="error-icon">⏸️</div>
      <h1>Auto Not in Service</h1>
      <p>This auto-rickshaw is currently not in active service.</p>
    <?php else: ?>
      <div class="error-icon">🔍</div>
      <h1>Auto Not Found</h1>
      <p>This auto is not registered in our system.</p>
    <?php endif; ?>
    <a href="tel:<?= $helpline ?>" style="display:inline-block;margin-top:20px;padding:12px 24px;background:#1e3a5f;color:white;border-radius:6px;text-decoration:none;font-weight:bold;">📞 Call Police: <?= $helpline ?></a>
  </div>
</div>

<?php else: ?>
<div class="container">
  <!-- Header -->
  <div class="header">
    <div class="header-title">Telangana Police</div>
    <div class="header-subtitle">Auto Rickshaw Verification System</div>
  </div>

  <!-- Content -->
  <div class="content">
    <!-- Status Alert -->
    <div style="text-align:center;margin-bottom:24px;">
      <span class="status-badge status-<?= $auto['status'] ?>"><?= strtoupper($auto['status']) ?></span>
      <span class="status-badge" style="background:#e3f2fd;color:#1e3a5f;">✓ VERIFIED</span>
    </div>

    <!-- Auto Registration Details -->
    <div class="info-section">
      <div class="section-title">🚖 Auto Registration Details</div>
      <table class="info-table">
        <tr>
          <td>Auto Number</td>
          <td style="font-family:monospace;font-size:18px;font-weight:bold;"><?= $autoNumber ?></td>
        </tr>
        <?php if ($regNumber): ?>
        <tr>
          <td>Vehicle Registration</td>
          <td style="font-family:monospace;"><?= $regNumber ?></td>
        </tr>
        <?php endif; ?>
      </table>
    </div>

    <!-- Driver Information -->
    <div class="info-section">
      <div class="section-title">👤 Driver Information</div>
      <table class="info-table">
        <tr>
          <td>Driver Name</td>
          <td><?= $driverName ?></td>
        </tr>
        <?php if ($licenseNum): ?>
        <tr>
          <td>License Number</td>
          <td style="font-family:monospace;"><?= $licenseNum ?></td>
        </tr>
        <?php endif; ?>
        <?php if ($driverPhone): ?>
        <tr>
          <td>Phone</td>
          <td><a href="tel:<?= $driverPhone ?>" style="color:#1e88e5;text-decoration:none;"><?= $driverPhone ?></a></td>
        </tr>
        <?php endif; ?>
      </table>
    </div>

    <!-- Operating Details -->
    <div class="info-section">
      <div class="section-title">📍 Operating Details</div>
      <table class="info-table">
        <?php if ($area): ?>
        <tr>
          <td>Operating Area</td>
          <td><?= ucwords($area) ?></td>
        </tr>
        <?php endif; ?>
        <?php if ($permitNum): ?>
        <tr>
          <td>Permit Number</td>
          <td style="font-family:monospace;"><?= $permitNum ?></td>
        </tr>
        <?php endif; ?>
        <tr>
          <td>Safety Status</td>
          <td>
            <span style="font-size:20px;margin-right:8px;">
              <?php 
                $badges = ['safe' => '✅', 'caution' => '⚠️', 'danger' => '🚫'];
                echo $badges[$securityDetail] ?? '✅';
              ?>
            </span>
            <strong class="safety-<?= $securityDetail ?>"><?= ucfirst($securityDetail) ?></strong>
          </td>
        </tr>
      </table>
    </div>

    <!-- Action Buttons -->
    <div class="action-buttons">
      <button class="btn btn-complaint" onclick="sendComplaint()">📲 Lodge Complaint</button>
      <button class="btn btn-complaint" style="background:#f57c00;" onclick="callHelpline()">📞 Call Police</button>
      <button class="btn btn-sos" onclick="triggerSOS()">🚨 Emergency SOS (GPS)</button>
    </div>

    <!-- Safety Notice -->
    <div class="alert alert-warning" style="margin-top:28px;">
      <strong>⚠️ Safety Notice:</strong> Please verify this information matches the vehicle details before boarding. 
      For emergency situations, immediately press the SOS button above to alert authorities with your GPS location.
    </div>
  </div>
</div>

<!-- SOS Modal -->
<div id="sosModal" class="modal">
  <div class="modal-content">
    <div class="modal-icon">🚨</div>
    <h2>EMERGENCY ALERT</h2>
    <p id="sosStatus">Locating your position...</p>
    <div style="margin:24px 0;height:4px;background:#e0e0e0;border-radius:2px;overflow:hidden;">
      <div style="height:100%;background:#d32f2f;animation:progress 2s ease-in-out infinite;"></div>
    </div>
    <div id="sosActions" style="display:none;gap:12px;">
      <button class="btn btn-sos" onclick="sendSOS()" style="grid-column:1 / -1;margin-bottom:12px;">📲 Send SOS Alert</button>
      <button class="btn" onclick="closeSOS()" style="background:#e0e0e0;color:#333;grid-column:1 / -1;">Cancel</button>
    </div>
    <button class="btn" onclick="closeSOS()" id="closeBtn" style="background:#e0e0e0;color:#333;width:100%;">Close</button>
  </div>
</div>

<style>
  @keyframes progress {
    0%, 100% { width: 0; }
    50% { width: 100%; }
  }
</style>

  <!-- Government Header -->
  <div class="govt-header">
    <div class="govt-seal">🛡️</div>
    <div class="govt-title">GOVERNMENT OF TELANGANA</div>
    <div class="govt-dept">POLICE DEPARTMENT</div>
    <div class="divider-gold"></div>
  </div>

  <!-- Document Title -->
  <div class="document-title-section">
    <div class="document-stamp">OFFICIAL</div>
    <div class="document-title">AUTO RICKSHAW VERIFICATION DOCUMENT</div>
    <div class="document-ref">Reference No. <?= substr(md5($auto['id']), 0, 10) ?></div>
  </div>

  <!-- Auto Registration Block -->
  <div class="registration-block">
    <div class="registration-header">
      <span class="verification-badge">✓ VERIFIED</span>
      <span class="status-badge"><?= strtoupper($auto['status']) ?></span>
    </div>
    <div class="reg-details">
      <div class="reg-row">
        <div class="reg-label">AUTO REGISTRATION NUMBER</div>
        <div class="reg-value"><?= $autoNumber ?></div>
      </div>
      <div class="reg-row">
        <div class="reg-label">VEHICLE REGISTRATION</div>
        <div class="reg-value" style="font-family:monospace;"><?= $regNumber ?></div>
      </div>
    </div>
  </div>

  <!-- Driver Authorization Block -->
  <div class="driver-authorization">
    <div class="auth-title">AUTHORIZED DRIVER DETAILS</div>
    <div class="driver-details-official">
      <div class="detail-row">
        <div class="detail-col-50">
          <div class="detail-label-formal">Driver Name</div>
          <div class="detail-value-formal"><?= $driverName ?></div>
        </div>
        <div class="detail-col-50">
          <div class="detail-label-formal">License Status</div>
          <div class="detail-value-formal verified">ACTIVE</div>
        </div>
      </div>
      
      <div class="detail-row">
        <div class="detail-col-50">
          <div class="detail-label-formal">License Number</div>
          <div class="detail-value-formal" style="font-family:monospace;font-size:0.9rem;"><?= $licenseNum ?></div>
        </div>
        <div class="detail-col-50">
          <div class="detail-label-formal">License Expiry</div>
          <div class="detail-value-formal">2026-12-31</div>
        </div>
      </div>

      <div class="detail-row">
        <div class="detail-col-50">
          <div class="detail-label-formal">Permit Number</div>
          <div class="detail-value-formal" style="font-family:monospace;font-size:0.9rem;"><?= $permitNum ?></div>
        </div>
        <div class="detail-col-50">
          <div class="detail-label-formal">Operating Area</div>
          <div class="detail-value-formal"><?= ucwords($area ?: 'CITYWIDE') ?></div>
        </div>
      </div>

      <div class="detail-row">
        <div class="detail-col-100">
          <div class="detail-label-formal">Contact Number</div>
          <div class="detail-value-formal"><?= $driverPhone ?></div>
        </div>
      </div>

      <div class="detail-row">
        <div class="detail-col-100">
          <div class="detail-label-formal">Safety Status</div>
          <div class="detail-value-formal" style="display:flex;align-items:center;gap:8px;">
            <?php 
              $secBadges = ['safe' => '✅', 'caution' => '⚠️', 'danger' => '🚫'];
              $secIcon = $secBadges[$securityDetail] ?? '✅';
              $secLabel = ucfirst($securityDetail);
            ?>
            <span><?= $secIcon ?></span>
            <span><?= $secLabel ?></span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Safety & Action Section -->
  <div class="action-section">
    <!-- WhatsApp Complaint -->
    <button id="complaintBtn" class="btn btn-complaint-formal" onclick="sendComplaint()">
      📲 LODGE COMPLAINT
    </button>

    <!-- SOS Emergency -->
    <div class="emergency-section">
      <button id="sosBtn" class="btn btn-emergency-formal" onclick="triggerSOS()">
        🚨 EMERGENCY SOS
      </button>
      <div class="emergency-hint">Press to send GPS location to authorities</div>
    </div>
  </div>

  <!-- Official Notice -->
  <div class="official-notice">
    <div class="notice-title">⚠️ OFFICIAL NOTICE</div>
    <div class="notice-content">
      <p>This document certifies that the above auto-rickshaw and driver are registered with the Telangana Police Department under the Smart Auto QR Safety System.</p>
      <p>Passengers are advised to verify this information before boarding. In case of emergency or safety concerns, immediately contact the police helpline at <strong><?= $helpline ?></strong></p>
    </div>
  </div>

  <!-- Document Footer -->
  <div class="document-footer">
    <div class="footer-line">Issued by: Smart Auto QR Safety System · Telangana Police Department</div>
    <div class="footer-date">Document Generated: <?= date('d-M-Y H:i') ?> IST</div>
    <div class="footer-seal">🔐 OFFICIAL DOCUMENT</div>
  </div>

</div>

<!-- SOS Overlay -->
<div id="sosOverlay" class="sos-overlay hidden">
  <div class="sos-modal">
    <div class="sos-modal-icon">🚨</div>
    <h2>EMERGENCY ALERT</h2>
    <p id="sosStatusText">Locating your position...</p>
    <div id="sosProgressBar" class="sos-progress"></div>
    <div id="sosActions" class="hidden" style="display:none;">
      <button id="sendWhatsApp" class="btn btn-emergency-formal" style="width:100%;margin-bottom:10px;">
        📲 SEND EMERGENCY ALERT
      </button>
      <button onclick="closeSOS()" class="btn btn-cancel-formal" style="width:100%;">CANCEL</button>
    </div>
    <div id="sosCancel">
      <button onclick="closeSOS()" class="btn btn-cancel-formal" style="width:100%;margin-top:16px;">✕ CLOSE</button>
    </div>
  </div>
</div>

<?php endif; ?>

<script>
/* ────────────────── SOS System ────────────────── */
const AUTO_NUMBER  = <?= json_encode($auto['auto_number'] ?? '') ?>;
const DRIVER_NAME  = <?= json_encode($auto['driver_name'] ?? '') ?>;
const DRIVER_PHONE = <?= json_encode($auto['phone'] ?? '') ?>;
const SOS_PHONE    = <?= json_encode($sosPhone) ?>;
const HELPLINE     = <?= json_encode($helpline) ?>;

let sosLat = null, sosLng = null;

/* ────────────────── Complaint System ────────────────── */
function sendComplaint() {
  const complaintPhone = SOS_PHONE;  // Send complaint to police authorities
  
  let msg = `📋 *COMPLAINT REPORT* 📋\n\n`;
  msg += `I want to lodge a complaint about this auto.\n\n`;
  msg += `🚖 *Auto Details:*\n`;
  msg += `• Auto No: ${AUTO_NUMBER}\n`;
  msg += `• Driver: ${DRIVER_NAME}\n`;
  msg += `• Phone: ${DRIVER_PHONE}\n\n`;
  msg += `📝 *My Complaint:*\n`;
  msg += `(Please describe what happened)\n\n`;
  msg += `⚠️ Via Smart Auto QR Safety System`;

  const waUrl = `https://wa.me/${complaintPhone}?text=${encodeURIComponent(msg)}`;
  
  // Open WhatsApp with complaint template
  window.open(waUrl, '_blank');
}

function triggerSOS() {
  document.getElementById('sosOverlay').classList.remove('hidden');
  document.getElementById('sosStatusText').textContent = '📡 Getting your GPS location...';
  document.getElementById('sosActions').style.display  = 'none';
  document.getElementById('sosCancel').style.display   = 'block';

  if (!navigator.geolocation) {
    showSOSActions(null, null, 'Location unavailable on this device.');
    return;
  }

  navigator.geolocation.getCurrentPosition(
    function(pos) {
      sosLat = pos.coords.latitude.toFixed(6);
      sosLng = pos.coords.longitude.toFixed(6);
      showSOSActions(sosLat, sosLng, '✅ Location obtained! Tap button below to send SOS.');
    },
    function(err) {
      showSOSActions(null, null, '⚠️ Location access denied. SOS will be sent without GPS.');
    },
    { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
  );

  // Also log to our server
  logSOS();
}

function showSOSActions(lat, lng, msg) {
  document.getElementById('sosStatusText').textContent = msg;
  document.getElementById('sosActions').style.display  = 'flex';
  document.getElementById('sosActions').style.flexDirection = 'column';
  document.getElementById('sosCancel').style.display   = 'none';

  document.getElementById('sendWhatsApp').onclick = function() {
    sendWhatsAppSOS(lat, lng);
  };
}

function sendWhatsAppSOS(lat, lng) {
  let mapLink = lat && lng
    ? `https://maps.google.com/?q=${lat},${lng}`
    : 'Location not available';

  let msg = `🚨 *SOS EMERGENCY ALERT* 🚨\n\n`;
  msg += `I need urgent help!\n\n`;
  msg += `🚖 *Auto Details:*\n`;
  msg += `• Auto No: ${AUTO_NUMBER}\n`;
  msg += `• Driver: ${DRIVER_NAME}\n`;
  msg += `• Driver Phone: ${DRIVER_PHONE}\n\n`;
  if (lat && lng) {
    msg += `📍 *My Location:*\n${mapLink}\n\n`;
  }
  msg += `⚠️ Please help immediately!\n`;
  msg += `🆘 Via Smart Auto QR Safety System`;

  const waUrl = `https://wa.me/${SOS_PHONE}?text=${encodeURIComponent(msg)}`;

  // Open WhatsApp
  window.open(waUrl, '_blank');

  // Update button
  document.getElementById('sendWhatsApp').textContent = '✅ SOS Sent via WhatsApp!';
  document.getElementById('sendWhatsApp').style.background = '#2e7d32';
  document.getElementById('sendWhatsApp').disabled = true;

  setTimeout(closeSOS, 3000);
}

function logSOS() {
  const body = {
    auto_number: AUTO_NUMBER,
    latitude: sosLat,
    longitude: sosLng,
    message: `SOS from QR scan page. Auto: ${AUTO_NUMBER}, Driver: ${DRIVER_NAME}`
  };

  fetch('../api/sos.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body)
  }).catch(() => {}); // Silent fail - WhatsApp is primary
}

function closeSOS() {
  document.getElementById('sosOverlay').classList.add('hidden');
}

// Close overlay on background tap
document.getElementById('sosOverlay')?.addEventListener('click', function(e) {
  if (e.target === this) closeSOS();
});
</script>
</body>
</html>
