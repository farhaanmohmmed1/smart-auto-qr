# 🗄️ Database Optimization & Caching Strategy
## Smart Auto QR Safety System

---

## 📊 Current Database Performance Profile

### Table Statistics
| Table | Rows | Indexes | Primary Key | Risk |
|-------|------|---------|-------------|------|
| `autos` | ~1K-10K | 2 | ✅ id | Medium (joins) |
| `scan_logs` | ~10K-100K | 2 | ✅ id | 🔴 High (joins, GROUP BY) |
| `sos_logs` | ~100-1K | 3 | ✅ id | Medium (joins) |
| `login_attempts` | ~100-1K | 3 | ✅ id | Low (cleanup) |
| `api_rate_limits` | ~1K-10K | 2 | ✅ id | Low (cleanup) |
| `import_logs` | ~10-100 | 2 | ✅ id | Low (audit) |
| `admins` | ~5-20 | 1 | ✅ id | Low (lookup) |

---

## 🔴 Missing Indexes (CRITICAL)

### 1. Foreign Key Indexes on scan_logs
**Current Issue:**
```sql
-- These join queries are SLOW without index
SELECT * FROM scan_logs sl 
JOIN autos a ON sl.auto_number = a.auto_number 
WHERE sl.scanned_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
-- Without index on scan_logs.auto_number: O(scan_logs_rows × autos_rows) — SLOW
-- With index on scan_logs.auto_number: O(scan_logs_rows × log(autos_rows)) — FAST
```

**Fix:**
```sql
ALTER TABLE scan_logs ADD INDEX idx_auto_number (auto_number);
ALTER TABLE scan_logs ADD INDEX idx_scanned_at (scanned_at);

-- Verify
SHOW INDEXES FROM scan_logs;
```

**Expected Impact:** -100 to -500ms on join queries (depends on scan_logs size)

---

### 2. Foreign Key Indexes on sos_logs
**Current Issue:**
```sql
-- SOS emergency log joins are slow
SELECT sl.*, a.driver_name, a.phone
FROM sos_logs sl
LEFT JOIN autos a ON sl.auto_number = a.auto_number
WHERE sl.status = 'pending'
```

**Fix:**
```sql
ALTER TABLE sos_logs ADD INDEX idx_auto_number (auto_number);
ALTER TABLE sos_logs ADD INDEX idx_status_created (status, created_at);
```

---

### 3. Filter Optimization Indexes
**Recommended:**
```sql
-- For manage.php filtering by status
ALTER TABLE autos ADD INDEX idx_status (status);

-- For manage.php filtering by area
ALTER TABLE autos ADD INDEX idx_area (area);

-- For dashboard stats
ALTER TABLE autos ADD INDEX idx_license (license_number);
ALTER TABLE autos ADD INDEX idx_reg (reg_number);

-- For rate limiting cleanup
ALTER TABLE login_attempts ADD INDEX idx_created_at (created_at);
ALTER TABLE api_rate_limits ADD INDEX idx_created_at (created_at);
```

---

## 📈 Query Performance Expectations

### Before Optimization
```sql
-- Dashboard stats query (6 separate queries)
Query 1: SELECT COUNT(*) FROM autos;                         -- ~5ms
Query 2: SELECT COUNT(*) FROM autos WHERE status='active';   -- ~5ms
Query 3: SELECT COUNT(*) FROM scan_logs;                     -- ~25ms (large table)
Query 4: SELECT COUNT(*) FROM scan_logs WHERE DATE(...)      -- ~25ms
Query 5: SELECT COUNT(*) FROM sos_logs;                      -- ~5ms
Query 6: SELECT COUNT(*) FROM sos_logs WHERE status='pending' -- ~5ms
TOTAL:                                                         ~70ms per load
(Plus network latency × 6 = +60-300ms) → Total: ~130-370ms
```

### After Optimization
```sql
-- Combined query (1 query)
SELECT (SELECT COUNT(*) FROM autos) as total_autos,
       (SELECT COUNT(*) FROM autos WHERE status='active') as active_autos,
       ...6 subselects
TOTAL:                                                         ~70ms per load
(Plus network latency × 1 = +10-50ms) → Total: ~80-120ms
```

**Net Improvement:** 50-300ms saved (depends on network latency)

---

## 🧠 Caching Strategy

### Strategy 1: Session-Based Caching (Recommended for Admin)
**Best For:** User-specific data, short-lived caches, admin panel  
**Pros:** No external dependency, automatic cleanup on logout  
**Cons:** Per-session (not shared), lost on session expiry  

**Implementation:**
```php
<?php
// Store in $_SESSION
$_SESSION['areas_cache'] = $areas;
$_SESSION['areas_cache_time'] = time();

// Retrieve with TTL check
if (isset($_SESSION['areas_cache']) && 
    (time() - $_SESSION['areas_cache_time']) < 3600) {
    $areas = $_SESSION['areas_cache'];
} else {
    // Query database, then cache
    $areas = $pdo->query("SELECT DISTINCT area FROM autos ...
")->fetchAll();
    $_SESSION['areas_cache'] = $areas;
    $_SESSION['areas_cache_time'] = time();
}
?>
```

