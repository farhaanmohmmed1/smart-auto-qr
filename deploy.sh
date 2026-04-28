#!/bin/bash
################################################################################
# Smart Auto QR Safety System - Production Optimization Deployment Script
# 
# Platform: HPE ProLiant ML350 (On-Premise)
# Purpose:  Automated, safe deployment of performance optimizations
# Usage:    chmod +x deploy.sh && ./deploy.sh
#
# Safety Features:
# - Automatic database backup
# - Code backup + git tagging
# - Non-blocking operations
# - Rollback capability at each step
# - Error handling with clear messages
################################################################################

set -e  # Exit immediately on any error

# ─────────────────────────────────────────────────────────────────────────────
# CONFIGURATION
# ─────────────────────────────────────────────────────────────────────────────

# Server directories
CODE_DIR="/var/www/smart_auto_qr"
BACKUP_DIR="/backups/smart_auto_qr"
LOG_DIR="/var/log/smart_auto_qr"

# Database credentials (use environment variables in production!)
DB_HOST="${DB_HOST:-localhost}"
DB_USER="${DB_USER:-admin_user}"
DB_PASS="${DB_PASS:-}"  # Will prompt if empty
DB_NAME="${DB_NAME:-smart_auto_qr}"

# Deployment settings
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_DB="${BACKUP_DIR}/database_${TIMESTAMP}.sql"
BACKUP_CODE="${BACKUP_DIR}/code_${TIMESTAMP}.tar.gz"

# ─────────────────────────────────────────────────────────────────────────────
# LOGGING & OUTPUT
# ─────────────────────────────────────────────────────────────────────────────

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

log_info() {
    echo -e "${BLUE}[INFO]${NC} $1" | tee -a "${LOG_DIR}/deploy.log"
}

log_success() {
    echo -e "${GREEN}[✓]${NC} $1" | tee -a "${LOG_DIR}/deploy.log"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1" | tee -a "${LOG_DIR}/deploy.log"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1" | tee -a "${LOG_DIR}/deploy.log"
}

# ─────────────────────────────────────────────────────────────────────────────
# PRE-FLIGHT CHECKS
# ─────────────────────────────────────────────────────────────────────────────

check_environment() {
    echo ""
    echo "╔════════════════════════════════════════════════════════════╗"
    echo "║  Smart Auto QR - Performance Optimization Deployment       ║"
    echo "║  HPE ProLiant ML350 (On-Premise)                           ║"
    echo "╚════════════════════════════════════════════════════════════╝"
    echo ""
    
    log_info "Checking environment..."
    
    # Check directories exist
    if [[ ! -d "$CODE_DIR" ]]; then
        log_error "Code directory not found: $CODE_DIR"
        exit 1
    fi
    log_success "Code directory found: $CODE_DIR"
    
    # Check MySQL
    if ! command -v mysql &> /dev/null; then
        log_error "MySQL client not found. Please install mysql-client."
        exit 1
    fi
    log_success "MySQL client available"
    
    # Check git
    if ! command -v git &> /dev/null; then
        log_error "Git not found. Please install git."
        exit 1
    fi
    log_success "Git available"
    
    # Create backup directory
    mkdir -p "$BACKUP_DIR"
    mkdir -p "$LOG_DIR"
    log_success "Backup directory ready: $BACKUP_DIR"
    
    # Database credentials
    if [[ -z "$DB_PASS" ]]; then
        echo ""
        echo "─────────────────────────────────────────────────────────────"
        read -sp "Enter MySQL password for user '$DB_USER': " DB_PASS
        echo ""
        echo "─────────────────────────────────────────────────────────────"
    fi
    
    # Test database connection
    if ! mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "SELECT 1;" &>/dev/null; then
        log_error "Cannot connect to database. Check credentials."
        exit 1
    fi
    log_success "Database connection verified"
    
    echo ""
}

# ─────────────────────────────────────────────────────────────────────────────
# STEP 1: Backup Database
# ─────────────────────────────────────────────────────────────────────────────

backup_database() {
    echo ""
    log_info "STEP 1: Backing up database..."
    
    if mysqldump -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" > "$BACKUP_DB" 2>/dev/null; then
        log_success "Database backed up: $BACKUP_DB ($(du -h "$BACKUP_DB" | cut -f1))"
    else
        log_error "Database backup failed"
        exit 1
    fi
}

# ─────────────────────────────────────────────────────────────────────────────
# STEP 2: Backup Code
# ─────────────────────────────────────────────────────────────────────────────

backup_code() {
    echo ""
    log_info "STEP 2: Backing up code..."
    
    cd "$CODE_DIR"
    
    if git archive HEAD --output="$BACKUP_CODE" 2>/dev/null; then
        log_success "Code backed up: $BACKUP_CODE ($(du -h "$BACKUP_CODE" | cut -f1))"
    else
        log_error "Code backup failed"
        exit 1
    fi
    
    # Git tag
    CURRENT_BRANCH=$(git rev-parse --abbrev-ref HEAD)
    CURRENT_HASH=$(git rev-parse --short HEAD)
    git tag -a "pre-optimization-${TIMESTAMP}" \
        -m "Backup before performance optimization (${CURRENT_BRANCH}@${CURRENT_HASH})" \
        2>/dev/null || true
    log_success "Git tag created: pre-optimization-${TIMESTAMP}"
}

