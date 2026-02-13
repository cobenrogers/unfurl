<?php

declare(strict_types=1);

namespace Unfurl\Tests\Integration\Repositories;

use PHPUnit\Framework\TestCase;
use Unfurl\Core\Database;
use Unfurl\Core\TimezoneHelper;
use Unfurl\Repositories\ArticleRepository;
use Unfurl\Repositories\FeedRepository;
use PDO;

class ArticleRepositoryTest extends TestCase
{
    private Database $db;
    private ArticleRepository $repository;
    private FeedRepository $feedRepository;
    private TimezoneHelper $timezone;
    private int $testFeedId;

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
        $this->repository = new ArticleRepository($this->db, $this->timezone);
        $this->feedRepository = new FeedRepository($this->db, $this->timezone);

        $this->createTables();

        // Create a test feed
        $this->testFeedId = $this->feedRepository->create([
            'topic' => 'Test Topic',
            'url' => 'https://example.com/test',
        ]);
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
    }

    public function testCreateArticle(): void
    {
        $articleData = [
            'feed_id' => $this->testFeedId,
            'topic' => 'Test Topic',
            'google_news_url' => 'https://news.google.com/articles/123',
            'rss_title' => 'Test Article',
            'rss_description' => 'Test description',
            'rss_source' => 'Test Source',
        ];

        $articleId = $this->repository->create($articleData);

        $this->assertIsInt($articleId);
        $this->assertGreaterThan(0, $articleId);

        $article = $this->repository->findById($articleId);
        $this->assertEquals('Test Article', $article['rss_title']);
        $this->assertEquals('pending', $article['status']);
    }

    public function testFindById(): void
    {
        $articleId = $this->repository->create([
            'feed_id' => $this->testFeedId,
            'topic' => 'Test',
            'google_news_url' => 'https://news.google.com/articles/456',
            'rss_title' => 'Find By ID Test',
        ]);

        $article = $this->repository->findById($articleId);

        $this->assertIsArray($article);
        $this->assertEquals('Find By ID Test', $article['rss_title']);
    }

    public function testFindByIdReturnsNullForNonExistent(): void
    {
        $article = $this->repository->findById(99999);

        $this->assertNull($article);
    }

    public function testFindByFeedId(): void
    {
        $this->repository->create([
            'feed_id' => $this->testFeedId,
            'topic' => 'Test',
            'google_news_url' => 'https://news.google.com/articles/1',
            'rss_title' => 'Article 1',
        ]);

        $this->repository->create([
            'feed_id' => $this->testFeedId,
            'topic' => 'Test',
            'google_news_url' => 'https://news.google.com/articles/2',
            'rss_title' => 'Article 2',
        ]);

        $articles = $this->repository->findByFeedId($this->testFeedId);

        $this->assertCount(2, $articles);
    }

    public function testFindByStatus(): void
    {
        $this->repository->create([
            'feed_id' => $this->testFeedId,
            'topic' => 'Test',
            'google_news_url' => 'https://news.google.com/articles/p1',
            'rss_title' => 'Pending 1',
            'status' => 'pending',
        ]);

        $this->repository->create([
            'feed_id' => $this->testFeedId,
            'topic' => 'Test',
            'google_news_url' => 'https://news.google.com/articles/s1',
            'rss_title' => 'Success 1',
            'status' => 'success',
        ]);

        $pendingArticles = $this->repository->findByStatus('pending');
        $successArticles = $this->repository->findByStatus('success');

        $this->assertCount(1, $pendingArticles);
        $this->assertCount(1, $successArticles);
        $this->assertEquals('Pending 1', $pendingArticles[0]['rss_title']);
        $this->assertEquals('Success 1', $successArticles[0]['rss_title']);
    }

    public function testUpdate(): void
    {
        $articleId = $this->repository->create([
            'feed_id' => $this->testFeedId,
            'topic' => 'Test',
            'google_news_url' => 'https://news.google.com/articles/update',
            'status' => 'pending',
        ]);

        $updated = $this->repository->update($articleId, [
            'status' => 'success',
            'final_url' => 'https://example.com/article',
            'page_title' => 'Resolved Article',
        ]);

        $this->assertTrue($updated);

        $article = $this->repository->findById($articleId);
        $this->assertEquals('success', $article['status']);
        $this->assertEquals('https://example.com/article', $article['final_url']);
        $this->assertEquals('Resolved Article', $article['page_title']);
    }

    public function testDelete(): void
    {
        $articleId = $this->repository->create([
            'feed_id' => $this->testFeedId,
            'topic' => 'Test',
            'google_news_url' => 'https://news.google.com/articles/delete',
        ]);

        $deleted = $this->repository->delete($articleId);

        $this->assertTrue($deleted);
        $this->assertNull($this->repository->findById($articleId));
    }

    public function testUniqueFinalUrlConstraint(): void
    {
        $this->repository->create([
            'feed_id' => $this->testFeedId,
            'topic' => 'Test',
            'google_news_url' => 'https://news.google.com/articles/first',
            'final_url' => 'https://example.com/unique',
        ]);

        // SQLite uses PDOException with SQLSTATE 23000 for unique constraint
        $this->expectException(\PDOException::class);

        $this->repository->create([
            'feed_id' => $this->testFeedId,
            'topic' => 'Test',
            'google_news_url' => 'https://news.google.com/articles/second',
            'final_url' => 'https://example.com/unique',
        ]);
    }

    public function testIncrementRetryCount(): void
    {
        $articleId = $this->repository->create([
            'feed_id' => $this->testFeedId,
            'topic' => 'Test',
            'google_news_url' => 'https://news.google.com/articles/retry',
            'retry_count' => 0,
        ]);

        $this->repository->incrementRetryCount($articleId);

        $article = $this->repository->findById($articleId);
        $this->assertEquals(1, $article['retry_count']);

        $this->repository->incrementRetryCount($articleId);

        $article = $this->repository->findById($articleId);
        $this->assertEquals(2, $article['retry_count']);
    }

    public function testFindPendingRetries(): void
    {
        // Create article with next_retry_at in the past
        $this->repository->create([
            'feed_id' => $this->testFeedId,
            'topic' => 'Test',
            'google_news_url' => 'https://news.google.com/articles/retry1',
            'status' => 'failed',
            'retry_count' => 1,
            'next_retry_at' => date('Y-m-d H:i:s', strtotime('-1 hour')),
        ]);

        // Create article with next_retry_at in the future
        $this->repository->create([
            'feed_id' => $this->testFeedId,
            'topic' => 'Test',
            'google_news_url' => 'https://news.google.com/articles/retry2',
            'status' => 'failed',
            'retry_count' => 1,
            'next_retry_at' => date('Y-m-d H:i:s', strtotime('+1 hour')),
        ]);

        $articles = $this->repository->findPendingRetries();

        $this->assertCount(1, $articles);
    }

    public function testFindByTopic(): void
    {
        $this->repository->create([
            'feed_id' => $this->testFeedId,
            'topic' => 'Technology',
            'google_news_url' => 'https://news.google.com/articles/tech1',
        ]);

        $this->repository->create([
            'feed_id' => $this->testFeedId,
            'topic' => 'Sports',
            'google_news_url' => 'https://news.google.com/articles/sports1',
        ]);

        $techArticles = $this->repository->findByTopic('Technology');

        $this->assertCount(1, $techArticles);
        $this->assertEquals('Technology', $techArticles[0]['topic']);
    }

    public function testDeleteOlderThan(): void
    {
        // Create old article
        $this->repository->create([
            'feed_id' => $this->testFeedId,
            'topic' => 'Test',
            'google_news_url' => 'https://news.google.com/articles/old',
            'created_at' => date('Y-m-d H:i:s', strtotime('-100 days')),
        ]);

        // Create recent article
        $this->repository->create([
            'feed_id' => $this->testFeedId,
            'topic' => 'Test',
            'google_news_url' => 'https://news.google.com/articles/new',
        ]);

        $deletedCount = $this->repository->deleteOlderThan(90);

        $this->assertEquals(1, $deletedCount);
    }

    public function testCountByStatus(): void
    {
        $this->repository->create([
            'feed_id' => $this->testFeedId,
            'topic' => 'Test',
            'google_news_url' => 'https://news.google.com/articles/p1',
            'status' => 'pending',
        ]);

        $this->repository->create([
            'feed_id' => $this->testFeedId,
            'topic' => 'Test',
            'google_news_url' => 'https://news.google.com/articles/p2',
            'status' => 'pending',
        ]);

        $this->repository->create([
            'feed_id' => $this->testFeedId,
            'topic' => 'Test',
            'google_news_url' => 'https://news.google.com/articles/s1',
            'status' => 'success',
        ]);

        $pendingCount = $this->repository->countByStatus('pending');
        $successCount = $this->repository->countByStatus('success');

        $this->assertEquals(2, $pendingCount);
        $this->assertEquals(1, $successCount);
    }

    public function testMarkAsProcessed(): void
    {
        $articleId = $this->repository->create([
            'feed_id' => $this->testFeedId,
            'topic' => 'Test',
            'google_news_url' => 'https://news.google.com/articles/process',
            'status' => 'pending',
        ]);

        $this->repository->markAsProcessed($articleId);

        $article = $this->repository->findById($articleId);
        $this->assertEquals('success', $article['status']);
        $this->assertNotNull($article['processed_at']);
    }

    public function testFindWithFiltersNoFilters(): void
    {
        $this->repository->create([
            'feed_id' => $this->testFeedId,
            'topic' => 'Test',
            'google_news_url' => 'https://news.google.com/articles/1',
            'rss_title' => 'Article 1',
            'pub_date' => date('Y-m-d H:i:s'),
        ]);

        $this->repository->create([
            'feed_id' => $this->testFeedId,
            'topic' => 'Test',
            'google_news_url' => 'https://news.google.com/articles/2',
            'rss_title' => 'Article 2',
            'pub_date' => date('Y-m-d H:i:s'),
        ]);

        $articles = $this->repository->findWithFilters([], 10, 0);

        $this->assertCount(2, $articles);
    }

    public function testFindWithFiltersByTopic(): void
    {
        $this->repository->create([
            'feed_id' => $this->testFeedId,
            'topic' => 'AI',
            'google_news_url' => 'https://news.google.com/articles/ai1',
            'pub_date' => date('Y-m-d H:i:s'),
        ]);

        $this->repository->create([
            'feed_id' => $this->testFeedId,
            'topic' => 'Sports',
            'google_news_url' => 'https://news.google.com/articles/sports1',
            'pub_date' => date('Y-m-d H:i:s'),
        ]);

        $articles = $this->repository->findWithFilters(['topic' => 'AI'], 10, 0);

        $this->assertCount(1, $articles);
        $this->assertEquals('AI', $articles[0]['topic']);
    }

    public function testFindWithFiltersByStatus(): void
    {
        $this->repository->create([
            'feed_id' => $this->testFeedId,
            'topic' => 'Test',
            'google_news_url' => 'https://news.google.com/articles/pending1',
            'status' => 'pending',
            'pub_date' => date('Y-m-d H:i:s'),
        ]);

        $this->repository->create([
            'feed_id' => $this->testFeedId,
            'topic' => 'Test',
            'google_news_url' => 'https://news.google.com/articles/success1',
            'status' => 'success',
            'pub_date' => date('Y-m-d H:i:s'),
        ]);

        $articles = $this->repository->findWithFilters(['status' => 'failed'], 10, 0);

        $this->assertCount(0, $articles);

        $articles = $this->repository->findWithFilters(['status' => 'pending'], 10, 0);

        $this->assertCount(1, $articles);
        $this->assertEquals('pending', $articles[0]['status']);
    }

    public function testFindWithFiltersByFeedId(): void
    {
        // Create a second test feed
        $secondFeedId = $this->feedRepository->create([
            'topic' => 'Another Topic',
            'url' => 'https://example.com/another',
        ]);

        // Create articles for both feeds
        $this->repository->create([
            'feed_id' => $this->testFeedId,
            'topic' => 'Test Topic',
            'google_news_url' => 'https://news.google.com/articles/feed1-1',
            'status' => 'success',
            'pub_date' => date('Y-m-d H:i:s'),
        ]);

        $this->repository->create([
            'feed_id' => $this->testFeedId,
            'topic' => 'Test Topic',
            'google_news_url' => 'https://news.google.com/articles/feed1-2',
            'status' => 'success',
            'pub_date' => date('Y-m-d H:i:s'),
        ]);

        $this->repository->create([
            'feed_id' => $secondFeedId,
            'topic' => 'Another Topic',
            'google_news_url' => 'https://news.google.com/articles/feed2-1',
            'status' => 'success',
            'pub_date' => date('Y-m-d H:i:s'),
        ]);

        // Test filtering by first feed
        $articles = $this->repository->findWithFilters(['feed_id' => $this->testFeedId], 10, 0);
        $this->assertCount(2, $articles);
        $this->assertEquals($this->testFeedId, $articles[0]['feed_id']);
        $this->assertEquals($this->testFeedId, $articles[1]['feed_id']);

        // Test filtering by second feed
        $articles = $this->repository->findWithFilters(['feed_id' => $secondFeedId], 10, 0);
        $this->assertCount(1, $articles);
        $this->assertEquals($secondFeedId, $articles[0]['feed_id']);

        // Test no filter returns all
        $articles = $this->repository->findWithFilters([], 10, 0);
        $this->assertCount(3, $articles);
    }

    public function testFindWithFiltersByDateRange(): void
    {
        $this->repository->create([
            'feed_id' => $this->testFeedId,
            'topic' => 'Test',
            'google_news_url' => 'https://news.google.com/articles/old',
            'pub_date' => '2026-01-01 10:00:00',
        ]);

        $this->repository->create([
            'feed_id' => $this->testFeedId,
            'topic' => 'Test',
            'google_news_url' => 'https://news.google.com/articles/new',
            'pub_date' => '2026-02-01 10:00:00',
        ]);

        $articles = $this->repository->findWithFilters([
            'date_from' => '2026-01-15',
            'date_to' => '2026-02-15',
        ], 10, 0);

        $this->assertCount(1, $articles);
    }

    public function testFindWithFiltersPagination(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->repository->create([
                'feed_id' => $this->testFeedId,
                'topic' => 'Test',
                'google_news_url' => "https://news.google.com/articles/page{$i}",
                'pub_date' => date('Y-m-d H:i:s'),
            ]);
        }

        // First page
        $articles = $this->repository->findWithFilters([], 2, 0);
        $this->assertCount(2, $articles);

        // Second page
        $articles = $this->repository->findWithFilters([], 2, 2);
        $this->assertCount(2, $articles);

        // Third page
        $articles = $this->repository->findWithFilters([], 2, 4);
        $this->assertCount(1, $articles);
    }

    public function testFindWithFiltersMultipleFilters(): void
    {
        $this->repository->create([
            'feed_id' => $this->testFeedId,
            'topic' => 'AI',
            'google_news_url' => 'https://news.google.com/articles/ai-pending',
            'status' => 'pending',
            'pub_date' => '2026-02-01 10:00:00',
        ]);

        $this->repository->create([
            'feed_id' => $this->testFeedId,
            'topic' => 'AI',
            'google_news_url' => 'https://news.google.com/articles/ai-success',
            'status' => 'success',
            'pub_date' => '2026-02-01 10:00:00',
        ]);

        $this->repository->create([
            'feed_id' => $this->testFeedId,
            'topic' => 'Sports',
            'google_news_url' => 'https://news.google.com/articles/sports-pending',
            'status' => 'pending',
            'pub_date' => '2026-02-01 10:00:00',
        ]);

        $articles = $this->repository->findWithFilters([
            'topic' => 'AI',
            'status' => 'pending',
        ], 10, 0);

        $this->assertCount(1, $articles);
        $this->assertEquals('AI', $articles[0]['topic']);
        $this->assertEquals('pending', $articles[0]['status']);
    }

    public function testCountWithFiltersNoFilters(): void
    {
        $this->repository->create([
            'feed_id' => $this->testFeedId,
            'topic' => 'Test',
            'google_news_url' => 'https://news.google.com/articles/1',
        ]);

        $this->repository->create([
            'feed_id' => $this->testFeedId,
            'topic' => 'Test',
            'google_news_url' => 'https://news.google.com/articles/2',
        ]);

        $count = $this->repository->countWithFilters([]);

        $this->assertEquals(2, $count);
    }

    public function testCountWithFiltersByTopic(): void
    {
        $this->repository->create([
            'feed_id' => $this->testFeedId,
            'topic' => 'AI',
            'google_news_url' => 'https://news.google.com/articles/ai1',
        ]);

        $this->repository->create([
            'feed_id' => $this->testFeedId,
            'topic' => 'Sports',
            'google_news_url' => 'https://news.google.com/articles/sports1',
        ]);

        $count = $this->repository->countWithFilters(['topic' => 'AI']);

        $this->assertEquals(1, $count);
    }

    public function testCountWithFiltersByStatus(): void
    {
        $this->repository->create([
            'feed_id' => $this->testFeedId,
            'topic' => 'Test',
            'google_news_url' => 'https://news.google.com/articles/p1',
            'status' => 'pending',
        ]);

        $this->repository->create([
            'feed_id' => $this->testFeedId,
            'topic' => 'Test',
            'google_news_url' => 'https://news.google.com/articles/s1',
            'status' => 'success',
        ]);

        $count = $this->repository->countWithFilters(['status' => 'pending']);

        $this->assertEquals(1, $count);
    }

    public function testCountWithFiltersByFeedId(): void
    {
        // Create a second test feed
        $secondFeedId = $this->feedRepository->create([
            'topic' => 'Second Feed Topic',
            'url' => 'https://example.com/second',
        ]);

        $this->repository->create([
            'feed_id' => $this->testFeedId,
            'topic' => 'Test',
            'google_news_url' => 'https://news.google.com/articles/f1-1',
        ]);

        $this->repository->create([
            'feed_id' => $this->testFeedId,
            'topic' => 'Test',
            'google_news_url' => 'https://news.google.com/articles/f1-2',
        ]);

        $this->repository->create([
            'feed_id' => $secondFeedId,
            'topic' => 'Test',
            'google_news_url' => 'https://news.google.com/articles/f2-1',
        ]);

        $count = $this->repository->countWithFilters(['feed_id' => $this->testFeedId]);
        $this->assertEquals(2, $count);

        $count = $this->repository->countWithFilters(['feed_id' => $secondFeedId]);
        $this->assertEquals(1, $count);
    }

    public function testCountWithFiltersMultipleFilters(): void
    {
        $this->repository->create([
            'feed_id' => $this->testFeedId,
            'topic' => 'AI',
            'google_news_url' => 'https://news.google.com/articles/ai-pending',
            'status' => 'pending',
        ]);

        $this->repository->create([
            'feed_id' => $this->testFeedId,
            'topic' => 'AI',
            'google_news_url' => 'https://news.google.com/articles/ai-success',
            'status' => 'success',
        ]);

        $count = $this->repository->countWithFilters([
            'topic' => 'AI',
            'status' => 'pending',
        ]);

        $this->assertEquals(1, $count);
    }
}
