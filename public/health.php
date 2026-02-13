<?php
/**
 * Health Check Endpoint
 *
 * Simple endpoint to verify application is running and database is accessible.
 * Used by monitoring systems and CI/CD deployment verification.
 */

header('Content-Type: application/json');

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Load configuration
require_once __DIR__ . '/../config.php';

$response = [
    'status' => 'ok',
    'timestamp' => gmdate('Y-m-d\TH:i:s\Z')
];

// Check database connectivity
try {
    $db = new \Unfurl\Core\Database($config['database']);
    $pdo = $db->getConnection();

    // Simple query to verify database is responding
    $stmt = $pdo->query('SELECT 1');
    $stmt->fetch();

    $response['database'] = 'connected';
} catch (Exception $e) {
    $response['status'] = 'error';
    $response['database'] = 'disconnected';
    $response['error'] = 'Database connection failed';

    http_response_code(503); // Service Unavailable
    echo json_encode($response, JSON_PRETTY_PRINT);
    exit;
}

// All checks passed
http_response_code(200);
echo json_encode($response, JSON_PRETTY_PRINT);
