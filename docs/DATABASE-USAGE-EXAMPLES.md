# Database Layer Usage Examples

This document provides practical examples of using the database layer in the Unfurl application.

## Setup

### Initialize Database Connection

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use Unfurl\Core\Database;
use Unfurl\Repositories\FeedRepository;
use Unfurl\Repositories\ArticleRepository;
use Unfurl\Repositories\ApiKeyRepository;

// Load configuration
$config = require __DIR__ . '/config.php';

// Create database instance
$db = new Database($config);

// Create repository instances
$feedRepo = new FeedRepository($db);
$articleRepo = new ArticleRepository($db);
$apiKeyRepo = new ApiKeyRepository($db);
```

## Feed Management

### Create and Manage Feeds

```php
// Create a new feed
try {
    $feedId = $feedRepo->create([
        'topic' => 'Artificial Intelligence',
        'url' => 'https://news.google.com/rss/search?q=artificial+intelligence',
        'result_limit' => 20,
        'enabled' => 1,
    ]);

    echo "Created feed with ID: {$feedId}\n";
} catch (PDOException $e) {
    if ($e->getCode() == 23000) {
        echo "Error: Topic 'Artificial Intelligence' already exists\n";
    } else {
        echo "Database error: " . $e->getMessage() . "\n";
    }
}

// Get all enabled feeds for processing
$enabledFeeds = $feedRepo->findEnabled();
foreach ($enabledFeeds as $feed) {
    echo "Processing feed: {$feed['topic']}\n";

    // Process feed here...

    // Update last processed timestamp
    $feedRepo->updateLastProcessedAt($feed['id']);
}

// Update a feed
$feedRepo->update($feedId, [
    'result_limit' => 30,
    'enabled' => 0, // Disable feed
]);

// Find specific feed
$feed = $feedRepo->findByTopic('Artificial Intelligence');
if ($feed) {
    echo "Feed URL: {$feed['url']}\n";
}
```

## Article Processing

### Save Articles from RSS Feed

```php
// Typical workflow: Save articles from Google News RSS
$feedId = 1;
$rssArticles = [
    [
        'title' => 'AI Breakthrough in Healthcare',
        'link' => 'https://news.google.com/articles/CAIiEFd...',
        'pubDate' => '2026-02-07 10:30:00',
        'description' => 'Researchers announce major AI advancement...',
        'source' => 'TechNews',
    ],
    // More articles...
];

foreach ($rssArticles as $rssArticle) {
    try {
        $articleId = $articleRepo->create([
            'feed_id' => $feedId,
            'topic' => 'Artificial Intelligence',
            'google_news_url' => $rssArticle['link'],
            'rss_title' => $rssArticle['title'],
            'pub_date' => $rssArticle['pubDate'],
            'rss_description' => $rssArticle['description'],
            'rss_source' => $rssArticle['source'],
            'status' => 'pending',
        ]);

        echo "Saved article: {$rssArticle['title']} (ID: {$articleId})\n";
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            echo "Skipping duplicate article: {$rssArticle['title']}\n";
        } else {
            throw $e;
        }
    }
}
```

### Process Pending Articles

```php
// Get all pending articles
$pendingArticles = $articleRepo->findByStatus('pending');

foreach ($pendingArticles as $article) {
    echo "Processing article ID {$article['id']}...\n";

    try {
        // Decode Google News URL (your scraper logic here)
        $finalUrl = unfurlGoogleNewsUrl($article['google_news_url']);

        // Extract metadata (your extractor logic here)
        $metadata = extractMetadata($finalUrl);

        // Update article with resolved data
        $articleRepo->update($article['id'], [
            'final_url' => $finalUrl,
            'status' => 'success',
            'page_title' => $metadata['title'] ?? null,
            'og_title' => $metadata['og_title'] ?? null,
            'og_description' => $metadata['og_description'] ?? null,
            'og_image' => $metadata['og_image'] ?? null,
            'author' => $metadata['author'] ?? null,
            'article_content' => $metadata['content'] ?? null,
            'word_count' => str_word_count($metadata['content'] ?? ''),
        ]);

        // Mark as processed
        $articleRepo->markAsProcessed($article['id']);

        echo "Successfully processed article ID {$article['id']}\n";
    } catch (Exception $e) {
        // Handle failure with retry logic
        $articleRepo->update($article['id'], [
            'status' => 'failed',
            'error_message' => $e->getMessage(),
            'last_error' => date('Y-m-d H:i:s'),
            'next_retry_at' => date('Y-m-d H:i:s', strtotime('+1 hour')),
        ]);

        $articleRepo->incrementRetryCount($article['id']);

        echo "Failed to process article ID {$article['id']}: {$e->getMessage()}\n";
    }
}
```

### Retry Failed Articles

```php
// Get articles ready for retry
$retryArticles = $articleRepo->findPendingRetries();

