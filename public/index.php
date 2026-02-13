<?php

declare(strict_types=1);

// Bootstrap the application
require_once __DIR__ . '/../vendor/autoload.php';

use Unfurl\Core\Database;
use Unfurl\Core\Logger;
use Unfurl\Core\Router;
use Unfurl\Core\TimezoneHelper;
use Unfurl\Controllers\FeedController;
use Unfurl\Controllers\ArticleController;
use Unfurl\Controllers\SettingsController;
use Unfurl\Controllers\ApiController;
use Unfurl\Controllers\LogController;
use Unfurl\Repositories\FeedRepository;
use Unfurl\Repositories\ArticleRepository;
use Unfurl\Repositories\ApiKeyRepository;
use Unfurl\Repositories\LogRepository;
use Unfurl\Services\ProcessingQueue;
use Unfurl\Security\Auth;
use Unfurl\Security\CsrfToken;
use Unfurl\Security\InputValidator;
use Unfurl\Security\OutputEscaper;
use Unfurl\Security\UrlValidator;
use Unfurl\Services\UnfurlService;
use Unfurl\Services\ArticleExtractor;

// Load configuration
$config = require __DIR__ . '/../config.php';

// Error handling
if ($config['app']['debug']) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}

// Set timezone
date_default_timezone_set($config['app']['timezone']);

// Initialize timezone helper (for UTC database storage and local display)
$timezone = new TimezoneHelper($config['app']['timezone']);

// Initialize core services
$db = new Database($config);
$logger = new Logger(__DIR__ . '/../storage/logs', Logger::DEBUG, $timezone, $db);

// Initialize repositories
$feedRepo = new FeedRepository($db, $timezone);
$articleRepo = new ArticleRepository($db, $timezone);
$apiKeyRepo = new ApiKeyRepository($db, $timezone);
$logRepo = new LogRepository($db, $timezone);

// Initialize services
$isProduction = ($config['app']['env'] ?? 'development') === 'production';
$auth = new Auth($db->getConnection(), $isProduction);
$queue = new ProcessingQueue($articleRepo, $logger, $timezone);
$csrf = new CsrfToken();
$validator = new InputValidator();
$escaper = new OutputEscaper();
$unfurlService = new UnfurlService($logger, null, false); // Non-headless to avoid bot detection
$extractor = new ArticleExtractor();

// Initialize router
$router = new Router();

// ============================================================================
// Authentication Check
// ============================================================================

// Require authentication for all web UI routes (but not RSS/API endpoints)
$currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$publicPaths = ['/login', '/pending-approval', '/health', '/api/health'];

// Allow unauthenticated access to RSS feeds and API endpoints (they use API keys)
$isPublicEndpoint = in_array($currentPath, $publicPaths)
    || str_starts_with($currentPath, '/feed')
    || str_starts_with($currentPath, '/api/');

if (!$isPublicEndpoint) {
    $auth->requireApproval();
}

// ============================================================================
// Routes
// ============================================================================

// Authentication Routes
$router->get('/login', function () {
    require __DIR__ . '/../views/login.php';
});

$router->get('/pending-approval', function () use ($auth) {
    $user = $auth->getCurrentUser();
    require __DIR__ . '/../views/pending-approval.php';
});

$router->get('/logout', function () use ($auth) {
    header('Location: ' . $auth->getLogoutUrl());
    exit;
});

// Home - redirect to feeds
$router->get('/', function () {
    header('Location: /feeds');
    exit;
});

// Feeds Routes
$router->get('/feeds', function () use ($auth, $feedRepo, $queue, $csrf, $validator, $escaper, $logger, $timezone) {
    $controller = new FeedController($feedRepo, $queue, $csrf, $validator, $escaper, $logger);
    $data = $controller->index();

    // Add pagination variables expected by the view
    $data['total_count'] = count($data['feeds'] ?? []);
    $data['page'] = 1;
    $data['per_page'] = 50;
    $data['timezone'] = $timezone;

    extract($data);
    require __DIR__ . '/../views/feeds/index.php';
});

$router->get('/feeds/create', function () use ($csrf, $escaper) {
    require __DIR__ . '/../views/feeds/create.php';
});

