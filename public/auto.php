<?php
/**
 * PUBLIC PAGE — No login required.
 * Opens when a passenger scans the auto's QR code.
 * URL: /public/auto.php?id=AUTO_NUMBER
 */
require_once '../config/config.php';

$autoId = trim($_GET['id'] ?? '');
$auto   = null;
$error  = '';

if (!$autoId) {
    $error = 'invalid';
} else {
    $stmt = $pdo->prepare("SELECT * FROM autos WHERE auto_number = ? AND status = 'active' LIMIT 1");
    $stmt->execute([$autoId]);
    $auto = $stmt->fetch();

    if (!$auto) {
        // Check if exists but suspended/inactive
        $chk = $pdo->prepare("SELECT status FROM autos WHERE auto_number = ? LIMIT 1");
        $chk->execute([$autoId]);
        $chk = $chk->fetch();
        $error = $chk ? $chk['status'] : 'notfound';
    } else {
        // Log this scan
        $ip = getClientIP();
        $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
        $pdo->prepare("INSERT INTO scan_logs (auto_number, ip_address, user_agent) VALUES (?,?,?)")
            ->execute([$autoId, $ip, $ua]);
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
<!-- ═══════════════════════════════════ VERIFIED AUTO ════════════ -->
<div class="page">

  <!-- Header Bar -->
  <div class="header-bar">
    <div class="header-logo">🚔</div>
    <div>
      <div class="header-title">Police Verified</div>
      <div class="header-sub"><?= $appName ?></div>
    </div>
    <div class="verified-dot"></div>
  </div>

  <!-- Auto Number -->
  <div class="auto-number-card">
    <div class="auto-label">AUTO RICKSHAW</div>
    <div class="auto-number"><?= $autoNumber ?></div>
    <div class="reg-number"><?= $regNumber ?></div>
  </div>

  <!-- Driver Card -->
  <div class="driver-card">
    <div class="driver-avatar">
      <span><?= strtoupper(substr($driverName, 0, 1)) ?></span>
    </div>
    <div class="driver-info">
      <div class="driver-name"><?= $driverName ?></div>
      <div class="driver-badge">✔ Registered Driver</div>
    </div>
  </div>

  <!-- Details Grid -->
  <div class="details-grid">
    <div class="detail-item">
      <div class="detail-label">📱 Mobile</div>
      <div class="detail-value"><?= $driverPhone ?></div>
    </div>
    <div class="detail-item">
      <div class="detail-label">📋 License</div>
      <div class="detail-value" style="font-size:0.85rem;"><?= $licenseNum ?></div>
    </div>
    <div class="detail-item">
      <div class="detail-label">📄 Permit</div>
      <div class="detail-value" style="font-size:0.85rem;"><?= $permitNum ?></div>
    </div>
    <div class="detail-item">
      <div class="detail-label">📍 Area</div>
      <div class="detail-value"><?= $area ?: '—' ?></div>
    </div>
  </div>

  <!-- Call Driver Button -->
  <a href="tel:<?= $driverPhone ?>" class="btn btn-call">
    📞 Call Driver
  </a>

  <!-- SOS Section -->
  <div class="sos-section">
    <button id="sosBtn" class="btn btn-sos" onclick="triggerSOS()">
      🚨 SOS EMERGENCY
    </button>
    <div class="sos-hint">Tap SOS to send your GPS location via WhatsApp</div>
  </div>

  <!-- Safety Tips -->
  <div class="safety-card">
    <div class="safety-title">🛡️ Safety Tips</div>
    <div class="safety-items">
      <div class="safety-item">📸 Screenshot this page for records</div>
      <div class="safety-item">📍 Share location with family before ride</div>
      <div class="safety-item">🚨 Use SOS if you feel unsafe</div>
    </div>
  </div>

  <!-- Footer -->
  <div class="footer">
    <div class="footer-helpline">Emergency: <a href="tel:<?= $helpline ?>"><?= $helpline ?></a></div>
    <div class="footer-note"><?= $appName ?> · Government of Telangana</div>
  </div>
</div>

<!-- SOS Overlay -->
<div id="sosOverlay" class="sos-overlay hidden">
  <div class="sos-modal">
    <div class="sos-modal-icon">🚨</div>
    <h2>Emergency SOS</h2>
    <p id="sosStatusText">Getting your location...</p>
    <div id="sosProgressBar" class="sos-progress"></div>
    <div id="sosActions" class="hidden" style="display:none;">
      <button id="sendWhatsApp" class="btn btn-sos" style="width:100%;margin-bottom:10px;">
        📲 Send via WhatsApp
      </button>
      <button onclick="closeSOS()" class="btn btn-outline" style="width:100%;">Cancel</button>
    </div>
    <div id="sosCancel">
      <button onclick="closeSOS()" class="btn btn-outline" style="width:100%;margin-top:16px;">✕ Cancel</button>
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
