#!/usr/bin/env php
<?php

/**
 * CLI Production Sync Script
 *
 * Syncs locally processed articles to production server via API.
 *
 * Usage:
 *   php bin/sync-to-production.php              # Sync all pending articles
 *   php bin/sync-to-production.php --batch=50   # Sync in batches of 50
 *   php bin/sync-to-production.php --verbose    # Show detailed output
 *   php bin/sync-to-production.php --dry-run    # Show what would be synced
 *
 * Designed for cron execution:
 *   0 * * * * php /path/to/unfurl/bin/sync-to-production.php
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

// Load configuration
$config = require __DIR__ . '/../config.php';

// Parse command line arguments
$options = getopt('', ['batch:', 'verbose', 'dry-run', 'help']);

if (isset($options['help'])) {
    echo <<<HELP
Unfurl Production Sync Script

Usage:
  php bin/sync-to-production.php [OPTIONS]

Options:
  --batch=N       Sync in batches of N articles (default: 100)
  --verbose       Show detailed output
  --dry-run       Show what would be synced without actually syncing
  --help          Show this help message

Examples:
  php bin/sync-to-production.php              # Sync all pending
  php bin/sync-to-production.php --batch=50   # Batch size 50
  php bin/sync-to-production.php --dry-run    # Preview sync

Configuration (in .env):
  PRODUCTION_URL=https://unfurl.bennernet.com
  PRODUCTION_API_KEY=your_production_api_key_here

For cron scheduling:
  0 * * * * php /path/to/unfurl/bin/sync-to-production.php

HELP;
    exit(0);
}

$verbose = isset($options['verbose']);
$dryRun = isset($options['dry-run']);
$batchSize = isset($options['batch']) ? (int)$options['batch'] : 100;

// Get production configuration from .env
$productionUrl = getenv('PRODUCTION_URL') ?: null;
$productionApiKey = getenv('PRODUCTION_API_KEY') ?: null;

if (empty($productionUrl) || empty($productionApiKey)) {
    echo "ERROR: Missing production configuration!\n";
    echo "Please add to .env file:\n";
    echo "  PRODUCTION_URL=https://unfurl.bennernet.com\n";
    echo "  PRODUCTION_API_KEY=your_api_key_here\n";
    exit(1);
}

// Initialize services
$timezone = new TimezoneHelper($config['app']['timezone']);
$db = new Database($config);
$logger = new Logger(__DIR__ . '/../storage/logs', Logger::INFO, $timezone, $db);

// Log start
$startTime = microtime(true);
$logger->info('CLI production sync started', [
    'category' => 'cli-sync',
    'batch_size' => $batchSize,
    'dry_run' => $dryRun,
]);

if ($verbose) {
    echo "Starting production sync...\n";
    echo "Production URL: $productionUrl\n";
    echo "Batch size: $batchSize\n";
    if ($dryRun) {
        echo "DRY RUN MODE - No actual sync\n";
    }
    echo "\n";
}

// Get pending articles count
$countSql = "SELECT COUNT(*) as count FROM articles WHERE sync_pending = 1";
$countStmt = $db->query($countSql);
$totalPending = $countStmt->fetch()['count'];

if ($totalPending == 0) {
    echo "No articles pending sync\n";
    $logger->info('No articles to sync', ['category' => 'cli-sync']);
    exit(0);
}

if ($verbose) {
    echo "Found $totalPending articles pending sync\n\n";
}

// Statistics
$stats = [
    'total_pending' => $totalPending,
    'batches' => 0,
    'synced' => 0,
    'failed' => 0,
    'errors' => [],
];

// Process in batches
$offset = 0;

while ($offset < $totalPending) {
    $stats['batches']++;

    if ($verbose) {
        $batchNum = $stats['batches'];
        echo "Processing batch $batchNum (offset: $offset)...\n";
    }

    // Fetch batch
    $sql = "SELECT * FROM articles WHERE sync_pending = 1 LIMIT ? OFFSET ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$batchSize, $offset]);
    $articles = $stmt->fetchAll();

    if (empty($articles)) {
        break;
    }

    // Prepare batch for API
    $batchData = [];
    foreach ($articles as $article) {
        $batchData[] = [
            'id' => $article['id'],
            'feed_id' => $article['feed_id'],
            'topic' => $article['topic'],
            'google_news_url' => $article['google_news_url'],
            'rss_title' => $article['rss_title'],
            'pub_date' => $article['pub_date'],
            'rss_description' => $article['rss_description'],
            'rss_source' => $article['rss_source'],
            'final_url' => $article['final_url'],
            'status' => $article['status'],
            'page_title' => $article['page_title'],
            'og_title' => $article['og_title'],
            'og_description' => $article['og_description'],
            'og_image' => $article['og_image'],
            'og_url' => $article['og_url'],
            'og_site_name' => $article['og_site_name'],
            'twitter_image' => $article['twitter_image'],
            'twitter_card' => $article['twitter_card'],
            'author' => $article['author'],
            'article_content' => $article['article_content'],
            'word_count' => $article['word_count'],
            'categories' => $article['categories'],
            'error_message' => $article['error_message'],
            'retry_count' => $article['retry_count'],
            'processed_at' => $article['processed_at'],
            'created_at' => $article['created_at'],
        ];
    }

    if ($dryRun) {
        if ($verbose) {
            echo "  Would sync " . count($batchData) . " articles\n";
            foreach ($batchData as $article) {
                echo "    - [{$article['id']}] {$article['rss_title']}\n";
            }
        }
        $stats['synced'] += count($batchData);
    } else {
        // POST to production API
        $ch = curl_init($productionUrl . '/api/sync/import');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-API-Key: ' . $productionApiKey,
            ],
            CURLOPT_POSTFIELDS => json_encode(['articles' => $batchData]),
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false || !empty($error) || $httpCode !== 200) {
            $errorMsg = "HTTP $httpCode: $error";
            $stats['failed'] += count($batchData);
            $stats['errors'][] = $errorMsg;

            $logger->error('Batch sync failed', [
                'category' => 'cli-sync',
                'batch' => $stats['batches'],
                'count' => count($batchData),
                'error' => $errorMsg,
            ]);

            if ($verbose) {
                echo "  [ERROR] $errorMsg\n";
            }
        } else {
            // Parse response
            $result = json_decode($response, true);

            if (!$result || !isset($result['success']) || !$result['success']) {
                $errorMsg = $result['error'] ?? 'Unknown error';
                $stats['failed'] += count($batchData);
                $stats['errors'][] = $errorMsg;

                $logger->error('Batch sync failed', [
                    'category' => 'cli-sync',
                    'batch' => $stats['batches'],
                    'count' => count($batchData),
                    'error' => $errorMsg,
                ]);

                if ($verbose) {
                    echo "  [ERROR] $errorMsg\n";
                }
            } else {
                // Success - mark articles as synced
                $articleIds = array_column($batchData, 'id');
                $placeholders = implode(',', array_fill(0, count($articleIds), '?'));
                $updateSql = "UPDATE articles
                              SET sync_pending = 0, synced_at = NOW()
                              WHERE id IN ($placeholders)";
                $updateStmt = $db->prepare($updateSql);
                $updateStmt->execute($articleIds);

                $imported = $result['imported'] ?? count($batchData);
                $skipped = $result['skipped'] ?? 0;

                $stats['synced'] += $imported;

                $logger->info('Batch synced successfully', [
                    'category' => 'cli-sync',
                    'batch' => $stats['batches'],
                    'imported' => $imported,
                    'skipped' => $skipped,
                ]);

                if ($verbose) {
                    echo "  [SUCCESS] Imported: $imported, Skipped: $skipped\n";
                }
            }
        }
    }

    $offset += $batchSize;

    if ($verbose) {
        echo "\n";
    }

    // Small delay between batches to avoid overwhelming server
    if (!$dryRun && $offset < $totalPending) {
        sleep(1);
    }
}

// Calculate duration
$duration = round(microtime(true) - $startTime, 2);

// Log completion
$logger->info('CLI production sync completed', [
    'category' => 'cli-sync',
    'duration' => $duration,
    'stats' => $stats,
]);

// Output summary
echo "\n";
echo "========================================\n";
echo "Production Sync Complete\n";
echo "========================================\n";
echo "Total pending:     {$stats['total_pending']}\n";
echo "Batches processed: {$stats['batches']}\n";
echo "Articles synced:   {$stats['synced']}\n";
echo "Articles failed:   {$stats['failed']}\n";
echo "Duration:          {$duration}s\n";

if (!empty($stats['errors'])) {
    echo "\nErrors:\n";
    foreach ($stats['errors'] as $error) {
        echo "  - $error\n";
    }
}

echo "========================================\n";

exit($stats['failed'] > 0 ? 1 : 0);
