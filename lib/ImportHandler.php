<?php
/**
 * ImportHandler.php
 * ===============
 * Handles bulk import of auto data from Excel/CSV files
 * Uses PhpSpreadsheet library for Excel support
 * 
 * Usage:
 *   $importer = new ImportHandler($pdo, $adminId);
 *   $result = $importer->importFile($_FILES['file'], 'mapping_config');
 */

class ImportHandler {
    private $pdo;
    private $adminId;
    private $errors = [];
    private $results = [];
    private $headerMapping = [];  // Dynamic column mapping from file header
    
    // Column definition configuration
    private $columnDefinitions = [
        'auto_number'    => ['required' => true, 'transform' => 'uppercase', 'aliases' => ['Auto Number', 'Auto#', 'Auto No']],
        'reg_number'     => ['required' => false, 'transform' => 'uppercase', 'aliases' => ['Registration Number', 'Reg Number', 'Reg#', 'Vehicle Reg']],
        'driver_name'    => ['required' => true, 'transform' => 'trim', 'aliases' => ['Driver Name', 'Driver', 'Driver\'s Name', 'Auto Owner', 'Auto Owner Name', 'Owner Name', 'Owner']],
        'phone'          => ['required' => false, 'transform' => 'phone', 'aliases' => ['Phone Number', 'Phone', 'Mobile', 'Contact']],
        'license_number' => ['required' => false, 'transform' => 'uppercase', 'aliases' => ['License Number', 'License#', 'DL', 'License']],
        'permit_number'  => ['required' => false, 'transform' => 'uppercase', 'aliases' => ['Permit Number', 'Permit#', 'Permit']],
        'area'           => ['required' => false, 'transform' => 'trim', 'aliases' => ['Area', 'Zone', 'Operating Area']],
        'stand'          => ['required' => false, 'transform' => 'trim', 'aliases' => ['Stand', 'Stand Name', 'Depot', 'Stand Depot']],
    ];
    
    public function __construct($pdo, int $adminId) {
        $this->pdo = $pdo;
        $this->adminId = $adminId;
    }
    
    /**
     * Import file (CSV or Excel)
     * Returns array with results and summary
     */
    public function importFile(array $fileArray, string $importType = 'auto-detect'): array {
        // Validate file upload
        if ($fileArray['error'] !== UPLOAD_ERR_OK) {
            return $this->error('File upload error: ' . $this->getUploadErrorMessage($fileArray['error']));
        }
        
        $filename = $fileArray['name'];
        $tmpPath = $fileArray['tmp_name'];
        
        // Detect file type
        if ($importType === 'auto-detect') {
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            $importType = in_array($ext, ['xlsx', 'xls', 'csv']) ? $ext : null;
        }
        
        if (!$importType) {
            return $this->error('Invalid file type. Supported: .xlsx, .xls, .csv');
        }
        
        // File size check (max 50MB)
        if ($fileArray['size'] > 50 * 1024 * 1024) {
            return $this->error('File too large. Maximum 50MB allowed.');
        }
        
        // Parse file with headers
        try {
            if (in_array($importType, ['xlsx', 'xls'])) {
                $result = $this->parseExcel($tmpPath, $importType);
            } else {
                $result = $this->parseCSV($tmpPath);
            }
            
            $headers = $result['headers'] ?? [];
            $rows = $result['rows'] ?? [];
        } catch (Exception $e) {
            return $this->error('File parsing error: ' . $e->getMessage());
        }
        
        if (empty($rows)) {
            return $this->error('No data rows found in file (ensure first row is header).');
        }
        
        // Build header mapping from file columns
        $headerValidation = $this->buildHeaderMapping($headers);
        if (!$headerValidation['valid']) {
            return $this->error($headerValidation['error']);
        }
        
        // Process rows
        $importId = $this->logImportStart($filename, $importType, count($rows));
        $processed = $this->processRows($rows, $importId);
        
        // Log completion
        $this->logImportEnd($importId, $processed);
        
        return [
            'success' => true,
            'import_id' => $importId,
            'total' => $processed['total'],
            'successful' => $processed['successful'],
            'skipped' => $processed['skipped'],
            'errors' => $processed['error_count'],
            'details' => $this->results,
            'detected_columns' => $this->headerMapping,  // Show which columns were detected
            'message' => $this->formatSummary($processed),
        ];
    }
    