$router->post('/feeds', function () use ($feedRepo, $queue, $csrf, $validator, $escaper, $logger) {
    $controller = new FeedController($feedRepo, $queue, $csrf, $validator, $escaper, $logger);

    // Map form fields to controller expectations
    $data = $_POST;
    if (isset($data['result_limit'])) {
        $data['limit'] = $data['result_limit'];
    }

    // Auto-generate Google News RSS URL from topic
    // Always regenerate to ensure it matches the topic
    if (!empty($data['topic'])) {
        $searchQuery = urlencode($data['topic']);
        $data['url'] = "https://news.google.com/rss/search?q={$searchQuery}&hl=en-US&gl=US&ceid=US:en";
    }

    $result = $controller->create($data);

    if ($result['status'] === 'success') {
        // Set success message in session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['flash'] = [
            'type' => 'success',
            'message' => 'Feed created successfully!'
        ];

        header('Location: /feeds');
        exit;
    }

    // Show error
    $errors = $result['errors'] ?? [];
    $error = $result['message'] ?? 'Failed to create feed';
    require __DIR__ . '/../views/feeds/create.php';
});

$router->get('/feeds/{id}/edit', function ($id) use ($feedRepo, $csrf, $escaper, $logger, $timezone) {
    $feed = $feedRepo->findById((int)$id);
    if (!$feed) {
        http_response_code(404);
        require __DIR__ . '/../public/404.php';
        exit;
    }
    require __DIR__ . '/../views/feeds/edit.php';
});

$router->post('/feeds/{id}/edit', function ($id) use ($feedRepo, $queue, $csrf, $validator, $escaper, $logger) {
    $controller = new FeedController($feedRepo, $queue, $csrf, $validator, $escaper, $logger);
    $result = $controller->edit((int)$id, $_POST);

    if ($result['status'] === 'success') {
        header('Location: /feeds');
        exit;
    }

    $feed = $feedRepo->findById((int)$id);
    $error = $result['message'] ?? 'Failed to update feed';
    require __DIR__ . '/../views/feeds/edit.php';
});

$router->post('/feeds/{id}/delete', function ($id) use ($feedRepo, $queue, $csrf, $validator, $escaper, $logger) {
    $controller = new FeedController($feedRepo, $queue, $csrf, $validator, $escaper, $logger);
    $controller->delete((int)$id, $_POST);
    header('Location: /feeds');
    exit;
});

$router->post('/feeds/{id}/process', function ($id) use ($feedRepo, $queue, $csrf, $validator, $escaper, $logger) {
    $controller = new FeedController($feedRepo, $queue, $csrf, $validator, $escaper, $logger);
    $controller->process((int)$id, $_POST);
    header('Location: /feeds');
    exit;
});

// Articles Routes
$router->get('/articles', function () use ($auth, $articleRepo, $feedRepo, $queue, $csrf, $escaper, $logger, $timezone) {
    $controller = new ArticleController($articleRepo, $queue, $csrf, $escaper, $logger);
    $data = $controller->index();

    // Transform pagination data for view compatibility
    $pagination = $data['pagination'] ?? [];
    $per_page = (int)($pagination['limit'] ?? 20);
    $offset = (int)($pagination['offset'] ?? 0);
    $total_count = (int)($pagination['total'] ?? 0);

    // Prevent division by zero
    $per_page = max($per_page, 1);

    // Calculate page numbers
    $current_page = (int)(floor($offset / $per_page) + 1);
    $page_count = (int)(ceil($total_count / max($per_page, 1)));

    // Set pagination variables directly (not nested in array)
    $data['per_page'] = $per_page;
    $data['current_page'] = $current_page;
    $data['total_count'] = $total_count;
    $data['page_count'] = $page_count;
    $data['timezone'] = $timezone;

    // Add filter options
    $data['topics'] = array_column($feedRepo->findAll(), 'topic');
    $data['statuses'] = [
        'pending' => 'Pending',
        'success' => 'Success',
        'failed' => 'Failed',
    ];

    extract($data);
    require __DIR__ . '/../views/articles/index.php';
});

