#!/usr/bin/env php
<?php

/**
 * CLI Article Processing Script
 *
 * Processes RSS feeds and articles via command line for automated/scheduled execution.
 *
 * Usage:
 *   php bin/process-articles.php              # Process all enabled feeds
 *   php bin/process-articles.php --feed-id=1  # Process specific feed
 *   php bin/process-articles.php --verbose    # Show detailed output
 *
 * Designed for cron execution:
 *   */30 * * * * php /path/to/unfurl/bin/process-articles.php
 */

declare(strict_types=1);

// Ensure we're running from command line
if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line\n");
}

// Bootstrap application
require_once __DIR__ . '/../vendor/autoload.php';

use Unfurl\Core\Database;
use Unfurl\Core\Logger;
use Unfurl\Core\TimezoneHelper;
use Unfurl\Repositories\FeedRepository;
use Unfurl\Repositories\ArticleRepository;
use Unfurl\Services\UnfurlService;
use Unfurl\Services\GoogleNews\UrlDecoder;
use Unfurl\Services\ArticleExtractor;
use Unfurl\Services\ProcessingQueue;
use Unfurl\Security\UrlValidator;
use Unfurl\Exceptions\SecurityException;

// Load configuration
$config = require __DIR__ . '/../config.php';

// Parse command line arguments
$options = getopt('', ['feed-id:', 'verbose', 'help']);

if (isset($options['help'])) {
    echo <<<HELP
Unfurl Article Processing Script

Usage:
  php bin/process-articles.php [OPTIONS]

Options:
  --feed-id=ID    Process only the specified feed ID
  --verbose       Show detailed output
  --help          Show this help message

Examples:
  php bin/process-articles.php              # Process all enabled feeds
  php bin/process-articles.php --feed-id=1  # Process feed ID 1
  php bin/process-articles.php --verbose    # Verbose output

For cron scheduling:
  */30 * * * * php /path/to/unfurl/bin/process-articles.php

HELP;
    exit(0);
}

$verbose = isset($options['verbose']);
$feedId = isset($options['feed-id']) ? (int)$options['feed-id'] : null;

// Initialize services
$timezone = new TimezoneHelper($config['app']['timezone']);
$db = new Database($config);
$logger = new Logger(__DIR__ . '/../storage/logs', Logger::INFO, $timezone, $db);

$feedRepo = new FeedRepository($db, $timezone);
$articleRepo = new ArticleRepository($db, $timezone);
$urlValidator = new UrlValidator();
$urlDecoder = new UrlDecoder($urlValidator);
$extractor = new ArticleExtractor();
$unfurlService = new UnfurlService($logger, null, false);
$queue = new ProcessingQueue($articleRepo, $logger, $timezone);

$processor = $config['processing']['processor'] ?? 'php';

// Log start
$startTime = microtime(true);
$logger->info('CLI article processing started', [
    'category' => 'cli',
    'processor' => $processor,
    'feed_id' => $feedId,
]);

if ($verbose) {
    echo "Starting article processing...\n";
    echo "Processor: $processor\n";
    echo "Feed ID: " . ($feedId ? $feedId : 'all') . "\n\n";
}

// Get feeds to process
$feeds = $feedId ? [$feedRepo->findById($feedId)] : $feedRepo->findEnabled();
$feeds = array_filter($feeds); // Remove nulls

if (empty($feeds)) {
    echo "No feeds to process\n";
    $logger->warning('No feeds to process', ['category' => 'cli']);
    exit(0);
}

// Statistics
$stats = [
    'feeds_processed' => 0,
    'articles_created' => 0,
    'articles_failed' => 0,
    'articles_skipped' => 0,
];

