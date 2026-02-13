# Quick Start - Production Deployment

**Unfurl - Fast Track to Production**

This is a condensed version of the production checklist for experienced administrators. For detailed instructions, see `PRODUCTION-CHECKLIST.md`.

---

## Prerequisites

- PHP 8.1+ with extensions: pdo, pdo_mysql, curl, mbstring, json
- MySQL/MariaDB 5.7+
- Apache/Nginx with mod_rewrite
- Composer installed
- SSL certificate ready
- cPanel or SSH access

---

## 5-Minute Setup

### 1. Deploy Files

```bash
# Upload files to server or clone from git
git clone https://github.com/cobenrogers/unfurl.git
cd unfurl

# Run deployment script
./scripts/deploy.sh production
```

### 2. Configure Environment

```bash
# Copy and edit .env
cp .env.example .env
nano .env

# Generate session secret
php -r "echo bin2hex(random_bytes(32));"
```

**Required .env settings:**
```ini
APP_ENV=production
APP_DEBUG=false
APP_BASE_URL=https://yoursite.com/unfurl/
SESSION_SECRET=your_64_char_hex_here

DB_HOST=localhost
DB_NAME=unfurl_db
DB_USER=unfurl_user
DB_PASS=strong_password_here
```

### 3. Create Database

```sql
CREATE DATABASE unfurl_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'unfurl_user'@'localhost' IDENTIFIED BY 'strong_password';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX ON unfurl_db.* TO 'unfurl_user'@'localhost';
FLUSH PRIVILEGES;
```

### 4. Import Schema

```bash
mysql -u unfurl_user -p unfurl_db < sql/schema.sql
```

### 5. Set Permissions

```bash
chmod 600 .env
chmod -R 755 storage
chmod +x scripts/*.sh
chmod +x scripts/*.php
```

### 6. Verify Installation

```bash
# Check database indexes
php scripts/verify-indexes.php

# Run health check
./scripts/health-check.sh https://yoursite.com/unfurl
```

---

## Security Setup (Critical!)

### Enable HTTPS

Edit `public/.htaccess` and uncomment these lines:

```apache
# Force HTTPS
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Enable HSTS
Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains; preload"
```

### Verify Security Headers

```bash
curl -I https://yoursite.com/
```

Should see:
- Content-Security-Policy
- X-Frame-Options: DENY
- X-Content-Type-Options: nosniff
- Strict-Transport-Security (after HSTS enabled)

Test at: https://securityheaders.com

---

## Create API Key

1. Access settings page: `https://yoursite.com/unfurl/settings`
2. Create new API key
3. Save the key securely (shown only once)
4. Test API:

```bash
curl -X POST https://yoursite.com/unfurl/api.php \
  -H "X-API-Key: your-api-key-here"
```

---

## Configure Cron Jobs

Add to crontab:

```bash
# Daily feed processing at 9 AM
0 9 * * * curl -X POST -H "X-API-Key: YOUR_KEY" https://yoursite.com/unfurl/api.php

# Health check every 5 minutes
*/5 * * * * /path/to/unfurl/scripts/health-check.sh https://yoursite.com/unfurl

# Daily database backup at 1 AM
0 1 * * * mysqldump -u unfurl_user -pPASSWORD unfurl_db | gzip > /path/to/backups/unfurl_$(date +\%Y\%m\%d).sql.gz

# Weekly file backup on Sunday at 2 AM
0 2 * * 0 tar -czf /path/to/backups/unfurl_files_$(date +\%Y\%m\%d).tar.gz -C /path/to/unfurl .
```

---

## Monitoring Setup

### External Health Check