### Strategy 2: File-Based Caching (Recommended for Public)
**Best For:** Public data, longer caches, small datasets  
**Pros:** Shared across users, persistent, simple  
**Cons:** Filesystem access overhead, manual cleanup needed  

**Implementation:**
```php
<?php
function getCachedData($cacheKey, $callback, $ttl = 3600) {
    $cacheDir = __DIR__ . '/../cache';
    $cacheFile = $cacheDir . '/' . md5($cacheKey) . '.cache';
    
    // Check if cache exists and is valid
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $ttl) {
        return unserialize(file_get_contents($cacheFile));
    }
    
    // Cache miss: execute callback
    $data = $callback();
    
    // Store in cache
    @file_put_contents($cacheFile, serialize($data), LOCK_EX);
    
    return $data;
}

// Usage:
$areas = getCachedData('distinct_areas', function() {
    global $pdo;
    return $pdo->query("SELECT DISTINCT area FROM autos ...")->fetchAll();
}, 7200);  // 2-hour cache
?>
```

### Strategy 3: In-Memory Caching (APCu/Redis - Optional for High Traffic)
**Best For:** High-traffic systems, real-time data  
**Pros:** Fastest, shared across PHP processes  
**Cons:** Requires APCu/Redis installation, memory overhead  

**For Future Optimization:**
```php
<?php
function getCachedData($cacheKey, $callback, $ttl = 3600) {
    // Try APCu first (if available)
    if (function_exists('apcu_fetch')) {
        $cached = apcu_fetch($cacheKey);
        if ($cached !== false) return $cached;
    }
    
    // Fallback to file cache
    // ... file cache implementation ...
}
?>
```

---

## 🎯 Recommended Cache Layers

### Layer 1: Admin Panel (Session Cache)
```
User Action → Check Session Cache → Hit: Serve | Miss: Query DB → Cache → Serve
```

| Data | TTL | Type | Invalidation |
|------|-----|------|--------------|
| Areas dropdown | 1 hour | Session | On new auto with new area |
| Dashboard stats | 5 min | Session | On manual refresh / 5 min |
| Chart data | 5 min | Session | Automatic after 5 min |
| Admin list | 10 min | Session | On admin action |

### Layer 2: Public Page (File Cache)
```
QR Scan → Check File Cache → Hit: Serve | Miss: Query DB → Cache → Serve
```

| Data | TTL | Type | Invalidation |
|------|-----|------|--------------|
| Auto details | 1 hour | File | On auto update |
| Driver info | 1 hour | File | On driver update |

### Layer 3: API (Conditional Cache)
```
API Request → ETag/Last-Modified → Use Cache | 304 Response
```

| Endpoint | TTL | Caching Strategy |
|----------|-----|------------------|
| `/api/auto.php` | 5 min | ETag support |
| `/api/sos.php` | None | Never cache |

---

## 💾 Cache Implementation Examples

### Example 1: Areas Dropdown Cache
```php
<?php
// In manage.php
function getAreasFromCache($pdo) {
    $cacheKey = 'admin_areas_cache';
    $cacheTtl = 3600;  // 1 hour
    
    // Session-based cache
    if (isset($_SESSION[$cacheKey]) && 
        isset($_SESSION[$cacheKey . '_time']) &&
        (time() - $_SESSION[$cacheKey . '_time']) < $cacheTtl) {
        return $_SESSION[$cacheKey];
    }
    
    // Cache miss: fetch from database
    $areas = $pdo->query("
        SELECT DISTINCT area FROM autos 
        WHERE area IS NOT NULL AND area != '' 
        ORDER BY area
    ")->fetchAll();
    
    // Store in session
    $_SESSION[$cacheKey] = $areas;
    $_SESSION[$cacheKey . '_time'] = time();
    
    return $areas;
}

// Usage:
$areas = getAreasFromCache($pdo);
?>
```

### Example 2: Dashboard Stats Cache
```php
<?php
// In dashboard.php
function getDashboardStats($pdo) {
    $cacheKey = 'dashboard_stats';
    $cacheTtl = 300;  // 5 minutes
    
    // Check cache
    if (isset($_SESSION[$cacheKey]) && 
        isset($_SESSION[$cacheKey . '_time']) &&
        (time() - $_SESSION[$cacheKey . '_time']) < $cacheTtl) {
        return $_SESSION[$cacheKey];
    }
    
    // Fetch from database
    $stats = $pdo->query("
        SELECT 
            (SELECT COUNT(*) FROM autos) as total_autos,
            (SELECT COUNT(*) FROM autos WHERE status='active') as active_autos,
            (SELECT COUNT(*) FROM scan_logs) as total_scans,
            (SELECT COUNT(*) FROM scan_logs WHERE DATE(scanned_at)=CURDATE()) as today_scans,
            (SELECT COUNT(*) FROM sos_logs) as total_sos,
            (SELECT COUNT(*) FROM sos_logs WHERE status='pending') as pending_sos
    ")->fetch();
    
    // Cache result
    $_SESSION[$cacheKey] = $stats;
    $_SESSION[$cacheKey . '_time'] = time();
    
    return $stats;
}

// Usage:
$stats = getDashboardStats($pdo);
echo "Total Autos: {$stats['total_autos']}";
?>
```

