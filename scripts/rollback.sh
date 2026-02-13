#!/bin/bash

################################################################################
# Unfurl Rollback Script
#
# Emergency rollback script to restore from backup.
# Restores both application files and database.
#
# Usage: ./scripts/rollback.sh [backup_timestamp]
# Example: ./scripts/rollback.sh 20260207_143022
#
# If no timestamp is provided, will list available backups.
################################################################################

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
BACKUP_DIR="${PROJECT_ROOT}/backups"
BACKUP_TIMESTAMP="${1:-}"

################################################################################
# Functions
################################################################################

log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

list_backups() {
    echo ""
    echo -e "${BLUE}Available Backups:${NC}"
    echo -e "${BLUE}================================${NC}"

    if [ ! -d "$BACKUP_DIR" ]; then
        echo "No backup directory found"
        exit 1
    fi

    # List file backups
    echo ""
    echo "Application Backups:"
    if ls "$BACKUP_DIR"/unfurl_backup_*.tar.gz 1> /dev/null 2>&1; then
        ls -lh "$BACKUP_DIR"/unfurl_backup_*.tar.gz | awk '{print $9, "(" $5 ")"}'
    else
        echo "  No application backups found"
    fi

    # List database backups
    echo ""
    echo "Database Backups:"
    if ls "$BACKUP_DIR"/unfurl_db_*.sql.gz 1> /dev/null 2>&1; then
        ls -lh "$BACKUP_DIR"/unfurl_db_*.sql.gz | awk '{print $9, "(" $5 ")"}'
    else
        echo "  No database backups found"
    fi

    echo ""
    echo "Usage: $0 <timestamp>"
    echo "Example: $0 20260207_143022"
    echo ""
}

confirm_rollback() {
    echo ""
    log_warning "This will rollback to backup from: $BACKUP_TIMESTAMP"
    log_warning "Current data will be backed up before rollback"
    echo ""
    read -p "Are you sure you want to continue? (yes/no): " CONFIRM

    if [ "$CONFIRM" != "yes" ]; then
        log_info "Rollback cancelled"
        exit 0
    fi
}

backup_current_state() {
    log_info "Backing up current state before rollback..."

    CURRENT_TIMESTAMP=$(date +%Y%m%d_%H%M%S)

    # Backup current files
    CURRENT_BACKUP="${BACKUP_DIR}/unfurl_pre_rollback_${CURRENT_TIMESTAMP}.tar.gz"
    tar -czf "$CURRENT_BACKUP" \
        --exclude='vendor' \
        --exclude='storage/temp/*' \
        --exclude='.git' \
        -C "$PROJECT_ROOT" .

    log_success "Current state backed up: $CURRENT_BACKUP"

    # Backup current database
    if [ -f "$PROJECT_ROOT/.env" ]; then
        DB_HOST=$(grep "^DB_HOST=" "$PROJECT_ROOT/.env" | cut -d '=' -f2)
        DB_NAME=$(grep "^DB_NAME=" "$PROJECT_ROOT/.env" | cut -d '=' -f2)
        DB_USER=$(grep "^DB_USER=" "$PROJECT_ROOT/.env" | cut -d '=' -f2)
        DB_PASS=$(grep "^DB_PASS=" "$PROJECT_ROOT/.env" | cut -d '=' -f2)

        if [ -n "$DB_NAME" ]; then
            DB_BACKUP="${BACKUP_DIR}/unfurl_db_pre_rollback_${CURRENT_TIMESTAMP}.sql.gz"
            mysqldump -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" | gzip > "$DB_BACKUP"
            log_success "Current database backed up: $DB_BACKUP"
        fi
    fi
}

