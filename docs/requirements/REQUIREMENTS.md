# Unfurl - Requirements Document

**Project:** Unfurl - Google News Feed Processor
**Version:** 0.2.0
**Date:** 2026-02-07
**Status:** Draft - In Review

---

## 1. Project Overview

### 1.1 Purpose
Unfurl is a web-based application that processes Google News RSS feeds to extract actual article URLs and metadata, bypassing Google's obfuscated redirect URLs.

### 1.2 Problem Statement
- Google News RSS feeds contain obfuscated article URLs that don't redirect properly when accessed programmatically
- Traditional URL decoding approaches are fragile and break when Google changes their encoding
- Need a reliable way to extract actual article sources, metadata, and featured images
- Must be accessible from anywhere (desktop, tablet, mobile) without local software installation

### 1.3 Solution Approach
- Web-based application (accessible via browser on any device)
- Server-side URL resolution using Google's batchexecute API or browser automation
- Database storage for persistent article management
- Scheduled processing for automation
- Simple, intuitive UI for configuration and management

---

## 2. Goals & Objectives

### 2.1 Primary Goals
1. Extract actual article URLs from Google News feeds
2. Capture article metadata (title, description, images, author)
3. Support multiple feed configurations
4. Provide web-based access from any device
5. Enable both manual and scheduled processing

### 2.2 Success Criteria
- Successfully decode 95%+ of Google News URLs
- Process feeds in < 5 seconds per article
- Accessible from iPad/mobile browsers
- Zero local software dependencies for users
- Reliable scheduled processing (cron)

### 2.3 Non-Goals (Out of Scope for v1)
- Content analysis or summarization
- Social media posting integration
- Multi-user authentication/authorization
- Article full-text extraction
- Advanced analytics/reporting

---

## 3. Technical Requirements

### 3.1 Hosting Environment
- **Platform:** Bluehost shared hosting (existing infrastructure)
- **Server:** PHP 7.4+ / 8.x
- **Database:** MySQL 5.7+ / 8.x
- **Access:** Web-based (HTTP/HTTPS)
- **Cron:** cPanel cron jobs available

### 3.2 Technology Stack
- **Backend:** PHP (native to Bluehost)
- **Database:** MySQL
- **Frontend:** HTML, CSS, JavaScript (vanilla or lightweight library)
- **URL Resolution:**
  - Primary: Google batchexecute API (no browser required)
  - Fallback: External headless browser service if needed
- **Scheduling:** cPanel cron jobs

### 3.3 Dependencies
- PHP cURL extension (for HTTP requests)
- MySQL PDO/mysqli
- JSON functions (native to PHP)
- No external packages required for core functionality

### 3.4 Browser Automation Constraints
- **Issue:** Bluehost shared hosting cannot run headless browsers (Chrome/Chromium)
- **Solution:** Use Google's batchexecute API for URL decoding (no browser needed)
- **Fallback:** If API fails, consider external service (Browserless.io, etc.)

---

## 4. Functional Requirements

### 4.1 Feed Configuration Management

#### 4.1.1 Create Feed
- **Actor:** User
- **Input:**
  - Topic name (string, max 255 chars)
  - Google News RSS URL (valid URL)
  - Result limit (integer, 1-100)
  - Enabled status (boolean)
- **Validation:**
  - Topic name required, unique
  - URL must be valid Google News RSS format
  - Result limit must be positive integer
- **Output:** New feed created in database
- **Error Handling:** Display validation errors, prevent duplicates

#### 4.1.2 Edit Feed
- **Actor:** User
- **Input:** Feed ID + updated fields (topic, URL, limit, enabled)
- **Validation:** Same as create
- **Output:** Feed updated in database
- **Side Effects:** None (existing articles remain linked)

#### 4.1.3 Delete Feed
- **Actor:** User
- **Input:** Feed ID
- **Confirmation:** "Delete feed and all associated articles?"
- **Output:** Feed and all related articles removed
- **Implementation:** CASCADE delete on foreign key

#### 4.1.4 View Feeds
- **Actor:** User
- **Display:** List of all feeds with:
  - Topic name
  - URL (truncated)
  - Article count
  - Last processed timestamp
  - Enabled/disabled status
  - Quick actions (Edit, Run Now, Delete)

### 4.2 Feed Processing

