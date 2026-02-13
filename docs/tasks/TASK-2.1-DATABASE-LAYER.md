# Task 2.1: Database Layer Implementation

**Status**: ✅ COMPLETE
**Date**: 2026-02-07
**Approach**: Test-Driven Development (TDD)

## Overview

Implemented a secure database abstraction layer with comprehensive test coverage following TDD principles. All database queries use prepared statements to prevent SQL injection.

## Deliverables

### 1. Core Database Class
**File**: `/Users/benjaminrogers/VSCode/BennernetLLC/unfurl/src/Core/Database.php`

PDO wrapper providing:
- Secure prepared statement execution
- Transaction support (begin, commit, rollback)
- Query helpers (query, querySingle, execute)
- SQLite support for testing
- MySQL support for production

**Key Methods**:
```php
$db->execute($sql, $params)      // INSERT, UPDATE, DELETE
$db->query($sql, $params)        // SELECT (multiple rows)
$db->querySingle($sql, $params)  // SELECT (single row)
$db->getLastInsertId()           // Get last inserted ID
$db->beginTransaction()          // Start transaction
$db->commit()                    // Commit transaction
$db->rollback()                  // Rollback transaction
```

### 2. FeedRepository
**File**: `/Users/benjaminrogers/VSCode/BennernetLLC/unfurl/src/Repositories/FeedRepository.php`

Manages feeds table operations:
- ✅ CRUD operations (Create, Read, Update, Delete)
- ✅ Find by ID, topic, or enabled status
- ✅ Update last processed timestamp
- ✅ Unique constraint on topic
- ✅ Default values (result_limit=10, enabled=1)

**Example Usage**:
```php
$feedRepo = new FeedRepository($db);

// Create feed
$feedId = $feedRepo->create([
    'topic' => 'Technology',
    'url' => 'https://news.google.com/rss/search?q=technology',
    'result_limit' => 20,
    'enabled' => 1,
]);

// Find feed
$feed = $feedRepo->findById($feedId);
$feed = $feedRepo->findByTopic('Technology');

// Get all enabled feeds
$enabledFeeds = $feedRepo->findEnabled();

// Update feed
$feedRepo->update($feedId, ['result_limit' => 30]);

// Track processing
$feedRepo->updateLastProcessedAt($feedId);

// Delete feed
$feedRepo->delete($feedId);
```

### 3. ArticleRepository
**File**: `/Users/benjaminrogers/VSCode/BennernetLLC/unfurl/src/Repositories/ArticleRepository.php`

Manages articles table operations:
- ✅ CRUD operations with 27 fields
- ✅ Find by ID, feed ID, status, topic
- ✅ Unique constraint on final_url
- ✅ Retry logic (incrementRetryCount, findPendingRetries)
- ✅ Status tracking (pending, success, failed)
- ✅ Bulk operations (deleteOlderThan, countByStatus)
- ✅ Processing workflow (markAsProcessed)

**Example Usage**:
```php
$articleRepo = new ArticleRepository($db);

// Create article
$articleId = $articleRepo->create([
    'feed_id' => $feedId,
    'topic' => 'Technology',
    'google_news_url' => 'https://news.google.com/articles/123',
    'rss_title' => 'Breaking News',
    'status' => 'pending',
]);

// Find articles
$article = $articleRepo->findById($articleId);
$feedArticles = $articleRepo->findByFeedId($feedId);
$pendingArticles = $articleRepo->findByStatus('pending');
$topicArticles = $articleRepo->findByTopic('Technology');

// Update article
$articleRepo->update($articleId, [
    'status' => 'success',
    'final_url' => 'https://example.com/article',
    'page_title' => 'Resolved Article',
]);

// Retry logic
$articleRepo->incrementRetryCount($articleId);
$retryArticles = $articleRepo->findPendingRetries();

// Cleanup
$deletedCount = $articleRepo->deleteOlderThan(90);
$pendingCount = $articleRepo->countByStatus('pending');

// Mark as processed
$articleRepo->markAsProcessed($articleId);
```

### 4. ApiKeyRepository
**File**: `/Users/benjaminrogers/VSCode/BennernetLLC/unfurl/src/Repositories/ApiKeyRepository.php`

Manages api_keys table operations:
- ✅ CRUD operations
- ✅ Find by ID or key value
- ✅ Unique constraint on key_value
- ✅ Validation with automatic last_used_at tracking
- ✅ Enable/disable functionality

