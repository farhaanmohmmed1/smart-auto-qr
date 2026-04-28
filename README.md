# 🚔 Smart Auto QR Safety System
### A Government-Grade Auto-Rickshaw Safety & Monitoring Platform

---

## 📌 Overview

The **Smart Auto QR Safety System** is a full-stack PHP/MySQL web application designed for **police departments** to register and monitor auto-rickshaws. Each auto gets a unique QR code sticker. When scanned by a passenger, it shows the verified driver details and offers a one-tap **SOS WhatsApp emergency** button.

---

## 🛠 Tech Stack

| Layer     | Technology                      |
|-----------|---------------------------------|
| Frontend  | HTML5, CSS3, Vanilla JavaScript (mobile-first) |
| Backend   | PHP 7.4+ / 8.x                  |
| Database  | MySQL 5.7+ / MariaDB 10.3+      |
| QR Codes  | qrserver.com API + GD fallback  |
| PDF       | Browser Print-to-PDF (no deps)  |
| SOS       | WhatsApp deep link integration  |
| Server    | Apache (XAMPP / LAMP / cPanel)  |

---

## 📁 Folder Structure

```
smart_auto_qr/
├── index.php                  ← Root redirect to admin
├── .htaccess                  ← Apache security config
│
├── config/
│   └── config.php             ← DB credentials, app constants
│
├── database/
│   └── schema.sql             ← Full DB schema + sample data
│
├── lib/
│   ├── QRGenerator.php        ← QR code generation + caching
│   └── PDFGenerator.php       ← Print sticker HTML generator
│
├── admin/                     ← 🔐 Protected admin panel
│   ├── login.php
│   ├── logout.php
│   ├── dashboard.php          ← Stats, charts, recent SOS/scans
│   ├── register.php           ← Register new auto + generate QR
│   ├── manage.php             ← List, search, filter all autos
│   ├── edit.php               ← Edit auto/driver details
│   ├── delete.php             ← Delete handler
│   ├── view_auto.php          ← Detailed single auto view
│   ├── sos_logs.php           ← SOS alert management
│   ├── scan_logs.php          ← QR scan tracking
│   ├── bulk_upload.php        ← CSV bulk import
│   ├── download_qr.php        ← Download QR image
│   ├── download_pdf.php       ← Printable PDF sticker
│   ├── download_template.php  ← CSV template download
│   ├── partials/
│   │   ├── sidebar.php
│   │   └── topbar.php
│   └── assets/
│       ├── css/admin.css
│       └── js/admin.js
│
├── public/                    ← 🌐 No login required
│   ├── index.php
│   ├── auto.php               ← Main QR scan landing page
│   └── assets/
│       └── css/style.css
│
├── api/                       ← REST API endpoints
│   ├── sos.php                ← POST: Log SOS alert
│   └── auto.php               ← GET:  Fetch auto details (JSON)
│
├── qrcodes/                   ← Auto-generated QR PNG cache
└── uploads/                   ← CSV upload temp storage
```

---

## 🚀 Installation — XAMPP (Windows/Mac)