    /**
     * Build mapping between file columns and database fields
     * Handles flexible column order and missing optional columns
     */
    private function buildHeaderMapping(array $headers): array {
        $this->headerMapping = [];
        $requiredFields = [];
        
        // Get list of required fields
        foreach ($this->columnDefinitions as $fieldName => $config) {
            if ($config['required']) {
                $requiredFields[] = $fieldName;
            }
        }
        
        // Map each file column to a field
        foreach ($headers as $colIndex => $headerValue) {
            $headerNormalized = $this->normalizeHeader($headerValue);
            $matchedField = null;
            
            // Find matching field by aliases
            foreach ($this->columnDefinitions as $fieldName => $config) {
                $aliasesNormalized = array_map([$this, 'normalizeHeader'], $config['aliases']);
                if (in_array($headerNormalized, $aliasesNormalized) || $headerNormalized === $this->normalizeHeader($fieldName)) {
                    $matchedField = $fieldName;
                    break;
                }
            }
            
            if ($matchedField) {
                $this->headerMapping[$matchedField] = $colIndex;
            }
        }
        
        // Check if all required fields are found
        $missingRequired = [];
        foreach ($requiredFields as $fieldName) {
            if (!isset($this->headerMapping[$fieldName])) {
                $missingRequired[] = $fieldName;
            }
        }
        
        if (!empty($missingRequired)) {
            return [
                'valid' => false,
                'error' => 'Missing required columns: ' . implode(', ', $missingRequired)
            ];
        }
        
        return ['valid' => true];
    }
    
    /**
     * Normalize header text for comparison
     */
    private function normalizeHeader(string $header): string {
        return strtolower(trim(preg_replace('/[\s\-_#]+/', '', $header)));
    }
    
