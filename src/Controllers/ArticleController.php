<?php

declare(strict_types=1);

namespace Unfurl\Controllers;

use Unfurl\Repositories\ArticleRepository;
use Unfurl\Services\ProcessingQueue;
use Unfurl\Security\CsrfToken;
use Unfurl\Security\OutputEscaper;
use Unfurl\Core\Logger;
use Unfurl\Exceptions\SecurityException;

/**
 * Article Controller
 *
 * Handles all article management operations:
 * - List with pagination, filtering, and search
 * - View article details
 * - Edit article
 * - Delete article
 * - Bulk delete
 * - Retry failed articles
 *
 * Security:
 * - CSRF protection on all POST requests
 * - XSS prevention on all output
 * - SQL injection prevention via prepared statements
 */
class ArticleController
{
    /**
     * Default pagination limit
     */
    private const DEFAULT_LIMIT = 20;

    /**
     * Maximum pagination limit
     */
    private const MAX_LIMIT = 100;

    /**
     * @param ArticleRepository $articleRepo
     * @param ProcessingQueue $queue
     * @param CsrfToken $csrf
     * @param OutputEscaper $escaper
     * @param Logger $logger
     */
    public function __construct(
        private ArticleRepository $articleRepo,
        private ProcessingQueue $queue,
        private CsrfToken $csrf,
        private OutputEscaper $escaper,
        private Logger $logger
    ) {
    }

    /**
     * List articles with pagination, filtering, and search
     *
     * GET /articles
     *
     * Query parameters:
     * - limit: Results per page (default: 20, max: 100)
     * - offset: Skip N results (default: 0)
     * - topic: Filter by topic
     * - status: Filter by status (pending, success, failed)
     * - date_from: Filter by pub_date >= date
     * - date_to: Filter by pub_date <= date
     * - search: Fulltext search on titles/descriptions
     *
     * @return array View data
     */
    public function index(): array
    {
        // Get and validate pagination parameters
        $limit = min((int)($_GET['limit'] ?? self::DEFAULT_LIMIT), self::MAX_LIMIT);
        $offset = max((int)($_GET['offset'] ?? 0), 0);

        // Get filter parameters
        $filters = [
            'topic' => $_GET['topic'] ?? null,
            'status' => $_GET['status'] ?? null,
            'date_from' => $_GET['date_from'] ?? null,
            'date_to' => $_GET['date_to'] ?? null,
            'search' => $_GET['search'] ?? null,
        ];

        // Remove null/empty filters (trim whitespace before checking)
        $filters = array_filter($filters, fn($v) => $v !== null && trim((string)$v) !== '');

        // Get articles with filters
        $articles = $this->articleRepo->findWithFilters($filters, $limit, $offset);

        // Get total count for pagination
        $totalCount = $this->articleRepo->countWithFilters($filters);

        // Log access
        $this->logger->info('Articles list accessed', [
            'category' => 'article_controller',
            'filters' => $filters,
            'limit' => $limit,
            'offset' => $offset,
            'total' => $totalCount,
        ]);

        return [
            'articles' => $articles,
            'pagination' => [
                'limit' => $limit,
                'offset' => $offset,
                'total' => $totalCount,
                'has_more' => ($offset + $limit) < $totalCount,
            ],
            'filters' => $filters,
            'csrf_token' => $this->csrf->getToken(),
            'escaper' => $this->escaper,
            'flash' => $this->getFlashMessages(),
        ];
    }

    /**
     * View article details
     *
     * GET /articles/{id}
     *
     * @param int $id Article ID
     * @return array View data
     * @throws \Exception If article not found
     */
    public function view(int $id): array
    {
        $article = $this->articleRepo->findById($id);

        if ($article === null) {
            throw new \Exception("Article not found: {$id}");
        }

        $this->logger->info('Article viewed', [
            'category' => 'article_controller',
            'article_id' => $id,
        ]);

        return [
            'article' => $article,
            'csrf_token' => $this->csrf->getToken(),
            'escaper' => $this->escaper,
            'flash' => $this->getFlashMessages(),
        ];
    }

    /**
     * Edit article
     *
     * POST /articles/edit/{id}
     *
     * @param int $id Article ID
     * @return void Redirects to article view
     * @throws SecurityException If CSRF validation fails
     * @throws \Exception If article not found
     */
    public function edit(int $id): void
    {
        // Validate CSRF token
        $this->csrf->validateFromPost();

        // Check if article exists
        $article = $this->articleRepo->findById($id);
        if ($article === null) {
            throw new \Exception("Article not found: {$id}");
        }

        // Get editable fields from POST
        $updateData = [];

        if (isset($_POST['status'])) {
            $allowedStatuses = ['pending', 'success', 'failed'];
            if (in_array($_POST['status'], $allowedStatuses)) {
                $updateData['status'] = $_POST['status'];
            }
        }

        if (isset($_POST['rss_title'])) {
            $updateData['rss_title'] = $_POST['rss_title'];
        }

        if (isset($_POST['rss_description'])) {
            $updateData['rss_description'] = $_POST['rss_description'];
        }

        if (isset($_POST['error_message'])) {
            $updateData['error_message'] = $_POST['error_message'];
        }

        // Update article
        if (!empty($updateData)) {
            $success = $this->articleRepo->update($id, $updateData);

            if ($success) {
                $this->setFlashMessage('success', 'Article updated successfully');
                $this->logger->info('Article updated', [
                    'category' => 'article_controller',
                    'article_id' => $id,
                    'fields' => array_keys($updateData),
                ]);
            } else {
                $this->setFlashMessage('error', 'Failed to update article');
                $this->logger->error('Article update failed', [
                    'category' => 'article_controller',
                    'article_id' => $id,
                ]);
            }
        } else {
            $this->setFlashMessage('warning', 'No changes to save');
        }

        // Redirect to article view
        $this->redirect("/articles/{$id}");
    }

