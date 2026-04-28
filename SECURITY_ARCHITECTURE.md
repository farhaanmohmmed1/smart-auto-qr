# 🛡️ SMART AUTO QR - SECURITY ARCHITECTURE REFERENCE
## For Developers & Security Auditors

---

## **🏗️ SECURITY LAYERS OVERVIEW**

```
┌─────────────────────────────────────────────────────────────┐
│  LAYER 1: HTTPS/TLS Transport Security                      │
│  - Enforced via .htaccess rewrite rules                     │
│  - HSTS header forces HTTPS for 1 year                      │
│  │ BLOCKS: Man-in-the-middle attacks, password interception │
│  └─────────────────────────────────────────────────────────────┘
          ↓
┌─────────────────────────────────────────────────────────────┐
│  LAYER 2: Authentication & Authorization                    │
│  - Session-based auth with bcrypt password hashing          │
│  - Rate limiting: 5 failed attempts → 30 min lockout        │
│  │ BLOCKS: Brute force attacks, credential stuffing         │
│  └─────────────────────────────────────────────────────────────┘
          ↓
┌─────────────────────────────────────────────────────────────┐
│  LAYER 3: CSRF & Session Protection                         │
│  - CSRF tokens generated and validated on forms             │
│  - Secure session cookies (HttpOnly, SameSite=Strict)       │
│  │ BLOCKS: CSRF attacks, session hijacking, XSS             │
│  └─────────────────────────────────────────────────────────────┘
          ↓
┌─────────────────────────────────────────────────────────────┐
│  LAYER 4: Input Validation & Sanitization                   │
│  - Auto ID: alphanumeric + hyphen only, max 50 chars        │
│  - GPS: latitude -90 to 90, longitude -180 to 180           │
│  - Phone: 10-12 digits only                                 │
│  │ BLOCKS: SQL injection, XSS, invalid data                 │
│  └─────────────────────────────────────────────────────────────┘
          ↓
┌─────────────────────────────────────────────────────────────┐
│  LAYER 5: API Rate Limiting & Abuse Prevention              │
│  - Public API: 30 requests/minute per IP                    │
│  - SOS API: 3 requests/10 minutes per IP                    │
│  │ BLOCKS: DDoS, API scraping, SOS flooding                 │
│  └─────────────────────────────────────────────────────────────┘
          ↓
┌─────────────────────────────────────────────────────────────┐
│  LAYER 6: Data Protection & Privacy                         │
│  - Phone numbers masked in API responses                    │
│  - GPS coordinates rounded for privacy                      │
│  - Sensitive fields not included in public API              │
│  │ BLOCKS: Information leakage, privacy violations          │
│  └─────────────────────────────────────────────────────────────┘
          ↓
┌─────────────────────────────────────────────────────────────┐
│  LAYER 7: Apache & Server Security                          │
│  - Directory listing disabled (Options -Indexes)            │
│  - Sensitive files denied (.sql, .env, .log)                │
│  - Security headers (X-Frame-Options, CSP, etc)             │
│  │ BLOCKS: Directory traversal, file exposure, clickjacking │
│  └─────────────────────────────────────────────────────────────┘
```

---

## **🔐 ATTACK PREVENTION MATRIX**

### **SQL Injection**
```
┌─ DEFENSE: Prepared Statements (PDO)
│
├─ VULNERABLE:
│   $sql = "SELECT * FROM autos WHERE id = " . $_GET['id'];
│   $pdo->query($sql);  // ❌ Directly inserted
│
└─ PROTECTED:
    $stmt = $pdo->prepare("SELECT * FROM autos WHERE id = ?");
    $stmt->execute([$_GET['id']]);  // ✅ Parameterized
    
    Even if $_GET['id'] = "1' OR '1'='1", the query treats it as a string value
```

### **Cross-Site Scripting (XSS)**
```
┌─ DEFENSE 1: HTML Escaping
│
├─ VULNERABLE:
│   echo "Welcome " . $_GET['name'];  // ❌ Script tags render
│
├─ PROTECTED:
│   echo "Welcome " . e($_GET['name']);  // ✅ <script> → &lt;script&gt;
│   function e($str) { return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5); }
│
├─ DEFENSE 2: Content Security Policy (CSP)
│   Header: Content-Security-Policy: script-src 'self' 'unsafe-inline'
│   Blocks: Inline scripts, external script injections
│
└─ DEFENSE 3: Session Cookie Security
    session.cookie_httponly = true      // JS cannot access cookies
    session.cookie_samesite = Strict    // Blocks XSS exfiltration
```

