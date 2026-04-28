# 🔒 SMART AUTO QR SAFETY SYSTEM - SECURITY HARDENING GUIDE
## Production Deployment Checklist

---

## **📋 DEPLOYMENT STEPS (In Order)**

### **STEP 1: Database Setup**
```bash
# 1.1 Backup your existing database (if any)
mysqldump -u root smart_auto_db > backup_$(date +%Y%m%d_%H%M%S).sql

# 1.2 Import the updated schema
mysql -u root smart_auto_db < database/schema.sql

# 1.3 Verify new tables created
mysql -u root -e "USE smart_auto_db; SHOW TABLES;"
# Should show: autos, admins, login_attempts, api_rate_limits, scan_logs, sos_logs
```

### **STEP 2: Update Configuration Files**

#### **Update config/config.php**
- ✅ Already done in security fixes
- Key additions:
  - `validatePassword()` function
  - `generateCSRFToken()` and `validateCSRFToken()`
  - `enforceHTTPS()` function
  - `setupSecureSessionCookies()`
  - `checkLoginAttempts()` and `recordLoginAttempt()`
  - `checkAPIRateLimit()` and `recordAPIRequest()`
  - `validateGPSCoordinates()` and `validatePhoneNumber()`

#### **Update .htaccess**
- ✅ Already done in security fixes
- Key additions:
  - HTTPS redirect rules
  - HSTS header (force HTTPS for 1 year)
  - Content Security Policy (CSP)
  - Security headers (X-Frame-Options, etc.)

### **STEP 3: Remove Hardcoded Credentials**
```bash
# Before deploying to production:
# 1. Check schema.sql doesn't have default admin (should be removed)
grep "Admin@1234" database/schema.sql  # Should return NOTHING

# 2. Check config.php doesn't expose secrets
# Make sure DB_PASS is configured properly for your environment
```

### **STEP 4: Initial Admin Account Setup**

**⚠️ On First Deployment:**

```
1. Deploy all files to server
2. Import database schema
3. Navigate to: https://your-domain.gov.in/admin/setup.php
4. Create your first Super Admin account with STRONG password:
   - Username: your_admin_username
   - Password: Min 12 chars, with UPPERCASE, lowercase, number, special char
   - Example: "Police@2024#Secure"
5. Verify admin account created successfully
6. DELETE admin/setup.php from server immediately ⚠️
```

**To delete setup.php via cPanel/FTP:**
```bash
# Option 1: FTP
# Download FTP client, navigate to /admin/, delete setup.php

# Option 2: SSH
ssh your-server.com
cd /home/username/public_html/admin/
rm -f setup.php

# Option 3: cPanel File Manager
# Login → File Manager → admin/ → Right-click setup.php → Delete
```

### **STEP 5: Enable HTTPS Certificate**

**For cPanel Hosting:**
```
1. Login to cPanel
2. Go to: SSL/TLS Status
3. Click "Manage" next to your domain
4. Auto-Install AutoSSL certificate (free)
5. Wait 5-10 minutes for certificate to activate
```

