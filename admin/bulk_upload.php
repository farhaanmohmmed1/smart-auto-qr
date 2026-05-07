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
        'Driver Name',
        'Phone Number',
        'Registration Number',
        'License Number',
        'Permit Number',
        'Area',
        'Stand',
        'Security'
    ]);
    
    // Sample rows
    $samples = [
        ['AP 39 TB 5780', 'THONDA LAKSHMI NARASIMHARAO', '9177233696', 'AP39TB5780', '', '', 'Hytech', 'High Tech Bus Stand', 'caution'],
        ['AP 39 UH 3856', 'DAVULURI PURUSHOTHAMA RAO', '9845612345', 'AP39UH3856', 'TS14DL20200045', 'HYD/PERMIT/2024/002', 'Kukatpally', 'KPHB Colony Stand', 'safe'],
        ['AP05 TJ 0633', 'ERUSUMALLA VASU', '9912378456', 'AP05TJ0633', 'TS14DL20180023', 'HYD/PERMIT/2024/003', 'Secunderabad', 'Clock Tower Stand', 'safe'],
        ['AP 39 UQ 9305', 'KOLLI BALAYOGI', '9900087654', 'AP39UQ9305', 'TS14DL20210067', 'HYD/PERMIT/2024/004', 'Hitech City', 'Cyber Tower Stand', 'safe'],
        ['AP 31 TF 2581', 'Neeli Veera Venkata Satyanarayana', '9988776655', 'AP31TF2581', 'TS14DL20220012', 'HYD/PERMIT/2024/005', 'LB Nagar', 'LB Nagar Stand', 'safe'],
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
                        $error = $importResults['error'] ?? $importResults['message'] ?? 'Unknown error occurred';
                    } else {
                        $success = "✅ Imported " . ($importResults['successful'] ?? 0) . " records successfully. ";
                        if (($importResults['error_count'] ?? 0) > 0) {
                            $success .= ($importResults['error_count'] ?? 0) . " errors.";
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
  <link rel="stylesheet" href="assets/css/admin.css">
  <style>
    .upload-box {
      border: 2px dashed #0d47a1;
      padding: 40px;
      text-align: center;
      background-color: #f9f9f9;
      cursor: pointer;
      border-radius: 5px;
      margin: 20px 0;
    }

    .upload-icon {
      font-size: 48px;
      margin-bottom: 15px;
    }

    .upload-box h3 {
      margin: 10px 0;
      font-size: 1.2rem;
      color: #0d47a1;
    }

    .upload-box p {
      color: #666;
      margin: 5px 0;
      font-size: 0.9rem;
    }

    .results-table {
      margin-top: 20px;
      overflow-x: auto;
    }

    .results-table table {
      width: 100%;
      border-collapse: collapse;
      background: white;
    }

    .results-table th {
      background-color: #0d47a1;
      color: white;
      padding: 12px;
      text-align: left;
      font-weight: 600;
    }

    .results-table td {
      padding: 10px 12px;
      border-bottom: 1px solid #e0e0e0;
    }

    .status-badge {
      display: inline-block;
      padding: 5px 10px;
      border-radius: 3px;
      font-size: 0.75rem;
      font-weight: 600;
    }

    .status-success {
      background-color: #c8e6c9;
      color: #2e7d32;
    }

    .status-error {
      background-color: #ffcdd2;
      color: #c41c3b;
    }

    .summary-stat {
      background-color: white;
      border: 1px solid #ddd;
      padding: 20px;
      border-radius: 5px;
      text-align: center;
    }

    .summary-stat .number {
      font-size: 2rem;
      font-weight: 700;
      color: #0d47a1;
      margin-bottom: 5px;
    }

    .summary-stat .label {
      font-size: 0.85rem;
      color: #666;
      font-weight: 600;
    }

    .tab-btn {
      padding: 10px 15px;
      background: none;
      border: none;
      color: #666;
      cursor: pointer;
      font-weight: 600;
      border-bottom: 3px solid transparent;
      margin-right: 5px;
    }

    .tab-btn.active {
      color: #0d47a1;
      border-bottom-color: #0d47a1;
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
          
          <div class="upload-box" id="uploadBox" onclick="document.getElementById('fileInput').click()">
            <div class="upload-icon">📊</div>
            <h3>Select File to Import</h3>
            <p>.csv (Recommended) | .xlsx, .xls</p>
            <p style="font-size: 0.85rem; color: #999; margin-top: 8px;">Click to browse or drag & drop</p>
            <input type="file" id="fileInput" name="import_file" accept=".xlsx,.xls,.csv,.txt" style="display:none">
          </div>

          <div class="form-hint">
            💡 <strong>Tip:</strong> First row should be headers. Max 50MB. CSV format recommended and works out of the box.
          </div>

          <div class="info-yellow">
            <strong>📝 Using CSV Format:</strong> Export your Excel file as CSV. File → Export as → CSV.
          </div>

          <div class="info-blue">
            <strong>ℹ️ Excel Support:</strong> For .xlsx or .xls files, contact admin to install Excel support.
          </div>

          <div style="margin-top: 20px;">
            <button type="submit" class="btn btn-primary btn-block" id="submitBtn" style="padding: 12px 20px; font-size: 1rem; font-weight: 600; border-radius: 5px;">
              📤 Upload & Import
            </button>
            <div id="fileInfo" style="display: none; margin-top: 10px;">
              <p style="color: #0d47a1; font-weight: 600;"><strong>✓ File selected:</strong> <span id="fileName"></span></p>
            </div>
          </div>
        </form>
      </div>
    </div>

  </div>
</div>

<script>
  const fileInput = document.getElementById('fileInput');
  const submitBtn = document.getElementById('submitBtn');
  const fileInfo = document.getElementById('fileInfo');
  const fileName = document.getElementById('fileName');
  const uploadBox = document.getElementById('uploadBox');

  // Disable submit button initially
  submitBtn.disabled = true;
  submitBtn.style.opacity = '0.6';

  // File selection
  fileInput.addEventListener('change', (e) => {
    if (e.target.files.length > 0) {
      fileName.textContent = e.target.files[0].name;
      fileInfo.style.display = 'block';
      submitBtn.disabled = false;
      submitBtn.style.opacity = '1';
    }
  });

  // Drag & drop
  uploadBox.addEventListener('dragover', (e) => {
    e.preventDefault();
    uploadBox.style.backgroundColor = '#f0f4ff';
  });

  uploadBox.addEventListener('dragleave', () => {
    uploadBox.style.backgroundColor = '#f9f9f9';
  });

  uploadBox.addEventListener('drop', (e) => {
    e.preventDefault();
    uploadBox.style.backgroundColor = '#f9f9f9';
    
    if (e.dataTransfer.files.length > 0) {
      const file = e.dataTransfer.files[0];
      const dataTransfer = new DataTransfer();
      dataTransfer.items.add(file);
      fileInput.files = dataTransfer.files;
      
      fileName.textContent = file.name;
      fileInfo.style.display = 'block';
      submitBtn.disabled = false;
      submitBtn.style.opacity = '1';
    }
  });
</script>

    <!-- Import Results -->
    <?php if ($importResults && $importResults['success']): ?>
      <div class="card" style="margin-top: 25px;">
        <div class="card-header">
          <h2 class="card-title">✅ Import Report</h2>
        </div>
        <div class="card-body">
          
          <!-- Detected Columns Info -->
          <?php if (!empty($importResults['detected_columns'])): ?>
            <div class="info-blue" style="margin-bottom: 24px;">
              <strong>📋 Detected Columns:</strong>
              <ul style="margin: 10px 0 0 20px; font-size: 0.9rem; font-weight: 500;">
                <?php foreach ($importResults['detected_columns'] as $fieldName => $colIndex): ?>
                  <li><?= ucwords(str_replace('_', ' ', htmlspecialchars($fieldName))) ?> (Column <?= $colIndex + 1 ?>)</li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>
          
          <!-- Summary Statement -->
          <div class="info-success" style="margin-bottom: 28px; padding: 18px 20px; font-size: 1.05rem;">
            <p style="margin: 0; line-height: 1.7;">
              <strong>✅ Import Summary:</strong><br>
              <?php
                $total = $importResults['total'] ?? 0;
                $successful = $importResults['successful'] ?? 0;
                $errors = $importResults['error_count'] ?? 0;
                
                $summary = "Out of <strong>{$total} total records</strong>, ";
                $summary .= "<strong>{$successful} were successfully processed</strong> ";
                
                if ($errors > 0) {
                  $summary .= "and <strong>{$errors} had validation errors</strong>";
                } else {
                  $summary .= "with no validation errors";
                }
                
                $summary .= ".";
                echo $summary;
              ?>
            </p>
          </div>
          
          <!-- Summary Stats Cards -->
          <div class="summary-card">
            <div class="summary-stat">
              <div class="number" style="color: #2da44e;"><?= $importResults['successful'] ?? 0 ?></div>
              <div class="label">✅ Successfully Processed</div>
            </div>
            <div class="summary-stat">
              <div class="number" style="color: #da3633;"><?= $importResults['error_count'] ?? 0 ?></div>
              <div class="label">❌ Validation Errors</div>
            </div>
            <div class="summary-stat">
              <div class="number"><?= $importResults['total'] ?? 0 ?></div>
              <div class="label">📊 Total Processed</div>
            </div>
          </div>

          <!-- Tabbed Results Section -->
          <div style="margin-top: 32px;">
            <div style="display: flex; gap: 12px; margin-bottom: 20px; border-bottom: 2px solid var(--border-light); flex-wrap: wrap;">
              <button class="tab-btn active" data-tab="all" style="border-bottom-color: var(--accent);">
                📊 All Records (<?= $importResults['total'] ?? 0 ?>)
              </button>
              <button class="tab-btn" data-tab="success">
                ✅ Added (<?= $importResults['successful'] ?? 0 ?>)
              </button>

              <?php if (($importResults['error_count'] ?? 0) > 0): ?>
                <button class="tab-btn" data-tab="errors">
                  ❌ Errors (<?= $importResults['error_count'] ?? 0 ?>)
                </button>
              <?php endif; ?>
            </div>

            <!-- Detailed Results Table -->
            <?php if (!empty($importResults['details'])): ?>
              <div id="tab-all" class="tab-content" style="display: block;">
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
                                else echo '❌ Error';
                              ?>
                            </span>
                          </td>
                          <td style="font-size: 0.9rem; color: var(--text-muted);font-weight: 500;"><?= htmlspecialchars($detail['message']) ?></td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>

              <!-- Success Records Tab -->
              <div id="tab-success" class="tab-content" style="display: none;">
                <div class="results-table">
                  <table>
                    <thead>
                      <tr>
                        <th>Row</th>
                        <th>Auto Number</th>
                        <th>Details</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php 
                        $successCount = 0;
                        foreach ($importResults['details'] as $detail): 
                          if ($detail['status'] === 'success'): 
                            $successCount++;
                      ?>
                        <tr style="background: rgba(45,194,107,0.05);">
                          <td><?= (int)$detail['row'] ?></td>
                          <td><strong><?= htmlspecialchars($detail['auto']) ?></strong></td>
                          <td style="font-size: 0.9rem; color: #2da44e;">✅ <?= htmlspecialchars($detail['message']) ?></td>
                        </tr>
                      <?php endif; endforeach; ?>
                      <?php if ($successCount === 0): ?>
                        <tr><td colspan="3" style="text-align: center; padding: 20px; color: #8b949e;">No successful records</td></tr>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>
              </div>

              <!-- Errors Tab -->
              <?php if ($importResults['error_count'] > 0): ?>
                <div id="tab-errors" class="tab-content" style="display: none;">
                  <div class="results-table">
                    <table>
                      <thead>
                        <tr>
                          <th>Row</th>
                          <th>Auto Number</th>
                          <th>Error Message</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php 
                          $errorCount = 0;
                          foreach ($importResults['details'] as $detail): 
                            if ($detail['status'] === 'error'): 
                              $errorCount++;
                        ?>
                          <tr style="background: rgba(218,54,51,0.05);">
                            <td><?= (int)$detail['row'] ?></td>
                            <td><strong><?= htmlspecialchars($detail['auto'] ?? 'N/A') ?></strong></td>
                            <td style="font-size: 0.9rem; color: #da3633;">❌ <?= htmlspecialchars($detail['message']) ?></td>
                          </tr>
                        <?php endif; endforeach; ?>
                        <?php if ($errorCount === 0): ?>
                          <tr><td colspan="3" style="text-align: center; padding: 20px; color: #8b949e;">No validation errors</td></tr>
                        <?php endif; ?>
                      </tbody>
                    </table>
                  </div>
                </div>
              <?php endif; ?>
            <?php endif; ?>
          </div>
          
          <!-- Action Buttons -->
          <div style="margin-top: 30px; display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="manage.php" class="btn btn-primary">👁️ View All Autos</a>
            <a href="bulk_upload.php" class="btn btn-outline">📤 Import More</a>
            <?php if ($importResults['error_count'] > 0): ?>
              <a href="#" onclick="downloadErrorReport()" class="btn btn-outline">📥 Download Error Report</a>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endif; ?>

  </div>
</div>

<script>
// Tab Switching Functionality
document.querySelectorAll('.tab-btn').forEach(button => {
  button.addEventListener('click', function() {
    const tabName = this.getAttribute('data-tab');
    
    // Hide all tabs
    document.querySelectorAll('.tab-content').forEach(content => {
      content.style.display = 'none';
    });
    
    // Deactivate all buttons
    document.querySelectorAll('.tab-btn').forEach(btn => {
      btn.style.color = '#8b949e';
      btn.style.borderBottom = 'none';
      btn.style.marginBottom = '-2px';
    });
    
    // Show selected tab
    const selectedTab = document.getElementById('tab-' + tabName);
    if (selectedTab) {
      selectedTab.style.display = 'block';
    }
    
    // Activate button
    this.style.color = '#c9d1d9';
    this.style.borderBottom = '3px solid #2196F3';
    this.style.marginBottom = '-2px';
  });
});

// Download Error Report Function
function downloadErrorReport() {
  const details = <?= isset($importResults) ? json_encode($importResults['details'] ?? []) : '[]' ?>;
  const errors = details.filter(d => d.status === 'error');
  
  if (errors.length === 0) {
    alert('No errors to download');
    return;
  }
  
  let csv = 'Row,Auto Number,Error Message\n';
  errors.forEach(error => {
    const row = error.row || '';
    const auto = (error.auto || 'N/A').replace(/"/g, '""');
    const message = (error.message || '').replace(/"/g, '""');
    csv += `${row},"${auto}","${message}"\n`;
  });
  
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
  const link = document.createElement('a');
  const url = URL.createObjectURL(blob);
  link.setAttribute('href', url);
  link.setAttribute('download', 'import-errors_' + new Date().toISOString().split('T')[0] + '.csv');
  link.style.visibility = 'hidden';
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
}

// Drag & Drop functionality
const uploadBox = document.getElementById('uploadBox');
const fileInput = document.getElementById('fileInput');
const submitBtn = document.getElementById('submitBtn');
const fileInfo = document.getElementById('fileInfo');
const fileName = document.getElementById('fileName');

// Only initialize if elements exist
if (uploadBox && fileInput && submitBtn && fileInfo && fileName) {
  // Initial state - disable submit button
  submitBtn.disabled = true;
  submitBtn.style.opacity = '0.6';
  submitBtn.style.cursor = 'not-allowed';

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
      const file = e.dataTransfer.files[0];
      const dataTransfer = new DataTransfer();
      dataTransfer.items.add(file);
      fileInput.files = dataTransfer.files;
      
      // Show file info
      fileName.textContent = file.name;
      fileInfo.style.display = 'block';
      
      // Enable submit button
      submitBtn.disabled = false;
      submitBtn.style.opacity = '1';
      submitBtn.style.cursor = 'pointer';
      
      uploadBox.style.borderColor = '#2da44e';
      uploadBox.style.background = 'rgba(45,164,78,0.1)';
    }
  });

  fileInput.addEventListener('change', () => {
    if (fileInput.files.length > 0) {
      const file = fileInput.files[0];
      
      // Show file info
      fileName.textContent = file.name;
      fileInfo.style.display = 'block';
      
      // Enable submit button
      submitBtn.disabled = false;
      submitBtn.style.opacity = '1';
      submitBtn.style.cursor = 'pointer';
      
      uploadBox.style.borderColor = '#2da44e';
      uploadBox.style.background = 'rgba(45,164,78,0.1)';
    } else {
      // Hide file info if no file
      fileInfo.style.display = 'none';
      submitBtn.disabled = true;
      submitBtn.style.opacity = '0.6';
      submitBtn.style.cursor = 'not-allowed';
      
      uploadBox.style.borderColor = '#3949ab';
      uploadBox.style.background = 'rgba(57,73,171,0.05)';
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
      fileInfo.style.display = 'none';
      submitBtn.disabled = true;
      submitBtn.style.opacity = '0.6';
      submitBtn.style.cursor = 'not-allowed';
      fileInput.value = '';
      return false;
    }
  });
}
</script>
<script src="assets/js/admin.js"></script>
</body>
</html>
