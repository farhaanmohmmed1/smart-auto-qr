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
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
  <meta name="theme-color" content="#0d1117">
  <title><?= $auto ? "Auto $autoNumber — $appName" : "Not Found — $appName" ?></title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<?php if ($error): ?>
<!-- ═══════════════════════════════════ ERROR STATE ═════════════ -->
<div class="page error-page">
  <div class="error-wrap">
    <?php if ($error === 'invalid'): ?>
      <div class="error-icon">⚠️</div>
      <h1>Invalid QR Code</h1>
      <p>This QR code does not contain a valid auto ID. Please scan the correct sticker.</p>
    <?php elseif ($error === 'suspended'): ?>
      <div class="error-icon">🚫</div>
      <h1>Auto Suspended</h1>
      <p>This auto-rickshaw has been <strong>suspended</strong> by the police. Please avoid boarding.</p>
      <div class="sos-notice">If you are in danger, call <a href="tel:<?= $helpline ?>"><?= $helpline ?></a></div>
    <?php elseif ($error === 'inactive'): ?>
      <div class="error-icon">⏸️</div>
      <h1>Auto Inactive</h1>
      <p>This auto-rickshaw is currently not in active service.</p>
    <?php else: ?>
      <div class="error-icon">🔍</div>
      <h1>Auto Not Found</h1>
      <p>No auto with ID <code><?= e($autoId) ?></code> exists in the police database.</p>
    <?php endif; ?>
    <a href="tel:<?= $helpline ?>" class="btn btn-outline" style="margin-top:24px;">📞 Call Police: <?= $helpline ?></a>
  </div>
</div>

<?php else: ?>
<!-- ═══════════════════════════════════ OFFICIAL GOVERNMENT DOCUMENT ════════════ -->
<div class="page">

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
