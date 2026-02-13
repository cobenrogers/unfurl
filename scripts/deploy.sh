#!/bin/bash

################################################################################
# Unfurl Deployment Script
#
# Automated deployment script for Unfurl application.
# Handles code deployment, dependency installation, and post-deployment checks.
#
# Usage: ./scripts/deploy.sh [environment]
# Example: ./scripts/deploy.sh production
################################################################################

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
ENVIRONMENT="${1:-production}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
BACKUP_DIR="${PROJECT_ROOT}/backups"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)

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

check_requirements() {
    log_info "Checking requirements..."

    # Check PHP version
    if ! command -v php &> /dev/null; then
        log_error "PHP is not installed"
        exit 1
    fi

    PHP_VERSION=$(php -r "echo PHP_VERSION;")
    log_info "PHP version: $PHP_VERSION"

    # Check required PHP extensions
    REQUIRED_EXTENSIONS=("pdo" "pdo_mysql" "curl" "mbstring" "json")
    for ext in "${REQUIRED_EXTENSIONS[@]}"; do
        if ! php -m | grep -q "^$ext$"; then
            log_error "Required PHP extension not found: $ext"
            exit 1
        fi
    done

    log_success "All requirements met"
}

backup_current() {
    log_info "Creating backup of current deployment..."

    # Create backup directory if it doesn't exist
    mkdir -p "$BACKUP_DIR"

    # Backup files (exclude vendor and temp files)
    BACKUP_FILE="${BACKUP_DIR}/unfurl_backup_${TIMESTAMP}.tar.gz"
    tar -czf "$BACKUP_FILE" \
        --exclude='vendor' \
        --exclude='storage/temp/*' \
        --exclude='.git' \
        --exclude='node_modules' \
        -C "$PROJECT_ROOT" .

    log_success "Backup created: $BACKUP_FILE"

    # Keep only last 5 backups
    cd "$BACKUP_DIR"
    ls -t unfurl_backup_*.tar.gz | tail -n +6 | xargs -r rm
    log_info "Old backups cleaned up (keeping last 5)"
}

backup_database() {
    log_info "Backing up database..."

    if [ ! -f "$PROJECT_ROOT/.env" ]; then
        log_warning "No .env file found, skipping database backup"
        return
    fi

    # Read database credentials from .env
    DB_HOST=$(grep "^DB_HOST=" "$PROJECT_ROOT/.env" | cut -d '=' -f2)
    DB_NAME=$(grep "^DB_NAME=" "$PROJECT_ROOT/.env" | cut -d '=' -f2)
    DB_USER=$(grep "^DB_USER=" "$PROJECT_ROOT/.env" | cut -d '=' -f2)
    DB_PASS=$(grep "^DB_PASS=" "$PROJECT_ROOT/.env" | cut -d '=' -f2)

    if [ -z "$DB_NAME" ]; then
        log_warning "Database credentials not found in .env"
        return
    fi

    # Create database backup
    DB_BACKUP_FILE="${BACKUP_DIR}/unfurl_db_${TIMESTAMP}.sql.gz"
    mysqldump -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" | gzip > "$DB_BACKUP_FILE"

    if [ $? -eq 0 ]; then
        log_success "Database backup created: $DB_BACKUP_FILE"
    else
        log_error "Database backup failed"
        exit 1
    fi

    # Keep only last 10 database backups
    cd "$BACKUP_DIR"
    ls -t unfurl_db_*.sql.gz | tail -n +11 | xargs -r rm
}

