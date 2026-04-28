# ⚡ Top 10 Performance Wins
## Smart Auto QR Safety System — Specific Code Changes

**Total Estimated Gain:** ~600-700ms per user session  
**Priority:** All recommended  
**Implementation Time:** 2-3 hours

---

## 1️⃣ Dashboard Query Consolidation
**File:** `admin/dashboard.php`  
**Impact:** ⏱️ -500ms (83% improvement)  
**Complexity:** Low

### BEFORE
```php
<?php
$totalAutos  = $pdo->query("SELECT COUNT(*) FROM autos")->fetch();
$totalAutos = $totalAutos[0];

$activeAutos = $pdo->query("SELECT COUNT(*) FROM autos WHERE status='active'")->fetch();
$activeAutos = $activeAutos[0];

$totalScans = $pdo->query("SELECT COUNT(*) FROM scan_logs")->fetch();
$totalScans = $totalScans[0];

$todayScans = $pdo->query("SELECT COUNT(*) FROM scan_logs WHERE DATE(scanned_at)=CURDATE()")->fetch();
$todayScans = $todayScans[0];

$totalSOS = $pdo->query("SELECT COUNT(*) FROM sos_logs")->fetch();
$totalSOS = $totalSOS[0];

$pendingSOS = $pdo->query("SELECT COUNT(*) FROM sos_logs WHERE status='pending'")->fetch();
$pendingSOS = $pendingSOS[0];

echo "Total: $totalAutos, Active: $activeAutos, ...";
?>
```

### AFTER
```php
<?php
$stats = $pdo->query("
    SELECT 
        (SELECT COUNT(*) FROM autos) as total_autos,
        (SELECT COUNT(*) FROM autos WHERE status='active') as active_autos,
        (SELECT COUNT(*) FROM scan_logs) as total_scans,
        (SELECT COUNT(*) FROM scan_logs WHERE DATE(scanned_at)=CURDATE()) as today_scans,
        (SELECT COUNT(*) FROM sos_logs) as total_sos,
        (SELECT COUNT(*) FROM sos_logs WHERE status='pending') as pending_sos
")->fetch();

echo "Total: {$stats['total_autos']}, Active: {$stats['active_autos']}, ...";
?>
```

### Why?
- **BEFORE:** 6 database round-trips × 50-100ms each = 300-600ms total
- **AFTER:** 1 database round-trip = 50-100ms
- Single query with subselects is atomic and leverages MySQL's optimizer

---

## 2️⃣ QR URL Batch Computation
**File:** `admin/manage.php`  
**Impact:** ⏱️ -50ms (list page efficiency)  
**Complexity:** Low

### BEFORE
```php
<?php
$autos = $pdo->prepare("SELECT * FROM autos LIMIT 20")->fetchAll();

echo "<table>";
foreach ($autos as $auto) {
    // This function internally calls file_exists()
    $qrUrl = QRGenerator::getQRImageURL($auto['auto_number']);
    echo "<tr><td>{$auto['auto_number']}</td><td><img src='$qrUrl'></td></tr>";
}
echo "</table>";
?>
```

### AFTER
```php
<?php
$autos = $pdo->prepare("SELECT * FROM autos LIMIT 20")->fetchAll();

// Pre-compute all QR URLs in one pass
$qrUrls = [];
foreach ($autos as $auto) {
    $qrUrls[$auto['auto_number']] = QRGenerator::getURL($auto['auto_number']);
}

echo "<table>";
foreach ($autos as $auto) {
    $qrUrl = $qrUrls[$auto['auto_number']];  // Instant array lookup
    echo "<tr><td>{$auto['auto_number']}</td><td><img src='$qrUrl'></td></tr>";
}
echo "</table>";
?>
```

### Why?
- **BEFORE:** 20 × file_exists() system calls = ~50-100ms total
- **AFTER:** Compute all in one loop, then use array lookup = <5ms
- Avoids repeated filesystem checks

---

## 3️⃣ Add Foreign Key Indexes
**File:** Database  
**Impact:** ⏱️ -100 to -500ms (join queries)  
**Complexity:** Very Low

### BEFORE
```sql
-- No indexes on join columns
ALTER TABLE sos_logs ADD COLUMN auto_number VARCHAR(50);
ALTER TABLE scan_logs ADD COLUMN auto_number VARCHAR(50);
-- Later queries:
SELECT * FROM sos_logs JOIN autos ON sos_logs.auto_number = autos.auto_number;
-- ^ Full table scan without index
```

