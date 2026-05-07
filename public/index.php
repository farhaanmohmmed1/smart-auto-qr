<?php
/**
 * PUBLIC LANDING PAGE — Smart Auto QR Safety System
 * No login required. Default page for public visitors.
 * Visitors can either:
 * 1. Scan QR code using their device camera
 * 2. Search by auto number manually
 */
require_once '../config/config.php';

$error = '';
$search_query = '';

// ── Handle Search by Auto Number ──────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search_type'])) {
    $search_type = $_POST['search_type'] ?? '';
    
    if ($search_type === 'auto_number') {
        $search_query = trim($_POST['auto_number'] ?? '');
        
        if (empty($search_query)) {
            $error = 'Please enter an auto number';
        } else {
            // Query database (case-insensitive search)
            $stmt = $pdo->prepare("
                SELECT qr_token FROM autos 
                WHERE LOWER(TRIM(auto_number)) = LOWER(TRIM(?)) AND status = 'active' 
                LIMIT 1
            ");
            $stmt->execute([$search_query]);
            $auto = $stmt->fetch();
            
            if (!$auto) {
                $error = 'Auto not found or inactive';
            } else {
                // Log this search
                $ip = getClientIP();
                $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
                $pdo->prepare("INSERT INTO scan_logs (auto_number, ip_address, user_agent) VALUES (?,?,?)")
                    ->execute([$search_query, $ip, $ua]);
                
                // Redirect to auto details page
                header('Location: auto.php?token=' . urlencode($auto['qr_token']));
                exit;
            }
        }
    }
}

