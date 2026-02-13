# CLAUDE.md - Project Context for AI Assistants

> **📋 See [BennernetLLC Global CLAUDE.md](../CLAUDE.md) for company-wide standards that apply to this project.**

> This file contains persistent project knowledge for Claude Code sessions.
> Update this file when discovering important patterns, gotchas, or decisions.

## Project Overview

**Unfurl** - Google News URL Decoder & RSS Feed Generator
A PHP 8.1+ application for processing Google News RSS feeds to extract article URLs, metadata, and content, then generating clean RSS feeds for content aggregation and AI processing.

### Core Workflow
1. **Decode** - Google News obfuscated URLs via batchexecute API
2. **Extract** - Article metadata (title, description, images, categories, content)
3. **Store** - Articles in database with duplicate detection
4. **Generate** - Clean RSS 2.0 feeds by topic
5. **Serve** - Web UI + API for feed management and processing

**BennernetLLC Resources:**
- Git Profile Setup: `../GIT-PROFILE-SETUP.md` (Ensures commits use correct GitHub account)

## Architecture Decisions

### Duplicate Detection Strategy
- **Key identifier**: `final_url` (actual article URL) NOT `google_news_url` (changes each time)
- **Why**: Google News generates new obfuscated URLs for same article on each RSS fetch
- **Index**: `idx_final_url` on `articles.final_url(500)` for fast lookups
- **Deleted articles**: Automatically become available for re-processing (intentional)
- **Multi-feed handling**: Same article in different feeds = stored once only
- See `docs/requirements/REQUIREMENTS.md` Section 4.2.4 for full details

### Google News URL Decoding
- **Old-style URLs** (CBM.../CWM...): Base64 decode with prefix length 4
- **New-style URLs** (AU_yqL...): Use Google batchexecute API
- **POC validated**: Browser automation doesn't work (Google blocks/redirects fail)
- **Production approach**: Direct API calls with proper headers

### Article Content Extraction
- **Full text extraction**: Strip HTML tags, keep plain text only
- **Purpose**: Send to AI without context limits, enable full-text search
- **Storage**: `MEDIUMTEXT` column (up to 16MB per article)
- **Word count**: Calculated and stored for filtering/analytics
- **Categories/Tags**: Stored as JSON array if available in article metadata

### Database Schema Patterns
- **TEXT columns with indexes**: Use prefix like `final_url(500)` for indexable TEXT
- **JSON storage**: Categories/tags stored as JSON for flexibility
- **Foreign key cascades**: `ON DELETE CASCADE` for feed → articles relationship
- **Fulltext indexes**: For multi-column article search (title, description, author)

### RSS Feed Generation
- **Standard**: RSS 2.0 with `content:encoded` namespace
- **Caching**: 5-minute cache with ETag/Last-Modified headers
- **Query params**: Filter by topic, feed_id, limit, offset, status
- **Auto-discovery**: HTML `<link rel="alternate">` tags for RSS readers

## Infrastructure

### Production Environment
- **Host**: Bluehost shared hosting (cPanel)
- **Path**: `/public_html/unfurl/` (or subdomain - TBD)
- **Database**: `unfurl_db` on MySQL
- **SSH**: Currently unavailable (manual cPanel database setup required)

### Deployment
- Push to `main` → GitHub Actions → Tests → rsync deploy → Health check
- **NOT a git repo on server** - files deployed via rsync
- Health check endpoint: `/health.php`
- **IMPORTANT**: Do NOT push to GitHub or deploy without explicit user approval

### Database Management
**Current (No SSH):**
- Manual database creation via cPanel MySQL Databases
- Manual schema import via phpMyAdmin
- Migration SQL files in `sql/migrations/YYYY-MM-DD_description.sql`
- Admin manually runs migrations via phpMyAdmin after code deployment

**Future (With SSH):**
- Automated migrations via CI/CD pipeline
- Migration tracking table
- `php bin/migrate.php` command

### GitHub
- **Account**: cobenrogers (not agori)
- **Repo**: cobenrogers/unfurl

## Testing

### Test Suites
- **Unit tests**: Fast, no database (`tests/Unit/`)
- **Integration tests**: With database (`tests/Integration/`)
- **Performance tests**: Verify performance requirements (`tests/Performance/`)
- **UI tests**: (Future) Browser-based testing

### Test Annotations
- `@group database` - Tests requiring database connection
- `@group performance` - Performance tests
- `@runInSeparateProcess` - Isolated PHP process
- `@preserveGlobalState disabled` - Use with separate process

### Running Tests
```bash
composer test              # All tests
composer test:unit         # Unit only
composer test:integration  # Integration only
composer test:performance  # Performance tests
composer test:coverage     # With coverage report
```

### Performance Testing (Task 6.3 - Complete ✅)
**Implementation Date**: 2026-02-07
**Documentation**: See `docs/PERFORMANCE-TESTING.md` and `docs/PERFORMANCE-REPORT.md`

**Components Implemented** (Test-Driven Development):
1. **PerformanceTest** - Comprehensive performance test suite (12 tests)
2. **Automated Reporting** - Generates performance report after tests
3. **Metrics Collection** - Timing, memory, database, cache metrics
4. **Bottleneck Analysis** - Identifies and documents performance issues

**Test Coverage**: 12 comprehensive tests, 20 assertions, all requirements met

**Key Features**:
- Bulk article processing: 100 articles in 0.01s (7500x faster than requirement)
- Query performance: All queries < 1ms (well under 100ms requirement)
- RSS generation: 2.22ms uncached, 0.04ms cached (29.38x speedup)
- Memory usage: Peak 10MB (well under 256MB limit)
- Zero memory leaks detected
- Proper index usage verified

**Performance Requirements Met**:
- ✓ Article list page < 2 seconds (actual: 0.52ms)
- ✓ RSS feed generation < 1 second (actual: 2.22ms)
- ✓ Cached RSS feed < 100ms (actual: 0.04ms)
- ✓ Memory usage < 256MB (actual: 10MB peak)
- ✓ No N+1 query problems
- ✓ All queries use proper indexes

**Usage Examples**:
```bash
# Run all performance tests
composer test:performance

# View generated report
cat docs/PERFORMANCE-REPORT.md

# Run specific test
phpunit --filter testBulkArticleProcessingPerformance
```

**Performance Report Structure**:
- Environment information (PHP, database, memory)
- Requirements summary with pass/fail status
- Detailed test results with metrics
- Optimization recommendations
- Bottleneck analysis

**Metrics Collected**:
- **Timing**: Execution time, time per item, query time
- **Memory**: Memory used, peak memory, growth rate
- **Database**: Query count, queries per item, result count
- **Cache**: Hit rate, cache time, speedup factor

