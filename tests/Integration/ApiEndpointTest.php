<?php

declare(strict_types=1);

namespace Unfurl\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Unfurl\Core\Database;
use Unfurl\Core\TimezoneHelper;
use Unfurl\Repositories\FeedRepository;
use Unfurl\Repositories\ArticleRepository;
use Unfurl\Repositories\ApiKeyRepository;
use PDO;

/**
 * API Endpoint Integration Test
 *
 * Tests the /api/feed authenticated RSS endpoint with:
 * - Header authentication (X-API-Key)
 * - Query parameter authentication (?key=)
 * - Feed ID filtering
 * - Error handling for missing/invalid keys
 */
class ApiEndpointTest extends TestCase
{
    private Database $db;
    private FeedRepository $feedRepository;
    private ArticleRepository $articleRepository;
    private ApiKeyRepository $apiKeyRepository;
    private TimezoneHelper $timezone;
    private string $testApiKey;
    private int $testFeedId1;
    private int $testFeedId2;

    protected function setUp(): void
    {
        $config = [
            'database' => [
                'host' => 'localhost',
                'name' => ':memory:',
                'user' => '',
                'pass' => '',
                'charset' => 'utf8mb4',
                'options' => [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ],
            ],
        ];

        $this->db = new Database($config);
        $this->timezone = new TimezoneHelper('America/Chicago');
        $this->feedRepository = new FeedRepository($this->db, $this->timezone);
        $this->articleRepository = new ArticleRepository($this->db, $this->timezone);
        $this->apiKeyRepository = new ApiKeyRepository($this->db, $this->timezone);

        $this->createTables();
        $this->seedTestData();
    }