### **Cross-Site Request Forgery (CSRF)**
```
┌─ VULNERABLE:
│   Attacker page: <img src="https://police.gov/admin/delete.php?id=1">
│   If admin visits → Record deleted without consent
│
└─ PROTECTED:
    1. Generate unique token per session:
       $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    
    2. Include in HTML:
       <form method="POST">
         <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
       </form>
    
    3. Validate on server:
       if (!validateCSRFToken($_POST['csrf_token'])) {
           http_response_code(403);
           exit('CSRF token invalid');
       }
    
    Attacker cannot include correct token in forged request!
```

### **Brute Force Login Attacks**
```
┌─ VULNERABLE:
│   while (true) {
│       Try password 1, password 2, password 3, ... password 1000000
│   }
│   With 1000 attempts/sec, crack 12-char password in ~1 hour
│
└─ PROTECTED:
    Table: login_attempts
    ├─ username
    ├─ ip_address
    ├─ attempt_time
    └─ success (bool)
    
    On each login attempt:
    1. Count failed attempts in last 15 min
    2. If >= 5 failed: Check if ANY failed in last 30 min
    3. If 5+ in 30 min window: Lock and return error
    4. Attacker must wait 30 minutes to retry
    
    With 1000 attempts/sec, cracks only 5 passwords per 30 min!
```

### **API Scraping/DDoS**
```
┌─ VULNERABLE:
│   for i in range(1000000):
│       requests.get("https://api/auto.php?id=AUTO-" + str(i))
│   Scrapes all drivers, extracts phone numbers
│
└─ PROTECTED:
    Table: api_rate_limits
    ├─ endpoint (e.g., '/api/auto.php')
    ├─ ip_address
    └─ request_time
    
    On each API request:
    1. Count requests from IP in last 1 minute
    2. For /api/auto.php: Max 30 requests → reject on 31st
    3. For /api/sos.php: Max 3 requests/10 min → reject on 4th
    
    Scraper can only extract 30 records per minute = 43k per day (vs 1M+)
```

### **Fake SOS Emergencies**
```
┌─ VULNERABLE:
│   POST /api/sos.php with auto_number="AUTO-001"
│   System logs emergency, police respond
│   Attacker floods with fake SOS → Police overloaded
│
└─ PROTECTED:
    1. Rate limit: Max 3 SOS/10 min per IP
    2. Auto validation: Auto must exist & be active
    3. GPS validation: Coordinates must be within valid range
    4. Duplicate detection: Don't log same SOS twice
    
    Attacker limited to 3 SOS every 10 minutes
    (Real emergency: passenger clicks once, waits for help)
```

### **Credential Exposure**
```
┌─ VULNERABLE:
│   Database schema.sql contains:
│   INSERT INTO admins VALUES ('admin', 'Admin@1234');
│   If schema.sql is leaked → Password known to world
│
└─ PROTECTED:
    1. NO default credentials in schema
    2. First-time setup.php forces strong password:
       - 12+ chars, UPPERCASE, lowercase, number, special char
    3. setup.php deleted immediately after use
    4. Password stored as bcrypt hash (not reversible)
    
    Even if database is leaked, password is hashed with cost=12
    (Takes $10 trillion years to crack per password)
```

---

## **📊 SECURITY CONFIGURATION DETAILS**

### **Session Cookie Settings**

```php
// In setupSecureSessionCookies():
session_set_cookie_params([
    'lifetime' => 3600,           // 1 hour total
    'path'     => '/',            // Available everywhere
    'domain'   => $_SERVER['HTTP_HOST'], // This domain only
    'secure'   => true,           // HTTPS only (no HTTP)
    'httponly' => true,           // JS cannot access via document.cookie
    'samesite' => 'Strict',       // Never send cross-site (CSRF protection)
]);
```

**Why each setting matters:**

| Setting | Why Needed |
|---------|-----------|
| `secure=true` | Prevents MITM attacks (WiFi sniffing) |
| `httponly=true` | Blocks XSS cookie theft via JS |
| `samesite=Strict` | Total CSRF protection, no cross-site requests |
| `lifetime=3600` | Session expires, logout not optional |

### **Password Validation Rules**

```php
function validatePassword(string $password): bool {
    // Requirement 1: Minimum length = 12 characters
    if (strlen($password) < 12) return false;
    
    // Requirement 2: At least 1 uppercase letter
    if (!preg_match('/[A-Z]/', $password)) return false;
    
    // Requirement 3: At least 1 lowercase letter
    if (!preg_match('/[a-z]/', $password)) return false;
    
    // Requirement 4: At least 1 digit
    if (!preg_match('/[0-9]/', $password)) return false;
    
    // Requirement 5: At least 1 special character
    if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) return false;
    
    return true;  // Meets all requirements
}
```

