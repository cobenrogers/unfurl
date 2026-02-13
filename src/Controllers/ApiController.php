<?php

declare(strict_types=1);

namespace Unfurl\Controllers;

use Unfurl\Core\Database;
use Unfurl\Core\Logger;
use Unfurl\Repositories\ApiKeyRepository;
use Unfurl\Repositories\FeedRepository;
use Unfurl\Repositories\ArticleRepository;
use Unfurl\Services\UnfurlService;
use Unfurl\Services\ArticleExtractor;
use Unfurl\Services\GoogleNews\UrlDecoder;
use Unfurl\Services\ProcessingQueue;
use Unfurl\Exceptions\SecurityException;
use SimpleXMLElement;

/**
 * API Controller for Feed Processing
 *
 * Provides API endpoints for:
 * - Feed processing (POST /api.php)
 * - Health check (GET /health.php)
 *
 * Security:
 * - API key authentication (X-API-Key header)
 * - Rate limiting (60 requests/min per API key)
 * - Input validation
 * - Error handling without exposing internals
 */
class ApiController
{
    /**
     * Rate limit: 60 requests per minute
     */
    private const RATE_LIMIT = 60;

    /**
     * Rate limit window in seconds (1 minute)
     */
    private const RATE_LIMIT_WINDOW = 60;

    /**
     * Rate limit tracking (in-memory, per API key)
     * Format: ['api_key_id' => ['timestamp1', 'timestamp2', ...]]
     */
    private static array $rateLimitTracker = [];

    private ApiKeyRepository $apiKeyRepo;
    private FeedRepository $feedRepo;
    private ArticleRepository $articleRepo;
    private UnfurlService $unfurlService;
    private UrlDecoder $urlDecoder;
    private ArticleExtractor $extractor;
    private ProcessingQueue $queue;
    private Logger $logger;
    private string $processor;

    public function __construct(
        ApiKeyRepository $apiKeyRepo,
        FeedRepository $feedRepo,
        ArticleRepository $articleRepo,
        UnfurlService $unfurlService,
        UrlDecoder $urlDecoder,
        ArticleExtractor $extractor,
        ProcessingQueue $queue,
        Logger $logger,
        string $processor = 'node'
    ) {
        $this->apiKeyRepo = $apiKeyRepo;
        $this->feedRepo = $feedRepo;
        $this->articleRepo = $articleRepo;
        $this->unfurlService = $unfurlService;
        $this->urlDecoder = $urlDecoder;
        $this->extractor = $extractor;
        $this->queue = $queue;
        $this->logger = $logger;
        $this->processor = $processor;
    }