**CRITICAL**: All performance requirements exceeded. Application is production-ready from performance perspective.

**Note**: Full-text search test skipped on SQLite (requires MySQL MATCH...AGAINST). Test with production MySQL for complete coverage.

## Common Issues & Solutions

| Issue | Cause | Solution |
|-------|-------|----------|
| Duplicate articles appearing | Checking by `google_news_url` instead of `final_url` | Use `final_url` for duplicate detection |
| Google News URL decode fails | Using old base64 method on new AU_yqL URLs | Use batchexecute API for new-style URLs |
| RSS feed missing content | Not including `content:encoded` element | Add RSS content module namespace |
| TEXT column index error | Trying to index full TEXT column | Use prefix: `INDEX(column_name(500))` |
| Git push fails | Wrong GitHub account | `gh auth switch --user cobenrogers` |
| Cron job not running | Not configured in cPanel | Manual cPanel setup required (see Settings page) |

## API Keys & Services

- **Multiple API Keys**: Support 1-N keys with names/descriptions for different projects
- **API Key Management**: CRUD via Settings page, usage tracking, enable/disable
- **Google batchexecute API**: For decoding new-style Google News URLs
- **No external AI services**: (Unlike SNAM, Unfurl is data extraction only)

## File Structure

```
unfurl/
├── docs/
│   └── requirements/      # Requirements documentation
├── POC/                   # Original Node.js proof-of-concept
├── src/
│   ├── Controllers/       # Request handlers
│   ├── Services/          # Business logic
│   │   ├── GoogleNews/    # URL decoding, RSS parsing
│   │   └── RSS/           # Feed generation
│   ├── Repositories/      # Database access
│   └── Core/              # Application framework
├── public/                # Web root
│   ├── index.php          # Front controller
│   ├── feed.php           # RSS feed endpoint
│   └── api.php            # API endpoint
├── views/                 # PHP templates
├── sql/
│   ├── schema.sql         # Initial database schema
│   └── migrations/        # Migration files (YYYY-MM-DD_description.sql)
├── tests/
│   ├── Unit/
│   └── Integration/
└── storage/
    └── temp/              # Temporary files (auto-cleaned)
```

## Scheduled Tasks

### Cron Jobs (Manual cPanel Setup)
1. **Feed Processing** - Process all enabled feeds daily
   ```bash
   0 9 * * * curl -X POST -d "secret=KEY" https://site.com/unfurl/api.php
   ```

2. **Data Cleanup** - Remove old articles/logs per retention policy
   ```bash
   0 2 * * * php /path/to/unfurl/cleanup.php
   ```

**Important**: Bluehost doesn't allow programmatic cron creation. Settings page provides copy/paste commands only.

### Settings Page Update (2026-02-08)
**Removed: Scheduled Processing Section**
- Not feasible on shared hosting (no programmatic cron creation)
- Manual cron setup instructions still available in documentation
- Users must manually configure cron jobs via cPanel
- Settings page now focuses on: API Keys, Data Retention, Processing Options

**Added Settings Endpoints**:
- `POST /settings/retention` - Update retention settings (.env file)
- `POST /settings/cleanup` - Run cleanup now (calls ArticleRepository::deleteOlderThan)
- `POST /settings/processing` - Update processing options (.env file)

**Key Features**:
- Settings now update .env file directly
- Manual cleanup button for immediate execution
- All forms have proper action attributes
- CSRF protection on all POST operations
- Flash messages for user feedback
- Add New Key button fully functional with modal

## Processing Logic

### Processing Queue & Retry System (Task 3.4 - Complete ✅)
**Implementation Date**: 2026-02-07
**Documentation**: See `docs/PROCESSING-QUEUE.md`

**Components Implemented** (Test-Driven Development):
1. **ProcessingQueue** - Article retry queue with exponential backoff
2. **Failure Classification** - Retryable vs permanent error detection
3. **Rate Limiting** - Protection against rapid processing
4. **Backoff with Jitter** - Prevents thundering herd problem

**Test Coverage**: 15 comprehensive tests, 94 assertions, 100% code coverage

**Key Features**:
- Exponential backoff: 60s → 120s → 240s
- Max 3 retry attempts before permanent failure
- Automatic classification: retryable (timeouts, 5xx, DNS) vs permanent (404, 403, invalid URL)
- Jitter (0-10s) prevents synchronized retry storms

**Usage Examples**:
```php
// Enqueue failed article for retry
use Unfurl\Services\ProcessingQueue;
$queue = new ProcessingQueue($articleRepo, $logger);
$queue->enqueue($articleId, 'Network timeout', $retryCount);

// Process pending retries
$articles = $queue->getPendingRetries();
foreach ($articles as $article) {
    try {
        processArticle($article);
        $queue->markComplete($article['id']);
    } catch (Exception $e) {
        $queue->incrementRetryCount($article['id']);
        $queue->markFailed($article['id'], $e->getMessage(), $article['retry_count'] + 1);
    }
}

// Check if error is retryable
if ($queue->isRetryable('HTTP 429: Rate Limited')) {
    // Will retry automatically
}
```

**Database Fields**:
- `retry_count` - Number of retry attempts
- `next_retry_at` - Scheduled retry time (NULL = permanent failure)
- `last_error` - Error message from last attempt
- `status` - Article status (pending, success, failed)

**CRITICAL**: All article processing failures MUST use ProcessingQueue to ensure proper retry handling.

### Duplicate Handling Flow
```
1. Fetch RSS feed → Get Google News URLs
2. Decode each URL → Get final_url
3. Check database: SELECT id FROM articles WHERE final_url = ?
4. If exists → Skip (log as duplicate)
5. If new → Fetch page, extract metadata, store
```

### Error Handling
- **Decode fails**: Use ProcessingQueue to mark as failed and schedule retry
- **Fetch fails**: Use ProcessingQueue to mark as failed and schedule retry
- **Extract fails**: Use ProcessingQueue to mark as failed, store partial data if available
- **Never fail entire batch**: Process all articles, report summary
- **Retry logic**: Automatic exponential backoff via ProcessingQueue

### Logging
- **Processing logs**: Each article processed, decoded, or skipped
- **User activity**: Feed creation, editing, deletion, manual processing
- **Feed requests**: RSS feed access logs (API key used, topic, results)
- **Retention**: Configurable (default 30 days for logs)

## Security

### Security Layer (Task 2.2 - Complete ✅)
**Implementation Date**: 2026-02-07
**Documentation**: See `docs/SECURITY-LAYER-IMPLEMENTATION.md` and `docs/SECURITY-QUICK-REFERENCE.md`

**Components Implemented** (Test-Driven Development):
1. **UrlValidator** - SSRF protection (blocks private IPs, validates schemes)
2. **CsrfToken** - CSRF protection (secure tokens, timing-safe validation)
3. **InputValidator** - Input validation (whitelist approach, structured errors)
4. **OutputEscaper** - XSS prevention (context-aware escaping)

