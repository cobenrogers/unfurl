# ProcessingQueue Quick Reference

**Quick reference for developers using the ProcessingQueue service.**

## Setup

```php
use Unfurl\Services\ProcessingQueue;
use Unfurl\Repositories\ArticleRepository;
use Unfurl\Core\Logger;
use Unfurl\Core\Database;

// Initialize
$db = new Database($config);
$articleRepo = new ArticleRepository($db);
$logger = new Logger('/path/to/logs');
$queue = new ProcessingQueue($articleRepo, $logger);
```

## Common Operations

### 1. Enqueue Failed Article

```php
// Enqueue for retry
$success = $queue->enqueue(
    $articleId,      // int - Article ID
    $errorMessage,   // string - Error description
    $retryCount      // int - Current retry count (0-3)
);

// Returns:
// true  = Queued for retry
// false = Max retries exceeded (permanent failure)
```

### 2. Mark Success

```php
$queue->markComplete($articleId);
```

### 3. Mark Failure

```php
// Automatically handles retry logic
$queue->markFailed(
    $articleId,
    $errorMessage,
    $retryCount
);

// Checks if retryable → enqueues or marks permanent
```

### 4. Get Pending Retries

```php
$articles = $queue->getPendingRetries();

foreach ($articles as $article) {
    // Process article
}
```

### 5. Check Error Type

```php
if ($queue->isRetryable($error)) {
    // Will retry automatically
} else {
    // Permanent failure
}
```

## Retry Delays

| Attempt | Delay | Total Time |
|---------|-------|------------|
| 1st | 60s + 0-10s jitter | ~1 minute |
| 2nd | 120s + 0-10s jitter | ~3 minutes |
| 3rd | 240s + 0-10s jitter | ~7 minutes |
| 4th | Permanent failure | - |

## Error Classification

### ✅ Retryable

- `"Network timeout"`
- `"Connection timeout"`
- `"HTTP 429: Rate Limited"`
- `"HTTP 502: Bad Gateway"`
- `"HTTP 503: Service Unavailable"`
- `"HTTP 504: Gateway Timeout"`
- `"DNS resolution failed"`

### ❌ Permanent

- `"HTTP 404: Not Found"`
- `"HTTP 403: Forbidden"`
- `"Invalid URL format"`
- `"SSRF validation failed"`
- `"No parseable content"`

## Complete Processing Example

```php
// 1. Try to process article
try {
    $result = processArticle($article);

    // Success!
    $queue->markComplete($article['id']);

} catch (Exception $e) {
    // Failure - increment and enqueue
    $queue->incrementRetryCount($article['id']);

    $queue->markFailed(
        $article['id'],
        $e->getMessage(),
        $article['retry_count'] + 1
    );
}
```

## Cron Job Example

```php
// Process pending retries
$pendingArticles = $queue->getPendingRetries();

foreach ($pendingArticles as $article) {
    // Rate limiting
    if (!$queue->canProcessNow()) {
        sleep(1);
        continue;
    }

    try {
        // Process
        $result = processArticle($article);
        $queue->markComplete($article['id']);

    } catch (Exception $e) {
        // Retry logic
        $queue->incrementRetryCount($article['id']);
        $queue->markFailed(
            $article['id'],
            $e->getMessage(),
            $article['retry_count'] + 1
        );
    }

    // Update rate limit
    $queue->setLastProcessTime(time());
}
```

## Constants

```php
ProcessingQueue::MAX_RETRIES = 3;
```

## Database Queries

### Find Ready for Retry
```sql
SELECT * FROM articles
WHERE status = 'failed'
  AND next_retry_at IS NOT NULL
  AND next_retry_at <= NOW()
ORDER BY next_retry_at ASC;
```

### Find Permanent Failures
```sql
SELECT * FROM articles
WHERE status = 'failed'
  AND next_retry_at IS NULL;
```

### Retry Statistics
```sql
SELECT
    retry_count,
    COUNT(*) as count,
    AVG(TIMESTAMPDIFF(SECOND, created_at, next_retry_at)) as avg_wait
FROM articles
WHERE status = 'failed'
  AND next_retry_at IS NOT NULL
GROUP BY retry_count;
```

## Logging

All logs use category: `processing_queue`

### Check Logs
```bash
# Today's processing queue logs
cat /path/to/logs/processing_queue-$(date +%Y-%m-%d).log

# Failed articles
grep "permanently failed" /path/to/logs/processing_queue-*.log

# Retry statistics
grep "queued for retry" /path/to/logs/processing_queue-*.log | wc -l
```

## Troubleshooting

### Articles Not Retrying?
```php
// Check pending retries
$pending = $queue->getPendingRetries();
var_dump(count($pending));

// Check specific article
$article = $articleRepo->findById($articleId);
var_dump($article['next_retry_at']);
var_dump($article['retry_count']);
```

### Too Many Failures?
```sql
-- Top error messages
SELECT last_error, COUNT(*) as count
FROM articles
WHERE status = 'failed'
GROUP BY last_error
ORDER BY count DESC
LIMIT 10;
```

### Reset Retry Count
```php
// Manually reset (use with caution)
$articleRepo->update($articleId, [
    'retry_count' => 0,
    'next_retry_at' => date('Y-m-d H:i:s'),
    'status' => 'failed'
]);
```

## Testing

```bash
# Run ProcessingQueue tests
./vendor/bin/phpunit tests/Unit/Services/ProcessingQueueTest.php --testdox

# Results
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
```

## Best Practices

### ✅ DO

- Always use `markFailed()` for automatic retry logic
- Increment retry count before enqueueing
- Check `canProcessNow()` for rate limiting
- Log all processing attempts
- Use try/catch around processing

### ❌ DON'T

- Manually set `next_retry_at` (use `enqueue()`)
- Skip retry count increment
- Process faster than rate limit
- Retry permanent failures
- Exceed MAX_RETRIES constant

## More Information

- **Full Documentation**: `docs/PROCESSING-QUEUE.md`
- **Requirements**: `docs/requirements/REQUIREMENTS.md` Section 4.2.5
- **Source Code**: `src/Services/ProcessingQueue.php`
- **Tests**: `tests/Unit/Services/ProcessingQueueTest.php`

---

**Last Updated**: 2026-02-07
