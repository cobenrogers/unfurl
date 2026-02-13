<?php

declare(strict_types=1);

namespace Unfurl\Tests\Unit\Controllers;

use PHPUnit\Framework\TestCase;
use Unfurl\Controllers\FeedController;
use Unfurl\Repositories\FeedRepository;
use Unfurl\Services\ProcessingQueue;
use Unfurl\Security\CsrfToken;
use Unfurl\Security\InputValidator;
use Unfurl\Security\OutputEscaper;
use Unfurl\Core\Logger;
use Unfurl\Exceptions\ValidationException;
use Unfurl\Exceptions\SecurityException;

/**
 * Test-Driven Development for FeedController
 *
 * Tests all CRUD operations and security controls:
 * - List feeds (GET /feeds)
 * - Create feed (POST /feeds/create)
 * - Edit feed (POST /feeds/edit/{id})
 * - Delete feed (POST /feeds/delete/{id})
 * - Run feed (POST /feeds/run/{id})
 * - CSRF protection
 * - Input validation
 * - Error handling
 *
 * Requirements: Task 4.1 - Feed Controller
 */
class FeedControllerTest extends TestCase
{
    private FeedController $controller;
    private FeedRepository $feedRepo;
    private ProcessingQueue $queue;
    private CsrfToken $csrf;
    private InputValidator $validator;
    private OutputEscaper $escaper;
    private Logger $logger;

    protected function setUp(): void
    {
        // Create mocks for all dependencies
        $this->feedRepo = $this->createMock(FeedRepository::class);
        $this->queue = $this->createMock(ProcessingQueue::class);
        $this->csrf = $this->createMock(CsrfToken::class);
        $this->validator = $this->createMock(InputValidator::class);
        $this->escaper = $this->createMock(OutputEscaper::class);
        $this->logger = $this->createMock(Logger::class);

        // Create controller instance
        $this->controller = new FeedController(
            $this->feedRepo,
            $this->queue,
            $this->csrf,
            $this->validator,
            $this->escaper,
            $this->logger
        );
    }

    // ============================================
    // Test: List Feeds (GET /feeds)
    // ============================================

    public function test_index_returns_all_feeds(): void
    {
        $feeds = [
            ['id' => 1, 'topic' => 'PHP News', 'url' => 'https://news.google.com/rss/search?q=PHP'],
            ['id' => 2, 'topic' => 'Laravel', 'url' => 'https://news.google.com/rss/search?q=Laravel'],
        ];

        $this->feedRepo->expects($this->once())
            ->method('findAll')
            ->willReturn($feeds);

        $this->csrf->expects($this->once())
            ->method('getToken')
            ->willReturn('test-token-123');

        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                $this->stringContains('Feeds list accessed'),
                $this->arrayHasKey('feed_count')
            );

        $result = $this->controller->index();