Set up monitoring at:
- [UptimeRobot](https://uptimerobot.com) (free)
- [Pingdom](https://pingdom.com)
- [StatusCake](https://statuscake.com)

**Monitor:** `https://yoursite.com/unfurl/health.php`
**Check interval:** 5 minutes
**Expected response:** `{"status":"ok","timestamp":"..."}`

### Log Monitoring

```bash
# Watch error logs
tail -f storage/logs/unfurl.log | grep ERROR

# Count recent errors
tail -100 storage/logs/unfurl.log | grep -c ERROR

# Find critical errors
grep CRITICAL storage/logs/unfurl.log
```

---

## Testing Checklist

Quick tests after deployment:

```bash
# 1. Health check
curl https://yoursite.com/unfurl/health.php
# Expected: {"status":"ok",...}

# 2. Database indexes
php scripts/verify-indexes.php
# Expected: All checks pass

# 3. Error pages
curl -I https://yoursite.com/unfurl/nonexistent
# Expected: HTTP 404

# 4. Security headers
curl -I https://yoursite.com/unfurl/
# Expected: CSP, X-Frame-Options, etc.

# 5. API authentication
curl -X POST https://yoursite.com/unfurl/api.php \
  -H "X-API-Key: your-key"
# Expected: {"success":true,...}
```

---

## Common Issues

### Error: "Database connection failed"
- Check .env database credentials
- Verify database exists: `mysql -u unfurl_user -p -e "USE unfurl_db;"`
- Check database user permissions

### Error: "Permission denied"
- Set correct permissions: `chmod -R 755 storage`
- Verify .env readable: `chmod 600 .env`
- Check ownership: `chown -R www-data:www-data /path/to/unfurl`

### Error: "Missing indexes"
- Run: `php scripts/verify-indexes.php`
- Re-import schema: `mysql unfurl_db < sql/schema.sql`

### Error: "Health check failed"
- Check .env APP_BASE_URL is correct
- Verify .htaccess is in place
- Check mod_rewrite is enabled
- Review web server error logs

---

## Emergency Procedures

### Rollback Deployment

```bash
# List available backups
./scripts/rollback.sh

# Rollback to specific timestamp
./scripts/rollback.sh 20260207_143022
```

### Database Recovery

```bash
# Restore from backup
gunzip < backups/unfurl_db_20260207.sql.gz | \
  mysql -u unfurl_user -p unfurl_db
```

### Check System Health

```bash
./scripts/health-check.sh https://yoursite.com/unfurl
```

---

## Performance Optimization

Quick wins:

```bash
# 1. Enable OPcache (php.ini)
opcache.enable=1
opcache.memory_consumption=128

# 2. Optimize database tables
mysql -u unfurl_user -p unfurl_db -e "OPTIMIZE TABLE articles;"

# 3. Verify indexes are used
mysql -u unfurl_user -p unfurl_db -e "EXPLAIN SELECT * FROM articles WHERE status = 'success' LIMIT 10;"

# 4. Check gzip compression
curl -H "Accept-Encoding: gzip" -I https://yoursite.com/unfurl/
# Should see: Content-Encoding: gzip
```

---

## Monitoring Dashboard

Access: `https://yoursite.com/unfurl/dashboard`

Shows:
- Total feeds (enabled/disabled)
- Article statistics (success/failed/pending)
- Processing queue status
- Recent activity
- System health
- Auto-refreshes every 30 seconds

---

## Daily Maintenance

```bash
# 1. Check health
./scripts/health-check.sh

# 2. Review errors
grep ERROR storage/logs/unfurl.log | tail -20

# 3. Verify backups
ls -lh backups/ | tail -5

# 4. Check disk space
df -h | grep /path/to/unfurl
```

---

## Support

- **Full Guide**: `docs/PRODUCTION-CHECKLIST.md`
- **Security**: `docs/SECURITY-LAYER-IMPLEMENTATION.md`
- **Performance**: `docs/PERFORMANCE-TESTING.md`
- **API**: `docs/API-CONTROLLER.md`
- **Project**: `CLAUDE.md`

---

## Next Steps After Deployment

1. **Monitor for 24 hours** - Watch logs, check metrics
2. **Create test feed** - Verify end-to-end functionality
3. **Test API processing** - Ensure articles are created
4. **Verify backups** - Confirm daily backups running
5. **Set up alerts** - Configure monitoring notifications
6. **Document specifics** - Note any custom configurations

---

## Success Checklist

- [x] Files deployed
- [x] .env configured
- [x] Database created and imported
- [x] Permissions set
- [x] HTTPS enabled
- [x] Security headers verified
- [x] API key created
- [x] Cron jobs configured
- [x] Monitoring enabled
- [x] Backups configured
- [x] Health check passes
- [x] Test feed created

**🚀 Production deployment complete!**

---

**Remember:** This is the quick start. For comprehensive guidance, always refer to `docs/PRODUCTION-CHECKLIST.md`.
