# Vercel Repository Structure Guide

Complete folder and file organization for Vercel deployment.

---

## Full Repository Structure

```
smart-auto-qr/
│
├── 📦 ROOT LEVEL FILES
│
├── vercel.json                          ✨ Vercel config (build, routes, runtime)
├── composer.json                        ✅ PHP dependencies (endroid/qr-code)
├── composer.lock                        (Managed by Composer)
├── .gitignore                           ✨ Exclude secrets, logs, vendor
├── .env.example                         ✨ Template for environment variables
├── .env                                 🔒 (LOCAL ONLY - never commit!)
│
├── 📚 DOCUMENTATION (Core Migration)
├── VERCEL_MIGRATION_GUIDE.md            📖 Complete 100-section migration guide
├── VERCEL_DEPLOYMENT_CHECKLIST.md       ✅ Step-by-step deployment checklist
├── API_MIGRATION_GUIDE.md               🔄 Convert each endpoint to serverless
├── VERCEL_STRUCTURE.md                  (This file - file organization)
│
├── 📚 DOCUMENTATION (Existing)
├── README.md                            (Keep and update)
├── ARCHITECTURE_QR.md
├── SECURITY_ARCHITECTURE.md
├── OPTIMIZATION_CHECKLIST.md
├── [other previous audit docs]
│
├── 🔌 API ENDPOINTS (Serverless Functions)
├── api/
│   ├── index.php                        (Optional: API documentation/health check)
│   │
│   ├── 🔓 PUBLIC ENDPOINTS (No Auth Required)
│   ├── auto.php                         GET /api/auto?id=AUTO-001
│   ├── sos.php                          POST /api/sos (emergency report)
│   ├── qr.php                           GET /api/qr?id=AUTO-001 (QR image)
│   ├── scan.php                         POST /api/scan (log QR scan)
│   │
│   ├── 🔒 PROTECTED ENDPOINTS (JWT Required)
│   └── admin/
│       ├── login.php                    POST /api/admin/login (returns JWT token)
│       ├── register.php                 POST /api/admin/register (create new admin)
│       ├── logout.php                   POST /api/admin/logout (optional)
│       ├── dashboard.php                GET /api/admin/dashboard
│       ├── profile.php                  GET /api/admin/profile
│       │
│       ├── 📋 AUTO MANAGEMENT
│       ├── auto-list.php                GET /api/admin/auto/list
│       ├── auto-create.php              POST /api/admin/auto/create
│       ├── auto-update.php              PUT /api/admin/auto/update
│       ├── auto-delete.php              DELETE /api/admin/auto/delete
│       ├── auto-download-qr.php         GET /api/admin/auto/{id}/qr
│       ├── auto-bulk-upload.php         POST /api/admin/auto/bulk-upload
│       │
│       ├── 📊 REPORTS & LOGS
│       ├── scan-logs.php                GET /api/admin/scan-logs
│       ├── sos-logs.php                 GET /api/admin/sos-logs
│       ├── download-pdf.php             GET /api/admin/download-pdf
│       │
│       └── 👥 ADMIN MANAGEMENT
│           ├── admin-list.php           GET /api/admin/admins
│           ├── admin-create.php         POST /api/admin/admins
│           └── admin-delete.php         DELETE /api/admin/admins/{id}
│
├── 📦 SHARED LIBRARIES
├── lib/
│   ├── QRGenerator.php                  ✅ Local QR code generation (endroid/qr-code)
│   ├── PDFGenerator.php                 ✅ PDF export generation
│   ├── ImportHandler.php                ✅ Bulk upload CSV/Excel processing
│   ├── JWTAuth.php                      ✨ JWT token creation/verification
│   └── (other helper classes)
│
├── ⚙️ CONFIGURATION
├── config/
│   ├── config.php                       ✨ Main config (environment-based)
│   ├── database.php                     (Optional: DB connection pool)
│   └── constants.php                    (Optional: app constants)
│
├── 🗄️ DATABASE
├── database/
│   ├── schema.sql                       ✅ Database schema (import to PlanetScale)
│   ├── seeds.sql                        (Optional: demo data)
│   └── migrations/                      (Optional: future schema versions)
│
├── 🎨 FRONTEND (Single Page App)
├── public/
│   ├── index.html                       ✨ Login page / SPA entry point
│   │
│   ├── 📄 PAGE TEMPLATES (SPA routes)
│   ├── dashboard.html                   ✨ Admin dashboard
│   ├── manage.html                      ✨ Auto management interface
│   ├── auto.html                        ✨ Public auto details view
│   ├── sos.html                         ✨ SOS emergency form
│   ├── reports.html                     ✨ Scan/SOS logs view
│   ├── profile.html                     ✨ Admin profile/settings
│   ├── 404.html                         ✨ 404 page
│   │
│   ├── 📂 ASSETS
│   └── assets/
│       ├── css/
│       │   ├── main.css                 ✨ Global styles
│       │   ├── dashboard.css
│       │   ├── responsive.css           (Mobile-friendly)
│       │   └── theme.css                (Dark/light theme)
│       │
│       ├── js/
│       │   ├── api.js                   ✨ API client (fetch wrapper)
│       │   ├── auth.js                  ✨ JWT token management
│       │   ├── router.js                ✨ SPA routing (page switching)
│       │   ├── dashboard.js             Dashboard-specific logic
│       │   ├── manage.js                Auto management logic
│       │   ├── utils.js                 Common utilities
│       │   └── vendor/                  Third-party libraries
│       │       ├── chart.js             (For charts, optional)
│       │       └── qrcode.min.js        (Client-side QR, optional)
│       │
│       ├── img/
│       │   ├── logo.svg
│       │   ├── icon-*.png
│       │   └── emoji/
│       │
│       └── fonts/
│           └── (custom fonts if needed)
│
├── 🧪 TESTING (Optional)
├── tests/
│   ├── bootstrap.php
│   ├── unit/
│   │   ├── JWTAuthTest.php
│   │   └── QRGeneratorTest.php
│   └── integration/
│       ├── LoginAPITest.php
│       └── DashboardAPITest.php
│
├── 📁 DEVELOPER FILES (Exclude from Git)
├── .vscode/
│   ├── settings.json                    (IDE settings, ignored by .gitignore)
│   ├── extensions.json
│   └── launch.json                      (Debugging config)
│
└── 🚫 REMOVE FROM GIT (Use .gitignore)
    ├── vendor/                          (Generated by Composer)
    ├── node_modules/                    (If using JS build tools)
    ├── .env                             (Secrets!)
    ├── .env.local
    ├── qrcodes/                         (Generated QR codes)
    ├── uploads/                         (Generated temp files)
    ├── logs/
    ├── /tmp/
    ├── *.log
    ├── *.bak
    ├── .DS_Store
    └── Thumbs.db
```

