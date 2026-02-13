# Repository API Quick Reference

Fast lookup guide for all repository methods.

## Database Class

```php
use Unfurl\Core\Database;

$db = new Database($config);
```

| Method | Parameters | Returns | Description |
|--------|------------|---------|-------------|
| `getConnection()` | - | `PDO` | Get PDO instance |
| `execute($sql, $params)` | `string`, `array` | `bool` | Execute INSERT/UPDATE/DELETE |
| `query($sql, $params)` | `string`, `array` | `array` | SELECT multiple rows |
| `querySingle($sql, $params)` | `string`, `array` | `array\|null` | SELECT single row |
| `getLastInsertId()` | - | `int` | Get last inserted ID |
| `beginTransaction()` | - | `bool` | Start transaction |
| `commit()` | - | `bool` | Commit transaction |
| `rollback()` | - | `bool` | Rollback transaction |

## FeedRepository

```php
use Unfurl\Repositories\FeedRepository;

$feedRepo = new FeedRepository($db);
```

| Method | Parameters | Returns | Description |
|--------|------------|---------|-------------|
| `create($data)` | `array` | `int` | Create feed, return ID |
| `findById($id)` | `int` | `array\|null` | Find by ID |
| `findByTopic($topic)` | `string` | `array\|null` | Find by topic |
| `findAll()` | - | `array` | Get all feeds |
| `findEnabled()` | - | `array` | Get enabled feeds |
| `update($id, $data)` | `int`, `array` | `bool` | Update feed |
| `delete($id)` | `int` | `bool` | Delete feed |
| `updateLastProcessedAt($id)` | `int` | `bool` | Update timestamp |

### Feed Data Structure

```php
[
    'topic' => 'Technology',              // REQUIRED, UNIQUE
    'url' => 'https://...',               // REQUIRED
    'result_limit' => 10,                 // Optional, default: 10
    'enabled' => 1,                       // Optional, default: 1
]
```

## ArticleRepository

```php
use Unfurl\Repositories\ArticleRepository;

$articleRepo = new ArticleRepository($db);
```

| Method | Parameters | Returns | Description |
|--------|------------|---------|-------------|
| `create($data)` | `array` | `int` | Create article, return ID |
| `findById($id)` | `int` | `array\|null` | Find by ID |
| `findByFeedId($feedId)` | `int` | `array` | Find by feed ID |
| `findByStatus($status)` | `string` | `array` | Find by status |
| `findByTopic($topic)` | `string` | `array` | Find by topic |
| `findPendingRetries()` | - | `array` | Find articles ready for retry |
| `update($id, $data)` | `int`, `array` | `bool` | Update article |
| `delete($id)` | `int` | `bool` | Delete article |
| `deleteOlderThan($days)` | `int` | `int` | Delete old articles, return count |
| `countByStatus($status)` | `string` | `int` | Count articles by status |
| `incrementRetryCount($id)` | `int` | `bool` | Increment retry counter |
| `markAsProcessed($id)` | `int` | `bool` | Mark as success + timestamp |

### Article Data Structure

**Minimal** (required fields):
```php
[
    'feed_id' => 1,                       // REQUIRED, foreign key
    'topic' => 'Technology',              // REQUIRED
    'google_news_url' => 'https://...',   // REQUIRED
]
```

**Full** (all available fields):
```php
[
    // Required
    'feed_id' => 1,
    'topic' => 'Technology',
    'google_news_url' => 'https://news.google.com/articles/...',

    // RSS data
    'rss_title' => 'Article Title',
    'pub_date' => '2026-02-07 10:30:00',
    'rss_description' => 'Article description',
    'rss_source' => 'TechNews',

    // Resolved data
    'final_url' => 'https://example.com/article',  // UNIQUE
    'status' => 'pending',  // pending, success, failed

    // Metadata
    'page_title' => 'Full Page Title',
    'og_title' => 'Open Graph Title',
    'og_description' => 'OG Description',
    'og_image' => 'https://example.com/image.jpg',
    'og_url' => 'https://example.com/article',
    'og_site_name' => 'Example Site',
    'twitter_image' => 'https://example.com/twitter.jpg',
    'twitter_card' => 'summary_large_image',
    'author' => 'John Doe',

    // Content
    'article_content' => 'Full article text...',
    'word_count' => 1500,
    'categories' => '["tech","ai"]',  // JSON string

    // Error handling
    'error_message' => 'Error description',
    'retry_count' => 0,
    'next_retry_at' => '2026-02-07 11:30:00',
    'last_error' => 'Last error message',

    // Timestamps
    'processed_at' => '2026-02-07 10:35:00',
    'created_at' => '2026-02-07 10:30:00',
]
```

### Article Status Values

| Status | Description |
|--------|-------------|
| `pending` | Not yet processed |
| `success` | Successfully processed |
| `failed` | Processing failed, may retry |

## ApiKeyRepository

