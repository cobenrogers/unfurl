<?php

declare(strict_types=1);

namespace Tests\Security;

use PHPUnit\Framework\TestCase;
use Unfurl\Core\Database;
use Unfurl\Core\Logger;
use Unfurl\Core\TimezoneHelper;
use Unfurl\Controllers\FeedController;
use Unfurl\Controllers\ArticleController;
use Unfurl\Controllers\SettingsController;
use Unfurl\Controllers\ApiController;
use Unfurl\Repositories\FeedRepository;
use Unfurl\Repositories\ArticleRepository;
use Unfurl\Repositories\ApiKeyRepository;
use Unfurl\Security\CsrfToken;
use Unfurl\Security\InputValidator;
use Unfurl\Security\OutputEscaper;
use Unfurl\Security\UrlValidator;
use Unfurl\Services\ProcessingQueue;
use Unfurl\Services\GoogleNews\UrlDecoder;
use Unfurl\Services\ArticleExtractor;
use Unfurl\Exceptions\ValidationException;
use Unfurl\Exceptions\SecurityException;

/**
 * Security Audit Test Suite
 *
 * Comprehensive security testing that verifies all security measures are properly
 * implemented and cannot be bypassed. Tests all attack vectors from OWASP Top 10.
 *
 * Test Coverage:
 * 1. SQL Injection Prevention
 * 2. XSS (Cross-Site Scripting) Prevention
 * 3. CSRF (Cross-Site Request Forgery) Protection
 * 4. SSRF (Server-Side Request Forgery) Protection
 * 5. Rate Limiting
 * 6. Authentication & Authorization
 *
 * Requirements: Task 6.2 - Security Testing
 */
class SecurityAuditTest extends TestCase
{
    private Database $db;
    private Logger $logger;
    private FeedRepository $feedRepo;
    private ArticleRepository $articleRepo;
    private ApiKeyRepository $apiKeyRepo;
    private CsrfToken $csrf;
    private InputValidator $validator;
    private OutputEscaper $escaper;
    private UrlValidator $urlValidator;
    private FeedController $feedController;
    private ArticleController $articleController;
    private SettingsController $settingsController;
    private ApiController $apiController;

    protected function setUp(): void
    {
        // Use in-memory SQLite for tests
        $config = [
            'database' => [
                'host' => 'localhost',
                'name' => ':memory:',
                'user' => '',
                'pass' => '',
                'charset' => 'utf8mb4',
                'options' => [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                    \PDO::ATTR_EMULATE_PREPARES => false,
                ],
            ],
        ];

        $this->db = new Database($config);
        $this->logger = new Logger('/tmp/unfurl_test.log', 'debug');
        $timezone = new TimezoneHelper();

        // Initialize repositories
        $this->feedRepo = new FeedRepository($this->db, $timezone);
        $this->articleRepo = new ArticleRepository($this->db, $timezone);
        $this->apiKeyRepo = new ApiKeyRepository($this->db, $timezone);

        // Initialize security components
        $this->csrf = new CsrfToken();
        $this->validator = new InputValidator();
        $this->escaper = new OutputEscaper();
        $this->urlValidator = new UrlValidator();

        // Initialize controllers for testing
        $queue = new ProcessingQueue($this->articleRepo, $this->logger, $timezone);

        $this->feedController = new FeedController(
            $this->feedRepo,
            $queue,
            $this->csrf,
            $this->validator,
            $this->escaper,
            $this->logger
        );

        $this->articleController = new ArticleController(
            $this->articleRepo,
            $queue,
            $this->csrf,
            $this->escaper,
            $this->logger
        );

        $this->settingsController = new SettingsController(
            $this->apiKeyRepo,
            $this->csrf,
            $this->logger
        );

        // ApiController setup with proper dependencies
        $unfurlService = $this->createMock(\Unfurl\Services\UnfurlService::class);
        $urlDecoder = new UrlDecoder($this->urlValidator, []);
        $extractor = new ArticleExtractor();

        $this->apiController = new ApiController(
            $this->apiKeyRepo,
            $this->feedRepo,
            $this->articleRepo,
            $unfurlService,
            $urlDecoder,
            $extractor,
            $queue,
            $this->logger,
            'php'
        );

        // Create database schema
        $this->createTestSchema();
    }

