# Vercel Deployment Checklist

Complete this checklist to ensure smooth deployment on Vercel Free Tier.

---

## ✅ PHASE 1: Local Preparation (Before Git)

### Repository Cleanup
- [ ] Remove `.env` file (use `.env.example` instead)
- [ ] Delete all `*.log` files in logs/
- [ ] Delete contents of `qrcodes/` directory
- [ ] Delete contents of `uploads/` directory  
- [ ] Remove `vendor/` folder if present (composer.json will regenerate)
- [ ] Delete temporary backup files (*.bak, *~)
- [ ] Verify `.gitignore` excludes all secrets

**Command to verify:**
```bash
git status --short | grep -E '\.(env|log|bak)$'  # Should show NOTHING
```

### Code Review
- [ ] All database credentials use `getenv()` in config/config.php
- [ ] No hardcoded hostnames or IPs in code
- [ ] `.htaccess` routes are documented in `vercel.json`
- [ ] All API files include `setCORSHeaders()`
- [ ] Protected endpoints use `JWTAuth::requireAuth()`
- [ ] No server-rendered HTML in API endpoints (use JSON instead)

### Configuration Files
- [ ] `vercel.json` created in project root
- [ ] `vercel.json` has correct PHP runtime version
- [ ] `.env.example` created with all required variables
- [ ] `config/config.php` refactored for Vercel
- [ ] All paths use constants (ROOT_PATH, QR_DIR, etc.)

**Verify config loading:**
```bash
php -r "require 'config/config.php'; echo 'Config loaded: ' . (defined('API_URL') ? 'YES' : 'NO');"
```

---

## ✅ PHASE 2: GitHub Repository

### Git Initialization
- [ ] Repository initialized: `git init`
- [ ] Remote configured: `git remote add origin https://github.com/YOU/smart-auto-qr`
- [ ] First commit created with all files

**Commands:**
```bash
git add .
git commit -m "feat: Vercel serverless migration"
git branch -M main
git push -u origin main
```

### Verify No Secrets in Git
- [ ] No `.env` in commit history: `git log --all -p -- .env | head -1`
- [ ] No database passwords visible: `git log -S 'password' --all`
- [ ] No JWT secrets: `git log -S JWT_SECRET --all`

**Fix if secrets committed:**
```bash
# Remove file from history (advanced)
git filter-branch --tree-filter 'rm -f .env' HEAD
git push origin --force-all
```

---

## ✅ PHASE 3: Vercel Setup

### Vercel Account
- [ ] Account created: https://vercel.com
- [ ] GitHub connected
- [ ] Private repository (recommended)

### Create Vercel Project
- [ ] Project created from GitHub repo
- [ ] Framework selected: "Other"
- [ ] Build command: auto-detected (should be PHP)
- [ ] Output directory: verified

**Steps:**
1. Vercel Dashboard → New Project
2. Select GitHub repository
3. Import project
4. Go to Project Settings

### Environment Variables
Set these in **Vercel Dashboard → Settings → Environment Variables**:

- [ ] `ENVIRONMENT` = `production`
- [ ] `DB_HOST` = MySQL host (PlanetScale/Railway/Aiven)
- [ ] `DB_PORT` = `3306`
- [ ] `DB_NAME` = database name
- [ ] `DB_USER` = database user
- [ ] `DB_PASS` = database password (will be masked)
- [ ] `JWT_SECRET` = 32-char random string
  - Generate: `openssl rand -base64 32`
- [ ] `API_URL` = `https://yourproject.vercel.app/api`
- [ ] `FRONTEND_URL` = `https://yourproject.vercel.app`
- [ ] `FILE_STORAGE` = `local` (or `cloudinary`)

**If using Cloudinary:**
- [ ] `CLOUDINARY_NAME` = your Cloudinary name
- [ ] `CLOUDINARY_KEY` = API key
- [ ] `CLOUDINARY_SECRET` = API secret

---

## ✅ PHASE 4: Database Setup

### Create Remote Database

**Option A: PlanetScale (Recommended - Free)**
- [ ] Account created: https://planetscale.com
- [ ] Database created
- [ ] Branch created (main)
- [ ] Connection string copied in "Passwords" section
- [ ] MySQL credentials extracted