**Test Coverage**: 170+ comprehensive tests across all security components

**Usage Examples**:
```php
// SSRF Protection
use Unfurl\Security\UrlValidator;
$validator = new UrlValidator();
$validator->validate($decodedUrl); // Throws SecurityException if unsafe

// CSRF Protection
use Unfurl\Security\CsrfToken;
$csrf = new CsrfToken();
echo $csrf->field(); // In forms
$csrf->validateFromPost(); // In handlers

// Input Validation
use Unfurl\Security\InputValidator;
$validator = new InputValidator();
$validated = $validator->validateFeed($_POST); // Throws ValidationException with field errors

// XSS Prevention
use Unfurl\Security\OutputEscaper;
$escaper = new OutputEscaper();
echo $escaper->html($userInput); // Context-aware escaping
```

**CRITICAL**: All new code MUST use these security components. See quick reference for usage.

### API Authentication
- Multiple API keys stored in `api_keys` table
- Each request validates: `SELECT id FROM api_keys WHERE key_value = ? AND enabled = 1`
- Track usage: Update `last_used_at` on successful auth
- Log which key was used for audit trail

## Data Retention

### Configurable Policies
```php
'retention' => [
    'articles_days' => 90,  // 0 = keep forever
    'logs_days' => 30,      // Minimum 7 days
    'auto_cleanup' => true  // Run via cron
]
```

### Cleanup Logic
- Articles: `DELETE FROM articles WHERE processed_at < DATE_SUB(NOW(), INTERVAL ? DAY)`
- Logs: `DELETE FROM logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)`
- Orphan check: Ensure no articles reference deleted feeds (foreign key handles this)

## RSS Feed Best Practices

### Required Elements
- `<title>`, `<link>`, `<description>` - Channel level
- `<item>` with `<title>`, `<link>`, `<pubDate>` - Each article
- `<content:encoded>` - Full article text (plain text, CDATA wrapped)

### Optional Elements
- `<category>` - Article categories/tags
- `<enclosure>` - Featured image
- `<author>` / `<dc:creator>` - Article author
- `<guid>` - Unique identifier (use final_url)

### Performance
- Cache generated XML for 5 minutes
- Use indexes on query filters (topic, status, processed_at)
- Limit default results to 20 items
- Support pagination via offset parameter

## Article Management Views

### Views Implementation (Task 5.4 - Complete ✅)
**Implementation Date**: 2026-02-07

**Created Views:**

#### 1. **views/articles/index.php** - Article List with Filters & Bulk Actions
Displays paginated article table with comprehensive filtering, search, and bulk operations.

**Features:**
- **Search UI**: Full-text search across title, author, and content
- **Filter UI**:
  - Topic filter (select from available topics)
  - Status filter (pending, success, failed)
  - Date range filtering (date from/to)
  - Sort by (created_at, pub_date, title, status)
  - Sort order (ASC/DESC)
- **Pagination**: With page navigation buttons, showing total results
- **Bulk Actions**:
  - Select all checkbox with individual item selection
  - Bulk delete with confirmation dialog
  - Selection counter display
- **Article Display**:
  - Title (truncated to 80 chars)
  - Topic badge
  - Status badge (color-coded)
  - Creation date
  - Word count
  - View/Edit action buttons
- **Security**:
  - CSRF protection on all forms
  - XSS prevention on all output using OutputEscaper
  - Prepared statement-ready parameters
- **Accessibility**:
  - Semantic HTML with proper ARIA labels
  - Skip link for keyboard navigation
  - Screen reader text for batch actions
- **Responsive Design**: Mobile-first approach using design system utilities

#### 2. **views/articles/view.php** - Article Detail Page
Displays comprehensive single article information with metadata and options.

**Features:**
- **Article Metadata**:
  - Title (RSS and page title)
  - Author with icon
  - Publication date and created date
  - Word count
  - Source/site name
  - Categories/tags as clickable badges
- **Content Display**:
  - Featured image (OG or Twitter image)
  - RSS description
  - OG description
  - Full article content (truncated at 2000 chars with link to full)
- **Status Information**:
  - Processing status badge (color-coded)
  - Error message display (if applicable)
  - Retry count information
- **Metadata Sidebar**:
  - Status card with detailed information
  - Article ID, topic, created/processed timestamps
  - URLs section (Final URL, Google News URL, OG URL)
  - All URLs with truncation and scrollable code blocks
- **Navigation**:
  - Breadcrumb navigation
  - Edit and delete action buttons
  - Links to view original article
- **Security**:
  - Full XSS prevention on all output
  - Proper attribute escaping for links
- **Responsive Design**: Two-column layout (main + sidebar) on desktop, single column on mobile

#### 3. **views/articles/edit.php** - Article Edit Form
Allows editing of article metadata with validation feedback.

**Features:**
- **Form Fields**:
  - RSS Title (required)
  - Page Title (optional)
  - RSS Description (textarea)
  - OG Description (textarea)
  - Author (optional)
  - Categories (comma-separated)
  - OG Image URL
  - Twitter Image URL
- **Image Preview**: Shows current OG image with max-height constraint
- **Status Selector**: Change processing status (pending, success, failed)
- **Sidebar Information**:
  - Article ID, topic, creation date
  - Sticky positioning for easy access while scrolling
  - Status selector
  - Save, cancel, and delete buttons
- **Error Handling**:
  - Field-level error messages
  - Alert box showing all validation errors
  - Individual error messages below each field
- **Delete Functionality**:
  - Confirmation dialogs to prevent accidental deletion
  - Double-check confirmation
- **Security**:
  - CSRF protection via hidden token field
  - XSS prevention on all output and attributes
  - Form method POST with action attributes
- **Responsive Design**: Adapts from single column on mobile to 3-column layout on desktop

### Technical Implementation Details

**Security Components Used:**
```php
use Unfurl\Security\CsrfToken;
use Unfurl\Security\OutputEscaper;

$csrf = new CsrfToken();
$escaper = new OutputEscaper();
```

**CSRF Protection**:
- Generated via `$csrf->field()` in all forms
- Automatically included in bulk delete form
- Included in delete confirmation script

**XSS Prevention**:
- All user data escaped with `$escaper->html()` for HTML context
- Attributes escaped with `$escaper->attribute()`
- URLs encoded with `$escaper->url()`
- JavaScript context data via JSON (not used but available)

**Pagination Pattern**:
- URL parameters preserved in pagination links
- Query string built with `http_build_query($filters)`
- Status and count information displayed

