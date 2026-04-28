<?php
/**
 * QR Code Generation - Test Suite
 * ================================
 * 
 * Comprehensive tests for local QR generation system.
 * Run: php bin/test_qr.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load config
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/QRGenerator.php';

// ═════════════════════════════════════════════════════════════
// TEST SUITE
// ═════════════════════════════════════════════════════════════

class QRTestSuite {
    private array $results = [];
    private array $timings = [];
    
    public function run(): void {
        $this->printHeader();
        
        try {
            $this->testBasicGeneration();
            $this->testCaching();
            $this->testErrorHandling();
            $this->testBatchGeneration();
            $this->testMultiFormat();
            $this->testFileSize();
            $this->testStats();
            
            $this->printSummary();
            
        } catch (Exception $e) {
            echo "\n❌ Test suite error: " . $e->getMessage() . "\n";
            exit(1);
        }
    }
    
    // ─────────────────────────────────────────────────────────
    // Test: Basic Generation
    // ─────────────────────────────────────────────────────────
    private function testBasicGeneration(): void {
        $this->testHeader('Basic PNG Generation');
        
        $start = microtime(true);
        $path = QRGenerator::generate('AUTO-001', 300, 'png');
        $time = (microtime(true) - $start) * 1000;
        
        $this->timings['png_generation'] = $time;
        
        if ($path && file_exists($path)) {
            $this->pass("Generated PNG: $path ({$time}ms)");
        } else {
            $this->fail("PNG generation failed");
        }
    }
    
    // ─────────────────────────────────────────────────────────
    // Test: Caching
    // ─────────────────────────────────────────────────────────
    private function testCaching(): void {
        $this->testHeader('Cache Hit Performance');
        
        // Second call should hit cache
        $start = microtime(true);
        $path = QRGenerator::generate('AUTO-001', 300, 'png');
        $time = (microtime(true) - $start) * 1000;
        
        $this->timings['cache_hit'] = $time;
        
        if ($time < 5) {
            $this->pass("Cache hit: {$time}ms (target: <5ms)");
        } else {
            $this->warn("Cache hit: {$time}ms (slower than expected)");
        }
    }
    
    // ─────────────────────────────────────────────────────────
    // Test: Error Handling
    // ─────────────────────────────────────────────────────────
    private function testErrorHandling(): void {
        $this->testHeader('Security & Validation');
        
        // Invalid auto IDs
        $invalid = ['../../../etc/passwd', 'AUTO//001', 'AUTO\..\001', ''];
        
        foreach ($invalid as $id) {
            $path = QRGenerator::generate($id);
            if ($path === false) {
                $this->pass("Rejected invalid ID: '$id'");
            } else {
                $this->fail("Accepted invalid ID: '$id'");
            }
        }
        
        // Valid IDs
        $valid = ['AUTO-001', 'AUTO_001', 'A1', 'AUTO123456789'];
        
        foreach ($valid as $id) {
            $path = QRGenerator::generate($id);
            if ($path !== false) {
                $this->pass("Accepted valid ID: '$id'");
            } else {
                $this->fail("Rejected valid ID: '$id'");
            }
        }
    }
    
    // ─────────────────────────────────────────────────────────
    // Test: Batch Generation
    // ─────────────────────────────────────────────────────────
    private function testBatchGeneration(): void {
        $this->testHeader('Batch Generation (10 QRs)');
        
        $autoIds = [];
        for ($i = 2; $i <= 11; $i++) {
            $autoIds[] = 'AUTO-' . str_pad($i, 3, '0', STR_PAD_LEFT);
        }
        
        $start = microtime(true);
        $result = QRGenerator::batchRegenerate($autoIds, function($current, $total) {
            // Progress callback
        });
        $time = (microtime(true) - $start) * 1000;
        
        $this->timings['batch_10'] = $time;
        
        $success = $result['completed'];
        $failed = $result['failed'];
        
        if ($failed === 0) {
            $this->pass("Batch generated: $success/{$success} QRs in {$time}ms");
        } else {
            $this->fail("Batch failed: $failed/{$success} QRs");
        }
    }
    
    // ─────────────────────────────────────────────────────────
    // Test: Multiple Formats
    // ─────────────────────────────────────────────────────────
    private function testMultiFormat(): void {
        $this->testHeader('SVG Generation');
        
        $start = microtime(true);
        $path = QRGenerator::generate('AUTO-SVG-001', 300, 'svg');
        $time = (microtime(true) - $start) * 1000;
        
        $this->timings['svg_generation'] = $time;
        
        if ($path && file_exists($path)) {
            $this->pass("Generated SVG: {$time}ms");
        } else {
            $this->fail("SVG generation failed");
        }
        
        // Test Base64 encoding
        $start = microtime(true);
        $base64 = QRGenerator::getBase64('AUTO-001');
        $time = (microtime(true) - $start) * 1000;
        
        if (str_starts_with($base64, 'data:image/png;base64,')) {
            $this->pass("Base64 encoding: {$time}ms");
        } else {
            $this->fail("Base64 encoding failed");
        }
    }
    
    // ─────────────────────────────────────────────────────────
    // Test: File Size
    // ─────────────────────────────────────────────────────────
    private function testFileSize(): void {
        $this->testHeader('File Size Analysis');
        
        $pngPath = QR_DIR . 'qr_AUTO-001.png';
        $svgPath = QR_DIR . 'qr_AUTO-SVG-001.svg';
        
        if (file_exists($pngPath)) {
            $pngSize = filesize($pngPath);
            $this->pass("PNG size: " . $this->formatBytes($pngSize));
            
            if ($pngSize > 800 && $pngSize < 5000) {
                // Expected: 2-3KB
                $this->pass("PNG size is optimal (2-5KB expected)");
            } else {
                $this->warn("PNG size outside expected range (2-5KB)");
            }
        }
        
        if (file_exists($svgPath)) {
            $svgSize = filesize($svgPath);
            $this->pass("SVG size: " . $this->formatBytes($svgSize));
            
            if ($svgSize > 500 && $svgSize < 3000) {
                // Expected: 1-2KB
                $this->pass("SVG size is optimal (1-3KB expected)");
            } else {
                $this->warn("SVG size outside expected range (1-3KB)");
            }
        }
    }
    
    // ─────────────────────────────────────────────────────────
    // Test: Statistics
    // ─────────────────────────────────────────────────────────
    private function testStats(): void {
        $this->testHeader('System Statistics');
        
        $stats = QRGenerator::getStats();
        
        echo "  Total QRs generated: {$stats['total_qrs']}\n";
        echo "  PNG count: {$stats['by_format']['png']}\n";
        echo "  SVG count: {$stats['by_format']['svg']}\n";
        echo "  Total disk usage: " . $this->formatBytes($stats['total_size']) . "\n";
        
        if ($stats['total_qrs'] > 0) {
            $this->pass("Stats calculated");
        }
        
        if (!empty($stats['errors'])) {
            echo "\n  Errors logged: " . count($stats['errors']) . "\n";
            foreach ($stats['errors'] as $error) {
                echo "    - [{$error['timestamp']}] {$error['message']}\n";
            }
        }
    }
    
    // ═════════════════════════════════════════════════════════
    // UTILITY METHODS
    // ═════════════════════════════════════════════════════════
    
    private function pass(string $msg): void {
        echo "  ✓ $msg\n";
        $this->results['pass']++;
    }
    
    private function fail(string $msg): void {
        echo "  ✗ $msg\n";
        $this->results['fail']++;
    }
    
    private function warn(string $msg): void {
        echo "  ⚠ $msg\n";
        $this->results['warn']++;
    }
    
    private function testHeader(string $title): void {
        echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "  $title\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    }
    
    private function printHeader(): void {
        echo "\n";
        echo "╔═══════════════════════════════════════════════════════════╗\n";
        echo "║ Local QR Code Generation System - Test Suite             ║\n";
        echo "║ PHP 8+ with endroid/qr-code                             ║\n";
        echo "╚═══════════════════════════════════════════════════════════╝\n";
        echo "\nConfiguration:\n";
        echo "  PHP Version: " . PHP_VERSION . "\n";
        echo "  Memory Limit: " . ini_get('memory_limit') . "\n";
        echo "  QR Directory: " . QR_DIR . "\n";
    }
    
    private function printSummary(): void {
        echo "\n╔═══════════════════════════════════════════════════════════╗\n";
        echo "║ Test Results                                              ║\n";
        echo "╚═══════════════════════════════════════════════════════════╝\n";
        
        $pass = $this->results['pass'] ?? 0;
        $fail = $this->results['fail'] ?? 0;
        $warn = $this->results['warn'] ?? 0;
        
        echo "\nPassed:  $pass\n";
        if ($fail > 0) echo "Failed:  $fail\n";
        if ($warn > 0) echo "Warned:  $warn\n";
        
        echo "\nPerformance Benchmarks:\n";
        foreach ($this->timings as $name => $time) {
            echo "  $name: {$time}ms\n";
        }
        
        // Overall assessment
        echo "\n";
        if ($fail === 0 && !empty($this->timings['png_generation']) && $this->timings['png_generation'] < 50) {
            echo "✓ All tests passed! QR generation ready for production.\n";
        } elseif ($fail === 0) {
            echo "⚠ All tests passed, but performance could be optimized.\n";
        } else {
            echo "✗ Some tests failed. Review output above.\n";
        }
        
        echo "\n";
    }
    
    private function formatBytes(int $bytes): string {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}

// ═════════════════════════════════════════════════════════════
// RUN TESTS
// ═════════════════════════════════════════════════════════════

$suite = new QRTestSuite();
$suite->run();
?>
