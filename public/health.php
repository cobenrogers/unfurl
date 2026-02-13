<?php
/**
 * Health Check Endpoint
 *
 * Simple endpoint to verify application is running.
 * Used by monitoring systems and CI/CD deployment verification.
 */

header('Content-Type: application/json');
http_response_code(200);

echo json_encode([
    'status' => 'ok',
    'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
    'php_version' => PHP_VERSION
], JSON_PRETTY_PRINT);