    /**
     * Delete article
     *
     * POST /articles/delete/{id}
     *
     * @param int $id Article ID
     * @return void Redirects to article list
     * @throws SecurityException If CSRF validation fails
     */
    public function delete(int $id): void
    {
        // Validate CSRF token
        $this->csrf->validateFromPost();

        $success = $this->articleRepo->delete($id);

        if ($success) {
            $this->setFlashMessage('success', 'Article deleted successfully');
            $this->logger->info('Article deleted', [
                'category' => 'article_controller',
                'article_id' => $id,
            ]);
        } else {
            $this->setFlashMessage('error', 'Failed to delete article');
            $this->logger->error('Article deletion failed', [
                'category' => 'article_controller',
                'article_id' => $id,
            ]);
        }

        // Redirect to article list
        $this->redirect('/articles');
    }

    /**
     * Bulk delete articles
     *
     * POST /articles/bulk-delete
     *
     * POST data:
     * - article_ids: Array of article IDs
     *
     * @return void Redirects to article list
     * @throws SecurityException If CSRF validation fails
     */
    public function bulkDelete(): void
    {
        // Validate CSRF token
        $this->csrf->validateFromPost();

        // Accept both 'ids' (from bulk-actions.js) and 'article_ids' (from API)
        $articleIds = $_POST['ids'] ?? $_POST['article_ids'] ?? [];

        // Parse JSON if string (bulk-actions.js sends JSON-encoded array)
        if (is_string($articleIds)) {
            $articleIds = json_decode($articleIds, true) ?? [];
        }

        if (!is_array($articleIds) || empty($articleIds)) {
            $this->setFlashMessage('error', 'No articles selected');
            $this->redirect('/articles');
            return;
        }

        // Validate all IDs are integers
        $articleIds = array_filter($articleIds, fn($id) => is_numeric($id));
        $articleIds = array_map('intval', $articleIds);

        if (empty($articleIds)) {
            $this->setFlashMessage('error', 'Invalid article IDs');
            $this->redirect('/articles');
            return;
        }

        // Delete each article
        $deleted = 0;
        $failed = 0;

        foreach ($articleIds as $id) {
            if ($this->articleRepo->delete($id)) {
                $deleted++;
            } else {
                $failed++;
            }
        }

        // Set flash message
        if ($deleted > 0) {
            $this->setFlashMessage('success', "Deleted {$deleted} article(s)");
        }

        if ($failed > 0) {
            $this->setFlashMessage('warning', "Failed to delete {$failed} article(s)");
        }

        $this->logger->info('Bulk delete completed', [
            'category' => 'article_controller',
            'deleted' => $deleted,
            'failed' => $failed,
            'total' => count($articleIds),
        ]);

        // Redirect to article list
        $this->redirect('/articles');
    }

    /**
     * Retry failed article
     *
     * POST /articles/retry/{id}
     *
     * Resets article status to pending and clears error messages
     * so it can be reprocessed.
     *
     * @param int $id Article ID
     * @return void Redirects to article view
     * @throws SecurityException If CSRF validation fails
     * @throws \Exception If article not found
     */
    public function retry(int $id): void
    {
        // Validate CSRF token
        $this->csrf->validateFromPost();

        // Check if article exists
        $article = $this->articleRepo->findById($id);
        if ($article === null) {
            throw new \Exception("Article not found: {$id}");
        }

        // Reset article for retry
        $updateData = [
            'status' => 'pending',
            'retry_count' => 0,
            'next_retry_at' => null,
            'error_message' => null,
            'last_error' => null,
        ];

        $success = $this->articleRepo->update($id, $updateData);

        if ($success) {
            $this->setFlashMessage('success', 'Article queued for retry');
            $this->logger->info('Article retry initiated', [
                'category' => 'article_controller',
                'article_id' => $id,
            ]);
        } else {
            $this->setFlashMessage('error', 'Failed to retry article');
            $this->logger->error('Article retry failed', [
                'category' => 'article_controller',
                'article_id' => $id,
            ]);
        }

        // Redirect to article view
        $this->redirect("/articles/{$id}");
    }

    /**
     * Set flash message
     *
     * @param string $type Message type (success, error, warning, info)
     * @param string $message Message text
     * @return void
     */
    private function setFlashMessage(string $type, string $message): void
    {
        if (!CsrfToken::isTestMode() && session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION['flash'] = [
            'type' => $type,
            'message' => $message,
        ];
    }

    /**
     * Get and clear flash messages
     *
     * @return array|null Flash message or null
     */
    private function getFlashMessages(): ?array
    {
        if (!CsrfToken::isTestMode() && session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);

        return $flash;
    }

    /**
     * Redirect to URL
     *
     * Note: This method is protected (not private) to allow mocking in tests.
     *
     * @param string $url URL to redirect to
     * @return void
     */
    protected function redirect(string $url): void
    {
        // In test mode, throw exception instead of exiting
        // This prevents PHPUnit from hanging on exit calls
        if (CsrfToken::isTestMode()) {
            throw new \Exception("Redirect to: {$url}");
        }

        header("Location: {$url}");
        exit;
    }
}
