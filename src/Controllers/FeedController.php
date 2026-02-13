<?php

declare(strict_types=1);

namespace Unfurl\Controllers;

use Unfurl\Repositories\FeedRepository;
use Unfurl\Services\ProcessingQueue;
use Unfurl\Security\CsrfToken;
use Unfurl\Security\InputValidator;
use Unfurl\Security\OutputEscaper;
use Unfurl\Core\Logger;
use Unfurl\Exceptions\ValidationException;
use Unfurl\Exceptions\SecurityException;

/**
 * Feed Controller
 *
 * Handles CRUD operations for RSS feeds with security controls:
 * - CSRF protection on all POST requests
 * - Input validation
 * - XSS prevention via output escaping
 * - SQL injection prevention (via FeedRepository prepared statements)
 *
 * Requirements: Task 4.1 - Feed Controller
 */
class FeedController
{
    public function __construct(
        private FeedRepository $feedRepo,
        private ProcessingQueue $queue,
        private CsrfToken $csrf,
        private InputValidator $validator,
        private OutputEscaper $escaper,
        private Logger $logger
    ) {
    }

    /**
     * List all feeds with pagination
     *
     * GET /feeds
     *
     * @return array Response data
     */
    public function index(): array
    {
        try {
            $feeds = $this->feedRepo->findAll();

            $this->logger->info('Feeds list accessed', [
                'category' => 'feed_controller',
                'feed_count' => count($feeds),
            ]);

            return [
                'status' => 'success',
                'feeds' => $feeds,
                'csrf_token' => $this->csrf->getToken(),
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to list feeds', [
                'category' => 'feed_controller',
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 'error',
                'message' => 'Failed to load feeds',
            ];
        }
    }