$router->get('/articles/{id}', function ($id) use ($articleRepo, $queue, $csrf, $escaper, $logger, $timezone) {
    $article = $articleRepo->findById((int)$id);
    if (!$article) {
        http_response_code(404);
        require __DIR__ . '/../public/404.php';
        exit;
    }
    require __DIR__ . '/../views/articles/edit.php';
});

$router->get('/articles/{id}/edit', function ($id) use ($articleRepo, $csrf, $escaper, $logger, $timezone) {
    $article = $articleRepo->findById((int)$id);
    if (!$article) {
        http_response_code(404);
        require __DIR__ . '/../public/404.php';
        exit;
    }
    require __DIR__ . '/../views/articles/edit.php';
});

$router->post('/articles/{id}/edit', function ($id) use ($articleRepo, $queue, $csrf, $escaper, $logger) {
    $controller = new ArticleController($articleRepo, $queue, $csrf, $escaper, $logger);
    try {
        $controller->edit((int)$id);
        header('Location: /articles/' . $id);
        exit;
    } catch (\Exception $e) {
        header('Location: /articles/' . $id);
        exit;
    }
});

$router->get('/articles/process', function () use ($feedRepo, $csrf, $escaper) {
    // Redirect to the main process page
    header('Location: /process');
    exit;
});

$router->post('/articles/{id}/delete', function ($id) use ($articleRepo, $queue, $csrf, $escaper, $logger) {
    $controller = new ArticleController($articleRepo, $queue, $csrf, $escaper, $logger);
    try {
        $controller->delete((int)$id);
    } catch (\Exception $e) {
        // Ignore exception from redirect
    }
    header('Location: /articles');
    exit;
});

$router->post('/articles/bulk-delete', function () use ($articleRepo, $queue, $csrf, $escaper, $logger) {
    $controller = new ArticleController($articleRepo, $queue, $csrf, $escaper, $logger);
    try {
        $controller->bulkDelete();
    } catch (\Exception $e) {
        // Ignore exception from redirect
    }
    header('Location: /articles');
    exit;
});

$router->post('/articles/{id}/retry', function ($id) use ($articleRepo, $queue, $csrf, $escaper, $logger) {
    $controller = new ArticleController($articleRepo, $queue, $csrf, $escaper, $logger);
    try {
        $controller->retry((int)$id);
    } catch (\Exception $e) {
        // Ignore exception from redirect
    }
    header('Location: /articles/' . $id);
    exit;
});

// Logs Routes
$router->get('/logs', function () use ($logRepo, $escaper, $logger, $timezone) {
    $controller = new LogController($logRepo, $escaper, $logger);
    $data = $controller->index();
    $data['timezone'] = $timezone;
    extract($data);
    require __DIR__ . '/../views/logs/index.php';
});

$router->get('/logs/{id}', function ($id) use ($logRepo, $escaper, $logger, $timezone) {
    $controller = new LogController($logRepo, $escaper, $logger);
    $data = $controller->view((int)$id);

    if (!$data) {
        http_response_code(404);
        require __DIR__ . '/../public/404.php';
        exit;
    }

    $data['timezone'] = $timezone;
    extract($data);
    require __DIR__ . '/../views/logs/view.php';
});

// Settings Routes
$router->get('/settings', function () use ($auth, $apiKeyRepo, $csrf, $escaper, $logger, $config) {
    $controller = new SettingsController($apiKeyRepo, $csrf, $logger);
    $data = $controller->index();
    $data['config'] = $config; // Pass config to view
    extract($data);
    require __DIR__ . '/../views/settings.php';
});

$router->post('/settings/api-keys/create', function () use ($apiKeyRepo, $csrf, $escaper, $logger) {
    $controller = new SettingsController($apiKeyRepo, $csrf, $logger);
    $result = $controller->createApiKey($_POST);

    if (isset($result['redirect'])) {
        header('Location: ' . $result['redirect']);
        exit;
    }

    $data = $controller->index();
    extract($data);
    extract($result);
    require __DIR__ . '/../views/settings.php';
});

