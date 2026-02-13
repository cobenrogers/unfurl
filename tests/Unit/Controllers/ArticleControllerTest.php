<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use PHPUnit\Framework\TestCase;
use Unfurl\Controllers\ArticleController;
use Unfurl\Repositories\ArticleRepository;
use Unfurl\Services\ProcessingQueue;
use Unfurl\Security\CsrfToken;
use Unfurl\Security\OutputEscaper;
use Unfurl\Core\Logger;
use Unfurl\Exceptions\SecurityException;

/**
 * Tests for ArticleController
 *
 * Tests all article management operations:
 * - List with pagination, filtering, and search
 * - View article details
 * - Edit article
 * - Delete article
 * - Bulk delete
 * - Retry failed articles
 * - Security (CSRF, XSS)
 */
class ArticleControllerTest extends TestCase
{
    private ArticleController $controller;
    private ArticleRepository $articleRepo;
    private ProcessingQueue $queue;
    private CsrfToken $csrf;
    private OutputEscaper $escaper;
    private Logger $logger;

    protected function setUp(): void
    {
        // Mock dependencies
        $this->articleRepo = $this->createMock(ArticleRepository::class);
        $this->queue = $this->createMock(ProcessingQueue::class);
        $this->csrf = $this->createMock(CsrfToken::class);
        $this->escaper = $this->createMock(OutputEscaper::class);
        $this->logger = $this->createMock(Logger::class);

        // Clear session before each test
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];
        $_GET = [];
        $_POST = [];

        // Create controller
        $this->controller = new ArticleController(
            $this->articleRepo,
            $this->queue,
            $this->csrf,
            $this->escaper,
            $this->logger
        );
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_GET = [];
        $_POST = [];

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    /**
     * Create controller with redirect mocked
     *
     * Use this for tests that trigger redirects
     */
    private function createControllerWithMockedRedirect(): ArticleController
    {
        return $this->getMockBuilder(ArticleController::class)
            ->setConstructorArgs([
                $this->articleRepo,
                $this->queue,
                $this->csrf,
                $this->escaper,
                $this->logger
            ])
            ->onlyMethods(['redirect'])
            ->getMock();
    }

    // ============================================
    // List Articles
    // ============================================

    public function test_index_returns_articles_with_default_pagination(): void
    {
        $articles = [
            ['id' => 1, 'rss_title' => 'Article 1'],
            ['id' => 2, 'rss_title' => 'Article 2'],
        ];

        $this->articleRepo->expects($this->once())
            ->method('findWithFilters')
            ->with([], 20, 0)
            ->willReturn($articles);

        $this->articleRepo->expects($this->once())
            ->method('countWithFilters')
            ->with([])
            ->willReturn(2);

        $this->csrf->expects($this->once())
            ->method('getToken')
            ->willReturn('test_token');

        $result = $this->controller->index();

        $this->assertEquals($articles, $result['articles']);
        $this->assertEquals(20, $result['pagination']['limit']);
        $this->assertEquals(0, $result['pagination']['offset']);
        $this->assertEquals(2, $result['pagination']['total']);
        $this->assertFalse($result['pagination']['has_more']);
    }

    public function test_index_respects_custom_pagination(): void
    {
        $_GET['limit'] = '10';
        $_GET['offset'] = '5';

        $this->articleRepo->expects($this->once())
            ->method('findWithFilters')
            ->with([], 10, 5)
            ->willReturn([]);

        $this->articleRepo->expects($this->once())
            ->method('countWithFilters')
            ->willReturn(0);

        $this->csrf->expects($this->once())
            ->method('getToken')
            ->willReturn('test_token');

        $result = $this->controller->index();

        $this->assertEquals(10, $result['pagination']['limit']);
        $this->assertEquals(5, $result['pagination']['offset']);
    }

    public function test_index_enforces_max_limit(): void
    {
        $_GET['limit'] = '200'; // Over max of 100

        $this->articleRepo->expects($this->once())
            ->method('findWithFilters')
            ->with([], 100, 0) // Should be capped at 100
            ->willReturn([]);

        $this->articleRepo->expects($this->once())
            ->method('countWithFilters')
            ->willReturn(0);

        $this->csrf->expects($this->once())
            ->method('getToken')
            ->willReturn('test_token');

        $result = $this->controller->index();

        $this->assertEquals(100, $result['pagination']['limit']);
    }

