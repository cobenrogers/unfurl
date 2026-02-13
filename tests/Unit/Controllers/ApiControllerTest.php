<?php

declare(strict_types=1);

namespace Unfurl\Tests\Unit\Controllers;

use PHPUnit\Framework\TestCase;
use Unfurl\Controllers\ApiController;
use Unfurl\Core\Logger;
use Unfurl\Repositories\ApiKeyRepository;
use Unfurl\Repositories\FeedRepository;
use Unfurl\Repositories\ArticleRepository;
use Unfurl\Services\UnfurlService;
use Unfurl\Services\GoogleNews\UrlDecoder;
use Unfurl\Services\ArticleExtractor;
use Unfurl\Services\ProcessingQueue;
use Unfurl\Exceptions\SecurityException;
use Unfurl\Exceptions\UrlDecodeException;

/**
 * Unit tests for ApiController
 *
 * Tests:
 * - Valid API key authentication
 * - Invalid API key rejection
 * - Rate limiting enforcement
 * - Process enabled feeds
 * - Handle processing errors
 * - Health check response
 * - JSON response format
 */
class ApiControllerTest extends TestCase
{
    private ApiKeyRepository $apiKeyRepo;
    private FeedRepository $feedRepo;
    private ArticleRepository $articleRepo;
    private UnfurlService $unfurlService;
    private UrlDecoder $urlDecoder;
    private ArticleExtractor $extractor;
    private ProcessingQueue $queue;
    private Logger $logger;
    private ApiController $controller;

    protected function setUp(): void
    {
        // Define constant to prevent exit() during tests
        if (!defined('PHPUNIT_RUNNING')) {
            define('PHPUNIT_RUNNING', true);
        }

        // Reset rate limit tracker between tests
        $reflection = new \ReflectionClass(ApiController::class);
        $property = $reflection->getProperty('rateLimitTracker');
        $property->setAccessible(true);
        $property->setValue([]);

        // Create mocks
        $this->apiKeyRepo = $this->createMock(ApiKeyRepository::class);
        $this->feedRepo = $this->createMock(FeedRepository::class);
        $this->articleRepo = $this->createMock(ArticleRepository::class);
        $this->unfurlService = $this->createMock(UnfurlService::class);
        $this->urlDecoder = $this->createMock(UrlDecoder::class);
        $this->extractor = $this->createMock(ArticleExtractor::class);
        $this->queue = $this->createMock(ProcessingQueue::class);
        $this->logger = $this->createMock(Logger::class);

        $this->controller = new ApiController(
            $this->apiKeyRepo,
            $this->feedRepo,
            $this->articleRepo,
            $this->unfurlService,
            $this->urlDecoder,
            $this->extractor,
            $this->queue,
            $this->logger,
            'php'
        );
    }

    protected function tearDown(): void
    {
        // Clear output buffer (may have multiple levels)
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        // Reset HTTP response code
        http_response_code(200);
    }

    /**
     * Test: Valid API key authentication
     */
    public function testValidApiKeyAuthentication(): void
    {
        $_SERVER['HTTP_X_API_KEY'] = 'valid-api-key-12345';

        $apiKeyData = [
            'id' => 1,
            'key_name' => 'Test Key',
            'key_value' => 'valid-api-key-12345',
            'enabled' => 1,
        ];

        $this->apiKeyRepo->expects($this->once())
            ->method('findByKeyValue')
            ->with('valid-api-key-12345')
            ->willReturn($apiKeyData);

        $this->apiKeyRepo->expects($this->once())
            ->method('updateLastUsedAt')
            ->with(1);

        $this->feedRepo->expects($this->once())
            ->method('findEnabled')
            ->willReturn([]);

        $this->logger->expects($this->once())
            ->method('info')
            ->with('API key authenticated', $this->anything());

        ob_start();
        $this->controller->processFeeds();
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertTrue($response['success']);
        $this->assertEquals(0, $response['feeds_processed']);
        $this->assertArrayHasKey('timestamp', $response);
    }

    /**
     * Test: Invalid API key rejection
     */
    public function testInvalidApiKeyRejection(): void
    {
        $_SERVER['HTTP_X_API_KEY'] = 'invalid-api-key';

        $this->apiKeyRepo->expects($this->once())
            ->method('findByKeyValue')
            ->with('invalid-api-key')
            ->willReturn(null);

        $this->logger->expects($this->once())
            ->method('warning')
            ->with('Invalid API key attempted', $this->anything());

        ob_start();
        $this->controller->processFeeds();
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertFalse($response['success']);
        $this->assertEquals('Invalid API key', $response['error']);
        $this->assertEquals(401, http_response_code());
    }

    /**
     * Test: Missing API key header
     */
    public function testMissingApiKeyHeader(): void
    {
        unset($_SERVER['HTTP_X_API_KEY']);

        ob_start();
        $this->controller->processFeeds();
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertFalse($response['success']);
        $this->assertArrayHasKey('error', $response);
    }

