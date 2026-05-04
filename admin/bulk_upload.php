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
        ['AP 40 CB 6407', 'AP40CB6407', 'Ramesh Kumar', '9876543210', 'TS14DL20190001', 'HYD/PERMIT/2024/001', 'Ameerpet', 'Ameerpet Stand'],
        ['TS 09 EA 1234', 'TS09EA1234', 'Suresh Reddy', '9845612345', 'TS14DL20200045', 'HYD/PERMIT/2024/002', 'Kukatpally', 'KPHB Colony Stand'],
        ['TS 09 EB 5678', 'TS09EB5678', 'Mahesh Yadav', '9912378456', 'TS14DL20180023', 'HYD/PERMIT/2024/003', 'Secunderabad', 'Clock Tower Stand'],
        ['AP 39 UQ 9305', 'AP39UQ9305', 'Venkat Rao', '9900087654', 'TS14DL20210067', 'HYD/PERMIT/2024/004', 'Hitech City', 'Cyber Tower Stand'],
        ['AP 31 TF 2581', 'AP31TF2581', 'Naresh Sharma', '9988776655', 'TS14DL20220012', 'HYD/PERMIT/2024/005', 'LB Nagar', 'LB Nagar Stand'],
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
                        if (($importResults['skipped'] ?? 0) > 0) {
                            $success .= ($importResults['skipped'] ?? 0) . " skipped (duplicates). ";
                        }
                        if (($importResults['errors'] ?? 0) > 0) {
                            $success .= ($importResults['errors'] ?? 0) . " errors.";
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
              <p>.csv (Recommended) | .xlsx, .xls</p>
              <p style="font-size: 0.8rem; color: #8b949e; margin-top: 10px;">Click to browse or drag & drop</p>
              <input type="file" id="fileInput" name="import_file" accept=".xlsx,.xls,.csv,.txt" style="display:none">
            </div>
          </div>

          <div class="form-hint">
            💡 <strong>Tip:</strong> The first row should be headers. Maximum file size: 50MB. 
            Each file can contain 1,000–100,000 records. <strong>CSV format is recommended and works out of the box.</strong>
          </div>

          <div style="padding: 12px; background: rgba(255,193,7,0.1); border-left: 3px solid #FFC107; border-radius: 4px; margin: 15px 0; font-size: 0.9rem;">
            <strong>📝 Using CSV Format:</strong> The easiest way is to export your Excel file as CSV. 
            Most spreadsheet apps support this: File → Export as → CSV.
          </div>

          <div style="padding: 12px; background: rgba(33,150,243,0.1); border-left: 3px solid #2196F3; border-radius: 4px; margin: 15px 0; font-size: 0.9rem;">
            <strong>ℹ️ Excel Support:</strong> If you want to upload .xlsx or .xls files directly, 
            contact your administrator to install Excel support via Composer.
          </div>

          <div style="margin-top: 20px; display: flex; flex-direction: column; gap: 12px;">
            <button type="submit" class="btn btn-primary btn-block" id="submitBtn">
              📤 Upload & Import
            </button>
            <div id="fileInfo" style="padding: 12px; background: rgba(33,150,243,0.1); border-left: 3px solid #2196F3; border-radius: 4px; display: none;">
              <strong>✓ File selected:</strong> <span id="fileName" style="color: #2196F3;"></span>
            </div>
          </div>
        </form>
      </div>
    </div>

    <!-- Import Guide -->
    <div class="card" style="margin-top: 25px;">
      <div class="card-header">
        <h2 class="card-title">📋 Column Requirements</h2>
      </div>
      <div class="card-body">
        <div style="margin-bottom: 15px; padding: 12px; background: rgba(33,150,243,0.1); border-left: 3px solid #2196F3; border-radius: 4px;">
          <strong>ℹ️ Flexible Column Mapping:</strong> Your file columns will be automatically detected by headers. Any column order is supported! 
          Missing optional fields will be left blank in the database.
        </div>
        
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
              <tr style="border-bottom: 1px solid #30363d; background: rgba(255,107,107,0.05);">
                <td style="padding: 12px;"><strong>Auto Number</strong></td>
                <td style="padding: 12px;">✅ MUST HAVE</td>
                <td style="padding: 12px;">2-50 characters (any format)</td>
                <td style="padding: 12px;"><code>AP 40 CB 6407</code>, <code>AUTO-001</code>, etc.</td>
              </tr>
              <tr style="border-bottom: 1px solid #30363d; background: rgba(255,107,107,0.05);">
                <td style="padding: 12px;"><strong>Driver Name</strong></td>
                <td style="padding: 12px;">✅ MUST HAVE</td>
                <td style="padding: 12px;">3-100 characters</td>
                <td style="padding: 12px;"><code>Ramesh Kumar</code></td>
              </tr>
              <tr style="border-bottom: 1px solid #30363d;">
                <td style="padding: 12px;"><strong>Reg Number</strong></td>
                <td style="padding: 12px;">⚪ Optional*</td>
                <td style="padding: 12px;">Vehicle registration</td>
                <td style="padding: 12px;"><code>TS09EA1234</code></td>
              </tr>
              <tr style="border-bottom: 1px solid #30363d;">
                <td style="padding: 12px;"><strong>Phone</strong></td>
                <td style="padding: 12px;">⚪ Optional*</td>
                <td style="padding: 12px;">10-12 digits</td>
                <td style="padding: 12px;"><code>9876543210</code></td>
              </tr>
              <tr style="border-bottom: 1px solid #30363d;">
                <td style="padding: 12px;"><strong>License Number</strong></td>
                <td style="padding: 12px;">⚪ Optional*</td>
                <td style="padding: 12px;">Driver license ID</td>
                <td style="padding: 12px;"><code>TS14DL20190001</code></td>
              </tr>
              <tr style="border-bottom: 1px solid #30363d;">
                <td style="padding: 12px;"><strong>Permit Number</strong></td>
                <td style="padding: 12px;">⚪ Optional*</td>
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
        
        <div style="margin-top: 15px; padding: 12px; background: rgba(255,193,7,0.1); border-left: 3px solid #FFC107; border-radius: 4px;">
          <strong>* Note on Optional Fields:</strong> Fields marked as optional will be stored as empty/blank in the database if the column is not present in your file.
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
          <h2 class="card-title">✅ Import Report</h2>
        </div>
        <div class="card-body">
          
          <!-- Detected Columns Info -->
          <?php if (!empty($importResults['detected_columns'])): ?>
            <div style="padding: 12px; background: rgba(33,150,243,0.1); border-left: 3px solid #2196F3; border-radius: 4px; margin-bottom: 20px;">
              <strong>📋 Detected Columns:</strong>
              <ul style="margin: 8px 0 0 20px; font-size: 0.9rem;">
                <?php foreach ($importResults['detected_columns'] as $fieldName => $colIndex): ?>
                  <li><?= ucwords(str_replace('_', ' ', htmlspecialchars($fieldName))) ?> (Column <?= $colIndex + 1 ?>)</li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>
          
          <!-- Summary Statement -->
          <div style="padding: 15px; background: rgba(45,194,107,0.1); border-left: 4px solid #2dc26b; border-radius: 6px; margin-bottom: 25px;">
            <p style="margin: 0; font-size: 1.05rem; line-height: 1.6;">
              <strong>✅ Import Summary:</strong><br>
              <?php
                $total = $importResults['total'] ?? 0;
                $successful = $importResults['successful'] ?? 0;
                $skipped = $importResults['skipped'] ?? 0;
                $errors = $importResults['errors'] ?? 0;
                
                $summary = "Out of <strong>{$total} total records</strong>, ";
                $summary .= "<strong>{$successful} were successfully added</strong> ";
                
                if ($skipped > 0) {
                  $summary .= "and <strong>{$skipped} were skipped due to duplicates</strong> ";
                }
                
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
              <div class="label">✅ Successfully Added</div>
            </div>
            <div class="summary-stat">
              <div class="number" style="color: #d29922;"><?= $importResults['skipped'] ?? 0 ?></div>
              <div class="label">⚠️ Duplicate Skipped</div>
            </div>
            <div class="summary-stat">
              <div class="number" style="color: #da3633;"><?= $importResults['errors'] ?? 0 ?></div>
              <div class="label">❌ Validation Errors</div>
            </div>
            <div class="summary-stat">
              <div class="number"><?= $importResults['total'] ?? 0 ?></div>
              <div class="label">📊 Total Processed</div>
            </div>
          </div>

          <!-- Tabbed Results Section -->
          <div style="margin-top: 30px;">
            <div style="display: flex; gap: 10px; margin-bottom: 15px; border-bottom: 2px solid #30363d; flex-wrap: wrap;">
              <button class="tab-btn active" data-tab="all" style="padding: 12px 20px; background: none; border: none; color: #c9d1d9; cursor: pointer; font-weight: 600; border-bottom: 3px solid #2196F3; margin-bottom: -2px; transition: all 0.3s;">
                📊 All Records (<?= $importResults['total'] ?? 0 ?>)
              </button>
              <button class="tab-btn" data-tab="success" style="padding: 12px 20px; background: none; border: none; color: #8b949e; cursor: pointer; font-weight: 600; transition: all 0.3s;">
                ✅ Added (<?= $importResults['successful'] ?? 0 ?>)
              </button>
              <button class="tab-btn" data-tab="skipped" style="padding: 12px 20px; background: none; border: none; color: #8b949e; cursor: pointer; font-weight: 600; transition: all 0.3s;">
                ⚠️ Duplicates (<?= $importResults['skipped'] ?? 0 ?>)
              </button>
              <?php if (($importResults['errors'] ?? 0) > 0): ?>
                <button class="tab-btn" data-tab="errors" style="padding: 12px 20px; background: none; border: none; color: #8b949e; cursor: pointer; font-weight: 600; transition: all 0.3s;">
                  ❌ Errors (<?= $importResults['errors'] ?? 0 ?>)
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

              <!-- Duplicates Tab -->
              <div id="tab-skipped" class="tab-content" style="display: none;">
                <div class="results-table">
                  <table>
                    <thead>
                      <tr>
                        <th>Row</th>
                        <th>Auto Number</th>
                        <th>Reason</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php 
                        $skippedCount = 0;
                        foreach ($importResults['details'] as $detail): 
                          if ($detail['status'] === 'skip'): 
                            $skippedCount++;
                      ?>
                        <tr style="background: rgba(210,154,34,0.05);">
                          <td><?= (int)$detail['row'] ?></td>
                          <td><strong><?= htmlspecialchars($detail['auto']) ?></strong></td>
                          <td style="font-size: 0.9rem; color: #d29922;">⚠️ <?= htmlspecialchars($detail['message']) ?></td>
                        </tr>
                      <?php endif; endforeach; ?>
                      <?php if ($skippedCount === 0): ?>
                        <tr><td colspan="3" style="text-align: center; padding: 20px; color: #8b949e;">No duplicate records found</td></tr>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>
              </div>

              <!-- Errors Tab -->
              <?php if ($importResults['errors'] > 0): ?>
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
            <?php if ($importResults['errors'] > 0): ?>
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
