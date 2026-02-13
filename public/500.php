<?php
/**
 * 500 Internal Server Error Page
 *
 * Professional error page matching "Unfolding Revelation" theme.
 * Provides helpful information without exposing sensitive details.
 */

http_response_code(500);

// Get error details if available (only in development)
$show_details = (getenv('APP_ENV') === 'development' || getenv('APP_DEBUG') === 'true');
$error_message = $_GET['error'] ?? 'An unexpected error occurred';
$base_url = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>500 Internal Server Error - Unfurl</title>

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
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
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
            color: #f5576c;
        }

        .error-code {
            font-size: 4rem;
            font-weight: 700;
            color: #f5576c;
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

        .error-details {
            background: #fff5f5;
            border-left: 4px solid #f5576c;
            padding: 1rem;
            border-radius: 0.5rem;
            text-align: left;
            margin-bottom: 2rem;
        }

        .error-details-title {
            font-weight: 600;
            color: #c53030;
            margin-bottom: 0.5rem;
        }

        .error-details-message {
            font-family: 'Courier New', monospace;
            font-size: 0.875rem;
            color: #742a2a;
            word-break: break-all;
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

        .help-section p {
            color: var(--color-text-muted, #718096);
            font-size: 0.875rem;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="error-page">
        <div class="error-container">
            <!-- Error Icon -->
            <svg class="error-icon" viewBox="0 0 120 120" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="60" cy="60" r="55" stroke="currentColor" stroke-width="4" opacity="0.2"/>
                <path d="M60 30 L60 70" stroke="currentColor" stroke-width="6" stroke-linecap="round"/>
                <circle cx="60" cy="90" r="4" fill="currentColor"/>
                <path d="M30 30 L90 90 M90 30 L30 90" stroke="currentColor" stroke-width="4" stroke-linecap="round" opacity="0.3"/>
            </svg>

            <!-- Error Details -->
            <div class="error-code">500</div>
            <h1 class="error-title">Internal Server Error</h1>
            <p class="error-message">
                Something went wrong on our end. We're working to fix it. Please try again in a few moments.
            </p>

            <?php if ($show_details && !empty($error_message)): ?>
            <div class="error-details">
                <div class="error-details-title">Error Details (Development Mode)</div>
                <div class="error-details-message"><?= htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8') ?></div>
            </div>
            <?php endif; ?>

            <!-- Action Buttons -->
            <div class="error-actions">
                <a href="<?= $base_url ?>/" class="btn btn-primary">Go Home</a>
                <a href="javascript:location.reload()" class="btn btn-secondary">Try Again</a>
            </div>

            <!-- Help Section -->
            <div class="help-section">
                <h3>What can I do?</h3>
                <p>
                    If this problem persists, please check your configuration or contact your system administrator.
                    The error has been logged for investigation.
                </p>
            </div>
        </div>
    </div>
</body>
</html>