```php
use Unfurl\Repositories\ApiKeyRepository;

$apiKeyRepo = new ApiKeyRepository($db);
```

| Method | Parameters | Returns | Description |
|--------|------------|---------|-------------|
| `create($data)` | `array` | `int` | Create API key, return ID |
| `findById($id)` | `int` | `array\|null` | Find by ID |
| `findByKeyValue($keyValue)` | `string` | `array\|null` | Find by key value |
| `findAll()` | - | `array` | Get all API keys |
| `findEnabled()` | - | `array` | Get enabled API keys |
| `update($id, $data)` | `int`, `array` | `bool` | Update API key |
| `delete($id)` | `int` | `bool` | Delete API key |
| `updateLastUsedAt($id)` | `int` | `bool` | Update usage timestamp |
| `validateApiKey($keyValue)` | `string` | `bool` | Validate + auto-track usage |

### API Key Data Structure

```php
[
    'key_name' => 'Mobile App',           // REQUIRED
    'key_value' => 'abc123xyz789...',     // REQUIRED, UNIQUE, 64 chars
    'description' => 'Production app',    // Optional
    'enabled' => 1,                       // Optional, default: 1
]
```

## Common Patterns

### Create Feed + Articles

```php
// Create feed
$feedId = $feedRepo->create([
    'topic' => 'AI',
    'url' => 'https://news.google.com/rss/search?q=ai',
]);

// Create articles
foreach ($rssItems as $item) {
    $articleRepo->create([
        'feed_id' => $feedId,
        'topic' => 'AI',
        'google_news_url' => $item['link'],
        'rss_title' => $item['title'],
    ]);
}
```

### Process Articles

```php
// Get pending
$articles = $articleRepo->findByStatus('pending');

foreach ($articles as $article) {
    try {
        // Process...
        $articleRepo->update($article['id'], [
            'status' => 'success',
            'final_url' => $resolvedUrl,
        ]);
        $articleRepo->markAsProcessed($article['id']);
    } catch (Exception $e) {
        $articleRepo->update($article['id'], [
            'status' => 'failed',
            'error_message' => $e->getMessage(),
        ]);
        $articleRepo->incrementRetryCount($article['id']);
    }
}
```

### Authenticate with API Key

```php
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';

if (!$apiKeyRepo->validateApiKey($apiKey)) {
    http_response_code(401);
    die('Invalid API key');
}

// Proceed with authenticated request...
```

### Transaction Pattern

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

## Error Handling

### Duplicate Constraint Violations

```php
try {
    $feedId = $feedRepo->create(['topic' => 'Duplicate']);
} catch (PDOException $e) {
    if ($e->getCode() == 23000) {
        // Handle duplicate topic
        echo "Topic already exists";
    }
}
```

### Null vs Empty Array

| Method | Not Found Returns |
|--------|------------------|
| `findById()` | `null` |
| `findByTopic()` | `null` |
| `findByKeyValue()` | `null` |
| `querySingle()` | `null` |
| `findAll()` | `[]` (empty array) |
| `findEnabled()` | `[]` (empty array) |
| `findByStatus()` | `[]` (empty array) |
| `findByFeedId()` | `[]` (empty array) |

## Performance Tips

### Use Specific Queries

```php
// ✅ GOOD - Specific query
$article = $articleRepo->findById($id);

// ❌ BAD - Get all then filter in PHP
$allArticles = $articleRepo->findAll();
$article = array_filter($allArticles, fn($a) => $a['id'] == $id)[0];
```

### Batch Operations with Transactions

```php
// ✅ GOOD - Single transaction
$db->beginTransaction();
foreach ($articles as $article) {
    $articleRepo->create($article);
}
$db->commit();

// ❌ BAD - Individual commits (slower)
foreach ($articles as $article) {
    $articleRepo->create($article); // Each creates new transaction
}
```

### Use Count Methods

```php
// ✅ GOOD - Database count
$count = $articleRepo->countByStatus('pending');

// ❌ BAD - Fetch all then count in PHP
$count = count($articleRepo->findByStatus('pending'));
```

## Testing

### Unit Tests
- Test Database class with SQLite `:memory:`
- Mock PDO for edge cases

### Integration Tests
- Test repositories with SQLite `:memory:`
- Verify constraints, defaults, relationships
- Test CRUD operations end-to-end

```php
// Test setup pattern
protected function setUp(): void
{
    $config = ['database' => [
        'host' => 'localhost',
        'name' => ':memory:',
        'user' => '',
        'pass' => '',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ],
    ]];

    $this->db = new Database($config);
    $this->repository = new FeedRepository($this->db);
    $this->createTables();
}
```

---

**Quick Links**:
- [Full Implementation Guide](TASK-2.1-DATABASE-LAYER.md)
- [Usage Examples](DATABASE-USAGE-EXAMPLES.md)
- [Database Schema](../sql/schema.sql)