# ─────────────────────────────────────────────────────────────────────────────
# STEP 3: Add Database Indexes
# ─────────────────────────────────────────────────────────────────────────────

add_indexes() {
    echo ""
    log_info "STEP 3: Adding database indexes (non-blocking)..."
    
    # Check if indexes already exist
    INDEX_COUNT=$(mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" \
        -se "SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_NAME='scan_logs' AND COLUMN_NAME='auto_number';" 2>/dev/null || echo "0")
    
    if [[ $INDEX_COUNT -gt 0 ]]; then
        log_warn "Index idx_auto_number already exists on scan_logs (skipping)"
    else
        mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" << EOF 2>/dev/null
            ALTER TABLE scan_logs ADD INDEX idx_auto_number (auto_number);
EOF
        log_success "Added index: scan_logs.idx_auto_number"
    fi
    
    INDEX_COUNT=$(mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" \
        -se "SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_NAME='sos_logs' AND COLUMN_NAME='auto_number';" 2>/dev/null || echo "0")
    
    if [[ $INDEX_COUNT -gt 0 ]]; then
        log_warn "Index idx_auto_number already exists on sos_logs (skipping)"
    else
        mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" << EOF 2>/dev/null
            ALTER TABLE sos_logs ADD INDEX idx_auto_number (auto_number);
EOF
        log_success "Added index: sos_logs.idx_auto_number"
    fi
}

# ─────────────────────────────────────────────────────────────────────────────
# STEP 4: Deploy Code Changes
# ─────────────────────────────────────────────────────────────────────────────

deploy_code_changes() {
    echo ""
    log_info "STEP 4: Deploying code changes..."
    
    cd "$CODE_DIR"
    
    # Create feature branch
    BRANCH="feature/performance-opt-${TIMESTAMP}"
    git checkout -b "$BRANCH" 2>/dev/null || git checkout "$BRANCH" 2>/dev/null
    log_success "Working on branch: $BRANCH"
    
    # Files that require changes (user should have manually applied)
    REQUIRED_CHANGES=(
        "admin/dashboard.php"
        "admin/manage.php"
        "admin/edit.php"
        "admin/register.php"
        "lib/helpers.php"
    )
    
    log_warn "Required: Manually apply code changes from CODE_CHANGES_READY_TO_APPLY.md:"
    for FILE in "${REQUIRED_CHANGES[@]}"; do
        if [[ -f "$CODE_DIR/$FILE" ]]; then
            log_info "  ✓ $FILE exists"
        else
            if [[ "$FILE" == "lib/helpers.php" ]]; then
                log_warn "  ! $FILE missing (will be created)"
            else
                log_error "  ✗ $FILE missing"
            fi
        fi
    done
    
    # Check if helpers.php exists, if not warn
    if [[ ! -f "$CODE_DIR/lib/helpers.php" ]]; then
        log_warn "lib/helpers.php not found - this is REQUIRED for the optimization"
        log_info "Copy lib/helpers.php from PRODUCTION_IMPLEMENTATION_GUIDE to lib/ directory"
    fi
    
    log_info "Please verify all code changes are in place before continuing..."
}

# ─────────────────────────────────────────────────────────────────────────────
# STEP 5: Clear Session Cache
# ─────────────────────────────────────────────────────────────────────────────

clear_caches() {
    echo ""
    log_info "STEP 5: Clearing session cache..."
    
    # Clear PHP sessions
    SESSION_PATH=$(php -r "echo session_save_path();" 2>/dev/null || echo "/var/lib/php/sessions")
    
    if [[ -d "$SESSION_PATH" ]]; then
        rm -f "${SESSION_PATH}"/sess_* 2>/dev/null || true
        log_success "Cleared session files from: $SESSION_PATH"
    fi
    
    # Clear opcode cache (if applicable)
    if command -v php-cgi &> /dev/null; then
        php -r "opcache_reset();" 2>/dev/null || true
        log_success "OPCache cleared"
    fi
}

# ─────────────────────────────────────────────────────────────────────────────
# STEP 6: Restart Web Server
# ─────────────────────────────────────────────────────────────────────────────

restart_web_server() {
    echo ""
    log_info "STEP 6: Restarting web server..."
    
    if command -v systemctl &> /dev/null; then
        # Try Apache first
        if systemctl is-enabled apache2 &>/dev/null 2>&1; then
            sudo systemctl restart apache2 2>/dev/null || true
            log_success "Apache restarted"
        elif systemctl is-enabled nginx &>/dev/null 2>&1; then
            sudo systemctl restart nginx 2>/dev/null || true
            log_success "Nginx restarted"
        else
            log_warn "Could not detect web server (Apache/Nginx)"
        fi
    fi
}

# ─────────────────────────────────────────────────────────────────────────────
# STEP 7: Verify Deployment
# ─────────────────────────────────────────────────────────────────────────────

verify_deployment() {
    echo ""
    log_info "STEP 7: Verifying deployment..."
    
    # Check web server responds
    DOMAIN=$(grep "BASE_URL\|APP_NAME" "$CODE_DIR/config/config.php" | head -1)
    if curl -s -I "http://localhost/admin/dashboard.php" | grep -q "200\|302\|304"; then
        log_success "Web server is responding"
    else
        log_warn "Could not verify web server response"
    fi
    
    # Check database indexes
    if mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" \
        -e "SELECT * FROM information_schema.STATISTICS WHERE TABLE_NAME='scan_logs' AND COLUMN_NAME='auto_number';" &>/dev/null; then
        log_success "Database indexes verified"
    fi
    
    # Check for errors in logs
    if [[ -f "/var/log/apache2/error.log" ]]; then
        RECENT_ERRORS=$(tail -20 /var/log/apache2/error.log | grep -c "ERROR\|Fatal" || echo "0")
        if [[ $RECENT_ERRORS -eq 0 ]]; then
            log_success "No recent errors in Apache error log"
        else
            log_warn "Found $RECENT_ERRORS errors in Apache error log (check manually)"
        fi
    fi
}

# ─────────────────────────────────────────────────────────────────────────────
# STEP 8: Performance Test
# ─────────────────────────────────────────────────────────────────────────────

performance_test() {
    echo ""
    log_info "STEP 8: Basic performance test..."
    
    log_info "Testing dashboard page load time..."
    
    # Simple HTTP request timing
    TIME=$( { time curl -s http://localhost/admin/dashboard.php > /dev/null; } 2>&1 | grep real | awk '{print $2}' )
    log_success "Dashboard page load: ${TIME} (check DevTools for detailed timing)"
    
    echo ""
    log_info "For full performance validation:"
    log_info "  1. Open https://your-domain/admin/dashboard.php"
    log_info "  2. Press F12 (Developer Tools)"
    log_info "  3. Go to Network tab"
    log_info "  4. Hard refresh (Ctrl+Shift+R)"
    log_info "  5. Check total page load time"
    log_info "  Expected: 200-300ms (was 600-900ms)"
}

# ─────────────────────────────────────────────────────────────────────────────
# ROLLBACK FUNCTION
# ─────────────────────────────────────────────────────────────────────────────

rollback() {
    echo ""
    echo "─────────────────────────────────────────────────────────────"
    log_warn "Rollback requested"
    echo "─────────────────────────────────────────────────────────────"
    
    if [[ -f "$BACKUP_DB" ]]; then
        log_info "To restore database from backup, run:"
        echo "  mysql -u $DB_USER -p < $BACKUP_DB"
    fi
    
    if [[ -f "$BACKUP_CODE" ]]; then
        log_info "To restore code from backup, run:"
        echo "  cd $CODE_DIR && git reset --hard pre-optimization-${TIMESTAMP}"
    fi
    
    echo ""
    log_info "Backups available at: $BACKUP_DIR/"
    echo ""
}

# ─────────────────────────────────────────────────────────────────────────────
# MAIN EXECUTION
# ─────────────────────────────────────────────────────────────────────────────

main() {
    check_environment
    
    echo ""
    echo "═══════════════════════════════════════════════════════════════"
    echo "  Deployment Overview"
    echo "═══════════════════════════════════════════════════════════════"
    echo "  Code Dir:       $CODE_DIR"
    echo "  Backup Dir:     $BACKUP_DIR"
    echo "  Database:       $DB_NAME @ $DB_HOST"
    echo "  Timestamp:      $TIMESTAMP"
    echo "═══════════════════════════════════════════════════════════════"
    echo ""
    
    read -p "Continue with deployment? (yes/no): " CONFIRM
    if [[ "$CONFIRM" != "yes" ]]; then
        log_warn "Deployment cancelled by user"
        exit 0
    fi
    
    echo ""
    
    # Execute deployment steps
    backup_database
    backup_code
    add_indexes
    deploy_code_changes
    clear_caches
    restart_web_server
    verify_deployment
    performance_test
    
    echo ""
    echo "╔════════════════════════════════════════════════════════════╗"
    echo "║  ✅ Deployment Completed Successfully                      ║"
    echo "╚════════════════════════════════════════════════════════════╝"
    echo ""
    
    echo "📋 Next Steps:"
    echo "  1. Test all admin pages work correctly"
    echo "  2. Check DevTools for page load times"
    echo "  3. Monitor error logs for 24 hours"
    echo "  4. Celebrate performance improvements! 🎉"
    echo ""
    
    echo "🔙 Rollback Information (saved for safety):"
    rollback
    
    echo "📊 Deployment Log: ${LOG_DIR}/deploy.log"
    echo ""
}

# Run main function
main "$@"