foreach ($retryArticles as $article) {
    echo "Retrying article ID {$article['id']} (attempt {$article['retry_count']})...\n";

    // Check if max retries exceeded
    $maxRetries = 3;
    if ($article['retry_count'] >= $maxRetries) {
        echo "Max retries exceeded for article ID {$article['id']}, giving up.\n";
        continue;
    }

    // Retry processing logic here...
}
```

### Query Articles by Topic

```php
$topic = 'Artificial Intelligence';
$articles = $articleRepo->findByTopic($topic);

echo "Found " . count($articles) . " articles for topic: {$topic}\n";

foreach ($articles as $article) {
    echo "- {$article['rss_title']} ({$article['status']})\n";
}
```

### Cleanup Old Articles

```php
// Delete articles older than 90 days
$retentionDays = 90;
$deletedCount = $articleRepo->deleteOlderThan($retentionDays);

echo "Deleted {$deletedCount} articles older than {$retentionDays} days\n";

// Get statistics
$pendingCount = $articleRepo->countByStatus('pending');
$successCount = $articleRepo->countByStatus('success');
$failedCount = $articleRepo->countByStatus('failed');

echo "Article statistics:\n";
echo "- Pending: {$pendingCount}\n";
echo "- Success: {$successCount}\n";
echo "- Failed: {$failedCount}\n";
```

## API Key Management

### Create and Validate API Keys

```php
// Generate a secure API key
$apiKey = bin2hex(random_bytes(32));

// Create API key record
$keyId = $apiKeyRepo->create([
    'key_name' => 'Mobile App v1',
    'key_value' => $apiKey,
    'description' => 'Production mobile application',
    'enabled' => 1,
]);

echo "Created API key: {$apiKey}\n";

// Validate API key (e.g., in middleware)
function authenticateRequest(string $apiKey, ApiKeyRepository $apiKeyRepo): bool
{
    // validateApiKey checks enabled status AND updates last_used_at
    if (!$apiKeyRepo->validateApiKey($apiKey)) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid or disabled API key']);
        return false;
    }

    return true;
}

// Example request handling
$requestApiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';

if (authenticateRequest($requestApiKey, $apiKeyRepo)) {
    // Process authenticated request
    echo "Request authenticated\n";
}

// Disable API key
$apiKeyRepo->update($keyId, ['enabled' => 0]);

// Get all active API keys
$activeKeys = $apiKeyRepo->findEnabled();
foreach ($activeKeys as $key) {
    echo "Active key: {$key['key_name']} (last used: {$key['last_used_at']})\n";
}
```

## Transaction Examples

### Multi-Step Operations

```php
// Create feed with initial articles atomically
$db->beginTransaction();

try {
    // Create feed
    $feedId = $feedRepo->create([
        'topic' => 'Climate Change',
        'url' => 'https://news.google.com/rss/search?q=climate+change',
    ]);

    // Create initial articles
    $articleIds = [];
    foreach ($initialArticles as $article) {
        $articleIds[] = $articleRepo->create([
            'feed_id' => $feedId,
            'topic' => 'Climate Change',
            'google_news_url' => $article['url'],
            'rss_title' => $article['title'],
            'status' => 'pending',
        ]);
    }

    $db->commit();

    echo "Created feed {$feedId} with " . count($articleIds) . " articles\n";
} catch (Exception $e) {
    $db->rollback();
    echo "Failed to create feed: {$e->getMessage()}\n";
}
```

### Batch Processing with Transactions

```php
// Process multiple articles in a transaction
$articles = $articleRepo->findByStatus('pending');
$batchSize = 10;
$processed = 0;

foreach (array_chunk($articles, $batchSize) as $batch) {
    $db->beginTransaction();

    try {
        foreach ($batch as $article) {
            // Process article
            $finalUrl = processArticle($article);

            // Update article
            $articleRepo->update($article['id'], [
                'final_url' => $finalUrl,
                'status' => 'success',
            ]);

            $processed++;
        }

        $db->commit();
        echo "Processed batch of {$batchSize} articles\n";
    } catch (Exception $e) {
        $db->rollback();
        echo "Batch processing failed: {$e->getMessage()}\n";
        break; // Stop processing batches on failure
    }
}

echo "Total processed: {$processed} articles\n";
```

## Error Handling Patterns

### Handle Duplicate Entries

```php
// Gracefully handle duplicate topics
function createFeedIfNotExists(FeedRepository $feedRepo, array $feedData): ?int
{
    try {
        return $feedRepo->create($feedData);
    } catch (PDOException $e) {
        // SQLSTATE 23000 = Integrity constraint violation
        if ($e->getCode() == 23000) {
            // Feed already exists, find and return it
            $existingFeed = $feedRepo->findByTopic($feedData['topic']);
            return $existingFeed['id'] ?? null;
        }

        // Re-throw other database errors
        throw $e;
    }
}