    /**
     * Test: Disabled API key rejection
     */
    public function testDisabledApiKeyRejection(): void
    {
        $_SERVER['HTTP_X_API_KEY'] = 'disabled-key';

        $apiKeyData = [
            'id' => 2,
            'key_name' => 'Disabled Key',
            'key_value' => 'disabled-key',
            'enabled' => 0,
        ];

        $this->apiKeyRepo->expects($this->once())
            ->method('findByKeyValue')
            ->with('disabled-key')
            ->willReturn($apiKeyData);

        $this->logger->expects($this->once())
            ->method('warning')
            ->with('Disabled API key attempted', $this->anything());

        ob_start();
        $this->controller->processFeeds();
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertFalse($response['success']);
        $this->assertEquals('API key is disabled', $response['error']);
        $this->assertEquals(403, http_response_code());
    }

    /**
     * Test: Rate limiting enforcement
     */
    public function testRateLimitingEnforcement(): void
    {
        $_SERVER['HTTP_X_API_KEY'] = 'valid-api-key';

        $apiKeyData = [
            'id' => 1,
            'key_name' => 'Test Key',
            'key_value' => 'valid-api-key',
            'enabled' => 1,
        ];

        $this->apiKeyRepo->method('findByKeyValue')
            ->willReturn($apiKeyData);

        $this->apiKeyRepo->method('updateLastUsedAt');

        $this->feedRepo->method('findEnabled')
            ->willReturn([]);

        // Make 60 requests (should all succeed)
        for ($i = 0; $i < 60; $i++) {
            ob_start();
            $this->controller->processFeeds();
            $output = ob_get_clean();
            $response = json_decode($output, true);
            $this->assertTrue($response['success'], "Request $i should succeed");
        }

        // 61st request should be rate limited
        $this->logger->expects($this->once())
            ->method('warning')
            ->with('Rate limit exceeded', $this->anything());

        ob_start();
        $this->controller->processFeeds();
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('Rate limit exceeded', $response['error']);
        $this->assertEquals(429, http_response_code());
    }

    /**
     * Test: Process enabled feeds
     */
    public function testProcessEnabledFeeds(): void
    {
        $_SERVER['HTTP_X_API_KEY'] = 'valid-api-key';

        $apiKeyData = [
            'id' => 1,
            'key_name' => 'Test Key',
            'key_value' => 'valid-api-key',
            'enabled' => 1,
        ];

        $feeds = [
            [
                'id' => 1,
                'topic' => 'Technology',
                'url' => 'https://news.google.com/rss/search?q=technology',
                'result_limit' => 10,
            ],
        ];

        $this->apiKeyRepo->method('findByKeyValue')
            ->willReturn($apiKeyData);

        $this->apiKeyRepo->method('updateLastUsedAt');

        $this->feedRepo->expects($this->once())
            ->method('findEnabled')
            ->willReturn($feeds);

        // The feed processing info will be logged (API key auth + Processing feed)
        $this->logger->expects($this->atLeastOnce())
            ->method('info');

        ob_start();
        $this->controller->processFeeds();
        $output = ob_get_clean();

        $response = json_decode($output, true);

        // Should return success response
        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('feeds_processed', $response);
        $this->assertArrayHasKey('articles_created', $response);
        $this->assertArrayHasKey('articles_failed', $response);
    }

    /**
     * Test: Handle processing errors gracefully
     */
    public function testHandleProcessingErrors(): void
    {
        $_SERVER['HTTP_X_API_KEY'] = 'valid-api-key';

        $apiKeyData = [
            'id' => 1,
            'key_name' => 'Test Key',
            'key_value' => 'valid-api-key',
            'enabled' => 1,
        ];

        $this->apiKeyRepo->method('findByKeyValue')
            ->willReturn($apiKeyData);

        $this->apiKeyRepo->method('updateLastUsedAt');

        // Simulate database error when fetching feeds
        $this->feedRepo->expects($this->once())
            ->method('findEnabled')
            ->willThrowException(new \Exception('Database connection failed'));

        $this->logger->expects($this->once())
            ->method('error')
            ->with('API error', $this->anything());

        ob_start();
        $this->controller->processFeeds();
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertFalse($response['success']);
        $this->assertArrayHasKey('error', $response);
        $this->assertEquals(500, http_response_code());
    }

    /**
     * Test: Health check response (success)
     */
    public function testHealthCheckSuccess(): void
    {
        $this->feedRepo->expects($this->once())
            ->method('findAll')
            ->willReturn([]);

        ob_start();
        $this->controller->healthCheck();
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertEquals('ok', $response['status']);
        $this->assertArrayHasKey('timestamp', $response);
        $this->assertEquals(200, http_response_code());
    }

