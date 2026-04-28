<?php
/**
 * PDF / Print Page Generator
 * Generates a printable HTML sticker page for each auto.
 * Uses browser print-to-PDF (works on all devices, no library needed).
 */

class PDFGenerator
{
    /**
     * Output a self-contained HTML print page for an auto.
     * User hits Ctrl+P or the Print button to save as PDF.
     */
    public static function renderPrintPage(array $auto, string $qrBase64): void
    {
        $autoNum    = e($auto['auto_number']);
        $driverName = e($auto['driver_name']);
        $regNum     = e($auto['reg_number']);
        $phone      = e($auto['phone']);
        $area       = e($auto['area'] ?? '');
        $helpline   = HELPLINE;
        $appName    = APP_NAME;

        echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>QR Sticker – {$autoNum}</title>
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  body { background:#f0f0f0; display:flex; justify-content:center; align-items:center; min-height:100vh; font-family:'Segoe UI',Arial,sans-serif; }
  
  .sticker {
    background:#fff;
    width:90mm;
    padding:8mm;
    border-radius:4mm;
    box-shadow:0 4px 20px rgba(0,0,0,0.15);
    text-align:center;
    border:2px solid #1a237e;
    page-break-inside:avoid;
  }

  .header {
    background:#1a237e;
    color:#fff;
    padding:6px 10px;
    border-radius:3mm;
    margin-bottom:6px;
  }
  .header .title { font-size:10pt; font-weight:700; letter-spacing:1px; }
  .header .sub   { font-size:7pt; opacity:0.85; margin-top:2px; }

  .auto-number {
    font-size:22pt;
    font-weight:900;
    color:#1a237e;
    letter-spacing:2px;
    margin:6px 0;
    border:3px solid #1a237e;
    border-radius:3mm;
    padding:4px;
    background:#e8eaf6;
  }

  .qr-wrap { margin:8px auto; }
  .qr-wrap img { width:55mm; height:55mm; display:block; margin:0 auto; }

  .scan-text {
    font-size:8pt;
    color:#555;
    margin:4px 0 8px;
    font-style:italic;
  }

  .driver-info {
    background:#f5f5f5;
    border-radius:3mm;
    padding:6px 8px;
    margin-bottom:6px;
    text-align:left;
    font-size:8pt;
  }
  .driver-info .label { color:#777; font-size:7pt; }
  .driver-info .value { font-weight:600; color:#1a1a2e; font-size:9pt; }
  .driver-row { display:flex; justify-content:space-between; margin-bottom:3px; }

  .footer {
    background:#d32f2f;
    color:#fff;
    border-radius:3mm;
    padding:5px;
    font-size:8pt;
    font-weight:700;
  }
  .footer .sos { font-size:12pt; }

  .verified {
    display:inline-block;
    background:#2e7d32;
    color:#fff;
    border-radius:10mm;
    padding:2px 10px;
    font-size:7pt;
    font-weight:600;
    margin-bottom:4px;
  }

  /* Print-specific */
  @media print {
    body { background:#fff; margin:0; padding:0; }
    .no-print { display:none !important; }
    .sticker { box-shadow:none; margin:0 auto; border-color:#1a237e; }
    @page { size:A5; margin:10mm; }
  }
</style>
</head>
<body>

<div class="sticker">
  <div class="header">
    <div class="title">🚖 {$appName}</div>
    <div class="sub">Government Verified Auto-Rickshaw</div>
  </div>

  <div class="auto-number">{$autoNum}</div>
  <span class="verified">✔ POLICE REGISTERED</span>

  <div class="qr-wrap">
    <img src="{$qrBase64}" alt="QR Code for {$autoNum}">
  </div>

  <p class="scan-text">📱 Scan QR code to verify driver &amp; vehicle details</p>

  <div class="driver-info">
    <div class="driver-row">
      <div>
        <div class="label">DRIVER NAME</div>
        <div class="value">{$driverName}</div>
      </div>
      <div style="text-align:right;">
        <div class="label">VEHICLE REG.</div>
        <div class="value">{$regNum}</div>
      </div>
    </div>
    <div class="driver-row">
      <div>
        <div class="label">MOBILE</div>
        <div class="value">{$phone}</div>
      </div>
      <div style="text-align:right;">
        <div class="label">AREA</div>
        <div class="value">{$area}</div>
      </div>
    </div>
  </div>

  <div class="footer">
    <div class="sos">🚨 EMERGENCY: <span>{$helpline}</span></div>
    <div style="font-size:7pt;margin-top:2px;">Police Helpline | SOS button available after scan</div>
  </div>
</div>

<!-- Print Button (hidden in print) -->
<div class="no-print" style="position:fixed;top:20px;right:20px;">
  <button onclick="window.print()" style="
    padding:12px 24px;
    background:#1a237e;
    color:#fff;
    border:none;
    border-radius:8px;
    font-size:14px;
    font-weight:700;
    cursor:pointer;
    box-shadow:0 4px 15px rgba(0,0,0,0.2);
  ">🖨 Print / Save as PDF</button>
  <button onclick="window.close()" style="
    margin-left:10px;
    padding:12px 24px;
    background:#fff;
    color:#1a237e;
    border:2px solid #1a237e;
    border-radius:8px;
    font-size:14px;
    font-weight:700;
    cursor:pointer;
  ">✕ Close</button>
</div>

<script>
  // Auto-trigger print dialog
  setTimeout(() => {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('autoprint') === '1') { window.print(); }
  }, 800);
</script>
</body>
</html>
HTML;
    }
}