    public function test_index_enforces_min_offset(): void
    {
        $_GET['offset'] = '-10'; // Negative offset

        $this->articleRepo->expects($this->once())
            ->method('findWithFilters')
            ->with([], 20, 0) // Should be 0
            ->willReturn([]);

        $this->articleRepo->expects($this->once())
            ->method('countWithFilters')
            ->willReturn(0);

        $this->csrf->expects($this->once())
            ->method('getToken')
            ->willReturn('test_token');

        $result = $this->controller->index();

        $this->assertEquals(0, $result['pagination']['offset']);
    }

    public function test_index_filters_by_topic(): void
    {
        $_GET['topic'] = 'AI';

        $this->articleRepo->expects($this->once())
            ->method('findWithFilters')
            ->with(['topic' => 'AI'], 20, 0)
            ->willReturn([]);

        $this->articleRepo->expects($this->once())
            ->method('countWithFilters')
            ->with(['topic' => 'AI'])
            ->willReturn(0);

        $this->csrf->expects($this->once())
            ->method('getToken')
            ->willReturn('test_token');

        $result = $this->controller->index();

        $this->assertEquals(['topic' => 'AI'], $result['filters']);
    }

    public function test_index_filters_by_status(): void
    {
        $_GET['status'] = 'failed';

        $this->articleRepo->expects($this->once())
            ->method('findWithFilters')
            ->with(['status' => 'failed'], 20, 0)
            ->willReturn([]);

        $this->articleRepo->expects($this->once())
            ->method('countWithFilters')
            ->with(['status' => 'failed'])
            ->willReturn(0);

        $this->csrf->expects($this->once())
            ->method('getToken')
            ->willReturn('test_token');

        $result = $this->controller->index();

        $this->assertEquals(['status' => 'failed'], $result['filters']);
    }

    public function test_index_filters_by_date_range(): void
    {
        $_GET['date_from'] = '2026-01-01';
        $_GET['date_to'] = '2026-01-31';

        $expectedFilters = [
            'date_from' => '2026-01-01',
            'date_to' => '2026-01-31',
        ];

        $this->articleRepo->expects($this->once())
            ->method('findWithFilters')
            ->with($expectedFilters, 20, 0)
            ->willReturn([]);

        $this->articleRepo->expects($this->once())
            ->method('countWithFilters')
            ->with($expectedFilters)
            ->willReturn(0);

        $this->csrf->expects($this->once())
            ->method('getToken')
            ->willReturn('test_token');

        $result = $this->controller->index();

        $this->assertEquals($expectedFilters, $result['filters']);
    }

    public function test_index_searches_articles(): void
    {
        $_GET['search'] = 'artificial intelligence';

        $this->articleRepo->expects($this->once())
            ->method('findWithFilters')
            ->with(['search' => 'artificial intelligence'], 20, 0)
            ->willReturn([]);

        $this->articleRepo->expects($this->once())
            ->method('countWithFilters')
            ->with(['search' => 'artificial intelligence'])
            ->willReturn(0);

        $this->csrf->expects($this->once())
            ->method('getToken')
            ->willReturn('test_token');

        $result = $this->controller->index();

        $this->assertEquals(['search' => 'artificial intelligence'], $result['filters']);
    }

    public function test_index_combines_multiple_filters(): void
    {
        $_GET['topic'] = 'AI';
        $_GET['status'] = 'success';
        $_GET['search'] = 'machine learning';

        $expectedFilters = [
            'topic' => 'AI',
            'status' => 'success',
            'search' => 'machine learning',
        ];

        $this->articleRepo->expects($this->once())
            ->method('findWithFilters')
            ->with($expectedFilters, 20, 0)
            ->willReturn([]);

        $this->articleRepo->expects($this->once())
            ->method('countWithFilters')
            ->with($expectedFilters)
            ->willReturn(0);

        $this->csrf->expects($this->once())
            ->method('getToken')
            ->willReturn('test_token');

        $result = $this->controller->index();

        $this->assertEquals($expectedFilters, $result['filters']);
    }

