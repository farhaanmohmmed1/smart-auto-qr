<?php
/**
 * Bulk Upload - Admin Interface
 * =============================
 * Allows bulk import of auto-rickshaw data from:
 * - Excel (.xlsx, .xls)
 * - CSV files
 * - Google Sheets (exported as CSV)
 */

require_once '../config/config.php';
require_once '../lib/ImportHandler.php';
require_once '../lib/QRGenerator.php';

requireAdmin();

// ── Handle template download ──────────────────────────────
if (isset($_GET['download_template'])) {
    downloadTemplate();
    exit;
}

/**
 * Generate and download sample template CSV
 */
function downloadTemplate() {
    $filename = 'auto_import_template_' . date('Y-m-d') . '.csv';
    
    header('Content-Type: text/csv; charset=utf-8');
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    
    // Header row
    fputcsv($output, [
        'Auto Number',
        'Registration Number',
        'Driver Name',
        'Phone Number',
        'License Number',
        'Permit Number',
        'Area',
        'Stand'
    ]);
    
    // Sample rows
    $samples = [
        ['AUTO-001', 'TS09EA1234', 'Ramesh Kumar', '9876543210', 'TS14DL20190001', 'HYD/PERMIT/2024/001', 'Ameerpet', 'Ameerpet Stand'],
        ['AUTO-002', 'TS09EB5678', 'Suresh Reddy', '9845612345', 'TS14DL20200045', 'HYD/PERMIT/2024/002', 'Kukatpally', 'KPHB Colony Stand'],
        ['AUTO-003', 'TS09EC9101', 'Mahesh Yadav', '9912378456', 'TS14DL20180023', 'HYD/PERMIT/2024/003', 'Secunderabad', 'Clock Tower Stand'],
        ['AUTO-004', 'TS09ED1121', 'Venkat Rao', '9900087654', 'TS14DL20210067', 'HYD/PERMIT/2024/004', 'Hitech City', 'Cyber Tower Stand'],
        ['AUTO-005', 'TS09EE3141', 'Naresh Sharma', '9988776655', 'TS14DL20220012', 'HYD/PERMIT/2024/005', 'LB Nagar', 'LB Nagar Stand'],
    ];
    
    foreach ($samples as $row) {
        fputcsv($output, $row);
    }
    
    fclose($output);
}

// ── Handle file upload ────────────────────────────────────
$importResults = null;
$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Token validation
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'CSRF token invalid. Please try again.';
    } elseif (empty($_FILES['import_file']['name'])) {
        $error = 'Please select a file to upload.';
    } else {
        // Validate file format
        $filename = $_FILES['import_file']['name'];
        $fileExt = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $allowedFormats = ['xlsx', 'xls', 'csv', 'txt'];
        
        if (!in_array($fileExt, $allowedFormats)) {
            $error = "❌ Invalid file format! Allowed formats: " . implode(', ', array_map(fn($f) => strtoupper($f), $allowedFormats));
        } else {
            // Process the import
            try {
                $adminId = $_SESSION['admin_id'] ?? null;
                if (!$adminId) {
                    $error = 'Admin session not found. Please login again.';
                } else {
                    $handler = new ImportHandler($pdo, $adminId);
                    $importResults = $handler->importFile($_FILES['import_file']);
                    
                    if (!$importResults['success']) {
                        $error = $importResults['message'];
                    } else {
                        $success = "✅ Imported {$importResults['successful']} records successfully. ";
                        if ($importResults['skipped'] > 0) {
                            $success .= "{$importResults['skipped']} skipped (duplicates). ";
                        }
                        if ($importResults['errors'] > 0) {
                            $success .= "{$importResults['errors']} errors.";
                        }
                    }
                }
            } catch (Exception $e) {
                $error = 'Import failed: ' . $e->getMessage();
            }
        }
    }
}

