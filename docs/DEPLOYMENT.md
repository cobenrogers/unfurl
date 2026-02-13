# Deployment Guide

Production deployment guide for Unfurl - Google News URL Decoder & RSS Feed Generator.

## Table of Contents

- [Overview](#overview)
- [Pre-Deployment Checklist](#pre-deployment-checklist)
- [cPanel Deployment](#cpanel-deployment)
- [GitHub Actions CI/CD](#github-actions-cicd)
- [Database Migrations](#database-migrations)
- [Environment Configuration](#environment-configuration)
- [Security Hardening](#security-hardening)
- [Post-Deployment Verification](#post-deployment-verification)
- [Monitoring](#monitoring)
- [Rollback Procedures](#rollback-procedures)
- [Troubleshooting](#troubleshooting)

## Overview

Unfurl uses a CI/CD pipeline that automatically deploys to production when code is pushed to the `main` branch.

**Deployment Flow:**
1. Push to `main` branch on GitHub
2. GitHub Actions workflow triggered
3. Automated tests run (464 tests)
4. If tests pass, deploy via rsync to production
5. Health check verifies deployment
6. Deployment complete

**Production Environment:**
- **Hosting**: Bluehost cPanel shared hosting
- **Database**: MySQL via cPanel
- **Deployment Method**: rsync (not git clone)
- **Web Server**: Apache with mod_rewrite

## Pre-Deployment Checklist

Before deploying to production:

### Code Quality
- [ ] All tests passing locally (`composer test`)
- [ ] No debug code or console logs
- [ ] No hardcoded credentials or API keys
- [ ] `.env` file not committed to git
- [ ] Code reviewed and approved

### Documentation
- [ ] CHANGELOG.md updated with changes
- [ ] README.md reflects current functionality
- [ ] API documentation current
- [ ] Migration notes added (if database changes)

### Security
- [ ] All secrets in `.env` file
- [ ] No credentials in source code
- [ ] CSRF tokens implemented on forms
- [ ] Input validation on all endpoints
- [ ] SQL injection protection verified
- [ ] XSS prevention tested

### Database
- [ ] Migration SQL prepared (if needed)
- [ ] Backup of production database taken
- [ ] Migration tested on staging/local
- [ ] Rollback plan documented

### Configuration
- [ ] Production `.env` values ready
- [ ] API keys generated
- [ ] Database credentials verified
- [ ] Base URL configured
- [ ] Timezone set correctly

### Testing
- [ ] Unit tests pass: `composer test:unit`
- [ ] Integration tests pass: `composer test:integration`
- [ ] Performance tests pass: `composer test:performance`
- [ ] Manual smoke test completed
- [ ] RSS feeds tested

## cPanel Deployment

### Initial Setup (One-Time)

#### 1. Database Setup

**Create Database:**
1. Log in to cPanel
2. Navigate to **MySQL Databases**
3. Create database: `unfurl_db`
4. Note the full database name (e.g., `cpanel_user_unfurl_db`)

**Create Database User:**
1. In **MySQL Database Users** section
2. Create user: `unfurl_user`
3. Generate strong password (save securely!)
4. Add user to database with **All Privileges**

**Import Schema:**
1. Open **phpMyAdmin**
2. Select database: `unfurl_db`
3. Click **Import** tab
4. Choose file: `sql/schema.sql`
5. Click **Go**
6. Verify all tables created successfully

#### 2. File Upload

**Create Directory Structure:**
1. Navigate to **File Manager**
2. Create directory: `public_html/unfurl/`
3. Upload all files EXCEPT:
   - `.git/` directory
   - `tests/` directory (optional, for space)
   - `.env` file (create separately)
   - `node_modules/` (if any)
   - `coverage/` (if any)

**Set Permissions:**
```
Directories: 755
Files: 644
storage/: 755 (must be writable)
storage/temp/: 755
```

#### 3. Create .env File

In cPanel File Manager:
1. Navigate to `public_html/unfurl/`
2. Create new file: `.env`
3. Copy content from `.env.example`
4. Update all values for production
5. Save file

**Production .env Example:**
```env
# Database (use actual cPanel values)
DB_HOST=localhost
DB_NAME=cpaneluser_unfurl_db
DB_USER=cpaneluser_unfurl_user
DB_PASS=generated_secure_password

# Application
APP_ENV=production
APP_DEBUG=false
APP_BASE_URL=https://yoursite.com/unfurl/
APP_TIMEZONE=America/New_York

# Security (generate unique value!)
SESSION_SECRET=generate_with_php_bin2hex_random_bytes_32

# Processing
PROCESSING_TIMEOUT=30
PROCESSING_MAX_RETRIES=3
PROCESSING_RETRY_DELAY=60

# Retention
RETENTION_ARTICLES_DAYS=90
RETENTION_LOGS_DAYS=30
RETENTION_AUTO_CLEANUP=true
```

**Generate Session Secret:**
```bash
php -r "echo bin2hex(random_bytes(32));"
```

#### 4. Install Dependencies

**Via SSH (if available):**
```bash
cd ~/public_html/unfurl
composer install --no-dev --optimize-autoloader
```

**Via cPanel Terminal (if available):**
1. Open **Terminal** in cPanel
2. Run same commands as above

**No SSH/Terminal Access:**
1. Install dependencies locally
2. Upload `vendor/` directory via FTP/File Manager
3. Ensure all files uploaded successfully

#### 5. Configure Web Server

**Subdomain Setup:**
1. In cPanel, navigate to **Subdomains**
2. Create subdomain: `unfurl.yoursite.com`
3. Document root: `public_html/unfurl/public`
4. Create subdomain

**Verify .htaccess:**
Ensure `public/.htaccess` contains:
```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]
```

#### 6. SSL Certificate

**Using Let's Encrypt (cPanel):**
1. Navigate to **SSL/TLS Status**
2. Check subdomain: `unfurl.yoursite.com`
3. Click **Run AutoSSL**
4. Wait for certificate installation
5. Verify HTTPS works

#### 7. Create API Key

1. Access site: `https://unfurl.yoursite.com`
2. Log in (or create admin account)
3. Navigate to **Settings**
4. Create API key for cron jobs
5. Save key value securely

#### 8. Setup Cron Jobs

**In cPanel Cron Jobs:**

**Daily Feed Processing:**
- Minute: `0`
- Hour: `9`
- Day: `*`
- Month: `*`
- Weekday: `*`
- Command: `curl -X POST -H "X-API-Key: YOUR_API_KEY" https://unfurl.yoursite.com/api.php >/dev/null 2>&1`

**Daily Cleanup (Optional):**
- Minute: `0`
- Hour: `2`
- Day: `*`
- Month: `*`
- Weekday: `*`
- Command: `curl -X POST -H "X-API-Key: YOUR_API_KEY" https://unfurl.yoursite.com/api.php?action=cleanup >/dev/null 2>&1`

## GitHub Actions CI/CD

### Setup GitHub Secrets

Required secrets (Settings → Secrets → Actions):

- `DEPLOY_HOST` - Production server hostname
- `DEPLOY_USER` - SSH username
- `DEPLOY_PATH` - Path to deployment directory
- `DEPLOY_KEY` - SSH private key (if using SSH deployment)

### Workflow File

Create `.github/workflows/deploy.yml`:

```yaml
name: Deploy to Production

on:
  push:
    branches: [ main ]

jobs:
  test:
    runs-on: ubuntu-latest

    steps:
    - uses: actions/checkout@v3

    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: '8.1'
        extensions: pdo, pdo_mysql, json, curl, dom, mbstring

    - name: Install dependencies
      run: composer install --prefer-dist --no-progress

    - name: Run tests
      run: composer test

    - name: Run security tests
      run: composer test:security

    - name: Run performance tests
      run: composer test:performance

  deploy:
    needs: test
    runs-on: ubuntu-latest
    if: github.ref == 'refs/heads/main'

    steps:
    - uses: actions/checkout@v3

    - name: Install dependencies
      run: composer install --no-dev --optimize-autoloader

    - name: Deploy via rsync
      uses: burnett01/rsync-deployments@5.2
      with:
        switches: -avzr --delete --exclude='.git' --exclude='.env' --exclude='storage/*' --exclude='tests' --exclude='coverage'
        path: ./
        remote_path: ${{ secrets.DEPLOY_PATH }}
        remote_host: ${{ secrets.DEPLOY_HOST }}
        remote_user: ${{ secrets.DEPLOY_USER }}
        remote_key: ${{ secrets.DEPLOY_KEY }}

    - name: Health check
      run: |
        sleep 5
        curl --fail https://unfurl.yoursite.com/health.php || exit 1
```

### Manual Deployment Trigger

If you need to deploy without pushing to main:

```bash
# Using GitHub CLI
gh workflow run deploy.yml

# Or push to main
git push origin main
```

## Database Migrations

### Creating Migration Files

When database schema changes:

1. Create migration file: `sql/migrations/YYYY-MM-DD_description.sql`
2. Document changes in migration
3. Test on local database first
4. Add rollback SQL in comments

**Example Migration:**
```sql
-- Migration: Add retry_count column to articles
-- Date: 2026-02-07
-- Author: Your Name

-- Forward migration
ALTER TABLE articles
ADD COLUMN retry_count INT DEFAULT 0 AFTER error_message;

-- Rollback (in comments)
-- ALTER TABLE articles DROP COLUMN retry_count;
```

### Applying Migrations (Manual - No SSH)

**Via phpMyAdmin:**
1. Open phpMyAdmin in cPanel
2. Select `unfurl_db` database
3. Click **SQL** tab
4. Paste migration SQL
5. Click **Go**
6. Verify success
7. Record in `migrations` table:
```sql
INSERT INTO migrations (migration_name, applied_at)
VALUES ('2026-02-07_add_retry_count.sql', NOW());
```

### Applying Migrations (With SSH)

```bash
# Connect to server
ssh user@yourserver.com

# Navigate to project
cd ~/public_html/unfurl

# Run migration
mysql -u DB_USER -p DB_NAME < sql/migrations/2026-02-07_migration.sql

# Record migration
mysql -u DB_USER -p DB_NAME -e "
INSERT INTO migrations (migration_name, applied_at)
VALUES ('2026-02-07_migration.sql', NOW());
"
```

### Migration Best Practices

- Always backup database before migrations
- Test migrations on staging first
- Keep migrations small and focused
- Include rollback instructions
- Never modify old migrations
- Record all applied migrations

## Environment Configuration

### Production Settings

```env
# ALWAYS use these in production
APP_ENV=production
APP_DEBUG=false

# NEVER use these in production
# APP_DEBUG=true
# DB_PASS=password
```

### Security Settings

```env
# Force HTTPS in production
FORCE_HTTPS=true

# Secure session cookies
SESSION_SECURE=true
SESSION_HTTPONLY=true
SESSION_SAMESITE=Strict

# Rate limiting
RATE_LIMIT_ENABLED=true
RATE_LIMIT_REQUESTS=60
RATE_LIMIT_WINDOW=60
```

### Performance Tuning

```env
# Enable opcode caching
OPCACHE_ENABLED=true

# Cache settings
CACHE_DRIVER=file
CACHE_TTL=300

# RSS feed cache
RSS_CACHE_TTL=300
```

## Security Hardening

### File Permissions

```bash
# Set secure permissions
find . -type d -exec chmod 755 {} \;
find . -type f -exec chmod 644 {} \;

# Storage writable
chmod 755 storage
chmod 755 storage/temp

# Protect .env
chmod 600 .env
```

### Web Server Security

**Apache (.htaccess):**
```apache
# Disable directory listing
Options -Indexes

# Protect .env file
<Files .env>
    Require all denied
</Files>

# Protect sensitive files
<FilesMatch "^(composer\.(json|lock)|package\.json|\.git)">
    Require all denied
</FilesMatch>

# Security headers
Header set X-Content-Type-Options "nosniff"
Header set X-Frame-Options "SAMEORIGIN"
Header set X-XSS-Protection "1; mode=block"
Header set Referrer-Policy "strict-origin-when-cross-origin"
Header set Content-Security-Policy "default-src 'self'"

# Force HTTPS
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

### Database Security

```sql
-- Use least privilege principle
REVOKE ALL PRIVILEGES ON unfurl_db.* FROM 'unfurl_user'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON unfurl_db.* TO 'unfurl_user'@'localhost';
FLUSH PRIVILEGES;

-- No DROP or ALTER needed in production
```

### Application Security

- All user input validated
- SQL injection prevention (prepared statements)
- XSS prevention (output escaping)
- CSRF protection on forms
- Rate limiting on API endpoints
- Secure password hashing (bcrypt)
- API key validation

## Post-Deployment Verification

### Health Check

```bash
# Test health endpoint
curl https://unfurl.yoursite.com/health.php

# Expected response
{"status":"ok","timestamp":"2026-02-07T15:30:00Z"}
```

### Functionality Tests

**1. Test Web Interface:**
- [ ] Can access homepage
- [ ] Can log in
- [ ] Can view feeds
- [ ] Can view articles
- [ ] Can access settings

**2. Test API:**
```bash
# Test API authentication
curl -X POST https://unfurl.yoursite.com/api.php \
  -H "X-API-Key: YOUR_KEY"

# Expected: JSON response with processing stats
```

**3. Test RSS Feeds:**
```bash
# Test feed generation
curl https://unfurl.yoursite.com/feed.php?topic=technology

# Expected: Valid RSS 2.0 XML
```

**4. Test Database:**
```sql
-- Check tables exist
SHOW TABLES;

-- Check recent logs
SELECT * FROM logs ORDER BY created_at DESC LIMIT 5;

-- Check articles
SELECT COUNT(*) FROM articles;
```

### Performance Verification

```bash
# Test response times
time curl https://unfurl.yoursite.com/health.php

# Expected: < 100ms
```

### SSL Verification

```bash
# Check SSL certificate
openssl s_client -connect unfurl.yoursite.com:443 -servername unfurl.yoursite.com

# Or use SSL checker
curl -vI https://unfurl.yoursite.com
```

## Monitoring

### Health Monitoring

Setup external monitoring (e.g., UptimeRobot, Pingdom):

- URL: `https://unfurl.yoursite.com/health.php`
- Interval: Every 5 minutes
- Expected: HTTP 200, JSON response
- Alert: Email/SMS on failure

### Log Monitoring

Check logs regularly:

```sql
-- Recent errors
SELECT * FROM logs
WHERE level IN ('ERROR', 'CRITICAL')
ORDER BY created_at DESC
LIMIT 10;

-- Processing failures
SELECT * FROM articles
WHERE status = 'failed'
ORDER BY created_at DESC
LIMIT 10;

-- API usage
SELECT key_name, COUNT(*) as requests
FROM api_keys
WHERE last_used_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
GROUP BY key_name;
```

### Performance Monitoring

```sql
-- Track processing metrics
SELECT
    DATE(created_at) as date,
    COUNT(*) as articles_processed,
    AVG(word_count) as avg_words,
    COUNT(CASE WHEN status = 'failed' THEN 1 END) as failures
FROM articles
WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY DATE(created_at);
```

## Rollback Procedures

### Quick Rollback (File Level)

If deployment causes issues:

**Option 1: Restore from backup (cPanel):**
1. Navigate to **File Manager**
2. Select `public_html/unfurl/`
3. Click **Restore** from backup
4. Select previous backup point
5. Restore files

**Option 2: Redeploy previous version:**
```bash
# Checkout previous commit
git checkout PREVIOUS_COMMIT_HASH

# Redeploy
git push origin main --force
```

### Database Rollback

**If migration causes issues:**

1. Restore database backup (cPanel → phpMyAdmin → Import)
2. Or run rollback SQL:
```sql
-- Example rollback
ALTER TABLE articles DROP COLUMN retry_count;

-- Remove migration record
DELETE FROM migrations WHERE migration_name = '2026-02-07_migration.sql';
```

### Emergency Rollback Checklist

- [ ] Verify issue requires rollback (not configuration)
- [ ] Communicate rollback to team
- [ ] Take snapshot of current state (for analysis)
- [ ] Restore files from backup or previous version
- [ ] Restore database if schema changed
- [ ] Clear caches
- [ ] Test health endpoint
- [ ] Verify functionality restored
- [ ] Document issue for post-mortem

## Troubleshooting

### Deployment Fails in CI/CD

**Check:**
1. GitHub Actions logs for error details
2. Test suite results
3. SSH connection (if using SSH deployment)
4. Disk space on server
5. File permissions

### Site Returns 500 Error After Deploy

**Solutions:**
1. Check `.env` file exists and is correct
2. Verify file permissions (755/644)
3. Check Apache error log
4. Verify `vendor/` directory uploaded
5. Check database connection
6. Enable debug mode temporarily

### Database Migration Failed

**Solutions:**
1. Check migration SQL syntax
2. Verify database user has permissions
3. Check for table locks
4. Review error in phpMyAdmin
5. Rollback and try again

### RSS Feeds Not Working

**Solutions:**
1. Verify articles exist in database
2. Check feed.php permissions
3. Test query directly in MySQL
4. Check for PHP errors in log
5. Verify RSS cache directory writable

### API Returns Authentication Error

**Solutions:**
1. Verify API key exists in database
2. Check key is enabled
3. Verify header format: `X-API-Key: key_value`
4. Check for typos in key value
5. Review API logs for details

## Best Practices

1. **Always test before deploying** - Run full test suite
2. **Backup before changes** - Database and files
3. **Deploy during low traffic** - Minimize user impact
4. **Monitor after deployment** - Check logs and metrics
5. **Have rollback plan ready** - Know how to revert
6. **Document changes** - Update CHANGELOG.md
7. **Communicate deployments** - Notify team
8. **Use staging environment** - Test in production-like environment first
9. **Keep .env secure** - Never commit to git
10. **Regular backups** - Automated daily backups

---

**Last Updated**: 2026-02-07
**Version**: 1.0.0
