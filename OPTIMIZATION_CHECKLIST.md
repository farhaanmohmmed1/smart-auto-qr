# ✅ Optimization Checklist
## Smart Auto QR Safety System — Action Items

**Total Items:** 12  
**Estimated Implementation Time:** 2-3 hours  
**Performance Impact:** ~600ms total improvement

---

## 🔴 CRITICAL (High Impact, ~1 hour)

### 1. Fix Dashboard N+1 Query Problem
- **File:** `admin/dashboard.php`
- **Priority:** 🔴 CRITICAL
- **Time:** 10 minutes
- **Impact:** -500ms (dashboard load time)
- **Steps:**
  - [ ] Combine 6 COUNT queries into 1-2 queries
  - [ ] Test that all stats display correctly
  - [ ] Verify dashboard performance improvement

**Code Change Required:**
```php
// BEFORE: 6 queries
$totalAutos  = $pdo->query("SELECT COUNT(*) FROM autos");
// ... 5 more queries ...

// AFTER: 1 query
$stats = $pdo->query("
    SELECT 
        (SELECT COUNT(*) FROM autos) as total_autos,
        (SELECT COUNT(*) FROM autos WHERE status='active') as active_autos,
        (SELECT COUNT(*) FROM scan_logs) as total_scans,
        (SELECT COUNT(*) FROM scan_logs WHERE DATE(scanned_at)=CURDATE()) as today_scans,
        (SELECT COUNT(*) FROM sos_logs) as total_sos,
        (SELECT COUNT(*) FROM sos_logs WHERE status='pending') as pending_sos
")->fetch();
```

---

### 2. Fix Manage Page QR URL N+1 (List Should Show Efficiently)
- **File:** `admin/manage.php`
- **Priority:** 🔴 CRITICAL
- **Time:** 15 minutes
- **Impact:** -50ms (avoid 20 filesystem checks)
- **Steps:**
  - [ ] Pre-compute QR URLs in single loop before rendering
  - [ ] Test that all QR URLs display correctly
  - [ ] Verify page load improvement

**Code Change Required:**
```php
// BEFORE: Inside loop
foreach ($autos as $a) {
    $qrUrl = QRGenerator::getQRImageURL($a['auto_number']);  // ← 20 calls
}

// AFTER: Pre-compute all
$qrUrls = [];
foreach ($autos as $a) {
    $qrUrls[$a['auto_number']] = QRGenerator::getURL($a['auto_number']);
}
// Then use: $qrUrls[$a['auto_number']]
```

---

### 3. Add Missing Database Indexes
- **File:** `database/schema.sql` or run as migration
- **Priority:** 🔴 CRITICAL
- **Time:** 5 minutes
- **Impact:** -100 to -500ms (join queries)
- **Steps:**
  - [ ] Run SQL index creation commands
  - [ ] Verify indexes are created: `SHOW INDEXES FROM sos_logs; SHOW INDEXES FROM scan_logs;`
  - [ ] Benchmark join queries (optional)

**SQL Commands:**
```sql
ALTER TABLE sos_logs ADD INDEX idx_auto_number (auto_number);
ALTER TABLE scan_logs ADD INDEX idx_auto_number (auto_number);

-- Optional: Add indexes for other common queries
ALTER TABLE login_attempts ADD INDEX idx_ip_method (ip_address, method);
ALTER TABLE api_rate_limits ADD INDEX idx_endpoint_ip (endpoint, ip_address);
```

✅ **Subtotal:** 30 minutes, -550ms improvement

---

## ⚠️ IMPORTANT (Medium Priority, ~1 hour)

### 4. Extract QR Regeneration Helper Function
- **File:** Create `lib/helpers.php` (new file)
- **Priority:** ⚠️ HIGH
- **Time:** 15 minutes
- **Impact:** Code reusability, consistency
- **Steps:**
  - [ ] Create `lib/helpers.php`
  - [ ] Add `regenerateAutoQR($autoNumber, $pdo)` function
  - [ ] Update `admin/register.php` to use helper
  - [ ] Update `admin/edit.php` to use helper
  - [ ] Test both register and edit pages work correctly