#### 4.2.1 Manual Processing
- **Actor:** User
- **Trigger:** Click "Process Feeds" button
- **Selection:** Can select which feeds to process (default: all enabled)
- **Process:**
  1. Fetch RSS feed from Google News
  2. Parse XML to extract article entries
  3. For each article (up to feed's result_limit):
     - Decode Google News URL using batchexecute API
     - Fetch final article page
     - Extract metadata (og:image, og:title, etc.)
     - Store in database
  4. Update feed's last_processed_at timestamp
- **Output:**
  - Progress indicator during processing
  - Summary: X articles processed, Y successful, Z failed
  - Redirect to articles view

#### 4.2.2 Scheduled Processing
- **Actor:** Cron job
- **Trigger:** Scheduled time (configurable)
- **Process:**
  1. Call API endpoint with API key (X-API-Key header)
  2. Process all enabled feeds
  3. For each feed:
     - Fetch RSS feed
     - Parse articles
     - Process each article individually and sequentially
     - Update progress in real-time (via API)
  4. Log results to database
- **Output:**
  - Articles stored in database
  - JSON response with summary statistics
  - Email notification (optional, future feature)
- **Implementation Note:** Articles are processed individually (not in batch) to provide real-time progress tracking and better error handling. See `/api/feeds/fetch` and `/api/articles/process/{id}` endpoints.

#### 4.2.3 Processing Logic
**For each article:**
```
1. Decode Google News URL to get final URL
   - Extract article ID from Google News URL
   - Call batchexecute API
   - Parse response to get actual article URL
   - If fails: Mark as failed, store error, skip to next article

2. Check for duplicates (by final_url)
   - Query database for existing article with same final_url
   - If exists:
     * Skip processing (already have this article)
     * Log: "Duplicate skipped: {final_url}"
     * Continue to next article
   - If new: Continue processing

3. Fetch article page (final URL)
   - Use cURL with proper headers
   - Follow redirects
   - Get HTML content

4. Extract metadata and content
   - Parse HTML for meta tags
   - Extract metadata: og:image, og:title, og:description, og:url, og:site_name
   - Extract additional: twitter:image, author
   - Extract: page title
   - Extract categories/tags (if available in meta or article)
   - Extract full article content:
     * Strip all HTML tags (keep text only)
     * Remove scripts, styles, navigation
     * Clean whitespace
     * Store as plain text (for AI processing)
   - Calculate word count

5. Store in database
   - Create article record
   - Link to feed_id
   - Store all metadata
   - Store article content (plain text)
   - Store categories/tags
   - Mark status as 'success'

6. Error handling
   - Any step fails: Mark status as 'failed'
   - Store error message
   - Continue to next article
```

#### 4.2.4 Duplicate Article Handling

**Problem:** When processing feeds multiple times (daily, weekly, etc.), the same articles appear in the RSS feed repeatedly. We must avoid re-processing and storing duplicates.

**Strategy:**
- **Duplicate Detection:** Check if an article with the same `final_url` already exists in the database
- **Detection Point:** After decoding the Google News URL to get the final URL, before fetching the article content
- **Action on Duplicate:** Skip processing, log as duplicate, continue to next article
- **Efficiency:** Use indexed `final_url` column for fast lookups

**Important Considerations:**

1. **Why final_url?**
   - Google News generates new obfuscated URLs each time the RSS feed is fetched
   - Same article will have different `google_news_url` values on different days
   - The `final_url` (actual article URL) remains constant and uniquely identifies the article

2. **What about deleted articles?**
   - If a user deletes an article from the database, it becomes available for re-processing
   - Next time the feed runs, the deleted article will be processed again (since it's no longer in the database)
   - This is intentional behavior - allows users to re-capture articles if needed

3. **What about articles in multiple feeds?**
   - If the same article appears in two different feed topics, only the first one processed is stored
   - Subsequent feeds skip it as a duplicate
   - **Alternative:** Could link articles to multiple feeds (many-to-many relationship)
   - **Current design:** One article, one feed (simpler, avoids complexity)

4. **Performance:**
   - Duplicate check is a single indexed query: `SELECT id FROM articles WHERE final_url = ?`
   - Index on `final_url(500)` ensures fast lookups even with millions of articles
   - Check happens before expensive operations (fetching HTML, parsing content)

**Example Flow:**
```
Day 1 Processing:
- Article A (final_url: example.com/article-1) → Not in DB → Process & Store ✓
- Article B (final_url: example.com/article-2) → Not in DB → Process & Store ✓

Day 2 Processing (same feed):
- Article A (final_url: example.com/article-1) → Already in DB → Skip 🔁
- Article B (final_url: example.com/article-2) → Already in DB → Skip 🔁
- Article C (final_url: example.com/article-3) → Not in DB → Process & Store ✓

User deletes Article A from database

Day 3 Processing:
- Article A (final_url: example.com/article-1) → Not in DB → Process & Store ✓
- Article B (final_url: example.com/article-2) → Already in DB → Skip 🔁
- Article C (final_url: example.com/article-3) → Already in DB → Skip 🔁
```

**Logging:**
```php
// When duplicate detected
Logger::processing('INFO', 'Duplicate article skipped', [
    'final_url' => $finalUrl,
    'existing_article_id' => $existingArticle['id'],
    'feed_id' => $feedId
]);
```

**Processing Summary Output:**
```
Feed: IBD Research
Total articles in RSS: 25
  - New articles processed: 3
  - Duplicates skipped: 22
  - Errors: 0
Processing time: 45 seconds
```

#### 4.2.5 Error Recovery & Retry Logic

**Requirement:** Failed article processing must support automatic retry with exponential backoff.

**Failure Categories:**

1. **Temporary Failures (Retryable):**
   - Network timeouts
   - HTTP 429 (Rate Limited)
   - HTTP 502/503/504 (Server errors)
   - DNS resolution failures
   - Connection timeouts

2. **Permanent Failures (Not Retryable):**
   - HTTP 404 (Not Found)
   - HTTP 403 (Forbidden)
   - Invalid URL format
   - SSRF validation failures
   - Malformed HTML (no parseable content)

**Retry Strategy:**

```php
class RetryableException extends Exception {
    public function __construct(
        string $message,
        private int $retryAfter = 0
    ) {
        parent::__construct($message);
    }

    public function getRetryAfter(): int {
        return $this->retryAfter;
    }
}

class ProcessingError {
    public const RETRYABLE_CODES = [
        'TIMEOUT',
        'NETWORK_ERROR',
        'RATE_LIMIT',
        'SERVER_ERROR_5XX',
        'DNS_FAILURE',
    ];

    public function __construct(
        private string $code,
        private string $message,
        private int $httpStatus = 0
    ) {}

    public function isRetryable(): bool {
        return in_array($this->code, self::RETRYABLE_CODES)
            || ($this->httpStatus >= 500 && $this->httpStatus < 600)
            || $this->httpStatus === 429;
    }
}
```

**Implementation:**

```php
function processArticleWithRetry(array $article, int $attemptNumber = 1): void {
    const MAX_ATTEMPTS = 3;

    try {
        // Attempt processing
        $result = processArticle($article);

        // Success - mark as complete
        markArticleAsSuccess($article['id'], $result);

    } catch (RetryableException $e) {
        if ($attemptNumber < MAX_ATTEMPTS) {
            // Calculate backoff delay (exponential: 60s, 120s, 240s)
            $delay = pow(2, $attemptNumber - 1) * 60;

            // Add jitter (random 0-10s) to prevent thundering herd
            $delay += rand(0, 10);

            // Schedule retry
            scheduleRetry($article['id'], $delay, $attemptNumber + 1);

            Logger::processing('WARNING', 'Article processing failed - will retry', [
                'article_id' => $article['id'],
                'attempt' => $attemptNumber,
                'retry_after' => $delay,
                'error' => $e->getMessage()
            ]);

        } else {
            // Max attempts reached - mark as permanently failed
            markArticleAsFailed($article['id'], $e->getMessage(), [
                'attempts' => $attemptNumber,
                'last_error' => $e->getMessage()
            ]);

            Logger::processing('ERROR', 'Article processing failed after max retries', [
                'article_id' => $article['id'],
                'attempts' => $attemptNumber,
                'error' => $e->getMessage()
            ]);
        }

    } catch (Exception $e) {
        // Permanent failure - don't retry
        markArticleAsFailed($article['id'], $e->getMessage());

        Logger::processing('ERROR', 'Article processing failed (permanent)', [
            'article_id' => $article['id'],
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }
}
```

**Database Schema for Retry Tracking:**

```sql
ALTER TABLE articles ADD COLUMN retry_count INT DEFAULT 0;
ALTER TABLE articles ADD COLUMN next_retry_at TIMESTAMP NULL;
ALTER TABLE articles ADD COLUMN last_error TEXT NULL;
```

**Retry Queue Processing:**

```php
// In cron job
function processFailedArticles(): void {
    $articles = getArticlesReadyForRetry();

    foreach ($articles as $article) {
        processArticleWithRetry(
            $article,
            $article['retry_count'] + 1
        );
    }
}

function getArticlesReadyForRetry(): array {
    global $db;

    return $db->query("
        SELECT *
        FROM articles
        WHERE status = 'failed'
          AND retry_count < 3
          AND (next_retry_at IS NULL OR next_retry_at <= NOW())
        ORDER BY next_retry_at ASC
        LIMIT 20
    ")->fetchAll();
}
```

**Manual Retry (UI):**

```php
// User can manually retry failed articles
function retryArticle(int $article_id): void {
    $article = getArticleById($article_id);

    if ($article['status'] !== 'failed') {
        throw new Exception('Can only retry failed articles');
    }

    // Reset retry count for manual retry
    $db->prepare("
        UPDATE articles
        SET status = 'pending',
            retry_count = 0,
            next_retry_at = NULL,
            last_error = NULL
        WHERE id = ?
    ")->execute([$article_id]);

    Logger::processing('INFO', 'Article manually retried', [
        'article_id' => $article_id,
        'user_action' => true
    ]);

    // Process immediately or queue
    processArticleWithRetry($article, 1);
}
```

**Bulk Retry (UI):**

```html
<!-- Articles page - bulk actions -->
<form method="POST" action="/articles/bulk-retry">
    <?php echo csrfField(); ?>
    <input type="hidden" name="article_ids" value="[1,2,3,4,5]">
    <button type="submit" class="btn-secondary">
        Retry Selected Failed Articles
    </button>
</form>
```

**Rate Limiting Integration:**

```php
function processArticle(array $article): array {
    // Check if we're hitting rate limits
    if (isRateLimited()) {
        throw new RetryableException(
            'Rate limit reached',
            retryAfter: 300 // 5 minutes
        );
    }

    // Decode URL
    try {
        $finalUrl = decodeGoogleNewsUrl($article['google_news_url']);
    } catch (GoogleApiException $e) {
        if ($e->getCode() === 429) {
            throw new RetryableException(
                'Google API rate limited',
                retryAfter: 3600 // 1 hour
            );
        }
        throw $e;
    }

    // Continue processing...
}
```

### 4.3 Article Management

#### 4.3.1 View Articles
- **Actor:** User
- **Display:** Paginated list of articles
- **Columns:**
  - Checkbox (for bulk selection)
  - Topic/Feed name
  - Article title (truncated)
  - Source domain
  - Image status (✓/✗)
  - Processing status (success/failed/pending)
  - Processed date
  - Actions (View, Edit, Delete)
- **Pagination:** 20 articles per page
- **Sorting:** By processed_at DESC (newest first)

#### 4.3.2 Filter Articles
- **By Topic:** Dropdown list of all feed topics (+ "All Topics")
- **By Status:** success, failed, pending, all
- **By Date Range:** Start date + End date (optional)
- **Clear Filters:** Reset to default view

#### 4.3.3 Search Articles
- **Search Fields:** title, description, source, author
- **Implementation:** SQL LIKE query across multiple columns
- **Case insensitive:** Convert to lowercase for matching
- **Minimum length:** 3 characters to search
- **Clear Search:** Reset to unfiltered view

#### 4.3.4 View Article Details
- **Actor:** User
- **Trigger:** Click "View" button
- **Display:** Modal or separate page showing:
  - **Original RSS Data:**
    - RSS title
    - Google News URL
    - Publication date
  - **Resolved Article:**
    - Final URL (clickable link)
    - Site name
    - Author
  - **Metadata:**
    - Page title
    - OG title
    - OG description
    - OG URL
    - OG site name
  - **Featured Image:**
    - Image preview (if available)
    - Image URL (copyable)
    - Link to open in new tab
  - **Processing Info:**
    - Status
    - Processed timestamp
    - Error message (if failed)
- **Actions:** Edit, Delete, Close

#### 4.3.5 Edit Article
- **Actor:** User
- **Trigger:** Click "Edit" button
- **Editable Fields:**
  - Topic/Feed (dropdown to reassign)
  - Article title
  - Final URL
  - Image URL
  - Description
  - Author
  - Site name
  - Status (pending/success/failed)
- **Validation:**
  - URLs must be valid format
  - Topic must exist
- **Save:** Update database, show success message
- **Cancel:** Discard changes, close modal

#### 4.3.6 Delete Article
- **Actor:** User
- **Trigger:** Click "Delete" button
- **Confirmation:** "Delete this article?"
- **Output:** Remove from database, refresh list

#### 4.3.7 Bulk Actions
- **Select All:** Checkbox to select all visible articles
- **Select Individual:** Checkboxes per article
- **Bulk Delete:**
  - Button enabled when 1+ selected
  - Confirmation: "Delete X selected articles?"
  - Delete all selected, refresh list

#### 4.3.8 Retry Failed Articles
- **Actor:** User
- **Trigger:** Click "Retry" button on failed article
- **Process:** Re-run processing logic for that article
- **Update:** Status, metadata, error message
- **Output:** Success/failure message

### 4.4 API Key Management

#### 4.4.1 Purpose
Support multiple API keys for different purposes:
- Separate keys for different cron jobs
- Separate keys for different projects (e.g., SNAM, other integrations)
- Test keys that can be easily disabled
- Track usage per key

#### 4.4.2 Create API Key
- **Actor:** User (admin)
- **Trigger:** Click "Add New Key" on Settings page
- **Input:**
  - Key name (required, max 255 chars)
  - Description (optional, text)
  - Enabled status (default: true)
- **Process:**
  1. Generate random 32-character key (cryptographically secure)
  2. Store in database with name and description
  3. Display key to user (one-time only)
- **Output:** Modal showing new key with copy button
- **Validation:**
  - Key name required and unique
  - Auto-generate key (don't allow user input)

#### 4.4.3 View API Keys
- **Actor:** User
- **Display:** List of all API keys
- **Show:**
  - Key name
  - Key value (first 8 chars + "...")
  - Description
  - Created date
  - Last used timestamp
  - Enabled/disabled status
- **Actions:** Show full key, Edit, Delete, Enable/Disable

#### 4.4.4 Show Full API Key
- **Actor:** User
- **Trigger:** Click "Show" button
- **Display:** Modal with full key value
- **Security:** Require confirmation or re-authentication (future)
- **Include:** Copy button for convenience

#### 4.4.5 Edit API Key
- **Actor:** User
- **Editable:**
  - Key name
  - Description
  - Enabled status
- **NOT Editable:**
  - Key value (cannot change, only regenerate)
  - Created date
  - Last used

#### 4.4.6 Delete API Key
- **Actor:** User
- **Confirmation:** "Delete this API key? Any services using it will stop working."
- **Process:** Remove from database
- **Side Effects:** Future API calls with this key will fail

#### 4.4.7 Enable/Disable API Key
- **Actor:** User
- **Purpose:** Temporarily disable key without deleting it
- **Process:** Toggle `enabled` flag
- **Use Case:** Disable compromised or unused keys

#### 4.4.8 Track Key Usage
- **Automatic:** Update `last_used_at` on every API call
- **Display:** Show on Settings page
- **Monitoring:** Identify unused keys (last used > 30 days)
- **Logs:** Record which key was used in API logs

### 4.5 RSS Feed Generation

#### 4.4.1 Purpose
Unfurl generates clean RSS feeds from stored articles, allowing users to:
- Subscribe in RSS readers
- Filter by topic
- Get articles with actual URLs (not Google News redirects)
- Include full article text for offline reading
- Use in other applications/integrations

#### 4.4.2 RSS Feed Endpoints

**All Articles Feed:**
```
GET /feed.php
GET /rss.xml
```

**Topic-Specific Feed:**
```
GET /feed.php?topic=IBD+Research
GET /feed.php?topic=Crohns+Disease
```

**Feed by Feed ID:**
```
GET /feed.php?feed_id=1
```

#### 4.4.3 RSS Feed Format (RSS 2.0)
```xml
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0" xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:dc="http://purl.org/dc/elements/1.1/">
  <channel>
    <title>Unfurl - [Topic Name]</title>
    <link>https://yoursite.com/unfurl/</link>
    <description>Curated articles about [Topic]</description>
    <language>en-us</language>
    <lastBuildDate>Fri, 07 Feb 2026 12:00:00 GMT</lastBuildDate>
    <generator>Unfurl v1.0</generator>

    <item>
      <title>New Hope for Long-Term Crohn's Remission</title>
      <link>https://beingpatient.com/inflammatory-bowel-disease-ibd-may-accelerate-dementia/</link>
      <description><![CDATA[Is your gut fast-tracking memory loss? A Swedish registry hints IBD can accelerate decline once dementia starts.]]></description>
      <content:encoded><![CDATA[Full article text here... stripped of HTML, plain text]]></content:encoded>
      <pubDate>Fri, 06 Feb 2026 06:01:29 GMT</pubDate>
      <guid isPermaLink="true">https://beingpatient.com/inflammatory-bowel-disease-ibd-may-accelerate-dementia/</guid>
      <dc:creator>Being Patient</dc:creator>
      <category>IBD Research</category>
      <category>Dementia</category>
      <enclosure url="https://beingpatient.com/wp-content/uploads/2026/01/IBS.jpg" type="image/jpeg" />
    </item>

    <!-- More items... -->

  </channel>
</rss>
```

#### 4.4.4 RSS Feed Fields

**Channel (Feed) Level:**
- `title`: "Unfurl - {Topic Name}" or "Unfurl - All Articles"
- `link`: Base URL of Unfurl installation
- `description`: Feed description based on topic
- `language`: en-us (configurable)
- `lastBuildDate`: Timestamp of most recent article
- `generator`: "Unfurl v1.0"

**Item (Article) Level:**
- `title`: Article title (prefer og:title, fallback to page_title, RSS title)
- `link`: Final article URL (actual destination, not Google News)
- `description`: Article summary (og:description or RSS description)
- `content:encoded`: Full article content (plain text, CDATA wrapped)
- `pubDate`: Original publication date from RSS feed
- `guid`: Unique identifier (use final_url as permalink)
- `dc:creator`: Author name
- `category`: Topic name + extracted categories/tags
- `enclosure`: Featured image (og:image URL)

#### 4.4.5 Feed Options/Parameters

**Query Parameters:**
- `topic`: Filter by topic name (URL encoded)
- `feed_id`: Filter by feed ID
- `limit`: Number of articles (default: 20, max: 100)
- `offset`: Pagination offset (default: 0)
- `status`: Filter by status (default: success only)

**Examples:**
```
/feed.php?topic=IBD+Research&limit=50
/feed.php?feed_id=1&limit=10
/feed.php?limit=100
```

#### 4.4.6 Feed Caching
- Cache RSS XML for 5 minutes
- Invalidate cache when new articles added
- Return 304 Not Modified if no changes (ETag/Last-Modified headers)

#### 4.4.7 Feed Discovery
Add auto-discovery link to HTML pages:
```html
<link rel="alternate" type="application/rss+xml"
      title="Unfurl - All Articles"
      href="https://yoursite.com/unfurl/feed.php" />
<link rel="alternate" type="application/rss+xml"
      title="Unfurl - IBD Research"
      href="https://yoursite.com/unfurl/feed.php?topic=IBD+Research" />
```

#### 4.4.8 Feed Management Page
Add to UI:
- List of available feeds with RSS URLs
- Copy RSS URL button
- Preview feed (show XML or formatted)
- Subscription instructions
- Feed validator link

---

## 5. User Interface Requirements

### 5.1 Visual Identity & Design System

#### 5.1.1 Theme Concept: "Unfolding Revelation"
The interface should reflect the act of revealing/unwrapping hidden information - unfolding URLs to discover their true destinations.

**Design Principles:**
- **Progressive disclosure**: Information reveals itself as needed
- **Clarity over decoration**: Clean, purposeful design
- **Trustworthy**: Professional appearance for research tool
- **Delightful**: Subtle animations that enhance understanding

#### 5.1.2 Color Palette

**Primary Colors:**
```css
:root {
  /* Primary: Deep teal suggesting depth/revelation */
  --color-primary: #0D7377;
  --color-primary-light: #14FFEC;
  --color-primary-dark: #053B3E;

  /* Accent: Warm amber for "aha!" moments */
  --color-accent: #F4A261;
  --color-accent-light: #F6BD8B;

  /* Neutrals: Clean slate for content */
  --color-bg: #FAFAFA;
  --color-surface: #FFFFFF;
  --color-text: #1A1A1A;
  --color-text-muted: #6B6B6B;
  --color-border: #E5E5E5;
  --color-border-light: #F0F0F0;

  /* Status colors */
  --color-success: #2A9D8F;
  --color-warning: #E9C46A;
  --color-error: #E76F51;
  --color-info: #4A90E2;
}
```

**Contrast Requirements:**
- All text must meet WCAG 2.1 AA standards (4.5:1 minimum)
- Interactive elements: 3:1 minimum
- Color is never the only indicator of state

#### 5.1.3 Typography System

**Font Families:**
```css
:root {
  --font-display: 'Space Grotesk', sans-serif;  /* Headings, emphasis */
  --font-body: 'Inter', system-ui, sans-serif;  /* Body text, UI */
  --font-mono: 'JetBrains Mono', monospace;     /* URLs, code, API keys */
}
```

**Font Loading:**
```html
<!-- Preload critical fonts -->
<link rel="preload" href="/fonts/SpaceGrotesk-Bold.woff2" as="font" crossorigin>
<link rel="preload" href="/fonts/Inter-Regular.woff2" as="font" crossorigin>

<!-- Google Fonts with display=swap -->
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;600;700&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
```

**Type Scale:**
```css
/* Headings */
h1, .text-h1 {
  font-family: var(--font-display);
  font-size: 2.5rem;      /* 40px */
  font-weight: 700;
  line-height: 1.2;
  letter-spacing: -0.02em;
}

h2, .text-h2 {
  font-family: var(--font-display);
  font-size: 2rem;        /* 32px */
  font-weight: 600;
  line-height: 1.3;
  letter-spacing: -0.01em;
}

h3, .text-h3 {
  font-family: var(--font-display);
  font-size: 1.5rem;      /* 24px */
  font-weight: 600;
  line-height: 1.4;
}

/* Body text */
body, .text-body {
  font-family: var(--font-body);
  font-size: 1rem;        /* 16px */
  line-height: 1.6;
  font-weight: 400;
}

.text-small {
  font-size: 0.875rem;    /* 14px */
  line-height: 1.5;
}

.text-large {
  font-size: 1.125rem;    /* 18px */
  line-height: 1.6;
}

/* Monospace (technical data) */
.text-mono, code, pre {
  font-family: var(--font-mono);
  font-size: 0.875rem;
  line-height: 1.6;
}
```

#### 5.1.4 Spacing System

**Based on 4px increments:**
```css
:root {
  --space-1: 0.25rem;   /* 4px */
  --space-2: 0.5rem;    /* 8px */
  --space-3: 0.75rem;   /* 12px */
  --space-4: 1rem;      /* 16px */
  --space-5: 1.5rem;    /* 24px */
  --space-6: 2rem;      /* 32px */
  --space-8: 3rem;      /* 48px */
  --space-10: 4rem;     /* 64px */
}
```

**Usage:**
- Tight spacing: 4-8px (within components)
- Standard spacing: 16-24px (between sections)
- Generous spacing: 32-64px (page sections)

#### 5.1.5 Border Radius

```css
:root {
  --radius-sm: 4px;     /* Small elements, badges */
  --radius-md: 8px;     /* Buttons, inputs, cards */
  --radius-lg: 12px;    /* Modals, large cards */
  --radius-full: 9999px; /* Pills, circular elements */
}
```

#### 5.1.6 Shadows

```css
:root {
  /* Elevation system */
  --shadow-sm: 0 1px 2px rgba(13, 115, 119, 0.05);
  --shadow-md: 0 4px 6px rgba(13, 115, 119, 0.08),
               0 1px 3px rgba(13, 115, 119, 0.04);
  --shadow-lg: 0 10px 15px rgba(13, 115, 119, 0.1),
               0 4px 6px rgba(13, 115, 119, 0.05);
  --shadow-xl: 0 20px 25px rgba(13, 115, 119, 0.12),
               0 8px 10px rgba(13, 115, 119, 0.06);
}
```

#### 5.1.7 Component Styles

**Buttons:**
```css
.btn {
  font-family: var(--font-body);
  font-weight: 500;
  padding: 0.75rem 1.5rem;
  border-radius: var(--radius-md);
  min-height: 44px; /* Touch target */
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.btn-primary {
  background: var(--color-primary);
  color: white;
  box-shadow: var(--shadow-sm);
}

.btn-primary:hover {
  background: var(--color-primary-dark);
  box-shadow: var(--shadow-md);
  transform: translateY(-1px);
}

.btn-secondary {
  background: transparent;
  border: 2px solid var(--color-primary);
  color: var(--color-primary);
}

.btn-danger {
  background: var(--color-error);
  color: white;
}
```

**Form Inputs:**
```css
.input-field {
  font-family: var(--font-body);
  font-size: 1rem;
  padding: 0.875rem;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  width: 100%;
  min-height: 44px;
  transition: border-color 0.2s, box-shadow 0.2s;
}

.input-field:focus {
  outline: none;
  border-color: var(--color-primary);
  box-shadow: 0 0 0 3px rgba(13, 115, 119, 0.1);
}

.input-field.error {
  border-color: var(--color-error);
}
```

**Status Badges:**
```css
.badge {
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
  padding: 0.25rem 0.75rem;
  border-radius: var(--radius-full);
  font-size: 0.875rem;
  font-weight: 500;
}

.badge.success {
  background: #D4F4DD;
  color: #1A6B3D;
}

.badge.warning {
  background: #FEF3C7;
  color: #92400E;
}

.badge.error {
  background: #FEE2E2;
  color: #991B1B;
}

/* Dot indicator */
.badge::before {
  content: '';
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: currentColor;
}
```

#### 5.1.8 Animation & Motion

**Timing Functions:**
```css
:root {
  --ease-in-out: cubic-bezier(0.4, 0, 0.2, 1);
  --ease-out: cubic-bezier(0, 0, 0.2, 1);
  --ease-in: cubic-bezier(0.4, 0, 1, 1);
}
```

**Durations:**
- Micro-interactions: 150-200ms
- Component transitions: 200-300ms
- Page transitions: 300-400ms

**Key Animations:**
```css
/* Unfurl reveal (page load) */
@keyframes unfurl {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.article-card {
  animation: unfurl 0.4s var(--ease-out) backwards;
}

/* Stagger children */
.article-card:nth-child(1) { animation-delay: 0.05s; }
.article-card:nth-child(2) { animation-delay: 0.1s; }
.article-card:nth-child(3) { animation-delay: 0.15s; }

/* Processing pulse */
@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.5; }
}

.processing {
  animation: pulse 2s var(--ease-in-out) infinite;
}

/* Skeleton shimmer */
@keyframes shimmer {
  0% { background-position: -200% 0; }
  100% { background-position: 200% 0; }
}

.skeleton {
  background: linear-gradient(
    90deg,
    var(--color-border-light) 25%,
    var(--color-border) 50%,
    var(--color-border-light) 75%
  );
  background-size: 200% 100%;
  animation: shimmer 1.5s ease-in-out infinite;
}
```

#### 5.1.9 Accessibility Requirements

**Keyboard Navigation:**
- All interactive elements focusable via Tab
- Logical tab order (top to bottom, left to right)
- Skip link to main content
- Escape closes modals/dropdowns
- Enter/Space activates buttons

**Focus Indicators:**
```css
*:focus-visible {
  outline: 3px solid var(--color-primary);
  outline-offset: 2px;
  border-radius: var(--radius-sm);
}

/* Skip to main content link */
.skip-link {
  position: absolute;
  top: -100px;
  left: var(--space-4);
  background: var(--color-primary);
  color: white;
  padding: var(--space-3) var(--space-4);
  border-radius: var(--radius-md);
  z-index: 9999;
}

.skip-link:focus {
  top: var(--space-4);
}
```

**Screen Reader Support:**
```html
<!-- Live regions for status updates -->
<div role="status" aria-live="polite" class="sr-only">
  Processing: 12 of 27 articles complete
</div>

<!-- Icon buttons need labels -->
<button aria-label="Delete article">
  <svg aria-hidden="true"><!-- icon --></svg>
</button>
```

**Color Contrast:**
- Text: Minimum 4.5:1
- Large text (18px+): Minimum 3:1
- Interactive elements: Minimum 3:1
- Never use color alone to convey information

### 5.2 Navigation
```
┌──────────────────────────────────────┐
│ Unfurl                    [Settings] │
├──────────────────────────────────────┤
│ [Feeds] [Articles] [Process] [About] │
└──────────────────────────────────────┘
```

### 5.3 Pages

#### 5.3.1 Feeds Page (`/feeds` or `/index.php`)
- List all configured feeds
- Add new feed button (prominent)
- Each feed shows: topic, URL, status, article count, last run
- Actions per feed: Edit, Run Now, Delete
- Responsive design (works on mobile)

#### 5.3.2 Articles Page (`/articles.php`)
- Primary interface for viewing/managing articles
- Filters and search at top
- Article list with inline actions
- Pagination controls at bottom
- Bulk action toolbar when items selected

#### 5.3.3 Process Page (`/process.php`) ✅ IMPLEMENTED
**Status:** Implemented with enhanced real-time progress tracking

- Select which feeds to process (checkboxes)
- "Process Selected Feeds" button
- **Real-time per-article progress indicator** (shows each article as it processes)
- Progress bar with percentage complete
- Live status updates (success/failed) for each article
- Results summary when complete
- Link to view processed articles

**Implementation Details:**
- Articles processed individually and sequentially (not in batch)
- AJAX calls to `/api/feeds/fetch` (get article list) and `/api/articles/process/{id}` (process one)
- Progress updates after each article completes
- Prevents timeout issues by breaking work into small chunks
- Better error handling - one failure doesn't stop entire batch
- User can see exactly which articles succeeded/failed in real-time

#### 5.3.4 Settings Page (`/settings.php`)
```
┌──────────────────────────────────────────────────────────┐
│ Settings                                                  │
├──────────────────────────────────────────────────────────┤
│ API Configuration                                         │
│ ┌────────────────────────────────────────────────────┐  │
│ │ API Endpoint:                                       │  │
│ │ https://yoursite.com/unfurl/api.php                │  │
│ │                                                     │  │
│ │ API Keys:                          [+ Add New Key] │  │
│ │                                                     │  │
│ │ ┌──────────────────────────────────────────────┐   │  │
│ │ │ ✓ Main Cron Job                              │   │  │
│ │ │ Key: abc123... Created: 2026-01-15           │   │  │
│ │ │ Last used: 2 hours ago                       │   │  │
│ │ │ [👁 Show] [✏️ Edit] [🗑️ Delete]               │   │  │
│ │ └──────────────────────────────────────────────┘   │  │
│ │                                                     │  │
│ │ ┌──────────────────────────────────────────────┐   │  │
│ │ │ ✓ SNAM Integration                           │   │  │
│ │ │ Key: xyz789... Created: 2026-02-01           │   │  │
│ │ │ Last used: Never                             │   │  │
│ │ │ [👁 Show] [✏️ Edit] [🗑️ Delete]               │   │  │
│ │ └──────────────────────────────────────────────┘   │  │
│ │                                                     │  │
│ │ ┌──────────────────────────────────────────────┐   │  │
│ │ │ ⊘ Test Key (Disabled)                        │   │  │
│ │ │ Key: test456... Created: 2026-01-20          │   │  │
│ │ │ Last used: 3 days ago                        │   │  │
│ │ │ [👁 Show] [✏️ Edit] [🗑️ Delete]               │   │  │
│ │ └──────────────────────────────────────────────┘   │  │
│ └────────────────────────────────────────────────────┘  │
│                                                          │
│ Scheduled Processing                                     │
│ ┌────────────────────────────────────────────────────┐  │
│ │ Status: ⚠️ Not Configured                           │  │
│ │ Last Run: Never                                     │  │
│ │                                                     │  │
│ │ [▶ Run Processing Now]                              │  │
│ │                                                     │  │
│ │ To enable automatic processing:                     │  │
│ │ → [📖 View cPanel Setup Instructions]               │  │
│ │ → [🔗 External Cron Service Setup]                  │  │
│ └────────────────────────────────────────────────────┘  │
│                                                          │
│ Data Retention                                           │
│ ┌────────────────────────────────────────────────────┐  │
│ │ Keep articles for: [90] days (0 = forever)         │  │
│ │ Keep logs for: [30] days                           │  │
│ │                                                     │  │
│ │ ☑ Enable automatic cleanup                         │  │
│ │                                                     │  │
│ │ Last cleanup: Never                                 │  │
│ │                                                     │  │
│ │ [🗑️ Run Cleanup Now]                                 │  │
│ │                                                     │  │
│ │ Automatic cleanup requires cron job:                │  │
│ │ → [📖 View Cleanup Cron Setup]                      │  │
│ └────────────────────────────────────────────────────┘  │
│                                                          │
│ Processing Options                                       │
│ ┌────────────────────────────────────────────────────┐  │
│ │ Timeout: [30] seconds per article                  │  │
│ │ Max retries: [3]                                    │  │
│ │ Retry delay: [60] seconds                          │  │
│ └────────────────────────────────────────────────────┘  │
│                                                          │
│ [Save Settings]                                          │
└──────────────────────────────────────────────────────────┘
```

**Note:** Scheduled processing and automatic cleanup require manual cron job setup in cPanel. The Settings page provides instructions and copy/paste commands, but cannot programmatically create cron jobs on shared hosting.

#### 5.3.5 Add/Edit API Key Modal
```
┌──────────────────────────────────────┐
│ Create API Key                 [✕]  │
├──────────────────────────────────────┤
│                                      │
│ Key Name: *                          │
│ [Main Cron Job_____________]         │
│                                      │
│ Description:                         │
│ [Used by daily cron job to____]     │
│ [process feeds automatically___]     │
│                                      │
│ Enabled:                             │
│ ☑ Active                             │
│                                      │
│ Generated Key:                       │
│ ┌────────────────────────────────┐  │
│ │ abc123xyz789def456ghi789jkl012 │  │
│ └────────────────────────────────┘  │
│ [📋 Copy Key]                        │
│                                      │
│ ⚠️ Save this key! It won't be shown │
│    again after you close this dialog│
│                                      │
│ [Cancel] [Create Key]                │
└──────────────────────────────────────┘
```

#### 5.3.6 Cron Setup Instructions Modal
```
┌────────────────────────────────────────────────────────┐
│ cPanel Cron Job Setup                            [✕]  │
├────────────────────────────────────────────────────────┤
│                                                        │
│ Step 1: Log into cPanel                               │
│ ────────────────────────────                          │
│ Go to your Bluehost cPanel dashboard                  │
│                                                        │
│ Step 2: Open Cron Jobs                                │
│ ────────────────────────────                          │
│ Navigate to: Advanced → Cron Jobs                     │
│                                                        │
│ Step 3: Add Processing Cron Job                       │
│ ────────────────────────────────────                  │
│ Common Settings: Daily (Once per day - 0 0 * * *)    │
│                                                        │
│ OR Custom Settings:                                    │
│   Minute: 0                                           │
│   Hour: 9    (9:00 AM)                                │
│   Day: *                                              │
│   Month: *                                            │
│   Weekday: *                                          │
│                                                        │
│ Command:                                              │
│ ┌────────────────────────────────────────────────┐   │
│ │ curl -X POST -d "secret=abc123xyz789" \        │   │
│ │   https://yoursite.com/unfurl/api.php          │   │
│ │   > /dev/null 2>&1                             │   │
│ └────────────────────────────────────────────────┘   │
│ [📋 Copy Command]                                      │
│                                                        │
│ Step 4: Add Cleanup Cron Job                          │
│ ────────────────────────────────────                  │
│ Common Settings: Daily (Once per day - 0 0 * * *)    │
│                                                        │
│ OR Custom Settings:                                    │
│   Minute: 0                                           │
│   Hour: 2    (2:00 AM)                                │
│   Day: *                                              │
│   Month: *                                            │
│   Weekday: *                                          │
│                                                        │
│ Command:                                              │
│ ┌────────────────────────────────────────────────┐   │
│ │ php /home/username/public_html/unfurl/         │   │
│ │   includes/cleanup.php > /dev/null 2>&1        │   │
│ └────────────────────────────────────────────────┘   │
│ [📋 Copy Command]                                      │
│                                                        │
│ ⚠️ Important: Replace "username" with your actual     │
│ cPanel username in the cleanup command!                │
│                                                        │
│ Step 5: Save & Verify                                 │
│ ────────────────────────────────                      │
│ • Click "Add New Cron Job" for each                   │
│ • You should see both jobs listed in cPanel           │
│ • Test by clicking "Run Processing Now" and check logs│
│                                                        │
│ [Close] [📺 Watch Video Tutorial]                      │
└────────────────────────────────────────────────────────┘
```

### 5.3 Responsive Design
- **Desktop:** Full layout with all columns
- **Tablet (iPad):** Condensed layout, card-based
- **Mobile:** Stacked layout, simplified actions
- **Minimum Width:** 320px (iPhone SE)

### 5.4 Visual Design
- **Color Scheme:** Clean, professional (blues/grays)
- **Typography:** Sans-serif, readable (16px base)
- **Icons:** Simple, recognizable (✓✗✏️🗑️👁)
- **Buttons:** Clear labels, obvious primary actions
- **Forms:** Labeled inputs, inline validation
- **Modals:** Overlay with backdrop, clear close button

### 5.5 User Feedback
- **Success Messages:** Green banner, auto-dismiss (3 seconds)
- **Error Messages:** Red banner, manual dismiss
- **Loading States:** Spinner/progress bar during processing
- **Confirmations:** Modal dialogs for destructive actions
- **Empty States:** Helpful message when no data ("No articles yet. Process a feed to get started.")

---

## 6. Database Requirements

### 6.1 Schema

#### 6.1.1 `feeds` Table
```sql
CREATE TABLE feeds (
    id INT AUTO_INCREMENT PRIMARY KEY,
    topic VARCHAR(255) NOT NULL UNIQUE,
    url TEXT NOT NULL,
    result_limit INT DEFAULT 10,
    enabled TINYINT(1) DEFAULT 1,
    last_processed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_enabled (enabled),
    INDEX idx_topic (topic)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### 6.1.2 `articles` Table
```sql
CREATE TABLE articles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    feed_id INT NOT NULL,
    topic VARCHAR(255) NOT NULL,

    -- Original RSS data
    google_news_url TEXT NOT NULL,
    rss_title TEXT,
    pub_date TIMESTAMP NULL,
    rss_description TEXT,
    rss_source VARCHAR(255),

    -- Resolved data
    final_url TEXT,
    status ENUM('pending', 'success', 'failed') DEFAULT 'pending',

    -- Metadata
    page_title TEXT,
    og_title TEXT,
    og_description TEXT,
    og_image TEXT,
    og_url TEXT,
    og_site_name VARCHAR(255),
    twitter_image TEXT,
    twitter_card VARCHAR(50),
    author VARCHAR(255),

    -- Article content
    article_content MEDIUMTEXT,  -- Plain text, HTML stripped
    word_count INT,
    categories TEXT,  -- JSON array of categories/tags

    -- Processing info
    error_message TEXT,
    processed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (feed_id) REFERENCES feeds(id) ON DELETE CASCADE,
    INDEX idx_feed_id (feed_id),
    INDEX idx_topic (topic),
    INDEX idx_status (status),
    INDEX idx_processed_at (processed_at),
    INDEX idx_google_news_url (google_news_url(255)),
    UNIQUE INDEX idx_final_url_unique (final_url(500)),  -- Prevent duplicate articles (race condition protection)
    FULLTEXT idx_search (rss_title, page_title, og_title, og_description, author)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### 6.1.3 `api_keys` Table
```sql
CREATE TABLE api_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    key_name VARCHAR(255) NOT NULL,
    key_value VARCHAR(64) NOT NULL UNIQUE,
    description TEXT,
    enabled TINYINT(1) DEFAULT 1,
    last_used_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_key_value (key_value),
    INDEX idx_enabled (enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Purpose:** Support multiple API keys for different projects/purposes.

**Fields:**
- `key_name`: Identifier (e.g., "Main Cron Job", "SNAM Integration", "Test Key")
- `key_value`: Actual secret key (SHA-256 hash recommended)
- `description`: Optional notes about what this key is for
- `enabled`: Can disable keys without deleting them
- `last_used_at`: Track when key was last used (for monitoring)

#### 6.1.4 `settings` Table
```sql
CREATE TABLE settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 6.2 Data Validation
- **URLs:** Validate format, max 2083 characters
- **Text Fields:** Sanitize for XSS prevention
- **Enums:** Validate against allowed values
- **Timestamps:** Store in UTC, display in local timezone

### 6.3 Data Integrity
- **Foreign Keys:** CASCADE delete articles when feed deleted
- **Unique Constraints:** Prevent duplicate topics, duplicate articles
- **NOT NULL:** Enforce required fields
- **Indexes:** Optimize common queries (filter by topic, status, date)

---

## 7. API Requirements

### 7.1 API Endpoint for Scheduled Processing

#### 7.1.1 Endpoint
```
POST /api.php
```

#### 7.1.2 Authentication
```
POST data: secret=YOUR_API_KEY
```

**Validation Process:**
```php
function validateApiKey($providedKey) {
    global $db;

    // Check if key exists and is enabled
    $stmt = $db->prepare("
        SELECT id, key_name
        FROM api_keys
        WHERE key_value = ? AND enabled = 1
    ");
    $stmt->execute([$providedKey]);
    $apiKey = $stmt->fetch();

    if (!$apiKey) {
        Logger::api('ERROR', 'Invalid or disabled API key', [
            'provided_key' => substr($providedKey, 0, 8) . '...',
            'ip' => $_SERVER['REMOTE_ADDR']
        ]);
        return false;
    }

    // Update last_used_at
    $db->prepare("
        UPDATE api_keys
        SET last_used_at = NOW()
        WHERE id = ?
    ")->execute([$apiKey['id']]);

    // Log successful authentication
    Logger::api('INFO', 'API key validated', [
        'key_name' => $apiKey['key_name'],
        'key_id' => $apiKey['id']
    ]);

    return $apiKey;
}
```

#### 7.1.3 Request
```bash
curl -X POST \
  -d "secret=abc123xyz789" \
  https://yoursite.com/unfurl/api.php
```

#### 7.1.4 Response (JSON)
```json
{
  "success": true,
  "message": "Processed 3 feeds",
  "results": [
    {
      "feed_id": 1,
      "topic": "IBD Research",
      "articles_processed": 10,
      "successful": 9,
      "failed": 1
    },
    {
      "feed_id": 2,
      "topic": "Crohn's Disease",
      "articles_processed": 5,
      "successful": 5,
      "failed": 0
    }
  ],
  "timestamp": "2026-02-07T12:00:00Z"
}
```

#### 7.1.5 Error Response
```json
{
  "success": false,
  "error": "Invalid secret key",
  "timestamp": "2026-02-07T12:00:00Z"
}
```

### 7.2 Database Security

#### 7.2.1 Prepared Statements (REQUIRED)
**All database queries MUST use prepared statements to prevent SQL injection.**

```php
// ❌ NEVER do this - SQL injection vulnerability
$topic = $_POST['topic'];
$sql = "SELECT * FROM feeds WHERE topic = '$topic'";
$result = $db->query($sql);

// ✅ ALWAYS use prepared statements
$topic = $_POST['topic'];
$stmt = $db->prepare("SELECT * FROM feeds WHERE topic = ?");
$stmt->execute([$topic]);
$result = $stmt->fetchAll();

// ✅ Or with named parameters
$stmt = $db->prepare("SELECT * FROM feeds WHERE topic = :topic AND enabled = :enabled");
$stmt->execute([
    'topic' => $topic,
    'enabled' => 1
]);
```

**Requirements:**
- Use PDO with prepared statements for ALL queries
- NEVER concatenate or interpolate user input into SQL
- Use parameter binding with appropriate type hints (`PDO::PARAM_*`)
- Validate data types before binding (integers, booleans, etc.)

#### 7.2.2 Duplicate Key Error Handling
**Handle UNIQUE constraint violations gracefully:**

```php
try {
    $stmt = $db->prepare("
        INSERT INTO articles (feed_id, final_url, rss_title, ...)
        VALUES (?, ?, ?, ...)
    ");
    $stmt->execute([$feed_id, $final_url, $title, ...]);
} catch (PDOException $e) {
    // Check if duplicate entry error (SQLSTATE 23000)
    if ($e->getCode() === '23000') {
        // Article already exists (race condition or retry)
        Logger::processing('INFO', 'Duplicate article skipped', [
            'final_url' => $final_url,
            'error' => $e->getMessage()
        ]);
        return; // Skip silently
    }
    // Other database errors - rethrow
    throw $e;
}
```

### 7.3 SSRF Protection (Server-Side Request Forgery)

#### 7.3.1 URL Validation Requirements
**Before fetching any decoded article URL, validate it to prevent SSRF attacks.**

**Attack Scenario:**
```
Google News URL decodes to:
http://169.254.169.254/latest/meta-data/iam/security-credentials/
↑ AWS metadata endpoint - can expose credentials!
```

#### 7.3.2 Required Validation Implementation
```php
class UrlValidator {
    private const BLOCKED_IP_RANGES = [
        '10.0.0.0/8',        // Private network
        '172.16.0.0/12',     // Private network
        '192.168.0.0/16',    // Private network
        '127.0.0.0/8',       // Loopback
        '169.254.0.0/16',    // Link-local (AWS metadata)
        '::1/128',           // IPv6 localhost
        'fc00::/7',          // IPv6 private
        'fe80::/10',         // IPv6 link-local
    ];

    private const ALLOWED_SCHEMES = ['http', 'https'];

    public function validate(string $url): void {
        // 1. Parse URL
        $parsed = parse_url($url);
        if (!$parsed || empty($parsed['host'])) {
            throw new SecurityException('Invalid URL format');
        }

        // 2. Check scheme
        if (!in_array(strtolower($parsed['scheme'] ?? ''), self::ALLOWED_SCHEMES)) {
            throw new SecurityException('Invalid URL scheme (must be HTTP/HTTPS)');
        }

        // 3. Resolve hostname to IP
        $ip = gethostbyname($parsed['host']);
        if ($ip === $parsed['host']) {
            // DNS resolution failed
            throw new SecurityException('Could not resolve hostname');
        }

        // 4. Block private IP ranges
        foreach (self::BLOCKED_IP_RANGES as $range) {
            if ($this->ipInRange($ip, $range)) {
                throw new SecurityException(
                    'Private IP address blocked: ' . $ip
                );
            }
        }

        // 5. Additional checks
        if (strlen($url) > 2000) {
            throw new SecurityException('URL too long (max 2000 chars)');
        }
    }

    private function ipInRange(string $ip, string $range): bool {
        // IPv4/IPv6 CIDR range checking
        // Implementation: convert IP and range to binary, compare
        // ... (full implementation)
    }
}
```

#### 7.3.3 Apply Validation Before All HTTP Requests
```php
// In article processing logic
$finalUrl = decodeGoogleNewsUrl($googleNewsUrl);

// VALIDATE before fetching
$validator = new UrlValidator();
try {
    $validator->validate($finalUrl);
} catch (SecurityException $e) {
    Logger::security('WARNING', 'SSRF attempt blocked', [
        'url' => $finalUrl,
        'google_url' => $googleNewsUrl
    ]);
    markArticleAsFailed($article_id, 'Invalid URL');
    return;
}

// Safe to fetch
$html = fetchUrl($finalUrl);
```

#### 7.3.4 HTTP Client Configuration
```php
// cURL safety settings
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS => 3,           // Limit redirects
    CURLOPT_TIMEOUT => 10,             // 10 second timeout
    CURLOPT_CONNECTTIMEOUT => 5,       // 5 second connect timeout
    CURLOPT_USERAGENT => 'Unfurl/1.0', // Identify ourselves
    CURLOPT_SSL_VERIFYPEER => true,    // Verify SSL certificates
]);

// Follow redirects with same validation
curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($ch, $header) {
    if (preg_match('/^Location: (.+)$/i', $header, $matches)) {
        $redirectUrl = trim($matches[1]);
        // Validate redirect target
        (new UrlValidator())->validate($redirectUrl);
    }
    return strlen($header);
});
```

### 7.4 XSS Protection (Cross-Site Scripting)

#### 7.4.1 Output Escaping Requirements
**All user-controlled content MUST be escaped before display.**

```php
// ❌ NEVER output raw content
echo $article['title'];
echo '<a href="' . $article['final_url'] . '">Link</a>';

// ✅ ALWAYS escape output
echo htmlspecialchars($article['title'], ENT_QUOTES, 'UTF-8');
echo '<a href="' . htmlspecialchars($article['final_url'], ENT_QUOTES, 'UTF-8') . '">Link</a>';

// ✅ Helper function
function e(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

echo e($article['title']);
```

#### 7.4.2 Context-Aware Escaping
```php
// HTML context
echo '<div class="title">' . e($title) . '</div>';

// Attribute context
echo '<img alt="' . e($altText) . '" src="' . e($imageUrl) . '">';

// JavaScript context
echo '<script>const title = ' . json_encode($title) . ';</script>';

// URL context
echo '<a href="' . urlencode($userInput) . '">Link</a>';
```

#### 7.4.3 Content Security Policy (CSP)
```php
// Add to all HTML responses
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' https:;");
```

### 7.5 CSRF Protection (Cross-Site Request Forgery)

#### 7.5.1 Token Generation & Validation
```php
// Generate token for session
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Include in all state-changing forms
function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' .
           e($_SESSION['csrf_token']) . '">';
}

// Validate on form submission
function validateCsrfToken(): void {
    $provided = $_POST['csrf_token'] ?? '';
    $expected = $_SESSION['csrf_token'] ?? '';

    // Use hash_equals for timing-attack resistance
    if (!hash_equals($expected, $provided)) {
        throw new SecurityException('CSRF token validation failed');
    }

    // Regenerate token after successful validation
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
```

#### 7.5.2 Apply to All Forms
```html
<!-- Feed creation form -->
<form method="POST" action="/feeds/create">
    <?php echo csrfField(); ?>
    <input name="topic" required>
    <button type="submit">Create Feed</button>
</form>
```

```php
// In controller
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();
    // Process form...
}
```

### 7.6 Input Validation

#### 7.6.1 Validation Requirements
```php
class FeedValidator {
    public function validate(array $data): array {
        $errors = [];

        // Topic name
        if (empty($data['topic'])) {
            $errors['topic'] = 'Topic name is required';
        } elseif (strlen($data['topic']) > 255) {
            $errors['topic'] = 'Topic name too long (max 255 characters)';
        } elseif (!preg_match('/^[a-zA-Z0-9\s\-\_]+$/', $data['topic'])) {
            $errors['topic'] = 'Topic name contains invalid characters';
        }

        // URL validation
        if (empty($data['url'])) {
            $errors['url'] = 'Feed URL is required';
        } elseif (!filter_var($data['url'], FILTER_VALIDATE_URL)) {
            $errors['url'] = 'Invalid URL format';
        } else {
            $host = parse_url($data['url'], PHP_URL_HOST);
            if (!str_ends_with($host, 'google.com')) {
                $errors['url'] = 'Must be a Google News URL';
            }
        }

        // Result limit
        if (!isset($data['limit']) || !is_numeric($data['limit'])) {
            $errors['limit'] = 'Result limit must be a number';
        } elseif ($data['limit'] < 1 || $data['limit'] > 100) {
            $errors['limit'] = 'Result limit must be between 1 and 100';
        }

        if (!empty($errors)) {
            throw new ValidationException('Validation failed', $errors);
        }

        return $data;
    }
}
```

### 7.7 API Security

#### 7.7.1 API Key Generation
```php
// Use cryptographically secure random bytes
$apiKey = bin2hex(random_bytes(32)); // 64 hex characters

// ❌ NEVER use weak random generators
// $apiKey = md5(rand()); // INSECURE!
// $apiKey = uniqid(); // INSECURE!
```

#### 7.7.2 Rate Limiting
```php
function checkRateLimit(string $apiKeyId): void {
    $key = "rate_limit:api:{$apiKeyId}:" . date('Y-m-d-H-i');

    // Use APCu or file-based counter
    $count = apcu_inc($key, 1, $success);
    if (!$success) {
        apcu_add($key, 1, 60); // 1 minute TTL
        $count = 1;
    }

    if ($count > 60) { // Max 60 requests per minute
        http_response_code(429);
        die(json_encode([
            'error' => 'Rate limit exceeded',
            'retry_after' => 60
        ]));
    }
}
```

#### 7.7.3 HTTPS Enforcement
```php
// Redirect HTTP to HTTPS
if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
    $redirect = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    header('Location: ' . $redirect, true, 301);
    exit;
}

// Set HSTS header
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
```

---

## 8. Processing Logic Requirements

### 8.1 Google News URL Decoding

#### 8.1.1 Batchexecute API Method
```php
function decodeGoogleNewsUrl($articleId) {
    $url = 'https://news.google.com/_/DotsSplashUi/data/batchexecute?rpcids=Fbv4je';

    $payload = [
        'f.req' => json_encode([
            [
                [
                    'Fbv4je',
                    json_encode([
                        'garturlreq',
                        [
                            ['en-US', 'US', ['FINANCE_TOP_INDICES', 'WEB_TEST_1_0_0']],
                            null, null, 1, 1, 'US:en', null, 180,
                            null, null, null, null, null, 0, 1
                        ],
                        $articleId
                    ]),
                    null,
                    'generic'
                ]
            ]
        ])
    ];

    // cURL request
    // Parse response
    // Extract URL from "garturlres" field
    // Return actual URL or null on failure
}
```

#### 8.1.2 Fallback Strategy
1. Try batchexecute API
2. If fails: Log error, mark as failed
3. Optional: Queue for retry with external service

### 8.2 Metadata and Content Extraction
```php
function extractMetadata($html) {
    $metadata = [];

    // Parse HTML (DOMDocument)
    $doc = new DOMDocument();
    @$doc->loadHTML($html);
    $xpath = new DOMXPath($doc);

    // Extract meta tags:
    // <meta property="og:image" content="..." />
    // <meta name="twitter:image" content="..." />
    // <meta property="og:title" content="..." />

    // Extract categories/tags
    // Look for: <meta name="keywords" content="..." />
    // Look for: <meta property="article:tag" content="..." />
    // Look for: class="category", class="tag", etc.

    // Extract article content
    // Priority order:
    // 1. <article> tag
    // 2. [itemprop="articleBody"]
    // 3. class="article-content", "post-content", "entry-content"
    // 4. <main> tag
    // Strip: <script>, <style>, <nav>, <header>, <footer>, <aside>
    // Convert to plain text, clean whitespace

    $articleContent = extractArticleContent($doc, $xpath);
    $wordCount = str_word_count($articleContent);

    return [
        'og_image' => $ogImage ?? null,
        'og_title' => $ogTitle ?? null,
        'og_description' => $ogDescription ?? null,
        'og_url' => $ogUrl ?? null,
        'og_site_name' => $ogSiteName ?? null,
        'twitter_image' => $twitterImage ?? null,
        'author' => $author ?? null,
        'page_title' => $pageTitle ?? null,
        'article_content' => $articleContent,
        'word_count' => $wordCount,
        'categories' => $categories // JSON array
    ];
}

function extractArticleContent($doc, $xpath) {
    // Try to find article content using various selectors
    $selectors = [
        '//article',
        '//*[@itemprop="articleBody"]',
        '//*[contains(@class, "article-content")]',
        '//*[contains(@class, "post-content")]',
        '//*[contains(@class, "entry-content")]',
        '//main'
    ];

    foreach ($selectors as $selector) {
        $nodes = $xpath->query($selector);
        if ($nodes->length > 0) {
            $content = $nodes->item(0)->textContent;
            // Clean up whitespace
            $content = preg_replace('/\s+/', ' ', $content);
            $content = trim($content);
            if (strlen($content) > 100) { // Minimum content length
                return $content;
            }
        }
    }

    return ''; // No content found
}
```

### 8.3 Error Handling
- **Network Errors:** Timeout after 30 seconds, log error
- **Parsing Errors:** Log HTML snippet, mark as failed
- **Rate Limiting:** Detect 429 responses, back off exponentially
- **Invalid URLs:** Validate before processing, skip invalid
- **Duplicate Detection:** Check google_news_url before inserting

### 8.4 Performance
- **Batch Processing:** Process feeds sequentially (not parallel on shared hosting)
- **Timeout:** Max 30 seconds per article
- **Memory:** Monitor memory usage, set PHP memory limit appropriately
- **Logging:** Log warnings/errors, not every success (reduces disk I/O)

---

## 9. Deployment Requirements

### 9.1 CI/CD Pipeline (GitHub Actions)

**Requirement:** All code deployments must go through GitHub and CI/CD pipeline.

#### 9.1.1 GitHub Repository Setup
- **Repository:** `cobenrogers/unfurl`
- **Main Branch:** `main`
- **Protected Branch:** Require PR reviews before merging to `main`
- **Auto-deploy:** Pushes to `main` trigger automatic deployment

#### 9.1.2 CI/CD Workflow
**On Pull Request:**
1. Run PHPUnit tests (unit + integration)
2. Run code quality checks (PHPStan, PHPCS if configured)
3. Generate test coverage report
4. Block merge if tests fail

**On Push to Main:**
1. Run all tests again
2. Deploy to production via rsync (if tests pass)
3. Run post-deployment health check
4. Send notification on failure

**Similar to SNAM workflow:**
```yaml
# .github/workflows/deploy.yml
name: Deploy to Production

on:
  push:
    branches: [ main ]
  pull_request:
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
      - name: Install dependencies
        run: composer install
      - name: Run tests
        run: composer test

  deploy:
    needs: test
    if: github.ref == 'refs/heads/main'
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - name: Deploy via rsync
        uses: burnett01/rsync-deployments@5.2
        with:
          switches: -avzr --delete
          path: ./
          remote_path: /home/username/public_html/unfurl/
          remote_host: ${{ secrets.DEPLOY_HOST }}
          remote_user: ${{ secrets.DEPLOY_USER }}
          remote_key: ${{ secrets.DEPLOY_KEY }}
      - name: Health check
        run: curl -f https://yoursite.com/unfurl/health.php || exit 1
```

#### 9.1.3 Deployment Secrets (GitHub Secrets)
- `DEPLOY_HOST`: Bluehost server hostname
- `DEPLOY_USER`: SSH username
- `DEPLOY_KEY`: SSH private key (if SSH enabled)
- `DEPLOY_PATH`: Deployment path on server

**Note:** Requires SSH access to be enabled on Bluehost for automated deployments.

### 9.2 Database Setup & Migrations

**Current State:** No SSH access - manual cPanel setup required
**Future State:** With SSH access - automated migrations via CI/CD

#### 9.2.1 Current Approach (No SSH - Manual cPanel)

**Initial Setup:**
1. **Create Database**
   - Log into cPanel
   - Go to "MySQL Databases"
   - Create new database: `unfurl_db`
   - Create database user with all privileges
   - Note credentials for `config.php`

2. **Import Schema**
   - Go to phpMyAdmin in cPanel
   - Select `unfurl_db` database
   - Import `sql/schema.sql` file
   - Verify all tables created successfully

**Schema Updates/Migrations:**
- Developer creates migration SQL file in `sql/migrations/` directory
- SQL file committed to GitHub with descriptive name: `YYYY-MM-DD_description.sql`
- After deployment, admin manually runs migration via phpMyAdmin:
  1. Open phpMyAdmin in cPanel
  2. Select `unfurl_db` database
  3. Click "SQL" tab
  4. Copy/paste migration SQL
  5. Execute
  6. Verify changes

**Example Migration File:**
```sql
-- sql/migrations/2026-02-05_add_final_url_index.sql
-- Description: Add index on final_url for duplicate detection

ALTER TABLE articles
ADD INDEX idx_final_url (final_url(500));

-- Verification query
SHOW INDEX FROM articles WHERE Key_name = 'idx_final_url';
```

#### 9.2.2 Future Approach (With SSH - Automated)

**When SSH is enabled:**
- Add migration step to CI/CD pipeline
- Use migration tool (Phinx, custom PHP script)
- Automatically apply pending migrations on deployment
- Track applied migrations in database table

**Example automated migration:**
```yaml
# In .github/workflows/deploy.yml
- name: Run database migrations
  run: |
    ssh ${{ secrets.DEPLOY_USER }}@${{ secrets.DEPLOY_HOST }} \
      "cd /path/to/unfurl && php bin/migrate.php"
```

**Migration tracking table:**
```sql
CREATE TABLE migrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    migration_name VARCHAR(255) NOT NULL UNIQUE,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### 9.3 Installation Steps

#### 9.1.1 Basic Installation
1. **Upload Files**
   - Upload all files to Bluehost via FTP or cPanel File Manager
   - Target directory: `/public_html/unfurl/` (or subdomain)

2. **Create Database**
   - Log into cPanel
   - Go to "MySQL Databases"
   - Create new database: `unfurl_db`
   - Create database user with all privileges

3. **Import Schema**
   - Go to phpMyAdmin in cPanel
   - Select `unfurl_db` database
   - Import `sql/schema.sql` file

4. **Configure Application**
   - Edit `config.php` with database credentials
   - Generate and set API secret key
   - Set application base URL

5. **Set Permissions**
   - Directories: `chmod 755`
   - PHP files: `chmod 644`
   - Config file: `chmod 600` (more secure)

6. **Test Installation**
   - Visit `https://yoursite.com/unfurl/`
   - Create a test feed
   - Process manually to verify it works

#### 9.3.2 Cron Job Setup (Required for Automation)

**Important:** Bluehost does not allow programmatic cron job creation. You must manually configure cron jobs through cPanel.

**Step 1: Get Cron Commands from Settings Page**
- Log into Unfurl
- Go to Settings page
- Copy the provided cron commands

**Step 2: Configure in cPanel**
1. Log into cPanel
2. Navigate to "Advanced" → "Cron Jobs"
3. Add two cron jobs as shown below

**Cron Job 1: Feed Processing (Daily at 9:00 AM)**
```
Minute: 0
Hour: 9
Day: *
Month: *
Weekday: *
Command: curl -X POST -d "secret=YOUR_SECRET_KEY" https://yoursite.com/unfurl/api.php > /dev/null 2>&1
```

**Cron Job 2: Data Cleanup (Daily at 2:00 AM)**
```
Minute: 0
Hour: 2
Day: *
Month: *
Weekday: *
Command: php /home/username/public_html/unfurl/includes/cleanup.php > /dev/null 2>&1
```

**Step 3: Verify Cron Jobs**
- Wait for next scheduled run OR
- Trigger manually via Settings page
- Check logs to confirm execution

**Alternative: External Cron Service**
If cPanel access is limited, use free service like cron-job.org:
1. Sign up at https://cron-job.org
2. Create new job pointing to: `https://yoursite.com/unfurl/api.php`
3. Set to POST with data: `secret=YOUR_SECRET_KEY`
4. Set schedule: Daily at 9:00 AM

### 9.4 Configuration Management

#### 9.4.1 Environment Variables (REQUIRED)

**All sensitive configuration MUST use environment variables to prevent credential exposure.**

**Environment File (`.env`):**
```bash
# Database Configuration
DB_HOST=localhost
DB_NAME=unfurl_db
DB_USER=unfurl_user
DB_PASS=your_secure_password_here

# Application
APP_ENV=production
APP_DEBUG=false
APP_BASE_URL=https://yoursite.com/unfurl/
APP_TIMEZONE=America/New_York

# Security
SESSION_SECRET=random_32_char_string_here

# Processing
PROCESSING_TIMEOUT=30
PROCESSING_MAX_RETRIES=3
PROCESSING_RETRY_DELAY=60

# Data Retention
RETENTION_ARTICLES_DAYS=90
RETENTION_LOGS_DAYS=30
RETENTION_AUTO_CLEANUP=true
```

**IMPORTANT Security Requirements:**
- `.env` file MUST be added to `.gitignore`
- Never commit `.env` to version control
- Use restrictive file permissions: `chmod 600 .env`
- Provide `.env.example` template (without secrets)

**Template File (`.env.example`):**
```bash
# Copy this file to .env and fill in your values
# DO NOT commit .env to git!

# Database Configuration
DB_HOST=localhost
DB_NAME=unfurl_db
DB_USER=your_db_username
DB_PASS=your_db_password

# Application
APP_ENV=production
APP_DEBUG=false
APP_BASE_URL=https://yoursite.com/unfurl/
APP_TIMEZONE=America/New_York

# Security
SESSION_SECRET=generate_with_openssl_rand_base64_32

# Processing
PROCESSING_TIMEOUT=30
PROCESSING_MAX_RETRIES=3
PROCESSING_RETRY_DELAY=60

# Data Retention
RETENTION_ARTICLES_DAYS=90
RETENTION_LOGS_DAYS=30
RETENTION_AUTO_CLEANUP=true
```

#### 9.4.2 Configuration File (config.php)

```php
// config.php - NO SECRETS IN THIS FILE
<?php

// Load environment variables
function loadEnv(string $path): void {
    if (!file_exists($path)) {
        throw new Exception('.env file not found');
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        // Parse KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Set environment variable
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

// Load .env file
loadEnv(__DIR__ . '/.env');

// Helper function to get environment variable with default
function env(string $key, $default = null) {
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

    if ($value === false) {
        return $default;
    }

    // Convert string booleans
    if (in_array(strtolower($value), ['true', 'false'])) {
        return strtolower($value) === 'true';
    }

    return $value;
}

// Configuration array
return [
    'database' => [
        'host' => env('DB_HOST', 'localhost'),
        'name' => env('DB_NAME'),
        'user' => env('DB_USER'),
        'pass' => env('DB_PASS'),
    ],

    'app' => [
        'env' => env('APP_ENV', 'production'),
        'debug' => env('APP_DEBUG', false),
        'base_url' => env('APP_BASE_URL'),
        'timezone' => env('APP_TIMEZONE', 'UTC'),
    ],

    'processing' => [
        'timeout' => (int)env('PROCESSING_TIMEOUT', 30),
        'max_retries' => (int)env('PROCESSING_MAX_RETRIES', 3),
        'retry_delay' => (int)env('PROCESSING_RETRY_DELAY', 60),
    ],

    'retention' => [
        'articles_days' => (int)env('RETENTION_ARTICLES_DAYS', 90),
        'logs_days' => (int)env('RETENTION_LOGS_DAYS', 30),
        'auto_cleanup' => env('RETENTION_AUTO_CLEANUP', true),
    ],

    'security' => [
        'session_secret' => env('SESSION_SECRET'),
    ],
];
```

#### 9.4.3 `.gitignore` Requirements

**CRITICAL: Add these entries to `.gitignore`:**
```gitignore
# Environment & Secrets
.env
.env.local
.env.*.local

# Configuration backups (may contain secrets)
config.php.bak
*.env.backup

# Sensitive data
storage/temp/*
!storage/temp/.gitkeep

# Logs (may contain sensitive info)
*.log
logs/

# Database dumps
*.sql
*.sql.gz
!sql/schema.sql
!sql/migrations/*.sql
```

#### 9.4.4 Generating Secrets

**Generate secure random values:**
```bash
# Session secret (32 bytes = 64 hex chars)
php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"

# Or using OpenSSL
openssl rand -hex 32

# Or using /dev/urandom
head -c 32 /dev/urandom | base64
```

#### 9.4.5 Configuration Validation

**Validate configuration on application startup:**
```php
// bootstrap.php
function validateConfig(array $config): void {
    $required = [
        'database.host',
        'database.name',
        'database.user',
        'database.pass',
        'app.base_url',
        'security.session_secret',
    ];

    $missing = [];
    foreach ($required as $key) {
        $keys = explode('.', $key);
        $value = $config;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                $missing[] = $key;
                break;
            }
            $value = $value[$k];
        }

        if (empty($value)) {
            $missing[] = $key;
        }
    }

    if (!empty($missing)) {
        throw new Exception(
            'Missing required configuration: ' .
            implode(', ', $missing)
        );
    }

    // Validate session secret strength
    if (strlen($config['security']['session_secret']) < 32) {
        throw new Exception(
            'SESSION_SECRET must be at least 32 characters'
        );
    }
}

$config = require __DIR__ . '/config.php';
validateConfig($config);
```

#### 9.4.6 Production Deployment Checklist

**Before deploying to production:**
- [ ] Create `.env` file with production values
- [ ] Set `APP_ENV=production`
- [ ] Set `APP_DEBUG=false`
- [ ] Generate strong `SESSION_SECRET`
- [ ] Use strong database password
- [ ] Set file permissions: `chmod 600 .env`
- [ ] Verify `.env` is in `.gitignore`
- [ ] Test configuration validation
- [ ] Verify no secrets in `config.php`
- [ ] Remove any `.env.example` values from production `.env`

### 9.3 Directory Structure
```
/unfurl/
├── index.php           # Feeds management page
├── articles.php        # Articles view/management
├── process.php         # Manual feed processing
├── settings.php        # Configuration page
├── api.php             # API endpoint for cron
├── config.php          # Configuration file
├── includes/
│   ├── db.php          # Database connection
│   ├── functions.php   # Helper functions
│   ├── processor.php   # Feed processing logic
│   └── decoder.php     # URL decoding logic
├── assets/
│   ├── css/
│   │   └── style.css
│   └── js/
│       └── script.js
├── sql/
│   └── schema.sql      # Database schema
└── README.md           # Installation/usage guide
```

### 9.4 Cron Job Setup
```bash
# Run daily at 9:00 AM
0 9 * * * curl -X POST -d "secret=YOUR_SECRET_KEY" https://yoursite.com/unfurl/api.php > /dev/null 2>&1
```

---

## 10. Testing Requirements

### 10.1 Testing Philosophy
- **Test-Driven Development:** Write tests before or alongside code
- **Comprehensive Coverage:** Target 80%+ code coverage
- **Continuous Testing:** Run tests on every commit
- **Multiple Levels:** Unit, integration, and UI tests

### 10.2 Unit Tests

#### 10.2.1 Scope
Test individual functions and methods in isolation.

#### 10.2.2 Test Framework
- **PHP:** PHPUnit 9.x+
- **Location:** `/tests/unit/`
- **Naming:** `*Test.php`

#### 10.2.3 Unit Test Coverage

**URL Decoding (`decoder.php`):**
```php
class DecoderTest extends PHPUnit\Framework\TestCase {
    public function testDecodeGoogleNewsUrl() {
        // Test successful decode
        // Test various URL formats
        // Test error handling
        // Test invalid input
    }

    public function testExtractArticleId() {
        // Test article ID extraction
        // Test different URL patterns
    }
}
```

**Metadata Extraction (`functions.php`):**
```php
class MetadataExtractorTest extends PHPUnit\Framework\TestCase {
    public function testExtractMetadata() {
        // Test with complete HTML
        // Test with missing meta tags
        // Test with malformed HTML
    }

    public function testExtractArticleContent() {
        // Test <article> tag
        // Test fallback selectors
        // Test content cleaning
    }

    public function testExtractCategories() {
        // Test meta keywords
        // Test article tags
        // Test no categories
    }

    public function testStripHtml() {
        // Test HTML removal
        // Test script/style removal
        // Test whitespace cleaning
    }
}
```

**RSS Feed Generation (`feed.php`):**
```php
class RssFeedTest extends PHPUnit\Framework\TestCase {
    public function testGenerateRssFeed() {
        // Test valid XML output
        // Test required elements
        // Test CDATA escaping
    }

    public function testFilterByTopic() {
        // Test topic filtering
        // Test case sensitivity
    }

    public function testPagination() {
        // Test limit parameter
        // Test offset parameter
    }
}
```

**Database Operations (`db.php`):**
```php
class DatabaseTest extends PHPUnit\Framework\TestCase {
    public function testInsertArticle() {
        // Test successful insert
        // Test duplicate detection
        // Test validation
    }

    public function testUpdateArticle() {
        // Test update existing
        // Test non-existent article
    }

    public function testDeleteArticle() {
        // Test cascade delete
    }
}
```

### 10.3 Integration Tests

#### 10.3.1 Scope
Test component interactions and external dependencies.

#### 10.3.2 Test Framework
- **PHP:** PHPUnit with database
- **Location:** `/tests/integration/`
- **Database:** Test database (separate from production)

#### 10.3.3 Integration Test Coverage

**Feed Processing Pipeline:**
```php
class FeedProcessingTest extends PHPUnit\Framework\TestCase {
    public function setUp(): void {
        // Create test database
        // Seed test data
    }

    public function testProcessFeedEndToEnd() {
        // Mock RSS feed response
        // Process feed
        // Verify articles in database
        // Verify metadata extracted
        // Verify content extracted
    }

    public function testDuplicateHandling() {
        // Process same feed twice
        // Verify no duplicates created
    }

    public function testFailedArticleHandling() {
        // Mock failed URL decode
        // Verify error stored
        // Verify status marked as failed
    }

    public function tearDown(): void {
        // Clean up test database
    }
}
```

**API Endpoint Tests:**
```php
class ApiEndpointTest extends PHPUnit\Framework\TestCase {
    public function testAuthenticatedRequest() {
        // Test with valid secret
        // Verify processing occurs
    }

    public function testUnauthorizedRequest() {
        // Test with invalid secret
        // Verify 403 response
    }

    public function testRateLimiting() {
        // Test multiple rapid requests
        // Verify rate limit enforced
    }
}
```

**RSS Feed Generation Tests:**
```php
class RssFeedIntegrationTest extends PHPUnit\Framework\TestCase {
    public function testGenerateFeedFromDatabase() {
        // Seed articles in database
        // Generate RSS feed
        // Verify valid XML
        // Verify all articles present
        // Verify correct formatting
    }

    public function testFeedCaching() {
        // Generate feed
        // Verify cache created
        // Request again, verify cache used
        // Add new article, verify cache invalidated
    }
}
```

### 10.4 UI/E2E Tests

#### 10.4.1 Scope
Test complete user workflows through the browser.

#### 10.4.2 Test Framework
- **Tool:** Playwright (PHP or Node.js)
- **Location:** `/tests/e2e/`
- **Browsers:** Chromium, Safari (WebKit)

#### 10.4.3 E2E Test Coverage

**Feed Management:**
```javascript
test('Create new feed', async ({ page }) => {
  await page.goto('/feeds');
  await page.click('text=Add New Feed');
  await page.fill('#topic', 'Test Topic');
  await page.fill('#url', 'https://news.google.com/rss/...');
  await page.fill('#limit', '10');
  await page.click('button:has-text("Save")');
  await expect(page.locator('.success-message')).toBeVisible();
  await expect(page.locator('text=Test Topic')).toBeVisible();
});

test('Edit existing feed', async ({ page }) => {
  // Navigate to feeds
  // Click edit
  // Modify fields
  // Save
  // Verify changes
});

test('Delete feed', async ({ page }) => {
  // Navigate to feeds
  // Click delete
  // Confirm
  // Verify removed
});
```

**Article Management:**
```javascript
test('View articles list', async ({ page }) => {
  await page.goto('/articles');
  await expect(page.locator('table.articles')).toBeVisible();
});

test('Filter articles by topic', async ({ page }) => {
  await page.goto('/articles');
  await page.selectOption('#topic-filter', 'IBD Research');
  await page.click('button:has-text("Apply")');
  // Verify only matching articles shown
});

test('Search articles', async ({ page }) => {
  await page.goto('/articles');
  await page.fill('#search', 'Crohn');
  await page.click('button:has-text("Search")');
  // Verify search results
});

test('Edit article', async ({ page }) => {
  // View article
  // Click edit
  // Modify fields
  // Save
  // Verify changes
});

test('Delete article', async ({ page }) => {
  // Select article
  // Click delete
  // Confirm
  // Verify removed
});
```

**Feed Processing:**
```javascript
test('Process feed manually', async ({ page }) => {
  await page.goto('/process');
  await page.check('#feed-1');
  await page.click('button:has-text("Process Selected")');
  // Wait for processing
  await expect(page.locator('.progress-bar')).toBeVisible();
  await expect(page.locator('.success-summary')).toBeVisible({ timeout: 60000 });
  // Verify articles created
});
```

**RSS Feed Generation:**
```javascript
test('Generate RSS feed', async ({ page }) => {
  const response = await page.goto('/feed.php?topic=IBD+Research');
  expect(response.headers()['content-type']).toContain('application/rss+xml');
  const xml = await response.text();
  expect(xml).toContain('<rss version="2.0">');
  expect(xml).toContain('<title>Unfurl - IBD Research</title>');
});
```

**Mobile Responsiveness:**
```javascript
test('Mobile: View articles on iPad', async ({ page }) => {
  await page.setViewportSize({ width: 768, height: 1024 });
  await page.goto('/articles');
  await expect(page.locator('.article-list')).toBeVisible();
  // Verify mobile-friendly layout
});
```

### 10.5 Test Data Management

#### 10.5.1 Fixtures
- Store sample RSS feeds in `/tests/fixtures/rss/`
- Store sample HTML pages in `/tests/fixtures/html/`
- Store expected outputs in `/tests/fixtures/expected/`

#### 10.5.2 Mocking
- Mock external HTTP requests (Google News, article sites)
- Mock database for unit tests
- Use test database for integration tests

#### 10.5.3 Test Database
```sql
-- Create separate test database
CREATE DATABASE unfurl_test;

-- Same schema as production
-- Automated setup in test bootstrap
```

### 10.6 Continuous Integration

#### 10.6.1 GitHub Actions Workflow
```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.0'
      - name: Install dependencies
        run: composer install
      - name: Run unit tests
        run: vendor/bin/phpunit tests/unit
      - name: Run integration tests
        run: vendor/bin/phpunit tests/integration
      - name: Install Playwright
        run: npx playwright install
      - name: Run E2E tests
        run: npx playwright test
```

### 10.7 Test Coverage Goals

- **Unit Tests:** 80%+ code coverage
- **Integration Tests:** All critical paths covered
- **E2E Tests:** All user workflows covered
- **Overall:** 85%+ combined coverage

### 10.8 Test Execution

**Local Development:**
```bash
# Run all unit tests
composer test

# Run specific test file
vendor/bin/phpunit tests/unit/DecoderTest.php

# Run integration tests
composer test:integration

# Run E2E tests
npx playwright test

# Run E2E tests in headed mode
npx playwright test --headed
```

**Pre-Commit Hook:**
```bash
# Run unit tests before every commit
composer test:unit
```

**Pre-Deploy:**
```bash
# Run all tests before deployment
composer test:all
npx playwright test
```

### 10.9 Test Documentation

Each test should include:
- **Description:** What is being tested
- **Given/When/Then:** Test scenario
- **Expected outcome:** What should happen
- **Edge cases:** What edge cases are covered

**Example:**
```php
/**
 * Test: URL decoding with invalid article ID
 *
 * Given: A Google News URL with malformed article ID
 * When: decodeGoogleNewsUrl() is called
 * Then: Should return null and log error
 *
 * Edge cases:
 * - Empty article ID
 * - Non-base64 characters
 * - Truncated ID
 */
public function testDecodeInvalidArticleId() {
    // Test implementation
}
```

---

## 11. Logging & Monitoring Requirements

### 11.1 Logging Philosophy
- **Comprehensive:** Log all significant events
- **Structured:** Use consistent format for parsing
- **Searchable:** Enable filtering and searching
- **Performance-aware:** Don't impact system performance
- **Privacy-conscious:** Don't log sensitive data

### 11.2 Log Categories

#### 11.2.1 Processing Logs
Track all feed processing activities.

**What to Log:**
- Feed processing start/end
- Each article processed (success/failure)
- URL decoding attempts
- Metadata extraction results
- Content extraction results
- Errors and warnings
- Processing duration/performance

**Log Level:**
- `INFO`: Successful operations
- `WARNING`: Recoverable errors (retry, fallback)
- `ERROR`: Failed operations
- `DEBUG`: Detailed processing steps (dev only)

**Example Log Entry:**
```
[2026-02-07 12:00:15] PROCESSING INFO: Started processing feed_id=1 (IBD Research)
[2026-02-07 12:00:16] PROCESSING INFO: Fetched RSS feed (23 articles)
[2026-02-07 12:00:18] PROCESSING INFO: Article processed article_id=145 url=https://beingpatient.com/... (2.1s)
[2026-02-07 12:00:19] PROCESSING WARNING: Failed to extract content article_id=146 reason="No article tag found"
[2026-02-07 12:00:20] PROCESSING ERROR: URL decode failed article_id=147 error="batchexecute timeout"
[2026-02-07 12:00:35] PROCESSING INFO: Completed feed_id=1 total=10 success=8 failed=2 duration=20s
```

#### 11.2.2 User Activity Logs
Track user interactions with the system.

**What to Log:**
- Page views
- Feed CRUD operations (create, update, delete)
- Article CRUD operations
- Bulk actions
- Search queries
- Filter usage
- Failed login attempts (future)

**Example Log Entry:**
```
[2026-02-07 12:05:30] USER INFO: Page view page=/articles ip=192.168.1.100 user_agent="Mozilla/5.0..."
[2026-02-07 12:05:45] USER INFO: Feed created feed_id=5 topic="UC Research" user_ip=192.168.1.100
[2026-02-07 12:06:12] USER INFO: Article deleted article_id=123 feed_id=1 user_ip=192.168.1.100
[2026-02-07 12:07:22] USER INFO: Search executed query="Crohn's" results=15 user_ip=192.168.1.100
[2026-02-07 12:08:05] USER INFO: Bulk delete count=5 feed_id=2 user_ip=192.168.1.100
```

#### 11.2.3 Feed Request Logs
Track RSS feed generation and requests.

**What to Log:**
- Feed requests (URL, parameters)
- Response time
- Cache hits/misses
- Number of items returned
- Client user agent (RSS reader)

**Example Log Entry:**
```
[2026-02-07 12:10:15] FEED INFO: Feed request topic="IBD Research" limit=20 cache=HIT response_time=12ms user_agent="Feedly/1.0"
[2026-02-07 12:15:30] FEED INFO: Feed request topic="All" limit=50 cache=MISS response_time=145ms user_agent="NetNewsWire/6.1"
[2026-02-07 12:20:45] FEED WARNING: Feed request rate_limited ip=192.168.1.200 requests_per_minute=10
```

#### 11.2.4 API Logs
Track API endpoint usage (cron, webhooks).

**What to Log:**
- API requests (endpoint, authentication)
- Request parameters
- Response status
- Processing results
- IP address
- Timestamp

**Example Log Entry:**
```
[2026-02-07 09:00:00] API INFO: Request endpoint=/api.php auth=SUCCESS ip=cron.server.com
[2026-02-07 09:00:01] API INFO: Processing 3 enabled feeds
[2026-02-07 09:00:45] API INFO: Completed feeds=3 articles=25 success=23 failed=2 duration=45s
[2026-02-07 09:05:00] API ERROR: Request endpoint=/api.php auth=FAILED ip=192.168.1.250 reason="Invalid secret"
```

#### 11.2.5 System Logs
Track system-level events and errors.

**What to Log:**
- Database connection errors
- Configuration errors
- Memory/performance issues
- Cron job execution
- Cache operations
- File I/O errors

**Example Log Entry:**
```
[2026-02-07 12:00:00] SYSTEM INFO: Cron job started job=process_feeds
[2026-02-07 12:00:45] SYSTEM INFO: Cron job completed job=process_feeds duration=45s
[2026-02-07 12:15:30] SYSTEM ERROR: Database connection failed host=localhost error="Too many connections"
[2026-02-07 12:20:15] SYSTEM WARNING: High memory usage usage=85% threshold=80%
```

### 11.3 Log Storage

#### 11.3.1 Database Logging (Primary) ✅ IMPLEMENTED
**Status:** Fully implemented and operational as of 2026-02-07

Database logging is the primary logging mechanism for Unfurl. All application events are logged to the `logs` table.

```sql
CREATE TABLE logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    log_type ENUM('processing', 'user', 'feed', 'api', 'system') NOT NULL,
    log_level ENUM('DEBUG', 'INFO', 'WARNING', 'ERROR') NOT NULL,
    message TEXT NOT NULL,
    context JSON,  -- Additional structured data
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_type_level (log_type, log_level),
    INDEX idx_created_at (created_at),
    INDEX idx_ip (ip_address)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Implementation Details:**
- Logger class: `src/Core/Logger.php`
- All timestamps stored in UTC
- JSON context for structured data
- Indexed for fast filtering by type, level, date, and IP
- Web interface for viewing logs (filterable by type, level, date range)
- Logs viewable at `/logs` endpoint with search and filtering

**Context JSON Examples:**
```json
// Processing log
{
  "feed_id": 1,
  "article_id": 145,
  "url": "https://beingpatient.com/...",
  "duration": 2.1,
  "status": "success"
}

// User activity log
{
  "action": "delete_article",
  "article_id": 123,
  "feed_id": 1
}

// Feed request log
{
  "topic": "IBD Research",
  "limit": 20,
  "cache_hit": true,
  "response_time": 12,
  "items_returned": 20
}
```

#### 11.3.2 File Logging (Backup) ⏸️ NOT IMPLEMENTED
**Status:** Not implemented in v1.0 - database logging is sufficient

File logging was considered as a backup mechanism but not implemented because:
- Database logging provides sufficient reliability
- Database logs are easier to query and filter
- Database retention policies handle cleanup automatically
- File logging adds complexity without clear benefits for this use case

**Future Consideration:** File logging may be added if:
- Database becomes performance bottleneck for logging
- Need for offline log analysis tools
- Compliance requirements mandate file-based logs
- High-volume logging requires separate storage

Recommended approach for now: Use database exports for backup/archival needs.

**Log File Format:**
```
[YYYY-MM-DD HH:MM:SS] [TYPE] [LEVEL] message key1=value1 key2=value2
```

### 11.4 Log Management Interface

#### 11.4.1 View Logs Page (`/logs.php`)
```
┌──────────────────────────────────────────────────────────┐
│ System Logs                              [Export] [Clear]│
├──────────────────────────────────────────────────────────┤
│ Filters:                                                 │
│ Type: [All ▼]  Level: [All ▼]  Date: [Today ▼]         │
│ Search: [________________] [Search]                      │
├──────────────────────────────────────────────────────────┤
│ 2026-02-07 12:00:15 | PROCESSING | INFO                 │
│ Started processing feed_id=1 (IBD Research)              │
│ [View Details]                                           │
├──────────────────────────────────────────────────────────┤
│ 2026-02-07 12:00:20 | PROCESSING | ERROR                │
│ URL decode failed article_id=147                         │
│ [View Details]                                           │
├──────────────────────────────────────────────────────────┤
│ 2026-02-07 12:05:45 | USER | INFO                       │
│ Feed created feed_id=5 topic="UC Research"              │
│ IP: 192.168.1.100                                        │
│ [View Details]                                           │
└──────────────────────────────────────────────────────────┘
```

#### 11.4.2 Log Details Modal
```
┌──────────────────────────────────────┐
│ Log Entry Details              [✕]  │
├──────────────────────────────────────┤
│ Timestamp: 2026-02-07 12:00:20       │
│ Type: PROCESSING                     │
│ Level: ERROR                         │
│                                      │
│ Message:                             │
│ URL decode failed article_id=147     │
│                                      │
│ Context:                             │
│ {                                    │
│   "article_id": 147,                 │
│   "url": "https://news.google...",  │
│   "error": "batchexecute timeout",  │
│   "duration": 30.2                  │
│ }                                    │
│                                      │
│ IP Address: 192.168.1.100            │
│ User Agent: Mozilla/5.0...           │
│                                      │
│ [Close]                              │
└──────────────────────────────────────┘
```

#### 11.4.3 Log Filtering
- **By Type:** All, Processing, User, Feed, API, System
- **By Level:** All, DEBUG, INFO, WARNING, ERROR
- **By Date:** Today, Yesterday, Last 7 days, Last 30 days, Custom range
- **By IP:** Filter logs from specific IP
- **Search:** Full-text search in message and context

#### 11.4.4 Log Export
- **Format:** CSV, JSON, plain text
- **Filters:** Apply current filters to export
- **Download:** Direct download or email

### 11.5 Data Cleanup & Retention

#### 11.5.1 Retention Policy (Configurable)
- **Articles:** Configurable (default: 90 days, 0 = keep forever)
- **Logs:** Configurable (default: 30 days)
- **Automatic cleanup:** Daily cron job (if enabled)
- **Manual cleanup:** Admin can trigger immediate cleanup

#### 11.5.2 Settings Storage
Store retention settings in database or config file:
```sql
-- Option 1: Database (allows UI configuration)
CREATE TABLE settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value VARCHAR(255),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO settings VALUES
('retention_articles_days', '90', NOW()),
('retention_logs_days', '30', NOW()),
('retention_auto_cleanup', '1', NOW());

-- Option 2: Use config.php (shown in section 9.2)
```

#### 11.5.3 Automatic Cleanup (Cron)
```php
// File: /includes/cleanup.php
// Cron job: Run daily at 2:00 AM

require_once 'config.php';
require_once 'db.php';
require_once 'functions.php';

function cleanupOldData() {
    global $db, $config;

    $results = [
        'articles_deleted' => 0,
        'logs_deleted' => 0
    ];

    // Get retention settings
    $articlesRetention = getSetting('retention_articles_days', 90);
    $logsRetention = getSetting('retention_logs_days', 30);
    $autoCleanup = getSetting('retention_auto_cleanup', true);

    if (!$autoCleanup) {
        Logger::system('INFO', 'Auto cleanup disabled, skipping');
        return $results;
    }

    // Clean up old articles (if retention > 0)
    if ($articlesRetention > 0) {
        $stmt = $db->prepare("
            DELETE FROM articles
            WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $stmt->execute([$articlesRetention]);
        $results['articles_deleted'] = $stmt->rowCount();

        Logger::system('INFO', 'Article cleanup completed', [
            'deleted_count' => $results['articles_deleted'],
            'retention_days' => $articlesRetention
        ]);
    }

    // Clean up old logs
    if ($logsRetention > 0) {
        $stmt = $db->prepare("
            DELETE FROM logs
            WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $stmt->execute([$logsRetention]);
        $results['logs_deleted'] = $stmt->rowCount();

        Logger::system('INFO', 'Log cleanup completed', [
            'deleted_count' => $results['logs_deleted'],
            'retention_days' => $logsRetention
        ]);
    }

    return $results;
}

// Helper function to get settings
function getSetting($key, $default = null) {
    global $db, $config;

    // Try database first
    $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetchColumn();

    if ($result !== false) {
        return $result;
    }

    // Fallback to config file
    $configKey = str_replace('retention_', '', $key);
    return $config['retention'][$configKey] ?? $default;
}

// Execute cleanup
$results = cleanupOldData();
echo "Cleanup completed: Articles={$results['articles_deleted']}, Logs={$results['logs_deleted']}\n";
```

#### 11.5.4 Manual Cleanup
Add cleanup section to Settings page:
```
┌──────────────────────────────────────────────┐
│ Data Cleanup                                  │
├──────────────────────────────────────────────┤
│ Articles:                                     │
│   Total: 1,234                                │
│   Older than 90 days: 156                    │
│   [Clean Up Old Articles]                     │
│                                              │
│ Logs:                                         │
│   Total: 12,450                               │
│   Older than 30 days: 3,200                  │
│   [Clean Up Old Logs]                         │
│                                              │
│ [Clean Up All Old Data]                       │
└──────────────────────────────────────────────┘
```

#### 11.5.5 Cleanup Confirmation
```
┌──────────────────────────────────────┐
│ Confirm Data Cleanup           [✕]  │
├──────────────────────────────────────┤
│ You are about to delete:             │
│                                      │
│ • 156 articles (older than 90 days)  │
│ • 3,200 logs (older than 30 days)    │
│                                      │
│ This action cannot be undone!        │
│                                      │
│ [Cancel] [Yes, Delete Old Data]      │
└──────────────────────────────────────┘
```

#### 11.5.6 Cron Job Setup
```bash
# Add to cPanel cron jobs
# Run daily at 2:00 AM
0 2 * * * php /path/to/unfurl/includes/cleanup.php > /dev/null 2>&1
```

#### 11.5.7 Archive Option (Future Enhancement)
Before deleting articles, optionally export to archive:
- Export old articles to JSON file
- Store in `/archives/` directory
- Compressed (gzip)
- Organized by month (e.g., `articles-2026-01.json.gz`)
- Keep archives for 1 year

#### 11.5.3 Archive
- Optional: Archive old logs to file before deletion
- Location: `/logs/archive/`
- Format: Compressed JSON (gzip)

### 11.6 Monitoring & Alerts

#### 11.6.1 Error Rate Monitoring
- Track errors per hour
- Alert if error rate > 10% of total logs
- Email notification to admin

#### 11.6.2 Processing Failures
- Alert if feed processing fails 3 times in a row
- Alert if >50% of articles fail to process

#### 11.6.3 System Health
- Monitor database connection errors
- Monitor memory usage warnings
- Monitor API authentication failures

### 11.7 Privacy & Security

#### 11.7.1 Data Privacy
- **Don't log:** Passwords, API secrets, sensitive user data
- **Do log:** IP addresses (for abuse prevention)
- **Anonymize:** User agents (truncate after 255 chars)

#### 11.7.2 Log Access
- Restrict log viewing to admin users (future)
- Protect log files with .htaccess
- Use HTTPS for log viewing pages

#### 11.7.3 Log Integrity
- Prevent log tampering
- Consider write-only log table (no updates/deletes except automated cleanup)

### 11.8 Logging Functions

#### 11.8.1 PHP Logger Class
```php
class Logger {
    public static function log($type, $level, $message, $context = []) {
        // Insert into database
        // Write to file (optional)
        // Send alert if ERROR level
    }

    public static function processing($level, $message, $context = []) {
        self::log('processing', $level, $message, $context);
    }

    public static function user($level, $message, $context = []) {
        self::log('user', $level, $message, $context);
    }

    public static function feed($level, $message, $context = []) {
        self::log('feed', $level, $message, $context);
    }

    public static function api($level, $message, $context = []) {
        self::log('api', $level, $message, $context);
    }

    public static function system($level, $message, $context = []) {
        self::log('system', $level, $message, $context);
    }
}
```

#### 11.8.2 Usage Examples
```php
// Processing
Logger::processing('INFO', 'Started processing feed', ['feed_id' => 1]);
Logger::processing('ERROR', 'URL decode failed', [
    'article_id' => 147,
    'error' => 'Timeout'
]);

// User activity
Logger::user('INFO', 'Feed created', [
    'feed_id' => 5,
    'topic' => 'UC Research',
    'ip' => $_SERVER['REMOTE_ADDR']
]);

// Feed requests
Logger::feed('INFO', 'Feed request', [
    'topic' => 'IBD Research',
    'cache_hit' => true,
    'response_time' => 12
]);

// API
Logger::api('INFO', 'API request completed', [
    'endpoint' => '/api.php',
    'feeds_processed' => 3,
    'duration' => 45
]);

// System
Logger::system('ERROR', 'Database connection failed', [
    'host' => 'localhost',
    'error' => 'Too many connections'
]);
```

### 11.5 Monitoring & Alerting

#### 11.5.1 Health Check Endpoint

**Purpose:** Verify application and dependencies are functioning

**Endpoint:** `GET /health.php`

**Response (Healthy):**
```json
{
  "status": "healthy",
  "timestamp": "2026-02-07T12:00:00Z",
  "checks": {
    "database": "ok",
    "disk_space": "ok",
    "last_successful_run": "2026-02-07T09:00:00Z"
  },
  "version": "1.0.0"
}
```

**Response (Unhealthy):**
```json
{
  "status": "unhealthy",
  "timestamp": "2026-02-07T12:00:00Z",
  "checks": {
    "database": "error: connection failed",
    "disk_space": "warning: 85% full",
    "last_successful_run": "2026-02-05T09:00:00Z"
  },
  "version": "1.0.0"
}
```

**Implementation:**
```php
// health.php
header('Content-Type: application/json');

$checks = [];

// Database connectivity
try {
    $db = Database::getInstance();
    $db->query("SELECT 1");
    $checks['database'] = 'ok';
} catch (Exception $e) {
    $checks['database'] = 'error: ' . $e->getMessage();
}

// Disk space
$free = disk_free_space('/');
$total = disk_total_space('/');
$percent_used = (($total - $free) / $total) * 100;

if ($percent_used > 90) {
    $checks['disk_space'] = 'critical: ' . round($percent_used) . '% full';
} elseif ($percent_used > 80) {
    $checks['disk_space'] = 'warning: ' . round($percent_used) . '% full';
} else {
    $checks['disk_space'] = 'ok';
}

// Last successful processing run
$lastRun = $db->query("
    SELECT MAX(updated_at)
    FROM feeds
    WHERE last_processed_at IS NOT NULL
")->fetchColumn();

$checks['last_successful_run'] = $lastRun ?: 'never';

// Overall status
$status = (
    strpos(json_encode($checks), 'error') === false &&
    strpos(json_encode($checks), 'critical') === false
) ? 'healthy' : 'unhealthy';

http_response_code($status === 'healthy' ? 200 : 503);

echo json_encode([
    'status' => $status,
    'timestamp' => date('c'),
    'checks' => $checks,
    'version' => '1.0.0'
]);
```

#### 11.5.2 Metrics to Track

**Processing Metrics:**
```php
// Track in database or metrics table
CREATE TABLE metrics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    metric_name VARCHAR(255) NOT NULL,
    metric_value DECIMAL(10,2) NOT NULL,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_name_time (metric_name, recorded_at)
);

// Record metrics
function recordMetric(string $name, float $value): void {
    global $db;
    $db->prepare("
        INSERT INTO metrics (metric_name, metric_value)
        VALUES (?, ?)
    ")->execute([$name, $value]);
}

// During processing
$startTime = microtime(true);
processArticle($article);
$duration = microtime(true) - $startTime;

recordMetric('article_processing_duration', $duration);
recordMetric('article_processing_success', 1);
```

**Key Metrics:**
- `article_processing_duration` - Time to process one article (seconds)
- `article_processing_success` - Successful article count
- `article_processing_failure` - Failed article count
- `duplicate_articles_skipped` - Duplicate detection count
- `api_requests_google` - Google API call count
- `feed_processing_duration` - Time to process entire feed
- `retry_queue_depth` - Number of articles waiting for retry

#### 11.5.3 Alerting Requirements

**Alert Conditions:**

1. **Error Rate Alert**
   - Trigger: > 5% of articles fail processing in last hour
   - Severity: WARNING
   - Action: Email notification

2. **Processing Stopped Alert**
   - Trigger: No successful processing in > 24 hours
   - Severity: CRITICAL
   - Action: Email + SMS notification

3. **Database Connection Alert**
   - Trigger: Health check fails 3 consecutive times
   - Severity: CRITICAL
   - Action: Email notification immediately

4. **Disk Space Alert**
   - Trigger: > 85% disk usage
   - Severity: WARNING
   - Action: Email notification

5. **Rate Limit Alert**
   - Trigger: > 10 rate limit errors in 1 hour
   - Severity: WARNING
   - Action: Email notification, pause processing

**Alert Implementation:**
```php
class AlertManager {
    private const ALERT_EMAIL = 'admin@example.com';

    public function checkAlerts(): void {
        $this->checkErrorRate();
        $this->checkProcessingStopped();
        $this->checkDiskSpace();
    }

    private function checkErrorRate(): void {
        global $db;

        $stats = $db->query("
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
            FROM articles
            WHERE processed_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ")->fetch();

        if ($stats['total'] > 0) {
            $errorRate = ($stats['failed'] / $stats['total']) * 100;

            if ($errorRate > 5) {
                $this->sendAlert(
                    'High Error Rate',
                    "Article processing error rate: {$errorRate}%\n" .
                    "Failed: {$stats['failed']} / {$stats['total']}",
                    'WARNING'
                );
            }
        }
    }

    private function checkProcessingStopped(): void {
        global $db;

        $lastSuccess = $db->query("
            SELECT MAX(last_processed_at) as last_run
            FROM feeds
        ")->fetchColumn();

        if ($lastSuccess) {
            $hoursSince = (time() - strtotime($lastSuccess)) / 3600;

            if ($hoursSince > 24) {
                $this->sendAlert(
                    'Processing Stopped',
                    "No successful processing in {$hoursSince} hours\n" .
                    "Last run: {$lastSuccess}",
                    'CRITICAL'
                );
            }
        }
    }

    private function sendAlert(
        string $title,
        string $message,
        string $severity
    ): void {
        // Email alert
        mail(
            self::ALERT_EMAIL,
            "[Unfurl {$severity}] {$title}",
            $message,
            "From: alerts@unfurl.example.com"
        );

        // Log alert
        Logger::system($severity, $title, ['message' => $message]);
    }
}

// Run in cron job
$alertManager = new AlertManager();
$alertManager->checkAlerts();
```

#### 11.5.4 Status Dashboard

**Requirements:**
- Display current system status
- Show recent processing runs
- Display error trends
- Show retry queue depth
- Display rate limit status

**Implementation:**
```php
// /status.php
function getSystemStatus(): array {
    global $db;

    return [
        'feeds' => [
            'total' => $db->query("SELECT COUNT(*) FROM feeds")->fetchColumn(),
            'enabled' => $db->query("SELECT COUNT(*) FROM feeds WHERE enabled = 1")->fetchColumn(),
            'last_run' => $db->query("SELECT MAX(last_processed_at) FROM feeds")->fetchColumn(),
        ],
        'articles' => [
            'total' => $db->query("SELECT COUNT(*) FROM articles")->fetchColumn(),
            'last_24h' => $db->query("SELECT COUNT(*) FROM articles WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetchColumn(),
            'failed_pending_retry' => $db->query("SELECT COUNT(*) FROM articles WHERE status = 'failed' AND retry_count < 3")->fetchColumn(),
        ],
        'processing' => [
            'success_rate_24h' => getSuccessRate(24),
            'avg_duration_24h' => getAvgDuration(24),
            'duplicates_skipped_24h' => getDuplicatesSkipped(24),
        ],
        'health' => [
            'database' => isDatabaseHealthy(),
            'disk_usage' => getDiskUsagePercent(),
        ]
    ];
}
```

**Dashboard UI:**
```html
<!-- Status page -->
<div class="status-dashboard">
    <h1>Unfurl System Status</h1>

    <div class="status-card">
        <h2>Overall Health: <span class="badge success">Healthy</span></h2>
        <p>Last updated: 2 minutes ago</p>
    </div>

    <div class="metrics-grid">
        <div class="metric">
            <h3>Processing Success Rate</h3>
            <div class="value">94.5%</div>
            <p>Last 24 hours</p>
        </div>

        <div class="metric">
            <h3>Articles Processed</h3>
            <div class="value">247</div>
            <p>Last 24 hours</p>
        </div>

        <div class="metric">
            <h3>Retry Queue</h3>
            <div class="value warning">12</div>
            <p>Pending retries</p>
        </div>

        <div class="metric">
            <h3>Avg Processing Time</h3>
            <div class="value">4.2s</div>
            <p>Per article</p>
        </div>
    </div>

    <div class="recent-runs">
        <h2>Recent Processing Runs</h2>
        <table>
            <tr>
                <th>Feed</th>
                <th>Time</th>
                <th>Processed</th>
                <th>Success</th>
                <th>Failed</th>
            </tr>
            <!-- Rows... -->
        </table>
    </div>
</div>
```

---

## 12. Operational & Non-Functional Requirements

### 12.1 Performance Requirements

#### 12.1.1 Response Time
- **Page Load:** < 2 seconds for article list page (with 20 items)
- **Search Results:** < 1 second for article search
- **Feed Processing:** < 5 seconds per article average
- **RSS Feed Generation:** < 500ms for 20 items (cached)
- **Database Queries:** < 100ms for most queries

#### 12.1.2 Throughput
- **Concurrent Users:** Support 10+ simultaneous users
- **Feed Processing:** Process 100 articles/hour minimum
- **RSS Feed Requests:** Handle 100 requests/minute
- **API Calls:** Process 10 feeds in single cron job (< 10 minutes total)

#### 12.1.3 Resource Limits
- **PHP Memory:** Limit to 256MB per request
- **PHP Execution Time:** Max 120 seconds for processing, 30 seconds for web requests
- **Database Connections:** Max 10 concurrent connections
- **Disk Space:** Monitor storage, alert at 80% capacity

#### 12.1.4 Optimization Strategies
- **Database Indexing:** All foreign keys, search fields, filter columns
- **Query Optimization:** Use EXPLAIN to optimize slow queries
- **Caching:** Cache RSS feeds (5 minutes), cache article counts
- **Pagination:** Limit results to 20-100 items per page
- **Lazy Loading:** Load images on demand

### 12.2 Scalability

#### 12.2.1 Current Design (v1)
- **Expected Volume:** 10-50 feeds, 1,000-10,000 articles
- **Growth Capacity:** Can handle up to 100 feeds, 100,000 articles
- **User Load:** Single user (admin)

#### 12.2.2 Future Scalability (Post-v1)
- **Horizontal Scaling:** Separate database from web server
- **Load Balancing:** Add multiple web servers if needed
- **Queue System:** Background job processing (Redis, RabbitMQ)
- **CDN:** Cache static assets, RSS feeds
- **Database Optimization:** Partitioning, read replicas

### 12.3 Reliability & Availability

#### 12.3.1 Uptime Target
- **Goal:** 99% uptime (< 7 hours downtime/month)
- **Acceptable:** 95% uptime (< 36 hours downtime/month)

#### 12.3.2 Error Handling
- **Graceful Degradation:** If feed processing fails, system continues
- **Retry Logic:** Failed articles retry up to 3 times
- **Fallback:** If batchexecute API fails, log error and continue
- **User Feedback:** Clear error messages, no cryptic technical errors

#### 12.3.3 Data Integrity
- **Transactions:** Use database transactions for critical operations
- **Validation:** Validate all input before database insertion
- **Constraints:** Foreign keys, unique constraints prevent bad data
- **Backup:** Regular database backups (see section 12.4)

### 12.4 Backup & Recovery

#### 12.4.1 Backup Strategy
- **Database:** Daily automated backups via cPanel
- **Frequency:** Every 24 hours (2:00 AM)
- **Retention:** Keep 7 daily backups, 4 weekly backups
- **Storage:** Bluehost backup system + optional offsite backup
- **Files:** Backup PHP files, config files (weekly)

#### 12.4.2 Recovery Procedures
```sql
-- Restore database from backup
mysql unfurl_db < backup-2026-02-07.sql

-- Verify data integrity
SELECT COUNT(*) FROM feeds;
SELECT COUNT(*) FROM articles;
SELECT COUNT(*) FROM logs;

-- Test critical functions
-- - View articles page
-- - Process feed
-- - Generate RSS feed
```

#### 12.4.3 Disaster Recovery Plan
1. **Identify Issue:** System down, data corrupted, etc.
2. **Assess Impact:** What data was lost? Since when?
3. **Restore from Backup:** Use most recent clean backup
4. **Verify System:** Test all functions
5. **Document Incident:** Log what happened, how fixed
6. **Prevent Future:** Update procedures, add monitoring

### 12.5 Security Requirements

#### 12.5.1 Authentication (Future)
- **Admin Access:** Password-protected admin panel (post-v1)
- **API Access:** Secret key required for API calls
- **Session Management:** Secure sessions (HTTPOnly, Secure flags)

#### 12.5.2 Input Validation
- **SQL Injection:** Use prepared statements (PDO/mysqli)
- **XSS Prevention:** Escape all output (htmlspecialchars)
- **CSRF Protection:** Tokens for forms (future)
- **URL Validation:** Validate URLs before processing

#### 12.5.3 Data Protection
- **HTTPS:** Enforce SSL/TLS for all connections
- **Secret Storage:** Store API secrets in config file (not database)
- **File Permissions:** 644 for files, 755 for directories
- **Database Access:** Limit database user to specific operations

#### 12.5.4 Rate Limiting
- **API Endpoint:** Max 1 request/minute
- **RSS Feed:** Max 100 requests/minute per IP
- **Login Attempts:** Max 5 failed attempts/hour (future)

### 12.6 Usability Requirements

#### 12.6.1 User Interface
- **Intuitive:** Clear navigation, obvious actions
- **Consistent:** Same UI patterns throughout
- **Feedback:** Immediate feedback for all actions
- **Help Text:** Tooltips, placeholder text for guidance
- **Error Messages:** Actionable, non-technical language

#### 12.6.2 Accessibility
- **Keyboard Navigation:** All functions accessible via keyboard
- **Screen Readers:** Semantic HTML, ARIA labels where needed
- **Color Contrast:** WCAG AA compliance minimum
- **Font Size:** Minimum 16px, scalable text
- **Mobile-Friendly:** Responsive design, touch targets 44x44px+

#### 12.6.3 Browser Compatibility
- **Desktop:** Chrome 90+, Firefox 88+, Safari 14+, Edge 90+
- **Mobile:** iOS Safari 14+, Chrome Mobile 90+
- **Tablet:** iPad Safari, Android Chrome

### 12.7 Documentation Requirements

#### 12.7.1 User Documentation
- **Installation Guide:** Step-by-step setup instructions
- **User Manual:** How to use each feature
- **FAQ:** Common questions and answers
- **Troubleshooting:** Common issues and solutions
- **Video Tutorials:** Optional walkthrough videos (future)

#### 12.7.2 Technical Documentation
- **API Documentation:** Endpoint specifications, examples
- **Database Schema:** Table definitions, relationships
- **Code Documentation:** PHPDoc comments for functions
- **Architecture Diagram:** System overview, data flow
- **Deployment Guide:** How to deploy updates

#### 12.7.3 Documentation Location
- **README.md:** Project overview, quick start
- **docs/** directory:
  - `/docs/installation.md`
  - `/docs/user-guide.md`
  - `/docs/api-reference.md`
  - `/docs/troubleshooting.md`
  - `/docs/changelog.md`

### 12.8 Maintenance & Support

#### 12.8.1 Regular Maintenance
- **Daily:** Cron jobs (processing, cleanup)
- **Weekly:** Review logs for errors
- **Monthly:** Database optimization, check disk space
- **Quarterly:** Review and update dependencies
- **Annually:** Security audit, performance review

#### 12.8.2 Update Procedures
```bash
# 1. Backup before update
mysqldump unfurl_db > backup-pre-update.sql

# 2. Test in development first
# (on local environment)

# 3. Deploy to production
# - Upload new files via FTP
# - Run database migrations if needed
# - Clear cache
# - Test critical functions

# 4. Monitor for issues
# - Check logs
# - Test user workflows
# - Verify RSS feeds working
```

#### 12.8.3 Support Channels (Future)
- **Email:** support@example.com
- **Documentation:** https://unfurl.example.com/docs
- **GitHub Issues:** For bug reports (if open source)
- **Community Forum:** User discussions (future)

### 12.9 Compliance & Legal

#### 12.9.1 Data Privacy
- **User Data:** Minimal collection (IP addresses in logs only)
- **GDPR Compliance:** Not applicable (no EU users expected)
- **Privacy Policy:** Disclose data collection practices
- **Data Deletion:** Automatic cleanup per retention policy

#### 12.9.2 Terms of Use
- **RSS Feeds:** Respect robots.txt, rate limits
- **Google News:** Use within terms of service
- **Attribution:** Credit original article sources
- **Commercial Use:** Define acceptable use

#### 12.9.3 Content Rights
- **Fair Use:** Article excerpts for personal use
- **Copyright:** Respect original content copyright
- **Images:** Link to images, don't host without permission
- **Attribution:** Always link to original source

### 12.10 Monitoring & Alerts

#### 12.10.1 System Monitoring
- **Uptime:** Ping test every 5 minutes
- **Disk Space:** Alert at 80% full
- **Database Size:** Monitor growth, alert at 1GB
- **PHP Errors:** Log to file, review weekly

#### 12.10.2 Application Monitoring
- **Processing Failures:** Alert if > 50% fail rate
- **API Errors:** Alert after 3 consecutive failures
- **RSS Feed Errors:** Monitor cache hit rate
- **Performance:** Alert if page load > 5 seconds

#### 12.10.3 Alert Methods
- **Email:** Send to admin email
- **Log File:** Critical errors logged to separate file
- **Dashboard:** Status indicators on admin dashboard (future)
- **SMS:** Critical alerts via SMS service (future)

### 12.11 Configuration Management

#### 12.11.1 Environment-Specific Configs
```php
// config.production.php
return [
    'app' => [
        'debug' => false,
        'log_level' => 'ERROR'
    ]
];

// config.development.php
return [
    'app' => [
        'debug' => true,
        'log_level' => 'DEBUG'
    ]
];
```

#### 12.11.2 Version Control
- **Git:** Track all code in repository
- **Branches:** main (production), develop (testing)
- **Commits:** Descriptive commit messages
- **Tags:** Tag releases (v1.0.0, v1.1.0, etc.)

#### 12.11.3 Deployment Tracking
- Keep CHANGELOG.md with all changes
- Document breaking changes
- Note database migrations needed

---

## 13. Future Enhancements (Post-v1)

### 10.1 Phase 2 Features
- **Export Functionality:** Export articles as JSON, CSV, RSS
- **Webhook Integration:** Send articles to external services (Zapier, IFTTT)
- **Email Notifications:** Alerts when processing completes or fails
- **Article Deduplication:** Detect duplicate articles across feeds
- **Content Archiving:** Store full article HTML/text

### 10.2 Phase 3 Features
- **Multi-User Support:** User accounts, permissions, separate workspaces
- **Advanced Search:** Full-text search, saved searches, filters
- **Analytics Dashboard:** Charts/graphs of processing stats
- **Custom Fields:** User-defined metadata fields
- **API v2:** RESTful API for external integrations

### 10.3 Technical Improvements
- **Browser Automation:** Migrate to external service if batchexecute breaks
- **Caching:** Cache RSS feeds, reduce redundant requests
- **Queue System:** Background job queue for processing
- **Monitoring:** Health checks, uptime monitoring
- **Logging:** Structured logging, error tracking (Sentry)

---

## 11. Constraints & Assumptions

### 11.1 Constraints
- **Shared Hosting:** Cannot run headless browsers locally
- **Resource Limits:** PHP memory limit, execution time limits
- **Database Size:** Monitor storage, may need cleanup strategy
- **Google's API:** Batchexecute is undocumented, may change
- **SSL/HTTPS:** Required for API security

### 11.2 Assumptions
- User has access to Bluehost cPanel
- MySQL database available
- PHP 7.4+ with cURL extension
- Google News RSS feeds remain accessible
- Batchexecute API remains functional (or fallback available)

### 11.3 Risks
- **Google Changes:** URL encoding or API changes break decoding
  - **Mitigation:** Monitor for failures, implement fallback, stay updated on changes
- **Rate Limiting:** Google blocks excessive requests
  - **Mitigation:** Respect rate limits, add delays, use rotating user agents
- **Shared Hosting Limitations:** Resource constraints cause timeouts
  - **Mitigation:** Optimize queries, process in batches, monitor performance
- **Data Growth:** Database grows too large
  - **Mitigation:** Implement archiving, cleanup old articles, monitor disk usage

---

## 12. Success Metrics

### 12.1 Technical Metrics
- **Decode Success Rate:** >95% of Google News URLs successfully decoded
- **Processing Speed:** <5 seconds per article average
- **Uptime:** >99% availability for web interface
- **Error Rate:** <5% of processing attempts fail

### 12.2 User Experience Metrics
- **Page Load Time:** <2 seconds for article list page
- **Mobile Usability:** Fully functional on iPad/mobile browsers
- **Ease of Use:** User can configure first feed in <2 minutes

### 12.3 Business Metrics
- **Adoption:** Successfully process 100+ articles/month
- **Reliability:** Zero data loss incidents
- **Maintainability:** Clear code, documented, easy to update

---

## 13. Acceptance Criteria

### 13.1 Must Have (v1.0)
- ✅ Create, edit, delete feed configurations
- ✅ Process feeds manually via web interface
- ✅ Process feeds automatically via cron
- ✅ Store articles in database
- ✅ View, search, filter articles
- ✅ Edit article metadata
- ✅ Delete articles (individual or bulk)
- ✅ Works on iPad/mobile browsers
- ✅ Successfully decode Google News URLs (95%+ success)

### 13.2 Should Have (v1.0)
- ✅ Retry failed articles (ProcessingQueue with exponential backoff)
- ✅ Progress indicator during processing (real-time per-article progress)
- ✅ Responsive design for all screen sizes (mobile-first design system)
- ✅ Error logging and display (database logging with web interface at `/logs`)
- ✅ Basic documentation (comprehensive docs in `docs/` directory)

### 13.3 Could Have (Future)
- Export functionality
- Webhook integrations
- Email notifications
- Multi-user support
- Advanced analytics

### 13.4 Won't Have (v1.0)
- Full article text extraction
- Content summarization
- Social media posting
- Advanced reporting/analytics
- Mobile app (native)

---

## 14. Open Questions & Decisions Needed

### 14.1 Open Questions
1. **Archiving Strategy:** How long to keep articles? Auto-delete after X days?
2. **Image Storage:** Store images locally or just URLs?
3. **User Authentication:** Needed for v1 or add later?
4. **Duplicate Handling:** What if same article appears in multiple feeds?
5. **Feed Validation:** Should we validate RSS URLs before saving?

### 14.2 Decisions Needed
1. **Batchexecute Fallback:** If API fails, use external service or fail gracefully?
2. **Mobile UI:** Full feature parity or simplified mobile view?
3. **Error Notifications:** Email alerts on failures or just log?
4. **Database Cleanup:** Manual or automatic article pruning?
5. **API Rate Limiting:** How strict? Per-IP or global?

---

## 15. Appendix

### 15.1 Glossary
- **Google News RSS:** RSS feed provided by Google News aggregating articles
- **Obfuscated URL:** Encoded URL that hides the actual destination
- **Batchexecute:** Google's internal RPC protocol for various services
- **og:image:** Open Graph meta tag specifying article featured image
- **Metadata:** Article information (title, description, images, author)
- **cPanel:** Web hosting control panel (Bluehost uses this)

### 15.2 References
- Google News RSS Documentation: https://support.google.com/news/publisher-center/
- Open Graph Protocol: https://ogp.me/
- Batchexecute Research: GitHub gists and community documentation

### 15.3 Revision History
| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 0.1.0 | 2026-02-07 | Initial | First draft based on POC |
| 0.2.0 | 2026-02-07 | Iteration | Added web UI, database, scheduling requirements |

---

## 16. Appendix A: Frontend Design Review

**Reviewer:** Frontend Design Skill
**Date:** 2026-02-07
**Focus:** UI/UX, Visual Design, User Experience

### 16.1 Overall Assessment

The requirements document provides solid functional specifications but lacks **visual identity and design direction**. The UI wireframes are functional but generic. For a tool named "Unfurl," there's an opportunity to create a distinctive, memorable interface that reflects the act of revealing/unwrapping hidden information.

**Current State:** 📊 Functional specifications with basic wireframes
**Recommendation:** 🎨 Define a bold aesthetic direction that makes Unfurl visually distinctive

### 16.2 Critical Design Gaps

#### 16.2.1 Missing Visual Identity
**Issue:** No defined color palette, typography, or aesthetic direction
**Impact:** Risk of generic "admin panel" look that lacks personality
**Recommendation:**
```css
/* Suggested Theme: "Unfolding Revelation" */
:root {
  /* Primary: Deep teal suggesting depth/revelation */
  --color-primary: #0D7377;
  --color-primary-light: #14FFEC;
  --color-primary-dark: #053B3E;

  /* Accent: Warm amber for "aha!" moments */
  --color-accent: #F4A261;
  --color-accent-light: #F6BD8B;

  /* Neutrals: Clean slate for content */
  --color-bg: #FAFAFA;
  --color-surface: #FFFFFF;
  --color-text: #1A1A1A;
  --color-text-muted: #6B6B6B;

  /* Status colors */
  --color-success: #2A9D8F;
  --color-warning: #E9C46A;
  --color-error: #E76F51;

  /* Typography */
  --font-display: 'Space Grotesk', sans-serif; /* For headings */
  --font-body: 'Inter', system-ui, sans-serif; /* For content */
  --font-mono: 'JetBrains Mono', monospace; /* For URLs/code */
}
```

**Alternative Direction: Brutalist/Raw**
- Black & white base with single accent color
- Monospace typography throughout
- Sharp corners, no shadows
- Grid-based layouts with intentional breaks
- Exposed "behind the scenes" aesthetic (fitting for URL decoder)

#### 16.2.2 Typography Not Specified
**Issue:** No font choices defined
**Current:** Generic system fonts assumed
**Recommendation:**
- **Display/Headers:** Space Grotesk (geometric, modern, tech-forward) OR Poppins (friendly, approachable)
- **Body Text:** Inter (neutral, readable) OR System stack for performance
- **Monospace:** JetBrains Mono for URLs, API keys, technical data
- **Font Loading:** Use `font-display: swap` with FOUT strategy

**Hierarchy:**
```css
h1 { font-size: 2.5rem; font-weight: 700; line-height: 1.2; }
h2 { font-size: 2rem; font-weight: 600; line-height: 1.3; }
h3 { font-size: 1.5rem; font-weight: 600; line-height: 1.4; }
body { font-size: 1rem; line-height: 1.6; }
.text-small { font-size: 0.875rem; }
```

#### 16.2.3 Animation & Motion Strategy Missing
**Issue:** No micro-interactions or loading states defined
**Impact:** Interface feels static and unresponsive
**Recommendation:**

**Loading States:**
- Processing feeds: Skeleton screens with shimmer effect
- URL decoding: Progress bar with pulse animation
- Article fetch: Staggered card reveals (animation-delay)

**Micro-interactions:**
```css
/* Hover states */
.card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgba(13, 115, 119, 0.15);
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

/* Button feedback */
.btn:active {
  transform: scale(0.98);
}

/* Feed processing animation */
@keyframes unfurl {
  0% { clip-path: inset(0 100% 0 0); }
  100% { clip-path: inset(0 0 0 0); }
}

.article-reveal {
  animation: unfurl 0.6s cubic-bezier(0.65, 0, 0.35, 1) forwards;
}
```

**Page Transitions:**
- Fade in + slide up for page loads (100ms delay per element)
- Cross-fade between states (200ms duration)
- Morphing transitions for modal open/close

#### 16.2.4 Mobile-First Not Emphasized
**Issue:** "Responsive design" mentioned but not prioritized
**Current:** Desktop-first wireframes with mobile as adaptation
**Recommendation:** Design mobile-first, enhance for desktop

**Mobile Critical Path:**
1. View feeds (simplified cards)
2. Trigger processing (large tap targets)
3. View results (scannable list)
4. Access settings (drawer navigation)

**Breakpoints:**
```css
/* Mobile: 320-640px */
/* Tablet: 641-1024px */
/* Desktop: 1025px+ */

/* Use container queries where possible */
@container (min-width: 768px) {
  .article-grid { grid-template-columns: repeat(2, 1fr); }
}
```

### 16.3 Specific Page Recommendations

#### 16.3.1 Feeds Page (Dashboard)
**Current:** Basic table/list
**Recommendation:** Card-based grid with visual status indicators

```html
<!-- Visual concept -->
<div class="feed-card" data-status="active">
  <div class="feed-header">
    <h3>IBD Research</h3>
    <span class="status-badge">● Active</span>
  </div>

  <div class="feed-stats">
    <div class="stat">
      <span class="stat-value">247</span>
      <span class="stat-label">Articles</span>
    </div>
    <div class="stat">
      <span class="stat-value">2h ago</span>
      <span class="stat-label">Last Run</span>
    </div>
  </div>

  <div class="feed-actions">
    <button class="btn-primary">▶ Run Now</button>
    <button class="btn-icon">⚙️</button>
  </div>

  <!-- Progress bar for active processing -->
  <div class="feed-progress" data-progress="45">
    <div class="progress-fill"></div>
    <span class="progress-text">Processing: 12/27</span>
  </div>
</div>
```

**Visual Enhancements:**
- Color-coded status (green dot = active, gray = paused, amber = processing)
- Live progress bars during processing
- Skeleton loading states
- Hover reveals additional options

#### 16.3.2 Articles Page
**Current:** Table with pagination
**Recommendation:** Hybrid view (cards on mobile, table on desktop)

**Key Features:**
- **Filter pills** (not dropdowns) for better touch targets
- **Infinite scroll** OR **Load more** (not traditional pagination on mobile)
- **Quick preview** on card tap (modal overlay)
- **Bulk select** with floating action bar

```css
/* Mobile: Stacked cards */
.article-list-mobile .article {
  display: flex;
  flex-direction: column;
  padding: 1rem;
  border-bottom: 1px solid var(--color-border);
}

/* Desktop: Data table */
@media (min-width: 1024px) {
  .article-list-desktop {
    display: table;
    width: 100%;
  }
}
```

#### 16.3.3 Process Page (Real-time)
**Current:** Basic progress indicator
**Recommendation:** Engaging real-time visualization

**Visual Concept:**
- **Feed queue:** Vertical timeline showing queued feeds
- **Current processing:** Large card with animated progress
- **Results stream:** Articles revealed as they're processed
- **Success/failure visualization:** Color-coded badges, not just text

```html
<div class="processing-view">
  <div class="queue-panel">
    <h3>Queue (3)</h3>
    <div class="queue-item active">IBD Research</div>
    <div class="queue-item">Dementia News</div>
    <div class="queue-item">Clinical Trials</div>
  </div>

  <div class="processing-main">
    <div class="current-article">
      <div class="article-preview shimmer">
        <!-- Shows article being fetched with shimmer effect -->
      </div>
      <div class="progress-ring">
        <svg><!-- Circular progress --></svg>
        <span class="progress-percent">67%</span>
      </div>
    </div>
  </div>

  <div class="results-stream">
    <div class="result success" style="animation-delay: 0.1s">
      ✓ Article 1
    </div>
    <!-- More results appear with stagger -->
  </div>
</div>
```

#### 16.3.4 Settings Page
**Current:** Form-based layout
**Recommendation:** Accordion/section-based with inline editing

**Issues with Current Wireframe:**
- Too much information visible at once
- No visual hierarchy between sections
- API keys listed with raw values (security concern)
- Emoji icons (👁️, ✏️, 🗑️) not accessible

**Improved Pattern:**
```html
<section class="settings-section">
  <header class="section-header" data-state="expanded">
    <h2>API Keys</h2>
    <button class="toggle-section">⌃</button>
  </header>

  <div class="section-content">
    <div class="api-key-item">
      <div class="key-info">
        <h4>Main Cron Job</h4>
        <p class="key-description">Daily feed processing</p>
        <code class="key-value masked">abc...xyz</code>
      </div>

      <div class="key-meta">
        <span class="badge success">Active</span>
        <time>Last used: 2h ago</time>
      </div>

      <div class="key-actions">
        <button class="btn-text" aria-label="Show full key">
          <svg><!-- Eye icon --></svg>
          Show
        </button>
        <button class="btn-text" aria-label="Edit key">Edit</button>
        <button class="btn-text danger" aria-label="Delete key">Delete</button>
      </div>
    </div>
  </div>
</section>
```

### 16.4 Component-Level Recommendations

#### 16.4.1 Buttons
**Current:** Not specified
**Recommendation:** Clear visual hierarchy

```css
/* Primary action */
.btn-primary {
  background: var(--color-primary);
  color: white;
  padding: 0.75rem 1.5rem;
  border-radius: 8px;
  font-weight: 600;
  box-shadow: 0 2px 8px rgba(13, 115, 119, 0.2);
}

/* Secondary action */
.btn-secondary {
  background: transparent;
  border: 2px solid var(--color-primary);
  color: var(--color-primary);
}

/* Destructive action */
.btn-danger {
  background: var(--color-error);
  color: white;
}

/* Text-only action */
.btn-text {
  background: none;
  color: var(--color-primary);
  padding: 0.5rem 1rem;
}
```

#### 16.4.2 Form Inputs
**Current:** Not specified
**Recommendation:** Accessible, touch-friendly

```css
.input-field {
  width: 100%;
  padding: 0.875rem;
  border: 2px solid var(--color-border);
  border-radius: 8px;
  font-size: 1rem;
  transition: border-color 0.2s;
}

.input-field:focus {
  outline: none;
  border-color: var(--color-primary);
  box-shadow: 0 0 0 3px rgba(13, 115, 119, 0.1);
}

/* Touch targets: minimum 44x44px */
.input-field,
.btn {
  min-height: 44px;
}
```

#### 16.4.3 Status Badges
**Recommendation:** Consistent visual language

```css
.badge {
  display: inline-flex;
  align-items: center;
  padding: 0.25rem 0.75rem;
  border-radius: 12px;
  font-size: 0.875rem;
  font-weight: 500;
}

.badge.success { background: #D4F4DD; color: #1A6B3D; }
.badge.warning { background: #FEF3C7; color: #92400E; }
.badge.error { background: #FEE2E2; color: #991B1B; }
.badge.info { background: #DBEAFE; color: #1E40AF; }

/* Dot indicator for active states */
.badge::before {
  content: '';
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: currentColor;
  margin-right: 0.5rem;
}
```

#### 16.4.4 Data Tables (Desktop)
**Current:** Standard table
**Recommendation:** Enhanced with sorting, hover states

```css
.data-table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
}

.data-table th {
  background: var(--color-surface);
  position: sticky;
  top: 0;
  padding: 1rem;
  text-align: left;
  font-weight: 600;
  border-bottom: 2px solid var(--color-border);
}

.data-table td {
  padding: 1rem;
  border-bottom: 1px solid var(--color-border-light);
}

.data-table tr:hover {
  background: var(--color-bg);
}

/* Sortable columns */
.data-table th.sortable {
  cursor: pointer;
  user-select: none;
}

.data-table th.sortable:hover {
  background: var(--color-bg);
}
```

### 16.5 Accessibility Concerns

#### 16.5.1 Color Contrast
**Issue:** No contrast ratios specified
**Recommendation:** WCAG 2.1 AA compliance minimum

- Text: 4.5:1 contrast ratio
- Large text (18px+): 3:1 contrast ratio
- Interactive elements: 3:1 contrast ratio

**Test all color combinations:**
```
Primary (#0D7377) on white: ✓ 5.2:1
Accent (#F4A261) on white: ⚠️ 2.8:1 (fails - needs darker variant)
Text (#1A1A1A) on white: ✓ 15.8:1
```

#### 16.5.2 Keyboard Navigation
**Issue:** Not explicitly defined
**Recommendation:** Full keyboard support required

- Tab order follows visual hierarchy
- Focus indicators visible (not `outline: none`)
- Skip links for screen readers
- Escape key closes modals
- Enter/Space activates buttons

```css
/* Visible focus indicator */
*:focus-visible {
  outline: 3px solid var(--color-primary);
  outline-offset: 2px;
}

/* Skip to main content */
.skip-link {
  position: absolute;
  top: -40px;
  left: 0;
  background: var(--color-primary);
  color: white;
  padding: 0.5rem 1rem;
  z-index: 100;
}

.skip-link:focus {
  top: 0;
}
```

#### 16.5.3 Screen Reader Support
**Issue:** No ARIA labels specified
**Recommendation:** Semantic HTML + ARIA where needed

```html
<!-- Status announcements -->
<div role="status" aria-live="polite" class="sr-only">
  Processing feed: 12 of 27 articles complete
</div>

<!-- Loading states -->
<button aria-busy="true" aria-label="Processing...">
  <span aria-hidden="true">⟳</span>
  Processing
</button>

<!-- Icon buttons -->
<button aria-label="Delete article">
  <svg aria-hidden="true"><!-- trash icon --></svg>
</button>
```

### 16.6 Performance Optimizations

#### 16.6.1 Critical CSS
**Recommendation:** Inline critical styles for above-fold content

```html
<style>
  /* Inline in <head> - navigation, hero, first paint */
  :root { /* CSS variables */ }
  body { /* Base styles */ }
  .header { /* Navigation */ }
  .hero { /* First screen */ }
</style>

<link rel="stylesheet" href="/css/main.css" media="print" onload="this.media='all'">
```

#### 16.6.2 Lazy Loading
**Recommendation:** Progressive enhancement

- Images: `loading="lazy"` attribute
- Below-fold sections: Intersection Observer
- Infinite scroll: Load more on scroll proximity

```javascript
// Intersection Observer for lazy sections
const observer = new IntersectionObserver((entries) => {
  entries.forEach(entry => {
    if (entry.isIntersecting) {
      entry.target.classList.add('visible');
      observer.unobserve(entry.target);
    }
  });
});

document.querySelectorAll('.lazy-section').forEach(el => {
  observer.observe(el);
});
```

#### 16.6.3 Animation Performance
**Recommendation:** GPU-accelerated transforms only

```css
/* Good: Uses compositor */
.card:hover {
  transform: translateY(-4px);
  will-change: transform;
}

/* Bad: Triggers layout */
.card:hover {
  margin-top: -4px; /* DON'T */
}

/* Limit will-change usage */
.card:hover {
  will-change: transform;
}
.card:not(:hover) {
  will-change: auto;
}
```

### 16.7 Mobile-Specific Enhancements

#### 16.7.1 Touch Gestures
**Recommendation:** Beyond tap - swipe actions

```javascript
// Swipe to delete on mobile
let startX = 0;
card.addEventListener('touchstart', e => {
  startX = e.touches[0].clientX;
});

card.addEventListener('touchend', e => {
  const endX = e.changedTouches[0].clientX;
  const diff = startX - endX;

  if (diff > 100) {
    // Swipe left: reveal delete action
    card.classList.add('swiped-left');
  }
});
```

#### 16.7.2 Pull-to-Refresh
**Recommendation:** Native gesture for feed refresh

```javascript
let startY = 0;
let isPulling = false;

document.addEventListener('touchstart', e => {
  if (window.scrollY === 0) {
    startY = e.touches[0].clientY;
    isPulling = true;
  }
});

document.addEventListener('touchmove', e => {
  if (isPulling) {
    const pullDistance = e.touches[0].clientY - startY;
    if (pullDistance > 100) {
      // Trigger refresh
      refreshFeeds();
    }
  }
});
```

#### 16.7.3 Bottom Sheet Navigation (Mobile)
**Recommendation:** Modal actions slide from bottom

```css
.bottom-sheet {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  background: white;
  border-radius: 24px 24px 0 0;
  padding: 1.5rem;
  transform: translateY(100%);
  transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.bottom-sheet.open {
  transform: translateY(0);
}

/* Handle indicator */
.bottom-sheet::before {
  content: '';
  display: block;
  width: 40px;
  height: 4px;
  background: var(--color-border);
  border-radius: 2px;
  margin: 0 auto 1rem;
}
```

### 16.8 Dark Mode Consideration

**Issue:** Not addressed in requirements
**Recommendation:** Add dark mode support (optional v1, required v2)

```css
@media (prefers-color-scheme: dark) {
  :root {
    --color-bg: #1A1A1A;
    --color-surface: #2A2A2A;
    --color-text: #FAFAFA;
    --color-text-muted: #A0A0A0;
    --color-border: #3A3A3A;

    /* Adjust primary colors for dark mode */
    --color-primary: #14FFEC;
    --color-primary-dark: #0D7377;
  }
}

/* Manual toggle */
[data-theme="dark"] {
  /* Same variables as media query */
}
```

### 16.9 Visual Design Deliverables Needed

**Before development starts, create:**

1. **Style Guide** (1-2 pages)
   - Color palette with hex codes
   - Typography scale and font families
   - Spacing/sizing system (4px, 8px, 16px, 24px, etc.)
   - Border radius standards
   - Shadow depths

2. **Component Library** (Figma/Sketch)
   - Buttons (all states: default, hover, active, disabled)
   - Form inputs (default, focus, error, success)
   - Cards, badges, tables
   - Navigation elements
   - Modals and overlays

3. **High-Fidelity Mockups** (key pages)
   - Dashboard/Feeds page (desktop + mobile)
   - Articles page (desktop + mobile)
   - Processing page (real-time states)
   - Settings page

4. **Motion Specification**
   - Easing curves to use
   - Animation durations
   - Loading states
   - Transition patterns

### 16.10 Summary & Recommendations

**Strengths:**
✅ Comprehensive functional requirements
✅ Clear user workflows defined
✅ Good consideration of edge cases
✅ Mobile accessibility mentioned

**Critical Gaps:**
❌ No visual identity or aesthetic direction
❌ Typography not specified
❌ Color palette undefined
❌ Animation/motion strategy missing
❌ Component design system needed
❌ Accessibility requirements incomplete

**Priority Actions (Before Development):**

1. **Define Visual Identity** (1-2 days)
   - Choose aesthetic direction (modern utility vs. brutalist vs. refined)
   - Create color palette (5-7 colors + neutrals)
   - Select typography (2-3 font families)

2. **Design Component System** (2-3 days)
   - Button variations and states
   - Form inputs and validation
   - Cards, tables, lists
   - Navigation patterns (desktop + mobile)

3. **Create High-Fi Mockups** (3-5 days)
   - Dashboard, Articles, Process, Settings
   - Desktop and mobile views
   - Interactive states and transitions

4. **Document Design System** (1 day)
   - CSS variables
   - Component usage guidelines
   - Accessibility requirements

**Estimated Design Effort:** 7-11 days before development
**Recommended Tool:** Figma (collaborative, web-based, component libraries)

**Key Principle:**
> "Unfurl should feel like revealing hidden treasure, not just another admin panel. The interface should unfold information progressively with purpose and delight."

---

## 17. Appendix B: Code Review & Architecture Analysis

**Reviewer:** Code Review Skill (Sentry Engineering Practices)
**Date:** 2026-02-07
**Focus:** Security, Performance, Architecture, Testing, Long-term Maintainability

### 17.1 Overall Assessment

The requirements document shows solid thinking about functionality but has **critical gaps in security, error handling, and architectural details**. Before implementation, several high-risk areas need specification to prevent production issues.

**Risk Level:** 🟡 MEDIUM-HIGH
- Security considerations incomplete
- Database design has potential performance issues
- Error handling not fully specified
- No migration strategy defined

### 17.2 CRITICAL Security Issues

#### 17.2.1 SQL Injection Risks
**Issue:** No explicit requirement for parameterized queries
**Risk:** HIGH - Database compromise, data theft

**Current State:** Database queries not specified
**Required:**
```php
// NEVER do this
$sql = "SELECT * FROM articles WHERE topic = '$topic'";

// ALWAYS use prepared statements
$stmt = $pdo->prepare("SELECT * FROM articles WHERE topic = ?");
$stmt->execute([$topic]);
```

**Recommendation:** Add explicit requirement in Section 7 (API Requirements):
- All database queries MUST use prepared statements
- No string concatenation or interpolation in SQL
- Use PDO with `PDO::PARAM_*` type hints where applicable

#### 17.2.2 SSRF (Server-Side Request Forgery)
**Issue:** Fetching arbitrary URLs from decoded Google News links
**Risk:** HIGH - Internal network scanning, cloud metadata access, local file inclusion

**Attack Vector:**
```
Google News URL decodes to:
http://169.254.169.254/latest/meta-data/iam/security-credentials/
```

**Required Validation:**
```php
function validateArticleUrl($url) {
    // Parse URL
    $parsed = parse_url($url);
    if (!$parsed || !in_array($parsed['scheme'], ['http', 'https'])) {
        throw new SecurityException('Invalid URL scheme');
    }

    // Resolve hostname to IP
    $ip = gethostbyname($parsed['host']);

    // Block private IP ranges
    $private_ranges = [
        '10.0.0.0/8',
        '172.16.0.0/12',
        '192.168.0.0/16',
        '127.0.0.0/8',
        '169.254.0.0/16',  // AWS metadata
        '::1/128',         // IPv6 localhost
        'fc00::/7',        // IPv6 private
    ];

    foreach ($private_ranges as $range) {
        if (ip_in_range($ip, $range)) {
            throw new SecurityException('Private IP address blocked');
        }
    }

    return true;
}
```

**Recommendation:** Add Section 7.3 - SSRF Protection Requirements
- Validate all decoded URLs before fetching
- Block private IP ranges (RFC 1918, link-local, loopback)
- Implement timeout limits (5-10 seconds max)
- Follow redirects with same validation

#### 17.2.3 XSS (Cross-Site Scripting)
**Issue:** Displaying user-controlled content (article titles, descriptions)
**Risk:** MEDIUM - Session hijacking, credential theft

**Required Output Escaping:**
```php
// ALWAYS escape output
echo htmlspecialchars($article['title'], ENT_QUOTES, 'UTF-8');

// For HTML attributes
echo '<img src="' . htmlspecialchars($article['og_image'], ENT_QUOTES, 'UTF-8') . '">';

// For JavaScript context
echo '<script>const title = ' . json_encode($article['title']) . ';</script>';
```

**Recommendation:** Add requirement:
- All user-generated content MUST be escaped before display
- Use context-aware escaping (HTML, JavaScript, URL, CSS)
- Implement Content Security Policy (CSP) headers

#### 17.2.4 API Key Security
**Issue:** API keys in Section 4.4 don't specify generation/storage security
**Risk:** MEDIUM - Unauthorized access, key compromise

**Current:** "Generate random 32-character key"
**Required:**
```php
// Use cryptographically secure random generation
$api_key = bin2hex(random_bytes(32)); // 64 hex chars

// Hash before storage (like passwords)
$hashed_key = password_hash($api_key, PASSWORD_ARGON2ID);

// Validate
if (password_verify($provided_key, $stored_hash)) {
    // Valid
}
```

**Recommendation:** Update Section 4.4.2:
- Generate keys using `random_bytes()` (not `rand()` or `mt_rand()`)
- Consider hashing keys like passwords (show once on creation)
- Implement rate limiting per API key
- Add key rotation mechanism

#### 17.2.5 CSRF Protection
**Issue:** Not mentioned for form submissions
**Risk:** MEDIUM - Unauthorized actions via victim's session

**Required:**
```php
// Generate token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// Include in forms
<input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

// Validate on submission
if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    throw new SecurityException('CSRF token invalid');
}
```

**Recommendation:** Add Section 7.4 - CSRF Protection
- All state-changing forms MUST include CSRF token
- Use `hash_equals()` for constant-time comparison
- Regenerate token after successful validation

### 17.3 Performance & Scalability Issues

#### 17.3.1 N+1 Query Problem
**Issue:** Article listing could trigger multiple queries
**Risk:** Page load time increases linearly with article count

**Potential Problem:**
```php
// Bad: Separate query per article for feed name
foreach ($articles as $article) {
    $feed = getFeedById($article['feed_id']); // N queries!
    echo $feed['topic'];
}

// Good: JOIN or preload
SELECT a.*, f.topic
FROM articles a
JOIN feeds f ON a.feed_id = f.id
WHERE a.status = 'success'
```

**Recommendation:** Update Section 6.1.2:
- Add explicit indexes on foreign keys
- Document expected query patterns
- Consider denormalizing `topic` into articles table for performance

#### 17.3.2 Full-Text Search Performance
**Issue:** `FULLTEXT` index on multiple columns may be slow
**Risk:** Article search becomes unusable with >10k articles

**Current Schema:**
```sql
FULLTEXT idx_search (rss_title, page_title, og_title, og_description, author)
```

**Concerns:**
- 5 columns in one index = larger index, slower updates
- `article_content` (MEDIUMTEXT) excluded but should be searchable
- No specification of FULLTEXT parsing mode (natural language vs boolean)

**Recommendation:**
```sql
-- Option 1: Separate indexes for different search scopes
FULLTEXT idx_title_search (rss_title, page_title, og_title)
FULLTEXT idx_content_search (article_content)

-- Option 2: Use dedicated search solution (Elasticsearch, Meilisearch)
-- For >50k articles, consider external search engine
```

#### 17.3.3 RSS Feed Caching Strategy
**Issue:** Section 4.4.6 mentions 5-minute cache but no implementation details
**Risk:** Cache stampede under load

**Required Specification:**
```php
// Cache key strategy
$cache_key = 'rss_feed_' . md5($topic . $limit . $offset);

// Stale-while-revalidate pattern
if ($cached = getFromCache($cache_key)) {
    if ($cached['age'] < 300) { // 5 minutes
        return $cached['data'];
    } else {
        // Serve stale, regenerate in background
        scheduleRegeneration($cache_key);
        return $cached['data'];
    }
}
```

**Recommendation:** Add Section 4.4.6.1 - Cache Implementation:
- Specify cache backend (file, Redis, Memcached)
- Define cache key structure
- Implement stale-while-revalidate to prevent stampede
- Add cache warming for popular feeds

#### 17.3.4 Database Connection Pooling
**Issue:** Not addressed - may exhaust connections under load
**Risk:** "Too many connections" errors during feed processing

**Recommendation:** Add Section 3.5 - Database Connection Management:
- Use persistent connections (`PDO::ATTR_PERSISTENT`)
- Define max connection limit
- Implement connection timeout/retry logic
- Close connections after long-running operations

### 17.4 Architecture & Design Concerns

#### 17.4.1 Duplicate Detection Race Condition
**Issue:** Section 4.2.4 duplicate check has TOCTOU vulnerability
**Risk:** Same article processed twice simultaneously

**Race Condition:**
```
Process A: Check if URL exists → Not found
Process B: Check if URL exists → Not found
Process A: Insert article
Process B: Insert article → Duplicate!
```

**Solution:**
```sql
-- Add UNIQUE constraint
ALTER TABLE articles ADD UNIQUE INDEX idx_final_url_unique (final_url(500));

-- Handle duplicate insert gracefully
try {
    $stmt->execute([...]);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') { // Duplicate entry
        // Already processed by another process, skip
        return;
    }
    throw $e;
}
```

**Recommendation:** Update Section 6.1.2:
- Change `INDEX idx_final_url` to `UNIQUE INDEX`
- Add requirement for duplicate key error handling
- Consider database-level locking for critical sections

#### 17.4.2 Processing Queue vs. Real-Time
**Issue:** Sections 4.2.2 (scheduled) and 4.2.3 (processing) don't specify queue architecture
**Risk:** Timeout failures, lost work, no retry mechanism

**Current Approach:** Direct processing (not fault-tolerant)
**Better Approach:** Queue-based architecture

```php
// 1. Enqueue articles for processing
foreach ($rss_articles as $article) {
    enqueue('article_processing', [
        'feed_id' => $feed_id,
        'google_url' => $article['link'],
    ]);
}

// 2. Worker processes queue
while ($job = dequeue('article_processing')) {
    try {
        processArticle($job['data']);
        acknowledgeJob($job);
    } catch (Exception $e) {
        if ($job['attempts'] < 3) {
            requeueWithDelay($job, $delay = 60);
        } else {
            markAsFailed($job, $e->getMessage());
        }
    }
}
```

**Recommendation:** Add Section 4.2.5 - Queue-Based Processing (v1.1+):
- Implement job queue for article processing
- Support retry logic with exponential backoff
- Track processing state (pending, processing, complete, failed)
- Add dead letter queue for permanently failed items

#### 17.4.3 Database Migration Strategy
**Issue:** Section 9.2 mentions manual migrations but no rollback plan
**Risk:** Schema changes break production with no recovery path

**Required:**
```sql
-- migrations/001_initial_schema.up.sql
CREATE TABLE feeds (...);
CREATE TABLE articles (...);

-- migrations/001_initial_schema.down.sql
DROP TABLE articles;
DROP TABLE feeds;

-- migrations/002_add_final_url_index.up.sql
ALTER TABLE articles ADD INDEX idx_final_url (final_url(500));

-- migrations/002_add_final_url_index.down.sql
ALTER TABLE articles DROP INDEX idx_final_url;
```

**Recommendation:** Update Section 9.2.1:
- Require both UP and DOWN migration files
- Add migration tracking table
- Document rollback procedure
- Test migrations on copy of production data before applying

#### 17.4.4 Configuration Management
**Issue:** Section 9.4 shows config in code file - no environment separation
**Risk:** Accidentally commit production credentials to git

**Current:**
```php
// config.php - DANGEROUS
return [
    'database' => [
        'host' => 'localhost',
        'user' => 'db_user',
        'pass' => 'db_password'  // In version control!
    ]
];
```

**Required:**
```php
// config.php - Safe
return [
    'database' => [
        'host' => getenv('DB_HOST') ?: 'localhost',
        'user' => getenv('DB_USER'),
        'pass' => getenv('DB_PASS'),
    ]
];

// .env (NOT in git)
DB_HOST=localhost
DB_USER=unfurl_user
DB_PASS=secure_password_here
```

**Recommendation:** Update Section 9.4:
- Use environment variables for all secrets
- Provide `.env.example` template
- Add `.env` to `.gitignore`
- Document required environment variables

### 17.5 Error Handling & Logging Gaps

#### 17.5.1 Error Recovery Not Specified
**Issue:** Section 4.2.3 says "mark as failed" but no recovery mechanism
**Risk:** Failed articles never reprocessed

**Required:**
- Retry logic (3 attempts with backoff)
- Manual retry button in UI
- Bulk retry for failed articles
- Failure categorization (temporary vs. permanent)

**Example:**
```php
class ProcessingError {
    public function isRetryable(): bool {
        return in_array($this->code, [
            'TIMEOUT',
            'NETWORK_ERROR',
            'RATE_LIMIT',
        ]);
    }
}

if ($error->isRetryable() && $attempts < 3) {
    $delay = pow(2, $attempts) * 60; // Exponential backoff
    scheduleRetry($article_id, $delay);
}
```

#### 17.5.2 Logging Insufficient for Debugging
**Issue:** Section 11 defines what to log but not how to structure logs
**Risk:** Can't debug production issues

**Current:** "Log to database table"
**Problems:**
- Database logging slow (I/O bottleneck)
- No log levels (INFO, WARN, ERROR)
- No structured data (can't parse/query)
- Logs grow unbounded

**Recommendation:** Update Section 11.1:
```php
// Use PSR-3 compatible logging
$logger->error('Article processing failed', [
    'article_id' => $article_id,
    'feed_id' => $feed_id,
    'error' => $exception->getMessage(),
    'trace' => $exception->getTraceAsString(),
]);

// Log levels
DEBUG   -> Development debugging
INFO    -> Normal operations
WARNING -> Recoverable issues
ERROR   -> Failed operations
CRITICAL -> System failures
```

**Structure:**
- File-based logging for performance (rotate daily)
- Database for user-facing activity log only
- JSON format for machine parsing
- Include context: user_id, ip_address, user_agent

#### 17.5.3 No Monitoring/Alerting Defined
**Issue:** How to know when processing fails?
**Risk:** Silent failures, data loss goes unnoticed

**Recommendation:** Add Section 11.5 - Monitoring & Alerting:
- Health check endpoint (`/health.php`)
- Metrics: processing success rate, average duration, queue depth
- Alerts: Error rate >5%, processing stopped >1 hour
- Status page: Last successful run, current queue size

### 17.6 Testing Requirements Gaps

#### 17.6.1 Missing Test Scenarios
**Issue:** Section 10 lists test types but not critical test cases
**Risk:** Edge cases not covered, bugs reach production

**Required Test Cases:**

**Security:**
- SQL injection attempts in all inputs
- SSRF via malicious decoded URLs
- XSS in article titles/descriptions
- Invalid API keys rejected
- CSRF token validation

**Edge Cases:**
- Google News URL decoding fails (fallback behavior?)
- Article URL returns 404/500
- Article has no og:image
- RSS feed malformed XML
- Database connection fails mid-processing
- Duplicate articles processed simultaneously
- Feed deleted while processing

**Performance:**
- 1000 articles in database (pagination works?)
- 10 feeds processed concurrently
- Large article content (>1MB text)

#### 17.6.2 No Load Testing Specified
**Issue:** Success criteria mentions "100+ articles/month" but no load testing
**Risk:** Performance degradation under expected load

**Recommendation:** Add Section 10.5 - Load Testing:
```bash
# Simulate daily processing
ab -n 100 -c 10 https://site.com/unfurl/api.php

# Expected results:
# - 95th percentile < 5 seconds per article
# - No database connection errors
# - Memory usage < 256MB
# - CPU usage < 80%
```

### 17.7 Data Integrity & Consistency

#### 17.7.1 Foreign Key Constraints
**Issue:** Section 6.1.2 has `ON DELETE CASCADE` - potentially dangerous
**Risk:** Accidentally deleting feed removes all articles

**Current:**
```sql
FOREIGN KEY (feed_id) REFERENCES feeds(id) ON DELETE CASCADE
```

**Safer:**
```sql
-- Option 1: Prevent deletion if articles exist
FOREIGN KEY (feed_id) REFERENCES feeds(id) ON DELETE RESTRICT

-- Option 2: Soft delete feeds instead
ALTER TABLE feeds ADD COLUMN deleted_at TIMESTAMP NULL;
-- Keep articles, mark feed as deleted
```

**Recommendation:** Update Section 6.1.2:
- Change to `ON DELETE RESTRICT`
- Require confirmation: "Delete feed and X articles?"
- Consider soft delete pattern for feeds

#### 17.7.2 Data Validation Missing
**Issue:** No input validation specifications
**Risk:** Invalid data stored in database

**Required Validation:**

```php
class FeedValidator {
    public function validate(array $data): void {
        // Topic name
        if (empty($data['topic']) || strlen($data['topic']) > 255) {
            throw new ValidationException('Topic required, max 255 chars');
        }

        // URL format
        if (!filter_var($data['url'], FILTER_VALIDATE_URL)) {
            throw new ValidationException('Invalid URL format');
        }

        // Must be Google News domain
        $host = parse_url($data['url'], PHP_URL_HOST);
        if (!str_ends_with($host, 'google.com')) {
            throw new ValidationException('Must be Google News URL');
        }

        // Result limit range
        if ($data['limit'] < 1 || $data['limit'] > 100) {
            throw new ValidationException('Limit must be 1-100');
        }
    }
}
```

**Recommendation:** Add Section 7.5 - Input Validation Requirements:
- Validate all user inputs before database storage
- Use whitelisting (not blacklisting)
- Return specific error messages
- Log validation failures

### 17.8 Backwards Compatibility Concerns

#### 17.8.1 RSS Feed Format Stability
**Issue:** Section 4.4.4 RSS format may change, breaking existing consumers
**Risk:** External integrations break on update

**Recommendation:** Add Section 4.4.9 - API Versioning:
```
/feed.php?topic=IBD&v=1          # Version 1 (stable)
/feed.php?topic=IBD&v=2          # Version 2 (new features)
/feed.php?topic=IBD               # Default: latest stable
```

- Support previous version for 6 months after new release
- Deprecation warnings in RSS feed (custom namespace)
- Document breaking changes in CHANGELOG

#### 17.8.2 Database Schema Evolution
**Issue:** Adding columns may break existing code
**Risk:** Deployment failures

**Recommendation:**
- New columns: Add with DEFAULT value (never NOT NULL without default)
- Removing columns: Two-phase migration (mark deprecated, then remove)
- Changing types: Create new column, migrate data, swap, drop old

### 17.9 Operational Concerns

#### 17.9.1 Backup & Disaster Recovery
**Issue:** Not addressed in requirements
**Risk:** Data loss from hardware failure, human error

**Recommendation:** Add Section 12.4 - Backup Strategy:
- Daily automated backups via cPanel
- Retain backups for 30 days
- Test restore quarterly
- Document recovery procedure
- Export critical config to git (feed URLs, API keys metadata)

#### 17.9.2 Rate Limiting for External APIs
**Issue:** Google batchexecute API may have rate limits
**Risk:** IP banned, processing stops

**Recommendation:** Add Section 4.2.6 - Rate Limiting:
```php
// Delay between requests to Google
sleep(1); // 1 second between articles

// Track request count
$redis->incr('google_api_requests_' . date('Y-m-d'));
if ($redis->get('google_api_requests_' . date('Y-m-d')) > 1000) {
    throw new RateLimitException('Daily limit reached');
}
```

- Implement exponential backoff on 429 responses
- Respect Retry-After headers
- Fallback to manual processing if rate limited

### 17.10 Code Organization Best Practices

#### 17.10.1 Recommended File Structure
**Issue:** Section 15.1 mentions file structure but not code organization
**Recommendation:**

```
src/
├── Controllers/          # HTTP request handlers
│   ├── FeedController.php
│   ├── ArticleController.php
│   └── ApiController.php
├── Services/             # Business logic
│   ├── GoogleNewsDecoder.php
│   ├── ArticleExtractor.php
│   ├── RssFeedGenerator.php
│   └── ProcessingQueue.php
├── Repositories/         # Database access
│   ├── FeedRepository.php
│   └── ArticleRepository.php
├── Validators/           # Input validation
│   ├── FeedValidator.php
│   └── UrlValidator.php
├── Exceptions/           # Custom exceptions
│   ├── ValidationException.php
│   ├── SecurityException.php
│   └── ProcessingException.php
└── Core/
    ├── Database.php      # PDO wrapper
    ├── Router.php        # URL routing
    └── Logger.php        # Logging implementation
```

**Principles:**
- Single Responsibility: Each class has one job
- Dependency Injection: Pass dependencies, don't create them
- Interface-based: Define contracts, code to interfaces
- Testability: Pure functions, minimal static calls

#### 17.10.2 Error Handling Pattern
**Recommendation:**

```php
// Custom exception hierarchy
class UnfurlException extends Exception {}
class ValidationException extends UnfurlException {}
class SecurityException extends UnfurlException {}
class ProcessingException extends UnfurlException {}

// Controller handles exceptions
try {
    $result = $processor->processArticle($url);
    return json_response(['success' => true, 'data' => $result]);
} catch (ValidationException $e) {
    return json_response(['error' => $e->getMessage()], 400);
} catch (SecurityException $e) {
    Logger::critical('Security violation', ['exception' => $e]);
    return json_response(['error' => 'Invalid request'], 403);
} catch (ProcessingException $e) {
    Logger::error('Processing failed', ['exception' => $e]);
    return json_response(['error' => 'Processing failed'], 500);
} catch (Throwable $e) {
    Logger::critical('Unexpected error', ['exception' => $e]);
    return json_response(['error' => 'Internal server error'], 500);
}
```

### 17.11 Summary & Critical Recommendations

**CRITICAL (Must Address Before Development):**

1. **Security Requirements** (Section 7)
   - ✅ Add SSRF protection specification
   - ✅ Require prepared statements for all SQL
   - ✅ Define XSS prevention strategy
   - ✅ Specify CSRF protection
   - ✅ Improve API key generation/storage

2. **Error Handling** (Section 4.2.7 - NEW)
   - ✅ Define retry logic and backoff strategy
   - ✅ Specify failure categorization
   - ✅ Add manual retry capability
   - ✅ Document recovery procedures

3. **Database Design** (Section 6.1)
   - ✅ Add UNIQUE constraint on final_url
   - ✅ Review FULLTEXT index strategy
   - ✅ Change CASCADE to RESTRICT on foreign keys
   - ✅ Add migration rollback specifications

4. **Configuration Management** (Section 9.4)
   - ✅ Require environment variables for secrets
   - ✅ Provide .env.example template
   - ✅ Document all required config values

**HIGH Priority (Recommend for v1.0):**

5. **Performance** (Section 12)
   - Add query optimization guidelines
   - Define connection pooling strategy
   - Specify cache implementation details
   - Add load testing requirements

6. **Testing** (Section 10)
   - List critical test scenarios
   - Add security test cases
   - Define performance benchmarks
   - Require load testing

7. **Monitoring** (Section 11.5 - NEW)
   - Health check endpoint
   - Error rate alerts
   - Processing metrics
   - Status dashboard

**MEDIUM Priority (Nice to Have v1.0, Required v1.1):**

8. **Queue Architecture** (Section 4.2.5 - NEW)
   - Job queue for article processing
   - Retry with exponential backoff
   - Dead letter queue

9. **API Versioning** (Section 4.4.9 - NEW)
   - RSS feed versioning
   - Backwards compatibility policy
   - Deprecation warnings

10. **Operational** (Section 12.4 - NEW)
    - Backup strategy
    - Disaster recovery plan
    - Restore procedure testing

**Code Quality Principles to Emphasize:**

- **Security First**: Validate all inputs, escape all outputs, parameterize all queries
- **Fail Safely**: Graceful degradation, no silent failures
- **Observable**: Comprehensive logging, monitoring, alerting
- **Maintainable**: Clear code organization, documented patterns
- **Tested**: Unit tests for logic, integration tests for data flow, security tests for attack vectors

**Risk Assessment:**
- 🔴 **HIGH RISK**: SSRF, SQL injection, no error recovery
- 🟡 **MEDIUM RISK**: Performance under load, duplicate processing race condition
- 🟢 **LOW RISK**: Most functional requirements well-defined

**Estimated Additional Specification Time:** 3-5 days
**Recommended Review:** Senior engineer/security specialist before development

---

**Document Status:** Draft - Ready for Review
**Next Steps:** Address security gaps, finalize architecture, begin development