### Step 1: Setup XAMPP
1. Download and install [XAMPP](https://www.apachefriends.org/)
2. Start **Apache** and **MySQL** from the XAMPP Control Panel

### Step 2: Copy Project Files
```
Copy the `smart_auto_qr/` folder to:
C:\xampp\htdocs\smart_auto_qr\         (Windows)
/Applications/XAMPP/htdocs/smart_auto_qr/  (Mac)
```

### Step 3: Create Database
1. Open `http://localhost/phpmyadmin`
2. Click **New** → Create database: `smart_auto_db`
3. Click **Import** → Choose `database/schema.sql` → Click **Go**

### Step 4: Configure `config/config.php`
```php
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'smart_auto_db');
define('DB_USER', 'root');
define('DB_PASS', '');  // Your MySQL password

// IMPORTANT: Set your server's base URL
define('BASE_URL', 'http://localhost/smart_auto_qr');

// Police helpline number
define('HELPLINE', '100');

// WhatsApp number for SOS (country code + number, no +)
define('SOS_WHATSAPP', '919100000000');  // Change to actual number
```

### Step 5: Set Directory Permissions
```bash
chmod 755 smart_auto_qr/qrcodes/
chmod 755 smart_auto_qr/uploads/
```

### Step 6: Access the Application
| URL | Purpose |
|-----|---------|
| `http://localhost/smart_auto_qr/admin/login.php` | Admin login |
| `http://localhost/smart_auto_qr/public/auto.php?id=AUTO-001` | Test scan page |

---

## 🐧 Installation — Linux (Ubuntu/Debian)

```bash
# Install LAMP stack
sudo apt update
sudo apt install apache2 php php-mysql mysql-server

# Clone/copy project
sudo cp -r smart_auto_qr/ /var/www/html/

# Set permissions
sudo chown -R www-data:www-data /var/www/html/smart_auto_qr/
sudo chmod 755 /var/www/html/smart_auto_qr/qrcodes/
sudo chmod 755 /var/www/html/smart_auto_qr/uploads/

# Enable mod_rewrite
sudo a2enmod rewrite
sudo systemctl restart apache2

# Create DB
mysql -u root -p
> CREATE DATABASE smart_auto_db CHARACTER SET utf8mb4;
> EXIT;

mysql -u root -p smart_auto_db < database/schema.sql
```

---

## 🔐 Default Login

| Field    | Value        |
|----------|--------------|
| Username | `admin`      |
| Password | `Admin@1234` |

> ⚠️ **Change the password immediately after first login!**

---

## 📲 QR Code Flow

```
1. Police admin registers auto → 
2. System generates URL: /public/auto.php?id=AUTO-001 → 
3. QR code generated from URL → 
4. PDF sticker printed and placed in auto → 
5. Passenger scans QR → 
6. Auto details shown instantly (no login needed) → 
7. Passenger can call driver or trigger SOS
```

---

## 🚨 SOS WhatsApp Message Format

When SOS is triggered, the following pre-filled WhatsApp message is sent:

```
🚨 SOS EMERGENCY ALERT 🚨

I need urgent help!

🚖 Auto Details:
• Auto No: AUTO-001
• Driver: Rajesh Kumar
• Driver Phone: 9876543210

📍 My Location:
https://maps.google.com/?q=17.3850,78.4867

⚠️ Please help immediately!
🆘 Via Smart Auto QR Safety System
```

---

## 📤 Bulk CSV Import Format

```csv
auto_number,reg_number,driver_name,phone,license_number,permit_number,area,stand
AUTO-101,TS09EA9999,Vijay Kumar,9876500001,TS14DL2021099,HYD/2024/101,Ameerpet,Bus Stand
AUTO-102,TS09EB8888,Ravi Sharma,9876500002,TS14DL2021100,HYD/2024/102,Kukatpally,KPHB Stand
```

Download the template from: **Admin → Bulk Upload → Download CSV Template**

---

## 🗄 Database Tables

| Table      | Purpose |
|------------|---------|
| `autos`    | Vehicle and driver records |
| `sos_logs` | SOS emergency alerts from passengers |
| `scan_logs`| QR scan tracking (time, IP, device) |
| `admins`   | Admin panel user accounts |

---

## 📊 Admin Panel Features

- **Dashboard** — Real-time stats: total autos, scans today, pending SOS alerts, chart of last 7 days
- **Register Auto** — Form with validation, instant QR generation
- **All Autos** — Search, filter by status/area, paginated table with QR preview
- **Edit Auto** — Modify details, QR auto-regenerated
- **Bulk Upload** — CSV drag-and-drop import with row-by-row results
- **SOS Alerts** — View all emergency alerts, update dispatch status, map links
- **Scan Logs** — Full history of QR scans with IP tracking
- **Print PDF** — Printable A5 sticker with QR code, auto number, driver details

---

## 🔒 Security Features

- PDO prepared statements (SQL injection prevention)
- `htmlspecialchars()` on all output (XSS prevention)
- Session timeout (1 hour)
- Password hashing with `password_hash()` (bcrypt)
- SOS rate limiting (3 per IP per 10 minutes)
- `.htaccess` blocks access to config/, lib/, uploads/
- Directory listing disabled

---

## 🌐 Production Deployment (cPanel)

1. Upload files via File Manager or FTP to `public_html/smart_auto_qr/`
2. Create MySQL database via cPanel → Databases
3. Import `schema.sql` via phpMyAdmin
4. Update `config/config.php` with production credentials
5. Set `BASE_URL` to your domain: `https://yourdomain.com/smart_auto_qr`
6. Set file permissions: `qrcodes/` and `uploads/` → 755

---

## ⚡ Performance Notes

- System is designed for **10,000+ autos** with database indexing on `auto_number`, `status`, `created_at`
- QR codes are **cached as PNG files** — only regenerated when auto details change
- Scan logs use **indexed queries** for fast dashboard stats
- Public scan page is **minimal HTML** — loads in < 1 second on mobile

---

## 📞 Support

This system is designed for real-world police deployment. For customization:
- Change `HELPLINE` in `config.php` for your city's police number
- Change `SOS_WHATSAPP` to the designated emergency WhatsApp number
- Customize the sticker design in `lib/PDFGenerator.php`

---

*Built with ❤️ for public safety. Smart Auto QR Safety System v1.0*