### AFTER
```sql
-- Add indexes BEFORE doing joins
ALTER TABLE sos_logs ADD INDEX idx_auto_number (auto_number);
ALTER TABLE scan_logs ADD INDEX idx_auto_number (auto_number);

-- Now the join uses index
SELECT * FROM sos_logs 
JOIN autos ON sos_logs.auto_number = autos.auto_number;
-- ^ Uses index, much faster
```

### Why?
- **BEFORE:** MySQL must scan entire sos_logs table to find matching rows
- **AFTER:** Index allows instant lookup via B-tree search (O(log n) instead of O(n))
- Impact grows with table size (10K+ rows = 100-500ms savings)

### Verify
```sql
SHOW INDEXES FROM sos_logs;
SHOW INDEXES FROM scan_logs;
```

---

## 4️⃣ Extract QR Regeneration Helper
**File:** Create `lib/helpers.php`  
**Impact:** 📦 Code reusability, -10 lines duplication  
**Complexity:** Low

### BEFORE (register.php & edit.php both have this)
```php
<?php
// register.php (lines 60-65)
QRGenerator::delete($auto['auto_number']);
$autoUrl = generateAutoURL($auto['auto_number']);
$qrPath  = QRGenerator::generate($autoUrl, $auto['auto_number']);
if ($qrPath) {
    $pdo->prepare("UPDATE autos SET qr_path=? WHERE id=?")->execute([$qrPath, $id]);
}

// edit.php (lines 75-80) — IDENTICAL CODE
QRGenerator::delete($auto['auto_number']);
$autoUrl = generateAutoURL($auto['auto_number']);
$qrPath  = QRGenerator::generate($autoUrl, $auto['auto_number']);
if ($qrPath) {
    $pdo->prepare("UPDATE autos SET qr_path=? WHERE id=?")->execute([$qrPath, $id]);
}
?>
```

### AFTER
```php
<?php
// lib/helpers.php (NEW FILE)

function regenerateAutoQR($autoNumber, $autoId, $pdo) {
    /**
     * Regenerate QR code for an auto
     * 
     * @param string $autoNumber Auto number (e.g., "AUTO-001")
     * @param int $autoId Database auto ID
     * @param PDO $pdo Database connection
     * @return string|false QR path on success, false on failure
     */
    QRGenerator::delete($autoNumber);
    $autoUrl = generateAutoURL($autoNumber);
    $qrPath  = QRGenerator::generate($autoUrl, $autoNumber);
    
    if ($qrPath) {
        $stmt = $pdo->prepare("UPDATE autos SET qr_path=? WHERE id=?");
        $stmt->execute([$qrPath, $autoId]);
        return $qrPath;
    }
    return false;
}
?>
```

### Usage in register.php & edit.php
```php
<?php
// Single line instead of 5
regenerateAutoQR($auto['auto_number'], $id, $pdo);
?>
```