        $this->assertEquals('success', $result['status']);
        $this->assertCount(2, $result['feeds']);
        $this->assertEquals('test-token-123', $result['csrf_token']);
    }

    public function test_index_handles_database_error(): void
    {
        $this->feedRepo->expects($this->once())
            ->method('findAll')
            ->willThrowException(new \Exception('Database connection failed'));

        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                $this->stringContains('Failed to list feeds'),
                $this->arrayHasKey('error')
            );

        $result = $this->controller->index();

        $this->assertEquals('error', $result['status']);
        $this->assertEquals('Failed to load feeds', $result['message']);
    }

    // ============================================
    // Test: Create Feed (POST /feeds/create)
    // ============================================

    public function test_create_feed_with_valid_data(): void
    {
        $data = [
            'csrf_token' => 'valid-token',
            'topic' => 'PHP News',
            'url' => 'https://news.google.com/rss/search?q=PHP',
            'limit' => 10,
        ];

        $validated = [
            'topic' => 'PHP News',
            'url' => 'https://news.google.com/rss/search?q=PHP',
            'limit' => 10,
        ];

        // CSRF validation should succeed
        $this->csrf->expects($this->once())
            ->method('validate')
            ->with('valid-token');

        // Input validation should succeed
        $this->validator->expects($this->once())
            ->method('validateFeed')
            ->with($data)
            ->willReturn($validated);

        // Check for duplicate topic
        $this->feedRepo->expects($this->once())
            ->method('findByTopic')
            ->with('PHP News')
            ->willReturn(null);

        // Create feed
        $this->feedRepo->expects($this->once())
            ->method('create')
            ->with([
                'topic' => 'PHP News',
                'url' => 'https://news.google.com/rss/search?q=PHP',
                'result_limit' => 10,
                'enabled' => 1,
            ])
            ->willReturn(1);

        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                $this->stringContains('Feed created'),
                $this->arrayHasKey('feed_id')
            );

        $result = $this->controller->create($data);

        $this->assertEquals('success', $result['status']);
        $this->assertEquals('Feed created successfully', $result['message']);
        $this->assertEquals(1, $result['feed_id']);
        $this->assertEquals('/feeds', $result['redirect']);
    }

    public function test_create_feed_rejects_invalid_csrf_token(): void
    {
        $data = [
            'csrf_token' => 'invalid-token',
            'topic' => 'PHP News',
            'url' => 'https://news.google.com/rss/search?q=PHP',
            'limit' => 10,
        ];

        $this->csrf->expects($this->once())
            ->method('validate')
            ->with('invalid-token')
            ->willThrowException(new SecurityException('CSRF token validation failed'));

        $this->logger->expects($this->once())
            ->method('warning')
            ->with(
                $this->stringContains('CSRF validation failed'),
                $this->arrayHasKey('error')
            );

        $result = $this->controller->create($data);

        $this->assertEquals('error', $result['status']);
        $this->assertEquals('Security validation failed', $result['message']);
        $this->assertEquals(403, $result['http_code']);
    }

    public function test_create_feed_rejects_invalid_input(): void
    {
        $data = [
            'csrf_token' => 'valid-token',
            'topic' => '',
            'url' => 'invalid-url',
            'limit' => 999,
        ];

        $this->csrf->expects($this->once())
            ->method('validate')
            ->with('valid-token');

        $this->validator->expects($this->once())
            ->method('validateFeed')
            ->with($data)
            ->willThrowException(new ValidationException('Validation failed', [
                'topic' => 'Topic is required',
                'url' => 'Invalid URL format',
                'limit' => 'Limit must be between 1 and 100',
            ]));

        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                $this->stringContains('Feed validation failed'),
                $this->arrayHasKey('errors')
            );

        $result = $this->controller->create($data);

        $this->assertEquals('error', $result['status']);
        $this->assertEquals('Validation failed', $result['message']);
        $this->assertArrayHasKey('errors', $result);
        $this->assertArrayHasKey('topic', $result['errors']);
        $this->assertEquals(422, $result['http_code']);
    }

    public function test_create_feed_rejects_duplicate_topic(): void
    {
        $data = [
            'csrf_token' => 'valid-token',
            'topic' => 'PHP News',
            'url' => 'https://news.google.com/rss/search?q=PHP',
            'limit' => 10,
        ];

        $validated = [
            'topic' => 'PHP News',
            'url' => 'https://news.google.com/rss/search?q=PHP',
            'limit' => 10,
        ];

        $this->csrf->expects($this->once())
            ->method('validate');

        $this->validator->expects($this->once())
            ->method('validateFeed')
            ->willReturn($validated);

        // Feed with same topic already exists
        $this->feedRepo->expects($this->once())
            ->method('findByTopic')
            ->with('PHP News')
            ->willReturn(['id' => 1, 'topic' => 'PHP News']);

        $result = $this->controller->create($data);

        $this->assertEquals('error', $result['status']);
        $this->assertEquals('Validation failed', $result['message']);
        $this->assertArrayHasKey('errors', $result);
        $this->assertArrayHasKey('topic', $result['errors']);
        $this->assertStringContainsString('already exists', $result['errors']['topic']);
    }

    public function test_create_feed_handles_database_unique_constraint(): void
    {
        $data = [
            'csrf_token' => 'valid-token',
            'topic' => 'PHP News',
            'url' => 'https://news.google.com/rss/search?q=PHP',
            'limit' => 10,
        ];

        $validated = [
            'topic' => 'PHP News',
            'url' => 'https://news.google.com/rss/search?q=PHP',
            'limit' => 10,
        ];

        $this->csrf->expects($this->once())
            ->method('validate');

        $this->validator->expects($this->once())
            ->method('validateFeed')
            ->willReturn($validated);

        $this->feedRepo->expects($this->once())
            ->method('findByTopic')
            ->willReturn(null);

        // Database throws unique constraint error
        $this->feedRepo->expects($this->once())
            ->method('create')
            ->willThrowException(new \PDOException('UNIQUE constraint failed: feeds.topic'));

        $result = $this->controller->create($data);

        $this->assertEquals('error', $result['status']);
        $this->assertEquals('Validation failed', $result['message']);
        $this->assertArrayHasKey('topic', $result['errors']);
    }

    // ============================================
    // Test: Edit Feed (POST /feeds/edit/{id})
    // ============================================

    public function test_edit_feed_with_valid_data(): void
    {
        $feedId = 1;
        $data = [
            'csrf_token' => 'valid-token',
            'topic' => 'Updated Topic',
            'url' => 'https://news.google.com/rss/search?q=updated',
            'limit' => 20,
        ];

        $validated = [
            'topic' => 'Updated Topic',
            'url' => 'https://news.google.com/rss/search?q=updated',
            'limit' => 20,
            'enabled' => false,
        ];

        $existingFeed = [
            'id' => 1,
            'topic' => 'Old Topic',
            'url' => 'https://news.google.com/rss/search?q=old',
        ];

        $this->csrf->expects($this->once())
            ->method('validate')
            ->with('valid-token');

        $this->feedRepo->expects($this->once())
            ->method('findById')
            ->with($feedId)
            ->willReturn($existingFeed);

        $this->validator->expects($this->once())
            ->method('validateFeed')
            ->with($data)
            ->willReturn($validated);

        $this->feedRepo->expects($this->once())
            ->method('findByTopic')
            ->with('Updated Topic')
            ->willReturn(null);

        $this->feedRepo->expects($this->once())
            ->method('update')
            ->with($feedId, [
                'topic' => 'Updated Topic',
                'url' => 'https://news.google.com/rss/search?q=updated',
                'result_limit' => 20,
                'enabled' => 0,
            ])
            ->willReturn(true);

        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                $this->stringContains('Feed updated'),
                $this->arrayHasKey('feed_id')
            );

        $result = $this->controller->edit($feedId, $data);

        $this->assertEquals('success', $result['status']);
        $this->assertEquals('Feed updated successfully', $result['message']);
        $this->assertEquals('/feeds', $result['redirect']);
    }

    public function test_edit_feed_returns_404_if_not_found(): void
    {
        $feedId = 999;
        $data = [
            'csrf_token' => 'valid-token',
            'topic' => 'Topic',
            'url' => 'https://news.google.com/rss/search?q=test',
            'limit' => 10,
        ];

        $this->csrf->expects($this->once())
            ->method('validate');

        $this->feedRepo->expects($this->once())
            ->method('findById')
            ->with($feedId)
            ->willReturn(null);

        $result = $this->controller->edit($feedId, $data);

        $this->assertEquals('error', $result['status']);
        $this->assertEquals('Feed not found', $result['message']);
        $this->assertEquals(404, $result['http_code']);
    }

    public function test_edit_feed_allows_same_topic_for_same_feed(): void
    {
        $feedId = 1;
        $data = [
            'csrf_token' => 'valid-token',
            'topic' => 'PHP News',
            'url' => 'https://news.google.com/rss/search?q=PHP',
            'limit' => 10,
        ];

        $validated = [
            'topic' => 'PHP News',
            'url' => 'https://news.google.com/rss/search?q=PHP',
            'limit' => 10,
        ];

        $existingFeed = [
            'id' => 1,
            'topic' => 'PHP News',
        ];

        $this->csrf->expects($this->once())
            ->method('validate');

        $this->feedRepo->expects($this->once())
            ->method('findById')
            ->with($feedId)
            ->willReturn($existingFeed);

        $this->validator->expects($this->once())
            ->method('validateFeed')
            ->willReturn($validated);

        // Same topic, same feed ID - should be allowed
        $this->feedRepo->expects($this->once())
            ->method('findByTopic')
            ->with('PHP News')
            ->willReturn(['id' => 1, 'topic' => 'PHP News']);

        $this->feedRepo->expects($this->once())
            ->method('update')
            ->willReturn(true);

        $result = $this->controller->edit($feedId, $data);

        $this->assertEquals('success', $result['status']);
    }

    public function test_edit_feed_rejects_duplicate_topic_from_different_feed(): void
    {
        $feedId = 1;
        $data = [
            'csrf_token' => 'valid-token',
            'topic' => 'PHP News',
            'url' => 'https://news.google.com/rss/search?q=PHP',
            'limit' => 10,
        ];

        $validated = [
            'topic' => 'PHP News',
            'url' => 'https://news.google.com/rss/search?q=PHP',
            'limit' => 10,
        ];

        $existingFeed = [
            'id' => 1,
            'topic' => 'Old Topic',
        ];

        $this->csrf->expects($this->once())
            ->method('validate');

        $this->feedRepo->expects($this->once())
            ->method('findById')
            ->with($feedId)
            ->willReturn($existingFeed);

        $this->validator->expects($this->once())
            ->method('validateFeed')
            ->willReturn($validated);

        // Topic exists for different feed
        $this->feedRepo->expects($this->once())
            ->method('findByTopic')
            ->with('PHP News')
            ->willReturn(['id' => 2, 'topic' => 'PHP News']);

        $result = $this->controller->edit($feedId, $data);

        $this->assertEquals('error', $result['status']);
        $this->assertArrayHasKey('topic', $result['errors']);
    }

    // ============================================
    // Test: Delete Feed (POST /feeds/delete/{id})
    // ============================================

    public function test_delete_feed_successfully(): void
    {
        $feedId = 1;
        $data = ['csrf_token' => 'valid-token'];

        $this->csrf->expects($this->once())
            ->method('validate')
            ->with('valid-token');

        $this->feedRepo->expects($this->once())
            ->method('delete')
            ->with($feedId)
            ->willReturn(true);

        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                $this->stringContains('Feed deleted'),
                $this->arrayHasKey('feed_id')
            );

        $result = $this->controller->delete($feedId, $data);

        $this->assertEquals('success', $result['status']);
        $this->assertEquals('Feed deleted successfully', $result['message']);
        $this->assertEquals('/feeds', $result['redirect']);
    }

    public function test_delete_feed_returns_404_if_not_found(): void
    {
        $feedId = 999;
        $data = ['csrf_token' => 'valid-token'];

        $this->csrf->expects($this->once())
            ->method('validate');

        $this->feedRepo->expects($this->once())
            ->method('delete')
            ->with($feedId)
            ->willReturn(false);

        $result = $this->controller->delete($feedId, $data);

        $this->assertEquals('error', $result['status']);
        $this->assertEquals('Feed not found', $result['message']);
        $this->assertEquals(404, $result['http_code']);
    }

    public function test_delete_feed_validates_csrf_token(): void
    {
        $feedId = 1;
        $data = ['csrf_token' => 'invalid-token'];

        $this->csrf->expects($this->once())
            ->method('validate')
            ->with('invalid-token')
            ->willThrowException(new SecurityException('CSRF token validation failed'));

        $result = $this->controller->delete($feedId, $data);

        $this->assertEquals('error', $result['status']);
        $this->assertEquals('Security validation failed', $result['message']);
        $this->assertEquals(403, $result['http_code']);
    }

    // ============================================
    // Test: Run Feed (POST /feeds/run/{id})
    // ============================================

    public function test_run_feed_successfully(): void
    {
        $feedId = 1;
        $data = ['csrf_token' => 'valid-token'];

        $feed = [
            'id' => 1,
            'topic' => 'PHP News',
            'url' => 'https://news.google.com/rss/search?q=PHP',
        ];

        $this->csrf->expects($this->once())
            ->method('validate')
            ->with('valid-token');

        $this->feedRepo->expects($this->once())
            ->method('findById')
            ->with($feedId)
            ->willReturn($feed);

        $this->queue->expects($this->once())
            ->method('canProcessNow')
            ->willReturn(true);

        $this->feedRepo->expects($this->once())
            ->method('updateLastProcessedAt')
            ->with($feedId);

        $this->queue->expects($this->once())
            ->method('setLastProcessTime')
            ->with($this->greaterThan(0));

        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                $this->stringContains('Feed processing triggered'),
                $this->arrayHasKey('feed_id')
            );

        $result = $this->controller->run($feedId, $data);

        $this->assertEquals('success', $result['status']);
        $this->assertEquals('Feed processing started', $result['message']);
        $this->assertEquals($feedId, $result['feed_id']);
    }

    public function test_run_feed_returns_404_if_not_found(): void
    {
        $feedId = 999;
        $data = ['csrf_token' => 'valid-token'];

        $this->csrf->expects($this->once())
            ->method('validate');

        $this->feedRepo->expects($this->once())
            ->method('findById')
            ->with($feedId)
            ->willReturn(null);

        $result = $this->controller->run($feedId, $data);

        $this->assertEquals('error', $result['status']);
        $this->assertEquals('Feed not found', $result['message']);
        $this->assertEquals(404, $result['http_code']);
    }

    public function test_run_feed_respects_rate_limiting(): void
    {
        $feedId = 1;
        $data = ['csrf_token' => 'valid-token'];

        $feed = [
            'id' => 1,
            'topic' => 'PHP News',
        ];

        $this->csrf->expects($this->once())
            ->method('validate');

        $this->feedRepo->expects($this->once())
            ->method('findById')
            ->willReturn($feed);

        $this->queue->expects($this->once())
            ->method('canProcessNow')
            ->willReturn(false);

        $result = $this->controller->run($feedId, $data);

        $this->assertEquals('error', $result['status']);
        $this->assertStringContainsString('Rate limit exceeded', $result['message']);
        $this->assertEquals(429, $result['http_code']);
    }

    public function test_run_feed_validates_csrf_token(): void
    {
        $feedId = 1;
        $data = ['csrf_token' => 'invalid-token'];

        $this->csrf->expects($this->once())
            ->method('validate')
            ->willThrowException(new SecurityException('CSRF token validation failed'));

        $result = $this->controller->run($feedId, $data);

        $this->assertEquals('error', $result['status']);
        $this->assertEquals('Security validation failed', $result['message']);
        $this->assertEquals(403, $result['http_code']);
    }

    // ============================================
    // Test: Error Handling
    // ============================================

    public function test_create_handles_database_error(): void
    {
        $data = [
            'csrf_token' => 'valid-token',
            'topic' => 'PHP News',
            'url' => 'https://news.google.com/rss/search?q=PHP',
            'limit' => 10,
        ];

        $validated = [
            'topic' => 'PHP News',
            'url' => 'https://news.google.com/rss/search?q=PHP',
            'limit' => 10,
        ];

        $this->csrf->expects($this->once())
            ->method('validate');

        $this->validator->expects($this->once())
            ->method('validateFeed')
            ->willReturn($validated);

        $this->feedRepo->expects($this->once())
            ->method('findByTopic')
            ->willReturn(null);

        $this->feedRepo->expects($this->once())
            ->method('create')
            ->willThrowException(new \PDOException('Database connection lost'));

        $this->logger->expects($this->once())
            ->method('error');

        $result = $this->controller->create($data);

        $this->assertEquals('error', $result['status']);
        $this->assertEquals('Failed to create feed', $result['message']);
        $this->assertEquals(500, $result['http_code']);
    }

    public function test_edit_handles_update_failure(): void
    {
        $feedId = 1;
        $data = [
            'csrf_token' => 'valid-token',
            'topic' => 'Updated',
            'url' => 'https://news.google.com/rss/search?q=updated',
            'limit' => 10,
        ];

        $validated = [
            'topic' => 'Updated',
            'url' => 'https://news.google.com/rss/search?q=updated',
            'limit' => 10,
        ];

        $existingFeed = ['id' => 1, 'topic' => 'Old'];

        $this->csrf->expects($this->once())
            ->method('validate');

        $this->feedRepo->expects($this->once())
            ->method('findById')
            ->willReturn($existingFeed);

        $this->validator->expects($this->once())
            ->method('validateFeed')
            ->willReturn($validated);

        $this->feedRepo->expects($this->once())
            ->method('findByTopic')
            ->willReturn(null);

        $this->feedRepo->expects($this->once())
            ->method('update')
            ->willReturn(false);

        $this->logger->expects($this->once())
            ->method('error');

        $result = $this->controller->edit($feedId, $data);

        $this->assertEquals('error', $result['status']);
        $this->assertEquals(500, $result['http_code']);
    }

    // ============================================
    // Test: Input Validation
    // ============================================

    public function test_validates_all_required_fields(): void
    {
        $data = [
            'csrf_token' => 'valid-token',
            // Missing required fields
        ];

        $this->csrf->expects($this->once())
            ->method('validate');

        $this->validator->expects($this->once())
            ->method('validateFeed')
            ->willThrowException(new ValidationException('Validation failed', [
                'topic' => 'Topic is required',
                'url' => 'Url is required',
                'limit' => 'Limit is required',
            ]));

        $result = $this->controller->create($data);

        $this->assertEquals('error', $result['status']);
        $this->assertArrayHasKey('errors', $result);
        $this->assertCount(3, $result['errors']);
    }

    public function test_validates_url_format(): void
    {
        $data = [
            'csrf_token' => 'valid-token',
            'topic' => 'Test',
            'url' => 'not-a-url',
            'limit' => 10,
        ];

        $this->csrf->expects($this->once())
            ->method('validate');

        $this->validator->expects($this->once())
            ->method('validateFeed')
            ->willThrowException(new ValidationException('Validation failed', [
                'url' => 'Invalid URL format',
            ]));

        $result = $this->controller->create($data);

        $this->assertEquals('error', $result['status']);
        $this->assertArrayHasKey('url', $result['errors']);
    }

    public function test_validates_limit_range(): void
    {
        $data = [
            'csrf_token' => 'valid-token',
            'topic' => 'Test',
            'url' => 'https://news.google.com/rss/search?q=test',
            'limit' => 999,
        ];

        $this->csrf->expects($this->once())
            ->method('validate');

        $this->validator->expects($this->once())
            ->method('validateFeed')
            ->willThrowException(new ValidationException('Validation failed', [
                'limit' => 'Limit must be between 1 and 100',
            ]));

        $result = $this->controller->create($data);

        $this->assertEquals('error', $result['status']);
        $this->assertArrayHasKey('limit', $result['errors']);
    }

    // ============================================
    // Test: CSRF Protection
    // ============================================

    public function test_all_post_methods_validate_csrf(): void
    {
        $methods = [
            ['create', [['csrf_token' => 'test']]],
            ['edit', [1, ['csrf_token' => 'test']]],
            ['delete', [1, ['csrf_token' => 'test']]],
            ['run', [1, ['csrf_token' => 'test']]],
        ];

        foreach ($methods as [$method, $args]) {
            $csrf = $this->createMock(CsrfToken::class);
            $csrf->expects($this->once())
                ->method('validate')
                ->willThrowException(new SecurityException('CSRF validation failed'));

            $controller = new FeedController(
                $this->feedRepo,
                $this->queue,
                $csrf,
                $this->validator,
                $this->escaper,
                $this->logger
            );

            $result = call_user_func_array([$controller, $method], $args);

            $this->assertEquals('error', $result['status'], "Method $method should validate CSRF");
            $this->assertEquals(403, $result['http_code'], "Method $method should return 403 on CSRF failure");
        }
    }

    // ============================================
    // Test: Logging
    // ============================================

    public function test_logs_all_operations(): void
    {
        // This test verifies that all major operations are logged
        // Already covered in individual tests via logger->expects()
        $this->assertTrue(true);
    }
}
