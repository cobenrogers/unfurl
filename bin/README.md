# Unfurl CLI Tools

Command-line scripts for automated article processing and production synchronization.

## Overview

The CLI tools enable a local processing → production sync workflow:

1. **Local Machine**: Processes articles with Node.js/Playwright
2. **Automated Sync**: Pushes processed data to production server
3. **Production Server**: Receives and stores articles (no Node.js needed)

---

## Scripts

### 1. `process-articles.php`

Processes RSS feeds and extracts article metadata.

**Usage:**
```bash
# Process all enabled feeds
php bin/process-articles.php

# Process specific feed
php bin/process-articles.php --feed-id=1

# Verbose output
php bin/process-articles.php --verbose

# Show help
php bin/process-articles.php --help
```

**What it does:**
- Fetches enabled RSS feeds
- Processes with configured processor (Node.js or PHP)
- Extracts article metadata and content
- Saves to local database with `sync_pending = 1`
- Logs all activity

**Cron setup (every 30 minutes):**
```bash
*/30 * * * * php /path/to/unfurl/bin/process-articles.php
```

---

### 2. `sync-to-production.php`

Syncs locally processed articles to production server via API.

**Usage:**
```bash
# Sync all pending articles
php bin/sync-to-production.php

# Custom batch size
php bin/sync-to-production.php --batch=50

# Verbose output
php bin/sync-to-production.php --verbose

# Dry run (preview without syncing)
php bin/sync-to-production.php --dry-run

# Show help
php bin/sync-to-production.php --help
```

**What it does:**
- Queries local database for articles with `sync_pending = 1`
- Batches articles (default: 100 per batch)
- POSTs to production API endpoint
- Marks successfully synced articles as `sync_pending = 0`
- Handles errors with detailed logging

**Cron setup (every hour):**
```bash
0 * * * * php /path/to/unfurl/bin/sync-to-production.php
```

---

## Setup

### 1. Local Environment Configuration

Add to your local `.env` file:

```env
# Article processor (use 'node' for Playwright browser automation)
ARTICLE_PROCESSOR=node

# Production sync configuration
PRODUCTION_URL=https://unfurl.bennernet.com
PRODUCTION_API_KEY=your_production_api_key_here
```

### 2. Database Migration (Local Only)

Run the sync tracking migration:

```bash
mysql -u root -p unfurl_db < sql/migrations/2026-02-13_add_sync_tracking.sql
```

This adds:
- `sync_pending` TINYINT(1) - Whether article needs syncing
- `synced_at` TIMESTAMP - When article was synced
- Index on `sync_pending` for query performance

### 3. Production API Key

On your production server:

1. Go to Settings page
2. Create new API key (e.g., "Local Sync")
3. Copy the full API key (shown only once)
4. Add to local `.env` as `PRODUCTION_API_KEY`

### 4. Set Up Cron Jobs

Edit your crontab (`crontab -e`):

```bash
# Process articles every 30 minutes
*/30 * * * * php /path/to/unfurl/bin/process-articles.php >> /var/log/unfurl-process.log 2>&1

# Sync to production every hour
0 * * * * php /path/to/unfurl/bin/sync-to-production.php >> /var/log/unfurl-sync.log 2>&1
```

---

## Production API Endpoint

### `POST /api/sync/import`

Receives articles from local environment and imports to production database.

**Authentication:**
```
X-API-Key: your_production_api_key
```

**Request Body:**
```json
{
  "articles": [
    {
      "id": 1,
      "feed_id": 1,
      "topic": "Technology",
      "google_news_url": "https://news.google.com/...",
      "final_url": "https://example.com/article",
      "rss_title": "Article Title",
      ...
    }
  ]
}
```

**Response:**
```json
{
  "success": true,
  "imported": 25,
  "skipped": 2,
  "errors": []
}
```

**Error Responses:**
- `401` - Missing or invalid API key
- `400` - Invalid request body
- `500` - Server error

---

## Workflow

### Full Automated Flow

