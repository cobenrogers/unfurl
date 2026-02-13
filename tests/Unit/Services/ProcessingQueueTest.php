<?php

declare(strict_types=1);

namespace Unfurl\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use Unfurl\Services\ProcessingQueue;
use Unfurl\Repositories\ArticleRepository;
use Unfurl\Core\Logger;

/**
 * Test-Driven Development for ProcessingQueue
 *
 * These tests are written FIRST before implementing ProcessingQueue.
 * They define the expected behavior and API for the queue system.
 */
class ProcessingQueueTest extends TestCase
{
    private ProcessingQueue $queue;
    private ArticleRepository $articleRepo;
    private Logger $logger;
    private \Unfurl\Core\TimezoneHelper $timezone;

    protected function setUp(): void
    {
        // Create mocks for dependencies
        $this->articleRepo = $this->createMock(ArticleRepository::class);
        $this->logger = $this->createMock(Logger::class);
        $this->timezone = $this->createMock(\Unfurl\Core\TimezoneHelper::class);

        // Create ProcessingQueue instance
        $this->queue = new ProcessingQueue($this->articleRepo, $this->logger, $this->timezone);
    }

    /**
     * Test 1: Enqueue an article for processing
     */
    public function testEnqueueArticle(): void
    {
        $articleId = 123;
        $error = 'Network timeout';
        $retryCount = 0;

        // Expect article repository to be updated with retry information
        $this->articleRepo->expects($this->once())
            ->method('update')
            ->with(
                $articleId,
                $this->callback(function ($data) {
                    return $data['status'] === 'failed'
                        && $data['retry_count'] === 0
                        && isset($data['next_retry_at'])
                        && isset($data['last_error']);
                })
            )
            ->willReturn(true);

        // Expect logging
        $this->logger->expects($this->once())
            ->method('warning')
            ->with(
                $this->stringContains('Article queued for retry'),
                $this->arrayHasKey('article_id')
            );

        // Enqueue the article
        $result = $this->queue->enqueue($articleId, $error, $retryCount);

        $this->assertTrue($result);
    }

    /**
     * Test 2: Exponential backoff calculation (60s, 120s, 240s)
     */
    public function testExponentialBackoffCalculation(): void
    {
        // Test backoff delays for attempts 1, 2, 3
        $attempt1Delay = $this->queue->calculateBackoff(0);
        $attempt2Delay = $this->queue->calculateBackoff(1);
        $attempt3Delay = $this->queue->calculateBackoff(2);

        // Verify exponential pattern: 60s, 120s, 240s
        // Allow for jitter (0-10 seconds)
        $this->assertGreaterThanOrEqual(60, $attempt1Delay);
        $this->assertLessThanOrEqual(70, $attempt1Delay);

        $this->assertGreaterThanOrEqual(120, $attempt2Delay);
        $this->assertLessThanOrEqual(130, $attempt2Delay);

        $this->assertGreaterThanOrEqual(240, $attempt3Delay);
        $this->assertLessThanOrEqual(250, $attempt3Delay);
    }

    /**
     * Test 3: Permanent failure after 3 attempts
     */
    public function testPermanentFailureAfterMaxRetries(): void
    {
        $articleId = 456;
        $error = 'Network timeout';
        $retryCount = 3; // Max attempts reached

        // Expect article to be marked as permanently failed (no next_retry_at)
        $this->articleRepo->expects($this->once())
            ->method('update')
            ->with(
                $articleId,
                $this->callback(function ($data) {
                    return $data['status'] === 'failed'
                        && $data['next_retry_at'] === null
                        && isset($data['last_error']);
                })
            )
            ->willReturn(true);

        // Expect error logging with 'permanently failed' message
        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                $this->stringContains('permanently failed'),
                $this->callback(function ($context) {
                    return isset($context['article_id'])
                        && isset($context['reason'])
                        && $context['reason'] === 'max retries exceeded';
                })
            );

        // Enqueue should fail (return false) when max retries exceeded
        $result = $this->queue->enqueue($articleId, $error, $retryCount);