// ── Handle QR Code Scan (via JavaScript) ──────────────────
// Results come via AJAX to avoid page reload
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <meta name="theme-color" content="#1a237e">
    <title>Smart Auto QR Safety System - Verify Auto Details</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        :root {
            --primary:     #0d47a1;
            --primary-l:   #1565c0;
            --accent:      #1976d2;
            --sos:         #c41c3b;
            --sos-light:   #e53935;
            --success:     #2e7d32;
            --success-l:   #388e3c;
            --bg:          #f5f5f5;
            --bg2:         #ffffff;
            --card:        #ffffff;
            --card2:       #fafafa;
            --border:      #e0e0e0;
            --border2:     #d0d0d0;
            --text:        #1a1a1a;
            --muted:       #666666;
            --white:       #ffffff;
            --gold:        #f39c12;
            --dark:        #0d1117;
            --light-gray:  #f9f9f9;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            color: var(--text);
            font-family: 'Segoe UI', 'Roboto', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            letter-spacing: 0.3px;
        }

        .landing-container {
            max-width: 1220px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        .hero-section {
            background: linear-gradient(135deg, #0d47a1 0%, #1565c0 50%, #0d6eaa 100%);
            color: var(--white);
            padding: 60px 40px;
            border-radius: 12px;
            text-align: center;
            margin-bottom: 50px;
            box-shadow: 0 12px 40px rgba(13, 71, 161, 0.2);
            border-bottom: 6px solid var(--gold);
            position: relative;
            overflow: hidden;
        }

        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at top right, rgba(255,255,255,0.15), transparent);
            pointer-events: none;
        }

        .hero-section h1 {
            font-size: 42px;
            font-weight: 800;
            margin-bottom: 16px;
            letter-spacing: 0.8px;
            position: relative;
            z-index: 1;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .hero-section p {
            font-size: 17px;
            opacity: 0.97;
            margin-bottom: 10px;
            line-height: 1.9;
            position: relative;
            z-index: 1;
            font-weight: 400;
        }

        .hero-section p:last-child {
            font-size: 14px;
            opacity: 0.92;
            margin-top: 24px;
            font-weight: 500;
        }

        .hero-section p:last-child strong {
            font-weight: 800;
            color: var(--gold);
        }

        .search-methods {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 36px;
            margin-bottom: 50px;
        }

        .method-card {
            background: var(--white);
            border: 2px solid #f0f0f0;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.06);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .method-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary) 0%, var(--accent) 100%);
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.4s ease;
        }

        .method-card:hover {
            transform: translateY(-12px);
            box-shadow: 0 16px 40px rgba(13, 71, 161, 0.15);
            border-color: var(--accent);
        }

        .method-card:hover::before {
            transform: scaleX(1);
        }

        .method-icon {
            font-size: 64px;
            margin-bottom: 24px;
            display: block;
            animation: float 3s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-12px); }
        }

        .method-card h3 {
            color: var(--primary);
            font-size: 24px;
            margin-bottom: 12px;
            font-weight: 700;
            letter-spacing: 0.3px;
        }

        .method-card p {
            color: var(--muted);
            font-size: 15px;
            line-height: 1.8;
            margin-bottom: 24px;
            font-weight: 400;
        }

        .search-tabs {
            display: none;
        }

        .tab-btn {
            display: none;
        }

        .tab-content {
            display: block !important;
            animation: fadeIn 0.3s;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .qr-scanner-wrapper {
            background: linear-gradient(135deg, #f8f9fa 0%, #f0f4ff 100%);
            border: 3px dashed var(--accent);
            border-radius: 10px;
            padding: 32px;
            text-align: center;
            margin-bottom: 24px;
            min-height: 240px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            transition: all 0.3s ease;
        }

        .qr-scanner-wrapper:hover {
            background: linear-gradient(135deg, #f5f8ff 0%, #eef3ff 100%);
            border-color: var(--primary);
        }

        #qr_canvas {
            max-width: 100%;
            border-radius: 6px;
            background: var(--dark);
        }

        #qr_video {
            max-width: 100%;
            border-radius: 6px;
            background: var(--dark);
        }

        .camera-controls {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin: 24px 0 0 0;
        }

        .btn {
            padding: 14px 32px;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            text-transform: uppercase;
            letter-spacing: 1px;
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }

        .btn:active::before {
            width: 300px;
            height: 300px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-l) 100%);
            color: var(--white);
            border: 2px solid var(--primary);
            box-shadow: 0 6px 20px rgba(13, 71, 161, 0.3);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--primary-l) 0%, var(--accent) 100%);
            box-shadow: 0 8px 28px rgba(13, 71, 161, 0.4);
            transform: translateY(-3px);
        }

        .btn-primary:active {
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: var(--light-gray);
            color: var(--text);
            border: 2px solid var(--border);
        }

        .btn-secondary:hover {
            border-color: var(--accent);
            color: var(--accent);
            background: var(--white);
        }

        .search-form {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-group label {
            font-weight: 700;
            color: var(--primary);
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }

        .form-group input {
            padding: 14px 18px;
            border: 2px solid var(--border);
            border-radius: 8px;
            font-size: 15px;
            background: var(--white);
            color: var(--text);
            transition: all 0.3s;
            font-weight: 500;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 4px rgba(25, 118, 210, 0.12);
            background: #f5f8ff;
        }

        .form-group input::placeholder {
            color: #999;
        }

        .error-msg {
            background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%);
            color: #c62828;
            padding: 18px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: none;
            border-left: 5px solid #c41c3b;
            box-shadow: 0 4px 12px rgba(196, 28, 59, 0.12);
            font-weight: 500;
        }

        .error-msg.show {
            display: block;
            animation: slideInDown 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .auto-details-card {
            background: var(--white);
            border: 2px solid #f0f0f0;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.06);
            margin-top: 40px;
        }

        .auto-details-card h2 {
            color: var(--primary);
            margin-bottom: 32px;
            font-size: 28px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        .detail-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 32px;
            margin-bottom: 24px;
            padding-bottom: 20px;
            border-bottom: 1px solid #f0f0f0;
        }

        .detail-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .detail-label {
            font-weight: 700;
            color: #888;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1.2px;
        }

        .detail-value {
            color: var(--text);
            font-size: 17px;
            font-weight: 600;
            margin-top: 8px;
            line-height: 1.6;
        }

        .sos-section {
            background: linear-gradient(135deg, #c41c3b 0%, #a01528 100%);
            color: var(--white);
            padding: 40px;
            border-radius: 12px;
            margin-top: 40px;
            text-align: center;
            box-shadow: 0 12px 40px rgba(196, 28, 59, 0.25);
            border-top: 6px solid var(--gold);
            position: relative;
            overflow: hidden;
        }

        .sos-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at top right, rgba(255,255,255,0.1), transparent);
            pointer-events: none;
        }

        .sos-section h3 {
            font-size: 24px;
            margin-bottom: 12px;
            font-weight: 700;
            position: relative;
            z-index: 1;
        }

        .sos-section p {
            font-size: 15px;
            margin-bottom: 24px;
            opacity: 0.97;
            position: relative;
            z-index: 1;
            font-weight: 500;
        }

        .sos-btn {
            background: var(--white);
            color: #c41c3b;
            padding: 14px 28px;
            border: none;
            border-radius: 8px;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            margin: 10px 8px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            display: inline-block;
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2);
            position: relative;
            z-index: 1;
        }

        .sos-btn:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 24px rgba(0, 0, 0, 0.3);
        }

        .sos-btn:active {
            transform: translateY(-2px);
        }

        .info-box {
            background: linear-gradient(135deg, #e3f2fd 0%, #f1f5fe 100%);
            border-left: 4px solid var(--accent);
            padding: 16px;
            border-radius: 6px;
            margin-top: 16px;
            font-size: 13px;
            color: #0d47a1;
            border: 1px solid #bbdefb;
            box-shadow: 0 2px 8px rgba(25, 118, 210, 0.08);
        }

        .info-box strong {
            color: var(--primary);
            font-weight: 700;
        }

        #qr_camera_status {
            color: var(--muted) !important;
        }

        /* ════════════════════════════════════════════════════ */
        /* RESPONSIVE DESIGN */
        /* ════════════════════════════════════════════════════ */

        @media (max-width: 1024px) {
            .landing-container {
                padding: 16px;
            }

            .search-methods {
                gap: 24px;
            }

            .method-card {
                padding: 28px;
            }
        }

        @media (max-width: 768px) {
            .hero-section {
                padding: 48px 24px;
                margin-bottom: 40px;
            }

            .hero-section h1 {
                font-size: 32px;
                margin-bottom: 14px;
            }

            .hero-section p {
                font-size: 15px;
            }

            .search-methods {
                grid-template-columns: 1fr;
                gap: 24px;
                margin-bottom: 40px;
            }

            .method-card {
                padding: 32px;
            }

            .method-icon {
                font-size: 48px;
                margin-bottom: 18px;
            }

            .method-card h3 {
                font-size: 20px;
            }

            .detail-row {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .auto-details-card {
                padding: 32px;
                margin-top: 40px;
            }

            .auto-details-card h2 {
                font-size: 24px;
                margin-bottom: 28px;
            }

            .sos-section {
                padding: 32px;
                margin-top: 40px;
            }

            .sos-btn {
                display: block;
                width: 100%;
                margin: 10px 0;
            }

            .btn {
                width: 100%;
                padding: 14px 20px;
            }

            .camera-controls {
                flex-direction: column;
            }
        }

        @media (max-width: 600px) {
            .landing-container {
                padding: 14px;
            }

            .hero-section {
                padding: 40px 20px;
                margin-bottom: 32px;
                border-radius: 10px;
            }

            .hero-section h1 {
                font-size: 26px;
                margin-bottom: 12px;
            }

            .hero-section p {
                font-size: 14px;
                margin-bottom: 6px;
            }

            .search-methods {
                gap: 20px;
                margin-bottom: 32px;
            }

            .method-card {
                padding: 28px;
                border-radius: 10px;
            }

            .method-card:hover {
                transform: translateY(-8px);
            }

            .method-icon {
                font-size: 44px;
                margin-bottom: 16px;
            }

            .method-card h3 {
                font-size: 18px;
                margin-bottom: 10px;
            }

            .method-card p {
                font-size: 14px;
                margin-bottom: 18px;
            }

            .btn {
                padding: 13px 18px;
                font-size: 13px;
                letter-spacing: 0.5px;
            }

            .detail-row {
                margin-bottom: 18px;
                padding-bottom: 14px;
            }

            .detail-label {
                font-size: 11px;
            }

            .detail-value {
                font-size: 16px;
            }

            .auto-details-card {
                padding: 28px;
                margin-top: 32px;
            }

            .auto-details-card h2 {
                font-size: 20px;
                margin-bottom: 24px;
            }

            .sos-section {
                padding: 28px;
                margin-top: 32px;
            }

            .sos-section h3 {
                font-size: 20px;
            }

            .sos-section p {
                font-size: 13px;
                margin-bottom: 18px;
            }

            .sos-btn {
                padding: 12px 22px;
                font-size: 13px;
            }

            .form-group input {
                padding: 12px 16px;
                font-size: 15px;
            }

            .info-box {
                padding: 14px;
                font-size: 12px;
            }
        }

        @media (max-width: 480px) {
            .landing-container {
                padding: 12px;
            }

            .hero-section {
                padding: 32px 16px;
                margin-bottom: 28px;
            }

            .hero-section h1 {
                font-size: 22px;
                font-weight: 800;
            }

            .hero-section p {
                font-size: 13px;
            }

            .method-card {
                padding: 24px;
            }

            .search-methods {
                gap: 16px;
            }

            .method-icon {
                font-size: 40px;
            }

            .method-card h3 {
                font-size: 16px;
            }

            .btn {
                padding: 12px 14px;
                font-size: 12px;
            }

            .form-group input {
                font-size: 16px;
                padding: 12px 14px;
            }

            .auto-details-card {
                padding: 20px;
            }

            .sos-section {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="landing-container">
        <!-- Hero Section -->
        <div class="hero-section">
            <h1>🚖 Smart Auto QR Safety System</h1>
            <p>Verify auto-rickshaw driver details and access emergency features</p>
            <p style="font-size: 13px; opacity: 0.85;">Operated by Police Department | For Emergencies: Call <strong>100</strong></p>
        </div>

        <!-- Search Tabs (Hidden) -->
        <div class="search-tabs" style="display: none;"></div>

        <!-- Error Message -->
        <div class="error-msg" id="error_msg"></div>

        <!-- Both Methods Side-by-Side -->
        <div class="search-methods">
            <!-- QR Scanner Method -->
            <div class="method-card">
                <div class="method-icon">📱</div>
                <h3>Scan QR Code</h3>
                <p>Point your device camera at the QR code sticker on the auto-rickshaw</p>

                <div class="qr-scanner-wrapper">
                    <div id="qr_camera_status" style="color: var(--muted); padding: 20px; font-size: 14px;">
                        Click button below to start
                    </div>
                    <video id="qr_video" style="display: none; max-width: 100%; border-radius: 8px;"></video>
                    <canvas id="qr_canvas" style="display: none;"></canvas>
                </div>

                <div class="camera-controls">
                    <button class="btn btn-primary" id="start_camera_btn" style="width: 100%;">📷 Start Camera</button>
                    <button class="btn btn-secondary" id="stop_camera_btn" style="display: none; width: 100%;">⏹ Stop Camera</button>
                </div>
            </div>

            <!-- Manual Search Method -->
            <div class="method-card">
                <div class="method-icon">🔍</div>
                <h3>Search by Auto Number</h3>
                <p>Enter the auto number to search for the vehicle details</p>

                <form method="POST" class="search-form">
                    <input type="hidden" name="search_type" value="auto_number">

                    <div class="form-group">
                        <label for="auto_number">Auto Number</label>
                        <input 
                            type="text" 
                            id="auto_number" 
                            name="auto_number" 
                            placeholder="e.g., TS01-AB-0001"
                            autocomplete="off"
                            maxlength="50"
                        >
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%;">Search</button>
                </form>
            </div>
        </div>

        <!-- Error Message Display (if any) -->
        <?php if ($error && $search_query): ?>
            <div style="margin-top: 32px;">
                <div class="auto-details-card">
                    <div style="text-align: center; color: #c62828; padding: 48px 20px;">
                        <p style="font-size: 56px; margin-bottom: 16px;">❌</p>
                        <h2 style="color: #c62828; margin-bottom: 12px; font-size: 22px;">Not Found</h2>
                        <p style="color: #666; margin-bottom: 24px;"><?= e($error) ?></p>
                        <button onclick="location.reload()" class="btn btn-primary">Try Again</button>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- QR Code Scanning Library -->
    <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js"></script>

    <script>
        let video = document.getElementById('qr_video');
        let canvas = document.getElementById('qr_canvas');
        let cameraActive = false;
        let animationId = null;

        const startBtn = document.getElementById('start_camera_btn');
        const stopBtn = document.getElementById('stop_camera_btn');
        const statusDiv = document.getElementById('qr_camera_status');
        const errorDiv = document.getElementById('error_msg');

        // Start Camera
        startBtn.addEventListener('click', startCamera);
        function startCamera() {
            navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
                .then(stream => {
                    cameraActive = true;
                    video.srcObject = stream;
                    video.setAttribute('playsinline', true);
                    video.play();
                    video.style.display = 'block';
                    statusDiv.textContent = '📷 Camera is active - Position QR code in frame';
                    statusDiv.style.color = '#2e7d32';
                    startBtn.style.display = 'none';
                    stopBtn.style.display = 'block';
                    scanQRCode();
                })
                .catch(err => {
                    statusDiv.textContent = '❌ Cannot access camera: ' + err.message;
                    statusDiv.style.color = '#c62828';
                    console.error('Camera error:', err);
                });
        }

        // Stop Camera
        stopBtn.addEventListener('click', stopCamera);
        function stopCamera() {
            if (video.srcObject) {
                video.srcObject.getTracks().forEach(track => track.stop());
            }
            video.style.display = 'none';
            canvas.style.display = 'none';
            statusDiv.textContent = 'Click button below to start';
            statusDiv.style.color = 'var(--muted)';
            startBtn.style.display = 'block';
            stopBtn.style.display = 'none';
            cameraActive = false;
            
            if (animationId) {
                cancelAnimationFrame(animationId);
            }
        }

        // Scan QR Code
        function scanQRCode() {
            if (!cameraActive) return;

            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;

            const ctx = canvas.getContext('2d');
            ctx.drawImage(video, 0, 0);

            const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
            const code = jsQR(imageData.data, imageData.width, imageData.height, {
                inversionAttempts: "dontInvert"
            });

            if (code) {
                // QR code found
                const scannedToken = code.data;
                console.log('QR Code Detected:', scannedToken);
                
                stopCamera();
                
                // Redirect to the scanned auto details page
                if (scannedToken.includes('token=')) {
                    window.location.href = 'auto.php?' + scannedToken.split('?')[1];
                } else {
                    window.location.href = 'auto.php?token=' + encodeURIComponent(scannedToken);
                }
            } else {
                animationId = requestAnimationFrame(scanQRCode);
            }
        }

        // Show/hide error message
        function showError(msg) {
            errorDiv.textContent = msg;
            errorDiv.classList.add('show');
            setTimeout(() => {
                errorDiv.classList.remove('show');
            }, 5000);
        }

        // Display errors from form submission
        <?php if ($error && !$auto_data): ?>
            showError('<?= json_encode($error) ?>');
        <?php endif; ?>
    </script>
</body>
</html>