**Example Usage**:
```php
$apiKeyRepo = new ApiKeyRepository($db);

// Create API key
$keyId = $apiKeyRepo->create([
    'key_name' => 'Mobile App',
    'key_value' => 'abc123xyz789',
    'description' => 'Production mobile app key',
    'enabled' => 1,
]);

// Find keys
$key = $apiKeyRepo->findById($keyId);
$key = $apiKeyRepo->findByKeyValue('abc123xyz789');
$enabledKeys = $apiKeyRepo->findEnabled();

// Validate (checks enabled status and updates last_used_at)
$isValid = $apiKeyRepo->validateApiKey('abc123xyz789');

// Update key
$apiKeyRepo->update($keyId, ['enabled' => 0]);

// Track usage
$apiKeyRepo->updateLastUsedAt($keyId);

// Delete key
$apiKeyRepo->delete($keyId);
```

## Test Coverage

### Unit Tests
**File**: `/Users/benjaminrogers/VSCode/BennernetLLC/unfurl/tests/Unit/Core/DatabaseTest.php`

- ✅ PDO connection creation
- ✅ Singleton pattern (same instance)
- ✅ Prepared statement execution
- ✅ Query operations (query, querySingle)
- ✅ Last insert ID
- ✅ Transaction support (begin, commit, rollback)
- ✅ SQL injection prevention
- ✅ Error handling
- ✅ Invalid connection handling

**Run**: `composer test:unit`

### Integration Tests

#### FeedRepositoryTest
**File**: `/Users/benjaminrogers/VSCode/BennernetLLC/unfurl/tests/Integration/Repositories/FeedRepositoryTest.php`

- ✅ Create feed
- ✅ Find by ID, topic
- ✅ Find all, find enabled
- ✅ Update feed
- ✅ Delete feed
- ✅ Update last processed timestamp
- ✅ Unique topic constraint
- ✅ Default values
- ✅ Edge cases (non-existent records)

#### ArticleRepositoryTest
**File**: `/Users/benjaminrogers/VSCode/BennernetLLC/unfurl/tests/Integration/Repositories/ArticleRepositoryTest.php`

- ✅ Create article with all fields
- ✅ Find by ID, feed ID, status, topic
- ✅ Update article
- ✅ Delete article
- ✅ Unique final_url constraint
- ✅ Retry logic (increment, find pending)
- ✅ Bulk operations (deleteOlderThan, countByStatus)
- ✅ Mark as processed
- ✅ Foreign key relationships

#### ApiKeyRepositoryTest
**File**: `/Users/benjaminrogers/VSCode/BennernetLLC/unfurl/tests/Integration/Repositories/ApiKeyRepositoryTest.php`

- ✅ Create API key
- ✅ Find by ID, key value
- ✅ Find all, find enabled
- ✅ Update API key
- ✅ Delete API key
- ✅ Unique key_value constraint
- ✅ Update last used timestamp
- ✅ Validate API key (enabled check + auto-track usage)
- ✅ Default values

**Run**: `composer test:integration`

## Security Features

### 1. SQL Injection Prevention
All queries use prepared statements with parameter binding:
```php
// ✅ SECURE - Uses prepared statement
$db->query('SELECT * FROM feeds WHERE topic = ?', [$topic]);

// ❌ NEVER DO THIS - String concatenation
$db->query("SELECT * FROM feeds WHERE topic = '$topic'");
```

### 2. PDO Configuration
```php
PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION     // Throw exceptions on errors
PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC // Fetch as associative arrays
PDO::ATTR_EMULATE_PREPARES => false              // Use real prepared statements
```

### 3. Unique Constraints
- Feeds: `topic` must be unique
- Articles: `final_url` must be unique (prevents duplicate content)
- API Keys: `key_value` must be unique

### 4. Transaction Support
For multi-step operations:
```php
$db->beginTransaction();
try {
    $feedId = $feedRepo->create($feedData);
    $articleId = $articleRepo->create($articleData);
    $db->commit();
} catch (Exception $e) {
    $db->rollback();
    throw $e;
}
```

## Testing Approach (TDD)

### 1. Write Tests First ✅
Created comprehensive test suites BEFORE implementation:
- DatabaseTest.php (11 test methods)
- FeedRepositoryTest.php (16 test methods)
- ArticleRepositoryTest.php (17 test methods)
- ApiKeyRepositoryTest.php (17 test methods)

