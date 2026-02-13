#!/usr/bin/env php
<?php

/**
 * Run Database Migration
 *
 * Usage: php scripts/run-migration.php <migration-file>
 * Example: php scripts/run-migration.php sql/migrations/016_create_logs_table.sql
 */

declare(strict_types=1);

// Load configuration
require_once __DIR__ . '/../vendor/autoload.php';
$config = require __DIR__ . '/../config.php';

use Unfurl\Core\Database;

// Get migration file from arguments
if ($argc < 2) {
    echo "Usage: php scripts/run-migration.php <migration-file>\n";
    echo "Example: php scripts/run-migration.php sql/migrations/016_create_logs_table.sql\n";
    exit(1);
}

$migrationFile = $argv[1];

// Check if file exists (try relative to project root first)
if (!file_exists($migrationFile)) {
    $migrationFile = __DIR__ . '/../' . $migrationFile;
}

if (!file_exists($migrationFile)) {
    echo "Error: Migration file not found: {$migrationFile}\n";
    exit(1);
}

// Read migration SQL
$sql = file_get_contents($migrationFile);
if ($sql === false) {
    echo "Error: Failed to read migration file: {$migrationFile}\n";
    exit(1);
}

try {
    // Initialize database
    $db = new Database($config);
    $pdo = $db->getConnection();

    echo "Running migration: {$migrationFile}\n";

    // Execute migration
    $pdo->exec($sql);

    echo "Migration completed successfully!\n";
    exit(0);
} catch (\Exception $e) {
    echo "Error running migration: " . $e->getMessage() . "\n";
    exit(1);
}