// Get CSRF token for form
$csrf_token = generateCSRFToken();

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Bulk Upload | <?= APP_NAME ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/admin.css">
  <style>
    .upload-section {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 30px;
      margin-bottom: 30px;
    }
    
    @media (max-width: 768px) {
      .upload-section { grid-template-columns: 1fr; }
    }
    
    .upload-box {
      border: 2px dashed #3949ab;
      border-radius: 12px;
      padding: 30px;
      text-align: center;
      background: rgba(57,73,171,0.05);
      cursor: pointer;
      transition: all 0.3s;
    }
    
    .upload-box:hover {
      border-color: #1a237e;
      background: rgba(26,35,126,0.1);
      transform: translateY(-2px);
    }
    
    .upload-box.dragover {
      border-color: #d32f2f;
      background: rgba(211,47,47,0.1);
    }
    
    .upload-icon {
      font-size: 40px;
      margin-bottom: 15px;
    }
    
    .upload-box h3 {
      margin: 15px 0;
      font-size: 1.1rem;
    }
    
    .upload-box p {
      color: #7d8590;
      font-size: 0.9rem;
      margin: 10px 0;
    }
    
    .form-hint {
      background: rgba(33,150,243,0.1);
      border-left: 3px solid #2196F3;
      padding: 12px;
      border-radius: 4px;
      margin: 15px 0;
      font-size: 0.9rem;
      color: #1976D2;
    }
    
    .results-table {
      margin-top: 20px;
      overflow-x: auto;
    }
    
    .results-table table {
      width: 100%;
      border-collapse: collapse;
    }
    
    .results-table th {
      background: #161b22;
      padding: 12px;
      text-align: left;
      font-weight: 600;
      border-bottom: 2px solid #30363d;
    }
    
    .results-table td {
      padding: 10px 12px;
      border-bottom: 1px solid #30363d;
    }
    
    .status-badge {
      display: inline-block;
      padding: 4px 8px;
      border-radius: 4px;
      font-size: 0.85rem;
      font-weight: 600;
    }
    
    .status-success { background: #238636; color: white; }
    .status-skip { background: #9e6a03; color: white; }
    .status-error { background: #da3633; color: white; }
    
    .summary-card {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 15px;
      margin-bottom: 25px;
    }
    
    @media (max-width: 768px) {
      .summary-card { grid-template-columns: repeat(2, 1fr); }
    }
    
    .summary-stat {
      background: #161b22;
      border: 1px solid #30363d;
      padding: 20px;
      border-radius: 8px;
      text-align: center;
    }
    
    .summary-stat .number {
      font-size: 2rem;
      font-weight: 700;
      margin-bottom: 5px;
    }
    
    .summary-stat .label {
      font-size: 0.9rem;
      color: #7d8590;
    }
  </style>
</head>
<body>
<?php include 'partials/sidebar.php'; ?>
<div class="main-wrapper">
  <?php include 'partials/topbar.php'; ?>
  <div class="page-content">
    
    <div class="page-header">
      <div>
        <h1 class="page-title">📊 Bulk Import Autos</h1>
        <p class="page-sub">Upload multiple vehicle records at once from Excel or CSV</p>
      </div>
      <div style="display: flex; gap: 10px;">
        <a href="manage.php" class="btn btn-outline">← Manage Autos</a>
        <a href="?download_template=1" class="btn btn-outline">📥 Sample Template</a>
      </div>
    </div>

    <!-- Alerts -->
    <?php if ($error): ?>
      <div class="alert alert-danger">
        ⚠️ <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <?php if ($success): ?>
      <div class="alert alert-success">
        ✅ <?= htmlspecialchars($success) ?>
      </div>
    <?php endif; ?>

    <!-- Upload Zone -->
    <div class="card">
      <div class="card-header">
        <h2 class="card-title">Choose File to Import</h2>
      </div>
      <div class="card-body">
        <form method="POST" enctype="multipart/form-data" id="uploadForm">
          <!-- CSRF Token -->
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRFToken()) ?>">
          
          <div class="upload-section">
            <!-- Unified Upload -->
            <div class="upload-box" id="uploadBox" onclick="document.getElementById('fileInput').click()" style="grid-column: 1 / -1;">
              <div class="upload-icon">📊</div>
              <h3>Import Auto Data</h3>
              <p>.xlsx, .xls, .csv</p>
              <p style="font-size: 0.8rem; color: #8b949e; margin-top: 10px;">Click to browse or drag & drop</p>
              <input type="file" id="fileInput" name="import_file" accept=".xlsx,.xls,.csv,.txt" style="display:none">
            </div>
          </div>

          <div class="form-hint">
            💡 <strong>Tip:</strong> The first row should be headers. Maximum file size: 50MB. 
            Each file can contain 1,000–100,000 records.
          </div>

          <button type="submit" class="btn btn-primary btn-block" id="submitBtn" style="display:none; margin-top: 20px;">
            Upload & Import
          </button>
        </form>
      </div>
    </div>

    <!-- Import Guide -->
    <div class="card" style="margin-top: 25px;">
      <div class="card-header">
        <h2 class="card-title">📋 Column Requirements</h2>
      </div>
      <div class="card-body">
        <div style="overflow-x: auto;">
          <table style="width: 100%; border-collapse: collapse;">
            <thead>
              <tr style="background: rgba(255,255,255,0.05);">
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #30363d;">Column</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #30363d;">Required</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #30363d;">Format</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #30363d;">Example</th>
              </tr>
            </thead>
            <tbody>
              <tr style="border-bottom: 1px solid #30363d;">
                <td style="padding: 12px;"><strong>Auto Number</strong></td>
                <td style="padding: 12px;">✅ Required</td>
                <td style="padding: 12px;">Alphanumeric, 3-20 chars</td>
                <td style="padding: 12px;"><code>AUTO-001</code></td>
              </tr>
              <tr style="border-bottom: 1px solid #30363d;">
                <td style="padding: 12px;"><strong>Reg Number</strong></td>
                <td style="padding: 12px;">✅ Required</td>
                <td style="padding: 12px;">Vehicle registration</td>
                <td style="padding: 12px;"><code>TS09EA1234</code></td>
              </tr>
              <tr style="border-bottom: 1px solid #30363d;">
                <td style="padding: 12px;"><strong>Driver Name</strong></td>
                <td style="padding: 12px;">✅ Required</td>
                <td style="padding: 12px;">3-100 characters</td>
                <td style="padding: 12px;"><code>Ramesh Kumar</code></td>
              </tr>
              <tr style="border-bottom: 1px solid #30363d;">
                <td style="padding: 12px;"><strong>Phone</strong></td>
                <td style="padding: 12px;">✅ Required</td>
                <td style="padding: 12px;">10-12 digits</td>
                <td style="padding: 12px;"><code>9876543210</code></td>
              </tr>
              <tr style="border-bottom: 1px solid #30363d;">
                <td style="padding: 12px;"><strong>License Number</strong></td>
                <td style="padding: 12px;">✅ Required</td>
                <td style="padding: 12px;">Driver license ID</td>
                <td style="padding: 12px;"><code>TS14DL20190001</code></td>
              </tr>
              <tr style="border-bottom: 1px solid #30363d;">
                <td style="padding: 12px;"><strong>Permit Number</strong></td>
                <td style="padding: 12px;">✅ Required</td>
                <td style="padding: 12px;">Operating permit ID</td>
                <td style="padding: 12px;"><code>HYD/PERMIT/2024/001</code></td>
              </tr>
              <tr style="border-bottom: 1px solid #30363d;">
                <td style="padding: 12px;"><strong>Area</strong></td>
                <td style="padding: 12px;">⚪ Optional</td>
                <td style="padding: 12px;">Operating zone/area</td>
                <td style="padding: 12px;"><code>Ameerpet</code></td>
              </tr>
              <tr>
                <td style="padding: 12px;"><strong>Stand</strong></td>
                <td style="padding: 12px;">⚪ Optional</td>
                <td style="padding: 12px;">Auto stand/depot name</td>
                <td style="padding: 12px;"><code>Ameerpet Stand</code></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Google Sheets Guide -->
    <div class="card" style="margin-top: 25px;">
      <div class="card-header">
        <h2 class="card-title">📊 How to Use Google Sheets</h2>
      </div>
      <div class="card-body">
        <ol style="line-height: 1.8; margin-left: 20px;">
          <li><strong>Create your spreadsheet</strong> in Google Sheets with the columns above</li>
          <li><strong>Add your data</strong> with one auto-rickshaw per row</li>
          <li><strong>Share the sheet</strong> (not necessary for export)</li>
          <li><strong>Download as CSV:</strong>
            <ul>
              <li>File → Download → Comma Separated Values (.csv)</li>
            </ul>
          </li>
          <li><strong>Upload the CSV file</strong> here using the form above</li>
        </ol>
        <div class="form-hint" style="margin-top: 20px;">
          💡 <strong>Tip:</strong> Copy our sample template into Google Sheets and share with your team for collaborative data entry!
        </div>
      </div>
    </div>

    <!-- Import Results -->
    <?php if ($importResults && $importResults['success']): ?>
      <div class="card" style="margin-top: 25px;">
        <div class="card-header">
          <h2 class="card-title">✅ Import Results</h2>
        </div>
        <div class="card-body">
          
          <!-- Summary Stats -->
          <div class="summary-card">
            <div class="summary-stat">
              <div class="number" style="color: #2da44e;"><?= $importResults['successful'] ?></div>
              <div class="label">✅ Successful</div>
            </div>
            <div class="summary-stat">
              <div class="number" style="color: #d29922;"><?= $importResults['skipped'] ?></div>
              <div class="label">⚠️ Skipped</div>
            </div>
            <div class="summary-stat">
              <div class="number" style="color: #da3633;"><?= $importResults['errors'] ?></div>
              <div class="label">❌ Errors</div>
            </div>
            <div class="summary-stat">
              <div class="number"><?= $importResults['total'] ?></div>
              <div class="label">📊 Total</div>
            </div>
          </div>

          <!-- Detailed Results Table -->
          <?php if (!empty($importResults['details'])): ?>
            <div class="results-table">
              <table>
                <thead>
                  <tr>
                    <th>Row</th>
                    <th>Auto Number</th>
                    <th>Status</th>
                    <th>Details</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($importResults['details'] as $detail): ?>
                    <tr>
                      <td><?= (int)$detail['row'] ?></td>
                      <td><?= htmlspecialchars($detail['auto'] ?? 'N/A') ?></td>
                      <td>
                        <span class="status-badge status-<?= htmlspecialchars($detail['status']) ?>">
                          <?php
                            if ($detail['status'] === 'success') echo '✅ Success';
                            elseif ($detail['status'] === 'skip') echo '⚠️ Skipped';
                            else echo '❌ Error';
                          ?>
                        </span>
                      </td>
                      <td style="font-size: 0.9rem;"><?= htmlspecialchars($detail['message']) ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
          
          <div style="margin-top: 20px; display: flex; gap: 10px;">
            <a href="manage.php" class="btn btn-primary">View All Autos</a>
            <a href="bulk_upload.php" class="btn btn-outline">Import More</a>
          </div>
        </div>
      </div>
    <?php endif; ?>

  </div>
</div>

<script>
// Drag & Drop functionality
const uploadBox = document.getElementById('uploadBox');
const fileInput = document.getElementById('fileInput');
const submitBtn = document.getElementById('submitBtn');

uploadBox.addEventListener('dragover', (e) => {
  e.preventDefault();
  uploadBox.classList.add('dragover');
});

uploadBox.addEventListener('dragleave', () => {
  uploadBox.classList.remove('dragover');
});

uploadBox.addEventListener('drop', (e) => {
  e.preventDefault();
  uploadBox.classList.remove('dragover');
  
  if (e.dataTransfer.files.length > 0) {
    const dataTransfer = new DataTransfer();
    dataTransfer.items.add(e.dataTransfer.files[0]);
    fileInput.files = dataTransfer.files;
    submitBtn.style.display = 'block';
    uploadBox.style.borderColor = '#2da44e';
    uploadBox.style.background = 'rgba(45,164,78,0.1)';
  }
});

fileInput.addEventListener('change', () => {
  if (fileInput.files.length > 0) {
    submitBtn.style.display = 'block';
    uploadBox.style.borderColor = '#2da44e';
    uploadBox.style.background = 'rgba(45,164,78,0.1)';
  }
});

// Form submission with validation
document.getElementById('uploadForm').addEventListener('submit', function(e) {
  if (!fileInput.files.length) {
    e.preventDefault();
    alert('Please select a file to upload');
    return false;
  }
  
  // Validate file format on client side
  const file = fileInput.files[0];
  const allowedFormats = ['xlsx', 'xls', 'csv', 'txt'];
  const fileExt = file.name.split('.').pop().toLowerCase();
  
  if (!allowedFormats.includes(fileExt)) {
    e.preventDefault();
    alert('❌ Invalid file format!\n\nAllowed formats: ' + allowedFormats.map(f => f.toUpperCase()).join(', ') + '\n\nYou selected: .' + fileExt);
    return false;
  }
});
</script>
<script src="assets/js/admin.js"></script>
</body>
</html>