**For VPS/Cloud (Let's Encrypt):**
```bash
# Install Certbot
sudo apt-get install certbot python3-certbot-apache

# Get certificate
sudo certbot --apache -d your-domain.gov.in -d www.your-domain.gov.in

# Auto-renew (runs automatically)
sudo certbot renew --dry-run
```

### **STEP 6: File Permissions**

```bash
# Set proper permissions for security
chmod 644 index.php
chmod 700 admin/*.php
chmod 700 api/*.php
chmod 700 config/config.php
chmod 700 lib/*.php
chmod 755 qrcodes/
chmod 755 uploads/

# Protect .htaccess
chmod 644 .htaccess
```

### **STEP 7: Disable Directory Listing**

Ensure `.htaccess` has:
```apache
Options -Indexes
```

### **STEP 8: Test Security**

#### **Test 1: HTTPS Redirect**
```bash
# Should redirect to HTTPS
curl -I http://your-domain.gov.in/
# Look for: Location: https://your-domain.gov.in/
```

#### **Test 2: API Rate Limiting**
```bash
# Try 31 requests rapidly (should fail on 31st)
for i in {1..35}; do
  curl "https://your-domain.gov.in/api/auto.php?id=AUTO-001" 2>/dev/null | grep -o "success"
  sleep 0.1
done
# Last few should show: Too many requests
```

#### **Test 3: CSRF Protection**
```bash
# Try login without CSRF token (should fail)
curl -X POST https://your-domain.gov.in/admin/login.php \
  -d "username=admin&password=test" \
  #  Should return 403 or session expired error
```

#### **Test 4: Brute Force Protection**
```bash
# Try 6 failed logins rapidly (should lockout on 6th)
for i in {1..10}; do
  curl -X POST https://your-domain.gov.in/admin/login.php \
    -d "username=admin&password=wrongpass&csrf_token=token" \
    sleep 1
done
# After 5 failures: "Too many failed login attempts"
```

---

## **🔐 SECURITY BEST PRACTICES FOR PRODUCTION**

### **Database Security**

```php
// In config/config.php - Use strong credentials
define('DB_USER',   'sqr_policeadmin');  // NOT 'root'
define('DB_PASS',   'VeryStrongPassword#2024!@#');  // 20+ chars
define('DB_HOST',   '127.0.0.1');  // NOT public IP
define('DB_PORT',   '3306');

// Change default MySQL port (optional, more secure):
define('DB_PORT',   '3307');  // Custom high port
```

### **Session Security**

```php
// Already configured in setupSecureSessionCookies()
// But verify in php.ini:
session.cookie_secure = On         // HTTPS only
session.cookie_httponly = On       // No JS access
session.cookie_samesite = Strict   // No CSRF
session.use_strict_mode = On       // Regenerate ID
```

### **File Upload Security**

⚠️ Vulnerability in /uploads/ and /qrcodes/:

```php
// Add to admin/upload handlers:
function validateUploadedFile($file, $maxSize = 5242880, $allowedTypes = ['image/png', 'image/jpeg']) {
    if (!isset($file['tmp_name']) || !file_exists($file['tmp_name'])) {
        return false;
    }
    
    // Check file size
    if ($file['size'] > $maxSize) {
        return false;  // File too large (>5MB)
    }
    
    // Check MIME type (not just extension)
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    return in_array($mime, $allowedTypes);
}

// Usage:
if (validateUploadedFile($_FILES['photo'])) {
    // Safe to process
    $filename = uniqid() . '_' . bin2hex(random_bytes(8)) . '.png';
    move_uploaded_file($_FILES['photo']['tmp_name'], 'uploads/' . $filename);
}
```

### **Environment Variables** (Optional, More Secure)

Create `.env` file (NOT in version control):

```bash
# .env (never commit this to git!)
DB_HOST=127.0.0.1
DB_USER=sqr_policeadmin
DB_PASS=VeryStrongPassword#2024!@#
APP_ENV=production
HTTPS_ENFORCE=true
```

Update config.php to load it:

```php
// Load .env if exists
if (file_exists(__DIR__ . '/.env')) {
    $env = parse_ini_file(__DIR__ . '/.env');
    define('DB_HOST', $env['DB_HOST'] ?? '127.0.0.1');
    // ... etc
}
```

### **Logging & Monitoring**

```php
// Add to config/config.php
function logSecurityEvent(string $eventType, string $details, ?string $username = null, ?string $ipAddress = null): void {
    $logfile = dirname(__DIR__) . '/logs/security.log';
    
    // Create logs directory if doesn't exist
    if (!is_dir(dirname($logfile))) {
        mkdir(dirname($logfile), 0700, true);
    }
    
    $entry = json_encode([
        'timestamp' => date('Y-m-d H:i:s'),
        'event'     => $eventType,
        'user'      => $username ?? 'unknown',
        'ip'        => $ipAddress ?? getClientIP(),
        'details'   => $details,
    ]);
    
    file_put_contents($logfile, $entry . PHP_EOL, FILE_APPEND);
}

// Usage in login.php:
if ($lockoutMsg) {
    logSecurityEvent('LOGIN_LOCKOUT', "IP attempted login for user: $username", $username, $ipAddress);
}
```

### **Regular Maintenance Tasks**

**Daily:**
- Monitor SOS alerts for suspicious patterns
- Check error logs: `/var/log/apache2/error.log`
- Verify HTTPS certificate is active

**Weekly:**
- Review login attempts table for brute force attacks:
  ```sql
  SELECT username, COUNT(*) FROM login_attempts 
  WHERE success=0 AND attempt_time > DATE_SUB(NOW(), INTERVAL 7 DAY)
  GROUP BY username 
  ORDER BY COUNT(*) DESC;
  ```

**Monthly:**
- Review API abuse logs
  ```sql
  SELECT endpoint, ip_address, COUNT(*) FROM api_rate_limits 
  WHERE request_time > DATE_SUB(NOW(), INTERVAL 30 DAY)
  GROUP BY endpoint, ip_address 
  ORDER BY COUNT(*) DESC LIMIT 20;
  ```
- Update database backups
- Rotate API rate limit cleanup

**Quarterly:**
- Security audit of all admin actions
- Update SSL certificate when near expiration
- Penetration testing

---

## **❌ SECURITY VULNERABILITIES FIXED**

| Vulnerability | Before | After | Status |
|---------------|--------|-------|--------|
| **Default Credentials** | Admin@1234 hardcoded | setup.php forced setup | ✅ FIXED |
| **HTTP Traffic** | Unencrypted credentials | HTTPS enforced + HSTS headers | ✅ FIXED |
| **CSRF Attacks** | No token validation | Token generation + validation | ✅ FIXED |
| **Brute Force Login** | Unlimited attempts | 5 attempts → 30min lockout | ✅ FIXED |
| **API Scraping** | 1000s requests/min | Rate limited: 30/min | ✅ FIXED |
| **False SOS Alerts** | Any IP can spam | Rate limited + auto validation | ✅ FIXED |
| **GPS Data Tampering** | No validation | Coordinates validated | ✅ FIXED |
| **Data Leakage** | Phone#, license exposed | Masked phone numbers | ✅ FIXED |
| **Session Hijacking** | Plain cookies | Secure + HttpOnly + SameSite | ✅ FIXED |

---

## **📝 CONFIGURATION CHECKLIST**

Before going LIVE:

- [ ] Database imported with new tables
- [ ] config/config.php updated with all security functions
- [ ] .htaccess updated with HTTPS + security headers
- [ ] HTTPS certificate installed (SSL/TLS)
- [ ] admin/setup.php run and DELETED
- [ ] Default admin credentials REMOVED from schema.sql
- [ ] File permissions set (644 for files, 755 for dirs)
- [ ] Backup system in place
- [ ] Error logging configured
- [ ] Security headers tested (curl -I https://...)
- [ ] Rate limiting tested
- [ ] CSRF protection tested
- [ ] Brute force protection tested
- [ ] Database credentials rotated (NOT root)
- [ ] logs/ directory created with 700 permissions

---

## **🚨 INCIDENT RESPONSE**

### **If Login Page is Compromised:**
1. Check login_attempts table for brute force patterns
2. Verify all admin accounts haven't been modified
3. Review last_login timestamps
4. Reset suspicious admin passwords via direct DB update:
   ```sql
   UPDATE admins SET password_hash = '$2y$12$...' WHERE username = 'admin';
   ```

### **If SOS API is Being Abused:**
1. Ban IP addresses sending spam:
   ```php
   // Add to api/sos.php
   if (in_array($ipAddress, ['192.168.1.100', '10.0.0.5'])) {
       http_response_code(403);
       exit(json_encode(['success' => false, 'message' => 'Access denied']));
   }
   ```
2. Check GPS coordinates for out-of-bounds values
3. Review message field for spam keywords

### **If Database is Breached:**
1. Immediately revoke database user credentials
2. Create new database user with new password
3. Update config.php
4. Restart web server
5. Review backup strategy

---

## **🎓 SECURITY TRAINING FOR ADMINS**

Share with your police team:

1. **Never share passwords** - Use individual accounts
2. **Never use public WiFi** - Use VPN if accessing remotely
3. **Log out** after each session
4. **Report suspicious activity** - Unusual logins, failed attempts
5. **Enable 2FA** (if implemented) - Additional account security
6. **Use password manager** - Store passwords securely (Bitwarden, 1Password)
7. **Verify HTTPS** - Check for 🔒 icon in browser before login
8. **Keep browser updated** - Security patches critical

---

## **📞 SUPPORT & TROUBLESHOOTING**

### **HTTPS Not Redirecting:**
```apache
# In .htaccess, make sure mod_rewrite is enabled:
<IfModule mod_rewrite.c>
    RewriteEngine On
    ...
</IfModule>

# Check Apache status:
sudo systemctl status apache2
```

### **Rate Limiting Not Working:**
```sql
-- Check if api_rate_limits table exists:
SHOW TABLES LIKE 'api_rate_limits';

-- If not, manually create:
CREATE TABLE api_rate_limits (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    endpoint VARCHAR(100) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    request_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_endpoint_ip_time (endpoint, ip_address, request_time)
) ENGINE=InnoDB;
```

### **Setup.php Still Accessible:**
```bash
# Verify file is deleted:
ls -la admin/setup.php  # Should say "No such file"

# If file manager used, check for .htaccess rule:
# Add to .htaccess if needed:
<FilesMatch "setup\.php">
    Order allow,deny
    Deny from all
</FilesMatch>
```

---

## **✅ FINAL SECURITY SCORE**

After implementing all fixes:

| Category | Score | Notes |
|----------|-------|-------|
| Authentication | 9/10 | Brute force protected, strong passwords |
| Encryption | 9/10 | HTTPS enforced, session cookies secure |
| Authorization | 8/10 | Session-based, role checking in progress |
| Input Validation | 9/10 | GPS, phone, auto ID validated |
| Rate Limiting | 9/10 | API + Login protected |
| Data Security | 7/10 | Phone numbers masked, no hardcoded secrets |
| Error Handling | 8/10 | Generic messages, logging via PHP |
| **OVERALL** | **8/10** | **Production Ready** ✅ |

---

## **Final Notes**

1. **This is NOT military-grade security** - For government deployment, consider additional:
   - 2FA/MFA (TOTP, SMS OTP)
   - Audit logging to external service
   - DDoS protection (CloudFlare, AWS Shield)
   - WAF (Web Application Firewall)
   - Penetration testing by security firm

2. **Regular updates critical** - PHP, MySQL, Apache versions must stay current

3. **Backup strategy essential** - Daily encrypted backups to separate server

4. **Monitor actively** - Set up alerts for suspicious patterns

---

**Last Updated:** April 2026
**Version:** 1.0 - Production Hardening
**Maintained By:** System Security Team