// Process each feed
foreach ($feeds as $feed) {
    if ($verbose) {
        echo "Processing feed: {$feed['topic']} (ID: {$feed['id']})\n";
    }

    $logger->info('Processing feed', [
        'category' => 'cli',
        'feed_id' => $feed['id'],
        'topic' => $feed['topic'],
    ]);

    try {
        // Fetch RSS feed
        $ch = curl_init($feed['url']);
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

        if ($response === false || !empty($error) || $httpCode >= 400) {
            throw new \Exception("Failed to fetch feed: $error (HTTP $httpCode)");
        }

        // Parse RSS
        libxml_use_internal_errors(true);
        $xml = new SimpleXMLElement($response);
        libxml_clear_errors();

        $limit = $feed['result_limit'] ?? 10;
        $count = 0;

        // Process each item
        foreach ($xml->channel->item as $item) {
            if ($count >= $limit) break;

            $googleNewsUrl = (string)$item->link;
            $rssTitle = (string)$item->title;

            if ($verbose) {
                echo "  - {$rssTitle}\n";
            }

            try {
                // Check if article already exists by google_news_url
                $existing = $articleRepo->findWithFilters(['google_news_url' => $googleNewsUrl], 1, 0);
                if (!empty($existing)) {
                    $stats['articles_skipped']++;
                    if ($verbose) {
                        echo "    [SKIP] Already exists\n";
                    }
                    continue;
                }

                // Parse pubDate
                $pubDate = null;
                if (!empty((string)$item->pubDate)) {
                    $timestamp = strtotime((string)$item->pubDate);
                    if ($timestamp !== false) {
                        $pubDate = date('Y-m-d H:i:s', $timestamp);
                    }
                }

                // Process based on configured processor
                if ($processor === 'node') {
                    // Use Node.js/Playwright
                    $tempId = -time(); // Temporary negative ID
                    $result = $unfurlService->processArticle($tempId, $googleNewsUrl);

                    if ($result['status'] !== 'success' || empty($result['finalUrl'])) {
                        throw new \Exception($result['error'] ?? 'Processing failed');
                    }

                    $articleData = [
                        'feed_id' => $feed['id'],
                        'topic' => $feed['topic'],
                        'google_news_url' => $googleNewsUrl,
                        'rss_title' => $rssTitle,
                        'rss_description' => (string)$item->description,
                        'rss_source' => (string)($item->source ?? ''),
                        'pub_date' => $pubDate,
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
                        'sync_pending' => 1, // Mark for sync to production
                    ];
                } else {
                    // Use PHP processor
                    $finalUrl = $urlDecoder->decode($googleNewsUrl);

                    // Fetch article HTML
                    $ch = curl_init($finalUrl);
                    curl_setopt_array($ch, [
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_TIMEOUT => 30,
                        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; Unfurl/1.0)',
                        CURLOPT_SSL_VERIFYPEER => true,
                        CURLOPT_SSL_VERIFYHOST => 2,
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_MAXREDIRS => 5,
                        CURLOPT_ENCODING => '',
                    ]);
                    $html = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);

                    if ($html === false || $httpCode >= 400) {
                        throw new \Exception("Failed to fetch article HTML (HTTP $httpCode)");
                    }

                    // Extract metadata
                    $metadata = $extractor->extract($html, $finalUrl);

                    $articleData = [
                        'feed_id' => $feed['id'],
                        'topic' => $feed['topic'],
                        'google_news_url' => $googleNewsUrl,
                        'rss_title' => $rssTitle,
                        'rss_description' => (string)$item->description,
                        'rss_source' => (string)($item->source ?? ''),
                        'pub_date' => $pubDate,
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
                        'sync_pending' => 1, // Mark for sync to production
                    ];
                }

                // Save article
                try {
                    $articleRepo->create($articleData);
                    $stats['articles_created']++;

                    if ($verbose) {
                        echo "    [SUCCESS] {$articleData['final_url']}\n";
                    }
                } catch (\PDOException $e) {
                    // Handle duplicate final_url
                    if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                        $stats['articles_skipped']++;
                        if ($verbose) {
                            echo "    [SKIP] Duplicate URL\n";
                        }
                    } else {
                        throw $e;
                    }
                }

            } catch (SecurityException $e) {
                // SSRF violation - permanent failure
                $stats['articles_failed']++;
                if ($verbose) {
                    echo "    [FAIL] Security violation: {$e->getMessage()}\n";
                }
                $logger->warning('Security violation', [
                    'category' => 'cli',
                    'feed_id' => $feed['id'],
                    'url' => $googleNewsUrl,
                    'error' => $e->getMessage(),
                ]);
            } catch (\Exception $e) {
                // Generic error
                $stats['articles_failed']++;
                if ($verbose) {
                    echo "    [FAIL] {$e->getMessage()}\n";
                }
                $logger->error('Article processing failed', [
                    'category' => 'cli',
                    'feed_id' => $feed['id'],
                    'url' => $googleNewsUrl,
                    'error' => $e->getMessage(),
                ]);
            }

            $count++;
        }

        // Update feed's last_processed_at
        $feedRepo->updateLastProcessedAt($feed['id']);
        $stats['feeds_processed']++;

        if ($verbose) {
            echo "\n";
        }

    } catch (\Exception $e) {
        $logger->error('Feed processing failed', [
            'category' => 'cli',
            'feed_id' => $feed['id'],
            'error' => $e->getMessage(),
        ]);

        if ($verbose) {
            echo "  [ERROR] {$e->getMessage()}\n\n";
        }
    }
}

// Calculate duration
$duration = round(microtime(true) - $startTime, 2);

// Log completion
$logger->info('CLI article processing completed', [
    'category' => 'cli',
    'duration' => $duration,
    'stats' => $stats,
]);

// Output summary
echo "\n";
echo "========================================\n";
echo "Article Processing Complete\n";
echo "========================================\n";
echo "Feeds processed:   {$stats['feeds_processed']}\n";
echo "Articles created:  {$stats['articles_created']}\n";
echo "Articles failed:   {$stats['articles_failed']}\n";
echo "Articles skipped:  {$stats['articles_skipped']}\n";
echo "Duration:          {$duration}s\n";
echo "========================================\n";

exit(0);
