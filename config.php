<?php

/**
 * Unfurl Configuration
 *
 * This file loads environment variables and provides configuration values.
 * All sensitive data must be in .env file, NOT in this file.
 */

// Load environment variables from .env file
function loadEnv(string $path): void {
    if (!file_exists($path)) {
        throw new Exception('.env file not found at: ' . $path);
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        // Parse KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Set environment variable
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

// Load .env file (skip if in testing environment)
if (php_sapi_name() !== 'cli' || !defined('PHPUNIT_RUNNING')) {
    $envFile = __DIR__ . '/.env';
    if (file_exists($envFile)) {
        loadEnv($envFile);
    }
}

// Helper function to get environment variable with default
function env(string $key, $default = null) {
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

    if ($value === false) {
        return $default;
    }

    // Convert string booleans
    if (in_array(strtolower($value), ['true', 'false'])) {
        return strtolower($value) === 'true';
    }

    return $value;
}

// Configuration array
$config = [
    'database' => [
        'host' => env('DB_HOST', 'localhost'),
        'name' => env('DB_NAME'),
        'user' => env('DB_USER'),
        'pass' => env('DB_PASS'),
        'charset' => 'utf8mb4',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ],
    ],

    'app' => [
        'env' => env('APP_ENV', 'production'),
        'debug' => env('APP_DEBUG', false),
        'base_url' => env('APP_BASE_URL'),
        'timezone' => env('APP_TIMEZONE', 'UTC'),
    ],

    'processing' => [
        'processor' => env('ARTICLE_PROCESSOR', 'node'), // 'node' (default) or 'php'
        'timeout' => (int)env('PROCESSING_TIMEOUT', 30),
        'max_retries' => (int)env('PROCESSING_MAX_RETRIES', 3),
        'retry_delay' => (int)env('PROCESSING_RETRY_DELAY', 60),
    ],

    'retention' => [
        'articles_days' => (int)env('RETENTION_ARTICLES_DAYS', 90),
        'logs_days' => (int)env('RETENTION_LOGS_DAYS', 30),
        'auto_cleanup' => env('RETENTION_AUTO_CLEANUP', true),
    ],

    'security' => [
        'session_secret' => env('SESSION_SECRET'),
    ],

    'paths' => [
        'root' => __DIR__,
        'storage' => __DIR__ . '/storage',
        'logs' => __DIR__ . '/storage/logs',
        'temp' => __DIR__ . '/storage/temp',
    ],
];

// Validate required configuration
function validateConfig(array $config): void {
    $required = [
        'database.host',
        'database.name',
        'database.user',
        'database.pass',
        'app.base_url',
        'security.session_secret',
    ];

    $missing = [];
    foreach ($required as $key) {
        $keys = explode('.', $key);
        $value = $config;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                $missing[] = $key;
                break;
            }
            $value = $value[$k];
        }

        if (empty($value) && $value !== '0' && $value !== 0) {
            $missing[] = $key;
        }
    }

    if (!empty($missing)) {
        throw new Exception(
            'Missing required configuration: ' .
            implode(', ', $missing) .
            "\n\nPlease check your .env file."
        );
    }

    // Validate session secret strength
    if (strlen($config['security']['session_secret']) < 32) {
        throw new Exception(
            'SESSION_SECRET must be at least 32 characters. ' .
            'Generate one with: php -r "echo bin2hex(random_bytes(32));"'
        );
    }
}

// Validate configuration (skip in test environment)
if (php_sapi_name() !== 'cli' || !defined('PHPUNIT_RUNNING')) {
    try {
        validateConfig($config);
    } catch (Exception $e) {
        die('Configuration Error: ' . $e->getMessage());
    }
}

// Set timezone
date_default_timezone_set($config['app']['timezone']);

return $config;