### Example 3: Cache Invalidation on Update
```php
<?php
// In auto registration/edit
if ($pdo->prepare("UPDATE autos SET area=? WHERE id=?")->execute([$area, $id])) {
    // Invalidate cache
    unset($_SESSION['admin_areas_cache']);
    unset($_SESSION['admin_areas_cache_time']);
    unset($_SESSION['dashboard_stats']);
    unset($_SESSION['dashboard_stats_time']);
}
?>
```

---

## 🧹 Cache Cleanup Strategy

### Option 1: Manual Invalidation (Immediate)
**When:** Data changes  
**Where:** After INSERT/UPDATE/DELETE

```php
<?php
function invalidateCache($keys = ['all']) {
    $cacheKeys = [
        'admin_areas_cache',
        'dashboard_stats',
        'dashboard_scan_chart',
        'auto_list_cache',
    ];
    
    if (in_array('all', $keys)) {
        foreach ($cacheKeys as $key) {
            unset($_SESSION[$key]);
            unset($_SESSION[$key . '_time']);
        }
    } else {
        foreach ($keys as $key) {
            unset($_SESSION[$key]);
            unset($_SESSION[$key . '_time']);
        }
    }
}

// After auto update:
if ($stmt->execute([$name, $id])) {
    invalidateCache(['admin_areas_cache', 'dashboard_stats']);
}
?>
```

### Option 2: Time-Based Expiration (Lazy)
**When:** TTL expires  
**Where:** Check before using cache

```php
<?php
function isCacheValid($key, $ttl) {
    return isset($_SESSION[$key . '_time']) && 
           (time() - $_SESSION[$key . '_time']) < $ttl;
}

// Usage:
if (isset($_SESSION[$cacheKey]) && isCacheValid($cacheKey, 3600)) {
    return $_SESSION[$cacheKey];  // Use cached
} else {
    // Regenerate cache
}
?>
```

### Option 3: Hybrid (Recommended)
**Best of both worlds:**
- Use cache if valid (time-based)
- Invalidate immediately on data change (event-based)

---

## 📊 Query Performance Optimization

### Slow Query Log
**Enable in MySQL:**
```sql
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL long_query_time = 0.5;  -- Log queries >500ms

-- Check slow log:
SHOW VARIABLES LIKE 'slow_query_log%';
SELECT * FROM mysql.slow_log;
```

### Index Analysis
```sql
-- Check if index is being used
EXPLAIN SELECT * FROM autos WHERE status = 'active';
-- Look for: key_len, rows_examined

-- Analyze table statistics
ANALYZE TABLE autos;
ANALYZE TABLE scan_logs;
ANALYZE TABLE sos_logs;
```

---

## 🚀 Implementation Priority

### Phase 1: Critical (1 day)
- [ ] Add missing indexes (5 min)
- [ ] Combine dashboard queries (10 min)
- [ ] Batch QR URL computation (15 min)

### Phase 2: Important (1 day)
- [ ] Implement session caching for areas (10 min)
- [ ] Implement session caching for dashboard (10 min)
- [ ] Extract QR helper function (15 min)

### Phase 3: Nice-to-Have (optional)
- [ ] File-based cache for public pages
- [ ] APCu/Redis (if high traffic)
- [ ] Query profiling & optimization

---

## 📈 Expected Performance Gains

### Before Optimization
- Dashboard: 300-600ms
- Manage page: 100-150ms
- Edit page: 50-100ms
- **Total per user session:** ~2-3 seconds waste

### After Optimization
- Dashboard: 50-100ms (cached) or 100-150ms (fresh)
- Manage page: 50-100ms (with index)
- Edit page: 25-50ms (no double query)
- **Total per user session:** <1 second waste

**Improvement:** ~2-3 seconds saved per user session

---

## ✅ Verification Checklist

- [ ] All indexes created and verified with `SHOW INDEXES`
- [ ] Dashboard queries reduced from 6 to 1
- [ ] Manage page batch computes QR URLs
- [ ] Cache invalidation works after updates
- [ ] No functionality regression
- [ ] Performance tests show improvements

---

## 🔗 Related Documentation

See:
- `OPTIMIZATION_CHECKLIST.md` — Detailed implementation steps
- `TOP_10_PERFORMANCE_WINS.md` — Code-level optimization examples
- `AUDIT_FINDINGS.md` — Full audit report