```
1. CRON TRIGGERS (every 30 min)
   └─> bin/process-articles.php
       └─> Fetch RSS feeds
       └─> Process with Node.js/Playwright
       └─> Save to LOCAL database
       └─> Mark as sync_pending = 1

2. CRON TRIGGERS (every hour)
   └─> bin/sync-to-production.php
       └─> Query pending articles
       └─> Batch into groups of 100
       └─> POST to PRODUCTION API
       └─> Mark as synced (sync_pending = 0)

3. PRODUCTION RECEIVES
   └─> /api/sync/import
       └─> Validate API key
       └─> Insert/update articles
       └─> Return success stats
```

### Manual Processing

```bash
# 1. Process specific feed
php bin/process-articles.php --feed-id=1 --verbose

# 2. Check pending count
mysql -u root -p unfurl_db -e "SELECT COUNT(*) FROM articles WHERE sync_pending = 1;"

# 3. Preview sync (dry run)
php bin/sync-to-production.php --dry-run --verbose

# 4. Actually sync
php bin/sync-to-production.php --verbose
```

---

## Monitoring

### Check Processing Logs

```bash
# View last 50 lines of processing log
tail -50 /var/log/unfurl-process.log

# View sync log
tail -50 /var/log/unfurl-sync.log

# Follow logs in real-time
tail -f /var/log/unfurl-process.log
tail -f /var/log/unfurl-sync.log
```

### Database Queries

```sql
-- Check pending sync count
SELECT COUNT(*) FROM articles WHERE sync_pending = 1;

-- View recent processed articles
SELECT id, rss_title, status, sync_pending, synced_at
FROM articles
ORDER BY created_at DESC
LIMIT 20;

-- Check sync statistics
SELECT
    COUNT(*) as total,
    SUM(sync_pending) as pending,
    COUNT(*) - SUM(sync_pending) as synced
FROM articles;
```

---

## Troubleshooting

### "No articles pending sync"

- Check if processing script ran successfully
- Verify `sync_pending` column exists
- Check cron job logs

### "Missing production configuration"

Add to local `.env`:
```env
PRODUCTION_URL=https://unfurl.bennernet.com
PRODUCTION_API_KEY=your_key_here
```

### Sync fails with 401 error

- Verify API key is correct
- Check API key is enabled in production
- Ensure `X-API-Key` header is being sent

### Articles not processing

- Check Node.js is installed: `node --version`
- Verify ARTICLE_PROCESSOR=node in `.env`
- Check processing logs for errors

---

## Security Notes

1. **API Key Protection**: Never commit `PRODUCTION_API_KEY` to git
2. **HTTPS Required**: Production should use HTTPS for API calls
3. **Rate Limiting**: Production API has rate limiting (60 req/min)
4. **Logs**: Sensitive data is not logged (only IDs and counts)

---

## Architecture Benefits

✅ **Local Processing**: Full Node.js/Playwright capabilities
✅ **Simple Production**: No Node.js needed on shared hosting
✅ **Automated**: Set and forget with cron jobs
✅ **Manual Override**: Can trigger processing/sync anytime
✅ **Resilient**: Retries on failure, detailed error logging
✅ **Scalable**: Batch processing prevents overwhelming server

---

## Examples

### Process and Sync Immediately

```bash
# Process all feeds
php bin/process-articles.php --verbose

# Sync immediately (don't wait for cron)
php bin/sync-to-production.php --verbose
```

### Process Single Feed with Dry-Run Sync

```bash
# Process one feed
php bin/process-articles.php --feed-id=1 --verbose

# Preview what would be synced
php bin/sync-to-production.php --dry-run --verbose
```

### Check Status

```bash
# Count pending
mysql -u root -p unfurl_db -e "SELECT COUNT(*) FROM articles WHERE sync_pending = 1;"

# View recent
mysql -u root -p unfurl_db -e "SELECT id, rss_title, created_at, synced_at FROM articles ORDER BY id DESC LIMIT 10;"
```

---

For more information, see the main project documentation in `/docs`.