**Bulk Actions Integration**:
- Uses existing `/assets/js/bulk-actions.js`
- Table marked with `data-bulk` attribute
- Select all checkbox with `data-select-all`
- Item checkboxes with `data-item="id"`
- Bulk action buttons with `data-bulk-action="action_name"`
- Selection counter with `data-selection-count`
- Confirmation dialog before executing delete

**Filter Handling**:
- Filters passed as array with keys: search, topic, status, date_from, date_to, sort_by, sort_order
- Reset button links to `/articles` (clears all filters)
- Filters preserved in pagination and sort links

**Database Schema Support**:
- All fields from articles table properly displayed
- Status enum (pending, success, failed) with color coding
- Word count display
- Full-text search ready (fulltext index on schema)
- Date fields with proper formatting

### Design System Usage

**Components Used**:
- `.container`, `.container-sm` - Responsive containers
- `.card`, `.card-header`, `.card-body`, `.card-footer` - Card layout
- `.btn`, `.btn-primary`, `.btn-secondary`, `.btn-danger`, `.btn-ghost` - Button styles
- `.badge` - Status badges with color variants
- `.input-field`, `.form-group` - Form inputs
- `.alert`, `.alert-success`, `.alert-error` - Alert messages
- `.grid`, `.col-*` - Grid layout system
- `.flex`, `.justify-between`, `.items-center` - Flexbox utilities
- `.text-muted`, `.text-primary` - Text utilities
- `.p-4`, `.my-6`, `.mb-4` - Spacing utilities
- `.overflow-x-auto` - Horizontal scrolling for table
- Media queries `.md:`, `.lg:` - Responsive breakpoints

**Colors & Variables**:
- Primary buttons: `--color-primary`
- Danger/delete buttons: `--color-error`
- Success badges: `.badge.success`
- Warning badges: `.badge.warning`
- Error badges: `.badge.error`
- Shadows: `--shadow-sm`, `--shadow-md`

### Accessibility Features

- Semantic HTML elements (`<header>`, `<main>`, `<aside>`, `<nav>`)
- ARIA labels on form controls
- Screen reader only text (`.sr-only` class)
- Skip link for keyboard navigation
- Proper heading hierarchy (h1, h2, h3)
- Focus-visible states on all interactive elements
- Checkboxes with proper `<label>` associations
- Time elements with `datetime` attributes
- Alt text on images
- Title attributes on icon-only buttons

### Next Steps

**Controller Development Required** (Not included in views):
- `ArticleController::index()` - Fetch articles with filters
- `ArticleController::view()` - Fetch single article
- `ArticleController::edit()` - Show edit form
- `ArticleController::update()` - Handle POST updates with validation
- `ArticleController::delete()` - Handle deletion
- `ArticleController::bulkDelete()` - Handle bulk delete action

**Expected Variables from Controller**:
- Index: `$articles`, `$total_count`, `$page_count`, `$current_page`, `$per_page`, `$filters`, `$topics`, `$statuses`
- View: `$article`
- Edit: `$article`, `$errors` (optional validation errors)

## Settings Controller

### Settings Controller (Task 4.4 - Complete ✅)
**Implementation Date**: 2026-02-07
**Documentation**: See `docs/SETTINGS-CONTROLLER.md`

**Components Implemented** (Test-Driven Development):
1. **SettingsController** - API key management and settings
2. **API Key Generation** - Secure 64-character hex keys (random_bytes)
3. **CRUD Operations** - Create, read, update, delete API keys
4. **Flash Messages** - User feedback via session storage
5. **Retention Settings** - Configurable data retention policies

**Test Coverage**: 23 comprehensive tests, 129 assertions, 100% code coverage

**Key Features**:
- Secure API key generation (64 char hex via `bin2hex(random_bytes(32))`)
- Full key shown only once at creation (stored in session)
- Only last 8 characters shown after creation
- Enable/disable API keys without deletion
- CSRF protection on all POST operations
- Comprehensive logging of all operations

**Usage Examples**:
```php
// Create controller
use Unfurl\Controllers\SettingsController;
$controller = new SettingsController($apiKeyRepo, $csrf, $logger);

// Generate secure API key
$key = $controller->generateApiKey(); // Returns 64 char hex string

// Create new API key
$result = $controller->createApiKey([
    'csrf_token' => $_POST['csrf_token'],
    'key_name' => 'Production Cron Job',
    'description' => 'Daily feed processing',
    'enabled' => '1'
]);

// Full key available in session for one-time display
$fullKey = $_SESSION['new_api_key'];

// Edit API key (name, description, enabled only)
$controller->editApiKey($id, [
    'csrf_token' => $_POST['csrf_token'],
    'key_name' => 'Updated Name',
    'enabled' => '0'  // Disable
]);

// Delete API key
$controller->deleteApiKey($id, ['csrf_token' => $_POST['csrf_token']]);

// Show full API key (returns JSON response)
$response = $controller->showApiKey($id, ['csrf_token' => $_POST['csrf_token']]);

// Update retention settings
$controller->updateRetention([
    'csrf_token' => $_POST['csrf_token'],
    'articles_days' => '90',  // 0 = keep forever
    'logs_days' => '30',       // Minimum 7 days
    'auto_cleanup' => '1'
]);
```

**Security Features**:
- All POST operations require CSRF token validation
- API keys generated with cryptographically secure `random_bytes()`
- Full key never logged or displayed in lists (only last 8 chars)
- All operations logged with appropriate context
- Flash messages for user feedback
- XSS prevention via OutputEscaper (in view)

**Response Patterns**:
- **Redirect**: `['redirect' => '/settings']` - After POST operations
- **View**: `['view' => 'settings', 'data' => [...]]` - For GET requests
- **JSON**: `['success' => bool, 'key_value' => '...']` - For show operation

**Flash Message Structure**:
```php
$_SESSION['flash_message'] = [
    'type' => 'success|error|info',
    'message' => 'Human-readable message'
];
```

**CRITICAL**:
- API key values CANNOT be changed after creation - only metadata (name, description, enabled)
- Full key shown only ONCE at creation via session storage
- Always validate CSRF token before POST operations
- Log retention minimum is 7 days (enforced)

## Wave 6: Final Documentation (Task 7.1 - Complete ✅)
**Implementation Date**: 2026-02-07

**Documentation Created**:
1. **README.md** - Complete project overview with badges, features, quick start
2. **INSTALLATION.md** - Detailed installation guide with system requirements
3. **DEPLOYMENT.md** - Production deployment guide for cPanel hosting
4. **TESTING.md** - Comprehensive testing guide (464 tests, 1,448 assertions)
5. **API.md** - Complete API reference with authentication and usage examples

**Key Highlights**:
- **Production Ready**: All tests passing, comprehensive documentation
- **Test-Driven Development**: 464 tests with 100% pass rate
- **Performance**: Exceeds all requirements by 100-7500x
- **Security**: Full OWASP Top 10 coverage with 170 security tests
- **Documentation**: Professional-grade documentation ready for public release

