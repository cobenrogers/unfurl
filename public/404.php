<?php
/**
 * 404 Not Found Error Page
 *
 * Professional error page matching "Unfolding Revelation" theme.
 * Provides helpful navigation and context to users.
 */

http_response_code(404);

// Get the requested URL for display
$requested_url = $_SERVER['REQUEST_URI'] ?? '';
$base_url = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>404 Not Found - Unfurl</title>

    <!-- Design System CSS -->
    <link rel="stylesheet" href="<?= $base_url ?>/assets/css/reset.css">
    <link rel="stylesheet" href="<?= $base_url ?>/assets/css/variables.css">
    <link rel="stylesheet" href="<?= $base_url ?>/assets/css/typography.css">
    <link rel="stylesheet" href="<?= $base_url ?>/assets/css/layout.css">
    <link rel="stylesheet" href="<?= $base_url ?>/assets/css/components.css">

    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="<?= $base_url ?>/assets/favicon.svg">

    <style>
        .error-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 2rem;
        }

        .error-container {
            max-width: 600px;
            background: white;
            border-radius: 1rem;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 3rem;
            text-align: center;
        }

        .error-icon {
            width: 120px;
            height: 120px;
            margin: 0 auto 2rem;
            color: var(--color-primary, #667eea);
        }

        .error-code {
            font-size: 4rem;
            font-weight: 700;
            color: var(--color-primary, #667eea);
            margin-bottom: 1rem;
            line-height: 1;
        }

        .error-title {
            font-size: 1.75rem;
            font-weight: 600;
            color: var(--color-text, #1a202c);
            margin-bottom: 1rem;
        }

        .error-message {
            font-size: 1.125rem;
            color: var(--color-text-muted, #718096);
            margin-bottom: 2rem;
            line-height: 1.6;
        }

        .error-url {
            background: var(--color-bg-secondary, #f7fafc);
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            font-family: 'Courier New', monospace;
            font-size: 0.875rem;
            color: var(--color-text-muted, #718096);
            word-break: break-all;
            margin-bottom: 2rem;
        }

        .error-actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            justify-content: center;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
            border: 2px solid transparent;
            display: inline-block;
        }

        .btn-primary {
            background: var(--color-primary, #667eea);
            color: white;
        }

        .btn-primary:hover {
            background: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: transparent;
            color: var(--color-primary, #667eea);
            border-color: var(--color-primary, #667eea);
        }

        .btn-secondary:hover {
            background: var(--color-primary, #667eea);
            color: white;
        }

        .helpful-links {
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid var(--color-border, #e2e8f0);
        }

        .helpful-links h3 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--color-text, #1a202c);
            margin-bottom: 1rem;
        }

        .helpful-links ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .helpful-links li {
            margin-bottom: 0.5rem;
        }

        .helpful-links a {
            color: var(--color-primary, #667eea);
            text-decoration: none;
            font-weight: 500;
        }

        .helpful-links a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="error-page">
        <div class="error-container">
            <!-- Error Icon -->
            <svg class="error-icon" viewBox="0 0 120 120" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="60" cy="60" r="55" stroke="currentColor" stroke-width="4" opacity="0.2"/>
                <circle cx="60" cy="60" r="45" stroke="currentColor" stroke-width="4"/>
                <path d="M40 50 Q60 30 80 50" stroke="currentColor" stroke-width="4" stroke-linecap="round" fill="none"/>
                <circle cx="45" cy="55" r="4" fill="currentColor"/>
                <circle cx="75" cy="55" r="4" fill="currentColor"/>
                <path d="M45 75 Q60 70 75 75" stroke="currentColor" stroke-width="4" stroke-linecap="round" fill="none"/>
            </svg>

            <!-- Error Details -->
            <div class="error-code">404</div>
            <h1 class="error-title">Page Not Found</h1>
            <p class="error-message">
                We couldn't find the page you're looking for. It may have been moved, deleted, or never existed.
            </p>

            <?php if (!empty($requested_url)): ?>
            <div class="error-url">
                Requested: <?= htmlspecialchars($requested_url, ENT_QUOTES, 'UTF-8') ?>
            </div>
            <?php endif; ?>

            <!-- Action Buttons -->
            <div class="error-actions">
                <a href="<?= $base_url ?>/" class="btn btn-primary">Go Home</a>
                <a href="javascript:history.back()" class="btn btn-secondary">Go Back</a>
            </div>

            <!-- Helpful Links -->
            <div class="helpful-links">
                <h3>Looking for something?</h3>
                <ul>
                    <li><a href="<?= $base_url ?>/feeds">Browse Feeds</a></li>
                    <li><a href="<?= $base_url ?>/articles">View Articles</a></li>
                    <li><a href="<?= $base_url ?>/settings">Settings</a></li>
                </ul>
            </div>
        </div>
    </div>
</body>
</html>
