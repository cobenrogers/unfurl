<?php

declare(strict_types=1);

namespace Unfurl\Tests\Integration\Repositories;

use PHPUnit\Framework\TestCase;
use Unfurl\Core\Database;
use Unfurl\Core\TimezoneHelper;
use Unfurl\Repositories\FeedRepository;
use Unfurl\Repositories\ArticleRepository;
use PDO;

class FeedRepositoryTest extends TestCase
{
    private Database $db;
    private FeedRepository $repository;
    private ArticleRepository $articleRepository;
    private TimezoneHelper $timezone;

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
        $this->repository = new FeedRepository($this->db, $this->timezone);
        $this->articleRepository = new ArticleRepository($this->db, $this->timezone);

        // Create feeds and articles tables
        $this->createFeedsTable();
        $this->createArticlesTable();
    }

    private function createFeedsTable(): void
    {
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
    }

    private function createArticlesTable(): void
    {
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

    public function testCreateFeed(): void
    {
        $feedData = [
            'topic' => 'Technology',
            'url' => 'https://news.google.com/rss/search?q=technology',
            'result_limit' => 10,
            'enabled' => 1,
        ];

        $feedId = $this->repository->create($feedData);

        $this->assertIsInt($feedId);
        $this->assertGreaterThan(0, $feedId);

        // Verify feed was created
        $feed = $this->repository->findById($feedId);
        $this->assertEquals('Technology', $feed['topic']);
        $this->assertEquals('https://news.google.com/rss/search?q=technology', $feed['url']);
        $this->assertEquals(10, $feed['result_limit']);
        $this->assertEquals(1, $feed['enabled']);
    }

    public function testFindById(): void
    {
        // Create test feed
        $feedId = $this->repository->create([
            'topic' => 'Sports',
            'url' => 'https://news.google.com/rss/search?q=sports',
        ]);

        $feed = $this->repository->findById($feedId);

        $this->assertIsArray($feed);
        $this->assertEquals('Sports', $feed['topic']);
        $this->assertEquals('https://news.google.com/rss/search?q=sports', $feed['url']);
    }

    public function testFindByIdReturnsNullForNonExistent(): void
    {
        $feed = $this->repository->findById(99999);

        $this->assertNull($feed);
    }

    public function testFindByTopic(): void
    {
        $this->repository->create([
            'topic' => 'Business',
            'url' => 'https://news.google.com/rss/search?q=business',
        ]);

        $feed = $this->repository->findByTopic('Business');

        $this->assertIsArray($feed);
        $this->assertEquals('Business', $feed['topic']);
    }

    public function testFindByTopicReturnsNullForNonExistent(): void
    {
        $feed = $this->repository->findByTopic('NonExistent');

        $this->assertNull($feed);
    }

    public function testFindAll(): void
    {
        $this->repository->create([
            'topic' => 'Health',
            'url' => 'https://news.google.com/rss/search?q=health',
        ]);

        $this->repository->create([
            'topic' => 'Science',
            'url' => 'https://news.google.com/rss/search?q=science',
        ]);

        $feeds = $this->repository->findAll();

        $this->assertIsArray($feeds);
        $this->assertCount(2, $feeds);
    }

    public function testFindAllReturnsEmptyArrayWhenNoFeeds(): void
    {
        $feeds = $this->repository->findAll();

        $this->assertIsArray($feeds);
        $this->assertCount(0, $feeds);
    }

    public function testFindEnabled(): void
    {
        $this->repository->create([
            'topic' => 'Enabled Feed',
            'url' => 'https://example.com/enabled',
            'enabled' => 1,
        ]);

        $this->repository->create([
            'topic' => 'Disabled Feed',
            'url' => 'https://example.com/disabled',
            'enabled' => 0,
        ]);

        $enabledFeeds = $this->repository->findEnabled();

        $this->assertCount(1, $enabledFeeds);
        $this->assertEquals('Enabled Feed', $enabledFeeds[0]['topic']);
    }

    public function testUpdate(): void
    {
        $feedId = $this->repository->create([
            'topic' => 'Original Topic',
            'url' => 'https://example.com/original',
            'result_limit' => 10,
        ]);

        $updated = $this->repository->update($feedId, [
            'topic' => 'Updated Topic',
            'result_limit' => 20,
        ]);

        $this->assertTrue($updated);

        $feed = $this->repository->findById($feedId);
        $this->assertEquals('Updated Topic', $feed['topic']);
        $this->assertEquals(20, $feed['result_limit']);
    }

    public function testUpdateReturnsFalseForNonExistent(): void
    {
        $updated = $this->repository->update(99999, [
            'topic' => 'Updated Topic',
        ]);

        $this->assertFalse($updated);
    }

    public function testDelete(): void
    {
        $feedId = $this->repository->create([
            'topic' => 'To Delete',
            'url' => 'https://example.com/delete',
        ]);

        $deleted = $this->repository->delete($feedId);

        $this->assertTrue($deleted);

        $feed = $this->repository->findById($feedId);
        $this->assertNull($feed);
    }

    public function testDeleteReturnsFalseForNonExistent(): void
    {
        $deleted = $this->repository->delete(99999);

        $this->assertFalse($deleted);
    }

    public function testDeleteCascadesArticles(): void
    {
        // Create a feed
        $feedId = $this->repository->create([
            'topic' => 'Feed With Articles',
            'url' => 'https://example.com/feed-with-articles',
        ]);

        // Create articles for this feed
        $articleId1 = $this->articleRepository->create([
            'feed_id' => $feedId,
            'topic' => 'Feed With Articles',
            'google_news_url' => 'https://news.google.com/articles/cascade1',
            'rss_title' => 'Article 1',
        ]);

        $articleId2 = $this->articleRepository->create([
            'feed_id' => $feedId,
            'topic' => 'Feed With Articles',
            'google_news_url' => 'https://news.google.com/articles/cascade2',
            'rss_title' => 'Article 2',
        ]);

        // Verify articles exist
        $this->assertNotNull($this->articleRepository->findById($articleId1));
        $this->assertNotNull($this->articleRepository->findById($articleId2));

        // Delete the feed
        $deleted = $this->repository->delete($feedId);
        $this->assertTrue($deleted);

        // Verify feed is deleted
        $this->assertNull($this->repository->findById($feedId));

        // Verify articles are also deleted (cascade)
        $this->assertNull($this->articleRepository->findById($articleId1));
        $this->assertNull($this->articleRepository->findById($articleId2));
    }

    public function testUpdateLastProcessedAt(): void
    {
        $feedId = $this->repository->create([
            'topic' => 'Process Test',
            'url' => 'https://example.com/process',
        ]);

        sleep(1); // Ensure timestamp differs

        $updated = $this->repository->updateLastProcessedAt($feedId);

        $this->assertTrue($updated);

        $feed = $this->repository->findById($feedId);
        $this->assertNotNull($feed['last_processed_at']);
    }

    public function testUniqueTopicConstraint(): void
    {
        $this->repository->create([
            'topic' => 'Duplicate',
            'url' => 'https://example.com/first',
        ]);

        // SQLite uses PDOException with SQLSTATE 23000 for unique constraint
        $this->expectException(\PDOException::class);

        $this->repository->create([
            'topic' => 'Duplicate',
            'url' => 'https://example.com/second',
        ]);
    }

    public function testCreateUsesDefaultValues(): void
    {
        $feedId = $this->repository->create([
            'topic' => 'Minimal Feed',
            'url' => 'https://example.com/minimal',
        ]);

        $feed = $this->repository->findById($feedId);

        $this->assertEquals(10, $feed['result_limit']); // Default value
        $this->assertEquals(1, $feed['enabled']); // Default value
    }

    public function testFindEnabledReturnsEmptyArrayWhenNoneEnabled(): void
    {
        $this->repository->create([
            'topic' => 'Disabled 1',
            'url' => 'https://example.com/disabled1',
            'enabled' => 0,
        ]);

        $this->repository->create([
            'topic' => 'Disabled 2',
            'url' => 'https://example.com/disabled2',
            'enabled' => 0,
        ]);

        $enabledFeeds = $this->repository->findEnabled();

        $this->assertIsArray($enabledFeeds);
        $this->assertCount(0, $enabledFeeds);
    }
}