    /**
     * Create a new feed
     *
     * POST /feeds/create
     *
     * @param array $data Feed data (topic, url, limit)
     * @return array Response data
     */
    public function create(array $data): array
    {
        try {
            // Validate CSRF token
            $this->csrf->validate($data['csrf_token'] ?? null);

            // Validate input
            $validated = $this->validator->validateFeed($data);

            // Check for duplicate topic
            $existingFeed = $this->feedRepo->findByTopic($validated['topic']);
            if ($existingFeed !== null) {
                throw new ValidationException('Feed already exists', [
                    'topic' => 'A feed with this topic already exists',
                ]);
            }

            // Create feed
            $feedId = $this->feedRepo->create([
                'topic' => $validated['topic'],
                'url' => $validated['url'],
                'result_limit' => $validated['limit'],
                'enabled' => 1,
            ]);

            $this->logger->info('Feed created', [
                'category' => 'feed_controller',
                'feed_id' => $feedId,
                'topic' => $validated['topic'],
            ]);

            return [
                'status' => 'success',
                'message' => 'Feed created successfully',
                'feed_id' => $feedId,
                'redirect' => '/feeds',
            ];
        } catch (SecurityException $e) {
            $this->logger->warning('CSRF validation failed on feed creation', [
                'category' => 'feed_controller',
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 'error',
                'message' => 'Security validation failed',
                'http_code' => 403,
            ];
        } catch (ValidationException $e) {
            $this->logger->info('Feed validation failed', [
                'category' => 'feed_controller',
                'errors' => $e->getErrors(),
            ]);

            return [
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->getErrors(),
                'http_code' => 422,
            ];
        } catch (\PDOException $e) {
            // Handle duplicate topic constraint violation
            if (strpos($e->getMessage(), 'UNIQUE constraint') !== false ||
                strpos($e->getMessage(), 'Duplicate entry') !== false) {
                $this->logger->info('Duplicate feed topic attempted', [
                    'category' => 'feed_controller',
                    'topic' => $data['topic'] ?? null,
                ]);

                return [
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => ['topic' => 'A feed with this topic already exists'],
                    'http_code' => 422,
                ];
            }

            $this->logger->error('Database error creating feed', [
                'category' => 'feed_controller',
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 'error',
                'message' => 'Failed to create feed',
                'http_code' => 500,
            ];
        } catch (\Exception $e) {
            $this->logger->error('Unexpected error creating feed', [
                'category' => 'feed_controller',
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 'error',
                'message' => 'An unexpected error occurred',
                'http_code' => 500,
            ];
        }
    }

    /**
     * Edit an existing feed
     *
     * POST /feeds/edit/{id}
     *
     * @param int $id Feed ID
     * @param array $data Feed data to update
     * @return array Response data
     */
    public function edit(int $id, array $data): array
    {
        try {
            // Validate CSRF token
            $this->csrf->validate($data['csrf_token'] ?? null);

            // Check if feed exists
            $feed = $this->feedRepo->findById($id);
            if ($feed === null) {
                return [
                    'status' => 'error',
                    'message' => 'Feed not found',
                    'http_code' => 404,
                ];
            }

            // Validate input
            $validated = $this->validator->validateFeed($data);

            // Check for duplicate topic (excluding current feed)
            $existingFeed = $this->feedRepo->findByTopic($validated['topic']);
            if ($existingFeed !== null && $existingFeed['id'] !== $id) {
                throw new ValidationException('Feed already exists', [
                    'topic' => 'A feed with this topic already exists',
                ]);
            }

            // Update feed
            $success = $this->feedRepo->update($id, [
                'topic' => $validated['topic'],
                'url' => $validated['url'],
                'result_limit' => $validated['limit'],
                'enabled' => $validated['enabled'] ? 1 : 0,
            ]);

            if (!$success) {
                throw new \Exception('Failed to update feed in database');
            }

            $this->logger->info('Feed updated', [
                'category' => 'feed_controller',
                'feed_id' => $id,
                'topic' => $validated['topic'],
            ]);

            return [
                'status' => 'success',
                'message' => 'Feed updated successfully',
                'redirect' => '/feeds',
            ];
        } catch (SecurityException $e) {
            $this->logger->warning('CSRF validation failed on feed edit', [
                'category' => 'feed_controller',
                'feed_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 'error',
                'message' => 'Security validation failed',
                'http_code' => 403,
            ];
        } catch (ValidationException $e) {
            $this->logger->info('Feed validation failed on edit', [
                'category' => 'feed_controller',
                'feed_id' => $id,
                'errors' => $e->getErrors(),
            ]);

            return [
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->getErrors(),
                'http_code' => 422,
            ];
        } catch (\PDOException $e) {
            // Handle duplicate topic constraint violation
            if (strpos($e->getMessage(), 'UNIQUE constraint') !== false ||
                strpos($e->getMessage(), 'Duplicate entry') !== false) {
                $this->logger->info('Duplicate feed topic attempted on edit', [
                    'category' => 'feed_controller',
                    'feed_id' => $id,
                    'topic' => $data['topic'] ?? null,
                ]);

                return [
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => ['topic' => 'A feed with this topic already exists'],
                    'http_code' => 422,
                ];
            }

            $this->logger->error('Database error updating feed', [
                'category' => 'feed_controller',
                'feed_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 'error',
                'message' => 'Failed to update feed',
                'http_code' => 500,
            ];
        } catch (\Exception $e) {
            $this->logger->error('Unexpected error updating feed', [
                'category' => 'feed_controller',
                'feed_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 'error',
                'message' => 'An unexpected error occurred',
                'http_code' => 500,
            ];
        }
    }

    /**
     * Delete a feed
     *
     * POST /feeds/delete/{id}
     *
     * @param int $id Feed ID
     * @param array $data Request data (csrf_token)
     * @return array Response data
     */
    public function delete(int $id, array $data): array
    {
        try {
            // Validate CSRF token
            $this->csrf->validate($data['csrf_token'] ?? null);

            // Delete feed
            $success = $this->feedRepo->delete($id);

            if (!$success) {
                return [
                    'status' => 'error',
                    'message' => 'Feed not found',
                    'http_code' => 404,
                ];
            }

            $this->logger->info('Feed deleted', [
                'category' => 'feed_controller',
                'feed_id' => $id,
            ]);

            return [
                'status' => 'success',
                'message' => 'Feed deleted successfully',
                'redirect' => '/feeds',
            ];
        } catch (SecurityException $e) {
            $this->logger->warning('CSRF validation failed on feed deletion', [
                'category' => 'feed_controller',
                'feed_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 'error',
                'message' => 'Security validation failed',
                'http_code' => 403,
            ];
        } catch (\Exception $e) {
            $this->logger->error('Unexpected error deleting feed', [
                'category' => 'feed_controller',
                'feed_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 'error',
                'message' => 'Failed to delete feed',
                'http_code' => 500,
            ];
        }
    }

    /**
     * Run a feed manually (trigger processing)
     *
     * POST /feeds/run/{id}
     *
     * @param int $id Feed ID
     * @param array $data Request data (csrf_token)
     * @return array Response data
     */
    public function run(int $id, array $data): array
    {
        try {
            // Validate CSRF token
            $this->csrf->validate($data['csrf_token'] ?? null);

            // Check if feed exists
            $feed = $this->feedRepo->findById($id);
            if ($feed === null) {
                return [
                    'status' => 'error',
                    'message' => 'Feed not found',
                    'http_code' => 404,
                ];
            }

            // Check if processing can proceed (rate limiting)
            if (!$this->queue->canProcessNow()) {
                return [
                    'status' => 'error',
                    'message' => 'Rate limit exceeded. Please wait before processing again.',
                    'http_code' => 429,
                ];
            }

            // Update last processed timestamp
            $this->feedRepo->updateLastProcessedAt($id);

            // Update last process time for rate limiting
            $this->queue->setLastProcessTime(time());

            $this->logger->info('Feed processing triggered', [
                'category' => 'feed_controller',
                'feed_id' => $id,
                'topic' => $feed['topic'],
            ]);

            return [
                'status' => 'success',
                'message' => 'Feed processing started',
                'feed_id' => $id,
            ];
        } catch (SecurityException $e) {
            $this->logger->warning('CSRF validation failed on feed run', [
                'category' => 'feed_controller',
                'feed_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 'error',
                'message' => 'Security validation failed',
                'http_code' => 403,
            ];
        } catch (\Exception $e) {
            $this->logger->error('Unexpected error running feed', [
                'category' => 'feed_controller',
                'feed_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 'error',
                'message' => 'Failed to start feed processing',
                'http_code' => 500,
            ];
        }
    }
}