    /**
     * Parse Excel file (.xlsx or .xls)
     * Returns array with headers and rows
     */
    private function parseExcel(string $filePath, string $type): array {
        // Check if PhpSpreadsheet is available
        if (!class_exists('PhpOffice\PhpSpreadsheet\IOFactory')) {
            throw new Exception(
                'Excel (.xlsx/.xls) support requires PhpSpreadsheet library. ' .
                'Please use CSV format instead, or contact your administrator to install the library. ' .
                'To install: Run "composer require phpoffice/phpspreadsheet" in your project directory.'
            );
        }
        
        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            
            $headers = [];
            $rows = [];
            $isFirstRow = true;
            
            foreach ($worksheet->getRowIterator() as $row) {
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(false);
                
                $rowData = [];
                foreach ($cellIterator as $cell) {
                    $rowData[] = $cell->getValue();
                }
                
                // Skip empty rows
                if (empty(array_filter($rowData))) {
                    continue;
                }
                
                // First non-empty row is header
                if ($isFirstRow) {
                    $headers = $rowData;
                    $isFirstRow = false;
                } else {
                    $rows[] = $rowData;
                }
            }
            
            return [
                'headers' => $headers,
                'rows' => $rows,
            ];
        } catch (Exception $e) {
            throw new Exception('Excel parsing failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Parse CSV file
     * Returns array with headers and rows
     */
    private function parseCSV(string $filePath): array {
        $rows = [];
        $headers = [];
        $isFirstRow = true;
        
        if (!is_readable($filePath)) {
            throw new Exception('Cannot read file');
        }
        
        $handle = fopen($filePath, 'r');
        
        while (($row = fgetcsv($handle, 0, ',')) !== false) {
            // Skip empty rows
            if (empty(array_filter($row))) {
                continue;
            }
            
            // First non-empty row is header
            if ($isFirstRow) {
                $headers = $row;
                $isFirstRow = false;
            } else {
                $rows[] = $row;
            }
        }
        
        fclose($handle);
        
        return [
            'headers' => $headers,
            'rows' => $rows,
        ];
    }
    
    /**
     * Process and validate each row
     */
    private function processRows(array $rows, int $importId): array {
        $stats = [
            'total' => count($rows),
            'successful' => 0,
            'skipped' => 0,
            'error_count' => 0,
        ];
        
        // Start transaction for batch insert
        $this->pdo->beginTransaction();
        
        try {
            foreach ($rows as $rowNum => $rowData) {
                $lineNum = $rowNum + 2;  // Adjust for 1-based + header
                $result = $this->validateAndInsertRow($rowData, $lineNum);
                
                $this->results[] = $result;
                
                if ($result['status'] === 'success') {
                    $stats['successful']++;
                } elseif ($result['status'] === 'skip') {
                    $stats['skipped']++;
                } else {
                    $stats['error_count']++;
                }
            }
            
            // Commit transaction
            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            $stats['error_count']++;
            $this->results[] = [
                'row' => 'Transaction',
                'auto' => '',
                'status' => 'error',
                'message' => 'Database transaction failed: ' . $e->getMessage(),
            ];
        }
        
        return $stats;
    }
    
    /**
     * Validate single row and insert
     */
    private function validateAndInsertRow(array $rowData, int $lineNum): array {
        $result = ['row' => $lineNum, 'auto' => ''];
        
        // Extract fields based on header mapping
        $fields = [];
        foreach ($this->columnDefinitions as $fieldName => $config) {
            $value = '';
            
            // Get value from row if column exists in file
            if (isset($this->headerMapping[$fieldName])) {
                $colIdx = $this->headerMapping[$fieldName];
                $value = isset($rowData[$colIdx]) ? trim($rowData[$colIdx]) : '';
            }
            
            // Transform value if not empty
            if ($value) {
                $value = $this->transformValue($value, $config['transform']);
            }
            
            // Validate required fields
            if ($config['required'] && !$value) {
                return array_merge($result, [
                    'status' => 'error',
                    'message' => "Missing required field: $fieldName",
                ]);
            }
            
            // Optional fields can be empty/null
            $fields[$fieldName] = $value ?: null;
        }
        
        $result['auto'] = $fields['auto_number'];
        
        // Validation checks
        $validation = $this->validateFields($fields);
        if (!$validation['valid']) {
            return array_merge($result, [
                'status' => 'error',
                'message' => $validation['error'],
            ]);
        }
        
        // Check for duplicates (only for unique fields if present)
        $duplicate = $this->checkDuplicate($fields['auto_number'], $fields['license_number']);
        if ($duplicate) {
            return array_merge($result, [
                'status' => 'skip',
                'message' => "Duplicate (auto exists): {$fields['auto_number']}",
            ]);
        }
        
        // Insert record
        try {
            // Generate secure token for QR code
            $qrToken = bin2hex(random_bytes(32));
            
            $stmt = $this->pdo->prepare("
                INSERT INTO autos 
                (auto_number, reg_number, driver_name, phone, license_number, permit_number, area, stand, qr_token, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
            ");
            
            $stmt->execute([
                $fields['auto_number'],
                $fields['reg_number'],
                $fields['driver_name'],
                $fields['phone'],
                $fields['license_number'],
                $fields['permit_number'],
                $fields['area'],
                $fields['stand'],
                $qrToken,
            ]);
            
            $autoId = $this->pdo->lastInsertId();
            
            // Generate QR code (done outside transaction to avoid blocking)
            // Queue for async processing or do it after commit
            
            return array_merge($result, [
                'status' => 'success',
                'message' => "Inserted successfully (ID: $autoId)",
            ]);
            
        } catch (Exception $e) {
            // Check if duplicate key error
            if (strpos($e->getMessage(), '23000') !== false || 
                strpos($e->getMessage(), 'Duplicate') !== false) {
                return array_merge($result, [
                    'status' => 'skip',
                    'message' => 'Duplicate entry (auto_number already exists)',
                ]);
            }
            
            return array_merge($result, [
                'status' => 'error',
                'message' => 'Database error: ' . substr($e->getMessage(), 0, 100),
            ]);
        }
    }
    
    /**
     * Transform field values
     */
    private function transformValue(string $value, string $transform): string {
        switch ($transform) {
            case 'uppercase':
                return strtoupper($value);
            case 'phone':
                return preg_replace('/\D/', '', $value);
            case 'trim':
                return trim($value);
            default:
                return $value;
        }
    }
    
    /**
     * Validate field values
     */
    private function validateFields(array $fields): array {
        // Phone validation (only if provided)
        if ($fields['phone'] && !preg_match('/^\d{10,12}$/', $fields['phone'])) {
            return ['valid' => false, 'error' => 'Invalid phone number (10-12 digits required)'];
        }
        
        // Auto number format check - Indian registration format (XX NN XX NNNN or XXNNXXNNNN)
        if (!preg_match('/^[A-Z]{2}\s*\d{2}\s*[A-Z]{2}\s*\d{4}$/i', $fields['auto_number'])) {
            return ['valid' => false, 'error' => 'Invalid auto number format. Expected: AP 40 CB 6407 or AP40CB6407'];
        }
        
        // Driver name length check
        if (strlen($fields['driver_name']) < 3 || strlen($fields['driver_name']) > 100) {
            return ['valid' => false, 'error' => 'Driver name must be 3-100 characters'];
        }
        
        return ['valid' => true];
    }
    
    /**
     * Check for duplicate auto
     */
    private function checkDuplicate(string $autoNumber, ?string $licenseNumber): bool {
        // Always check auto_number (it's unique and required)
        $stmt = $this->pdo->prepare("
            SELECT id FROM autos 
            WHERE auto_number = ? 
            LIMIT 1
        ");
        $stmt->execute([$autoNumber]);
        
        if ($stmt->rowCount() > 0) {
            return true;
        }
        
        // Only check license_number if provided
        if ($licenseNumber) {
            $stmt = $this->pdo->prepare("
                SELECT id FROM autos 
                WHERE license_number = ? 
                LIMIT 1
            ");
            $stmt->execute([$licenseNumber]);
            return $stmt->rowCount() > 0;
        }
        
        return false;
    }
    
    /**
     * Log import start
     */
    private function logImportStart(string $filename, string $type, int $totalRows): int {
        $stmt = $this->pdo->prepare("
            INSERT INTO import_logs (admin_id, filename, import_type, total_rows, status)
            VALUES (?, ?, ?, ?, 'completed')
        ");
        $stmt->execute([$this->adminId, $filename, $type, $totalRows]);
        return (int)$this->pdo->lastInsertId();
    }
    
    /**
     * Log import completion
     */
    private function logImportEnd(int $importId, array $stats): void {
        $stmt = $this->pdo->prepare("
            UPDATE import_logs 
            SET successful_rows = ?, skipped_rows = ?, error_rows = ?, details = ?
            WHERE id = ?
        ");
        
        $stmt->execute([
            $stats['successful'],
            $stats['skipped'],
            $stats['error_count'],
            json_encode($this->results),
            $importId,
        ]);
    }
    
    /**
     * Helper: Format import summary message
     */
    private function formatSummary(array $stats): string {
        return sprintf(
            'Import complete: ✅ %d inserted, ⚠️ %d skipped, ❌ %d errors (Total: %d rows)',
            $stats['successful'],
            $stats['skipped'],
            $stats['error_count'],
            $stats['total']
        );
    }
    
    /**
     * Helper: Get upload error message
     */
    private function getUploadErrorMessage(int $code): string {
        $errors = [
            UPLOAD_ERR_INI_SIZE   => 'File exceeds max_upload_filesize in php.ini',
            UPLOAD_ERR_FORM_SIZE  => 'File exceeds max_file_size in form',
            UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE    => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION  => 'Blocked by PHP extension',
        ];
        return $errors[$code] ?? 'Unknown error';
    }
    
    /**
     * Helper: Return error
     */
    private function error(string $message): array {
        return [
            'success' => false,
            'error' => $message,
            'details' => [],
        ];
    }
}