    public function test_index_ignores_empty_filters(): void
    {
        $_GET['topic'] = '';
        $_GET['status'] = '  ';
        $_GET['search'] = 'test';

        $expectedFilters = ['search' => 'test'];

        $this->articleRepo->expects($this->once())
            ->method('findWithFilters')
            ->with($expectedFilters, 20, 0)
            ->willReturn([]);

        $this->articleRepo->expects($this->once())
            ->method('countWithFilters')
            ->with($expectedFilters)
            ->willReturn(0);

        $this->csrf->expects($this->once())
            ->method('getToken')
            ->willReturn('test_token');

        $result = $this->controller->index();

        $this->assertEquals($expectedFilters, $result['filters']);
    }

    public function test_index_calculates_has_more_correctly(): void
    {
        $_GET['limit'] = '10';
        $_GET['offset'] = '0';

        $this->articleRepo->expects($this->once())
            ->method('findWithFilters')
            ->willReturn([]);

        $this->articleRepo->expects($this->once())
            ->method('countWithFilters')
            ->willReturn(25); // Total 25, showing 0-10, so has more

        $this->csrf->expects($this->once())
            ->method('getToken')
            ->willReturn('test_token');

        $result = $this->controller->index();

        $this->assertTrue($result['pagination']['has_more']);
    }

    public function test_index_logs_access(): void
    {
        $this->articleRepo->method('findWithFilters')->willReturn([]);
        $this->articleRepo->method('countWithFilters')->willReturn(0);
        $this->csrf->method('getToken')->willReturn('test_token');

        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                'Articles list accessed',
                $this->callback(function ($context) {
                    return $context['category'] === 'article_controller'
                        && isset($context['filters'])
                        && isset($context['limit'])
                        && isset($context['offset'])
                        && isset($context['total']);
                })
            );

