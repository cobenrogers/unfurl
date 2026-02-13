<?php
/**
 * Header Partial - Navigation & Page Structure
 *
 * Displays the main navigation, branding, and top-level page structure.
 * Responsive design with mobile-friendly navigation.
 */

use Unfurl\Security\OutputEscaper;
use Unfurl\Security\CsrfToken;

$escaper = new OutputEscaper();
$csrf = $csrf ?? new CsrfToken();

// Determine active page for nav highlighting
$current_page = $_SERVER['REQUEST_URI'] ?? '';
$is_feeds_page = strpos($current_page, '/feeds') !== false;
$is_articles_page = strpos($current_page, '/articles') !== false;
$is_logs_page = strpos($current_page, '/logs') !== false;
$is_settings_page = strpos($current_page, '/settings') !== false;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="csrf-token" content="<?= $escaper->attribute($csrf->getToken()) ?>">
    <title><?= isset($page_title) ? $escaper->html($page_title) . ' - Unfurl' : 'Unfurl' ?></title>

    <!-- Design System CSS -->
    <link rel="stylesheet" href="/assets/css/reset.css">
    <link rel="stylesheet" href="/assets/css/variables.css">
    <link rel="stylesheet" href="/assets/css/typography.css">
    <link rel="stylesheet" href="/assets/css/layout.css">
    <link rel="stylesheet" href="/assets/css/components.css">
    <link rel="stylesheet" href="/assets/css/animations.css">

    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
</head>
<body>
    <a href="#main-content" class="skip-link">Skip to main content</a>

    <!-- Header Navigation -->
    <header class="header" role="banner">
        <div class="container">
            <nav class="nav-bar flex justify-between items-center py-4" role="navigation">
                <!-- Logo/Branding -->
                <div class="nav-brand">
                    <a href="/" class="brand-link flex items-center gap-2" title="Unfurl Home">
                        <svg class="brand-icon" width="28" height="28" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="14" cy="14" r="13" stroke="currentColor" stroke-width="2"/>
                            <path d="M8 14C8 10.686 10.686 8 14 8M14 8C17.314 8 20 10.686 20 14M14 8V14" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <span class="brand-text" style="color: var(--color-primary); font-weight: 600; font-size: 1.25rem;">Unfurl</span>
                    </a>
                </div>

                <!-- Primary Navigation -->
                <div class="flex items-center gap-4">
                    <nav class="nav-primary flex gap-1" role="menubar">
                        <a href="/feeds"
                           class="nav-link btn btn-ghost <?= $is_feeds_page ? 'active' : '' ?>"
                           role="menuitem"
                           <?= $is_feeds_page ? 'aria-current="page"' : '' ?>>
                            Feeds
                        </a>
                        <a href="/articles"
                           class="nav-link btn btn-ghost <?= $is_articles_page ? 'active' : '' ?>"
                           role="menuitem"
                           <?= $is_articles_page ? 'aria-current="page"' : '' ?>>
                            Articles
                        </a>
                        <a href="/logs"
                           class="nav-link btn btn-ghost <?= $is_logs_page ? 'active' : '' ?>"
                           role="menuitem"
                           <?= $is_logs_page ? 'aria-current="page"' : '' ?>>
                            Logs
                        </a>
                        <a href="/settings"
                           class="nav-link btn btn-ghost <?= $is_settings_page ? 'active' : '' ?>"
                           role="menuitem"
                           <?= $is_settings_page ? 'aria-current="page"' : '' ?>>
                            Settings
                        </a>
                    </nav>

                    <!-- User Menu -->
                    <?php if (isset($auth)): ?>
                        <?php require __DIR__ . '/user-menu.php'; ?>
                    <?php endif; ?>
                </div>
            </nav>
        </div>
    </header>

    <!-- Flash Messages Container -->
    <?php if (isset($flash_messages) && is_array($flash_messages)): ?>
        <div class="flash-messages container mt-4" id="flash-messages">
            <?php foreach ($flash_messages as $message): ?>
                <div class="alert alert-<?= $escaper->html($message['type']) ?>" role="alert">
                    <div class="alert-icon">
                        <?php if ($message['type'] === 'success'): ?>
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"/>
                            </svg>
                        <?php elseif ($message['type'] === 'error'): ?>
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"/>
                            </svg>
                        <?php elseif ($message['type'] === 'warning'): ?>
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"/>
                            </svg>
                        <?php else: ?>
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M18 5v8a2 2 0 01-2 2h-5l-5 4v-4H4a2 2 0 01-2-2V5a2 2 0 012-2h12a2 2 0 012 2zm-11-1a1 1 0 11-2 0 1 1 0 012 0zm6 0a1 1 0 11-2 0 1 1 0 012 0z"/>
                            </svg>
                        <?php endif; ?>
                    </div>
                    <div class="alert-content">
                        <div class="alert-message"><?= $escaper->html($message['text']) ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Main Content -->
    <main id="main-content" role="main">
