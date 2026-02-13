<?php

declare(strict_types=1);

namespace Unfurl\Services;

use Unfurl\Repositories\ArticleRepository;
use Unfurl\Core\Logger;
use Unfurl\Core\TimezoneHelper;

/**
 * Processing Queue with Retry Logic
 *
 * Manages article processing queue with:
 * - Exponential backoff (60s, 120s, 240s)
 * - Retryable vs permanent failure classification
 * - Maximum 3 retry attempts
 * - Rate limiting protection
 *
 * Implementation follows requirements from Section 4.2.5
 */
class ProcessingQueue
{
    /**
     * Maximum retry attempts before permanent failure
     */
    public const MAX_RETRIES = 3;

    /**
     * Base backoff delay in seconds (60 seconds)
     */
    private const BASE_BACKOFF = 60;

    /**
     * Maximum jitter in seconds (0-10)
     */
    private const MAX_JITTER = 10;

    /**
     * Minimum delay between processing attempts (seconds)
     */
    private const RATE_LIMIT_DELAY = 5;

    /**
     * Retryable error patterns
     */
    private const RETRYABLE_PATTERNS = [
        'timeout',
        'connection',
        'network',
        'dns',
        'HTTP 429',
        'HTTP 502',
        'HTTP 503',
        'HTTP 504',
        'rate limit',
    ];

    /**
     * Permanent error patterns
     */
    private const PERMANENT_PATTERNS = [
        'HTTP 404',
        'HTTP 403',
        'Invalid URL',
        'SSRF',
        'parseable content',
    ];

    /**
     * Last process time for rate limiting
     */
    private int $lastProcessTime = 0;

    /**
     * @param ArticleRepository $articleRepo
     * @param Logger $logger
     * @param TimezoneHelper $timezone
     */
    public function __construct(
        private ArticleRepository $articleRepo,
        private Logger $logger,
        private TimezoneHelper $timezone
    ) {
    }

    /**
     * Enqueue an article for retry
     *
     * @param int $articleId Article ID
     * @param string $error Error message
     * @param int $retryCount Current retry count
     * @return bool True if enqueued, false if max retries exceeded
     */
    public function enqueue(int $articleId, string $error, int $retryCount): bool
    {
        // Check if max retries exceeded
        if ($retryCount >= self::MAX_RETRIES) {
            $this->markPermanentFailure($articleId, $error);
            return false;
        }

        // Calculate next retry time
        $backoffSeconds = $this->calculateBackoff($retryCount);
        $nextRetryAt = $this->calculateNextRetryTime($retryCount);

        // Update article with retry information
        $this->articleRepo->update($articleId, [
            'status' => 'failed',
            'retry_count' => $retryCount,
            'next_retry_at' => $nextRetryAt,
            'last_error' => $error,
        ]);

        // Log retry scheduling
        $this->logger->warning('Article queued for retry', [
            'category' => 'processing_queue',
            'article_id' => $articleId,
            'retry_count' => $retryCount,
            'next_retry_at' => $nextRetryAt,
            'backoff_seconds' => $backoffSeconds,
            'error' => $error,
        ]);

        return true;
    }

    /**
     * Calculate exponential backoff delay
     *
     * Formula: base * 2^retry_count + jitter
     * - Attempt 1: 60s (2^0 * 60)
     * - Attempt 2: 120s (2^1 * 60)
     * - Attempt 3: 240s (2^2 * 60)
     * - Plus random jitter (0-10s) to prevent thundering herd
     *
     * @param int $retryCount Current retry count
     * @return int Delay in seconds
     */
    public function calculateBackoff(int $retryCount): int
    {
        $baseDelay = self::BASE_BACKOFF * pow(2, $retryCount);
        $jitter = rand(0, self::MAX_JITTER);

        return $baseDelay + $jitter;
    }

    /**
     * Calculate next retry timestamp
     *
     * @param int $retryCount Current retry count
     * @return string MySQL datetime format in UTC
     */
    public function calculateNextRetryTime(int $retryCount): string
    {
        $backoffSeconds = $this->calculateBackoff($retryCount);
        $nextTime = time() + $backoffSeconds;
        return date('Y-m-d H:i:s', $nextTime);
    }

    /**
     * Determine if an error is retryable
     *
     * @param string $error Error message
     * @return bool True if retryable, false if permanent
     */
    public function isRetryable(string $error): bool
    {
        $errorLower = strtolower($error);

        // Check for permanent error patterns first
        foreach (self::PERMANENT_PATTERNS as $pattern) {
            if (stripos($error, $pattern) !== false) {
                return false;
            }
        }

        // Check for retryable patterns
        foreach (self::RETRYABLE_PATTERNS as $pattern) {
            if (stripos($error, $pattern) !== false) {
                return true;
            }
        }

        // Default to non-retryable for unknown errors
        return false;
    }

    /**
     * Get articles pending retry
     *
     * @return array Articles ready for retry
     */
    public function getPendingRetries(): array
    {
        return $this->articleRepo->findPendingRetries();
    }

    /**
     * Mark article as successfully processed
     *
     * @param int $articleId Article ID
     * @return bool Success status
     */
    public function markComplete(int $articleId): bool
    {
        $success = $this->articleRepo->markAsProcessed($articleId);

        if ($success) {
            $this->logger->info('Article successfully processed', [
                'category' => 'processing_queue',
                'article_id' => $articleId,
            ]);
        }

        return $success;
    }

    /**
     * Mark article as failed
     *
     * Determines if failure is retryable or permanent and handles accordingly.
     *
     * @param int $articleId Article ID
     * @param string $error Error message
     * @param int $retryCount Current retry count
     * @return bool Success status
     */
    public function markFailed(int $articleId, string $error, int $retryCount): bool
    {
        // Check if error is retryable
        if (!$this->isRetryable($error)) {
            return $this->markPermanentFailure($articleId, $error);
        }

        // Check if we can retry
        if ($retryCount >= self::MAX_RETRIES) {
            return $this->markPermanentFailure($articleId, $error);
        }

        // Enqueue for retry
        return $this->enqueue($articleId, $error, $retryCount);
    }

    /**
     * Mark article as permanently failed (no retry)
     *
     * @param int $articleId Article ID
     * @param string $error Error message
     * @return bool Success status
     */
    private function markPermanentFailure(int $articleId, string $error): bool
    {
        $success = $this->articleRepo->update($articleId, [
            'status' => 'failed',
            'next_retry_at' => null,
            'last_error' => $error,
        ]);

        if ($success) {
            $this->logger->error('Article permanently failed', [
                'category' => 'processing_queue',
                'article_id' => $articleId,
                'error' => $error,
                'reason' => $this->isRetryable($error) ? 'max retries exceeded' : 'permanent error',
            ]);
        }

        return $success;
    }

    /**
     * Increment retry count for an article
     *
     * @param int $articleId Article ID
     * @return bool Success status
     */
    public function incrementRetryCount(int $articleId): bool
    {
        return $this->articleRepo->incrementRetryCount($articleId);
    }

    /**
     * Check if processing can proceed (rate limiting)
     *
     * @return bool True if processing allowed
     */
    public function canProcessNow(): bool
    {
        $timeSinceLastProcess = time() - $this->lastProcessTime;
        return $timeSinceLastProcess >= self::RATE_LIMIT_DELAY;
    }

    /**
     * Set last process time (for testing and rate limiting)
     *
     * @param int $timestamp Unix timestamp
     * @return void
     */
    public function setLastProcessTime(int $timestamp): void
    {
        $this->lastProcessTime = $timestamp;
    }
}