        $this->controller->index();
    }

    // ============================================
    // View Article
    // ============================================

    public function test_view_returns_article_details(): void
    {
        $article = [
            'id' => 1,
            'rss_title' => 'Test Article',
            'status' => 'success',
        ];

        $this->articleRepo->expects($this->once())
            ->method('findById')
            ->with(1)
            ->willReturn($article);

        $this->csrf->expects($this->once())
            ->method('getToken')
            ->willReturn('test_token');

        $result = $this->controller->view(1);

        $this->assertEquals($article, $result['article']);
        $this->assertEquals('test_token', $result['csrf_token']);
        $this->assertInstanceOf(OutputEscaper::class, $result['escaper']);
    }

    public function test_view_throws_exception_if_article_not_found(): void
    {
        $this->articleRepo->expects($this->once())
            ->method('findById')
            ->with(999)
            ->willReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Article not found: 999');

        $this->controller->view(999);
    }

    public function test_view_logs_access(): void
    {
        $article = ['id' => 1, 'rss_title' => 'Test'];

        $this->articleRepo->method('findById')->willReturn($article);
        $this->csrf->method('getToken')->willReturn('test_token');

        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                'Article viewed',
                $this->callback(function ($context) {
                    return $context['category'] === 'article_controller'
                        && $context['article_id'] === 1;
                })
            );

        $this->controller->view(1);
    }

    // ============================================
    // Edit Article
    // ============================================

    public function test_edit_validates_csrf_token(): void
    {
        $this->csrf->expects($this->once())
            ->method('validateFromPost')
            ->willThrowException(new SecurityException('CSRF validation failed'));

        $this->expectException(SecurityException::class);

        $this->controller->edit(1);
    }

    public function test_edit_throws_exception_if_article_not_found(): void
    {
        $this->csrf->method('validateFromPost');

        $this->articleRepo->expects($this->once())
            ->method('findById')
            ->with(999)
            ->willReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Article not found: 999');

        $this->controller->edit(999);
    }

    public function test_edit_updates_status(): void
    {
        $_POST['status'] = 'success';

        $article = ['id' => 1, 'rss_title' => 'Test'];

        $controller = $this->createControllerWithMockedRedirect();

        $this->csrf->method('validateFromPost');
        $this->articleRepo->method('findById')->willReturn($article);

        $this->articleRepo->expects($this->once())
            ->method('update')
            ->with(1, ['status' => 'success'])
            ->willReturn(true);

        $controller->expects($this->once())
            ->method('redirect')
            ->with('/articles/1');

        $controller->edit(1);

        // Check that session has flash message
        $this->assertArrayHasKey('flash', $_SESSION);
        $this->assertEquals('success', $_SESSION['flash']['type']);
    }

    public function test_edit_updates_multiple_fields(): void
    {
        $_POST['status'] = 'failed';
        $_POST['rss_title'] = 'Updated Title';
        $_POST['error_message'] = 'Test error';

        $article = ['id' => 1, 'rss_title' => 'Test'];

        $controller = $this->createControllerWithMockedRedirect();

        $this->csrf->method('validateFromPost');
        $this->articleRepo->method('findById')->willReturn($article);

        $this->articleRepo->expects($this->once())
            ->method('update')
            ->with(1, [
                'status' => 'failed',
                'rss_title' => 'Updated Title',
                'error_message' => 'Test error',
            ])
            ->willReturn(true);

        $controller->edit(1);

        $this->assertArrayHasKey('flash', $_SESSION);
    }

    public function test_edit_rejects_invalid_status(): void
    {
        $_POST['status'] = 'invalid_status';

        $article = ['id' => 1, 'rss_title' => 'Test'];

        $controller = $this->createControllerWithMockedRedirect();

        $this->csrf->method('validateFromPost');
        $this->articleRepo->method('findById')->willReturn($article);

        // Should not call update since status is invalid
        $this->articleRepo->expects($this->never())
            ->method('update');

        $controller->edit(1);

        // Check warning flash message
        $this->assertEquals('warning', $_SESSION['flash']['type']);
        $this->assertEquals('No changes to save', $_SESSION['flash']['message']);
    }

    public function test_edit_sets_success_flash_message(): void
    {
        $_POST['status'] = 'success';

        $article = ['id' => 1];
        $controller = $this->createControllerWithMockedRedirect();

        $this->csrf->method('validateFromPost');
        $this->articleRepo->method('findById')->willReturn($article);
        $this->articleRepo->method('update')->willReturn(true);

        $controller->edit(1);

        $this->assertArrayHasKey('flash', $_SESSION);
        $this->assertEquals('success', $_SESSION['flash']['type']);
        $this->assertEquals('Article updated successfully', $_SESSION['flash']['message']);
    }

    public function test_edit_logs_update(): void
    {
        $_POST['status'] = 'success';

        $article = ['id' => 1];
        $controller = $this->createControllerWithMockedRedirect();

        $this->csrf->method('validateFromPost');
        $this->articleRepo->method('findById')->willReturn($article);
        $this->articleRepo->method('update')->willReturn(true);

        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                'Article updated',
                $this->callback(function ($context) {
                    return $context['category'] === 'article_controller'
                        && $context['article_id'] === 1
                        && isset($context['fields']);
                })
            );

        $controller->edit(1);
    }

    // ============================================
    // Delete Article
    // ============================================

    public function test_delete_validates_csrf_token(): void
    {
        $this->csrf->expects($this->once())
            ->method('validateFromPost')
            ->willThrowException(new SecurityException('CSRF validation failed'));

        $this->expectException(SecurityException::class);

        $this->controller->delete(1);
    }

    public function test_delete_removes_article(): void
    {
        $controller = $this->createControllerWithMockedRedirect();

        $this->csrf->method('validateFromPost');

        $this->articleRepo->expects($this->once())
            ->method('delete')
            ->with(1)
            ->willReturn(true);

        $controller->delete(1);

        $this->assertEquals('success', $_SESSION['flash']['type']);
        $this->assertEquals('Article deleted successfully', $_SESSION['flash']['message']);
    }

    public function test_delete_sets_error_on_failure(): void
    {
        $controller = $this->createControllerWithMockedRedirect();

        $this->csrf->method('validateFromPost');
        $this->articleRepo->method('delete')->willReturn(false);

        $controller->delete(1);

        $this->assertEquals('error', $_SESSION['flash']['type']);
    }

    public function test_delete_logs_action(): void
    {
        $controller = $this->createControllerWithMockedRedirect();

        $this->csrf->method('validateFromPost');
        $this->articleRepo->method('delete')->willReturn(true);

        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                'Article deleted',
                $this->callback(function ($context) {
                    return $context['category'] === 'article_controller'
                        && $context['article_id'] === 1;
                })
            );

        $controller->delete(1);
    }

    // ============================================
    // Bulk Delete
    // ============================================

    public function test_bulk_delete_validates_csrf_token(): void
    {
        $this->csrf->expects($this->once())
            ->method('validateFromPost')
            ->willThrowException(new SecurityException('CSRF validation failed'));

        $this->expectException(SecurityException::class);

        $this->controller->bulkDelete();
    }

    public function test_bulk_delete_requires_article_ids(): void
    {
        $controller = $this->createControllerWithMockedRedirect();

        $this->csrf->method('validateFromPost');
        $_POST['article_ids'] = [];

        $controller->bulkDelete();

        $this->assertEquals('error', $_SESSION['flash']['type']);
        $this->assertEquals('No articles selected', $_SESSION['flash']['message']);
    }

    public function test_bulk_delete_validates_article_ids_are_numeric(): void
    {
        $controller = $this->createControllerWithMockedRedirect();

        $this->csrf->method('validateFromPost');
        $_POST['article_ids'] = ['not_a_number', 'also_not_a_number'];

        $controller->bulkDelete();

        $this->assertEquals('error', $_SESSION['flash']['type']);
        $this->assertEquals('Invalid article IDs', $_SESSION['flash']['message']);
    }

    public function test_bulk_delete_removes_multiple_articles(): void
    {
        $controller = $this->createControllerWithMockedRedirect();

        $this->csrf->method('validateFromPost');
        $_POST['article_ids'] = ['1', '2', '3'];

        $this->articleRepo->expects($this->exactly(3))
            ->method('delete')
            ->willReturn(true);

        $controller->bulkDelete();

        $this->assertEquals('success', $_SESSION['flash']['type']);
        $this->assertStringContainsString('Deleted 3 article(s)', $_SESSION['flash']['message']);
    }

    public function test_bulk_delete_reports_failures(): void
    {
        $controller = $this->createControllerWithMockedRedirect();

        $this->csrf->method('validateFromPost');
        $_POST['article_ids'] = ['1', '2', '3'];

        $this->articleRepo->expects($this->exactly(3))
            ->method('delete')
            ->willReturnOnConsecutiveCalls(true, false, true);

        $controller->bulkDelete();

        // Should have both success and warning messages in session
        // Flash system only stores one message, so check last set
        $this->assertArrayHasKey('flash', $_SESSION);
    }

    public function test_bulk_delete_logs_action(): void
    {
        $controller = $this->createControllerWithMockedRedirect();

        $this->csrf->method('validateFromPost');
        $_POST['article_ids'] = ['1', '2'];
        $this->articleRepo->method('delete')->willReturn(true);

        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                'Bulk delete completed',
                $this->callback(function ($context) {
                    return $context['category'] === 'article_controller'
                        && $context['deleted'] === 2
                        && $context['failed'] === 0
                        && $context['total'] === 2;
                })
            );

        $controller->bulkDelete();
    }

    public function test_bulkDelete_accepts_json_array(): void
    {
        $controller = $this->createControllerWithMockedRedirect();

        $this->csrf->method('validateFromPost');
        // Simulate bulk-actions.js sending JSON-encoded array
        $_POST['ids'] = json_encode([1, 2, 3]);

        $this->articleRepo->expects($this->exactly(3))
            ->method('delete')
            ->willReturn(true);

        $controller->bulkDelete();

        $this->assertEquals('success', $_SESSION['flash']['type']);
        $this->assertStringContainsString('Deleted 3 article(s)', $_SESSION['flash']['message']);
    }

    public function test_bulkDelete_shows_error_for_empty_selection(): void
    {
        $controller = $this->createControllerWithMockedRedirect();

        $this->csrf->method('validateFromPost');
        $_POST['ids'] = json_encode([]);

        $this->articleRepo->expects($this->never())
            ->method('delete');

        $controller->bulkDelete();

        $this->assertEquals('error', $_SESSION['flash']['type']);
        $this->assertEquals('No articles selected', $_SESSION['flash']['message']);
    }

    public function test_bulkDelete_handles_partial_failures(): void
    {
        $controller = $this->createControllerWithMockedRedirect();

        $this->csrf->method('validateFromPost');
        $_POST['ids'] = json_encode([1, 2, 3]);

        // First succeeds, second fails, third succeeds
        $this->articleRepo->expects($this->exactly(3))
            ->method('delete')
            ->willReturnOnConsecutiveCalls(true, false, true);

        $controller->bulkDelete();

        // Last flash message should be warning about failures
        $this->assertEquals('warning', $_SESSION['flash']['type']);
        $this->assertStringContainsString('Failed to delete 1 article(s)', $_SESSION['flash']['message']);
    }

    public function test_delete_shows_error_when_article_not_found(): void
    {
        $controller = $this->createControllerWithMockedRedirect();

        $this->csrf->method('validateFromPost');

        // Delete returns false when article doesn't exist
        $this->articleRepo->expects($this->once())
            ->method('delete')
            ->with(999)
            ->willReturn(false);

        $controller->delete(999);

        $this->assertEquals('error', $_SESSION['flash']['type']);
        $this->assertStringContainsString('Failed to delete article', $_SESSION['flash']['message']);
    }

    // ============================================
    // Retry Failed Article
    // ============================================

    public function test_retry_validates_csrf_token(): void
    {
        $this->csrf->expects($this->once())
            ->method('validateFromPost')
            ->willThrowException(new SecurityException('CSRF validation failed'));

        $this->expectException(SecurityException::class);

        $this->controller->retry(1);
    }

    public function test_retry_throws_exception_if_article_not_found(): void
    {
        $this->csrf->method('validateFromPost');

        $this->articleRepo->expects($this->once())
            ->method('findById')
            ->with(999)
            ->willReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Article not found: 999');

        $this->controller->retry(999);
    }

    public function test_retry_resets_article_status(): void
    {
        $article = [
            'id' => 1,
            'status' => 'failed',
            'retry_count' => 2,
        ];

        $controller = $this->createControllerWithMockedRedirect();

        $this->csrf->method('validateFromPost');
        $this->articleRepo->method('findById')->willReturn($article);

        $this->articleRepo->expects($this->once())
            ->method('update')
            ->with(1, [
                'status' => 'pending',
                'retry_count' => 0,
                'next_retry_at' => null,
                'error_message' => null,
                'last_error' => null,
            ])
            ->willReturn(true);

        $controller->retry(1);

        $this->assertEquals('success', $_SESSION['flash']['type']);
        $this->assertEquals('Article queued for retry', $_SESSION['flash']['message']);
    }

    public function test_retry_logs_action(): void
    {
        $article = ['id' => 1, 'status' => 'failed'];

        $controller = $this->createControllerWithMockedRedirect();

        $this->csrf->method('validateFromPost');
        $this->articleRepo->method('findById')->willReturn($article);
        $this->articleRepo->method('update')->willReturn(true);

        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                'Article retry initiated',
                $this->callback(function ($context) {
                    return $context['category'] === 'article_controller'
                        && $context['article_id'] === 1;
                })
            );

        $controller->retry(1);
    }

    // ============================================
    // XSS Prevention
    // ============================================

    public function test_index_provides_output_escaper(): void
    {
        $this->articleRepo->method('findWithFilters')->willReturn([]);
        $this->articleRepo->method('countWithFilters')->willReturn(0);
        $this->csrf->method('getToken')->willReturn('test_token');

        $result = $this->controller->index();

        $this->assertInstanceOf(OutputEscaper::class, $result['escaper']);
    }

    public function test_view_provides_output_escaper(): void
    {
        $article = ['id' => 1, 'rss_title' => 'Test'];

        $this->articleRepo->method('findById')->willReturn($article);
        $this->csrf->method('getToken')->willReturn('test_token');

        $result = $this->controller->view(1);

        $this->assertInstanceOf(OutputEscaper::class, $result['escaper']);
    }

    // ============================================
    // Flash Messages
    // ============================================

    public function test_flash_messages_are_cleared_after_retrieval(): void
    {
        // Start session and set flash message
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Test'];

        $this->articleRepo->method('findWithFilters')->willReturn([]);
        $this->articleRepo->method('countWithFilters')->willReturn(0);
        $this->csrf->method('getToken')->willReturn('test_token');

        $result = $this->controller->index();

        // Flash should be in result
        $this->assertNotNull($result['flash']);
        $this->assertEquals('success', $result['flash']['type']);

        // Flash should be cleared from session
        $this->assertArrayNotHasKey('flash', $_SESSION);
    }

    public function test_index_returns_null_flash_when_none_exists(): void
    {
        $this->articleRepo->method('findWithFilters')->willReturn([]);
        $this->articleRepo->method('countWithFilters')->willReturn(0);
        $this->csrf->method('getToken')->willReturn('test_token');

        $result = $this->controller->index();

        $this->assertNull($result['flash']);
    }
}