**New Function:**
```php
<?php
// lib/helpers.php

function regenerateAutoQR($autoNumber, $pdo) {
    QRGenerator::delete($autoNumber);
    $autoUrl = generateAutoURL($autoNumber);
    $qrPath  = QRGenerator::generate($autoUrl, $autoNumber);
    if ($qrPath) {
        $stmt = $pdo->prepare("UPDATE autos SET qr_path=? WHERE auto_number=?");
        $stmt->execute([$qrPath, $autoNumber]);
        return $qrPath;
    }
    return false;
}
```

**Usage in register.php & edit.php:**
```php
regenerateAutoQR($auto['auto_number'], $pdo);
```

---

### 5. Remove Dead Code (Edit Page Double Query)
- **File:** `admin/edit.php`
- **Priority:** ⚠️ HIGH
- **Time:** 5 minutes
- **Impact:** Remove 1 dead DB query
- **Steps:**
  - [ ] Delete lines 5-6 (dead code)
  - [ ] Verify edit page still loads auto data correctly
  - [ ] Test edit form displays data

**Code to Remove:**
```php
// DELETE THIS LINE (line 5):
$auto = $id ? $pdo->prepare("SELECT * FROM autos WHERE id=?")->execute([$id]) ? null : null : null;
```

---

### 6. Cache Areas Dropdown
- **File:** `admin/manage.php`
- **Priority:** ⚠️ MEDIUM
- **Time:** 10 minutes
- **Impact:** -10ms per page load
- **Steps:**
  - [ ] Add session-level caching for areas
  - [ ] Invalidate cache when new auto is added/edited
  - [ ] Test dropdown shows all areas correctly
  - [ ] Test areas update when new auto with new area is added

**Code Change:**
```php
// BEFORE:
$areas = $pdo->query("SELECT DISTINCT area FROM autos WHERE area IS NOT NULL AND area != '' ORDER BY area")->fetchAll();

// AFTER:
if (!isset($_SESSION['areas_cache'])) {
    $_SESSION['areas_cache'] = $pdo->query("SELECT DISTINCT area FROM autos WHERE area IS NOT NULL AND area != '' ORDER BY area")->fetchAll();
}
$areas = $_SESSION['areas_cache'];
```

**Invalidation:** Add after INSERT/UPDATE with area:
```php
unset($_SESSION['areas_cache']);  // Invalidate cache
```

---

### 7. Cache Scan Chart Data
- **File:** `admin/dashboard.php`
- **Priority:** ⚠️ MEDIUM
- **Time:** 10 minutes
- **Impact:** -30ms per page load (5-min cache)
- **Steps:**
  - [ ] Implement 5-minute result cache for chart query
  - [ ] Test chart displays correctly
  - [ ] Verify cache invalidates after 5 minutes

**Code Change:**
```php
// Implement simple time-based cache
$cacheKey = 'scan_chart';
$cacheTtl = 300;  // 5 minutes

if (!isset($_SESSION[$cacheKey]) || (time() - ($_SESSION[$cacheKey . '_ts'] ?? 0)) > $cacheTtl) {
    $scanChart = $pdo->query("
        SELECT DATE(scanned_at) as day, COUNT(*) as cnt
        FROM scan_logs
        WHERE scanned_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        GROUP BY DATE(scanned_at)
    ")->fetchAll();
    $_SESSION[$cacheKey] = $scanChart;
    $_SESSION[$cacheKey . '_ts'] = time();
} else {
    $scanChart = $_SESSION[$cacheKey];
}
```

✅ **Subtotal:** 50 minutes, cleaner code

---

## 🟡 NICE-TO-HAVE (Low Priority, ~30 minutes)