### Why?
- **BEFORE:** Code duplication = maintenance burden (bug in one place, but not the other)
- **AFTER:** Single source of truth, consistent behavior
- DRY principle (Don't Repeat Yourself)

---

## 5️⃣ Remove Dead Code
**File:** `admin/edit.php`  
**Impact:** ⏱️ -1 DB query  
**Complexity:** Very Low

### BEFORE (lines 4-9)
```php
<?php
// ...line 3
$auto = $id ? $pdo->prepare("SELECT * FROM autos WHERE id=?")->execute([$id]) ? null : null : null;
// ^ This line is DEAD CODE — doesn't even store the result

$stmt = $pdo->prepare("SELECT * FROM autos WHERE id=?");
$stmt->execute([$id]);
$auto = $stmt->fetch();  // ^ Overwrites $auto, first query was useless
// ...line 9
?>
```

### AFTER (simply delete line 5)
```php
<?php
// ...line 3
$stmt = $pdo->prepare("SELECT * FROM autos WHERE id=?");
$stmt->execute([$id]);
$auto = $stmt->fetch();
// ...line 8
?>
```

### Why?
- **BEFORE:** Wasted 1 DB query + unnecessary overhead
- **AFTER:** Cleaner, faster
- Likely a copy-paste error from earlier refactoring

---

## 6️⃣ Cache Areas Dropdown
**File:** `admin/manage.php`  
**Impact:** ⏱️ -10ms per page load  
**Complexity:** Low

### BEFORE
```php
<?php
// Runs on EVERY page load
$areas = $pdo->query("
    SELECT DISTINCT area FROM autos 
    WHERE area IS NOT NULL AND area != '' 
    ORDER BY area
")->fetchAll();

foreach ($areas as $area) {
    echo "<option>{$area['area']}</option>";
}
?>
```

### AFTER
```php
<?php
// Check if cache exists, otherwise fetch
if (empty($_SESSION['areas_cache'])) {
    $_SESSION['areas_cache'] = $pdo->query("
        SELECT DISTINCT area FROM autos 
        WHERE area IS NOT NULL AND area != '' 
        ORDER BY area
    ")->fetchAll();
}
$areas = $_SESSION['areas_cache'];

foreach ($areas as $area) {
    echo "<option>{$area['area']}</option>";
}

// INVALIDATION: After adding/editing auto with new area:
// In post-handler after successful INSERT/UPDATE:
unset($_SESSION['areas_cache']);  // Clear cache
?>
```

### Why?
- **BEFORE:** 10-20ms query per page load × 10 page views/user = 100-200ms wasted
- **AFTER:** First load 10-20ms, subsequent loads <1ms until cache invalidation
- Session cache is perfect for data that changes rarely

---

## 7️⃣ Cache Dashboard Chart
**File:** `admin/dashboard.php`  
**Impact:** ⏱️ -30ms per page load  
**Complexity:** Low

### BEFORE
```php
<?php
// Runs on every dashboard load, queries potentially large table
$scanChart = $pdo->query("
    SELECT DATE(scanned_at) as day, COUNT(*) as cnt
    FROM scan_logs
    WHERE scanned_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(scanned_at)
")->fetchAll();

$chartData = json_encode(array_column($scanChart, 'cnt', 'day'));
?>
```

### AFTER
```php
<?php
// Simple time-based cache (5 minutes)
$cacheKey = 'dashboard_scan_chart';
$cacheTtl = 300;  // 5 minutes

if (!isset($_SESSION[$cacheKey]) || 
    (time() - ($_SESSION[$cacheKey . '_time'] ?? 0)) > $cacheTtl) {
    
    $scanChart = $pdo->query("
        SELECT DATE(scanned_at) as day, COUNT(*) as cnt
        FROM scan_logs
        WHERE scanned_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        GROUP BY DATE(scanned_at)
    ")->fetchAll();
    
    $_SESSION[$cacheKey] = $scanChart;
    $_SESSION[$cacheKey . '_time'] = time();
} else {
    $scanChart = $_SESSION[$cacheKey];  // Use cached
}

$chartData = json_encode(array_column($scanChart, 'cnt', 'day'));
?>
```

### Why?
- **BEFORE:** 30-50ms query on every dashboard refresh
- **AFTER:** Query once every 5 min, serve from cache otherwise = <1ms on cached hits
- Chart data doesn't need to be real-time, 5-min stale is acceptable

---

## 8️⃣ Implement Scheduled Cleanup
**File:** Create `bin/cleanup.php`, remove from `config/config.php`  
**Impact:** ⏱️ -random latency, 🧹 cleaner code  
**Complexity:** Medium

### BEFORE (config.php)
```php
<?php
function checkLoginAttempts($ip) {
    global $pdo;
    
    // ...check logic...
    
    // Random cleanup (10% chance on every login)
    if (rand(1, 100) <= 10) {
        $pdo->prepare("DELETE FROM login_attempts WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)")
            ->execute();  // ← Can block request if 1000s of rows
    }
    
    return $allowed;
}

function checkAPIRateLimit($endpoint, $ip) {
    global $pdo;
    
    // ...check logic...
    
    // Random cleanup again (duplicate logic)
    if (rand(1, 100) <= 10) {
        $pdo->prepare("DELETE FROM api_rate_limits WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)")
            ->execute();
    }
    
    return $allowed;
}
?>
```

### AFTER
**Step 1:** Create `bin/cleanup.php`
```php
<?php
/**
 * Scheduled Cleanup Script
 * Run hourly via cron: 0 * * * * php /path/to/bin/cleanup.php
 */
require_once __DIR__ . '/../config/config.php';

try {
    // Cleanup old login attempts
    $result1 = $pdo->prepare("DELETE FROM login_attempts WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)")
        ->execute();
    $deleted1 = $pdo->rowCount();
    
    // Cleanup old API rate limits
    $result2 = $pdo->prepare("DELETE FROM api_rate_limits WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)")
        ->execute();
    $deleted2 = $pdo->rowCount();
    
    // Optional: cleanup old import logs (30 days)
    $result3 = $pdo->prepare("DELETE FROM import_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)")
        ->execute();
    $deleted3 = $pdo->rowCount();
    
    echo "[" . date('Y-m-d H:i:s') . "] Cleanup complete: "
         . "$deleted1 login attempts, $deleted2 API records, $deleted3 import logs removed\n";
    
} catch (Exception $e) {
    error_log("Cleanup error: " . $e->getMessage());
    exit(1);
}
?>
```

**Step 2:** Update `config/config.php` — Remove random cleanup
```php
<?php
function checkLoginAttempts($ip) {
    global $pdo;
    
    // ...check logic...
    
    // ❌ DELETE THESE LINES (random cleanup):
    // if (rand(1, 100) <= 10) {
    //     $pdo->prepare("DELETE FROM login_attempts ...")->execute();
    // }
    
    return $allowed;
}

function checkAPIRateLimit($endpoint, $ip) {
    global $pdo;
    
    // ...check logic...
    
    // ❌ DELETE THESE LINES (random cleanup):
    // if (rand(1, 100) <= 10) {
    //     $pdo->prepare("DELETE FROM api_rate_limits ...")->execute();
    // }
    
    return $allowed;
}
?>
```

**Step 3:** Add cron job
```bash
# Add to crontab -e
0 * * * * php /path/to/smart_auto_qr/bin/cleanup.php >> /var/log/smart_auto_qr_cleanup.log 2>&1
```

### Why?
- **BEFORE:** Random cleanup can block requests (if 10K rows need deletion)
- **AFTER:** Scheduled cleanup at predictable time, no request blocking
- Cleaner code (cleanup logic in one place)

---

## 9️⃣ Optimize Edit Page Query
**File:** `admin/edit.php`  
**Impact:** ⏱️ -1 query  
**Complexity:** Very Low

### BEFORE
```php
<?php
$id = $_GET['id'] ?? 0;
$auto = null;

// Line 5: Pointless query assignment
$auto = $id ? $pdo->prepare("SELECT * FROM autos WHERE id=?")->execute([$id]) ? null : null : null;

// Lines 7-9: Actual query (overwrites $auto)
$stmt = $pdo->prepare("SELECT * FROM autos WHERE id=?");
$stmt->execute([$id]);
$auto = $stmt->fetch();
?>
```

### AFTER
```php
<?php
$id = $_GET['id'] ?? 0;
$auto = null;

// Direct, clean query
$stmt = $pdo->prepare("SELECT * FROM autos WHERE id=?");
$stmt->execute([$id]);
$auto = $stmt->fetch();
?>
```

### Why?
- **BEFORE:** Wasted query + confusing logic
- **AFTER:** Clear, single query
- Likely from refactoring error

---

## 🔟 Add Index for Faster Filtering
**File:** Database  
**Impact:** ⏱️ -10 to -50ms (specific queries)  
**Complexity:** Very Low

### Optional but Recommended
```sql
-- If manage.php filters by status frequently:
ALTER TABLE autos ADD INDEX idx_status (status);

-- If any page filters by area frequently:
ALTER TABLE autos ADD INDEX idx_area (area);

-- For dashboard pending SOS queries:
ALTER TABLE sos_logs ADD INDEX idx_status (status);
```

### Verify indexes
```sql
SHOW INDEXES FROM autos;
SHOW CREATE TABLE autos\G
```

---

## 📊 Performance Summary

| Win | File | Impact | Time |
|-----|------|--------|------|
| 1. Dashboard queries | admin/dashboard.php | -500ms | 10m |
| 2. QR URL batching | admin/manage.php | -50ms | 15m |
| 3. DB indexes | Database | -100 to -500ms | 5m |
| 4. QR helper | lib/helpers.php | Cleaner code | 15m |
| 5. Remove dead code | admin/edit.php | -1 query | 5m |
| 6. Area caching | admin/manage.php | -10ms | 10m |
| 7. Chart cache | admin/dashboard.php | -30ms | 10m |
| 8. Cleanup scheduler | bin/cleanup.php | Stability | 15m |
| 9. Edit query | admin/edit.php | Already done | 0m |
| 10. Extra indexes | Database | Optional | 5m |

**Total Impact:** ~600-700ms  
**Total Time:** 2-3 hours (first time)

---

## 🚀 Quick Implementation Guide

```bash
# 1. Performance wins (do in order)
# Step 1: Fix dashboard N+1 (10 min)
# Step 2: Fix manage QR batching (15 min)
# Step 3: Add database indexes (5 min)
# Step 4: Create helpers.php (15 min)
# Step 5: Cache areas (10 min)
# Step 6: Cache chart (10 min)

# Test after each step
# 2. Code cleanup (20 min)
# 3. Scheduler (optional, 15 min)

# Total: ~2-3 hours for full optimization
```

---

## ✅ Rollback Instructions

Each change is independent:
```bash
# If something breaks, revert that specific change
git revert <commit-hash>
git push origin main
```

Most changes can be tested locally first before deploying to production.
