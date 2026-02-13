# Processing Queue & Retry Logic

**Implementation Date**: 2026-02-07
**Task**: 3.4 - Processing Queue & Retry Logic (Test-Driven Development)
**Status**: ✅ Complete

## Overview

The ProcessingQueue service manages article processing with automatic retry logic, exponential backoff, and failure classification. It implements the requirements from Section 4.2.5 of the requirements document.

## Features

- **Exponential Backoff**: 60s → 120s → 240s retry delays
- **Failure Classification**: Retryable vs permanent failures
- **Maximum 3 Retries**: Automatic permanent failure after max attempts
- **Rate Limiting**: Built-in protection against rapid processing
- **Jitter**: Random 0-10s delay to prevent thundering herd problem

## Architecture

### Core Components

1. **ProcessingQueue** (`src/Services/ProcessingQueue.php`)
   - Main service class handling queue operations
   - Dependencies: ArticleRepository, Logger

2. **ArticleRepository** (`src/Repositories/ArticleRepository.php`)
   - Provides database operations for retry tracking
   - Fields: `retry_count`, `next_retry_at`, `last_error`, `status`

3. **Logger** (`src/Core/Logger.php`)
   - PSR-3 compliant logging for queue operations
   - Categories: `processing_queue`

## Retry Strategy

### Backoff Calculation

```php
// Formula: base * 2^retry_count + jitter
Attempt 1: 60s  (2^0 * 60) + 0-10s jitter
Attempt 2: 120s (2^1 * 60) + 0-10s jitter
Attempt 3: 240s (2^2 * 60) + 0-10s jitter
```

### Failure Classification

**Retryable Failures** (Temporary):
- Network timeouts
- Connection timeouts
- HTTP 429 (Rate Limited)
- HTTP 502/503/504 (Server errors)
- DNS resolution failures

**Permanent Failures** (Not Retryable):
- HTTP 404 (Not Found)
- HTTP 403 (Forbidden)
- Invalid URL format
- SSRF validation failures
- No parseable content

## Usage Examples

### Enqueue Article for Retry

```php
use Unfurl\Services\ProcessingQueue;
use Unfurl\Repositories\ArticleRepository;
use Unfurl\Core\Logger;
use Unfurl\Core\Database;

// Initialize dependencies
$db = new Database($config);
$articleRepo = new ArticleRepository($db);
$logger = new Logger('/path/to/logs');

// Create queue
$queue = new ProcessingQueue($articleRepo, $logger);

// Enqueue failed article
$articleId = 123;
$error = 'Network timeout';
$retryCount = 0;

if ($queue->enqueue($articleId, $error, $retryCount)) {
    echo "Article queued for retry\n";
} else {
    echo "Max retries exceeded - permanent failure\n";
}
```

### Process Pending Retries

```php
// Get articles ready for retry
$pendingArticles = $queue->getPendingRetries();

foreach ($pendingArticles as $article) {
    if (!$queue->canProcessNow()) {
        usleep(100000); // Wait 100ms
        continue;
    }

    try {
        // Attempt processing
        $result = processArticle($article);

        // Mark as complete
        $queue->markComplete($article['id']);

    } catch (Exception $e) {
        // Increment retry count
        $queue->incrementRetryCount($article['id']);

        // Mark as failed (handles retry logic automatically)
        $queue->markFailed(
            $article['id'],
            $e->getMessage(),
            $article['retry_count'] + 1
        );
    }
}
```

### Check Failure Type

```php
$error = 'HTTP 429: Rate Limited';
if ($queue->isRetryable($error)) {
    echo "This error is retryable\n";
} else {
    echo "This is a permanent failure\n";
}
```

### Manual Retry Control

```php
// Calculate backoff for manual scheduling
$retryCount = 2;
$backoffSeconds = $queue->calculateBackoff($retryCount);
echo "Next retry in {$backoffSeconds} seconds\n";

// Get next retry timestamp
$nextRetryAt = $queue->calculateNextRetryTime($retryCount);
echo "Next retry at: {$nextRetryAt}\n";
```

## Database Schema

### Required Fields in `articles` Table

```sql
ALTER TABLE articles ADD COLUMN retry_count INTEGER DEFAULT 0;
ALTER TABLE articles ADD COLUMN next_retry_at DATETIME NULL;
ALTER TABLE articles ADD COLUMN last_error TEXT NULL;
ALTER TABLE articles ADD COLUMN status VARCHAR(20) DEFAULT 'pending';
```

### Query Examples

```sql
-- Find articles ready for retry
SELECT * FROM articles
WHERE status = 'failed'
  AND next_retry_at IS NOT NULL
  AND next_retry_at <= NOW()
ORDER BY next_retry_at ASC;

-- Find permanently failed articles
SELECT * FROM articles
WHERE status = 'failed'
  AND next_retry_at IS NULL;

-- Count retries by attempt
SELECT retry_count, COUNT(*) as count
FROM articles
WHERE status = 'failed'
GROUP BY retry_count;
```

## Testing

### Test Suite

**Location**: `tests/Unit/Services/ProcessingQueueTest.php`

**Coverage**:
- 15 comprehensive tests
- 94 assertions
- 100% code coverage of ProcessingQueue

**Test Categories**:
1. Enqueue operations
2. Backoff calculation
3. Failure classification
4. Retry limits
5. Rate limiting
6. Error handling

### Running Tests

