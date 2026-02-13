<?php
/**
 * Router for PHP Built-in Web Server
 *
 * This file is used when running the PHP built-in server for E2E testing.
 * It ensures that requests for static files are served correctly,
 * while routing other requests through index.php.
 */

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Serve static files directly
if (preg_match('/\.(?:png|jpg|jpeg|gif|css|js|ico|woff|woff2|ttf|svg)$/', $path)) {
    return false; // Let PHP serve the file directly
}

// Route everything else through index.php
$_SERVER['SCRIPT_NAME'] = '/index.php';
require __DIR__ . '/public/index.php';