$router->post('/settings/api-keys/{id}/toggle', function ($id) use ($apiKeyRepo, $csrf, $logger) {
    $controller = new SettingsController($apiKeyRepo, $csrf, $logger);
    $controller->toggleApiKey((int)$id, $_POST);
    header('Location: /settings');
    exit;
});

$router->post('/settings/api-keys/{id}/delete', function ($id) use ($apiKeyRepo, $csrf, $logger) {
    $controller = new SettingsController($apiKeyRepo, $csrf, $logger);
    $controller->deleteApiKey((int)$id, $_POST);
    header('Location: /settings');
    exit;
});

$router->post('/settings/retention', function () use ($apiKeyRepo, $csrf, $logger, $config, $articleRepo) {
    $controller = new SettingsController($apiKeyRepo, $csrf, $logger);
    $result = $controller->updateRetention($_POST);

    if (isset($result['redirect'])) {
        header('Location: ' . $result['redirect']);
        exit;
    }

    $data = $controller->index();
    extract($data);
    require __DIR__ . '/../views/settings.php';
});

$router->post('/settings/cleanup', function () use ($apiKeyRepo, $csrf, $logger, $config, $articleRepo) {
    $controller = new SettingsController($apiKeyRepo, $csrf, $logger);
    $result = $controller->runCleanup($_POST, $articleRepo, $config);

    if (isset($result['redirect'])) {
        header('Location: ' . $result['redirect']);
        exit;
    }

    $data = $controller->index();
    extract($data);
    require __DIR__ . '/../views/settings.php';
});

$router->post('/settings/processing', function () use ($apiKeyRepo, $csrf, $logger, $config) {
    $controller = new SettingsController($apiKeyRepo, $csrf, $logger);
    $result = $controller->updateProcessing($_POST);

    if (isset($result['redirect'])) {
        header('Location: ' . $result['redirect']);
        exit;
    }

    $data = $controller->index();
    extract($data);
    require __DIR__ . '/../views/settings.php';
});

// Process Page
$router->get('/process', function () use ($auth, $feedRepo, $csrf, $escaper, $timezone) {
    $feeds = $feedRepo->findAll();
    require __DIR__ . '/../views/process.php';
});

// Dashboard (if it exists)
$router->get('/dashboard', function () use ($auth, $csrf, $escaper, $feedRepo, $articleRepo, $timezone) {
    // Calculate comprehensive metrics
    $allFeeds = $feedRepo->findAll();
    $totalFeeds = count($allFeeds);
    $enabledFeeds = count(array_filter($allFeeds, fn($feed) => $feed['enabled']));

    // Article metrics
    $successCount = $articleRepo->countWithFilters(['status' => 'success']);
    $failedCount = $articleRepo->countWithFilters(['status' => 'failed']);
    $pendingCount = $articleRepo->countWithFilters(['status' => 'pending']);
    $totalProcessed = $successCount + $failedCount;

    // Calculate rates
    $successRate = $totalProcessed > 0 ? round(($successCount / $totalProcessed) * 100, 1) : 0;
    $errorRate = $totalProcessed > 0 ? round(($failedCount / $totalProcessed) * 100, 1) : 0;

    // Get last processed article (success or failed, but not pending)
    $lastProcessedArticles = $articleRepo->findByStatus('success');
    if (empty($lastProcessedArticles)) {
        $lastProcessedArticles = $articleRepo->findByStatus('failed');
    }
    $lastProcessed = !empty($lastProcessedArticles)
        ? $timezone->formatLocal($lastProcessedArticles[0]['processed_at'] ?? $lastProcessedArticles[0]['created_at'], 'M d, Y g:i A')
        : 'Never';

    // Get recent activity (last 10 articles)
    $recentArticles = $articleRepo->findWithFilters([], 10, 0);
    $recent_activity = [];
    foreach ($recentArticles as $article) {
        $status = $article['status'];
        $title = $article['rss_title'] ?? $article['page_title'] ?? $article['og_title'] ?? 'Untitled';
        $recent_activity[] = [
            'type' => $status === 'pending' ? 'info' : ($status === 'success' ? 'success' : 'error'),
            'message' => $status === 'pending'
                ? 'Pending: ' . $title
                : ($status === 'success' ? 'Processed: ' . $title : 'Failed: ' . $title),
            'time_ago' => $timezone->formatLocal($article['updated_at'] ?? $article['created_at'], 'M d, Y g:i A')
        ];
    }

    // Assemble metrics
    $metrics = [
        'feeds_total' => $totalFeeds,
        'feeds_enabled' => $enabledFeeds,
        'articles_success' => $successCount,
        'articles_failed' => $failedCount,
        'articles_pending' => $pendingCount,
        'queue_pending' => 0, // No queue system yet
        'queue_ready' => 0,
        'success_rate' => $successRate,
        'error_rate' => $errorRate,
        'last_processed' => $lastProcessed
    ];

    require __DIR__ . '/../views/dashboard.php';
});

