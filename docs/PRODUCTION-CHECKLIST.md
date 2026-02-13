# Production Deployment Checklist

**Unfurl - Production Readiness Checklist**
Version: 1.0.0
Last Updated: 2026-02-07

This comprehensive checklist ensures your Unfurl deployment is production-ready with proper security, performance, monitoring, and backup procedures.

---

## Pre-Deployment Checks

### 1. Environment Configuration

- [ ] Copy `.env.example` to `.env`
- [ ] Set `APP_ENV=production`
- [ ] Set `APP_DEBUG=false`
- [ ] Generate secure `SESSION_SECRET` (64 char hex)
  ```bash
  php -r "echo bin2hex(random_bytes(32));"
  ```
- [ ] Configure correct `APP_BASE_URL`
- [ ] Set appropriate `APP_TIMEZONE`
- [ ] Configure database credentials (dedicated user)
- [ ] Set retention policies (`RETENTION_ARTICLES_DAYS`, `RETENTION_LOGS_DAYS`)
- [ ] Verify processing settings (`PROCESSING_TIMEOUT`, `PROCESSING_MAX_RETRIES`)

### 2. Database Setup

- [ ] Create database: `CREATE DATABASE unfurl_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`
- [ ] Create dedicated database user with minimal permissions
  ```sql
  CREATE USER 'unfurl_user'@'localhost' IDENTIFIED BY 'strong_password';
  GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX ON unfurl_db.* TO 'unfurl_user'@'localhost';
  FLUSH PRIVILEGES;
  ```
- [ ] Import schema: `mysql unfurl_db < sql/schema.sql`
- [ ] Run migrations (if any exist in `sql/migrations/`)
- [ ] Verify all tables created: `SHOW TABLES;`
- [ ] Run index verification: `php scripts/verify-indexes.php`
- [ ] Confirm all indexes present and optimal

### 3. File Permissions

- [ ] Set secure permissions on application directory:
  ```bash
  chmod 755 /path/to/unfurl
  chmod -R 755 /path/to/unfurl/public
  chmod -R 700 /path/to/unfurl/storage
  chmod 600 /path/to/unfurl/.env
  ```
- [ ] Verify storage directories are writable:
  ```bash
  chmod 755 storage/logs
  chmod 755 storage/temp
  ```
- [ ] Ensure `.env` is NOT readable by web server (outside public root or protected)
- [ ] Verify `.htaccess` is in place in `public/` directory
- [ ] Test that sensitive files cannot be accessed via web:
  - Try accessing: `/.env`, `/config.php`, `/composer.json`
  - Should return 403 Forbidden

### 4. Security Headers

