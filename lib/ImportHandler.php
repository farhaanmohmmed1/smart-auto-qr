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
    
    // Column mapping configuration
    private $columnMap = [
        'auto_number'    => ['col' => 0, 'required' => true, 'transform' => 'uppercase'],
        'reg_number'     => ['col' => 1, 'required' => true, 'transform' => 'uppercase'],
        'driver_name'    => ['col' => 2, 'required' => true, 'transform' => 'trim'],
        'phone'          => ['col' => 3, 'required' => true, 'transform' => 'phone'],
        'license_number' => ['col' => 4, 'required' => true, 'transform' => 'uppercase'],
        'permit_number'  => ['col' => 5, 'required' => true, 'transform' => 'uppercase'],
        'area'           => ['col' => 6, 'required' => false, 'transform' => 'trim'],
        'stand'          => ['col' => 7, 'required' => false, 'transform' => 'trim'],
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
        
        // Parse file
        try {
            if (in_array($importType, ['xlsx', 'xls'])) {
                $rows = $this->parseExcel($tmpPath, $importType);
            } else {
                $rows = $this->parseCSV($tmpPath);
            }
        } catch (Exception $e) {
            return $this->error('File parsing error: ' . $e->getMessage());
        }
        
        if (empty($rows)) {
            return $this->error('No data rows found in file (ensure first row is header).');
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
            'message' => $this->formatSummary($processed),
        ];
    }
    
    /**
     * Parse Excel file (.xlsx or .xls)
     * Requires PhpSpreadsheet library
     */
    private function parseExcel(string $filePath, string $type): array {
        // Check if PhpSpreadsheet is available
        if (!class_exists('PhpOffice\PhpSpreadsheet\IOFactory')) {
            throw new Exception('PhpSpreadsheet library not installed. Install via composer require phpoffice/phpspreadsheet');
        }
        
        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = [];
            
            foreach ($worksheet->getRowIterator(2) as $row) {  // Start from row 2 (skip header)
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(false);
                
                $rowData = [];
                foreach ($cellIterator as $cell) {
                    $rowData[] = $cell->getValue();
                }
                
                // Skip empty rows
                if (!empty(array_filter($rowData))) {
                    $rows[] = $rowData;
                }
            }
            
            return $rows;
        } catch (Exception $e) {
            throw new Exception('Excel parsing failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Parse CSV file
     * Uses standard fgetcsv
     */
    private function parseCSV(string $filePath): array {
        $rows = [];
        
        if (!is_readable($filePath)) {
            throw new Exception('Cannot read file');
        }
        
        $handle = fopen($filePath, 'r');
        
        while (($row = fgetcsv($handle, 0, ',')) !== false) {
            // Skip empty rows
            if (!empty(array_filter($row))) {
                $rows[] = $row;
            }
        }
        
        fclose($handle);
        return $rows;
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
        
        // Extract fields
        $fields = [];
        foreach ($this->columnMap as $fieldName => $config) {
            $colIdx = $config['col'];
            $value = isset($rowData[$colIdx]) ? trim($rowData[$colIdx]) : '';
            
            // Transform value
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
            
            $fields[$fieldName] = $value;
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
        
        // Check for duplicates
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
                    'message' => 'Duplicate entry (auto_number or license_number already exists)',
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
        // Phone validation (10-12 digits)
        if (!preg_match('/^\d{10,12}$/', $fields['phone'])) {
            return ['valid' => false, 'error' => 'Invalid phone number (10-12 digits required)'];
        }
        
        // Auto number format check
        if (!preg_match('/^[A-Z0-9\-]{3,20}$/', $fields['auto_number'])) {
            return ['valid' => false, 'error' => 'Invalid auto number format'];
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
    private function checkDuplicate(string $autoNumber, string $licenseNumber): bool {
        $stmt = $this->pdo->prepare("
            SELECT id FROM autos 
            WHERE auto_number = ? OR license_number = ? 
            LIMIT 1
        ");
        $stmt->execute([$autoNumber, $licenseNumber]);
        return $stmt->rowCount() > 0;
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