**Why 12 characters?**
- 12-char password with mixed types = 62^12 possible combinations
- Supercomputer trying 1 trillion/sec needs ~200 years to crack

### **HTTPS/HSTS Configuration**

```apache
# In .htaccess
# Force HTTP → HTTPS redirect
RewriteCond %{HTTPS} off
RewriteCond %{HTTP_HOST} !^(localhost|127\.0\.0\.1) [NC]
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Tell browser: ONLY use HTTPS for next 1 year
Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
```

**How HSTS works:**
```
Visit 1: User goes to http://police.gov → Redirected to https://
         Browser receives HSTS header for 31536000 seconds
Visit 2-N: Browser AUTOMATICALLY goes to https:// (no redirect needed)
           Attacker cannot intercept even first request
```

---

## **🚨 ATTACK SCENARIOS & MITIGATIONS**

### **Scenario 1: WiFi Attacker at Coffee Shop**

```
ATTACK:
1. Admin connects to public WiFi
2. Attacker intercepts traffic with Wireshark
3. Admin's password captured unencrypted
4. Attacker logs in to police system

DEFENSE:
✅ HTTPS enforced → All traffic encrypted, password unreadable
✅ Even if HTTPS ignored, .htaccess redirects HTTP → HTTPS
✅ HSTS header prevents downgrade attacks
```

### **Scenario 2: Automated Brute Force Bot**

```
ATTACK:
1. Bot tries password1, password2, ..., password1000000
2. Cracks weak password in < 1 hour
3. Gains admin access, modifies driver records

DEFENSE:
✅ Rate limiting: After 5 failed attempts in 15 min → locked
✅ Lockout duration: 30 minutes (must wait)
✅ IP-based: Each IP has separate counter (prevents timing attacks)
```

### **Scenario 3: CSRF Clickjacking**

```
ATTACK:
1. Attacker posts: "Click for free police records!" link
2. Link actually: <img src="admin/delete.php?id=123">
3. Admin clicks, browser automatically sends cookie
4. Record deleted without admin knowing

DEFENSE:
✅ CSRF token generated per session (random 64-byte value)
✅ Token must match in POST data
✅ Attacker cannot guess or steal token (secure randomness)
✅ SameSite=Strict blocks automatic cookie sending
```

### **Scenario 4: Data Scraping Bot**

```
ATTACK:
1. Bot: for i in range(1000000): GET /api/auto.php?id=AUTO-{i}
2. Extracts 1M auto records → phone numbers harvested
3. Sells to spam/harassment networks

DEFENSE:
✅ Rate limiter: 30 requests/min per IP
✅ Bot gets only 30 records per minute
✅ Legitimate APIs (30/min) not affected
✅ Aggressive scrapers detected and logged
```

### **Scenario 5: SOS Spam/False Emergency**

```
ATTACK:
1. Attacker: POST /api/sos.php with random autos
2. Floods emergency system with 1000 fake alerts
3. Police overwhelmed, real emergencies ignored

DEFENSE:
✅ Rate limit: 3 SOS per 10 minutes per IP
✅ Auto validation: Auto must exist & be active
✅ GPS validation: Coordinates must be realistic
✅ Attacker limited to 18 SOS per hour (vs 1000+)
✅ Pattern detection: 18 SOS from same IP flagged
```

---

## **🔍 SECURITY CODE REVIEW CHECKLIST**

When adding new features, ensure:

### **Authentication**
- [ ] All admin pages call `requireAdmin()` at top
- [ ] Sessions use `session_regenerate_id(true)` after login
- [ ] Passwords hashed with `password_hash(..., PASSWORD_BCRYPT)`
- [ ] Password verification uses `password_verify()`

### **CSRF Protection**
- [ ] All `<form method="POST">` includes CSRF token:
      ```html
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRFToken()) ?>">
      ```
- [ ] POST handler validates token:
      ```php
      if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
          http_response_code(403);
          exit('CSRF token invalid');
      }
      ```

### **Input Validation**
- [ ] All user input sanitized with `trim()`, `strtoupper()`, etc.
- [ ] Regex validation for special formats:
      - Auto ID: `/^[A-Z0-9-]{1,50}$/`
      - Phone: `/^\d{10}$|^\d{12}$/`
      - GPS: `validateGPSCoordinates($lat, $lon)`
- [ ] All inputs escaped with `e()` before output in HTML

