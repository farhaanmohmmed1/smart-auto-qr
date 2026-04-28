# Local QR Code Generation - Setup Guide

## Recommended Library: **endroid/qr-code**

### Why This Library?

| Criteria | endroid/qr-code | Alternative | Why Choose |
|----------|-----------------|-------------|-----------|
| **Pure PHP** | ✅ Yes | ✅ phpqrcode | endroid is more modern |
| **Error Correction** | ✅ L/M/H/Q | ✅ All levels | Same capability |
| **PNG Output** | ✅ Yes | ✅ Yes | Same |
| **SVG Output** | ✅ Yes | ❌ No | Better for print scaling |
| **Performance** | ⚡ ~15ms | ~20ms | Faster generation |
| **Maintenance** | ✅ Active | ⚠️ Legacy | Well-maintained |
| **Composer** | ✅ Yes | ⚠️ Manual | Easy to install |
| **Error Messages** | ✅ Clear | ⚠️ Minimal | Better debugging |
| **Memory Footprint** | ~2MB | ~1.5MB | Minimal difference |

---

## Installation

### Option A: Via Composer (Recommended)
```bash
cd /path/to/smart_auto_qr
composer require endroid/qr-code
```

### Option B: Manual Installation (Shared Hosting)

If Composer isn't available, download directly:

```bash
# Download and extract the library
wget https://github.com/endroid/qr-code/releases/download/2.10.2/endroid-qr-code-2.10.2.zip
unzip endroid-qr-code-2.10.2.zip -d lib/

# Or via curl if wget unavailable
curl -L -o lib/qr-code.zip https://github.com/endroid/qr-code/archive/refs/tags/2.10.2.zip
unzip lib/qr-code.zip -d lib/
```

Then manually require:
```php
require_once 'lib/qr-code/src/Encoder/Encoder.php';
// (See manual_include.php for full list)
```

---

## Performance Targets

### Generation Time
- **First Generation**: ~15-30ms (includes encoding + PNG generation)
- **Cached Load**: <1ms (disk read)
- **Bulk Generation**: 50,000 QRs in ~10 seconds

### File Sizes
- **PNG (300x300px)**: ~2-3KB per QR
- **SVG (300x300px)**: ~1-2KB per QR

### Disk Space for 1M Autos
- **PNG**: ~2.5-3GB (acceptable)
- **SVG**: ~1.5-2GB (preferred for scaling)

---

## Directory Structure

```
smart_auto_qr/
├── qrcodes/
│   ├── qr_AUTO-001.png      ← Generated QR codes
│   ├── qr_AUTO-002.png
│   └── ...
├── lib/
│   ├── QRGenerator.php      ← Your class (wrapper)
│   └── vendor/endroid/qr-code/  ← Installed library (if Composer)
└── config/
    └── config.php           ← QR_DIR, QR_URL constants
```

---

## Verify Installation

After Composer install, check:
```bash
ls -la vendor/endroid/qr-code/
# Should show: LICENSE, README.md, composer.json, src/

php -r "require 'vendor/autoload.php'; echo 'Composer OK';"
```

---

## Next Steps

1. ✅ Install library (Composer or manual)
2. ✅ Replace QRGenerator.php with new local version
3. ✅ Test: `php bin/test_qr.php`
4. ✅ Update bulk import to use local generation
5. ✅ Clear old cached QRs (optional)

---

## Troubleshooting

| Issue | Solution |
|-------|----------|
| `Class not found` | Run `composer dump-autoload` |
| `Permission denied` | Ensure `qrcodes/` is writable: `chmod 755 qrcodes/` |
| `OutOfMemory` | Increase PHP `memory_limit` in php.ini |
| `Take >100ms` | Check CPU usage; may be I/O bound |

---

## Rollback (If Needed)

If you need to revert to API-based generation:
```php
// Keep old file as backup
cp lib/QRGenerator.php lib/QRGenerator.php.local
cp lib/QRGenerator.php.api lib/QRGenerator.php  # Restore API version
```

---

## Support

For issues with endroid library:
- https://github.com/endroid/qr-code
- Documentation: https://qr-code.readthedocs.io/