    /**
     * Test: Health check response (failure)
     */
    public function testHealthCheckFailure(): void
    {
        $this->feedRepo->expects($this->once())
            ->method('findAll')
            ->willThrowException(new \Exception('Database unavailable'));

        $this->logger->expects($this->once())
            ->method('error')
            ->with('Health check failed', $this->anything());

        ob_start();
        $this->controller->healthCheck();
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertEquals('error', $response['status']);
        $this->assertArrayHasKey('timestamp', $response);
        $this->assertEquals(503, http_response_code());
    }

    /**
     * Test: JSON response format
     */
    public function testJsonResponseFormat(): void
    {
        $_SERVER['HTTP_X_API_KEY'] = 'valid-api-key';

        $apiKeyData = [
            'id' => 1,
            'key_name' => 'Test Key',
            'key_value' => 'valid-api-key',
            'enabled' => 1,
        ];

        $this->apiKeyRepo->method('findByKeyValue')
            ->willReturn($apiKeyData);

        $this->apiKeyRepo->method('updateLastUsedAt');

        $this->feedRepo->method('findEnabled')
            ->willReturn([]);

        ob_start();
        $this->controller->processFeeds();
        $output = ob_get_clean();

        // Verify valid JSON
        $response = json_decode($output, true);
        $this->assertIsArray($response);

        // Verify required fields
        $this->assertArrayHasKey('success', $response);
        $this->assertArrayHasKey('feeds_processed', $response);
        $this->assertArrayHasKey('articles_created', $response);
        $this->assertArrayHasKey('articles_failed', $response);
        $this->assertArrayHasKey('timestamp', $response);

        // Verify timestamp format (ISO 8601)
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/',
            $response['timestamp']
        );

        // Verify JSON is properly formatted (no unescaped slashes)
        $this->assertJson($output);
    }

    /**
     * Test: Rate limit window resets after time
     */
    public function testRateLimitWindowReset(): void
    {
        $_SERVER['HTTP_X_API_KEY'] = 'valid-api-key';

        $apiKeyData = [
            'id' => 1,
            'key_name' => 'Test Key',
            'key_value' => 'valid-api-key',
            'enabled' => 1,
        ];

        $this->apiKeyRepo->method('findByKeyValue')
            ->willReturn($apiKeyData);

        $this->apiKeyRepo->method('updateLastUsedAt');

        $this->feedRepo->method('findEnabled')
            ->willReturn([]);

        // Access the static rate limit tracker using reflection
        $reflection = new \ReflectionClass(ApiController::class);
        $property = $reflection->getProperty('rateLimitTracker');
        $property->setAccessible(true);

        // Add 60 timestamps that are 61+ seconds old (outside window)
        $oldTimestamps = array_fill(0, 60, time() - 61);
        $property->setValue([1 => $oldTimestamps]);

        // This request should succeed because old timestamps are outside window
        ob_start();
        $this->controller->processFeeds();
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertTrue($response['success'], 'Request should succeed after window reset');
    }

    /**
     * Test: Multiple API keys have separate rate limits
     */
    public function testSeparateRateLimitsPerApiKey(): void
    {
        $this->apiKeyRepo->method('updateLastUsedAt');
        $this->feedRepo->method('findEnabled')->willReturn([]);

        // API Key 1
        $_SERVER['HTTP_X_API_KEY'] = 'key-1';
        $this->apiKeyRepo->method('findByKeyValue')
            ->willReturnCallback(function ($key) {
                return [
                    'id' => $key === 'key-1' ? 1 : 2,
                    'key_name' => $key,
                    'key_value' => $key,
                    'enabled' => 1,
                ];
            });

        // Make 60 requests with key-1
        for ($i = 0; $i < 60; $i++) {
            ob_start();
            $this->controller->processFeeds();
            ob_get_clean();
        }

        // API Key 2 should still have full rate limit available
        $_SERVER['HTTP_X_API_KEY'] = 'key-2';

        ob_start();
        $this->controller->processFeeds();
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertTrue($response['success'], 'Different API key should have separate rate limit');
    }

    /**
     * Test: Error handling without exposing internals
     */
    public function testErrorHandlingWithoutExposingInternals(): void
    {
        $_SERVER['HTTP_X_API_KEY'] = 'valid-api-key';

        $apiKeyData = [
            'id' => 1,
            'key_name' => 'Test Key',
            'key_value' => 'valid-api-key',
            'enabled' => 1,
        ];

        $this->apiKeyRepo->method('findByKeyValue')
            ->willReturn($apiKeyData);

        $this->apiKeyRepo->method('updateLastUsedAt');

        // Simulate internal error with sensitive information
        $this->feedRepo->method('findEnabled')
            ->willThrowException(new \Exception('Database password is: secret123'));

        ob_start();
        $this->controller->processFeeds();
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertFalse($response['success']);
        // Should NOT contain the internal error message
        $this->assertStringNotContainsString('secret123', $response['error']);
        // Should have generic error message
        $this->assertEquals('An error occurred while processing your request', $response['error']);
    }
}
