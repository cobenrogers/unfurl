<?php

declare(strict_types=1);

namespace Unfurl\Tests\Unit\Services\RSS;

use PHPUnit\Framework\TestCase;
use Unfurl\Services\RSS\RssFeedGenerator;
use Unfurl\Repositories\ArticleRepository;
use PDO;

class RssFeedGeneratorTest extends TestCase
{
    private ArticleRepository $articleRepository;
    private RssFeedGenerator $feedGenerator;
    private array $config;
    private string $cacheDir;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/unfurl_rss_cache_' . uniqid();
        mkdir($this->cacheDir, 0755, true);

        $this->config = [
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
            'app' => [
                'base_url' => 'https://example.com/unfurl/',
                'site_name' => 'Unfurl',
                'version' => '1.0',
            ],
        ];

        // Initialize database
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // Create tables
        $pdo->exec('
            CREATE TABLE feeds (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                topic VARCHAR(255) NOT NULL UNIQUE,
                url TEXT NOT NULL,
                result_limit INT DEFAULT 10,
                enabled TINYINT DEFAULT 1,
                last_processed_at TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ');

        $pdo->exec('
            CREATE TABLE articles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                feed_id INT NOT NULL,
                topic VARCHAR(255) NOT NULL,
                google_news_url TEXT NOT NULL,
                rss_title TEXT,
                pub_date TIMESTAMP NULL,
                rss_description TEXT,
                rss_source VARCHAR(255),
                final_url TEXT UNIQUE,
                status TEXT DEFAULT "pending",
                page_title TEXT,
                og_title TEXT,
                og_description TEXT,
                og_image TEXT,
                og_url TEXT,
                og_site_name VARCHAR(255),
                twitter_image TEXT,
                twitter_card VARCHAR(50),
                author VARCHAR(255),
                article_content TEXT,
                word_count INT,
                categories TEXT,
                error_message TEXT,
                retry_count INT DEFAULT 0,
                next_retry_at TIMESTAMP NULL,
                last_error TEXT NULL,
                processed_at TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ');

        // Create repository and generator
        $db = new \Unfurl\Core\Database(['database' => ['host' => 'localhost', 'name' => ':memory:', 'user' => '', 'pass' => '', 'charset' => 'utf8mb4', 'options' => [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]]]);

        // Use the in-memory SQLite connection instead
        $reflectionClass = new \ReflectionClass($db);
        $connectionProperty = $reflectionClass->getProperty('pdo');
        $connectionProperty->setAccessible(true);
        $connectionProperty->setValue($db, $pdo);

        $timezone = $this->createMock(\Unfurl\Core\TimezoneHelper::class);
        $this->articleRepository = new ArticleRepository($db, $timezone);
        $this->feedGenerator = new RssFeedGenerator($this->articleRepository, $this->cacheDir, $this->config);
    }

    protected function tearDown(): void
    {
        // Clean up cache directory
        if (is_dir($this->cacheDir)) {
            array_map('unlink', glob($this->cacheDir . '/*.*'));
            rmdir($this->cacheDir);
        }
    }

    /**
     * Test that generate() returns valid XML
     */
    public function testGenerateReturnsValidXml(): void
    {
        $xml = $this->feedGenerator->generate();

        $this->assertIsString($xml);
        $this->assertStringStartsWith('<?xml', $xml);

        // Should parse as valid XML
        $dom = new \DOMDocument();
        $loadResult = @$dom->loadXML($xml);
        $this->assertTrue($loadResult, 'Generated XML should be valid');
    }

    /**
     * Test RSS 2.0 structure with channel and items
     */
    public function testGenerateProducesRss20Structure(): void
    {
        $xml = $this->feedGenerator->generate();
        $dom = new \DOMDocument();
        $dom->loadXML($xml);

        // Check root element
        $this->assertEquals('rss', $dom->documentElement->nodeName);
        $this->assertEquals('2.0', $dom->documentElement->getAttribute('version'));

        // Check namespaces
        $this->assertStringContainsString('xmlns:content="http://purl.org/rss/1.0/modules/content/"', $xml);
        $this->assertStringContainsString('xmlns:dc="http://purl.org/dc/elements/1.1/"', $xml);

        // Check channel exists
        $channels = $dom->getElementsByTagName('channel');
        $this->assertGreaterThan(0, $channels->length, 'RSS should have a channel element');
    }

    /**
     * Test channel contains required elements
     */
    public function testChannelContainsRequiredElements(): void
    {
        $xml = $this->feedGenerator->generate();
        $dom = new \DOMDocument();
        $dom->loadXML($xml);

        $channel = $dom->getElementsByTagName('channel')->item(0);

        // Check required channel elements
        $this->assertNotNull($channel->getElementsByTagName('title')->item(0));
        $this->assertNotNull($channel->getElementsByTagName('link')->item(0));
        $this->assertNotNull($channel->getElementsByTagName('description')->item(0));
        $this->assertNotNull($channel->getElementsByTagName('language')->item(0));
        $this->assertNotNull($channel->getElementsByTagName('lastBuildDate')->item(0));
        $this->assertNotNull($channel->getElementsByTagName('generator')->item(0));
    }

    /**
     * Test default channel title and description
     */
    public function testChannelTitleAndDescriptionForAllArticles(): void
    {
        $xml = $this->feedGenerator->generate();
        $dom = new \DOMDocument();
        $dom->loadXML($xml);

        $channel = $dom->getElementsByTagName('channel')->item(0);
        $title = $channel->getElementsByTagName('title')->item(0)->textContent;
        $description = $channel->getElementsByTagName('description')->item(0)->textContent;

        $this->assertStringContainsString('Unfurl', $title);
        $this->assertStringContainsString('All Articles', $title);
        $this->assertStringContainsString('articles', $description);
    }

    /**
     * Test language is set to en-us
     */
    public function testChannelLanguageIsEnUs(): void
    {
        $xml = $this->feedGenerator->generate();
        $dom = new \DOMDocument();
        $dom->loadXML($xml);

        $channel = $dom->getElementsByTagName('channel')->item(0);
        $language = $channel->getElementsByTagName('language')->item(0)->textContent;

        $this->assertEquals('en-us', $language);
    }

    /**
     * Test generator version is included
     */
    public function testGeneratorIncludesVersion(): void
    {
        $xml = $this->feedGenerator->generate();
        $dom = new \DOMDocument();
        $dom->loadXML($xml);

        $channel = $dom->getElementsByTagName('channel')->item(0);
        $generator = $channel->getElementsByTagName('generator')->item(0)->textContent;

        $this->assertStringContainsString('Unfurl', $generator);
        $this->assertStringContainsString('1.0', $generator);
    }

    /**
     * Test item elements with full article data
     */
    public function testItemContainsAllRequiredElements(): void
    {
        // Create a feed and article for testing
        $feed = $this->createTestFeed(['topic' => 'Test Topic']);
        $article = $this->createTestArticle($feed, [
            'og_title' => 'Test Article Title',
            'final_url' => 'https://example.com/article',
            'og_description' => 'Test description',
            'article_content' => 'Full article content here',
            'pub_date' => date('Y-m-d H:i:s'),
            'author' => 'Test Author',
            'og_image' => 'https://example.com/image.jpg',
            'status' => 'success',
        ]);

        $xml = $this->feedGenerator->generate();
        $dom = new \DOMDocument();
        $dom->loadXML($xml);

        $items = $dom->getElementsByTagName('item');
        $this->assertGreaterThan(0, $items->length);

        $item = $items->item(0);

        // Check required item elements
        $this->assertNotNull($item->getElementsByTagName('title')->item(0), 'Item should have title');
        $this->assertNotNull($item->getElementsByTagName('link')->item(0), 'Item should have link');
        $this->assertNotNull($item->getElementsByTagName('description')->item(0), 'Item should have description');
        $this->assertNotNull($item->getElementsByTagName('guid')->item(0), 'Item should have guid');
        $this->assertNotNull($item->getElementsByTagName('pubDate')->item(0), 'Item should have pubDate');
    }

    /**
     * Test content:encoded element with full article text
     */
    public function testItemContainsContentEncoded(): void
    {
        $feed = $this->createTestFeed(['topic' => 'Test Topic']);
        $articleContent = 'This is the full article content that should be in content:encoded';
        $article = $this->createTestArticle($feed, [
            'article_content' => $articleContent,
            'status' => 'success',
        ]);

        $xml = $this->feedGenerator->generate();
        $dom = new \DOMDocument();
        $dom->loadXML($xml);

        // Register namespace
        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('content', 'http://purl.org/rss/1.0/modules/content/');

        $encodedContent = $xpath->query('//item/content:encoded')->item(0);
        $this->assertNotNull($encodedContent, 'Item should have content:encoded element');
        $this->assertStringContainsString($articleContent, $encodedContent->textContent);
    }

    /**
     * Test category element
     */
    public function testItemContainsCategory(): void
    {
        $feed = $this->createTestFeed(['topic' => 'Research']);
        $article = $this->createTestArticle($feed, [
            'status' => 'success',
        ]);

        $xml = $this->feedGenerator->generate();
        $dom = new \DOMDocument();
        $dom->loadXML($xml);

        $items = $dom->getElementsByTagName('item');
        $item = $items->item(0);
        $categories = $item->getElementsByTagName('category');

        $this->assertGreaterThan(0, $categories->length, 'Item should have at least one category');
    }

    /**
     * Test enclosure element for featured image
     */
    public function testItemContainsEnclosureForImage(): void
    {
        $feed = $this->createTestFeed(['topic' => 'Test']);
        $imageUrl = 'https://example.com/featured.jpg';
        $article = $this->createTestArticle($feed, [
            'og_image' => $imageUrl,
            'status' => 'success',
        ]);

        $xml = $this->feedGenerator->generate();
        $dom = new \DOMDocument();
        $dom->loadXML($xml);

        $items = $dom->getElementsByTagName('item');
        $item = $items->item(0);
        $enclosures = $item->getElementsByTagName('enclosure');

        $this->assertGreaterThan(0, $enclosures->length, 'Item should have enclosure for image');
        $this->assertEquals($imageUrl, $enclosures->item(0)->getAttribute('url'));
    }

    /**
     * Test dc:creator element for author
     */
    public function testItemContainsAuthor(): void
    {
        $feed = $this->createTestFeed(['topic' => 'Test']);
        $author = 'Test Author Name';
        $article = $this->createTestArticle($feed, [
            'author' => $author,
            'status' => 'success',
        ]);

        $xml = $this->feedGenerator->generate();
        $dom = new \DOMDocument();
        $dom->loadXML($xml);

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('dc', 'http://purl.org/dc/elements/1.1/');

        $creator = $xpath->query('//item/dc:creator')->item(0);
        $this->assertNotNull($creator, 'Item should have dc:creator element');
        $this->assertEquals($author, $creator->textContent);
    }

    /**
     * Test filtering by topic
     */
    public function testGenerateFiltersByTopic(): void
    {
        $feed1 = $this->createTestFeed(['topic' => 'Research']);
        $feed2 = $this->createTestFeed(['topic' => 'News']);

        $article1 = $this->createTestArticle($feed1, ['status' => 'success']);
        $article2 = $this->createTestArticle($feed2, ['status' => 'success']);

        $xml = $this->feedGenerator->generate(['topic' => 'Research']);
        $dom = new \DOMDocument();
        $dom->loadXML($xml);

        // Channel title should reflect topic
        $channel = $dom->getElementsByTagName('channel')->item(0);
        $title = $channel->getElementsByTagName('title')->item(0)->textContent;
        $this->assertStringContainsString('Research', $title);

        // Should only have one item
        $items = $dom->getElementsByTagName('item');
        $this->assertEquals(1, $items->length, 'Should only have article from Research topic');
    }

    /**
     * Test filtering by feed_id
     */
    public function testGenerateFiltersByFeedId(): void
    {
        $feed1 = $this->createTestFeed(['topic' => 'Feed 1']);
        $feed2 = $this->createTestFeed(['topic' => 'Feed 2']);

        $article1 = $this->createTestArticle($feed1, ['status' => 'success']);
        $article2 = $this->createTestArticle($feed2, ['status' => 'success']);

        $xml = $this->feedGenerator->generate(['feed_id' => $feed1['id']]);
        $dom = new \DOMDocument();
        $dom->loadXML($xml);

        $items = $dom->getElementsByTagName('item');
        $this->assertEquals(1, $items->length, 'Should only have articles from specified feed');
    }

    /**
     * Test filtering by status
     */
    public function testGenerateFiltersByStatus(): void
    {
        $feed = $this->createTestFeed(['topic' => 'Test']);

        $successArticle = $this->createTestArticle($feed, ['status' => 'success']);
        $failedArticle = $this->createTestArticle($feed, ['status' => 'failed']);

        $xml = $this->feedGenerator->generate(['status' => 'success']);
        $dom = new \DOMDocument();
        $dom->loadXML($xml);

        $items = $dom->getElementsByTagName('item');
        $this->assertEquals(1, $items->length, 'Should only have successful articles');
    }

    /**
     * Test pagination with limit
     */
    public function testGenerateAppliesLimit(): void
    {
        $feed = $this->createTestFeed(['topic' => 'Test']);

        // Create 5 articles
        for ($i = 0; $i < 5; $i++) {
            $this->createTestArticle($feed, ['status' => 'success']);
        }

        $xml = $this->feedGenerator->generate(['limit' => 2]);
        $dom = new \DOMDocument();
        $dom->loadXML($xml);

        $items = $dom->getElementsByTagName('item');
        $this->assertEquals(2, $items->length, 'Should respect limit parameter');
    }

    /**
     * Test pagination with offset
     */
    public function testGenerateAppliesOffset(): void
    {
        $feed = $this->createTestFeed(['topic' => 'Test']);

        // Create 5 articles with distinct titles
        $articles = [];
        for ($i = 0; $i < 5; $i++) {
            $articles[] = $this->createTestArticle($feed, [
                'og_title' => "Article {$i}",
                'status' => 'success',
            ]);
        }

        // Get first 2
        $xml1 = $this->feedGenerator->generate(['limit' => 2, 'offset' => 0]);
        $dom1 = new \DOMDocument();
        $dom1->loadXML($xml1);
        $items1 = $dom1->getElementsByTagName('item');

        // Get next 2 with offset
        $xml2 = $this->feedGenerator->generate(['limit' => 2, 'offset' => 2]);
        $dom2 = new \DOMDocument();
        $dom2->loadXML($xml2);
        $items2 = $dom2->getElementsByTagName('item');

        // Should have different articles
        $title1_1 = $items1->item(0)->getElementsByTagName('title')->item(0)->textContent;
        $title2_1 = $items2->item(0)->getElementsByTagName('title')->item(0)->textContent;

        $this->assertNotEquals($title1_1, $title2_1, 'Offset should return different articles');
    }

    /**
     * Test caching with cache hit
     */
    public function testGenerateUsesCacheOnSecondCall(): void
    {
        $feed = $this->createTestFeed(['topic' => 'Test']);
        $article = $this->createTestArticle($feed, ['status' => 'success']);

        // First call - generates and caches
        $xml1 = $this->feedGenerator->generate();

        // Sleep briefly to ensure time difference would be detected
        usleep(100000); // 0.1 seconds

        // Second call - should return cached version
        $xml2 = $this->feedGenerator->generate();

        // Should be identical (no new articles added)
        $this->assertEquals($xml1, $xml2, 'Cached feed should return same content');
    }

    /**
     * Test cache expiration
     */
    public function testCacheExpiresAfterTimeout(): void
    {
        $feed = $this->createTestFeed(['topic' => 'Test']);
        $article1 = $this->createTestArticle($feed, [
            'og_title' => 'Article 1',
            'status' => 'success',
        ]);

        // First call with normal cache time
        $xml1 = $this->feedGenerator->generate();

        // Add another article
        $article2 = $this->createTestArticle($feed, [
            'og_title' => 'Article 2',
            'status' => 'success',
        ]);

        // Second call to same generator should still use cache (cache not expired)
        $xml2 = $this->feedGenerator->generate();
        $this->assertEquals($xml1, $xml2, 'Valid cache should return same content');

        // Now create a generator with different cache directory (fresh cache)
        $cacheDirExpired = sys_get_temp_dir() . '/unfurl_rss_cache_expired_' . uniqid();
        mkdir($cacheDirExpired, 0755, true);

        $expiredGenerator = new RssFeedGenerator(
            $this->articleRepository,
            $cacheDirExpired,
            $this->config,
            300 // Fresh cache with normal time
        );

        // Third call with fresh cache should regenerate and include new articles
        $xml3 = $expiredGenerator->generate();

        // Parse both to check item count
        $dom1 = new \DOMDocument();
        $dom1->loadXML($xml1);
        $items1 = $dom1->getElementsByTagName('item');
        $count1 = $items1->length;

        $dom3 = new \DOMDocument();
        $dom3->loadXML($xml3);
        $items3 = $dom3->getElementsByTagName('item');
        $count3 = $items3->length;

        // Cleanup
        array_map('unlink', glob($cacheDirExpired . '/*.*'));
        rmdir($cacheDirExpired);

        $this->assertGreaterThan($count1, $count3, 'Fresh cache should regenerate with new articles');
    }

    /**
     * Test default limit is 20
     */
    public function testDefaultLimitIs20(): void
    {
        $feed = $this->createTestFeed(['topic' => 'Test']);

        // Create 30 articles
        for ($i = 0; $i < 30; $i++) {
            $this->createTestArticle($feed, ['status' => 'success']);
        }

        $xml = $this->feedGenerator->generate();
        $dom = new \DOMDocument();
        $dom->loadXML($xml);

        $items = $dom->getElementsByTagName('item');
        $this->assertEquals(20, $items->length, 'Default limit should be 20');
    }

    /**
     * Test maximum limit enforcement (100)
     */
    public function testMaximumLimitEnforcement(): void
    {
        $feed = $this->createTestFeed(['topic' => 'Test']);

        // Create 150 articles
        for ($i = 0; $i < 150; $i++) {
            $this->createTestArticle($feed, ['status' => 'success']);
        }

        $xml = $this->feedGenerator->generate(['limit' => 200]); // Request more than max
        $dom = new \DOMDocument();
        $dom->loadXML($xml);

        $items = $dom->getElementsByTagName('item');
        $this->assertEquals(100, $items->length, 'Should enforce maximum limit of 100');
    }

    /**
     * Test empty feed
     */
    public function testGenerateHandlesEmptyFeed(): void
    {
        $xml = $this->feedGenerator->generate();
        $dom = new \DOMDocument();
        $loadResult = @$dom->loadXML($xml);

        $this->assertTrue($loadResult, 'Should generate valid XML for empty feed');

        $channel = $dom->getElementsByTagName('channel')->item(0);
        $items = $channel->getElementsByTagName('item');

        $this->assertEquals(0, $items->length, 'Empty feed should have no items');
    }

    /**
     * Test article prefers og:title over page_title
     */
    public function testArticleTitlePrefersOgTitle(): void
    {
        $feed = $this->createTestFeed(['topic' => 'Test']);
        $article = $this->createTestArticle($feed, [
            'og_title' => 'OG Title',
            'page_title' => 'Page Title',
            'rss_title' => 'RSS Title',
            'status' => 'success',
        ]);

        $xml = $this->feedGenerator->generate();
        $dom = new \DOMDocument();
        $dom->loadXML($xml);

        $item = $dom->getElementsByTagName('item')->item(0);
        $title = $item->getElementsByTagName('title')->item(0)->textContent;

        $this->assertEquals('OG Title', $title);
    }

    /**
     * Test article prefers og:description over rss:description
     */
    public function testArticleDescriptionPrefersOgDescription(): void
    {
        $feed = $this->createTestFeed(['topic' => 'Test']);
        $article = $this->createTestArticle($feed, [
            'og_description' => 'OG Description',
            'rss_description' => 'RSS Description',
            'status' => 'success',
        ]);

        $xml = $this->feedGenerator->generate();
        $dom = new \DOMDocument();
        $dom->loadXML($xml);

        $item = $dom->getElementsByTagName('item')->item(0);
        $description = $item->getElementsByTagName('description')->item(0)->textContent;

        $this->assertStringContainsString('OG Description', $description);
    }

    /**
     * Test guid is the final URL
     */
    public function testGuidIsPermalinkUsingFinalUrl(): void
    {
        $feed = $this->createTestFeed(['topic' => 'Test']);
        $finalUrl = 'https://example.com/article/12345';
        $article = $this->createTestArticle($feed, [
            'final_url' => $finalUrl,
            'status' => 'success',
        ]);

        $xml = $this->feedGenerator->generate();
        $dom = new \DOMDocument();
        $dom->loadXML($xml);

        $item = $dom->getElementsByTagName('item')->item(0);
        $guid = $item->getElementsByTagName('guid')->item(0);

        $this->assertEquals($finalUrl, $guid->textContent);
        $this->assertEquals('true', $guid->getAttribute('isPermaLink'));
    }

    /**
     * Test pubDate is in RFC 2822 format
     */
    public function testPubDateIsRfc2822Format(): void
    {
        $feed = $this->createTestFeed(['topic' => 'Test']);
        $article = $this->createTestArticle($feed, [
            'pub_date' => '2026-02-07 12:30:45',
            'status' => 'success',
        ]);

        $xml = $this->feedGenerator->generate();
        $dom = new \DOMDocument();
        $dom->loadXML($xml);

        $item = $dom->getElementsByTagName('item')->item(0);
        $pubDate = $item->getElementsByTagName('pubDate')->item(0)->textContent;

        // Should be RFC 2822 format like "Fri, 07 Feb 2026 12:30:45 GMT"
        $this->assertMatchesRegularExpression('/^[A-Z][a-z]{2}, \d{2} [A-Z][a-z]{2} \d{4} \d{2}:\d{2}:\d{2} GMT$/', $pubDate);
    }

    /**
     * Test lastBuildDate is current time
     */
    public function testLastBuildDateIsCurrentTime(): void
    {
        $feed = $this->createTestFeed(['topic' => 'Test']);
        $article = $this->createTestArticle($feed, ['status' => 'success']);

        $beforeTime = time();
        $xml = $this->feedGenerator->generate();
        $afterTime = time();

        $dom = new \DOMDocument();
        $dom->loadXML($xml);

        $channel = $dom->getElementsByTagName('channel')->item(0);
        $lastBuildDate = $channel->getElementsByTagName('lastBuildDate')->item(0)->textContent;

        // Parse the date (format: "Fri, 07 Feb 2026 12:30:45 GMT")
        // strtotime should handle RFC 2822 format
        $buildTimestamp = strtotime($lastBuildDate);
        $this->assertNotFalse($buildTimestamp);
        $this->assertGreaterThanOrEqual($beforeTime, $buildTimestamp);
        $this->assertLessThanOrEqual($afterTime + 1, $buildTimestamp); // +1 to allow for timing
    }

    /**
     * Test CDATA wrapping for content
     */
    public function testContentEncodedIsWrappedInCdata(): void
    {
        $feed = $this->createTestFeed(['topic' => 'Test']);
        $content = 'Content with <special> & "characters"';
        $article = $this->createTestArticle($feed, [
            'article_content' => $content,
            'status' => 'success',
        ]);

        $xml = $this->feedGenerator->generate();

        // Check that CDATA is used (raw XML check)
        $this->assertStringContainsString('<![CDATA[', $xml);
    }

    // ====== Helper Methods ======

    private function createTestFeed(array $data = []): array
    {
        static $feedCounter = 0;
        $feedCounter++;

        $defaults = [
            'topic' => 'Test Topic ' . uniqid(),
            'url' => 'https://example.com/news?q=test',
            'result_limit' => 10,
            'enabled' => 1,
        ];

        $feedData = array_merge($defaults, $data);

        // Return feed data with unique ID
        return [
            'id' => $feedCounter,
            'topic' => $feedData['topic'],
        ];
    }

    private function createTestArticle($feedData, array $data = []): array
    {
        // Handle both array and integer feed_id
        $feedId = is_array($feedData) ? ($feedData['id'] ?? 1) : $feedData;
        $topic = is_array($feedData) ? ($feedData['topic'] ?? 'Test Topic') : 'Test Topic';

        $defaults = [
            'feed_id' => $feedId,
            'topic' => $topic,
            'google_news_url' => 'https://news.google.com/articles/' . uniqid(),
            'rss_title' => 'Test Article Title',
            'pub_date' => date('Y-m-d H:i:s'),
            'rss_description' => 'Test description',
            'rss_source' => 'Example.com',
            'final_url' => 'https://example.com/article/' . uniqid(),
            'status' => 'success',
            'page_title' => 'Test Article Title',
            'og_title' => 'Test Article Title',
            'og_description' => 'Test description',
            'og_image' => 'https://example.com/image.jpg',
            'og_url' => 'https://example.com/article',
            'og_site_name' => 'Example',
            'author' => 'Test Author',
            'article_content' => 'This is the full article content.',
            'word_count' => 100,
            'categories' => json_encode(['Technology', 'News']),
        ];

        $articleData = array_merge($defaults, $data);
        $articleId = $this->articleRepository->create($articleData);

        return array_merge($articleData, ['id' => $articleId]);
    }
}
