<?php
/**
 * 403 Forbidden Error Page
 *
 * Professional error page matching "Unfolding Revelation" theme.
 * Provides helpful context for access denied scenarios.
 */

http_response_code(403);

$requested_url = $_SERVER['REQUEST_URI'] ?? '';
$base_url = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>403 Forbidden - Unfurl</title>

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
            background: linear-gradient(135deg, #ffa751 0%, #ffe259 100%);
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
            color: #ffa751;
        }

        .error-code {
            font-size: 4rem;
            font-weight: 700;
            color: #ffa751;
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

        .help-section {
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid var(--color-border, #e2e8f0);
        }

        .help-section h3 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--color-text, #1a202c);
            margin-bottom: 1rem;
        }

        .help-section ul {
            list-style: none;
            padding: 0;
            margin: 0;
            text-align: left;
        }

        .help-section li {
            padding: 0.5rem 0;
            color: var(--color-text-muted, #718096);
            font-size: 0.875rem;
            line-height: 1.6;
        }

        .help-section li::before {
            content: "•";
            color: #ffa751;
            font-weight: bold;
            display: inline-block;
            width: 1rem;
        }
    </style>
</head>
<body>
    <div class="error-page">
        <div class="error-container">
            <!-- Error Icon -->
            <svg class="error-icon" viewBox="0 0 120 120" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="60" cy="60" r="55" stroke="currentColor" stroke-width="4" opacity="0.2"/>
                <rect x="35" y="45" width="50" height="35" rx="3" stroke="currentColor" stroke-width="4"/>
                <rect x="52" y="60" width="16" height="10" rx="2" fill="currentColor"/>
                <path d="M45 45 L45 35 Q45 25 55 25 L65 25 Q75 25 75 35 L75 45" stroke="currentColor" stroke-width="4" stroke-linecap="round"/>
            </svg>

            <!-- Error Details -->
            <div class="error-code">403</div>
            <h1 class="error-title">Access Forbidden</h1>
            <p class="error-message">
                You don't have permission to access this resource. This could be due to authentication requirements or restricted access.
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

            <!-- Help Section -->
            <div class="help-section">
                <h3>Why am I seeing this?</h3>
                <ul>
                    <li>You may need an API key to access this resource</li>
                    <li>Your API key may be disabled or invalid</li>
                    <li>The resource may require special permissions</li>
                    <li>Rate limiting may be in effect</li>
                </ul>
            </div>
        </div>
    </div>
</body>
</html>
