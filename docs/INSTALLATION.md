# Installation Guide

Complete installation instructions for Unfurl - Google News URL Decoder & RSS Feed Generator.

## Table of Contents

- [System Requirements](#system-requirements)
- [Pre-Installation Checklist](#pre-installation-checklist)
- [Installation Steps](#installation-steps)
- [Database Setup](#database-setup)
- [Environment Configuration](#environment-configuration)
- [Web Server Configuration](#web-server-configuration)
- [First-Time Setup](#first-time-setup)
- [Verification](#verification)
- [Troubleshooting](#troubleshooting)

## System Requirements

### Required

- **PHP** 8.1 or higher
- **MySQL** 5.7+ or **MariaDB** 10.3+
- **Composer** 2.0+
- **Web Server** Apache 2.4+ or Nginx 1.18+

### PHP Extensions

The following PHP extensions must be enabled:

- `pdo` - Database connectivity
- `pdo_mysql` - MySQL driver
- `json` - JSON processing
- `curl` - HTTP requests
- `dom` - HTML/XML parsing
- `mbstring` - Multi-byte string handling
- `xml` - XML processing
- `libxml` - XML library

### Recommended

- **PHP OpCache** - Improves performance
- **APCu** - User cache for sessions
- **SSH Access** - For automated deployments (optional)
- **Git** - For version control

## Pre-Installation Checklist

Before installing, ensure you have:

- [ ] Server access (SSH or cPanel)
- [ ] Database credentials (username, password, database name)
- [ ] Web server with PHP 8.1+ installed
- [ ] Composer installed globally or locally
- [ ] Domain or subdomain configured
- [ ] SSL certificate (recommended for production)

## Installation Steps

### Step 1: Download Source Code

**Option A: Using Git (recommended)**

```bash
# Clone repository
git clone https://github.com/cobenrogers/unfurl.git

# Navigate to directory
cd unfurl
```

**Option B: Download ZIP**

1. Download latest release from GitHub
2. Extract to your web server directory
3. Navigate to extracted directory

### Step 2: Install Dependencies

```bash
# Install Composer dependencies
composer install --no-dev --optimize-autoloader

# For development (includes PHPUnit)
composer install
```

### Step 3: Set Permissions

```bash
# Make storage directory writable
chmod 755 storage
chmod 755 storage/temp

# Ensure web server can write to storage
chown -R www-data:www-data storage

# On cPanel shared hosting
chmod 755 storage
```

### Step 4: Configure Environment

```bash
# Copy example environment file
cp .env.example .env

# Edit configuration
nano .env  # or use your preferred editor
```

See [Environment Configuration](#environment-configuration) for details.

## Database Setup

### Option A: Using MySQL Command Line

```bash
# Create database
mysql -u root -p
CREATE DATABASE unfurl_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

# Create database user
CREATE USER 'unfurl_user'@'localhost' IDENTIFIED BY 'secure_password_here';
GRANT ALL PRIVILEGES ON unfurl_db.* TO 'unfurl_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;

# Import schema
mysql -u unfurl_user -p unfurl_db < sql/schema.sql
```

### Option B: Using phpMyAdmin (cPanel)

1. Log in to cPanel
2. Open **MySQL Databases**
3. Create new database: `unfurl_db`
4. Create new user with secure password
5. Add user to database with **All Privileges**
6. Open **phpMyAdmin**
7. Select `unfurl_db` database
8. Click **Import** tab
9. Choose file: `sql/schema.sql`
10. Click **Go** to import

### Verify Database Schema

After importing, verify these tables exist:

- `feeds` - Google News RSS feeds
- `articles` - Processed articles
- `api_keys` - API authentication keys
- `logs` - Application logs
- `migrations` - Migration tracking
- `metrics` - Performance metrics (optional)

```bash
# Verify tables
mysql -u unfurl_user -p unfurl_db -e "SHOW TABLES;"
```

## Environment Configuration

Edit `.env` file with your settings:

### Database Configuration

```env
# Database connection
DB_HOST=localhost
DB_NAME=unfurl_db
DB_USER=unfurl_user
DB_PASS=your_secure_password

# For remote database
# DB_HOST=mysql.yourhost.com
# DB_PORT=3306
```

### Application Settings

```env
# Environment (production, development, testing)
APP_ENV=production

# Enable debug mode (only in development!)
APP_DEBUG=false

# Base URL (include trailing slash)
APP_BASE_URL=https://yoursite.com/unfurl/

# Timezone
APP_TIMEZONE=America/New_York
```

### Security Configuration

```env
# Session secret (generate with command below)
SESSION_SECRET=generate_random_32_byte_hex_string

# Generate secure session secret:
# php -r "echo bin2hex(random_bytes(32));"
```

**IMPORTANT**: Always generate a unique `SESSION_SECRET` for each installation!

### Processing Configuration

```env
# HTTP timeout for fetching articles (seconds)
PROCESSING_TIMEOUT=30

# Maximum retry attempts for failed articles
PROCESSING_MAX_RETRIES=3

# Initial retry delay (seconds)
PROCESSING_RETRY_DELAY=60
```

### Data Retention

```env
# Keep articles for N days (0 = forever)
RETENTION_ARTICLES_DAYS=90

# Keep logs for N days (minimum 7)
RETENTION_LOGS_DAYS=30

# Enable automatic cleanup
RETENTION_AUTO_CLEANUP=true
```

## Web Server Configuration

### Apache (.htaccess)

The `public/.htaccess` file is included with the project:

```apache
# Enable rewrite engine
RewriteEngine On

# Redirect to HTTPS (production only)
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Front controller
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]

# Security headers
Header set X-Content-Type-Options "nosniff"
Header set X-Frame-Options "SAMEORIGIN"
Header set X-XSS-Protection "1; mode=block"
Header set Referrer-Policy "strict-origin-when-cross-origin"
```

**Verify mod_rewrite is enabled:**

```bash
# Check Apache modules
apache2ctl -M | grep rewrite

# Enable if not loaded
a2enmod rewrite
service apache2 restart
```

### Nginx

Create configuration file `/etc/nginx/sites-available/unfurl`:

```nginx
server {
    listen 80;
    server_name yoursite.com;
    root /var/www/unfurl/public;
    index index.php;

    # Redirect to HTTPS
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name yoursite.com;
    root /var/www/unfurl/public;
    index index.php;

    # SSL configuration
    ssl_certificate /path/to/cert.pem;
    ssl_certificate_key /path/to/key.pem;

    # Security headers
    add_header X-Content-Type-Options "nosniff";
    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-XSS-Protection "1; mode=block";
    add_header Referrer-Policy "strict-origin-when-cross-origin";

    # PHP handling
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Deny access to hidden files
    location ~ /\. {
        deny all;
    }
}
```

Enable site and reload:

```bash
ln -s /etc/nginx/sites-available/unfurl /etc/nginx/sites-enabled/
nginx -t
service nginx reload
```

### cPanel Shared Hosting

1. Upload files to `public_html/unfurl/`
2. Create subdomain pointing to `public_html/unfurl/public/`
3. Ensure `.htaccess` is uploaded and active
4. Set directory permissions: `755` for directories, `644` for files
5. Ensure `storage/` is writable: `chmod 755 storage`

## First-Time Setup

### 1. Access Web Interface

Visit your installation URL:
- Development: `http://localhost:8000`
- Production: `https://yoursite.com/unfurl/`

### 2. Create Admin Account

On first visit, you'll be prompted to create an admin account:

1. Navigate to `/install` (one-time setup page)
2. Enter admin username and password
3. Submit form to create account

**Note**: If `/install` route doesn't exist, create admin directly in database:

```bash
php -r "
require 'vendor/autoload.php';
\$password = password_hash('your_password', PASSWORD_BCRYPT);
echo \"Hashed password: \$password\n\";
"

# Insert into database
mysql -u unfurl_user -p unfurl_db -e "
INSERT INTO users (username, password_hash, created_at)
VALUES ('admin', 'paste_hashed_password_here', NOW());
"
```

### 3. Create First API Key

1. Log in to web interface
2. Navigate to **Settings** page
3. Click **Create API Key**
4. Enter name: "Production Cron"
5. Save key value (shown only once!)

### 4. Add Your First Feed

1. Navigate to **Feeds** page
2. Click **Add Feed**
3. Enter topic: "technology"
4. Enter Google News RSS URL
5. Set result limit (e.g., 10)
6. Enable feed
7. Click **Save**

### 5. Process Feed Manually

1. Navigate to **Feeds** page
2. Click **Process** button on your feed
3. Wait for processing to complete
4. Check **Articles** page for results

### 6. Test RSS Output

Visit feed endpoint:
```
https://yoursite.com/unfurl/feed.php?topic=technology
```

Verify RSS XML is generated correctly.

### 7. Setup Cron Job (Optional)

For automated processing, setup cron job:

**Using cPanel:**
1. Open **Cron Jobs** in cPanel
2. Add new cron job:
   - Minute: `0`
   - Hour: `9` (9 AM)
   - Day: `*`
   - Month: `*`
   - Weekday: `*`
   - Command: `curl -X POST -H "X-API-Key: YOUR_KEY" https://yoursite.com/unfurl/api.php`

**Using command line:**
```bash
# Edit crontab
crontab -e

# Add line (daily at 9 AM)
0 9 * * * curl -X POST -H "X-API-Key: YOUR_KEY" https://yoursite.com/unfurl/api.php
```

## Verification

### Health Check

```bash
# Test health endpoint
curl https://yoursite.com/unfurl/health.php

# Expected response
{"status":"ok","timestamp":"2026-02-07T15:30:00Z"}
```

### Run Tests (Development)

```bash
# Run all tests
composer test

# Expected output
# PHPUnit 10.5.63 by Sebastian Bergmann and contributors.
# ............................................................... (464/464)
# Time: XX.XX seconds, Memory: XX.XX MB
# OK (464 tests, 1448 assertions)
```

### Check File Permissions

```bash
# Verify storage is writable
touch storage/test.txt && rm storage/test.txt && echo "Storage is writable"

# Check ownership
ls -la storage
```

### Database Connectivity

```bash
# Test database connection
php -r "
require 'vendor/autoload.php';
\$config = parse_ini_file('.env');
\$dsn = \"mysql:host={\$config['DB_HOST']};dbname={\$config['DB_NAME']}\";
try {
    new PDO(\$dsn, \$config['DB_USER'], \$config['DB_PASS']);
    echo \"Database connection successful\n\";
} catch (PDOException \$e) {
    echo \"Database connection failed: \" . \$e->getMessage() . \"\n\";
}
"
```

## Troubleshooting

### Database Connection Failed

**Symptom**: "Could not connect to database" error

**Solutions**:
1. Verify database credentials in `.env`
2. Check database exists: `mysql -u root -p -e "SHOW DATABASES;"`
3. Verify user has privileges: `SHOW GRANTS FOR 'unfurl_user'@'localhost';`
4. Check database server is running: `service mysql status`
5. Test connection from command line: `mysql -u unfurl_user -p unfurl_db`

### 500 Internal Server Error

**Symptom**: White page or 500 error

**Solutions**:
1. Check PHP error log: `tail -f /var/log/apache2/error.log`
2. Enable debug mode temporarily: `APP_DEBUG=true` in `.env`
3. Verify `.htaccess` uploaded correctly
4. Check file permissions (755 for dirs, 644 for files)
5. Verify mod_rewrite enabled: `a2enmod rewrite`
6. Check PHP version: `php -v` (must be 8.1+)

### Blank White Page

**Symptom**: Empty page with no errors

**Solutions**:
1. Check PHP display_errors: `ini_get('display_errors')`
2. Check error log for details
3. Verify autoloader: `composer dump-autoload`
4. Check index.php exists in public directory
5. Verify web server document root points to `public/`

### Articles Not Processing

**Symptom**: Feed processing returns 0 articles

**Solutions**:
1. Check Google News URL is valid and accessible
2. Verify cURL extension installed: `php -m | grep curl`
3. Test URL manually in browser
4. Check logs table for error messages
5. Verify PROCESSING_TIMEOUT is sufficient (30+ seconds)
6. Check firewall not blocking outbound requests

### RSS Feed Empty

**Symptom**: Feed generates but has no items

**Solutions**:
1. Verify articles exist in database: `SELECT COUNT(*) FROM articles;`
2. Check article status: `SELECT status, COUNT(*) FROM articles GROUP BY status;`
3. Verify topic filter matches: `SELECT DISTINCT topic FROM articles;`
4. Check processed_at is not NULL
5. Try accessing feed without filters: `/feed.php`

### Permission Denied Errors

**Symptom**: Cannot write to storage directory

**Solutions**:
1. Set directory permissions: `chmod 755 storage`
2. Set ownership: `chown -R www-data:www-data storage`
3. For cPanel: ensure user owns directory
4. Check SELinux not blocking: `getenforce`
5. Verify parent directory is executable

### Composer Install Fails

**Symptom**: Composer dependencies won't install

**Solutions**:
1. Update Composer: `composer self-update`
2. Clear cache: `composer clear-cache`
3. Check PHP version: `composer show --platform`
4. Install with platform checks disabled: `composer install --ignore-platform-reqs`
5. Verify internet connectivity
6. Check memory limit: `php -i | grep memory_limit`

### API Rate Limit Errors

**Symptom**: "Rate limit exceeded" when processing

**Solutions**:
1. Rate limit is 60 requests/minute per API key
2. Wait 60 seconds and try again
3. Create additional API key for different processes
4. Reduce frequency of cron jobs
5. Check for multiple processes using same key

## Post-Installation

After successful installation:

1. **Remove install files** (if any)
2. **Change default passwords**
3. **Setup regular backups** (database + files)
4. **Configure monitoring** (health check endpoint)
5. **Setup SSL certificate** (Let's Encrypt recommended)
6. **Review security settings** in `.env`
7. **Test RSS feeds** with RSS reader
8. **Monitor logs** for errors: check `logs` table
9. **Setup cron jobs** for automated processing
10. **Document your configuration** for future reference

## Next Steps

- Read [DEPLOYMENT.md](DEPLOYMENT.md) for production deployment guide
- Review [API.md](API.md) for API integration
- Check [TESTING.md](TESTING.md) for running tests
- See [CLAUDE.md](CLAUDE.md) for architecture details

## Getting Help

If you encounter issues not covered here:

1. Check application logs in database: `SELECT * FROM logs ORDER BY created_at DESC LIMIT 10;`
2. Review [CLAUDE.md](CLAUDE.md) Common Issues section
3. Enable debug mode and check error details
4. Search existing GitHub issues
5. Open new GitHub issue with:
   - Error message
   - PHP version
   - Database version
   - Steps to reproduce

---

**Last Updated**: 2026-02-07
**Version**: 1.0.0