### **SQL Injection**
- [ ] NO string concatenation in SQL:
      ```php
      // ❌ WRONG
      $sql = "SELECT * FROM autos WHERE id = " . $_GET['id'];
      
      // ✅ RIGHT
      $stmt = $pdo->prepare("SELECT * FROM autos WHERE id = ?");
      $stmt->execute([$_GET['id']]);
      ```

### **XSS Prevention**
- [ ] All user data output escaped: `<?= e($data) ?>`
- [ ] Exception: explicit HTML is pre-escaped
- [ ] No `eval()`, `exec()`, or `system()` with user input

### **API Security**
- [ ] Rate limiting checked: `checkAPIRateLimit()` called
- [ ] Rate limit recorded: `recordAPIRequest()` called
- [ ] Input validation performed on all parameters
- [ ] Response headers set: `header('Content-Type: application/json')`

### **Error Handling**
- [ ] User-facing errors are generic (no SQL syntax shown)
- [ ] System errors logged to file (not displayed)
- [ ] HTTP status codes correct (400, 403, 404, 429, 500)

### **Data Protection**
- [ ] Sensitive data masked in outputs (phone → XXX-XXXX)
- [ ] No PII in URLs, API responses, or logs
- [ ] Database credentials in `config.php` (never hardcoded elsewhere)

---

## **📈 SECURITY METRICS & MONITORING**

### **Key Metrics to Track**

```sql
-- Failed login attempts per user (per week)
SELECT username, COUNT(*) as attempts, DATEDIFF(MAX(attempt_time), MIN(attempt_time)) as days
FROM login_attempts
WHERE success=0 AND attempt_time > DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY username
ORDER BY attempts DESC;

-- Most active API clients
SELECT endpoint, ip_address, COUNT(*) as requests
FROM api_rate_limits
WHERE request_time > DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY endpoint, ip_address
ORDER BY requests DESC
LIMIT 20;

-- SOS requests from same IP (detect spam)
SELECT ip_address, COUNT(*) as sos_count
FROM sos_logs
WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
GROUP BY ip_address
HAVING sos_count > 5
ORDER BY sos_count DESC;
```

### **Security Audit Log**
```sql
-- View all successful and failed admin logins
SELECT username, success, COUNT(*) as count, MAX(attempt_time) as latest
FROM login_attempts
WHERE attempt_time > DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY username, success
ORDER BY latest DESC;

-- Export to CSV for audit
SELECT * FROM login_attempts
WHERE attempt_time > '2024-01-01'
INTO OUTFILE '/tmp/login_audit.csv'
FIELDS TERMINATED BY ',' ENCLOSED BY '"';
```

---

## **🛠️ MAINTENANCE TASKS**

### **Daily (Automated)**
```cron
# Run nightly cleanup via cron job:
0 2 * * * php /var/www/html/cron/cleanup.php
```

### **Weekly (Manual)**
```php
// Cleanup old rate limit entries
DELETE FROM api_rate_limits 
WHERE request_time < DATE_SUB(NOW(), INTERVAL 7 DAY);

DELETE FROM login_attempts 
WHERE attempt_time < DATE_SUB(NOW(), INTERVAL 7 DAY);
```

### **Monthly (Audit)**
```sql
-- Review login patterns for compromise
SELECT DATE(attempt_time) as date, username, COUNT(*) as total, 
       SUM(success) as successful, count(*) - SUM(success) as failed
FROM login_attempts
WHERE attempt_time > DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY DATE(attempt_time), username
ORDER BY date DESC, total DESC;
```

---

## **🚀 DEPLOYMENT SECURITY CHECKLIST**

Before going live:

```bash
# 1. Remove sensitive files from server
rm -f admin/setup.php database/schema.sql .git .env.local

# 2. Set file permissions
chmod 600 config/config.php         # Only PHP can read
chmod 700 admin api lib             # Execute only
chmod 755 qrcodes uploads           # Public read

# 3. Verify HTTPS works
curl -I https://yourdomain.com/admin/
# Should show: 200 OK, no warnings

# 4. Verify HSTS header
curl -I https://yourdomain.com | grep Strict-Transport-Security
# Should show: max-age=31536000

# 5. Test rate limiting
for i in {1..35}; do curl https://yourdomain.com/api/auto.php?id=AUTO-001; done
# Should reject after 30 requests

# 6. Test CSRF protection
curl -X POST https://yourdomain.com/admin/login.php \
     -d "username=admin&password=test" \
     -H "Referer: https://evil.com"
# Should reject (missing CSRF token)
```

---

**Last Updated:** April 28, 2026  
**Author:** Security Engineering Team  
**Version:** 1.0 - Production Release