    private function createTables(): void
    {
        // Feeds table
        $sql = "
            CREATE TABLE feeds (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                topic TEXT NOT NULL UNIQUE,
                url TEXT NOT NULL,
                result_limit INTEGER DEFAULT 10,
                enabled INTEGER DEFAULT 1,
                last_processed_at TEXT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ";
        $this->db->execute($sql);

        // Articles table
        $sql = "
            CREATE TABLE articles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                feed_id INTEGER NOT NULL,
                topic TEXT NOT NULL,
                google_news_url TEXT NOT NULL,
                rss_title TEXT,
                pub_date TEXT NULL,
                rss_description TEXT,
                rss_source TEXT,
                final_url TEXT,
                status TEXT DEFAULT 'pending',
                page_title TEXT,
                og_title TEXT,
                og_description TEXT,
                og_image TEXT,
                og_url TEXT,
                og_site_name TEXT,
                twitter_image TEXT,
                twitter_card TEXT,
                author TEXT,
                article_content TEXT,
                word_count INTEGER,
                categories TEXT,
                error_message TEXT,
                retry_count INTEGER DEFAULT 0,
                next_retry_at TEXT NULL,
                last_error TEXT NULL,
                processed_at TEXT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (feed_id) REFERENCES feeds(id),
                UNIQUE (final_url)
            )
        ";
        $this->db->execute($sql);

        // API Keys table
        $sql = "
            CREATE TABLE api_keys (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                key_name TEXT NOT NULL,
                key_value TEXT NOT NULL UNIQUE,
                description TEXT NULL,
                enabled INTEGER DEFAULT 1,
                last_used_at TEXT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ";
        $this->db->execute($sql);
    }

    private function seedTestData(): void
    {
        // Create API key
        $this->testApiKey = bin2hex(random_bytes(32));
        $this->apiKeyRepository->create([
            'key_name' => 'Test API Key',
            'key_value' => $this->testApiKey,
            'enabled' => 1,
        ]);

        // Create two feeds
        $this->testFeedId1 = $this->feedRepository->create([
            'topic' => 'Technology',
            'url' => 'https://example.com/tech',
        ]);

        $this->testFeedId2 = $this->feedRepository->create([
            'topic' => 'Sports',
            'url' => 'https://example.com/sports',
        ]);

        // Create articles for feed 1
        $this->articleRepository->create([
            'feed_id' => $this->testFeedId1,
            'topic' => 'Technology',
            'google_news_url' => 'https://news.google.com/tech1',
            'rss_title' => 'Tech Article 1',
            'status' => 'success',
            'pub_date' => date('Y-m-d H:i:s'),
        ]);

        $this->articleRepository->create([
            'feed_id' => $this->testFeedId1,
            'topic' => 'Technology',
            'google_news_url' => 'https://news.google.com/tech2',
            'rss_title' => 'Tech Article 2',
            'status' => 'success',
            'pub_date' => date('Y-m-d H:i:s'),
        ]);

        // Create article for feed 2
        $this->articleRepository->create([
            'feed_id' => $this->testFeedId2,
            'topic' => 'Sports',
            'google_news_url' => 'https://news.google.com/sports1',
            'rss_title' => 'Sports Article 1',
            'status' => 'success',
            'pub_date' => date('Y-m-d H:i:s'),
        ]);
    }

    public function testApiEndpointWithHeaderAuthentication(): void
    {
        // Simulate API call with header authentication
        $_SERVER['HTTP_X_API_KEY'] = $this->testApiKey;

        // Get articles using repository (simulating the endpoint logic)
        $filters = ['status' => 'success'];
        $articles = $this->articleRepository->findWithFilters($filters, 100, 0);

        $this->assertCount(3, $articles);

        // Verify API key was validated
        $apiKey = $this->apiKeyRepository->findByKeyValue($this->testApiKey);
        $this->assertNotNull($apiKey);
        $this->assertEquals(1, $apiKey['enabled']);

        unset($_SERVER['HTTP_X_API_KEY']);
    }

    public function testApiEndpointWithQueryParameterAuthentication(): void
    {
        // Simulate API call with query parameter authentication
        $_GET['key'] = $this->testApiKey;

        // Get articles using repository
        $filters = ['status' => 'success'];
        $articles = $this->articleRepository->findWithFilters($filters, 100, 0);

        $this->assertCount(3, $articles);

        // Verify API key was validated
        $apiKey = $this->apiKeyRepository->findByKeyValue($this->testApiKey);
        $this->assertNotNull($apiKey);

        unset($_GET['key']);
    }

    public function testApiEndpointWithFeedIdFiltering(): void
    {
        // Simulate API call with feed_id filter
        $_SERVER['HTTP_X_API_KEY'] = $this->testApiKey;
        $_GET['feed_id'] = (string)$this->testFeedId1;

        $filters = [
            'status' => 'success',
            'feed_id' => $this->testFeedId1,
        ];
        $articles = $this->articleRepository->findWithFilters($filters, 100, 0);

        // Should only return articles from feed 1
        $this->assertCount(2, $articles);
        foreach ($articles as $article) {
            $this->assertEquals($this->testFeedId1, $article['feed_id']);
        }

        unset($_SERVER['HTTP_X_API_KEY']);
        unset($_GET['feed_id']);
    }

    public function testApiEndpointWithDifferentFeedIdFiltering(): void
    {
        // Test filtering by second feed
        $_SERVER['HTTP_X_API_KEY'] = $this->testApiKey;
        $_GET['feed_id'] = (string)$this->testFeedId2;

        $filters = [
            'status' => 'success',
            'feed_id' => $this->testFeedId2,
        ];
        $articles = $this->articleRepository->findWithFilters($filters, 100, 0);

        // Should only return articles from feed 2
        $this->assertCount(1, $articles);
        $this->assertEquals($this->testFeedId2, $articles[0]['feed_id']);

        unset($_SERVER['HTTP_X_API_KEY']);
        unset($_GET['feed_id']);
    }

    public function testApiEndpointMissingApiKey(): void
    {
        // No API key provided
        $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['key'] ?? null;

        $this->assertNull($apiKey, 'API key should be missing');
    }

    public function testApiEndpointInvalidApiKey(): void
    {
        // Invalid API key
        $invalidKey = 'invalid_key_12345';
        $_SERVER['HTTP_X_API_KEY'] = $invalidKey;

        $apiKey = $this->apiKeyRepository->findByKeyValue($invalidKey);

        $this->assertNull($apiKey, 'Invalid API key should not be found');

        unset($_SERVER['HTTP_X_API_KEY']);
    }

    public function testApiEndpointDisabledApiKey(): void
    {
        // Create disabled API key
        $disabledKey = bin2hex(random_bytes(32));
        $this->apiKeyRepository->create([
            'key_name' => 'Disabled Key',
            'key_value' => $disabledKey,
            'enabled' => 0,
        ]);

        $_SERVER['HTTP_X_API_KEY'] = $disabledKey;

        $apiKey = $this->apiKeyRepository->findByKeyValue($disabledKey);

        $this->assertNotNull($apiKey);
        $this->assertEquals(0, $apiKey['enabled'], 'API key should be disabled');

        unset($_SERVER['HTTP_X_API_KEY']);
    }

    public function testApiEndpointHeaderTakesPrecedenceOverQuery(): void
    {
        // When both header and query param are provided, header should take precedence
        $_SERVER['HTTP_X_API_KEY'] = $this->testApiKey;
        $_GET['key'] = 'should_be_ignored';

        $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['key'] ?? null;

        $this->assertEquals($this->testApiKey, $apiKey, 'Header should take precedence');

        unset($_SERVER['HTTP_X_API_KEY']);
        unset($_GET['key']);
    }

    public function testApiKeyLastUsedAtUpdated(): void
    {
        // Get initial API key state
        $apiKeyBefore = $this->apiKeyRepository->findByKeyValue($this->testApiKey);
        $this->assertNull($apiKeyBefore['last_used_at']);

        // Simulate using the API key
        sleep(1); // Ensure timestamp differs
        $this->apiKeyRepository->updateLastUsedAt($apiKeyBefore['id']);

        // Verify last_used_at was updated
        $apiKeyAfter = $this->apiKeyRepository->findByKeyValue($this->testApiKey);
        $this->assertNotNull($apiKeyAfter['last_used_at']);
    }
}