// Usage
$feedId = createFeedIfNotExists($feedRepo, [
    'topic' => 'Space Exploration',
    'url' => 'https://news.google.com/rss/search?q=space',
]);
```

### Retry Logic with Exponential Backoff

```php
function processWithRetry(ArticleRepository $articleRepo, int $articleId, int $maxRetries = 3): bool
{
    $retryCount = 0;

    while ($retryCount < $maxRetries) {
        try {
            $article = $articleRepo->findById($articleId);

            // Process article
            $result = processArticle($article);

            // Update on success
            $articleRepo->markAsProcessed($articleId);

            return true;
        } catch (Exception $e) {
            $retryCount++;

            // Calculate exponential backoff: 1s, 2s, 4s, 8s...
            $delay = pow(2, $retryCount);

            $articleRepo->update($articleId, [
                'status' => 'failed',
                'last_error' => $e->getMessage(),
                'next_retry_at' => date('Y-m-d H:i:s', time() + $delay),
            ]);

            $articleRepo->incrementRetryCount($articleId);

            if ($retryCount >= $maxRetries) {
                echo "Max retries exceeded for article {$articleId}\n";
                return false;
            }

            sleep($delay); // Wait before retry
        }
    }

    return false;
}
```

## Dashboard Statistics

### Generate Statistics for Admin Dashboard

```php
function getDashboardStats(
    FeedRepository $feedRepo,
    ArticleRepository $articleRepo,
    ApiKeyRepository $apiKeyRepo
): array {
    return [
        'feeds' => [
            'total' => count($feedRepo->findAll()),
            'enabled' => count($feedRepo->findEnabled()),
        ],
        'articles' => [
            'pending' => $articleRepo->countByStatus('pending'),
            'success' => $articleRepo->countByStatus('success'),
            'failed' => $articleRepo->countByStatus('failed'),
            'total' => array_sum([
                $articleRepo->countByStatus('pending'),
                $articleRepo->countByStatus('success'),
                $articleRepo->countByStatus('failed'),
            ]),
        ],
        'api_keys' => [
            'total' => count($apiKeyRepo->findAll()),
            'enabled' => count($apiKeyRepo->findEnabled()),
        ],
    ];
}

// Usage
$stats = getDashboardStats($feedRepo, $articleRepo, $apiKeyRepo);
echo "Dashboard Statistics:\n";
echo json_encode($stats, JSON_PRETTY_PRINT);
```

## Search and Filter

### Complex Queries

```php
// Get recent successful articles with metadata
function getRecentSuccessfulArticles(ArticleRepository $articleRepo, int $limit = 10): array
{
    // While repositories provide basic queries, complex queries can be done via Database
    $db = $articleRepo->db; // Assuming we expose $db or add custom methods

    $sql = "
        SELECT
            a.id,
            a.rss_title,
            a.final_url,
            a.og_image,
            a.author,
            a.word_count,
            a.processed_at,
            f.topic
        FROM articles a
        JOIN feeds f ON a.feed_id = f.id
        WHERE a.status = 'success'
        AND a.og_image IS NOT NULL
        ORDER BY a.processed_at DESC
        LIMIT ?
    ";

    return $db->query($sql, [$limit]);
}
```

## RSS Feed Generation

### Generate RSS Feed from Articles

```php
function generateRssFeed(ArticleRepository $articleRepo, string $topic): string
{
    $articles = $articleRepo->findByTopic($topic);
    $successfulArticles = array_filter($articles, fn($a) => $a['status'] === 'success');

    $rss = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><rss version="2.0"></rss>');
    $channel = $rss->addChild('channel');
    $channel->addChild('title', "Unfurl Feed: {$topic}");
    $channel->addChild('description', "Decoded Google News articles for {$topic}");
    $channel->addChild('link', 'https://unfurl.example.com/');

    foreach ($successfulArticles as $article) {
        $item = $channel->addChild('item');
        $item->addChild('title', htmlspecialchars($article['page_title'] ?? $article['rss_title']));
        $item->addChild('link', htmlspecialchars($article['final_url']));
        $item->addChild('description', htmlspecialchars($article['og_description'] ?? ''));
        $item->addChild('pubDate', date('r', strtotime($article['pub_date'] ?? $article['created_at'])));

        if ($article['author']) {
            $item->addChild('author', htmlspecialchars($article['author']));
        }
    }

    return $rss->asXML();
}

// Usage
$rssFeed = generateRssFeed($articleRepo, 'Artificial Intelligence');
file_put_contents('feeds/ai.xml', $rssFeed);
```

---

**Best Practices**:
1. Always use prepared statements (already done by repositories)
2. Handle `PDOException` for constraint violations
3. Use transactions for multi-step operations
4. Implement retry logic for failed operations
5. Clean up old data periodically
6. Track API key usage for monitoring
7. Validate input before passing to repositories
8. Log database errors for debugging