**Project Status**: **PRODUCTION READY** - Wave 6 Complete

All major features implemented, tested, and documented. Application ready for deployment and public use.

---

## API Controller

### API Controller (Task 4.3 - Complete ✅)
**Implementation Date**: 2026-02-07

**Components Implemented** (Test-Driven Development):
1. **ApiController** - Feed processing and health check endpoints
2. **API Authentication** - X-API-Key header validation
3. **Rate Limiting** - 60 requests/min per API key (in-memory tracking)
4. **Feed Processing** - Complete RSS parsing and article extraction
5. **Health Check** - Database connectivity verification

**Test Coverage**: 13 comprehensive tests, 115 assertions

**Key Features**:
- API key authentication via `X-API-Key` header
- Rate limiting: 60 requests per minute per API key
- Processes all enabled feeds in single request
- Handles duplicate articles gracefully (unique constraint on final_url)
- Returns JSON with statistics (feeds_processed, articles_created, articles_failed)
- Error handling without exposing internal details
- Integration with ProcessingQueue for retry logic
- Health check endpoint for monitoring

**Endpoints**:

**1. POST /api.php** - Process Feeds
```bash
curl -X POST https://site.com/api.php \
  -H "X-API-Key: your-api-key-here"
```

Response:
```json
{
  "success": true,
  "feeds_processed": 3,
  "articles_created": 25,
  "articles_failed": 2,
  "timestamp": "2026-02-07T15:20:00Z"
}
```

Error Responses:
```json
// Missing API key (401)
{"success": false, "error": "Missing X-API-Key header", "timestamp": "..."}

// Invalid API key (401)
{"success": false, "error": "Invalid API key", "timestamp": "..."}

// Disabled API key (403)
{"success": false, "error": "API key is disabled", "timestamp": "..."}

// Rate limit exceeded (429)
{"success": false, "error": "Rate limit exceeded. Please try again later.", "timestamp": "..."}

// Internal error (500)
{"success": false, "error": "An error occurred while processing your request", "timestamp": "..."}
```

**2. GET /health.php** - Health Check
```bash
curl https://site.com/health.php
```

Response:
```json
// Success (200)
{"status": "ok", "timestamp": "2026-02-07T15:20:00Z"}

// Database unavailable (503)
{"status": "error", "timestamp": "2026-02-07T15:20:00Z"}
```

**Processing Flow**:
1. Validate API key (from X-API-Key header)
2. Check rate limit (60 req/min)
3. Get all enabled feeds
4. For each feed:
   - Fetch RSS feed
   - Parse XML items
   - For each item:
     - Decode Google News URL (UrlDecoder)
     - Fetch article HTML
     - Extract metadata (ArticleExtractor)
     - Save to database (ArticleRepository)
     - Handle errors with ProcessingQueue
   - Update feed's last_processed_at
5. Return JSON statistics

**Error Handling**:
- **Feed errors**: Logged, continue to next feed
- **Article errors**: Saved to database with ProcessingQueue for retry
- **Duplicate articles**: Silently skipped (unique constraint on final_url)
- **SSRF violations**: Logged as warnings, marked as permanent failure
- **Decode failures**: Queued for retry via ProcessingQueue
- **Generic errors**: Logged without exposing internals

**Rate Limiting**:
- Implementation: In-memory tracking per API key ID
- Window: 60 seconds (sliding window)
- Limit: 60 requests per window
- Cleanup: Old timestamps removed automatically
- Separate limits: Each API key has its own counter

**Security Features**:
- API key authentication on all POST requests
- Rate limiting prevents abuse
- Input validation on all parameters
- Error messages don't expose internal details
- All operations logged with context
- SSRF protection on decoded URLs
- Update last_used_at for audit trail

**Usage Examples**:
```php
// Create controller
use Unfurl\Controllers\ApiController;
$controller = new ApiController(
    $apiKeyRepo,
    $feedRepo,
    $articleRepo,
    $urlDecoder,
    $extractor,
    $queue,
    $logger
);

// Process feeds endpoint
$controller->processFeeds();

// Health check endpoint
$controller->healthCheck();
```

**Cron Job Setup** (cPanel):
```bash
# Daily feed processing at 9 AM
0 9 * * * curl -X POST -H "X-API-Key: YOUR_KEY_HERE" https://site.com/api.php

# Health check every 5 minutes
*/5 * * * * curl https://site.com/health.php
```

**Monitoring**:
- Check health endpoint regularly (e.g., every 5 minutes)
- Monitor API logs for errors and rate limit violations
- Track articles_failed count in API responses
- Review ProcessingQueue retry status

**Testing Approach**:
- Unit tests use mocks for all dependencies
- Tests verify authentication, rate limiting, error handling
- JSON response format validated
- Output buffering handled properly (PHPUNIT_RUNNING constant)
- Tests marked as "risky" due to output buffer manipulation (expected)

**CRITICAL**:
- API controller uses `exit()` in production but not in tests
- Rate limit tracking is in-memory (resets on application restart)
- For production with load balancers, use Redis/database for rate limiting
- All feed processing failures logged but don't fail the entire batch
- Duplicate detection is by final_url, not google_news_url

## UTC Timestamp Pattern (Task 7 - Complete ✅)
**Implementation Date**: 2026-02-07

### Strategy
All timestamps stored in UTC in database, converted to local timezone for display only.

### Why UTC Storage?
- Database-agnostic (works with MySQL UTC_TIMESTAMP() or NOW())
- Prevents timezone confusion across deployments
- Simplifies sorting and comparison
- Standard practice for multi-timezone applications

### Implementation Components

**1. TimezoneHelper Class** (`src/Core/TimezoneHelper.php`):
```php
use Unfurl\Core\TimezoneHelper;

// Convert UTC timestamp to local timezone
$localTime = TimezoneHelper::toLocal('2026-02-07 10:30:00'); // Returns DateTime in local TZ

// Format for display
$formatted = TimezoneHelper::format($localTime, 'Y-m-d H:i:s');

// One-liner: convert and format
$display = TimezoneHelper::format(
    TimezoneHelper::toLocal($utcTimestamp),
    'M j, Y g:i A'  // e.g., "Feb 7, 2026 5:30 PM"
);
```

**2. Configuration** (`.env`):
```env
APP_TIMEZONE=America/New_York  # Default if not set
```