**Connection string format:**
```
mysql://user:password@host/dbname
```

**Option B: Railway.app**
- [ ] Account created: https://railway.app
- [ ] New project created
- [ ] MySQL plugin added
- [ ] Variables extracted from environment

**Option C: Aiven**
- [ ] Account created: https://aiven.io
- [ ] MySQL service created
- [ ] Credentials obtained

### Import Database Schema
- [ ] Schema file ready: `database/schema.sql`
- [ ] Database connected via mysql-cli or web UI
- [ ] Schema imported successfully

**Command (if using mysql client):**
```bash
mysql -h <host> -u <user> -p<pass> <dbname> < database/schema.sql
```

**Verify tables created:**
```bash
mysql -h <host> -u <user> -p<pass> <dbname> -e "SHOW TABLES;"
```

### Database Verification
- [ ] `autos` table created
- [ ] `sos_logs` table created
- [ ] `scan_logs` table created
- [ ] `admins` table created
- [ ] `login_attempts` table created
- [ ] `api_rate_limits` table created
- [ ] All indexes created
- [ ] Connection tested from Vercel logs

---

## ✅ PHASE 5: First Deployment

### Manual Deployment
- [ ] All changes pushed to GitHub: `git push origin main`
- [ ] Vercel deployment started (should auto-trigger)
- [ ] Deployment watch-ed in Vercel Dashboard

### Monitor Build Process
- [ ] Check deployment logs in Vercel Dashboard
- [ ] Build succeeded (no PHP errors)
- [ ] Function creation successful
- [ ] Look for errors in:
  - Build logs
  - Function logs
  - Edge logs

**Common issues:**
| Error | Fix |
|-------|-----|
| "PHP version not found" | Update runtime in vercel.json |
| "Composer install failed" | Check composer.json, remove invalid packages |
| "Function timeout" | Optimize database queries, reduce operations |
| "Missing PDO MySQL" | Add to composer.json: `php: "^8.0"` |

---

## ✅ PHASE 6: Testing API Endpoints

### Test Connection
```bash
# Public API (no auth)
curl -i https://yourproject.vercel.app/api/auto?id=AUTO-001

# Expected: 200 + JSON response or 404 if auto not found
```

### Test Admin Login
```bash
curl -i -X POST https://yourproject.vercel.app/api/admin/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"password"}'

# Expected: 200 + JWT token in response
```

### Test Protected Endpoint
```bash
TOKEN="your_jwt_token_from_login"

curl -i https://yourproject.vercel.app/api/admin/dashboard \
  -H "Authorization: Bearer $TOKEN"

# Expected: 200 + dashboard data OR 401 if token invalid
```

### Test QR Generation API
```bash
curl -i https://yourproject.vercel.app/api/qr?id=AUTO-001

# Expected: 200 + PNG image data OR 404
```

---

## ✅ PHASE 7: Security Verification

### HTTPS & CORS
- [ ] All requests redirect to HTTPS
- [ ] CORS headers present in responses
- [ ] Credentials not exposed in logs

**Verify:**
```bash
curl -v https://yourproject.vercel.app/api/auto?id=test 2>&1 | grep -i "strict-transport-security"
```

### Secrets Not Exposed
- [ ] No database passwords in error messages
- [ ] No JWT secret in responses
- [ ] Debug mode disabled in production

**Check Vercel logs:**
- [ ] Look for any `DB_PASS` leaks (should be masked)
- [ ] Check for `JWT_SECRET` (should be masked or absent)

### Rate Limiting Active
- [ ] Login attempts limited
- [ ] API endpoints rate-limited
- [ ] Brute force protection working

---

## ✅ PHASE 8: Frontend Deployment

### Build Frontend Assets
- [ ] Frontend files in `public/` directory
- [ ] CSS/JS optimized and minified
- [ ] Images optimized
- [ ] Index.html created (entry point)

### Configure Routes
- [ ] Verify `vercel.json` routes match your frontend
- [ ] Static assets served from `public/static/`
- [ ] SPA routing configured (if using React/Vue)