- [ ] Verify `.htaccess` security headers are active
- [ ] Test headers using: [securityheaders.com](https://securityheaders.com)
- [ ] Check CSP (Content-Security-Policy) is set
- [ ] Verify X-Frame-Options: DENY
- [ ] Confirm X-Content-Type-Options: nosniff
- [ ] Test Referrer-Policy is set
- [ ] Verify Permissions-Policy is configured
- [ ] Confirm Server header is removed

### 5. SSL/TLS Configuration

- [ ] Install SSL certificate (Let's Encrypt recommended)
- [ ] Uncomment HTTPS redirect in `.htaccess`:
  ```apache
  RewriteCond %{HTTPS} off
  RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
  ```
- [ ] Enable HSTS header in `.htaccess`:
  ```apache
  Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains; preload"
  ```
- [ ] Test SSL configuration: [ssllabs.com/ssltest](https://www.ssllabs.com/ssltest/)
- [ ] Verify all assets load over HTTPS (no mixed content)
- [ ] Test automatic HTTP to HTTPS redirect

### 6. Error Pages

- [ ] Verify custom error pages exist:
  - `public/403.php` (Forbidden)
  - `public/404.php` (Not Found)
  - `public/500.php` (Internal Server Error)
- [ ] Test each error page displays correctly
- [ ] Confirm error pages don't leak sensitive information
- [ ] Verify error pages match site design/branding

### 7. API Keys

- [ ] Create at least one API key via Settings page
- [ ] Document API key securely (use password manager)
- [ ] Test API authentication: `curl -H "X-API-Key: your-key" https://site.com/api.php`
- [ ] Verify disabled API keys are rejected
- [ ] Test rate limiting (60 requests/min per key)
- [ ] Configure monitoring for API failures

---

## Post-Deployment Verification

### 1. Health Checks

- [ ] Access health check endpoint: `curl https://yoursite.com/health.php`
- [ ] Verify response is: `{"status":"ok","timestamp":"..."}`
- [ ] Test database connectivity through health check
- [ ] Set up external monitoring (UptimeRobot, Pingdom, etc.)
- [ ] Configure alerts for health check failures

### 2. Functionality Testing

- [ ] Create a test feed via web UI
- [ ] Verify feed appears in feeds list
- [ ] Trigger manual processing (if available)
- [ ] Check articles are created successfully
- [ ] View article detail page
- [ ] Edit article metadata
- [ ] Test article deletion
- [ ] Verify RSS feed generation: `/feed.php?topic=test`
- [ ] Test feed caching (check response times)

### 3. API Testing

- [ ] Test API feed processing:
  ```bash
  curl -X POST https://yoursite.com/api.php \
    -H "X-API-Key: your-api-key"
  ```
- [ ] Verify response includes statistics:
  - `feeds_processed`
  - `articles_created`
  - `articles_failed`
- [ ] Check articles are created in database
- [ ] Review logs for any errors
- [ ] Test API rate limiting (make 61 requests rapidly)

### 4. Performance Verification

- [ ] Run performance tests: `composer test:performance`
- [ ] Review performance report: `docs/PERFORMANCE-REPORT.md`
- [ ] Verify all requirements met:
  - Article list page < 2 seconds
  - RSS feed generation < 1 second
  - Cached RSS feed < 100ms
  - Memory usage < 256MB
- [ ] Test with realistic data volume (100+ articles)
- [ ] Check database query performance: `EXPLAIN SELECT ...`
- [ ] Verify indexes are being used: `php scripts/verify-indexes.php`

### 5. Error Handling

- [ ] Test invalid URLs/routes return 404
- [ ] Test restricted access returns 403
- [ ] Verify database errors show 500 page
- [ ] Check all errors are logged properly
- [ ] Confirm error details not exposed in production
- [ ] Test retry queue for failed articles
- [ ] Verify exponential backoff works correctly

---

## Cron Job Setup

### 1. Feed Processing

Schedule regular feed processing using cron:

```bash
# Edit crontab
crontab -e

# Add this line (daily at 9 AM):
0 9 * * * curl -X POST -H "X-API-Key: YOUR_KEY_HERE" https://yoursite.com/api.php > /dev/null 2>&1

# Or use wget:
0 9 * * * wget --quiet --method=POST --header="X-API-Key: YOUR_KEY_HERE" https://yoursite.com/api.php -O /dev/null
```

**Checklist:**
- [ ] Add cron job for feed processing
- [ ] Test cron job runs successfully
- [ ] Verify output/logs are created
- [ ] Check articles are being created
- [ ] Monitor for failures

### 2. Data Cleanup (Optional)

If auto cleanup is enabled (`RETENTION_AUTO_CLEANUP=true`):

```bash
# Daily cleanup at 2 AM
0 2 * * * php /path/to/unfurl/scripts/cleanup.php > /dev/null 2>&1
```

**Checklist:**
- [ ] Create cleanup script (if not exists)
- [ ] Add cron job for cleanup
- [ ] Test cleanup deletes old records
- [ ] Verify foreign keys prevent orphan records
- [ ] Monitor cleanup execution

### 3. Health Check Monitoring

```bash
# Every 5 minutes
*/5 * * * * curl -f https://yoursite.com/health.php || echo "Health check failed" | mail -s "Unfurl Health Alert" admin@example.com
```

**Checklist:**
- [ ] Add health check monitoring cron
- [ ] Test alert email sends on failure
- [ ] Configure external monitoring service
- [ ] Set up escalation procedures

---

## Monitoring Setup

### 1. Application Monitoring

- [ ] Set up external uptime monitoring (UptimeRobot, Pingdom)
- [ ] Configure health check alerts
- [ ] Set up error rate monitoring
- [ ] Configure slow query alerts
- [ ] Monitor disk space usage
- [ ] Track API call success rates

### 2. Log Monitoring

- [ ] Set up log aggregation (if available)
- [ ] Configure alerts for ERROR and CRITICAL logs
- [ ] Monitor for repeated warnings
- [ ] Track API authentication failures
- [ ] Review logs daily for anomalies
- [ ] Set up log rotation

### 3. Performance Monitoring

- [ ] Monitor RSS feed response times
- [ ] Track database query performance
- [ ] Monitor memory usage trends
- [ ] Check processing queue depth
- [ ] Track retry queue growth
- [ ] Monitor article success/failure rates

### 4. Security Monitoring

- [ ] Monitor for failed API key attempts
- [ ] Track rate limit violations
- [ ] Watch for unusual traffic patterns
- [ ] Monitor for SQL injection attempts
- [ ] Check for unauthorized access attempts
- [ ] Review security headers regularly

---

## Backup Procedures

### 1. Database Backups

**Daily Automated Backups:**
```bash
#!/bin/bash
# backup-database.sh
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/path/to/backups"
DB_NAME="unfurl_db"
DB_USER="unfurl_user"
DB_PASS="your_password"

mysqldump -u $DB_USER -p$DB_PASS $DB_NAME | gzip > $BACKUP_DIR/unfurl_$DATE.sql.gz

# Delete backups older than 30 days
find $BACKUP_DIR -name "unfurl_*.sql.gz" -mtime +30 -delete
```

**Checklist:**
- [ ] Create backup script
- [ ] Add to cron (daily at 1 AM):
  ```bash
  0 1 * * * /path/to/backup-database.sh > /dev/null 2>&1
  ```
- [ ] Test backup creation
- [ ] Test restore procedure
- [ ] Verify backup integrity
- [ ] Store backups off-server (cloud storage)
- [ ] Document restore procedure

### 2. File Backups

**Weekly Full Backup:**
```bash
#!/bin/bash
# backup-files.sh
DATE=$(date +%Y%m%d)
BACKUP_DIR="/path/to/backups"
APP_DIR="/path/to/unfurl"

tar -czf $BACKUP_DIR/unfurl_files_$DATE.tar.gz \
  --exclude='storage/temp' \
  --exclude='vendor' \
  --exclude='.git' \
  $APP_DIR

# Delete backups older than 60 days
find $BACKUP_DIR -name "unfurl_files_*.tar.gz" -mtime +60 -delete
```

**Checklist:**
- [ ] Create file backup script
- [ ] Add to cron (weekly, Sunday 2 AM):
  ```bash
  0 2 * * 0 /path/to/backup-files.sh > /dev/null 2>&1
  ```
- [ ] Test file backup creation
- [ ] Test restore procedure
- [ ] Store backups off-server
- [ ] Document restore procedure

### 3. Backup Testing

- [ ] Schedule monthly restore tests
- [ ] Document restore procedures
- [ ] Test restoring to clean environment
- [ ] Verify data integrity after restore
- [ ] Time restore process (RTO - Recovery Time Objective)
- [ ] Measure data loss window (RPO - Recovery Point Objective)

---

## Security Hardening

### 1. Web Server Hardening

- [ ] Disable directory listings (in `.htaccess`)
- [ ] Block access to sensitive files (`.env`, `composer.json`, etc.)
- [ ] Remove server version disclosure
- [ ] Disable unnecessary HTTP methods
- [ ] Configure request size limits
- [ ] Set up fail2ban for repeated failures
- [ ] Enable ModSecurity (if available)

### 2. PHP Hardening

- [ ] Disable `expose_php` in php.ini
- [ ] Disable dangerous functions:
  ```ini
  disable_functions = exec,passthru,shell_exec,system,proc_open,popen
  ```
- [ ] Set `open_basedir` restriction
- [ ] Disable `allow_url_fopen` (if not needed)
- [ ] Set appropriate `memory_limit`
- [ ] Configure `max_execution_time`
- [ ] Set `post_max_size` and `upload_max_filesize`

### 3. Database Hardening

- [ ] Use dedicated database user (not root)
- [ ] Grant minimal required permissions
- [ ] Disable remote database access (if not needed)
- [ ] Use strong database password (20+ chars)
- [ ] Enable MySQL slow query log
- [ ] Set up database firewall rules
- [ ] Regular security updates for MySQL/MariaDB

### 4. Application Hardening

- [ ] Verify all user input is validated
- [ ] Confirm all output is escaped
- [ ] Check CSRF protection on all forms
- [ ] Verify SSRF protection is active
- [ ] Test SQL injection protection
- [ ] Verify XSS prevention
- [ ] Check rate limiting works
- [ ] Test retry queue security

---

## Performance Optimization

### 1. Database Optimization

- [ ] Run `ANALYZE TABLE` on all tables
- [ ] Verify all indexes are optimal: `php scripts/verify-indexes.php`
- [ ] Check for slow queries: Review slow query log
- [ ] Optimize table structure if needed: `OPTIMIZE TABLE articles;`
- [ ] Configure MySQL query cache (if beneficial)
- [ ] Set appropriate `innodb_buffer_pool_size`
- [ ] Monitor database connections

### 2. Caching

- [ ] Verify RSS feed caching works (5 min cache)
- [ ] Test cache hit rates
- [ ] Configure opcache for PHP (if not enabled):
  ```ini
  opcache.enable=1
  opcache.memory_consumption=128
  opcache.interned_strings_buffer=8
  opcache.max_accelerated_files=10000
  opcache.revalidate_freq=60
  ```
- [ ] Consider Redis/Memcached for session storage (optional)

### 3. Asset Optimization

- [ ] Verify compression is enabled (gzip/deflate)
- [ ] Check cache headers on static assets (1 year)
- [ ] Optimize images (use WebP if supported)
- [ ] Minify CSS/JS (if applicable)
- [ ] Use CDN for static assets (optional)

### 4. PHP Optimization

- [ ] Enable OpCache
- [ ] Tune `realpath_cache_size`
- [ ] Set appropriate `memory_limit` (256M recommended)
- [ ] Configure `max_input_vars` for large forms
- [ ] Monitor PHP-FPM pool size (if using)

---

## Documentation

### 1. Internal Documentation

- [ ] Document all configuration changes
- [ ] Record database credentials (securely)
- [ ] Document API keys and their purposes
- [ ] Note any customizations made
- [ ] Document cron job schedules
- [ ] Record monitoring setup details
- [ ] Document backup/restore procedures

### 2. Operational Documentation

- [ ] Create runbook for common tasks
- [ ] Document incident response procedures
- [ ] Write troubleshooting guide
- [ ] Document rollback procedures
- [ ] Create maintenance windows schedule
- [ ] Document escalation procedures

### 3. User Documentation

- [ ] Provide user guide for feed management
- [ ] Document API usage with examples
- [ ] Create RSS feed integration guide
- [ ] Document settings and their effects
- [ ] Provide FAQ for common issues

---

## Launch Preparation

### Final Pre-Launch Checks

- [ ] Run full test suite: `composer test`
- [ ] Run performance tests: `composer test:performance`
- [ ] Verify all production credentials are set
- [ ] Confirm backups are working
- [ ] Test disaster recovery plan
- [ ] Verify monitoring is active
- [ ] Check all cron jobs are scheduled
- [ ] Review all logs for warnings/errors
- [ ] Test from external network
- [ ] Verify error pages work correctly

### Launch Day

- [ ] Deploy code to production
- [ ] Run database migrations (if any)
- [ ] Verify health check returns OK
- [ ] Test feed creation and processing
- [ ] Monitor logs for first few hours
- [ ] Check performance metrics
- [ ] Verify backups run successfully
- [ ] Test all critical paths
- [ ] Monitor error rates
- [ ] Be available for issues

### Post-Launch (Week 1)

- [ ] Daily log reviews
- [ ] Monitor performance trends
- [ ] Check backup integrity
- [ ] Review security logs
- [ ] Track error rates
- [ ] Monitor resource usage
- [ ] Collect user feedback
- [ ] Address any issues promptly
- [ ] Update documentation as needed
- [ ] Schedule first maintenance window

---

## Maintenance Schedule

### Daily

- Review error logs
- Check health check status
- Monitor API success rates
- Verify backups completed
- Check disk space

### Weekly

- Review performance metrics
- Check retry queue status
- Review security logs
- Update dependencies (if needed)
- Review article processing stats

### Monthly

- Test backup restore procedure
- Review and optimize database
- Security audit
- Performance review
- Capacity planning review
- Update documentation

### Quarterly

- Full security review
- Disaster recovery test
- Performance optimization review
- Dependency updates
- Infrastructure review

---

## Support & Troubleshooting

### Common Issues

**Database Connection Failures:**
- Check database credentials in `.env`
- Verify database server is running
- Check firewall rules
- Review database error logs

**Feed Processing Failures:**
- Check API key is valid
- Verify network connectivity
- Review article retry queue
- Check for rate limiting
- Review error logs

**Performance Issues:**
- Run `php scripts/verify-indexes.php`
- Check slow query log
- Monitor memory usage
- Review caching configuration
- Check for long-running queries

**Security Concerns:**
- Review security headers
- Check for failed authentication attempts
- Monitor for unusual traffic
- Review rate limit violations
- Check for SQL injection attempts

### Getting Help

- Review `CLAUDE.md` for development patterns
- Check `docs/` directory for detailed documentation
- Review error logs in `storage/logs/`
- Run health check: `curl https://yoursite.com/health.php`
- Check database indexes: `php scripts/verify-indexes.php`

---

## Sign-Off

Once all items are checked and verified:

- [ ] All checklist items completed
- [ ] Production environment tested and verified
- [ ] Documentation complete and accurate
- [ ] Team trained on operational procedures
- [ ] Monitoring and alerts configured
- [ ] Backup and recovery tested
- [ ] Security hardening complete
- [ ] Performance requirements met

**Deployed By:** ___________________
**Date:** ___________________
**Verified By:** ___________________
**Date:** ___________________

---

**Production deployment is complete! Monitor closely for the first 24-48 hours.**