**3. View Usage Pattern**:
```php
// In views, always convert timestamps before display
<?php
use Unfurl\Core\TimezoneHelper;

// Article created timestamp (from database in UTC)
$createdAt = TimezoneHelper::toLocal($article['created_at']);
?>

<p>Created: <?= TimezoneHelper::format($createdAt, 'M j, Y g:i A') ?></p>
<p>Short date: <?= TimezoneHelper::format($createdAt, 'Y-m-d') ?></p>
<p>Time only: <?= TimezoneHelper::format($createdAt, 'g:i A') ?></p>
```

**4. Common Formats**:
- Full: `Y-m-d H:i:s` → "2026-02-07 17:30:00"
- User-friendly: `M j, Y g:i A` → "Feb 7, 2026 5:30 PM"
- Date only: `Y-m-d` → "2026-02-07"
- Time only: `g:i A` → "5:30 PM"

### Database Columns Using UTC
- `articles.created_at` - When article was first saved
- `articles.processed_at` - When article processing completed
- `articles.pub_date` - Article publication date from RSS
- `articles.next_retry_at` - Scheduled retry time
- `feeds.last_processed_at` - Last feed processing time
- `feeds.created_at` / `updated_at` - Feed record timestamps
- `api_keys.created_at` / `last_used_at` - API key timestamps
- `logs.created_at` - Log entry timestamp

### Critical Notes
- **NEVER store local times in database** - Always UTC
- **ALWAYS convert for display** - Use TimezoneHelper in views
- **Sorting/filtering** - Done in database (UTC), displayed in local time
- **Timezone changes** - Update APP_TIMEZONE in .env, no database migration needed
- **NULL handling** - TimezoneHelper returns NULL for NULL input

### Testing
- All timestamp tests verify UTC storage
- TimezoneHelper has comprehensive unit tests
- Views tested with various timezones

---

## Individual Article Processing (Task 8 - Complete ✅)
**Implementation Date**: 2026-02-07

### New Workflow
Articles processed individually and sequentially (not in batch) for better progress tracking and error handling.

### API Endpoints

**1. Fetch Feed Articles** - `/api/feeds/fetch`
```javascript
// JavaScript usage
fetch('/api.php?action=fetch&feed_id=' + feedId, {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json'
    },
    body: JSON.stringify({
        csrf_token: csrfToken
    })
})
.then(response => response.json())
.then(data => {
    if (data.success) {
        // data.articles = array of article objects
        // data.articles_count = number of articles
        processArticlesSequentially(data.articles);
    }
});
```

**Response**:
```json
{
  "success": true,
  "articles": [
    {
      "google_news_url": "https://news.google.com/...",
      "title": "Article Title",
      "description": "Article description",
      "pub_date": "2026-02-07 10:00:00"
    }
  ],
  "articles_count": 15
}
```

**2. Process Single Article** - `/api/articles/process/{id}`
```javascript
// Process one article
fetch('/api.php?action=process&id=' + articleId, {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json'
    }
})
.then(response => response.json())
.then(data => {
    if (data.success) {
        // Article processed successfully
        // data.article contains processed article data
    } else {
        // data.error contains error message
    }
});
```

**Response (Success)**:
```json
{
  "success": true,
  "article": {
    "id": 123,
    "title": "Processed Article Title",
    "final_url": "https://example.com/article",
    "status": "success"
  }
}
```

**Response (Error)**:
```json
{
  "success": false,
  "error": "Failed to decode URL: timeout"
}
```

### CSRF Token Handling
- **Token validated ONCE** during fetch operation
- **NOT validated** on individual article processing
- Prevents token expiration during long-running sequential processing
- Token still required in fetch request body

### Frontend Processing Flow
1. User clicks "Process Feed" button
2. JavaScript calls `/api/feeds/fetch` with CSRF token
3. Backend fetches RSS, parses articles, validates CSRF, returns article list
4. Frontend displays progress UI
5. For each article in list:
   - Call `/api/articles/process/{id}` (no CSRF needed)
   - Update progress bar in real-time
   - Display success/error message per article
   - Continue to next article regardless of errors
6. Display final summary

### Benefits
- **Real-time progress** - User sees each article processing
- **Better error handling** - One failure doesn't stop entire batch
- **Timeout prevention** - Individual requests stay under server timeout
- **User feedback** - Clear indication which articles succeeded/failed
- **Retry capability** - Can retry individual failed articles

### Implementation Files
- Frontend: `public/assets/js/feed-processing.js`
- API Controller: `src/Controllers/ApiController.php`
- Methods: `fetchFeedArticles()`, `processArticle()`

---

## Article Deletion Pattern (Task 9 - Complete ✅)
**Implementation Date**: 2026-02-07

### Individual Delete
**Button in View**:
```php
<form method="POST" action="/articles/delete/<?= $article['id'] ?>"
      onsubmit="return confirm('Are you sure you want to delete this article?')">
    <?= $csrf->field() ?>
    <button type="submit" class="btn btn-danger">Delete</button>
</form>
```

**Controller Handling**:
```php
public function delete($id) {
    // Validate CSRF token
    $this->csrf->validateFromPost();

    // Delete article
    $this->articleRepo->delete($id);

    // Redirect with success message
    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Article deleted'];
    header('Location: /articles');
    exit;
}
```

### Bulk Delete
**Checkbox Selection**:
```html
<table data-bulk>
    <thead>
        <tr>
            <th>
                <input type="checkbox" data-select-all
                       aria-label="Select all articles">
            </th>
            <!-- other headers -->
        </tr>
    </thead>
    <tbody>
        <?php foreach ($articles as $article): ?>
        <tr>
            <td>
                <input type="checkbox" data-item="<?= $article['id'] ?>"
                       aria-label="Select article">
            </td>
            <!-- other columns -->
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<div data-bulk-actions style="display: none;">
    <span data-selection-count>0</span> selected
    <button type="button" data-bulk-action="delete" class="btn btn-danger">
        Delete Selected
    </button>
</div>
```

**JavaScript Handling** (`bulk-actions.js`):
```javascript
// Automatically handles:
// - Select all checkbox
// - Individual checkbox selection
// - Selection counter
// - Bulk action button visibility
// - Confirmation dialog
// - Form submission with CSRF token
```

**Form Structure**:
```html
<form id="bulk-delete-form" method="POST" action="/articles/bulk-delete" style="display: none;">
    <?= $csrf->field() ?>
    <input type="hidden" name="article_ids" value="">
</form>
```

**Controller Handling**:
```php
public function bulkDelete() {
    // Validate CSRF token
    $this->csrf->validateFromPost();

    // Get article IDs
    $ids = explode(',', $_POST['article_ids']);

    // Delete each article
    foreach ($ids as $id) {
        $this->articleRepo->delete((int)$id);
    }

    // Redirect with success message
    $_SESSION['flash'] = [
        'type' => 'success',
        'message' => count($ids) . ' articles deleted'
    ];
    header('Location: /articles');
    exit;
}
```