### Test Frontend
- [ ] Login page loads: `https://yourproject.vercel.app`
- [ ] Login submits to `/api/admin/login`
- [ ] JWT token stored in localStorage
- [ ] Protected pages load with valid token

---

## ✅ PHASE 9: Performance & Monitoring

### Monitor Deployment
- [ ] Check Vercel Analytics dashboard
- [ ] Monitor function execution times
- [ ] Check for cold starts
- [ ] Review error rates

**Expected metrics:**
- Function execution time: <500ms
- Database query time: <100ms
- Cold start: <2s (acceptable for free tier)
- Error rate: <0.1%

### Set Up Alerts (Optional)
- [ ] Email alerts configured in Vercel
- [ ] Slack notifications (if desired)
- [ ] Error tracking (Sentry) optional but recommended

---

## ✅ PHASE 10: Cleanup & Documentation

### Clean Up
- [ ] Remove old admin/ folder (using API now)
- [ ] Remove public/index.php if migrated to single-page app
- [ ] Delete README.md old instructions
- [ ] Remove old shell scripts (deploy.sh, etc.)

### Documentation
- [ ] README.md updated with new deployment info
- [ ] Contributing guide created
- [ ] API documentation created
- [ ] Frontend setup guide written

---

## ✅ GO-LIVE CHECKLIST

Final verification before announcing to users:

### Functionality
- [ ] All QR codes generate correctly
- [ ] Admin dashboard displays data
- [ ] SOS reports submit successfully
- [ ] Scans logged correctly
- [ ] Reports downloadable (if applicable)

### Performance
- [ ] Dashboard loads in <1 second
- [ ] API responses <200ms
- [ ] QR codes generate <500ms
- [ ] No "Function timeout" errors

### Security
- [ ] HTTPS enforced
- [ ] Sessions are stateless (JWT)
- [ ] Database credentials not visible
- [ ] Rate limiting active
- [ ] CSRF protection (if forms used)

### Backup & Rollback
- [ ] Database backup created
- [ ] Previous deployment tagged in Git
- [ ] Rollback plan documented
- [ ] Emergency contact list updated

---

## 🚨 Troubleshooting During Deployment

### Build Fails
```bash
# Check logs in Vercel Dashboard → Deployments → Build Logs

# Common fixes:
1. Missing PHP extension → add to composer.json
2. Syntax error → run `php -l config/config.php`
3. Composer conflicts → update composer.lock
```

### Database Connection Fails
```bash
# Check Vercel Environment Variables
# Verify credentials match remote database

# Test locally with .env:
php -r "require 'config/config.php'; echo DB_CONNECTED ? 'OK' : 'FAIL';"
```

### Functions Return 502 Bad Gateway
```bash
# Check function logs in Vercel
# Common causes:
1. Uncaught PHP exception
2. Missing library/package
3. Infinite loop or timeout

# Add error logging:
error_log("Debug: Got here");  // Check in Vercel logs
```

### CORS Errors in Browser
```javascript
// Browser console shows:
// Access-Control-Allow-Origin missing

// Fix:
// 1. Ensure setCORSHeaders() called early in API files
// 2. Check FRONTEND_URL matches browser origin
// 3. Verify vercel.json headers section
```

---

## ✅ Success Criteria

Your Vercel deployment is **READY** when:

- ✅ All API endpoints respond (200, 400, 401, 404 as appropriate)
- ✅ Database queries execute in <100ms
- ✅ JWT authentication works end-to-end
- ✅ QR codes generate and display
- ✅ No database passwords in logs or errors
- ✅ HTTPS enforced
- ✅ Cold starts acceptable (<2s)
- ✅ No "502 Bad Gateway" errors
- ✅ Frontend loads and communicates with API
- ✅ Team ready for production use

---

## 📞 Support

**If deployment fails:**
1. Check Vercel logs (Dashboard → Deployments)
2. Review VERCEL_MIGRATION_GUIDE.md Troubleshooting section
3. Check GitHub Issues for similar problems
4. Contact Vercel support: https://vercel.com/support

**Useful links:**
- Vercel PHP docs: https://vercel.com/docs/concepts/functions/serverless-functions/runtimes/php
- PlanetScale docs: https://planetscale.com/docs
- JWT.io debugger: https://jwt.io