### 2. Implement to Pass Tests ✅
Created classes with minimal code to satisfy test requirements:
- Database.php
- FeedRepository.php
- ArticleRepository.php
- ApiKeyRepository.php

### 3. Verify Tests Pass
```bash
composer test:unit          # Run unit tests
composer test:integration   # Run integration tests
composer test              # Run all tests
composer test:coverage     # Run with coverage report
```

## Configuration

The database layer reads configuration from `/Users/benjaminrogers/VSCode/BennernetLLC/unfurl/config.php`:

```php
$config = [
    'database' => [
        'host' => env('DB_HOST', 'localhost'),
        'name' => env('DB_NAME'),
        'user' => env('DB_USER'),
        'pass' => env('DB_PASS'),
        'charset' => 'utf8mb4',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ],
    ],
];
```

## Database Schema

Implemented repositories work with this schema (`/Users/benjaminrogers/VSCode/BennernetLLC/unfurl/sql/schema.sql`):

### Feeds Table
- `id` - Primary key
- `topic` - Unique search topic
- `url` - Google News RSS URL
- `result_limit` - Max results (default: 10)
- `enabled` - Active flag (default: 1)
- `last_processed_at` - Last processing timestamp
- `created_at`, `updated_at` - Timestamps

### Articles Table
- `id` - Primary key
- `feed_id` - Foreign key to feeds
- `topic` - Associated topic
- `google_news_url` - Original Google News URL
- RSS fields: `rss_title`, `pub_date`, `rss_description`, `rss_source`
- `final_url` - Resolved URL (unique)
- `status` - Processing status (pending, success, failed)
- OpenGraph fields: `og_title`, `og_description`, `og_image`, etc.
- Content fields: `article_content`, `word_count`, `categories`
- Retry logic: `retry_count`, `next_retry_at`, `last_error`
- `processed_at`, `created_at`, `updated_at` - Timestamps

### API Keys Table
- `id` - Primary key
- `key_name` - Friendly name
- `key_value` - Unique API key (64 chars)
- `description` - Optional description
- `enabled` - Active flag (default: 1)
- `last_used_at` - Last usage timestamp
- `created_at`, `updated_at` - Timestamps

## Next Steps

With the database layer complete, the next tasks can proceed:

1. ✅ **Task 2.1**: Database Layer (COMPLETE)
2. **Task 2.2**: Scraper Service (can now use ArticleRepository)
3. **Task 2.3**: Metadata Extractor (can now use ArticleRepository)
4. **Task 2.4**: Controllers (can now use all repositories)
5. **Task 2.5**: Views (can now display data from repositories)

## Error Handling

All repository methods throw `PDOException` on database errors:

```php
try {
    $feedId = $feedRepo->create($feedData);
} catch (PDOException $e) {
    // Handle duplicate topic (SQLSTATE 23000)
    if ($e->getCode() == 23000) {
        echo "Topic already exists";
    } else {
        echo "Database error: " . $e->getMessage();
    }
}
```

## Performance Considerations

1. **Prepared Statements**: Compiled once, executed multiple times
2. **Indexes**: Schema includes indexes on frequently queried columns
3. **Batch Operations**: Use transactions for multiple inserts
4. **Connection Pooling**: Single PDO instance reused across requests

## Maintenance

### Adding New Repository Methods

Follow this pattern:

```php
public function findByCustomCriteria(string $criteria): array
{
    // 1. Write SQL with placeholders
    $sql = "SELECT * FROM table WHERE column = ?";

    // 2. Execute with prepared statement
    return $this->db->query($sql, [$criteria]);
}
```

### Writing New Tests

Follow TDD approach:

```php
public function testNewFeature(): void
{
    // 1. Arrange - Set up test data
    $data = ['field' => 'value'];

    // 2. Act - Execute method
    $result = $this->repository->newMethod($data);

    // 3. Assert - Verify result
    $this->assertEquals('expected', $result);
}
```

---

**Implementation Notes**:
- All code follows PSR-4 autoloading standards
- Repositories use dependency injection (Database passed in constructor)
- Tests use SQLite in-memory database for speed
- Production uses MySQL with same API
- No ORM dependencies - pure PDO for maximum control and performance