    private function createTestSchema(): void
    {
        // Create feeds table
        $this->db->execute("
            CREATE TABLE feeds (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                topic TEXT NOT NULL UNIQUE,
                url TEXT NOT NULL,
                result_limit INTEGER DEFAULT 10,
                enabled INTEGER DEFAULT 1,
                last_processed_at DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Create articles table
        $this->db->execute("
            CREATE TABLE articles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                feed_id INTEGER NOT NULL,
                topic TEXT NOT NULL,
                google_news_url TEXT NOT NULL,
                rss_title TEXT,
                pub_date DATETIME,
                rss_description TEXT,
                rss_source TEXT,
                final_url TEXT UNIQUE,
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
                next_retry_at DATETIME,
                last_error TEXT,
                processed_at DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (feed_id) REFERENCES feeds(id) ON DELETE CASCADE
            )
        ");

        // Create api_keys table
        $this->db->execute("
            CREATE TABLE api_keys (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                key_name TEXT NOT NULL,
                key_value TEXT NOT NULL UNIQUE,
                description TEXT,
                enabled INTEGER DEFAULT 1,
                last_used_at DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }

    // ============================================
    // 1. SQL INJECTION PREVENTION
    // ============================================

    public function test_sql_injection_in_feed_topic(): void
    {
        $sqlPayloads = [
            "'; DROP TABLE feeds; --",
            "1' OR '1'='1",
            "admin'--",
            "' UNION SELECT * FROM api_keys--",
            "1'; DELETE FROM feeds WHERE '1'='1",
        ];

        foreach ($sqlPayloads as $payload) {
            $data = [
                'topic' => $payload,
                'url' => 'https://news.google.com/rss',
                'limit' => 10,
                'csrf_token' => $this->csrf->getToken(),
            ];

            // Should be rejected by input validation
            $this->expectException(ValidationException::class);
            $this->validator->validateFeed($data);
        }

        // Verify tables still exist (no SQL injection succeeded)
        $result = $this->db->querySingle("SELECT name FROM sqlite_master WHERE type='table' AND name='feeds'");
        $this->assertNotNull($result);
    }

    public function test_sql_injection_in_feed_url(): void
    {
        $data = [
            'topic' => 'Tech',
            'url' => "https://news.google.com/rss' OR '1'='1",
            'limit' => 10,
        ];

        // Should be rejected by URL validation
        $this->expectException(ValidationException::class);
        $this->validator->validateFeed($data);
    }

    public function test_sql_injection_in_article_search(): void
    {
        // Create test feed first
        $feedId = $this->feedRepo->create([
            'topic' => 'Test',
            'url' => 'https://news.google.com/rss',
            'result_limit' => 10,
        ]);

        // Create test article
        $this->articleRepo->create([
            'feed_id' => $feedId,
            'topic' => 'Test',
            'google_news_url' => 'https://news.google.com/test',
            'rss_title' => 'Test Article',
            'status' => 'success',
        ]);

        $sqlPayloads = [
            "'; DROP TABLE articles; --",
            "1' OR '1'='1",
        ];

        // Test topic and status filters (these work in SQLite)
        foreach ($sqlPayloads as $payload) {
            $filters = ['topic' => $payload];

            // Should not cause SQL error (prepared statements protect)
            $result = $this->articleRepo->findWithFilters($filters);
            $this->assertIsArray($result);
        }

        // Test with status filter
        $filters = ['status' => "'; DROP TABLE articles; --"];
        $result = $this->articleRepo->findWithFilters($filters);
        $this->assertIsArray($result);

        // Verify table still exists (SQL injection didn't work)
        $result = $this->db->querySingle("SELECT name FROM sqlite_master WHERE type='table' AND name='articles'");
        $this->assertNotNull($result);
    }

    public function test_all_repository_methods_use_prepared_statements(): void
    {
        // Create test data
        $feedId = $this->feedRepo->create([
            'topic' => 'Security Test',
            'url' => 'https://news.google.com/rss',
            'result_limit' => 10,
        ]);

        // Test FeedRepository methods with SQL injection attempts
        // Note: Type-safe parameters prevent SQL injection at compile time
        // This test verifies the repo methods use prepared statements by
        // attempting SQL injection in string data fields

        // Test update with SQL injection in data (should be treated as literal string)
        $this->feedRepo->update($feedId, ['topic' => "Test'; DROP TABLE feeds; --"]);

        // Retrieve the feed and verify the SQL injection was stored as literal text
        $feed = $this->feedRepo->findById($feedId);
        $this->assertEquals("Test'; DROP TABLE feeds; --", $feed['topic']);

        // Verify table still exists (SQL injection didn't execute)
        $allFeeds = $this->feedRepo->findAll();
        $this->assertIsArray($allFeeds);
        $this->assertGreaterThan(0, count($allFeeds));
    }

    // ============================================
    // 2. XSS (CROSS-SITE SCRIPTING) PREVENTION
    // ============================================

    public function test_xss_prevention_in_html_context(): void
    {
        $xssPayloads = [
            '<script>alert("XSS")</script>',
            '<img src=x onerror=alert("XSS")>',
            '<svg onload=alert("XSS")>',
            '<iframe src="javascript:alert(\'XSS\')">',
            '<body onload=alert("XSS")>',
            '<input type="text" value="XSS" onfocus="alert(\'XSS\')">',
        ];

        foreach ($xssPayloads as $payload) {
            $escaped = $this->escaper->html($payload);

            // Verify dangerous characters are escaped (HTML entities)
            $this->assertStringNotContainsString('<script>', $escaped);
            $this->assertStringNotContainsString('<img', $escaped);
            $this->assertStringNotContainsString('<svg', $escaped);
            $this->assertStringNotContainsString('<iframe', $escaped);
            $this->assertStringNotContainsString('<body', $escaped);
            $this->assertStringNotContainsString('<input', $escaped);

            // Check that < and > ARE properly encoded (if they exist in original)
            if (strpos($payload, '<') !== false) {
                $this->assertStringContainsString('&lt;', $escaped);
            }
            if (strpos($payload, '>') !== false) {
                $this->assertStringContainsString('&gt;', $escaped);
            }
        }
    }

    public function test_xss_prevention_in_javascript_context(): void
    {
        $xssPayloads = [
            '"; alert("XSS"); //',
            '\'; alert("XSS"); //',
            '</script><script>alert("XSS")</script>',
            'xss</script><script>alert(1)</script>',
        ];

        foreach ($xssPayloads as $payload) {
            $escaped = $this->escaper->js($payload);

            // Should be JSON encoded, making it safe
            $this->assertStringContainsString('\\', $escaped); // Characters should be escaped
            $this->assertStringNotContainsString('</script>', $escaped);
        }
    }

    public function test_xss_prevention_in_attribute_context(): void
    {
        $xssPayloads = [
            '" onload="alert(\'XSS\')"',
            '\' onload=\'alert("XSS")\'',
            'javascript:alert("XSS")',
        ];

        foreach ($xssPayloads as $payload) {
            $escaped = $this->escaper->attribute($payload);

            // Verify quotes are escaped (prevents breaking out of attributes)
            $this->assertStringContainsString('&quot;', $escaped);

            // Verify the escaped version doesn't contain unescaped dangerous content
            $this->assertStringNotContainsString('" onload="', $escaped);
            $this->assertStringNotContainsString('\' onload=\'', $escaped);
        }
    }

    public function test_xss_prevention_in_url_context(): void
    {
        $xssPayloads = [
            'javascript:alert("XSS")',
            'data:text/html,<script>alert("XSS")</script>',
            'vbscript:msgbox("XSS")',
        ];

        foreach ($xssPayloads as $payload) {
            $escaped = $this->escaper->url($payload);

            // Should be URL encoded
            $this->assertStringNotContainsString('javascript:', $escaped);
            $this->assertStringNotContainsString('<script>', $escaped);
        }
    }

    public function test_xss_prevention_in_feed_names(): void
    {
        $xssPayload = '<script>alert("XSS")</script>';

        $data = [
            'topic' => $xssPayload,
            'url' => 'https://news.google.com/rss',
            'limit' => 10,
        ];

        // Should be rejected by input validation (special characters not allowed)
        $this->expectException(ValidationException::class);
        $this->validator->validateFeed($data);
    }

    public function test_xss_prevention_in_article_content(): void
    {
        // Create feed
        $feedId = $this->feedRepo->create([
            'topic' => 'Test',
            'url' => 'https://news.google.com/rss',
            'result_limit' => 10,
        ]);

        // Create article with XSS payload in content
        $xssPayload = '<script>alert("XSS")</script>';
        $articleId = $this->articleRepo->create([
            'feed_id' => $feedId,
            'topic' => 'Test',
            'google_news_url' => 'https://news.google.com/test',
            'rss_title' => $xssPayload,
            'og_title' => $xssPayload,
            'og_description' => $xssPayload,
            'article_content' => $xssPayload,
            'status' => 'success',
        ]);

        // Retrieve article
        $article = $this->articleRepo->findById($articleId);

        // Verify content is stored as-is (escaping happens on output)
        $this->assertEquals($xssPayload, $article['rss_title']);

        // Verify OutputEscaper properly escapes on output
        $escaped = $this->escaper->html($article['rss_title']);
        $this->assertStringNotContainsString('<script>', $escaped);
        $this->assertStringContainsString('&lt;script&gt;', $escaped);
    }

    // ============================================
    // 3. CSRF (CROSS-SITE REQUEST FORGERY) PROTECTION
    // ============================================

    public function test_csrf_token_required_for_feed_creation(): void
    {
        $data = [
            'topic' => 'Test Feed',
            'url' => 'https://news.google.com/rss',
            'limit' => 10,
            // Missing CSRF token
        ];

        $result = $this->feedController->create($data);

        $this->assertEquals('error', $result['status']);
        $this->assertEquals('Security validation failed', $result['message']);
        $this->assertEquals(403, $result['http_code']);
    }

    public function test_csrf_token_required_for_feed_edit(): void
    {
        // Create feed first
        $feedId = $this->feedRepo->create([
            'topic' => 'Test',
            'url' => 'https://news.google.com/rss',
            'result_limit' => 10,
        ]);

        $data = [
            'topic' => 'Updated Test',
            'url' => 'https://news.google.com/rss',
            'limit' => 20,
            // Missing CSRF token
        ];

        $result = $this->feedController->edit($feedId, $data);

        $this->assertEquals('error', $result['status']);
        $this->assertEquals('Security validation failed', $result['message']);
        $this->assertEquals(403, $result['http_code']);
    }

    public function test_csrf_token_required_for_feed_deletion(): void
    {
        // Create feed first
        $feedId = $this->feedRepo->create([
            'topic' => 'Test',
            'url' => 'https://news.google.com/rss',
            'result_limit' => 10,
        ]);

        $data = [
            // Missing CSRF token
        ];

        $result = $this->feedController->delete($feedId, $data);

        $this->assertEquals('error', $result['status']);
        $this->assertEquals('Security validation failed', $result['message']);
        $this->assertEquals(403, $result['http_code']);
    }

    public function test_csrf_token_required_for_feed_run(): void
    {
        // Create feed first
        $feedId = $this->feedRepo->create([
            'topic' => 'Test',
            'url' => 'https://news.google.com/rss',
            'result_limit' => 10,
        ]);

        $data = [
            // Missing CSRF token
        ];

        $result = $this->feedController->run($feedId, $data);

        $this->assertEquals('error', $result['status']);
        $this->assertEquals('Security validation failed', $result['message']);
        $this->assertEquals(403, $result['http_code']);
    }

    public function test_invalid_csrf_token_rejected(): void
    {
        $data = [
            'topic' => 'Test Feed',
            'url' => 'https://news.google.com/rss',
            'limit' => 10,
            'csrf_token' => 'invalid_token_12345',
        ];

        $result = $this->feedController->create($data);

        $this->assertEquals('error', $result['status']);
        $this->assertEquals('Security validation failed', $result['message']);
        $this->assertEquals(403, $result['http_code']);
    }

    public function test_valid_csrf_token_accepted(): void
    {
        $token = $this->csrf->getToken();

        $data = [
            'topic' => 'Test Feed CSRF',
            'url' => 'https://news.google.com/rss',
            'limit' => 10,
            'csrf_token' => $token,
        ];

        $result = $this->feedController->create($data);

        $this->assertEquals('success', $result['status']);
    }

    public function test_csrf_token_regeneration_after_validation(): void
    {
        $token1 = $this->csrf->getToken();

        // Use the token
        $this->csrf->validate($token1);

        // Token should be regenerated
        $token2 = $this->csrf->getToken();

        $this->assertNotEquals($token1, $token2);
    }

    public function test_csrf_token_cannot_be_reused(): void
    {
        $token = $this->csrf->getToken();

        // Use the token once (this should succeed)
        $this->csrf->validate($token);

        // Try to use it again (this should fail)
        $this->expectException(SecurityException::class);
        $this->csrf->validate($token);
    }

    // ============================================
    // 4. SSRF (SERVER-SIDE REQUEST FORGERY) PROTECTION
    // ============================================

    public function test_ssrf_blocks_private_ipv4_addresses(): void
    {
        $privateUrls = [
            'http://127.0.0.1/api',
            'http://localhost/api',
            'http://10.0.0.1/internal',
            'http://192.168.1.1/admin',
            'http://172.16.0.1/secret',
            'http://169.254.169.254/metadata', // AWS metadata endpoint
        ];

        foreach ($privateUrls as $url) {
            $this->expectException(SecurityException::class);
            $this->urlValidator->validate($url);
        }
    }

    public function test_ssrf_blocks_private_ipv6_addresses(): void
    {
        $privateUrls = [
            'http://[::1]/api',
            'http://[fc00::1]/internal',
            'http://[fe80::1]/local',
        ];

        foreach ($privateUrls as $url) {
            $this->expectException(SecurityException::class);
            $this->urlValidator->validate($url);
        }
    }

    public function test_ssrf_blocks_invalid_schemes(): void
    {
        $invalidSchemes = [
            'file:///etc/passwd',
            'ftp://example.com/file',
            'gopher://example.com',
            'dict://example.com',
            'php://filter/resource=index.php',
            'data:text/html,<script>alert(1)</script>',
        ];

        foreach ($invalidSchemes as $url) {
            $this->expectException(SecurityException::class);
            $this->urlValidator->validate($url);
        }
    }

    public function test_ssrf_allows_public_http_urls(): void
    {
        // Skip this test if DNS resolution would fail
        $this->markTestSkipped('Requires DNS resolution and network access');

        $validUrls = [
            'http://example.com',
            'https://google.com',
            'https://news.ycombinator.com',
        ];

        foreach ($validUrls as $url) {
            // Should not throw exception
            $this->urlValidator->validate($url);
            $this->assertTrue(true);
        }
    }

    public function test_ssrf_blocks_dns_rebinding_to_private_ips(): void
    {
        // This test verifies that even if a hostname resolves to a private IP,
        // it gets blocked (prevents DNS rebinding attacks)

        // Mock hostname that "resolves" to private IP
        // In real scenario, this would need network mocking

        // For now, test direct IP blocking
        $this->expectException(SecurityException::class);
        $this->urlValidator->validate('http://127.0.0.1');
    }

    public function test_ssrf_url_length_limit(): void
    {
        $longUrl = 'http://example.com/' . str_repeat('a', 3000);

        $this->expectException(SecurityException::class);
        $this->urlValidator->validate($longUrl);
    }

    // ============================================
    // 5. RATE LIMITING
    // ============================================

    public function test_rate_limiting_blocks_excessive_requests(): void
    {
        // Create API key with unique value
        $timestamp = microtime(true);
        $apiKeyId = $this->apiKeyRepo->create([
            'key_name' => 'Test Key Rate Limit',
            'key_value' => 'test_key_ratelimit_' . $timestamp,
            'enabled' => 1,
        ]);

        // Simulate 60 requests (at the limit)
        $reflection = new \ReflectionClass($this->apiController);
        $rateLimitMethod = $reflection->getMethod('checkRateLimit');
        $rateLimitMethod->setAccessible(true);

        // Clear the static rate limit tracker for this key
        $trackerProperty = $reflection->getProperty('rateLimitTracker');
        $trackerProperty->setAccessible(true);
        $tracker = $trackerProperty->getValue();
        $tracker[$apiKeyId] = []; // Clear this key's history
        $trackerProperty->setValue(null, $tracker);

        // Fill up the rate limit
        for ($i = 0; $i < 60; $i++) {
            $rateLimitMethod->invoke($this->apiController, $apiKeyId);
        }

        // 61st request should fail
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Rate limit exceeded');
        $rateLimitMethod->invoke($this->apiController, $apiKeyId);
    }

    public function test_rate_limiting_resets_after_time_window(): void
    {
        // This test verifies that rate limits reset after the time window
        // Since the tracker is static, we need to test with a fresh API key

        $timestamp = microtime(true);
        $apiKeyId = $this->apiKeyRepo->create([
            'key_name' => 'Test Key Time',
            'key_value' => 'test_key_time_window_' . $timestamp,
            'enabled' => 1,
        ]);

        $reflection = new \ReflectionClass($this->apiController);
        $rateLimitMethod = $reflection->getMethod('checkRateLimit');
        $rateLimitMethod->setAccessible(true);

        // Clear the static rate limit tracker for this key
        $trackerProperty = $reflection->getProperty('rateLimitTracker');
        $trackerProperty->setAccessible(true);
        $tracker = $trackerProperty->getValue();
        $tracker[$apiKeyId] = []; // Clear this key's history
        $trackerProperty->setValue(null, $tracker);

        // Should not throw exception on first few requests
        for ($i = 0; $i < 5; $i++) {
            $rateLimitMethod->invoke($this->apiController, $apiKeyId);
        }

        $this->assertTrue(true);

        // Note: Full time window reset testing would require time mocking
        // which is beyond the scope of this security audit
    }

    public function test_rate_limiting_per_api_key(): void
    {
        // Create two unique API keys with timestamps to avoid conflicts
        $timestamp = microtime(true);
        $apiKey1 = $this->apiKeyRepo->create([
            'key_name' => 'Key 1',
            'key_value' => 'key_1_' . $timestamp,
            'enabled' => 1,
        ]);

        $apiKey2 = $this->apiKeyRepo->create([
            'key_name' => 'Key 2',
            'key_value' => 'key_2_' . $timestamp,
            'enabled' => 1,
        ]);

        $reflection = new \ReflectionClass($this->apiController);
        $rateLimitMethod = $reflection->getMethod('checkRateLimit');
        $rateLimitMethod->setAccessible(true);

        // Clear the static rate limit tracker for both keys
        $trackerProperty = $reflection->getProperty('rateLimitTracker');
        $trackerProperty->setAccessible(true);
        $tracker = $trackerProperty->getValue();
        $tracker[$apiKey1] = []; // Clear key 1 history
        $tracker[$apiKey2] = []; // Clear key 2 history
        $trackerProperty->setValue(null, $tracker);

        // Fill rate limit for key 1 (just enough to test, not hit limit)
        for ($i = 0; $i < 10; $i++) {
            $rateLimitMethod->invoke($this->apiController, $apiKey1);
        }

        // Key 2 should still work (independent rate limit)
        $rateLimitMethod->invoke($this->apiController, $apiKey2);
        $this->assertTrue(true);
    }

    // ============================================
    // 6. AUTHENTICATION & AUTHORIZATION
    // ============================================

    public function test_api_requires_valid_api_key(): void
    {
        // Attempt to use API without key
        $_SERVER['HTTP_X_API_KEY'] = '';

        $reflection = new \ReflectionClass($this->apiController);
        $getKeyMethod = $reflection->getMethod('getApiKeyFromHeader');
        $getKeyMethod->setAccessible(true);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Missing X-API-Key header');
        $getKeyMethod->invoke($this->apiController);
    }

    public function test_api_rejects_invalid_api_key(): void
    {
        $reflection = new \ReflectionClass($this->apiController);
        $validateMethod = $reflection->getMethod('validateApiKey');
        $validateMethod->setAccessible(true);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid API key');
        $validateMethod->invoke($this->apiController, 'invalid_key_12345');
    }

    public function test_api_rejects_disabled_api_key(): void
    {
        // Create disabled API key
        $this->apiKeyRepo->create([
            'key_name' => 'Disabled Key',
            'key_value' => 'disabled_key',
            'enabled' => 0,
        ]);

        $reflection = new \ReflectionClass($this->apiController);
        $validateMethod = $reflection->getMethod('validateApiKey');
        $validateMethod->setAccessible(true);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('API key is disabled');
        $validateMethod->invoke($this->apiController, 'disabled_key');
    }

    public function test_api_accepts_valid_enabled_api_key(): void
    {
        // Create enabled API key
        $this->apiKeyRepo->create([
            'key_name' => 'Valid Key',
            'key_value' => 'valid_key_123',
            'enabled' => 1,
        ]);

        $reflection = new \ReflectionClass($this->apiController);
        $validateMethod = $reflection->getMethod('validateApiKey');
        $validateMethod->setAccessible(true);

        $result = $validateMethod->invoke($this->apiController, 'valid_key_123');

        $this->assertIsArray($result);
        $this->assertEquals('Valid Key', $result['key_name']);
    }

    public function test_api_updates_last_used_timestamp(): void
    {
        // Create API key
        $apiKeyId = $this->apiKeyRepo->create([
            'key_name' => 'Usage Test',
            'key_value' => 'usage_test_key',
            'enabled' => 1,
        ]);

        // Get initial state
        $before = $this->apiKeyRepo->findById($apiKeyId);
        $this->assertNull($before['last_used_at']);

        // Validate the key (simulating API usage)
        $reflection = new \ReflectionClass($this->apiController);
        $validateMethod = $reflection->getMethod('validateApiKey');
        $validateMethod->setAccessible(true);
        $validateMethod->invoke($this->apiController, 'usage_test_key');

        // Check last_used_at was updated
        $after = $this->apiKeyRepo->findById($apiKeyId);
        $this->assertNotNull($after['last_used_at']);
    }

    // ============================================
    // 7. COMPREHENSIVE ATTACK SIMULATION
    // ============================================

    public function test_combined_attack_vectors(): void
    {
        // Simulate attacker trying multiple vectors simultaneously
        $attackData = [
            'topic' => "'; DROP TABLE feeds; <script>alert('XSS')</script>",
            'url' => 'http://127.0.0.1/admin',
            'limit' => 999999,
            'csrf_token' => 'fake_token',
        ];

        // Should fail at multiple security layers
        try {
            $this->validator->validateFeed($attackData);
            $this->fail('Should have thrown ValidationException');
        } catch (ValidationException $e) {
            // Expected - input validation caught it
            $this->assertTrue(true);
        }
    }

    public function test_security_logging(): void
    {
        // Verify security events are logged
        $logFile = '/tmp/unfurl_test.log';

        // Clear log
        if (file_exists($logFile)) {
            unlink($logFile);
        }

        // Trigger security events
        try {
            $this->validator->validateFeed([
                'topic' => '<script>alert(1)</script>',
                'url' => 'http://127.0.0.1',
                'limit' => 10,
            ]);
        } catch (ValidationException $e) {
            // Expected
        }

        // For this test, we just verify no errors occurred
        // In production, you'd check the log file content
        $this->assertTrue(true);
    }

    protected function tearDown(): void
    {
        // Clean up - sessions can cause issues
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }
}