### Confirmation Modal
**Required Include** (in layout footer):
```php
<?php include __DIR__ . '/../components/confirm-modal.php'; ?>
```

**Modal Usage**:
- Automatically triggered by `data-bulk-action` buttons
- Displays custom confirmation message
- Prevents accidental deletions
- Accessible (keyboard navigation, ARIA labels)

### CSRF Protection
- All delete operations require CSRF token
- Token included in all forms automatically via `$csrf->field()`
- Token validated in controller before processing
- Invalid token returns 403 error

### Accessibility
- Select all checkbox has proper ARIA label
- Individual checkboxes have descriptive labels
- Selection counter announced to screen readers
- Confirmation dialog keyboard navigable
- Focus management on modal open/close

---

## Common Issues & Solutions (Updated)

| Issue | Cause | Solution |
|-------|-------|----------|
| Button hover state not working | Missing transition property | Add `transition: all 0.2s ease;` to button CSS |
| Long URLs break mobile layout | No word wrapping on code blocks | Add `word-break: break-all; overflow-wrap: break-word;` to `.code` class |
| Pagination showing wrong page | Type casting issue in controller | Cast page number: `$page = (int)($_GET['page'] ?? 1);` |
| Confirm modal not showing | Footer not included in view | Add `<?php include __DIR__ . '/../components/confirm-modal.php'; ?>` to layout |
| Timestamps showing wrong time | Not converting from UTC | Use `TimezoneHelper::toLocal()` before display |
| CSRF token expired during processing | Long processing time | Validate CSRF only on fetch, not on individual article processing |
| Delete button not working | Missing CSRF token | Include `<?= $csrf->field() ?>` in form |
| Bulk delete not working | JavaScript not loaded | Ensure `bulk-actions.js` included in page |

---

## Comprehensive Troubleshooting Guide

### Installation Issues

| Issue | Symptoms | Solution |
|-------|----------|----------|
| **Database Connection Failed** | "Could not connect to database" error | 1. Verify credentials in `.env`<br>2. Check database exists<br>3. Test: `mysql -u user -p db_name`<br>4. Verify user has privileges |
| **500 Internal Server Error** | White page or generic error | 1. Check PHP error log<br>2. Enable `APP_DEBUG=true` temporarily<br>3. Verify `.htaccess` uploaded<br>4. Check file permissions (755/644)<br>5. Verify mod_rewrite enabled |
| **Composer Install Fails** | Dependencies won't install | 1. Update Composer: `composer self-update`<br>2. Clear cache: `composer clear-cache`<br>3. Check PHP version: `php -v`<br>4. Increase memory: `php -d memory_limit=-1 composer install` |
| **Missing PHP Extensions** | Extension not found errors | 1. Check installed: `php -m`<br>2. Install missing: `apt-get install php8.1-{ext}`<br>3. Enable in php.ini<br>4. Restart web server |
| **Permission Denied** | Can't write to storage | 1. Set permissions: `chmod 755 storage`<br>2. Set ownership: `chown -R www-data:www-data storage`<br>3. Check SELinux: `getenforce` |

### Processing Issues

| Issue | Symptoms | Solution |
|-------|----------|----------|
| **Articles Not Processing** | Feed returns 0 articles | 1. Verify Google News URL is valid<br>2. Check cURL extension: `php -m \| grep curl`<br>3. Test URL in browser<br>4. Check logs table for errors<br>5. Verify timeout settings |
| **Duplicate Detection Not Working** | Same article appears multiple times | 1. Verify unique index on `final_url`<br>2. Check duplicate detection uses `final_url` not `google_news_url`<br>3. Review ArticleRepository::create() logic |
| **URL Decode Fails** | "Failed to decode URL" errors | 1. Check if old-style (CBM/CWM) vs new-style (AU_yqL)<br>2. Verify batchexecute API accessible<br>3. Check network connectivity<br>4. Review UrlDecoder logs |
| **Processing Queue Stuck** | Articles remain in retry status | 1. Check `next_retry_at` timestamp<br>2. Verify cron job running<br>3. Review `last_error` field<br>4. Check retry_count < 3<br>5. Run queue processor manually |
| **High Failure Rate** | Many articles fail processing | 1. Check timeout settings (increase if needed)<br>2. Review common error patterns in logs<br>3. Verify network connectivity<br>4. Check for rate limiting from sources<br>5. Review SSRF validation (may block legitimate URLs) |

### RSS Feed Issues

| Issue | Symptoms | Solution |
|-------|----------|----------|
| **RSS Feed Empty** | Feed generates but no items | 1. Verify articles exist: `SELECT COUNT(*) FROM articles`<br>2. Check status filter<br>3. Verify topic matches: `SELECT DISTINCT topic FROM articles`<br>4. Check processed_at not NULL<br>5. Try without filters: `/feed.php` |
| **RSS Invalid XML** | Feed doesn't validate | 1. Check for unescaped special characters<br>2. Verify CDATA wrapping on content<br>3. Validate with W3C validator<br>4. Check character encoding (UTF-8)<br>5. Review OutputEscaper usage |
| **RSS Not Cached** | Slow feed generation | 1. Verify cache directory writable<br>2. Check cache TTL settings<br>3. Review Cache-Control headers<br>4. Test with ETag: check response headers<br>5. Verify RSS cache implementation |
| **Images Missing in Feed** | No enclosure elements | 1. Check `og_image` or `twitter_image` populated<br>2. Verify image URLs valid<br>3. Review ArticleExtractor image logic<br>4. Check RSS generator includes enclosures |

### API Issues

| Issue | Symptoms | Solution |
|-------|----------|----------|
| **401 Unauthorized** | "Invalid API key" error | 1. Verify API key correct (64 char hex)<br>2. Check header format: `X-API-Key: value`<br>3. Verify key enabled in database<br>4. Check for typos or extra spaces<br>5. Test key exists: `SELECT * FROM api_keys WHERE key_value = ?` |
| **429 Rate Limited** | "Rate limit exceeded" | 1. Wait 60 seconds from first request<br>2. Check request frequency<br>3. Use different API key if needed<br>4. Review rate limit settings<br>5. Consider reducing cron frequency |
| **500 Internal Error** | Generic error response | 1. Check application logs in database<br>2. Enable debug mode temporarily<br>3. Review PHP error log<br>4. Check database connectivity<br>5. Verify all dependencies loaded |

### Database Issues

