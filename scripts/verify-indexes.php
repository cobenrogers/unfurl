#!/usr/bin/env php
<?php
/**
 * Database Index Verification Script
 *
 * Checks all required indexes exist, reports missing or unused indexes,
 * and provides performance recommendations.
 *
 * Usage: php scripts/verify-indexes.php
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Unfurl\Core\Database;

// Color output for terminal
class Colors {
    public static $GREEN = "\033[32m";
    public static $RED = "\033[31m";
    public static $YELLOW = "\033[33m";
    public static $BLUE = "\033[34m";
    public static $RESET = "\033[0m";
}

// Check if running in terminal
$isCli = php_sapi_name() === 'cli';
if (!$isCli) {
    Colors::$GREEN = '';
    Colors::$RED = '';
    Colors::$YELLOW = '';
    Colors::$BLUE = '';
    Colors::$RESET = '';
    header('Content-Type: text/plain');
}

echo Colors::$BLUE . "=====================================\n" . Colors::$RESET;
echo Colors::$BLUE . "Database Index Verification\n" . Colors::$RESET;
echo Colors::$BLUE . "=====================================\n\n" . Colors::$RESET;

try {
    $config = require __DIR__ . '/../config.php';
    $db = new Database(
        $config['database']['host'],
        $config['database']['name'],
        $config['database']['user'],
        $config['database']['pass']
    );

    // Required indexes for each table
    $requiredIndexes = [
        'feeds' => [
            ['name' => 'PRIMARY', 'columns' => ['id'], 'type' => 'PRIMARY'],
            ['name' => 'topic', 'columns' => ['topic'], 'type' => 'UNIQUE'],
            ['name' => 'idx_enabled', 'columns' => ['enabled'], 'type' => 'INDEX'],
            ['name' => 'idx_topic', 'columns' => ['topic'], 'type' => 'INDEX'],
            ['name' => 'idx_last_processed', 'columns' => ['last_processed_at'], 'type' => 'INDEX'],
        ],
        'articles' => [
            ['name' => 'PRIMARY', 'columns' => ['id'], 'type' => 'PRIMARY'],
            ['name' => 'idx_feed_id', 'columns' => ['feed_id'], 'type' => 'INDEX'],
            ['name' => 'idx_topic', 'columns' => ['topic'], 'type' => 'INDEX'],
            ['name' => 'idx_status', 'columns' => ['status'], 'type' => 'INDEX'],
            ['name' => 'idx_processed_at', 'columns' => ['processed_at'], 'type' => 'INDEX'],
            ['name' => 'idx_google_news_url', 'columns' => ['google_news_url'], 'type' => 'INDEX'],
            ['name' => 'idx_final_url_unique', 'columns' => ['final_url'], 'type' => 'UNIQUE'],
            ['name' => 'idx_retry', 'columns' => ['status', 'retry_count', 'next_retry_at'], 'type' => 'INDEX'],
            ['name' => 'idx_search', 'columns' => ['rss_title', 'page_title', 'og_title', 'og_description', 'author'], 'type' => 'FULLTEXT'],
            ['name' => 'feed_id', 'columns' => ['feed_id'], 'type' => 'FOREIGN KEY'],
        ],
        'api_keys' => [
            ['name' => 'PRIMARY', 'columns' => ['id'], 'type' => 'PRIMARY'],
            ['name' => 'key_value', 'columns' => ['key_value'], 'type' => 'UNIQUE'],
            ['name' => 'idx_key_value', 'columns' => ['key_value'], 'type' => 'INDEX'],
            ['name' => 'idx_enabled', 'columns' => ['enabled'], 'type' => 'INDEX'],
            ['name' => 'idx_key_name', 'columns' => ['key_name'], 'type' => 'INDEX'],
        ],
        'logs' => [
            ['name' => 'PRIMARY', 'columns' => ['id'], 'type' => 'PRIMARY'],
            ['name' => 'idx_level', 'columns' => ['level'], 'type' => 'INDEX'],
            ['name' => 'idx_category', 'columns' => ['category'], 'type' => 'INDEX'],
            ['name' => 'idx_created_at', 'columns' => ['created_at'], 'type' => 'INDEX'],
            ['name' => 'idx_level_category', 'columns' => ['level', 'category'], 'type' => 'INDEX'],
        ],
        'migrations' => [
            ['name' => 'PRIMARY', 'columns' => ['id'], 'type' => 'PRIMARY'],
            ['name' => 'migration_name', 'columns' => ['migration_name'], 'type' => 'UNIQUE'],
            ['name' => 'idx_migration_name', 'columns' => ['migration_name'], 'type' => 'INDEX'],
        ],
        'metrics' => [
            ['name' => 'PRIMARY', 'columns' => ['id'], 'type' => 'PRIMARY'],
            ['name' => 'idx_name_time', 'columns' => ['metric_name', 'recorded_at'], 'type' => 'INDEX'],
        ],
    ];

    $allPassed = true;
    $missingIndexes = [];
    $recommendations = [];

    // Check each table
    foreach ($requiredIndexes as $tableName => $indexes) {
        echo Colors::$BLUE . "\nTable: $tableName\n" . Colors::$RESET;
        echo str_repeat('-', 50) . "\n";

        // Get existing indexes for this table
        $stmt = $db->query("SHOW INDEX FROM `$tableName`");
        $existingIndexes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Group by index name
        $indexMap = [];
        foreach ($existingIndexes as $row) {
            $indexName = $row['Key_name'];
            if (!isset($indexMap[$indexName])) {
                $indexMap[$indexName] = [
                    'columns' => [],
                    'type' => $row['Key_name'] === 'PRIMARY' ? 'PRIMARY' : ($row['Non_unique'] == 0 ? 'UNIQUE' : 'INDEX'),
                    'index_type' => $row['Index_type'],
                ];
            }
            $indexMap[$indexName]['columns'][] = $row['Column_name'];
        }

        // Check each required index
        foreach ($indexes as $required) {
            $indexName = $required['name'];
            $exists = isset($indexMap[$indexName]);

            if ($exists) {
                // Verify columns match
                $existingCols = $indexMap[$indexName]['columns'];
                $requiredCols = $required['columns'];

                // For prefix indexes, just check if column name is in the list
                $colsMatch = count(array_intersect($existingCols, $requiredCols)) === count($requiredCols);

                if ($colsMatch) {
                    echo Colors::$GREEN . "✓ $indexName" . Colors::$RESET;
                    echo " (" . implode(', ', $existingCols) . ")\n";
                } else {
                    echo Colors::$YELLOW . "⚠ $indexName" . Colors::$RESET;
                    echo " - Column mismatch\n";
                    echo "  Expected: " . implode(', ', $requiredCols) . "\n";
                    echo "  Found: " . implode(', ', $existingCols) . "\n";
                    $allPassed = false;
                    $recommendations[] = "Review index $indexName on $tableName - columns don't match schema";
                }
            } else {
                echo Colors::$RED . "✗ $indexName" . Colors::$RESET;
                echo " - MISSING\n";
                $allPassed = false;
                $missingIndexes[] = [
                    'table' => $tableName,
                    'index' => $indexName,
                    'columns' => $required['columns'],
                    'type' => $required['type'],
                ];
            }
        }

        // Check for extra indexes (potential optimization opportunity)
        foreach ($indexMap as $indexName => $indexInfo) {
            $isRequired = false;
            foreach ($indexes as $required) {
                if ($required['name'] === $indexName) {
                    $isRequired = true;
                    break;
                }
            }

            if (!$isRequired) {
                echo Colors::$YELLOW . "ℹ $indexName" . Colors::$RESET;
                echo " - Extra index (not in schema)\n";
                $recommendations[] = "Consider if index $indexName on $tableName is needed";
            }
        }
    }

    // Summary
    echo "\n" . Colors::$BLUE . "=====================================\n" . Colors::$RESET;
    echo Colors::$BLUE . "Summary\n" . Colors::$RESET;
    echo Colors::$BLUE . "=====================================\n\n" . Colors::$RESET;

    if ($allPassed) {
        echo Colors::$GREEN . "✓ All required indexes are present and correct!\n" . Colors::$RESET;
    } else {
        echo Colors::$RED . "✗ Issues found with database indexes\n\n" . Colors::$RESET;

        if (!empty($missingIndexes)) {
            echo Colors::$RED . "Missing Indexes:\n" . Colors::$RESET;
            foreach ($missingIndexes as $missing) {
                echo "  - {$missing['table']}.{$missing['index']} ({$missing['type']})\n";
                echo "    Columns: " . implode(', ', $missing['columns']) . "\n";
            }
            echo "\n";
        }
    }

    // Performance recommendations
    if (!empty($recommendations)) {
        echo Colors::$YELLOW . "\nRecommendations:\n" . Colors::$RESET;
        foreach ($recommendations as $i => $rec) {
            echo "  " . ($i + 1) . ". $rec\n";
        }
        echo "\n";
    }

    // Index usage statistics (if available)
    echo Colors::$BLUE . "\nIndex Usage Statistics:\n" . Colors::$RESET;
    echo str_repeat('-', 50) . "\n";

    try {
        $stmt = $db->query("
            SELECT
                table_name,
                index_name,
                seq_in_index,
                column_name,
                cardinality,
                index_type
            FROM information_schema.statistics
            WHERE table_schema = DATABASE()
            AND table_name IN ('feeds', 'articles', 'api_keys', 'logs', 'migrations', 'metrics')
            ORDER BY table_name, index_name, seq_in_index
        ");
        $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $currentTable = '';
        $currentIndex = '';

        foreach ($stats as $stat) {
            if ($stat['table_name'] !== $currentTable) {
                $currentTable = $stat['table_name'];
                echo "\n" . Colors::$BLUE . $currentTable . ":\n" . Colors::$RESET;
            }

            if ($stat['index_name'] !== $currentIndex) {
                $currentIndex = $stat['index_name'];
                $cardinality = $stat['cardinality'] ?? 'N/A';
                echo "  {$stat['index_name']} ({$stat['index_type']}) - Cardinality: $cardinality\n";
            }
        }

        echo "\n";
    } catch (PDOException $e) {
        echo Colors::$YELLOW . "Unable to fetch index statistics\n" . Colors::$RESET;
    }

    // Performance tips
    echo Colors::$BLUE . "\nPerformance Tips:\n" . Colors::$RESET;
    echo str_repeat('-', 50) . "\n";
    echo "1. Monitor slow query log for queries not using indexes\n";
    echo "2. Use EXPLAIN on complex queries to verify index usage\n";
    echo "3. Consider composite indexes for common filter combinations\n";
    echo "4. Keep indexes on foreign keys for JOIN performance\n";
    echo "5. FULLTEXT indexes require MySQL 5.6+ with InnoDB\n";
    echo "6. Prefix indexes (e.g., url(255)) save space on TEXT columns\n";
    echo "7. Run ANALYZE TABLE periodically to update statistics\n\n";

    exit($allPassed ? 0 : 1);

} catch (Exception $e) {
    echo Colors::$RED . "\nError: " . $e->getMessage() . "\n" . Colors::$RESET;
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