// API Routes - Fetch RSS feeds and create pending articles
$router->post('/api/feeds/fetch', function () use ($feedRepo, $articleRepo, $csrf, $logger) {
    set_time_limit(60);
    ob_start();
    header('Content-Type: application/json');

    try {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $jsonData = json_decode(file_get_contents('php://input'), true) ?? [];
        $csrfTokenHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;

        if ($csrfTokenHeader) {
            try {
                $csrf->validate($csrfTokenHeader);
            } catch (\Exception $e) {
                ob_clean();
                http_response_code(403);
                echo json_encode(['error' => 'CSRF token validation failed']);
                ob_end_flush();
                exit;
            }
        }

        $feedIds = $jsonData['feed_ids'] ?? [];
        if (empty($feedIds)) {
            throw new \Exception('No feed IDs provided');
        }

        $allArticleIds = [];

        foreach ($feedIds as $feedId) {
            $feed = $feedRepo->findById((int)$feedId);
            if (!$feed) {
                continue;
            }

            // Fetch RSS feed
            $ch = curl_init($feed['url']);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; Unfurl/1.0)',
                CURLOPT_FOLLOWLOCATION => true,
            ]);
            $response = curl_exec($ch);
            curl_close($ch);

            if ($response === false) {
                continue;
            }

            // Parse RSS
            libxml_use_internal_errors(true);
            $xml = new SimpleXMLElement($response);
            libxml_clear_errors();

            $limit = $feed['result_limit'] ?? 10;
            $count = 0;

            foreach ($xml->channel->item as $item) {
                if ($count >= $limit) break;

                $googleNewsUrl = (string)$item->link;

                // Check if article already exists by google_news_url
                $existing = $articleRepo->findWithFilters(['google_news_url' => $googleNewsUrl], 1, 0);
                if (!empty($existing)) {
                    // Article already exists, skip it
                    continue;
                }

                $pubDate = null;
                if (!empty((string)$item->pubDate)) {
                    $timestamp = strtotime((string)$item->pubDate);
                    if ($timestamp !== false) {
                        $pubDate = date('Y-m-d H:i:s', $timestamp);
                    }
                }

                // Create pending article
                $articleId = $articleRepo->create([
                    'feed_id' => $feed['id'],
                    'topic' => $feed['topic'],
                    'google_news_url' => $googleNewsUrl,
                    'rss_title' => (string)$item->title,
                    'rss_description' => (string)$item->description,
                    'rss_source' => (string)($item->source ?? ''),
                    'pub_date' => $pubDate,
                    'status' => 'pending',
                ]);

                $allArticleIds[] = $articleId;
                $count++;
            }

            // Update feed's last_processed_at
            $feedRepo->updateLastProcessedAt($feed['id']);
        }

        ob_clean();
        echo json_encode([
            'success' => true,
            'article_ids' => $allArticleIds,
            'total' => count($allArticleIds)
        ]);
    } catch (\Exception $e) {
        ob_clean();
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    ob_end_flush();
});