---

## Environment Details by Folder

### `api/` Folder Structure Explained

**Purpose:** All PHP serverless functions live here  
**Deployment:** Each file becomes a separate Vercel function  
**Accessibility:** `https://yourdomain.vercel.app/api/<filename>.php`

```
api/
├── auto.php                    # GET /api/auto (public)
├── sos.php                     # POST /api/sos (public)
├── qr.php                      # GET /api/qr (public, generates QR)
├── scan.php                    # POST /api/scan (public, log scans)
│
└── admin/                      # Protected endpoints (JWT required)
    ├── login.php               # POST /api/admin/login (returns token)
    ├── register.php            # POST /api/admin/register
    ├── dashboard.php           # GET /api/admin/dashboard
    ├── auto-xxx.php            # CRUD operations for autos
    └── [other endpoints]
```

**Each file:**
- ✅ Must start with `require_once '../config/config.php';`
- ✅ Should call `setCORSHeaders();` (for Vercel)
- ✅ Must return JSON via `sendJSON()` or `error()`
- ✅ Protected endpoints call `JWTAuth::requireAuth()`

---

### `public/` Folder Structure Explained

**Purpose:** Frontend single-page application (SPA)  
**Deployment:** Static files served by Vercel  
**Accessibility:** `https://yourdomain.vercel.app/*`

```
public/
├── index.html                  # Entry point (login page)
├── dashboard.html              # Admin dashboard (if using multiple files)
├── manage.html                 # Auto management
├── auto.html                   # Public auto details view
├── 404.html                    # 404 error page
│
└── assets/
    ├── css/                    # Stylesheets
    │   ├── main.css            # Global styles & variables
    │   └── responsive.css      # Mobile/tablet responsive
    │
    ├── js/                     # JavaScript modules
    │   ├── api.js              # API client (wrapper around fetch)
    │   ├── auth.js             # JWT/localStorage management
    │   ├── router.js           # SPA routing (without page reload)
    │   └── utils.js            # Common functions
    │
    ├── img/                    # Images & icons
    │   └── logo.svg
    │
    └── fonts/                  # Custom fonts (if needed)
```

**Architecture Options:**

**Option A: Single-Page App (SPA)**
```
public/
├── index.html              # Single HTML file
└── assets/js/router.js     # Shows/hides content based on URL
```
All pages in one HTML, JavaScript handles routing.

**Option B: Multi-Page Progressive**
```
public/
├── index.html              # Login page
├── dashboard.html          # Admin dashboard
├── manage.html             # Auto management
└── auto.html               # Public view
```
Traditional multi-page (each page is separate HTML).

**Recommendation:** Use Option A (SPA) for modern architecture.

---

### `lib/` Folder Structure

**Purpose:** Reusable PHP classes used by API endpoints

```
lib/
├── QRGenerator.php         # Generates QR codes locally
├── PDFGenerator.php        # Creates PDF exports
├── ImportHandler.php       # Processes bulk CSV/Excel uploads
└── JWTAuth.php             # JWT token operations (NEW)
```