    /**
     * Process all enabled feeds
     *
     * POST /api.php
     * Headers: X-API-Key: {api_key_value}
     *
     * @return void Outputs JSON response
     */
    public function processFeeds(): void
    {
        try {
            // Validate API key
            $apiKey = $this->getApiKeyFromHeader();
            $apiKeyData = $this->validateApiKey($apiKey);

            // Check rate limiting
            $this->checkRateLimit($apiKeyData['id']);

            // Get all enabled feeds
            $feeds = $this->feedRepo->findEnabled();

            $stats = [
                'feeds_processed' => 0,
                'articles_created' => 0,
                'articles_failed' => 0,
            ];

            // Process each feed
            foreach ($feeds as $feed) {
                $this->processFeed($feed, $stats);
            }

            // Return success response
            $this->jsonResponse([
                'success' => true,
                'feeds_processed' => $stats['feeds_processed'],
                'articles_created' => $stats['articles_created'],
                'articles_failed' => $stats['articles_failed'],
                'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            ]);
        } catch (\Exception $e) {
            $this->handleError($e);
        }
    }

    /**
     * Process specific feeds (for web UI, no API key required)
     *
     * @param array $feedIds Array of feed IDs to process
     * @return array Processing statistics
     */
    public function processSelectedFeeds(array $feedIds): array
    {
        $stats = [
            'total' => 0,
            'success' => 0,
            'duplicates' => 0,
            'failed' => 0,
            'feeds_processed' => 0,
        ];

        foreach ($feedIds as $feedId) {
            $feed = $this->feedRepo->findById((int)$feedId);
            if (!$feed) {
                continue;
            }

            $feedStats = [
                'feeds_processed' => 0,
                'articles_created' => 0,
                'articles_failed' => 0,
            ];

            $this->processFeed($feed, $feedStats);

            $stats['feeds_processed'] += $feedStats['feeds_processed'];
            $stats['success'] += $feedStats['articles_created'];
            $stats['failed'] += $feedStats['articles_failed'];
            $stats['total'] += $feedStats['articles_created'] + $feedStats['articles_failed'];
        }

        return $stats;
    }

    /**
     * Health check endpoint
     *
     * GET /health.php
     *
     * @return void Outputs JSON response
     */
    public function healthCheck(): void
    {
        try {
            // Check database connectivity by running a simple query
            $this->feedRepo->findAll();

            $this->jsonResponse([
                'status' => 'ok',
                'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Health check failed', [
                'category' => 'api',
                'error' => $e->getMessage(),
            ]);

            $this->jsonResponse([
                'status' => 'error',
                'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            ], 503);
        }
    }

    /**
     * Get API key from X-API-Key header
     *
     * @return string API key value
     * @throws \Exception If header is missing
     */
    private function getApiKeyFromHeader(): string
    {
        // Check for X-API-Key header
        $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? null;

        if (empty($apiKey)) {
            throw new \Exception('Missing X-API-Key header', 401);
        }

        return $apiKey;
    }

    /**
     * Validate API key
     *
     * @param string $apiKey API key value
     * @return array API key data
     * @throws \Exception If API key is invalid
     */
    private function validateApiKey(string $apiKey): array
    {
        $apiKeyData = $this->apiKeyRepo->findByKeyValue($apiKey);

        if ($apiKeyData === null) {
            $this->logger->warning('Invalid API key attempted', [
                'category' => 'api',
                'api_key' => substr($apiKey, 0, 8) . '...',
            ]);

            throw new \Exception('Invalid API key', 401);
        }

        if ($apiKeyData['enabled'] != 1) {
            $this->logger->warning('Disabled API key attempted', [
                'category' => 'api',
                'api_key_id' => $apiKeyData['id'],
            ]);

            throw new \Exception('API key is disabled', 403);
        }

        // Update last_used_at
        $this->apiKeyRepo->updateLastUsedAt($apiKeyData['id']);

        // Log successful authentication
        $this->logger->info('API key authenticated', [
            'category' => 'api',
            'api_key_id' => $apiKeyData['id'],
            'api_key_name' => $apiKeyData['key_name'],
        ]);

        return $apiKeyData;
    }

    /**
     * Check rate limiting for API key
     *
     * @param int $apiKeyId API key ID
     * @return void
     * @throws \Exception If rate limit exceeded
     */
    private function checkRateLimit(int $apiKeyId): void
    {
        $now = time();

        // Initialize tracking for this API key if not exists
        if (!isset(self::$rateLimitTracker[$apiKeyId])) {
            self::$rateLimitTracker[$apiKeyId] = [];
        }

        // Remove timestamps outside the current window
        self::$rateLimitTracker[$apiKeyId] = array_filter(
            self::$rateLimitTracker[$apiKeyId],
            fn($timestamp) => ($now - $timestamp) < self::RATE_LIMIT_WINDOW
        );

        // Check if rate limit exceeded
        if (count(self::$rateLimitTracker[$apiKeyId]) >= self::RATE_LIMIT) {
            $this->logger->warning('Rate limit exceeded', [
                'category' => 'api',
                'api_key_id' => $apiKeyId,
                'requests' => count(self::$rateLimitTracker[$apiKeyId]),
            ]);

            throw new \Exception('Rate limit exceeded', 429);
        }

        // Add current request timestamp
        self::$rateLimitTracker[$apiKeyId][] = $now;
    }

    /**
     * Process a single feed
     *
     * @param array $feed Feed data
     * @param array &$stats Statistics array (passed by reference)
     * @return void
     */
    private function processFeed(array $feed, array &$stats): void
    {
        try {
            $this->logger->info('Processing feed', [
                'category' => 'api',
                'feed_id' => $feed['id'],
                'topic' => $feed['topic'],
            ]);

            // Fetch and parse RSS feed
            $rssContent = $this->fetchRssFeed($feed['url']);
            $items = $this->parseRssFeed($rssContent);

            // Limit items based on result_limit
            $limit = $feed['result_limit'] ?? 10;
            $items = array_slice($items, 0, $limit);

            // Process each article
            foreach ($items as $item) {
                $this->processArticle($feed, $item, $stats);
            }

            // Update feed's last_processed_at
            $this->feedRepo->updateLastProcessedAt($feed['id']);

            $stats['feeds_processed']++;
        } catch (\Exception $e) {
            $this->logger->error('Feed processing failed', [
                'category' => 'api',
                'feed_id' => $feed['id'],
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Fetch RSS feed content
     *
     * @param string $url RSS feed URL
     * @return string RSS XML content
     * @throws \Exception If fetch fails
     */
    private function fetchRssFeed(string $url): string
    {
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; Unfurl/1.0)',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if ($response === false || !empty($error)) {
            throw new \Exception('Failed to fetch RSS feed: ' . $error);
        }

        if ($httpCode >= 400) {
            throw new \Exception('HTTP error ' . $httpCode . ' when fetching feed');
        }

        return $response;
    }

    /**
     * Parse RSS feed XML into array of items
     *
     * @param string $xmlContent RSS XML content
     * @return array Array of RSS items
     * @throws \Exception If parsing fails
     */
    private function parseRssFeed(string $xmlContent): array
    {
        // Suppress XML parsing warnings
        libxml_use_internal_errors(true);

        try {
            $xml = new SimpleXMLElement($xmlContent);
            libxml_clear_errors();
        } catch (\Exception $e) {
            libxml_clear_errors();
            throw new \Exception('Failed to parse RSS feed: ' . $e->getMessage());
        }

        $items = [];

        foreach ($xml->channel->item as $item) {
            $items[] = [
                'title' => (string) $item->title,
                'link' => (string) $item->link,
                'description' => (string) $item->description,
                'pubDate' => (string) $item->pubDate,
                'source' => (string) ($item->source ?? ''),
            ];
        }

        return $items;
    }

    /**
     * Process a single article from RSS feed
     *
     * @param array $feed Feed data
     * @param array $item RSS item data
     * @param array &$stats Statistics array (passed by reference)
     * @return void
     */
    private function processArticle(array $feed, array $item, array &$stats): void
    {
        try {
            // Choose processing method based on configuration
            if ($this->processor === 'node') {
                $this->processArticleWithNode($feed, $item, $stats);
            } else {
                $this->processArticleWithPhp($feed, $item, $stats);
            }
        } catch (SecurityException $e) {
            // SSRF or security violation - permanent failure
            $this->logger->warning('Security violation during article processing', [
                'category' => 'api',
                'feed_id' => $feed['id'],
                'google_news_url' => $item['link'],
                'error' => $e->getMessage(),
            ]);

            $stats['articles_failed']++;
        } catch (\Exception $e) {
            // Generic error - may be retryable
            $this->handleArticleError($feed, $item, $e, $stats);
        }
    }

    /**
     * Process article using PHP-only approach (UrlDecoder + ArticleExtractor)
     *
     * @param array $feed Feed data
     * @param array $item RSS item data
     * @param array &$stats Statistics array (passed by reference)
     * @return void
     */
    private function processArticleWithPhp(array $feed, array $item, array &$stats): void
    {
        // Decode Google News URL to get final URL
        $finalUrl = $this->urlDecoder->decode($item['link']);

        // Fetch article HTML
        $html = $this->fetchArticleHtml($finalUrl);

        // Extract metadata from HTML
        $metadata = $this->extractor->extract($html, $finalUrl);

        // Parse pubDate
        $pubDate = null;
        if (!empty($item['pubDate'])) {
            $timestamp = strtotime($item['pubDate']);
            if ($timestamp !== false) {
                $pubDate = date('Y-m-d H:i:s', $timestamp);
            }
        }

        // Save article to database with PHP-extracted metadata
        $articleData = [
            'feed_id' => $feed['id'],
            'topic' => $feed['topic'],
            'google_news_url' => $item['link'],
            'rss_title' => $item['title'],
            'pub_date' => $pubDate,
            'rss_description' => $item['description'],
            'rss_source' => $item['source'],
            'final_url' => $finalUrl,
            'status' => 'success',
            'page_title' => $metadata['page_title'] ?? null,
            'og_title' => $metadata['og_title'] ?? null,
            'og_description' => $metadata['og_description'] ?? null,
            'og_image' => $metadata['og_image'] ?? null,
            'og_url' => $metadata['og_url'] ?? null,
            'og_site_name' => $metadata['og_site_name'] ?? null,
            'twitter_image' => $metadata['twitter_image'] ?? null,
            'twitter_card' => $metadata['twitter_card'] ?? null,
            'author' => $metadata['author'] ?? null,
            'article_content' => $metadata['article_content'] ?? null,
            'word_count' => $metadata['word_count'] ?? null,
            'categories' => !empty($metadata['categories']) ? json_encode($metadata['categories']) : null,
            'processed_at' => date('Y-m-d H:i:s'),
        ];

        try {
            $this->articleRepo->create($articleData);
            $stats['articles_created']++;

            $this->logger->info('Article processed successfully (PHP)', [
                'category' => 'api',
                'feed_id' => $feed['id'],
                'final_url' => $finalUrl,
                'processor' => 'php',
            ]);
        } catch (\PDOException $e) {
            // Handle duplicate final_url gracefully (unique constraint)
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                $this->logger->debug('Duplicate article skipped', [
                    'category' => 'api',
                    'feed_id' => $feed['id'],
                    'final_url' => $finalUrl,
                ]);
            } else {
                throw $e;
            }
        }
    }

    /**
     * Process article using Node.js/Playwright approach
     *
     * @param array $feed Feed data
     * @param array $item RSS item data
     * @param array &$stats Statistics array (passed by reference)
     * @return void
     */
    private function processArticleWithNode(array $feed, array $item, array &$stats): void
    {
        // Create a temporary article ID for Playwright processing
        // We'll use a temporary negative ID, then replace with real ID after saving
        $tempId = -1;

        // Use Playwright browser automation to follow Google News URL
        $result = $this->unfurlService->processArticle($tempId, $item['link']);

        // Check if Playwright processing was successful
        if ($result['status'] !== 'success') {
            throw new \Exception($result['error'] ?? 'Unfurl processing failed');
        }

        // Ensure we got a final URL
        if (empty($result['finalUrl'])) {
            throw new \Exception('No final URL returned from unfurl service');
        }

        $finalUrl = $result['finalUrl'];

        // Parse pubDate
        $pubDate = null;
        if (!empty($item['pubDate'])) {
            $timestamp = strtotime($item['pubDate']);
            if ($timestamp !== false) {
                $pubDate = date('Y-m-d H:i:s', $timestamp);
            }
        }

        // Save article to database with Playwright metadata
        $articleData = [
            'feed_id' => $feed['id'],
            'topic' => $feed['topic'],
            'google_news_url' => $item['link'],
            'rss_title' => $item['title'],
            'pub_date' => $pubDate,
            'rss_description' => $item['description'],
            'rss_source' => $item['source'],
            'final_url' => $finalUrl,
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
        ];

        try {
            $this->articleRepo->create($articleData);
            $stats['articles_created']++;

            $this->logger->info('Article processed successfully (Node)', [
                'category' => 'api',
                'feed_id' => $feed['id'],
                'final_url' => $finalUrl,
                'processor' => 'node',
            ]);
        } catch (\PDOException $e) {
            // Handle duplicate final_url gracefully (unique constraint)
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                $this->logger->debug('Duplicate article skipped', [
                    'category' => 'api',
                    'feed_id' => $feed['id'],
                    'final_url' => $finalUrl,
                ]);
            } else {
                throw $e;
            }
        }
    }

    /**
     * Fetch article HTML content
     *
     * @param string $url Article URL
     * @return string HTML content
     * @throws \Exception If fetch fails
     */
    private function fetchArticleHtml(string $url): string
    {
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; Unfurl/1.0)',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_ENCODING => '', // Accept all encodings
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if ($response === false || !empty($error)) {
            throw new \Exception('Failed to fetch article: ' . $error);
        }

        if ($httpCode >= 400) {
            throw new \Exception('HTTP ' . $httpCode . ' error when fetching article');
        }

        return $response;
    }

    /**
     * Handle article processing error
     *
     * @param array $feed Feed data
     * @param array $item RSS item data
     * @param \Exception $e Exception
     * @param array &$stats Statistics array (passed by reference)
     * @return void
     */
    private function handleArticleError(array $feed, array $item, \Exception $e, array &$stats): void
    {
        try {
            // Create failed article record
            $articleData = [
                'feed_id' => $feed['id'],
                'topic' => $feed['topic'],
                'google_news_url' => $item['link'],
                'rss_title' => $item['title'],
                'rss_description' => $item['description'],
                'rss_source' => $item['source'],
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'retry_count' => 0,
            ];

            $articleId = $this->articleRepo->create($articleData);

            // Use ProcessingQueue to determine if retryable
            $this->queue->markFailed($articleId, $e->getMessage(), 0);

            $stats['articles_failed']++;

            $this->logger->warning('Article processing failed', [
                'category' => 'api',
                'feed_id' => $feed['id'],
                'article_id' => $articleId,
                'error' => $e->getMessage(),
            ]);
        } catch (\Exception $saveError) {
            // Failed to save error - log it
            $this->logger->error('Failed to save article error', [
                'category' => 'api',
                'feed_id' => $feed['id'],
                'google_news_url' => $item['link'],
                'original_error' => $e->getMessage(),
                'save_error' => $saveError->getMessage(),
            ]);

            $stats['articles_failed']++;
        }
    }

    /**
     * Handle controller-level errors
     *
     * @param \Exception $e Exception
     * @return void Outputs JSON error response
     */
    private function handleError(\Exception $e): void
    {
        $statusCode = $e->getCode() ?: 500;

        // Map invalid codes to valid HTTP codes
        if ($statusCode < 100 || $statusCode > 599) {
            $statusCode = 500;
        }

        // Generic error message for security
        $message = 'An error occurred while processing your request';

        // Use specific messages for known error codes
        if ($statusCode === 401) {
            $message = $e->getMessage();
        } elseif ($statusCode === 403) {
            $message = $e->getMessage();
        } elseif ($statusCode === 429) {
            $message = 'Rate limit exceeded. Please try again later.';
        }

        // Log the error
        $this->logger->error('API error', [
            'category' => 'api',
            'error' => $e->getMessage(),
            'code' => $statusCode,
            'trace' => $e->getTraceAsString(),
        ]);

        $this->jsonResponse([
            'success' => false,
            'error' => $message,
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
        ], $statusCode);
    }

    /**
     * Output JSON response
     *
     * @param array $data Response data
     * @param int $statusCode HTTP status code
     * @return void
     */
    private function jsonResponse(array $data, int $statusCode = 200): void
    {
        // Don't use exit() during testing
        if (!defined('PHPUNIT_RUNNING')) {
            http_response_code($statusCode);
            header('Content-Type: application/json');
            echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            exit;
        } else {
            // In test mode, just output the JSON
            http_response_code($statusCode);
            echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
    }
}
