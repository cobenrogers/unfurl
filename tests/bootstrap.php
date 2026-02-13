<?php

declare(strict_types=1);

// Test bootstrap file

require_once __DIR__ . '/../vendor/autoload.php';

// Load test environment variables
$envFile = __DIR__ . '/../.env.test';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
            putenv(trim($key) . '=' . trim($value));
        }
    }
} else {
    // Set test defaults
    $_ENV['DB_HOST'] = 'localhost';
    $_ENV['DB_NAME'] = ':memory:'; // SQLite for tests
    $_ENV['DB_USER'] = '';
    $_ENV['DB_PASS'] = '';
    $_ENV['APP_ENV'] = 'testing';
    $_ENV['APP_DEBUG'] = 'true';
    $_ENV['SESSION_SECRET'] = 'test_secret_key_32_characters_12';
}