install_dependencies() {
    log_info "Installing dependencies..."

    cd "$PROJECT_ROOT"

    # Check if composer is available
    if ! command -v composer &> /dev/null; then
        log_error "Composer is not installed"
        exit 1
    fi

    # Install production dependencies only
    if [ "$ENVIRONMENT" == "production" ]; then
        composer install --no-dev --optimize-autoloader --no-interaction
    else
        composer install --optimize-autoloader --no-interaction
    fi

    log_success "Dependencies installed"
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

clear_caches() {
    log_info "Clearing caches..."

    cd "$PROJECT_ROOT"

    # Clear temporary files
    rm -rf storage/temp/*

    # Clear OPcache (if available)
    if command -v php &> /dev/null; then
        php -r "if (function_exists('opcache_reset')) { opcache_reset(); echo 'OPcache cleared'; }"
    fi

    log_success "Caches cleared"
}

run_migrations() {
    log_info "Checking for database migrations..."

    # Check if migrations directory exists
    if [ -d "$PROJECT_ROOT/sql/migrations" ]; then
        MIGRATION_COUNT=$(find "$PROJECT_ROOT/sql/migrations" -name "*.sql" | wc -l)
        if [ "$MIGRATION_COUNT" -gt 0 ]; then
            log_warning "Found $MIGRATION_COUNT migration file(s)"
            log_warning "Migrations must be run manually via phpMyAdmin or mysql CLI"
            log_warning "Location: sql/migrations/"
        else
            log_info "No migration files found"
        fi
    fi
}

verify_deployment() {
    log_info "Verifying deployment..."

    cd "$PROJECT_ROOT"

    # Check .env exists
    if [ ! -f .env ]; then
        log_error ".env file not found. Copy .env.example and configure."
        exit 1
    fi

    # Check required directories
    REQUIRED_DIRS=("storage" "storage/logs" "storage/temp" "public")
    for dir in "${REQUIRED_DIRS[@]}"; do
        if [ ! -d "$dir" ]; then
            log_error "Required directory not found: $dir"
            exit 1
        fi
    done

    # Check vendor directory
    if [ ! -d "vendor" ]; then
        log_error "Vendor directory not found. Run: composer install"
        exit 1
    fi

    # Verify database indexes
    log_info "Verifying database indexes..."
    php scripts/verify-indexes.php

    log_success "Deployment verification complete"
}

run_health_check() {
    log_info "Running health check..."

    # Get base URL from .env
    BASE_URL=$(grep "^APP_BASE_URL=" "$PROJECT_ROOT/.env" | cut -d '=' -f2)

    if [ -z "$BASE_URL" ]; then
        log_warning "APP_BASE_URL not set in .env, skipping health check"
        return
    fi

    # Remove trailing slash
    BASE_URL="${BASE_URL%/}"

    # Run health check
    HEALTH_RESPONSE=$(curl -s -o /dev/null -w "%{http_code}" "${BASE_URL}/health.php" 2>/dev/null || echo "000")

    if [ "$HEALTH_RESPONSE" == "200" ]; then
        log_success "Health check passed (HTTP $HEALTH_RESPONSE)"
    else
        log_error "Health check failed (HTTP $HEALTH_RESPONSE)"
        log_warning "Check ${BASE_URL}/health.php manually"
        exit 1
    fi
}

print_summary() {
    echo ""
    echo -e "${BLUE}================================${NC}"
    echo -e "${BLUE}Deployment Summary${NC}"
    echo -e "${BLUE}================================${NC}"
    echo -e "Environment: ${GREEN}${ENVIRONMENT}${NC}"
    echo -e "Timestamp: ${GREEN}${TIMESTAMP}${NC}"
    echo -e "Backup: ${GREEN}${BACKUP_DIR}/unfurl_backup_${TIMESTAMP}.tar.gz${NC}"
    echo -e "Database Backup: ${GREEN}${BACKUP_DIR}/unfurl_db_${TIMESTAMP}.sql.gz${NC}"
    echo -e "${BLUE}================================${NC}"
    echo ""
}

################################################################################
# Main Deployment Process
################################################################################

log_info "Starting Unfurl deployment..."
log_info "Environment: $ENVIRONMENT"
echo ""

# Pre-deployment checks
check_requirements

# Create backups
backup_current
backup_database

# Deploy
install_dependencies
set_permissions
clear_caches

# Post-deployment
run_migrations
verify_deployment
run_health_check

# Summary
print_summary
log_success "Deployment complete!"

echo ""
log_info "Next steps:"
echo "  1. Review any migration warnings above"
echo "  2. Test the application manually"
echo "  3. Monitor logs: storage/logs/"
echo "  4. Check health: [YOUR_URL]/health.php"
echo ""

exit 0