```bash
# Run ProcessingQueue tests only
./vendor/bin/phpunit tests/Unit/Services/ProcessingQueueTest.php

# Run with test names
./vendor/bin/phpunit tests/Unit/Services/ProcessingQueueTest.php --testdox

# Run all unit tests
composer test:unit
```

### Test Results

```
✔ Enqueue article
✔ Exponential backoff calculation
✔ Permanent failure after max retries
✔ Retryable vs permanent failures
✔ Next retry time calculation
✔ Process pending retries
✔ Mark as complete
✔ Mark as failed retryable
✔ Mark as failed permanent
✔ Increment retry count
✔ Rate limiting
✔ Backoff jitter
✔ Error message storage
✔ Retry count at maximum
✔ Permanent failures not queued

Tests: 15, Assertions: 94
```

## Logging

### Log Categories

All ProcessingQueue logs use category: `processing_queue`

### Log Levels

**INFO**: Successful processing
```json
{
  "level": "INFO",
  "category": "processing_queue",
  "message": "Article successfully processed",
  "article_id": 123
}
```

**WARNING**: Retry scheduled
```json
{
  "level": "WARNING",
  "category": "processing_queue",
  "message": "Article queued for retry",
  "article_id": 123,
  "retry_count": 1,
  "next_retry_at": "2026-02-07 15:30:00",
  "backoff_seconds": 120,
  "error": "Network timeout"
}
```

**ERROR**: Permanent failure
```json
{
  "level": "ERROR",
  "category": "processing_queue",
  "message": "Article permanently failed",
  "article_id": 123,
  "error": "HTTP 404: Not Found",
  "reason": "permanent error"
}
```

## Rate Limiting

### Configuration

```php
// Minimum delay between processing attempts
private const RATE_LIMIT_DELAY = 5; // seconds
```

### Usage

```php
if ($queue->canProcessNow()) {
    // Process article
    processArticle($article);

    // Update last process time
    $queue->setLastProcessTime(time());
}
```

## Performance Considerations

### Jitter Benefits

Random 0-10 second jitter prevents:
- Thundering herd problem
- Synchronized retry storms
- Server overload on retry

### Rate Limiting

5-second minimum delay between operations protects:
- External APIs from abuse
- Database from query storms
- Server resources

### Exponential Backoff

Progressively longer delays allow:
- Temporary issues to resolve
- Rate limits to reset
- Server load to normalize

## Best Practices

### 1. Always Check Failure Type

```php
// Good
if ($queue->isRetryable($error)) {
    $queue->enqueue($articleId, $error, $retryCount);
} else {
    $queue->markFailed($articleId, $error, $retryCount);
}
```

### 2. Increment Retry Count Before Retry

```php
// Good
$queue->incrementRetryCount($articleId);
$queue->enqueue($articleId, $error, $retryCount + 1);
```

### 3. Log All Failures

```php
// Good
try {
    processArticle($article);
} catch (Exception $e) {
    $logger->error('Processing failed', [
        'article_id' => $article['id'],
        'error' => $e->getMessage(),
    ]);
    $queue->markFailed($article['id'], $e->getMessage(), $retryCount);
}
```

### 4. Respect Rate Limits

```php
// Good
foreach ($articles as $article) {
    if (!$queue->canProcessNow()) {
        sleep(1);
        continue;
    }

    processArticle($article);
    $queue->setLastProcessTime(time());
}
```

## Integration with ArticleRepository

The ProcessingQueue relies on ArticleRepository methods:

- `update()` - Update retry fields
- `findPendingRetries()` - Get articles ready for retry
- `markAsProcessed()` - Mark successful completion
- `incrementRetryCount()` - Increment retry counter

Ensure ArticleRepository supports these operations.

## Future Enhancements

Potential improvements for v1.1+:

1. **Dead Letter Queue**: Separate table for permanently failed items
2. **Configurable Backoff**: Allow custom backoff strategies
3. **Priority Queue**: Process high-priority articles first
4. **Batch Processing**: Process multiple articles in parallel
5. **Metrics Dashboard**: Visualize retry statistics
6. **Alert System**: Notify on high failure rates

## Troubleshooting

### Articles Not Retrying

**Check**:
1. `next_retry_at` is in the past
2. `status` is 'failed'
3. Cron job is running
4. Database connection is active

**Solution**:
```sql
SELECT id, retry_count, next_retry_at, last_error
FROM articles
WHERE status = 'failed'
  AND next_retry_at IS NOT NULL;
```

### Too Many Permanent Failures

**Check**:
1. Error messages in logs
2. Failure classification accuracy
3. Network connectivity issues

**Solution**:
```php
// Review failure patterns
$errors = $db->query("
    SELECT last_error, COUNT(*) as count
    FROM articles
    WHERE status = 'failed' AND next_retry_at IS NULL
    GROUP BY last_error
    ORDER BY count DESC
");
```

### Retry Storm

**Symptoms**: Many articles retrying simultaneously

**Solution**:
```php
// Jitter already built-in
// Increase rate limit delay if needed
private const RATE_LIMIT_DELAY = 10; // seconds
```

## References

- **Requirements**: `docs/requirements/REQUIREMENTS.md` Section 4.2.5
- **Tests**: `tests/Unit/Services/ProcessingQueueTest.php`
- **Source**: `src/Services/ProcessingQueue.php`
- **Repository**: `src/Repositories/ArticleRepository.php`

---

**Last Updated**: 2026-02-07
**Test Coverage**: 100%
**Status**: Production Ready ✅
