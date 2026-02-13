#!/bin/bash

################################################################################
# Unfurl Health Check Script
#
# Performs comprehensive health checks on the Unfurl application.
# Can be run manually or via monitoring systems.
#
# Usage: ./scripts/health-check.sh [url]
# Example: ./scripts/health-check.sh https://example.com/unfurl
#
# Exit codes:
#   0 - All checks passed
#   1 - One or more checks failed
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
BASE_URL="${1:-}"

# If no URL provided, try to get from .env
if [ -z "$BASE_URL" ]; then
    if [ -f "$PROJECT_ROOT/.env" ]; then
        BASE_URL=$(grep "^APP_BASE_URL=" "$PROJECT_ROOT/.env" | cut -d '=' -f2)
        BASE_URL="${BASE_URL%/}"  # Remove trailing slash
    fi
fi

# Exit if still no URL
if [ -z "$BASE_URL" ]; then
    echo -e "${RED}[ERROR]${NC} No URL provided and APP_BASE_URL not found in .env"
    echo "Usage: $0 <base_url>"
    exit 1
fi

################################################################################
# Functions
################################################################################

log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[✓]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[!]${NC} $1"
}

log_error() {
    echo -e "${RED}[✗]${NC} $1"
}

# Health check counters
CHECKS_PASSED=0
CHECKS_FAILED=0
CHECKS_WARNING=0

check_health_endpoint() {
    log_info "Checking health endpoint..."

    RESPONSE=$(curl -s -w "\n%{http_code}" "${BASE_URL}/health.php" 2>/dev/null)
    HTTP_CODE=$(echo "$RESPONSE" | tail -n 1)
    BODY=$(echo "$RESPONSE" | head -n -1)

    if [ "$HTTP_CODE" == "200" ]; then
        # Parse JSON response
        STATUS=$(echo "$BODY" | grep -o '"status":"[^"]*"' | cut -d'"' -f4)

        if [ "$STATUS" == "ok" ]; then
            log_success "Health endpoint OK (HTTP $HTTP_CODE)"
            ((CHECKS_PASSED++))
        else
            log_error "Health endpoint returned error status: $STATUS"
            ((CHECKS_FAILED++))
        fi
    else
        log_error "Health endpoint check failed (HTTP $HTTP_CODE)"
        ((CHECKS_FAILED++))
    fi
}

check_database_connection() {
    log_info "Checking database connection..."

    if [ ! -f "$PROJECT_ROOT/.env" ]; then
        log_warning "Cannot check database: .env file not found"
        ((CHECKS_WARNING++))
        return
    fi

    # Read database credentials
    DB_HOST=$(grep "^DB_HOST=" "$PROJECT_ROOT/.env" | cut -d '=' -f2)
    DB_NAME=$(grep "^DB_NAME=" "$PROJECT_ROOT/.env" | cut -d '=' -f2)
    DB_USER=$(grep "^DB_USER=" "$PROJECT_ROOT/.env" | cut -d '=' -f2)
    DB_PASS=$(grep "^DB_PASS=" "$PROJECT_ROOT/.env" | cut -d '=' -f2)

    if [ -z "$DB_NAME" ]; then
        log_warning "Database credentials not found in .env"
        ((CHECKS_WARNING++))
        return
    fi

    # Try to connect
    if mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "SELECT 1" &>/dev/null; then
        log_success "Database connection OK"
        ((CHECKS_PASSED++))
    else
        log_error "Database connection failed"
        ((CHECKS_FAILED++))
    fi
}

check_file_permissions() {
    log_info "Checking file permissions..."

    PERMISSION_ISSUES=0

    # Check storage directories
    WRITABLE_DIRS=("storage" "storage/logs" "storage/temp")
    for dir in "${WRITABLE_DIRS[@]}"; do
        if [ ! -d "$PROJECT_ROOT/$dir" ]; then
            log_error "Directory not found: $dir"
            ((PERMISSION_ISSUES++))
        elif [ ! -w "$PROJECT_ROOT/$dir" ]; then
            log_error "Directory not writable: $dir"
            ((PERMISSION_ISSUES++))
        fi
    done

    # Check .env is readable
    if [ ! -f "$PROJECT_ROOT/.env" ]; then
        log_warning ".env file not found"
        ((CHECKS_WARNING++))
    elif [ ! -r "$PROJECT_ROOT/.env" ]; then
        log_error ".env file not readable"
        ((PERMISSION_ISSUES++))
    fi

    if [ $PERMISSION_ISSUES -eq 0 ]; then
        log_success "File permissions OK"
        ((CHECKS_PASSED++))
    else
        log_error "Found $PERMISSION_ISSUES permission issue(s)"
        ((CHECKS_FAILED++))
    fi
}

check_disk_space() {
    log_info "Checking disk space..."

    # Get disk usage for project directory
    DISK_USAGE=$(df "$PROJECT_ROOT" | tail -1 | awk '{print $5}' | sed 's/%//')

    if [ "$DISK_USAGE" -lt 80 ]; then
        log_success "Disk space OK (${DISK_USAGE}% used)"
        ((CHECKS_PASSED++))
    elif [ "$DISK_USAGE" -lt 90 ]; then
        log_warning "Disk space getting low (${DISK_USAGE}% used)"
        ((CHECKS_WARNING++))
    else
        log_error "Disk space critical (${DISK_USAGE}% used)"
        ((CHECKS_FAILED++))
    fi
}