### 8. Create Cleanup Scheduler
- **File:** Create `bin/cleanup.php` (new file)
- **Priority:** 🟡 LOW
- **Time:** 15 minutes
- **Impact:** Cleaner code, more efficient cleanup
- **Steps:**
  - [ ] Create `bin/cleanup.php` with scheduled cleanup script
  - [ ] Remove random cleanup from `config.php`
  - [ ] Add cron job: `0 * * * * php /path/to/bin/cleanup.php`
  - [ ] Test cleanup script works

**New Script (bin/cleanup.php):**
```php
<?php
require_once __DIR__ . '/../config/config.php';

// Cleanup old login attempts (>1 hour)
$pdo->prepare("DELETE FROM login_attempts WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)")->execute();

// Cleanup old API rate limits (>1 hour)
$pdo->prepare("DELETE FROM api_rate_limits WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)")->execute();

// Cleanup old import logs (>30 days, optional)
$pdo->prepare("DELETE FROM import_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)")->execute();

echo "[" . date('Y-m-d H:i:s') . "] Cleanup completed\n";
?>
```

**Cron Setup:**
```bash
# Add to crontab
0 * * * * php /path/to/smart_auto_qr/bin/cleanup.php >> /var/log/smart_auto_qr_cleanup.log 2>&1
```

---

### 9. Add Query Performance Logging
- **File:** Create `lib/QueryLogger.php` (new file)
- **Priority:** 🟡 LOW
- **Time:** 15 minutes
- **Impact:** Visibility into slow queries
- **Steps:**
  - [ ] Create simple query logger class
  - [ ] Optional: Wrap PDO queries with timing
  - [ ] Log slow queries (>100ms)

---

### 10. CSS Cleanup (Optional)
- **File:** `admin/assets/css/admin.css`
- **Priority:** 🟡 LOW (minimal impact)
- **Time:** 20 minutes
- **Impact:** Slightly smaller CSS file
- **Steps:**
  - [ ] Post-launch, use PurgeCSS to identify unused styles
  - [ ] Remove unused utility classes
  - [ ] Minify CSS (optional)

---

## 📋 Testing Checklist

### Before Deployment
- [ ] Dashboard page loads in <200ms (test with DevTools)
- [ ] Manage page loads in <100ms with 20 autos
- [ ] Register form works and generates QR
- [ ] Edit auto works and regenerates QR
- [ ] Dropdown areas show all unique areas
- [ ] Dashboard chart displays correctly
- [ ] Login rate limiting still works
- [ ] API rate limiting still works

### Verification Commands
```bash
# Test database indexes exist
mysql> SHOW INDEXES FROM sos_logs WHERE Column_name = 'auto_number';
mysql> SHOW INDEXES FROM scan_logs WHERE Column_name = 'auto_number';

# Verify function calls work
# Test helper function: regenerateAutoQR()
# Test caching: echo $_SESSION['areas_cache'];
```

---

## 🎯 Priority Execution Order

1. ✅ **P0 (Do First):** Checklist #1, #2, #3 (30 min, -550ms)
2. ⚠️ **P1 (Do Next):**  Checklist #4, #5 (20 min, code quality)
3. ⚠️ **P2 (Follow Up):** Checklist #6, #7 (20 min, -40ms)
4. 🟡 **P3 (Nice):** Checklist #8, #9, #10 (optional)

---

## 💡 Implementation Notes

**Git Workflow:**
```bash
git checkout -b feature/optimization
# Make changes per checklist
git commit -m "Optimize: Fix N+1 queries, cache results, extract helpers"
git push origin feature/optimization
# Create PR, review, merge
```

**Rollback Plan:**
Each change is independent and can be rolled back individually.

---

## 📊 Success Criteria

✅ All checklist items marked complete  
✅ All tests passing  
✅ Dashboard load time <200ms  
✅ Manage page load time <100ms  
✅ No regressions in functionality  
✅ Code review approved

---

**Estimated Total Time:** 2-3 hours for priority items 1-7  
**Performance Gain:** ~600ms total improvement  
**Code Quality:** Significant improvement in maintainability
