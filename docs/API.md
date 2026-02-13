# API Documentation

Complete API reference for Unfurl - Google News URL Decoder & RSS Feed Generator.

## Table of Contents

- [Overview](#overview)
- [Authentication](#authentication)
- [Rate Limiting](#rate-limiting)
- [Endpoints](#endpoints)
- [Response Formats](#response-formats)
- [Error Codes](#error-codes)
- [Usage Examples](#usage-examples)
- [RSS Feeds](#rss-feeds)
- [Best Practices](#best-practices)

## Overview

Unfurl provides a RESTful API for processing Google News feeds and generating RSS feeds.

**Base URL**: `https://yoursite.com/unfurl/`

**API Endpoints**:
- `POST /api.php` - Process all enabled feeds (cron/scheduled processing)
- `POST /api.php?action=fetch&feed_id={id}` - Fetch articles from a single feed
- `POST /api.php?action=process&id={id}` - Process a single article
- `GET /health.php` - Health check
- `GET /feed.php` - RSS feed generation (no auth required)

**Features**:
- Simple REST API
- API key authentication
- Rate limiting (60 requests/minute)
- JSON responses
- Standards-compliant RSS 2.0 feeds

## Authentication

### API Keys

API keys are required for processing endpoints. Create API keys via the Settings page in the web interface.

**Header Format**:
```
X-API-Key: your-api-key-here
```

### Creating API Keys

1. Log in to web interface
2. Navigate to Settings page
3. Click "Create API Key"
4. Enter name and description
5. Save key value (shown only once!)

**Key Properties**:
- **Key Value**: 64-character hexadecimal string
- **Name**: Human-readable identifier
- **Description**: Purpose/usage notes
- **Enabled**: Can be disabled without deletion
- **Last Used**: Tracks most recent usage

### Example Request

```bash
curl -X POST https://yoursite.com/unfurl/api.php \
  -H "X-API-Key: 1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef"
```

### Security

- API keys are hashed in database
- Keys transmitted over HTTPS only
- Rate limiting prevents abuse
- Keys can be revoked instantly
- Usage tracked for audit

## Rate Limiting

**Limit**: 60 requests per minute per API key

**Implementation**: Sliding window

**Headers**: No rate limit headers in response (may be added in future)

**When Exceeded**:
```json
{
  "success": false,
  "error": "Rate limit exceeded. Please try again later.",
  "timestamp": "2026-02-07T15:30:00Z"
}
```

**HTTP Status**: 429 Too Many Requests

**Reset**: Wait 60 seconds from first request in window

## Endpoints

### POST /api.php - Process Feeds

Process all enabled Google News feeds and extract articles.

**Authentication**: Required (X-API-Key header)

**Method**: POST

**Parameters**: None

**Request**:
```bash
curl -X POST https://yoursite.com/unfurl/api.php \
  -H "X-API-Key: YOUR_API_KEY"
```

**Success Response** (200 OK):
```json
{
  "success": true,
  "feeds_processed": 3,
  "articles_created": 25,
  "articles_failed": 2,
  "timestamp": "2026-02-07T15:30:00Z"
}
```

**Response Fields**:
- `success` - Boolean indicating overall success
- `feeds_processed` - Number of feeds processed
- `articles_created` - New articles successfully created
- `articles_failed` - Articles that failed processing
- `timestamp` - ISO 8601 timestamp

**Error Responses**:

**401 Unauthorized** - Missing API key:
```json
{
  "success": false,
  "error": "Missing X-API-Key header",
  "timestamp": "2026-02-07T15:30:00Z"
}
```

**401 Unauthorized** - Invalid API key:
```json
{
  "success": false,
  "error": "Invalid API key",
  "timestamp": "2026-02-07T15:30:00Z"
}
```

**403 Forbidden** - Disabled API key:
```json
{
  "success": false,
  "error": "API key is disabled",
  "timestamp": "2026-02-07T15:30:00Z"
}
```

**429 Too Many Requests** - Rate limit exceeded:
```json
{
  "success": false,
  "error": "Rate limit exceeded. Please try again later.",
  "timestamp": "2026-02-07T15:30:00Z"
}
```

**500 Internal Server Error** - Processing error:
```json
{
  "success": false,
  "error": "An error occurred while processing your request",
  "timestamp": "2026-02-07T15:30:00Z"
}
```

### POST /api.php?action=fetch - Fetch Feed Articles

Fetch article list from a Google News RSS feed without processing them. Used for real-time progress tracking in web UI.

**Authentication**: CSRF token required (session-based, not API key)

**Method**: POST

**Parameters**:
- `action=fetch` (query string)
- `feed_id` (query string) - Feed ID to fetch articles from

**Request Body**:
```json
{
  "csrf_token": "your-csrf-token"
}
```

**Request Example**:
```javascript
fetch('/api.php?action=fetch&feed_id=1', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json'
    },
    body: JSON.stringify({
        csrf_token: csrfToken
    })
})
```

**Success Response** (200 OK):
```json
{
  "success": true,
  "articles": [
    {
      "google_news_url": "https://news.google.com/rss/articles/...",
      "title": "Article Title",
      "description": "Article description",
      "pub_date": "2026-02-07 10:00:00"
    }
  ],
  "articles_count": 15
}
```

**Error Responses**:
- **403 Forbidden** - Invalid CSRF token
- **404 Not Found** - Feed not found
- **500 Internal Server Error** - Feed fetch failed

**Usage**:
1. User clicks "Process Feed" button
2. Frontend calls this endpoint to get article list
3. CSRF token validated once at this step
4. Frontend then processes articles individually using `/api.php?action=process`

---

### POST /api.php?action=process - Process Single Article

Process a single article (decode URL, extract metadata, save to database). Used for sequential processing with real-time progress updates.

**Authentication**: None required (CSRF validated at fetch step)

**Method**: POST

**Parameters**:
- `action=process` (query string)
- `id` (query string) - Article ID to process

**Request Example**:
```javascript
fetch('/api.php?action=process&id=123', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json'
    }
})
```

**Success Response** (200 OK):
```json
{
  "success": true,
  "article": {
    "id": 123,
    "title": "Processed Article Title",
    "final_url": "https://example.com/article",
    "status": "success",
    "word_count": 1250
  }
}
```

**Error Response**:
```json
{
  "success": false,
  "error": "Failed to decode URL: timeout"
}
```

**Processing Flow**:
1. Decode Google News URL to get final URL
2. Check for duplicates (by final_url)
3. Fetch article HTML
4. Extract metadata (title, description, images, etc.)
5. Save to database
6. Return processed article data

**Error Handling**:
- Errors returned as JSON (success: false)
- Article marked as failed in database
- Added to retry queue if error is retryable
- Processing continues for other articles

**CSRF Note**: This endpoint does NOT require CSRF token because:
- Token validated once during fetch operation
- Prevents token expiration during long sequential processing
- Article processing is idempotent (safe to retry)
- No sensitive operations performed

---

### GET /health.php - Health Check

Check if application and database are accessible.

**Authentication**: None required

**Method**: GET

**Parameters**: None

**Request**:
```bash
curl https://yoursite.com/unfurl/health.php
```

**Success Response** (200 OK):
```json
{
  "status": "ok",
  "timestamp": "2026-02-07T15:30:00Z"
}
```

**Error Response** (503 Service Unavailable):
```json
{
  "status": "error",
  "timestamp": "2026-02-07T15:30:00Z"
}
```

**Use Cases**:
- Monitoring uptime
- Load balancer health checks
- Deployment verification
- Automated alerting

### GET /feed.php - RSS Feed

Generate RSS 2.0 feed with article content.

**Authentication**: None required (public endpoint)

**Method**: GET

**Parameters**:

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `topic` | string | No | Filter by topic |
| `feed_id` | integer | No | Filter by feed ID |
| `limit` | integer | No | Results per page (default: 20, max: 100) |
| `offset` | integer | No | Pagination offset (default: 0) |
| `status` | string | No | Filter by status (pending, success, failed) |

**Request**:
```bash
# All articles
curl https://yoursite.com/unfurl/feed.php

# Filter by topic
curl https://yoursite.com/unfurl/feed.php?topic=technology

# Pagination
curl https://yoursite.com/unfurl/feed.php?topic=technology&limit=10&offset=20

# Filter by status
curl https://yoursite.com/unfurl/feed.php?status=success
```

**Response**: RSS 2.0 XML

**Headers**:
```
Content-Type: application/rss+xml; charset=utf-8
Cache-Control: public, max-age=300
ETag: "hash-of-content"
Last-Modified: Thu, 07 Feb 2026 15:30:00 GMT
```

**Caching**: 5-minute cache with ETag support

**Example Response**:
```xml
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0" xmlns:content="http://purl.org/rss/1.0/modules/content/">
  <channel>
    <title>Unfurl - Google News Articles</title>
    <link>https://yoursite.com/unfurl/</link>
    <description>Decoded Google News articles with full content</description>
    <lastBuildDate>Thu, 07 Feb 2026 15:30:00 +0000</lastBuildDate>

    <item>
      <title>Article Title Here</title>
      <link>https://original-article-url.com</link>
      <description>Brief description or excerpt</description>
      <pubDate>Thu, 07 Feb 2026 14:00:00 +0000</pubDate>
      <guid>https://original-article-url.com</guid>
      <category>Technology</category>
      <author>Article Author</author>
      <content:encoded><![CDATA[
        Full article text content here.
        Plain text format, HTML tags removed.
      ]]></content:encoded>
      <enclosure url="https://image-url.com/image.jpg" type="image/jpeg"/>
    </item>

    <!-- More items... -->
  </channel>
</rss>
```

**RSS Elements**:

**Channel Level**:
- `<title>` - Feed title
- `<link>` - Feed URL
- `<description>` - Feed description
- `<lastBuildDate>` - When feed was generated

**Item Level**:
- `<title>` - Article title (RSS title or page title)
- `<link>` - Original article URL (final_url)
- `<description>` - Article description/excerpt
- `<pubDate>` - Publication date (RFC 822 format)
- `<guid>` - Unique identifier (article URL)
- `<category>` - Article categories/tags
- `<author>` - Article author
- `<content:encoded>` - Full article text (CDATA wrapped)
- `<enclosure>` - Featured image (if available)

## Response Formats

### JSON Responses

All API endpoints (except RSS feed) return JSON.

**Success Response Structure**:
```json
{
  "success": true,
  "data": {},
  "timestamp": "2026-02-07T15:30:00Z"
}
```

**Error Response Structure**:
```json
{
  "success": false,
  "error": "Error message",
  "timestamp": "2026-02-07T15:30:00Z"
}
```

**Timestamps**: ISO 8601 format (UTC timezone)

### RSS Responses

RSS feeds follow RSS 2.0 specification with extensions:

- **content:encoded** - Full article content
- **dc:creator** - Dublin Core creator field (future)

**MIME Type**: `application/rss+xml`

**Character Encoding**: UTF-8

**CDATA Wrapping**: Used for content with special characters

## Error Codes

| Code | Status | Meaning |
|------|--------|---------|
| 200 | OK | Request successful |
| 400 | Bad Request | Invalid parameters |
| 401 | Unauthorized | Missing or invalid API key |
| 403 | Forbidden | API key disabled |
| 429 | Too Many Requests | Rate limit exceeded |
| 500 | Internal Server Error | Server error occurred |
| 503 | Service Unavailable | Database unavailable |

### Error Response Details

**Missing Authentication**:
- Code: 401
- Message: "Missing X-API-Key header"
- Action: Add X-API-Key header to request

**Invalid API Key**:
- Code: 401
- Message: "Invalid API key"
- Action: Verify API key value is correct

**Disabled API Key**:
- Code: 403
- Message: "API key is disabled"
- Action: Enable key in Settings or create new key

**Rate Limited**:
- Code: 429
- Message: "Rate limit exceeded. Please try again later."
- Action: Wait 60 seconds before retrying

**Server Error**:
- Code: 500
- Message: "An error occurred while processing your request"
- Action: Check logs, contact administrator if persists

## Usage Examples

### cURL

**Process Feeds**:
```bash
curl -X POST https://yoursite.com/unfurl/api.php \
  -H "X-API-Key: YOUR_API_KEY" \
  -H "Content-Type: application/json"
```

**Get RSS Feed**:
```bash
curl https://yoursite.com/unfurl/feed.php?topic=technology
```

**Health Check**:
```bash
curl https://yoursite.com/unfurl/health.php
```

### PHP

**Process Feeds**:
```php
$apiKey = 'YOUR_API_KEY';
$url = 'https://yoursite.com/unfurl/api.php';

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'X-API-Key: ' . $apiKey,
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode($response, true);

if ($httpCode === 200 && $data['success']) {
    echo "Processed {$data['feeds_processed']} feeds\n";
    echo "Created {$data['articles_created']} articles\n";
    echo "Failed {$data['articles_failed']} articles\n";
} else {
    echo "Error: {$data['error']}\n";
}
```

**Get RSS Feed**:
```php
$topic = 'technology';
$url = "https://yoursite.com/unfurl/feed.php?topic=" . urlencode($topic);

$rss = file_get_contents($url);
$xml = simplexml_load_string($rss);

foreach ($xml->channel->item as $item) {
    echo $item->title . "\n";
    echo $item->link . "\n";
    echo $item->description . "\n\n";
}
```

### Python

**Process Feeds**:
```python
import requests

api_key = 'YOUR_API_KEY'
url = 'https://yoursite.com/unfurl/api.php'

headers = {
    'X-API-Key': api_key,
    'Content-Type': 'application/json'
}

response = requests.post(url, headers=headers)
data = response.json()

if response.status_code == 200 and data['success']:
    print(f"Processed {data['feeds_processed']} feeds")
    print(f"Created {data['articles_created']} articles")
    print(f"Failed {data['articles_failed']} articles")
else:
    print(f"Error: {data['error']}")
```

**Parse RSS Feed**:
```python
import feedparser

url = 'https://yoursite.com/unfurl/feed.php?topic=technology'
feed = feedparser.parse(url)

for entry in feed.entries:
    print(entry.title)
    print(entry.link)
    print(entry.summary)
    print(entry.content[0].value)  # Full content
    print()
```

### JavaScript (Node.js)

**Process Feeds**:
```javascript
const fetch = require('node-fetch');

const apiKey = 'YOUR_API_KEY';
const url = 'https://yoursite.com/unfurl/api.php';

fetch(url, {
  method: 'POST',
  headers: {
    'X-API-Key': apiKey,
    'Content-Type': 'application/json'
  }
})
.then(res => res.json())
.then(data => {
  if (data.success) {
    console.log(`Processed ${data.feeds_processed} feeds`);
    console.log(`Created ${data.articles_created} articles`);
    console.log(`Failed ${data.articles_failed} articles`);
  } else {
    console.error(`Error: ${data.error}`);
  }
})
.catch(err => console.error(err));
```

### Cron Jobs

**Daily processing at 9 AM**:
```bash
0 9 * * * curl -X POST -H "X-API-Key: YOUR_KEY" https://yoursite.com/unfurl/api.php >/dev/null 2>&1
```

**Every 6 hours**:
```bash
0 */6 * * * curl -X POST -H "X-API-Key: YOUR_KEY" https://yoursite.com/unfurl/api.php >/dev/null 2>&1
```

**Health check every 5 minutes**:
```bash
*/5 * * * * curl https://yoursite.com/unfurl/health.php >/dev/null 2>&1
```

## RSS Feeds

### Consuming RSS Feeds

**RSS Readers**:
- Feedly: Add feed URL
- Inoreader: Subscribe to feed
- NewsBlur: Import feed
- RSS readers: Use feed URL

**Programmatic Access**:
```php
// PHP
$feed = simplexml_load_file('https://yoursite.com/unfurl/feed.php');

// Python
import feedparser
feed = feedparser.parse('https://yoursite.com/unfurl/feed.php')

// JavaScript
const Parser = require('rss-parser');
const parser = new Parser();
const feed = await parser.parseURL('https://yoursite.com/unfurl/feed.php');
```

### Feed Discovery

Add to HTML `<head>` for auto-discovery:
```html
<link rel="alternate" type="application/rss+xml"
      title="Technology News"
      href="https://yoursite.com/unfurl/feed.php?topic=technology">
```

### Caching

**Client-Side Caching**:
- Respect `Cache-Control` headers
- Use `ETag` for conditional requests
- Check `Last-Modified` header

**Conditional Request**:
```bash
curl https://yoursite.com/unfurl/feed.php \
  -H "If-None-Modified-Since: Thu, 07 Feb 2026 15:00:00 GMT"

# Returns 304 Not Modified if unchanged
```

**ETag Request**:
```bash
curl https://yoursite.com/unfurl/feed.php \
  -H "If-None-Match: \"etag-value-here\""

# Returns 304 Not Modified if unchanged
```

### Feed Validation

Validate RSS feeds:
- [W3C Feed Validator](https://validator.w3.org/feed/)
- [RSS Board Validator](http://www.rssboard.org/rss-validator/)

## Best Practices

### API Keys

1. **Keep keys secure** - Never commit to git, share publicly, or log
2. **Use environment variables** - Store keys in `.env` files
3. **Rotate regularly** - Create new keys, delete old ones
4. **One key per purpose** - Separate keys for cron, development, third-party
5. **Monitor usage** - Check `last_used_at` for suspicious activity

### Rate Limiting

1. **Respect limits** - Don't exceed 60 requests/minute
2. **Implement backoff** - Wait and retry on 429 errors
3. **Use cron wisely** - Schedule processing during off-peak hours
4. **Monitor rate** - Track request frequency
5. **Request increase** - Contact admin if limits too low

### Error Handling

1. **Check HTTP status** - Don't assume 200 OK
2. **Parse JSON safely** - Handle invalid responses
3. **Log errors** - Track failures for debugging
4. **Retry transient errors** - 500, 503 may be temporary
5. **Don't retry permanent errors** - 401, 403 require fixing

### Performance

1. **Use caching** - Respect cache headers
2. **Batch requests** - Process multiple feeds in one call
3. **Off-peak processing** - Schedule cron during low traffic
4. **Monitor response times** - Alert on slow responses
5. **Pagination** - Use limit/offset for large result sets

### Security

1. **Always use HTTPS** - Never send keys over HTTP
2. **Validate responses** - Don't trust external data
3. **Sanitize output** - Escape HTML/XML when displaying
4. **Keep credentials secure** - Use secrets management
5. **Monitor logs** - Watch for unauthorized access

### RSS Feeds

1. **Poll responsibly** - Check feed every 15-60 minutes max
2. **Use conditional requests** - Send ETag/Last-Modified
3. **Respect cache headers** - Honor Cache-Control
4. **Handle errors gracefully** - Feed may be temporarily unavailable
5. **Validate XML** - Parse carefully, handle malformed feeds

## Webhooks (Future Feature)

Webhooks are not currently implemented but planned for future release:

**Planned Events**:
- `feed.processed` - Feed processing completed
- `article.created` - New article created
- `article.failed` - Article processing failed

**Configuration** (future):
```json
{
  "url": "https://your-app.com/webhook",
  "events": ["article.created"],
  "secret": "webhook-secret-key"
}
```

## Support

### Getting Help

1. Check this documentation
2. Review [INSTALLATION.md](INSTALLATION.md) for setup issues
3. See [CLAUDE.md](CLAUDE.md) for troubleshooting
4. Open GitHub issue with:
   - API endpoint used
   - Request/response (redact API key!)
   - Error message
   - Expected behavior

### Reporting Issues

When reporting API issues:

1. **Redact API keys** - Never share actual keys
2. **Include request** - Method, headers, parameters
3. **Include response** - Status code, body, headers
4. **Steps to reproduce** - How to trigger the issue
5. **Expected behavior** - What should happen

### Feature Requests

To request API features:

1. Open GitHub issue
2. Describe use case
3. Provide example usage
4. Explain expected behavior

---

**Last Updated**: 2026-02-07
**Version**: 1.0.0
**API Version**: 1