| Issue | Symptoms | Solution |
|-------|----------|----------|
| **Migration Failed** | SQL errors during import | 1. Check SQL syntax<br>2. Verify database user has privileges<br>3. Check for table locks<br>4. Review migration file for errors<br>5. Try running statements individually |
| **Slow Queries** | Database operations timeout | 1. Verify indexes exist: `SHOW INDEX FROM articles`<br>2. Run EXPLAIN on slow queries<br>3. Check for N+1 query problems<br>4. Optimize with proper indexes<br>5. Review query patterns |
| **Table Not Found** | "Table doesn't exist" error | 1. Verify schema imported: `SHOW TABLES`<br>2. Check database name correct<br>3. Re-import schema.sql<br>4. Verify migrations applied |
| **Duplicate Key Error** | "Duplicate entry" errors | 1. Expected for duplicate articles (by design)<br>2. Check unique constraint on final_url<br>3. Verify duplicate detection logic<br>4. Review insert queries |

### Security Issues

| Issue | Symptoms | Solution |
|-------|----------|----------|
| **CSRF Token Invalid** | "Invalid CSRF token" on form submit | 1. Check session started<br>2. Verify token in form: `$csrf->field()`<br>3. Check token validation: `$csrf->validateFromPost()`<br>4. Review session configuration<br>5. Check for session timeout |
| **XSS Vulnerabilities** | Unescaped output | 1. Use OutputEscaper on all user data<br>2. Review context-appropriate escaping<br>3. Check for direct echo of user input<br>4. Run security tests<br>5. Validate with XSS scanner |
| **SSRF Blocking Valid URLs** | Legitimate URLs rejected | 1. Review UrlValidator whitelist<br>2. Check IP resolution<br>3. Verify URL not private IP<br>4. Review SSRF protection logic<br>5. Add exception if needed (carefully!) |
| **SQL Injection Risk** | Unsafe queries | 1. Use prepared statements everywhere<br>2. Never concatenate user input in SQL<br>3. Review Repository methods<br>4. Run security tests<br>5. Validate with SQL injection scanner |

### Performance Issues

| Issue | Symptoms | Solution |
|-------|----------|----------|
| **Slow Page Load** | Pages take > 2 seconds | 1. Run performance tests<br>2. Check for N+1 queries<br>3. Verify indexes used: EXPLAIN query<br>4. Enable opcode caching<br>5. Profile with Xdebug |
| **High Memory Usage** | Memory limit errors | 1. Check peak memory in performance tests<br>2. Review for memory leaks<br>3. Increase PHP memory_limit if needed<br>4. Optimize bulk operations<br>5. Use generators for large datasets |
| **Database Locks** | Queries timeout waiting | 1. Check for long-running transactions<br>2. Review deadlocks: `SHOW ENGINE INNODB STATUS`<br>3. Optimize transaction scope<br>4. Add appropriate indexes<br>5. Consider row-level locking |

### Testing Issues

| Issue | Symptoms | Solution |
|-------|----------|----------|
| **Tests Fail Locally** | Tests pass in CI, fail locally | 1. Update dependencies: `composer install`<br>2. Check PHP version matches<br>3. Verify database schema current<br>4. Check environment differences<br>5. Review test configuration |
| **Slow Test Execution** | Tests take > 30 seconds | 1. Run unit tests only: `composer test:unit`<br>2. Use SQLite for integration tests<br>3. Disable coverage: `--no-coverage`<br>4. Check for database issues<br>5. Profile slow tests |
| **Coverage Report Missing** | Coverage not generated | 1. Install Xdebug: `pecl install xdebug`<br>2. Enable in php.ini<br>3. Verify: `php -v` shows Xdebug<br>4. Clear coverage cache<br>5. Re-run with coverage flag |

### Deployment Issues

| Issue | Symptoms | Solution |
|-------|----------|----------|
| **CI/CD Deploy Fails** | GitHub Actions deployment error | 1. Check Actions logs for details<br>2. Verify SSH key configured<br>3. Test rsync locally<br>4. Check disk space on server<br>5. Verify file permissions |
| **Site Down After Deploy** | 500/503 errors post-deployment | 1. Check .env file exists<br>2. Verify vendor/ directory uploaded<br>3. Check file permissions<br>4. Review Apache/PHP error logs<br>5. Test health endpoint |
| **Health Check Fails** | /health.php returns error | 1. Check database connectivity<br>2. Verify health.php uploaded<br>3. Check PHP errors<br>4. Review database credentials<br>5. Test database query manually |

### Common Gotchas

1. **Google News URLs expire** - Always use `final_url` for duplicate detection, not `google_news_url`
2. **TEXT column indexes** - Must use prefix: `INDEX(column_name(500))`
3. **OR/AND precedence** - Always parenthesize OR conditions: `(a OR b) AND c`
4. **SSRF validation** - May block legitimate private network URLs in dev environments
5. **Rate limiting is in-memory** - Resets on application restart, not suitable for load balancers
6. **Full key shown once** - API keys displayed fully only at creation, save immediately
7. **CSRF tokens expire** - Session timeout causes token validation to fail
8. **MySQL fulltext search** - Not available in SQLite, performance tests skip on SQLite
9. **Retry queue timing** - Exponential backoff means retries may take hours for 3rd attempt
10. **RSS cache timing** - 5-minute cache may show stale data during testing

### Debugging Tips

**Enable Debug Mode** (development only):
```env
APP_ENV=development
APP_DEBUG=true
```

**Check Application Logs**:
```sql
SELECT * FROM logs
WHERE level IN ('ERROR', 'CRITICAL')
ORDER BY created_at DESC
LIMIT 20;
```

**Test Database Connection**:
```bash
php -r "
require 'vendor/autoload.php';
\$config = parse_ini_file('.env');
\$dsn = \"mysql:host={\$config['DB_HOST']};dbname={\$config['DB_NAME']}\";
new PDO(\$dsn, \$config['DB_USER'], \$config['DB_PASS']);
echo 'Database OK\n';
"
```

**Verify API Key**:
```sql
SELECT id, key_name, enabled, last_used_at
FROM api_keys
WHERE key_value = 'your_key_here';
```

**Check Processing Queue**:
```sql
SELECT id, topic, status, retry_count, last_error
FROM articles
WHERE status = 'pending' AND retry_count > 0
ORDER BY next_retry_at;
```

**Monitor Performance**:
```sql
SELECT
    DATE(created_at) as date,
    COUNT(*) as total,
    AVG(word_count) as avg_words,
    COUNT(CASE WHEN status='failed' THEN 1 END) as failures
FROM articles
WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY DATE(created_at);
```

### Getting Help

1. **Check Documentation**: Review this file and other docs/
2. **Search Issues**: Check GitHub issues for similar problems
3. **Enable Logging**: Set debug mode and review logs table
4. **Run Tests**: `composer test` to verify installation
5. **Check Health**: `/health.php` for basic connectivity
6. **Open Issue**: Provide error details, steps to reproduce, environment info

---

*Last updated: 2026-02-07*
*Update this file when discovering new patterns or gotchas.*
*Wave 6 Complete - Production Ready*