Each class is stateless and reusable:
```php
// Usage in api/admin/auto-create.php
require_once '../lib/QRGenerator.php';
$qrImage = QRGenerator::generate($autoNumber);

// Usage in api/admin/login.php
require_once '../lib/JWTAuth.php';
$token = JWTAuth::createToken($adminId, $username, $role);
```

---

### `config/` Folder Structure

**Purpose:** Configuration and database setup

```
config/
├── config.php              # Main config (environment variables, DB connection)
└── database.php            # (Optional) DB connection pooling
```

**Never commit:** Actual credentials (they're loaded from env variables)

```php
// ✅ This is OK to commit (uses env variables)
define('DB_HOST', getenv('DB_HOST'));

// ❌ Never commit this!
define('DB_HOST', 'mysql.example.com');
define('DB_PASS', 'actual_password_123');
```

---

### `database/` Folder Structure

**Purpose:** Database schema and initial data

```
database/
├── schema.sql              # Tables, indexes, constraints
├── seeds.sql               # Demo data (admins, test autos)
└── migrations/             # (Optional) versioned schema changes
    ├── 001_initial.sql
    └── 002_add_fields.sql
```

**How to use:**
```bash
# Import schema to PlanetScale/Railway
mysql -h host -u user -p dbname < database/schema.sql

# Load demo data (optional)
mysql -h host -u user -p dbname < database/seeds.sql
```

---

## Critical Files for Vercel

**MUST Exist:**
- [ ] `vercel.json` - Tells Vercel how to build & route
- [ ] `config/config.php` - Uses environment variables
- [ ] `lib/JWTAuth.php` - JWT authentication
- [ ] `api/admin/login.php` - JWT endpoint
- [ ] `public/index.html` - Frontend entry point

**IMPORTANT to Exclude:**
- [ ] `.env` (use `.env.example` only)
- [ ] `vendor/` (Composer installs automatically)
- [ ] `qrcodes/` (Generated on-demand)
- [ ] `uploads/` (Use external storage)

---

## Migration Steps

### 1. Create Vercel Structure
```bash
# Copy existing admin files to api/admin/
mkdir -p api/admin

# Create public folder for frontend
mkdir -p public/assets/{css,js,img}

# Create lib folder (already exists)
# Create config folder (already exists)
```

### 2. Migrate Each API Endpoint
```bash
# Example: Convert admin/login.php → api/admin/login.php
# Follow API_MIGRATION_GUIDE.md for each endpoint
```

### 3. Create Frontend
```bash
# Create public/index.html (login page)
# Create public/assets/js/api.js (API client)
# Create public/assets/js/auth.js (JWT management)
# Create public/assets/css/main.css (styles)
```

### 4. Update Configuration
```bash
# Update config/config.php to use environment variables
# Create vercel.json with build/route config
# Create .env.example with template variables
```

### 5. Add to Git
```bash
git add .
git commit -m "Vercel migration: Serverless functions, JWT auth, SPA frontend"
git push origin main
```

### 6. Deploy to Vercel
```bash
# Vercel auto-detects GitHub and starts building
# Set environment variables in Vercel dashboard
# Import database schema to remote DB
```

---

## File Size Limits on Vercel

| Item | Limit | Impact |
|------|-------|--------|
| Max function size | 50 MB | Large libraries OK |
| Max deployment size | 100 MB | Exclude vendor/ |
| Max execution time | 10s free, 60s pro | Optimize queries |
| Max response size | varies | Keep responses <100KB |
| Max concurrent | limited free | Acceptable for small team |

**Vercel Free Tier is sufficient for:**
- Team of 5-20 users
- < 1,000 QR scans/day
- < 100 SOS reports/day

For larger scale, upgrade to Hobby ($20/month).

---

## Summary: What Goes Where

| Type | Location | Deployed as | Commits to Git |
|------|----------|------------|---|
| Database | database/schema.sql | Imported to PlanetScale | YES |
| API handlers | api/**/*.php | Serverless functions | YES |
| Frontend | public/**/*.html | Static files | YES |
| Assets | public/assets/ | Static assets | YES |
| Libraries | lib/*.php | Bundled with functions | YES |
| Config | config/*.php | With deployment | YES (env vars only) |
| Secrets | .env | Environment variables (Vercel) | NO ❌ |
| Dependencies | vendor/ | Installed by Composer | NO ❌ |
| Generated | qrcodes/, uploads/ | Created at runtime | NO ❌ |

---

This structure is optimized for **Vercel Free Tier** with:
- ✅ Serverless PHP (no long-running processes)
- ✅ Stateless architecture (JWT tokens)
- ✅ Separated frontend/backend
- ✅ No file persistence needed
- ✅ Environment-based configuration
- ✅ 100% deployable with `git push`