check_log_files() {
    log_info "Checking log files..."

    if [ ! -d "$PROJECT_ROOT/storage/logs" ]; then
        log_warning "Logs directory not found"
        ((CHECKS_WARNING++))
        return
    fi

    # Check for recent errors
    if [ -f "$PROJECT_ROOT/storage/logs/unfurl.log" ]; then
        # Count ERROR and CRITICAL entries in last 100 lines
        ERROR_COUNT=$(tail -100 "$PROJECT_ROOT/storage/logs/unfurl.log" | grep -c '"level":"ERROR"\|"level":"CRITICAL"' || echo "0")

        if [ "$ERROR_COUNT" -eq 0 ]; then
            log_success "No recent errors in logs"
            ((CHECKS_PASSED++))
        elif [ "$ERROR_COUNT" -lt 5 ]; then
            log_warning "Found $ERROR_COUNT error(s) in recent logs"
            ((CHECKS_WARNING++))
        else
            log_error "Found $ERROR_COUNT error(s) in recent logs"
            ((CHECKS_FAILED++))
        fi
    else
        log_warning "Log file not found (may be new installation)"
        ((CHECKS_WARNING++))
    fi
}

check_required_tables() {
    log_info "Checking required database tables..."

    if [ ! -f "$PROJECT_ROOT/.env" ]; then
        log_warning "Cannot check tables: .env file not found"
        ((CHECKS_WARNING++))
        return
    fi

    # Read database credentials
    DB_HOST=$(grep "^DB_HOST=" "$PROJECT_ROOT/.env" | cut -d '=' -f2)
    DB_NAME=$(grep "^DB_NAME=" "$PROJECT_ROOT/.env" | cut -d '=' -f2)
    DB_USER=$(grep "^DB_USER=" "$PROJECT_ROOT/.env" | cut -d '=' -f2)
    DB_PASS=$(grep "^DB_PASS=" "$PROJECT_ROOT/.env" | cut -d '=' -f2)

    if [ -z "$DB_NAME" ]; then
        log_warning "Database credentials not found in .env"
        ((CHECKS_WARNING++))
        return
    fi

    # Check for required tables
    REQUIRED_TABLES=("feeds" "articles" "api_keys" "logs")
    MISSING_TABLES=0

    for table in "${REQUIRED_TABLES[@]}"; do
        TABLE_EXISTS=$(mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "SHOW TABLES LIKE '$table'" 2>/dev/null | grep -c "$table" || echo "0")

        if [ "$TABLE_EXISTS" -eq 0 ]; then
            log_error "Required table missing: $table"
            ((MISSING_TABLES++))
        fi
    done

    if [ $MISSING_TABLES -eq 0 ]; then
        log_success "All required tables exist"
        ((CHECKS_PASSED++))
    else
        log_error "Missing $MISSING_TABLES required table(s)"
        ((CHECKS_FAILED++))
    fi
}

check_php_version() {
    log_info "Checking PHP version..."

    if ! command -v php &> /dev/null; then
        log_error "PHP not found"
        ((CHECKS_FAILED++))
        return
    fi

    PHP_VERSION=$(php -r "echo PHP_VERSION;")
    PHP_MAJOR=$(echo "$PHP_VERSION" | cut -d. -f1)
    PHP_MINOR=$(echo "$PHP_VERSION" | cut -d. -f2)

    if [ "$PHP_MAJOR" -gt 8 ] || ([ "$PHP_MAJOR" -eq 8 ] && [ "$PHP_MINOR" -ge 1 ]); then
        log_success "PHP version OK ($PHP_VERSION)"
        ((CHECKS_PASSED++))
    else
        log_error "PHP version too old ($PHP_VERSION), require 8.1+"
        ((CHECKS_FAILED++))
    fi
}

check_required_extensions() {
    log_info "Checking required PHP extensions..."

    REQUIRED_EXTENSIONS=("pdo" "pdo_mysql" "curl" "mbstring" "json")
    MISSING_EXTENSIONS=0

    for ext in "${REQUIRED_EXTENSIONS[@]}"; do
        if ! php -m | grep -q "^$ext$"; then
            log_error "Required PHP extension missing: $ext"
            ((MISSING_EXTENSIONS++))
        fi
    done

    if [ $MISSING_EXTENSIONS -eq 0 ]; then
        log_success "All required PHP extensions present"
        ((CHECKS_PASSED++))
    else
        log_error "Missing $MISSING_EXTENSIONS required extension(s)"
        ((CHECKS_FAILED++))
    fi
}

################################################################################
# Main Health Check Process
################################################################################

echo ""
echo -e "${BLUE}================================${NC}"
echo -e "${BLUE}Unfurl Health Check${NC}"
echo -e "${BLUE}================================${NC}"
echo -e "URL: ${BASE_URL}"
echo -e "Time: $(date '+%Y-%m-%d %H:%M:%S')"
echo -e "${BLUE}================================${NC}"
echo ""

# Run all checks
check_health_endpoint
check_database_connection
check_required_tables
check_file_permissions
check_disk_space
check_log_files
check_php_version
check_required_extensions

# Print summary
echo ""
echo -e "${BLUE}================================${NC}"
echo -e "${BLUE}Health Check Summary${NC}"
echo -e "${BLUE}================================${NC}"
echo -e "${GREEN}Passed:${NC}  $CHECKS_PASSED"
echo -e "${RED}Failed:${NC}  $CHECKS_FAILED"
echo -e "${YELLOW}Warnings:${NC} $CHECKS_WARNING"
echo -e "${BLUE}================================${NC}"
echo ""

# Exit with appropriate code
if [ $CHECKS_FAILED -eq 0 ]; then
    if [ $CHECKS_WARNING -eq 0 ]; then
        echo -e "${GREEN}All health checks passed!${NC}"
        exit 0
    else
        echo -e "${YELLOW}Health checks passed with warnings${NC}"
        exit 0
    fi
else
    echo -e "${RED}Health checks failed!${NC}"
    exit 1
fi