// API Routes - Process single article
$router->post('/api/articles/{id}/process', function ($id) use ($articleRepo, $unfurlService, $logger) {
    set_time_limit(30);
    ob_start();
    header('Content-Type: application/json');

    try {
        // No CSRF check needed here - already validated in fetch endpoint
        $article = $articleRepo->findById((int)$id);
        if (!$article) {
            throw new \Exception('Article not found');
        }

        // Process with Playwright
        $result = $unfurlService->processArticle((int)$id, $article['google_news_url']);

        if ($result['status'] === 'success' && !empty($result['finalUrl'])) {
            // Update article with results
            try {
                $articleRepo->update((int)$id, [
                    'final_url' => $result['finalUrl'],
                    'status' => 'success',
                    'page_title' => $result['pageTitle'] ?? null,
                    'og_title' => $result['ogTitle'] ?? null,
                    'og_description' => $result['ogDescription'] ?? null,
                    'og_image' => $result['ogImage'] ?? null,
                    'og_url' => $result['ogUrl'] ?? null,
                    'og_site_name' => $result['ogSiteName'] ?? null,
                    'twitter_image' => $result['twitterImage'] ?? null,
                    'author' => $result['author'] ?? null,
                    'article_content' => $result['articleContent'] ?? null,
                    'word_count' => $result['wordCount'] ?? null,
                    'processed_at' => date('Y-m-d H:i:s'),
                ]);

                ob_clean();
                echo json_encode([
                    'success' => true,
                    'article_id' => (int)$id,
                    'final_url' => $result['finalUrl']
                ]);
            } catch (\PDOException $e) {
                // Handle duplicate final_url gracefully
                if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                    ob_clean();
                    echo json_encode([
                        'success' => true,
                        'article_id' => (int)$id,
                        'duplicate' => true,
                        'final_url' => $result['finalUrl']
                    ]);
                } else {
                    throw $e;
                }
            }
        } else {
            // Mark as failed
            $articleRepo->update((int)$id, [
                'status' => 'failed',
                'error_message' => $result['error'] ?? 'Unknown error',
            ]);

            ob_clean();
            echo json_encode([
                'success' => false,
                'article_id' => (int)$id,
                'error' => $result['error'] ?? 'Processing failed'
            ]);
        }
    } catch (\Exception $e) {
        ob_clean();
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    ob_end_flush();
});

$router->get('/api/health', function () use ($db) {
    header('Content-Type: application/json');
    try {
        $db->query('SELECT 1');
        echo json_encode(['status' => 'ok', 'database' => 'connected']);
    } catch (\Exception $e) {
        http_response_code(503);
        echo json_encode(['status' => 'error', 'database' => 'disconnected']);
    }
    exit;
});

// Health check
$router->get('/health', function () use ($db) {
    header('Content-Type: application/json');
    try {
        $db->query('SELECT 1');
        echo json_encode(['status' => 'ok', 'database' => 'connected', 'timestamp' => date('c')]);
    } catch (\Exception $e) {
        http_response_code(503);
        echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    }
    exit;
});