restore_files() {
    log_info "Restoring application files..."

    FILE_BACKUP="${BACKUP_DIR}/unfurl_backup_${BACKUP_TIMESTAMP}.tar.gz"

    if [ ! -f "$FILE_BACKUP" ]; then
        log_error "File backup not found: $FILE_BACKUP"
        exit 1
    fi

    # Create temporary directory
    TEMP_DIR="${PROJECT_ROOT}/temp_restore_$$"
    mkdir -p "$TEMP_DIR"

    # Extract backup to temp directory
    tar -xzf "$FILE_BACKUP" -C "$TEMP_DIR"

    # Preserve current .env if it exists
    if [ -f "$PROJECT_ROOT/.env" ]; then
        cp "$PROJECT_ROOT/.env" "$TEMP_DIR/.env.current"
    fi

    # Remove current files (except .env and backups)
    log_info "Removing current files..."
    find "$PROJECT_ROOT" -mindepth 1 -maxdepth 1 \
        ! -name 'backups' \
        ! -name '.env' \
        ! -name "temp_restore_$$" \
        -exec rm -rf {} +

    # Move restored files to project root
    log_info "Moving restored files..."
    mv "$TEMP_DIR"/* "$PROJECT_ROOT/" 2>/dev/null || true
    mv "$TEMP_DIR"/.* "$PROJECT_ROOT/" 2>/dev/null || true

    # Restore current .env if it was preserved
    if [ -f "$TEMP_DIR/.env.current" ]; then
        cp "$TEMP_DIR/.env.current" "$PROJECT_ROOT/.env"
        log_info "Preserved current .env configuration"
    fi

    # Clean up temp directory
    rm -rf "$TEMP_DIR"

    log_success "Application files restored"
}

restore_database() {
    log_info "Restoring database..."

    DB_BACKUP="${BACKUP_DIR}/unfurl_db_${BACKUP_TIMESTAMP}.sql.gz"

    if [ ! -f "$DB_BACKUP" ]; then
        log_warning "Database backup not found: $DB_BACKUP"
        log_warning "Skipping database restore"
        return
    fi

    if [ ! -f "$PROJECT_ROOT/.env" ]; then
        log_error "Cannot restore database: .env file not found"
        exit 1
    fi

    # Read database credentials
    DB_HOST=$(grep "^DB_HOST=" "$PROJECT_ROOT/.env" | cut -d '=' -f2)
    DB_NAME=$(grep "^DB_NAME=" "$PROJECT_ROOT/.env" | cut -d '=' -f2)
    DB_USER=$(grep "^DB_USER=" "$PROJECT_ROOT/.env" | cut -d '=' -f2)
    DB_PASS=$(grep "^DB_PASS=" "$PROJECT_ROOT/.env" | cut -d '=' -f2)

    if [ -z "$DB_NAME" ]; then
        log_error "Database credentials not found in .env"
        exit 1
    fi

    # Confirm database restore
    echo ""
    log_warning "About to restore database: $DB_NAME"
    read -p "This will OVERWRITE all current data. Continue? (yes/no): " DB_CONFIRM

    if [ "$DB_CONFIRM" != "yes" ]; then
        log_info "Database restore skipped"
        return
    fi

    # Restore database
    gunzip < "$DB_BACKUP" | mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME"

    log_success "Database restored"
}

reinstall_dependencies() {
    log_info "Reinstalling dependencies..."

    cd "$PROJECT_ROOT"

    if command -v composer &> /dev/null; then
        composer install --no-dev --optimize-autoloader --no-interaction
        log_success "Dependencies installed"
    else
        log_warning "Composer not found, skipping dependency installation"
    fi
}

set_permissions() {
    log_info "Setting file permissions..."

    cd "$PROJECT_ROOT"

    # Set directory permissions
    find . -type d -exec chmod 755 {} \;
    find . -type f -exec chmod 644 {} \;

    # Make scripts executable
    chmod +x scripts/*.sh 2>/dev/null || true
    chmod +x scripts/*.php 2>/dev/null || true

    # Secure .env file
    if [ -f .env ]; then
        chmod 600 .env
    fi

    # Make storage writable
    chmod -R 755 storage
    chmod -R 755 storage/logs
    chmod -R 755 storage/temp

    log_success "Permissions set"
}

verify_rollback() {
    log_info "Verifying rollback..."

    # Check required files exist
    if [ ! -f "$PROJECT_ROOT/config.php" ]; then
        log_error "Verification failed: config.php not found"
        exit 1
    fi

    if [ ! -d "$PROJECT_ROOT/vendor" ]; then
        log_warning "Vendor directory not found (may need: composer install)"
    fi

    # Run health check if script exists
    if [ -f "$SCRIPT_DIR/health-check.sh" ]; then
        log_info "Running health check..."
        bash "$SCRIPT_DIR/health-check.sh" || true
    fi

    log_success "Rollback verification complete"
}

print_summary() {
    echo ""
    echo -e "${BLUE}================================${NC}"
    echo -e "${BLUE}Rollback Summary${NC}"
    echo -e "${BLUE}================================${NC}"
    echo -e "Restored from: ${GREEN}${BACKUP_TIMESTAMP}${NC}"
    echo -e "File backup: ${GREEN}${BACKUP_DIR}/unfurl_backup_${BACKUP_TIMESTAMP}.tar.gz${NC}"
    echo -e "DB backup: ${GREEN}${BACKUP_DIR}/unfurl_db_${BACKUP_TIMESTAMP}.sql.gz${NC}"
    echo -e "${BLUE}================================${NC}"
    echo ""
}

################################################################################
# Main Rollback Process
################################################################################

log_info "Unfurl Rollback Script"
echo ""

# Check if backup directory exists
if [ ! -d "$BACKUP_DIR" ]; then
    log_error "Backup directory not found: $BACKUP_DIR"
    exit 1
fi

# If no timestamp provided, list backups
if [ -z "$BACKUP_TIMESTAMP" ]; then
    list_backups
    exit 0
fi

# Check if backups exist for this timestamp
FILE_BACKUP="${BACKUP_DIR}/unfurl_backup_${BACKUP_TIMESTAMP}.tar.gz"
if [ ! -f "$FILE_BACKUP" ]; then
    log_error "File backup not found for timestamp: $BACKUP_TIMESTAMP"
    echo ""
    list_backups
    exit 1
fi

# Confirm rollback
confirm_rollback

# Execute rollback
backup_current_state
restore_files
restore_database
reinstall_dependencies
set_permissions
verify_rollback

# Print summary
print_summary
log_success "Rollback complete!"

echo ""
log_info "Next steps:"
echo "  1. Test the application functionality"
echo "  2. Review logs: storage/logs/"
echo "  3. Check health: [YOUR_URL]/health.php"
echo "  4. Monitor for issues"
echo ""
log_info "If issues persist, you can rollback again to:"
echo "  $(ls -t "$BACKUP_DIR"/unfurl_pre_rollback_*.tar.gz | head -1)"
echo ""

exit 0