        $this->assertFalse($result);
    }

    /**
     * Test 4: Distinguish retryable vs permanent failures
     */
    public function testRetryableVsPermanentFailures(): void
    {
        // Retryable errors
        $this->assertTrue($this->queue->isRetryable('Network timeout'));
        $this->assertTrue($this->queue->isRetryable('Connection timeout'));
        $this->assertTrue($this->queue->isRetryable('HTTP 429: Rate Limited'));
        $this->assertTrue($this->queue->isRetryable('HTTP 502: Bad Gateway'));
        $this->assertTrue($this->queue->isRetryable('HTTP 503: Service Unavailable'));
        $this->assertTrue($this->queue->isRetryable('HTTP 504: Gateway Timeout'));
        $this->assertTrue($this->queue->isRetryable('DNS resolution failed'));

        // Permanent errors
        $this->assertFalse($this->queue->isRetryable('HTTP 404: Not Found'));
        $this->assertFalse($this->queue->isRetryable('HTTP 403: Forbidden'));
        $this->assertFalse($this->queue->isRetryable('Invalid URL format'));
        $this->assertFalse($this->queue->isRetryable('SSRF validation failed'));
        $this->assertFalse($this->queue->isRetryable('No parseable content'));
    }

    /**
     * Test 5: Get next retry time calculation
     */
    public function testNextRetryTimeCalculation(): void
    {
        $retryCount = 1;
        $backoffSeconds = 120; // 2nd attempt

        $nextRetryAt = $this->queue->calculateNextRetryTime($retryCount);

        // Should be approximately 120 seconds from now
        $expectedTime = time() + 120;
        $actualTime = strtotime($nextRetryAt);

        // Allow 15 second variance for jitter and execution time
        $this->assertEqualsWithDelta($expectedTime, $actualTime, 15);
    }

    /**
     * Test 6: Process pending retries
     */
    public function testProcessPendingRetries(): void
    {
        // Mock articles ready for retry
        $pendingArticles = [
            [
                'id' => 1,
                'retry_count' => 1,
                'google_news_url' => 'https://news.google.com/article1',
                'final_url' => null,
            ],
            [
                'id' => 2,
                'retry_count' => 2,
                'google_news_url' => 'https://news.google.com/article2',
                'final_url' => null,
            ],
        ];

        $this->articleRepo->expects($this->once())
            ->method('findPendingRetries')
            ->willReturn($pendingArticles);

        // Get pending articles
        $articles = $this->queue->getPendingRetries();

        $this->assertCount(2, $articles);
        $this->assertEquals(1, $articles[0]['id']);
        $this->assertEquals(2, $articles[1]['id']);
    }

    /**
     * Test 7: Mark article as successfully processed
     */
    public function testMarkAsComplete(): void
    {
        $articleId = 789;

        $this->articleRepo->expects($this->once())
            ->method('markAsProcessed')
            ->with($articleId)
            ->willReturn(true);

        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                $this->stringContains('successfully processed'),
                $this->arrayHasKey('article_id')
            );

        $result = $this->queue->markComplete($articleId);

        $this->assertTrue($result);
    }

    /**
     * Test 8: Mark article as failed (retryable)
     */
    public function testMarkAsFailedRetryable(): void
    {
        $articleId = 101;
        $error = 'Network timeout';
        $retryCount = 1;

        // Should enqueue for retry
        $this->articleRepo->expects($this->once())
            ->method('update')
            ->with(
                $articleId,
                $this->callback(function ($data) {
                    return $data['status'] === 'failed'
                        && isset($data['next_retry_at']);
                })
            )
            ->willReturn(true);

        $result = $this->queue->markFailed($articleId, $error, $retryCount);

        $this->assertTrue($result);
    }

    /**
     * Test 9: Mark article as failed (permanent)
     */
    public function testMarkAsFailedPermanent(): void
    {
        $articleId = 102;
        $error = 'HTTP 404: Not Found';
        $retryCount = 0;

        // Should mark as permanently failed (no retry)
        $this->articleRepo->expects($this->once())
            ->method('update')
            ->with(
                $articleId,
                $this->callback(function ($data) {
                    return $data['status'] === 'failed'
                        && $data['next_retry_at'] === null;
                })
            )
            ->willReturn(true);

        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                $this->stringContains('permanently failed'),
                $this->arrayHasKey('article_id')
            );

        $result = $this->queue->markFailed($articleId, $error, $retryCount);

        $this->assertTrue($result);
    }

    /**
     * Test 10: Increment retry count
     */
    public function testIncrementRetryCount(): void
    {
        $articleId = 999;

        $this->articleRepo->expects($this->once())
            ->method('incrementRetryCount')
            ->with($articleId)
            ->willReturn(true);

        $result = $this->queue->incrementRetryCount($articleId);

        $this->assertTrue($result);
    }

    /**
     * Test 11: Rate limiting protection
     */
    public function testRateLimiting(): void
    {
        // Initially, should be able to process (no previous process time set)
        $this->assertTrue($this->queue->canProcessNow());

        // After setting last process time to recent (within rate limit)
        $this->queue->setLastProcessTime(time() - 2); // 2 seconds ago (< 5 second limit)
        $this->assertFalse($this->queue->canProcessNow());

        // After setting last process time to past (beyond rate limit)
        $this->queue->setLastProcessTime(time() - 10); // 10 seconds ago (> 5 second limit)
        $this->assertTrue($this->queue->canProcessNow());
    }

    /**
     * Test 12: Backoff with jitter prevents thundering herd
     */
    public function testBackoffJitter(): void
    {
        $delays = [];

        // Generate multiple backoff delays for same retry count
        for ($i = 0; $i < 10; $i++) {
            $delays[] = $this->queue->calculateBackoff(1);
        }

        // Verify there's variation (jitter)
        $uniqueDelays = array_unique($delays);
        $this->assertGreaterThan(1, count($uniqueDelays), 'Jitter should create variation in delays');

        // All should be within expected range (120-130 seconds for attempt 2)
        foreach ($delays as $delay) {
            $this->assertGreaterThanOrEqual(120, $delay);
            $this->assertLessThanOrEqual(130, $delay);
        }
    }

    /**
     * Test 13: Error message storage
     */
    public function testErrorMessageStorage(): void
    {
        $articleId = 555;
        $error = 'Connection refused by remote server';
        $retryCount = 0;

        $this->articleRepo->expects($this->once())
            ->method('update')
            ->with(
                $articleId,
                $this->callback(function ($data) use ($error) {
                    return $data['last_error'] === $error;
                })
            )
            ->willReturn(true);

        $this->queue->enqueue($articleId, $error, $retryCount);
    }

    /**
     * Test 14: Handle edge case - retry count exactly at max
     */
    public function testRetryCountAtMaximum(): void
    {
        $articleId = 666;
        $error = 'Network error';
        $maxRetries = ProcessingQueue::MAX_RETRIES;

        $this->articleRepo->expects($this->once())
            ->method('update')
            ->with(
                $articleId,
                $this->callback(function ($data) {
                    return $data['next_retry_at'] === null;
                })
            )
            ->willReturn(true);

        $result = $this->queue->enqueue($articleId, $error, $maxRetries);
        $this->assertFalse($result);
    }

    /**
     * Test 15: Validate retry logic doesn't retry permanent failures
     */
    public function testPermanentFailuresNotQueued(): void
    {
        $articleId = 777;
        $permanentError = 'HTTP 403: Forbidden';
        $retryCount = 0;

        // Should NOT set next_retry_at for permanent failures
        $this->articleRepo->expects($this->once())
            ->method('update')
            ->with(
                $articleId,
                $this->callback(function ($data) {
                    return $data['status'] === 'failed'
                        && $data['next_retry_at'] === null;
                })
            )
            ->willReturn(true);

        $result = $this->queue->markFailed($articleId, $permanentError, $retryCount);
        $this->assertTrue($result);
    }
}