// Authenticated API RSS Feed endpoint
$router->get('/api/feed', function () use ($apiKeyRepo, $articleRepo, $escaper, $logger) {
    header('Content-Type: application/xml; charset=utf-8');

    try {
        // Validate API key - support both header (recommended) and query parameter (for RSS readers)
        $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['key'] ?? null;

        if (empty($apiKey)) {
            http_response_code(401);
            echo '<?xml version="1.0" encoding="UTF-8"?><error>Missing API key. Provide via X-API-Key header or ?key= parameter</error>';
            exit;
        }

        $apiKeyData = $apiKeyRepo->findByKeyValue($apiKey);

        if ($apiKeyData === null || !$apiKeyData['enabled']) {
            http_response_code(401);
            echo '<?xml version="1.0" encoding="UTF-8"?><error>Invalid or disabled API key</error>';
            exit;
        }

        // Update last used timestamp
        $apiKeyRepo->updateLastUsedAt($apiKeyData['id']);

        $logger->info('API feed accessed', [
            'category' => 'api',
            'api_key_id' => $apiKeyData['id'],
            'api_key_name' => $apiKeyData['key_name'],
        ]);

        // Get query parameters
        $feedId = isset($_GET['feed_id']) ? (int)$_GET['feed_id'] : null;
        $topic = $_GET['topic'] ?? null;
        $status = $_GET['status'] ?? 'success';
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;

        // Get articles with filters
        $filters = ['status' => $status];
        if ($feedId) {
            $filters['feed_id'] = $feedId;
        }
        if ($topic) {
            $filters['topic'] = $topic;
        }

        $articles = $articleRepo->findWithFilters($filters, $limit, 0);

        // Generate RSS
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<rss version="2.0" xmlns:content="http://purl.org/rss/1.0/modules/content/">' . "\n";
        echo '<channel>' . "\n";
        echo '<title>' . $escaper->html($topic ? "Unfurl - {$topic}" : 'Unfurl RSS Feed') . '</title>' . "\n";
        echo '<link>' . $escaper->html($_SERVER['HTTP_HOST'] ?? 'localhost') . '</link>' . "\n";
        echo '<description>Google News article feed processed by Unfurl</description>' . "\n";

        foreach ($articles as $article) {
            echo '<item>' . "\n";
            echo '<title>' . $escaper->html($article['rss_title'] ?? 'No title') . '</title>' . "\n";
            echo '<link>' . $escaper->html($article['final_url'] ?? $article['google_news_url']) . '</link>' . "\n";
            echo '<description>' . $escaper->html($article['rss_description'] ?? '') . '</description>' . "\n";
            echo '<pubDate>' . date('r', strtotime($article['pub_date'] ?? $article['processed_at'] ?? 'now')) . '</pubDate>' . "\n";

            if (!empty($article['article_content'])) {
                echo '<content:encoded><![CDATA[' . $article['article_content'] . ']]></content:encoded>' . "\n";
            }

            echo '</item>' . "\n";
        }

        echo '</channel>' . "\n";
        echo '</rss>';
        exit;

    } catch (\Exception $e) {
        http_response_code(500);
        echo '<?xml version="1.0" encoding="UTF-8"?><error>' . $escaper->html($e->getMessage()) . '</error>';
        exit;
    }
});

// RSS Feed endpoint (unauthenticated - for local/dev use)
$router->get('/feed', function () use ($articleRepo, $escaper) {
    $topic = $_GET['topic'] ?? null;
    $status = $_GET['status'] ?? 'success';
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;

    // Get articles
    $filters = ['status' => $status];
    if ($topic) {
        $filters['topic'] = $topic;
    }

    $articles = $articleRepo->findWithFilters($filters, $limit, 0);

    // Generate RSS
    header('Content-Type: application/xml; charset=utf-8');
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<rss version="2.0" xmlns:content="http://purl.org/rss/1.0/modules/content/">' . "\n";
    echo '<channel>' . "\n";
    echo '<title>' . $escaper->html($topic ? "Unfurl - {$topic}" : 'Unfurl RSS Feed') . '</title>' . "\n";
    echo '<link>' . $escaper->html($_SERVER['HTTP_HOST'] ?? 'localhost') . '</link>' . "\n";
    echo '<description>Google News article feed processed by Unfurl</description>' . "\n";

    foreach ($articles as $article) {
        echo '<item>' . "\n";
        echo '<title>' . $escaper->html($article['rss_title'] ?? 'No title') . '</title>' . "\n";
        echo '<link>' . $escaper->html($article['final_url'] ?? $article['google_news_url']) . '</link>' . "\n";
        echo '<description>' . $escaper->html($article['rss_description'] ?? '') . '</description>' . "\n";
        echo '<pubDate>' . date('r', strtotime($article['pub_date'] ?? $article['processed_at'] ?? 'now')) . '</pubDate>' . "\n";

        if (!empty($article['article_content'])) {
            echo '<content:encoded><![CDATA[' . $article['article_content'] . ']]></content:encoded>' . "\n";
        }

        echo '</item>' . "\n";
    }

    echo '</channel>' . "\n";
    echo '</rss>';
    exit;
});

// ============================================================================
// Dispatch Request
// ============================================================================

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

    $router->dispatch($method, $uri);
} catch (\Exception $e) {
    if ($config['app']['debug']) {
        echo '<h1>Error</h1>';
        echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
    } else {
        $logger->error('Application error', [
            'category' => 'router',
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);

        http_response_code(500);
        require __DIR__ . '/500.php';
    }
}
