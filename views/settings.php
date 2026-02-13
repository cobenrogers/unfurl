<?php
/**
 * Settings View - Application Configuration Interface
 *
 * Manages API keys, processing options, data retention, and cron setup.
 *
 * Requirements: Section 5.3.4 of REQUIREMENTS.md
 * Security: CSRF tokens, XSS prevention via OutputEscaper, secure API key masking
 */

use Unfurl\Security\CsrfToken;
use Unfurl\Security\OutputEscaper;

$page_title = 'Settings';
$csrf = new CsrfToken();
$escaper = new OutputEscaper();

// Get data from controller (would be injected in real implementation)
$apiKeys = $apiKeys ?? [];
$config = $config ?? [];
$lastProcessingRun = $lastProcessingRun ?? null;
$lastCleanupRun = $lastCleanupRun ?? null;

// Default config values
$processingTimeout = (int)($config['processing']['timeout'] ?? 30);
$maxRetries = (int)($config['processing']['max_retries'] ?? 3);
$retryDelay = (int)($config['processing']['retry_delay'] ?? 60);
$retentionArticlesDays = (int)($config['retention']['articles_days'] ?? 90);
$retentionLogsDays = (int)($config['retention']['logs_days'] ?? 30);
$autoCleanup = (bool)($config['retention']['auto_cleanup'] ?? true);

include __DIR__ . '/partials/header.php';
?>

<div class="container py-6">
    <div class="mb-8">
        <h1 style="font-size: 2rem; font-weight: 700;">Settings</h1>
        <p style="color: var(--color-text-muted); margin-top: 0.5rem;">Configure API keys, processing options, and data retention</p>
    </div>

        <!-- Status Messages -->
        <?php if (isset($flashMessage) && $flashMessage): ?>
            <div class="alert alert-<?= $escaper->attribute($flashMessage['type']) ?>" style="margin-bottom: var(--space-6);">
                <?= $escaper->html($flashMessage['message']) ?>
            </div>
        <?php endif; ?>
        <div id="status-messages" role="status" aria-live="polite" aria-atomic="true"></div>

        <div class="settings-container">
            <!-- API Configuration Section -->
            <section class="card" id="api-config">
                <div class="card-header">
                    <h3>API Configuration</h3>
                </div>
                <div class="card-body">
                    <div class="settings-group">
                        <label class="form-label">API Access Instructions</label>
                        <div class="alert alert-info">
                            <strong>How to use the RSS API:</strong>
                            <p style="margin-top: var(--space-2);">
                                You can authenticate using either a <strong>header</strong> (more secure) or <strong>query parameter</strong> (easier for RSS readers).
                            </p>
                        </div>

                        <div style="margin-top: var(--space-4);">
                            <h4 style="font-size: 0.95rem; font-weight: 600; margin-bottom: var(--space-2);">Method 1: Header Authentication (Recommended)</h4>
                            <div class="code-example">
                                <code>curl -H "X-API-Key: YOUR_API_KEY" <?= $escaper->attribute($_SERVER['REQUEST_SCHEME'] ?? 'http') ?>://<?= $escaper->attribute($_SERVER['HTTP_HOST']) ?>/api/feed</code>
                                <button type="button" class="btn-copy-code" data-copy-type="header" title="Copy header example">
                                    📋
                                </button>
                            </div>
                        </div>

                        <div style="margin-top: var(--space-3);">
                            <h4 style="font-size: 0.95rem; font-weight: 600; margin-bottom: var(--space-2);">Method 2: Query Parameter (For RSS Readers)</h4>
                            <div class="code-example">
                                <code><?= $escaper->attribute($_SERVER['REQUEST_SCHEME'] ?? 'http') ?>://<?= $escaper->attribute($_SERVER['HTTP_HOST']) ?>/api/feed?key=YOUR_API_KEY</code>
                                <button type="button" class="btn-copy-code" data-copy-type="query" title="Copy URL with parameter">
                                    📋
                                </button>
                            </div>
                        </div>

                        <div style="margin-top: var(--space-4); padding-top: var(--space-4); border-top: 1px solid var(--color-border);">
                            <h4 style="font-size: 0.95rem; font-weight: 600; margin-bottom: var(--space-2);">Filtering Options</h4>
                            <p style="font-size: 0.9rem; color: var(--color-text-muted); margin-bottom: var(--space-3);">
                                Add these parameters to filter results (use &amp; to combine multiple filters):
                            </p>

                            <div style="margin-left: var(--space-4);">
                                <div style="margin-bottom: var(--space-2);">
                                    <code style="background: var(--color-bg); padding: var(--space-1) var(--space-2); border-radius: var(--radius-sm);">?feed_id=2</code>
                                    <span style="margin-left: var(--space-2); color: var(--color-text-muted);">Get articles from a specific feed only</span>
                                </div>
                                <div style="margin-bottom: var(--space-2);">
                                    <code style="background: var(--color-bg); padding: var(--space-1) var(--space-2); border-radius: var(--radius-sm);">?topic=Bahamas</code>
                                    <span style="margin-left: var(--space-2); color: var(--color-text-muted);">Filter by topic name</span>
                                </div>
                                <div style="margin-bottom: var(--space-2);">
                                    <code style="background: var(--color-bg); padding: var(--space-1) var(--space-2); border-radius: var(--radius-sm);">?limit=100</code>
                                    <span style="margin-left: var(--space-2); color: var(--color-text-muted);">Limit number of articles (default: 50)</span>
                                </div>
                            </div>

                            <p style="font-size: 0.85rem; color: var(--color-text-muted); margin-top: var(--space-3); font-style: italic;">
                                Example: <code style="background: var(--color-bg); padding: 2px 6px; border-radius: 3px;">/api/feed?key=YOUR_KEY&amp;feed_id=4&amp;limit=20</code>
                                returns only Bahamas articles, limited to 20 results.
                            </p>
                        </div>
                    </div>

                    <!-- API Keys List -->
                    <div class="settings-group">
                        <div class="group-header">
                            <label class="form-label">API Keys</label>
                            <button
                                type="button"
                                class="btn btn-primary btn-small"
                                id="add-api-key-btn"
                                aria-label="Add new API key"
                            >
                                + Add New Key
                            </button>
                        </div>

                        <?php if (empty($apiKeys)): ?>
                            <div class="alert alert-info">
                                No API keys configured yet.
                                <button type="button" class="btn-link" id="create-first-key">
                                    Create your first key
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="api-keys-list">
                                <?php foreach ($apiKeys as $key): ?>
                                    <div class="api-key-card">
                                        <div class="api-key-header">
                                            <div class="api-key-info">
                                                <div class="api-key-name-row">
                                                    <h4 class="api-key-name">
                                                        <?= $escaper->html($key['key_name']) ?>
                                                    </h4>
                                                    <?php if ($key['enabled']): ?>
                                                        <span class="badge badge-success" title="This API key can be used">Enabled</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-warning" title="This API key is currently disabled">Disabled</span>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if ($key['description']): ?>
                                                    <p class="api-key-description">
                                                        <?= $escaper->html($key['description']) ?>
                                                    </p>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <div class="api-key-details">
                                            <div class="detail-row">
                                                <span class="detail-label">Key Preview:</span>
                                                <span class="detail-value">
                                                    <code class="key-preview"><?= $escaper->html(substr($key['key_value'], 0, 12)) ?>...<?= $escaper->html(substr($key['key_value'], -4)) ?></code>
                                                </span>
                                            </div>
                                            <div class="detail-row">
                                                <span class="detail-label">Created:</span>
                                                <span class="detail-value">
                                                    <time datetime="<?= $escaper->attribute($key['created_at']) ?>">
                                                        <?= $escaper->html(date('M d, Y g:i A', strtotime($key['created_at']))) ?>
                                                    </time>
                                                </span>
                                            </div>
                                            <div class="detail-row">
                                                <span class="detail-label">Last Used:</span>
                                                <span class="detail-value">
                                                    <?php if ($key['last_used_at']): ?>
                                                        <time datetime="<?= $escaper->attribute($key['last_used_at']) ?>">
                                                            <?= $escaper->html(date('M d, Y g:i A', strtotime($key['last_used_at']))) ?>
                                                        </time>
                                                    <?php else: ?>
                                                        <span class="text-muted">Never used</span>
                                                    <?php endif; ?>
                                                </span>
                                            </div>
                                        </div>

                                        <div class="api-key-actions">
                                            <button
                                                type="button"
                                                class="btn btn-secondary btn-sm action-btn"
                                                data-action="show"
                                                data-key-id="<?= $escaper->attribute($key['id']) ?>"
                                                data-key-value="<?= $escaper->attribute($key['key_value']) ?>"
                                                aria-label="Show full API key"
                                                title="Show and copy full key"
                                            >
                                                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                </svg>
                                                Show Key
                                            </button>
                                            <button
                                                type="button"
                                                class="btn btn-secondary btn-sm action-btn"
                                                data-action="toggle"
                                                data-key-id="<?= $escaper->attribute($key['id']) ?>"
                                                data-key-name="<?= $escaper->attribute($key['key_name']) ?>"
                                                data-key-enabled="<?= $key['enabled'] ? '1' : '0' ?>"
                                                aria-label="<?= $key['enabled'] ? 'Disable' : 'Enable' ?> API key"
                                                title="<?= $key['enabled'] ? 'Disable this key' : 'Enable this key' ?>"
                                            >
                                                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                                </svg>
                                                <?= $key['enabled'] ? 'Disable' : 'Enable' ?>
                                            </button>
                                            <button
                                                type="button"
                                                class="btn btn-danger btn-sm action-btn"
                                                data-action="delete"
                                                data-key-id="<?= $escaper->attribute($key['id']) ?>"
                                                data-key-name="<?= $escaper->attribute($key['key_name']) ?>"
                                                aria-label="Delete API key"
                                                title="Delete this key permanently"
                                            >
                                                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                </svg>
                                                Delete
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>


            <!-- Data Retention Section -->
            <section class="card" id="data-retention">
                <div class="card-header">
                    <h3>Data Retention</h3>
                </div>
                <div class="card-body">
                    <form id="retention-form" method="POST" action="/settings/retention">
                        <?= $csrf->field() ?>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="articles-days" class="form-label">Keep Articles For</label>
                                <div class="input-with-unit">
                                    <input
                                        type="number"
                                        id="articles-days"
                                        name="articles_days"
                                        class="input-field"
                                        min="0"
                                        value="<?= $escaper->attribute($retentionArticlesDays) ?>"
                                    >
                                    <span class="unit">days</span>
                                </div>
                                <p class="text-small text-muted">0 = keep forever</p>
                            </div>

                            <div class="form-group">
                                <label for="logs-days" class="form-label">Keep Logs For</label>
                                <div class="input-with-unit">
                                    <input
                                        type="number"
                                        id="logs-days"
                                        name="logs_days"
                                        class="input-field"
                                        min="7"
                                        max="365"
                                        value="<?= $escaper->attribute($retentionLogsDays) ?>"
                                    >
                                    <span class="unit">days</span>
                                </div>
                                <p class="text-small text-muted">Minimum 7 days recommended</p>
                            </div>
                        </div>

                        <div class="settings-group">
                            <label class="checkbox">
                                <input
                                    type="checkbox"
                                    id="auto-cleanup"
                                    name="auto_cleanup"
                                    value="1"
                                    <?= $autoCleanup ? 'checked' : '' ?>
                                >
                                <span>Enable automatic cleanup</span>
                            </label>
                            <p class="text-small text-muted">
                                Requires a cron job to run at 2:00 AM daily
                            </p>
                        </div>

                        <?php if ($lastCleanupRun): ?>
                            <div class="settings-group">
                                <label class="form-label">Last Cleanup</label>
                                <p class="text-small">
                                    <time datetime="<?= $escaper->attribute($lastCleanupRun) ?>">
                                        <?= $escaper->html(date('F d, Y at H:i:s', strtotime($lastCleanupRun))) ?>
                                    </time>
                                </p>
                            </div>
                        <?php else: ?>
                            <div class="settings-group">
                                <p class="text-small text-muted">Cleanup has never been run</p>
                            </div>
                        <?php endif; ?>

                        <div class="settings-group">
                            <form id="run-cleanup-form" method="POST" action="/settings/cleanup">
                                <?= $csrf->field() ?>
                                <button
                                    type="submit"
                                    class="btn btn-secondary"
                                    aria-label="Run cleanup immediately"
                                >
                                    🗑️ Run Cleanup Now
                                </button>
                            </form>
                        </div>

                        <div class="form-actions">
                            <button
                                type="submit"
                                class="btn btn-primary"
                                form="retention-form"
                                aria-label="Save retention settings"
                            >
                                Save Settings
                            </button>
                        </div>
                    </form>

                </div>
            </section>

            <!-- Processing Options Section -->
            <section class="card" id="processing-options">
                <div class="card-header">
                    <h3>Processing Options</h3>
                </div>
                <div class="card-body">
                    <form id="processing-form" method="POST" action="/settings/processing">
                        <?= $csrf->field() ?>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="timeout" class="form-label">Timeout Per Article</label>
                                <div class="input-with-unit">
                                    <input
                                        type="number"
                                        id="timeout"
                                        name="timeout"
                                        class="input-field"
                                        min="5"
                                        max="300"
                                        value="<?= $escaper->attribute($processingTimeout) ?>"
                                    >
                                    <span class="unit">seconds</span>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="max-retries" class="form-label">Max Retries</label>
                                <div class="input-with-unit">
                                    <input
                                        type="number"
                                        id="max-retries"
                                        name="max_retries"
                                        class="input-field"
                                        min="0"
                                        max="10"
                                        value="<?= $escaper->attribute($maxRetries) ?>"
                                    >
                                    <span class="unit">attempts</span>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="retry-delay" class="form-label">Retry Delay</label>
                                <div class="input-with-unit">
                                    <input
                                        type="number"
                                        id="retry-delay"
                                        name="retry_delay"
                                        class="input-field"
                                        min="10"
                                        max="3600"
                                        value="<?= $escaper->attribute($retryDelay) ?>"
                                    >
                                    <span class="unit">seconds</span>
                                </div>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button
                                type="submit"
                                class="btn btn-primary"
                                aria-label="Save processing options"
                            >
                                Save Settings
                            </button>
                        </div>
                    </form>
                </div>
            </section>
        </div>
    </main>

    <!-- Modals -->
    <!-- Add/Edit API Key Modal -->
    <div id="api-key-modal" class="modal" style="display: none;">
        <div class="modal-overlay"></div>
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modal-title">Create API Key</h3>
                <button type="button" class="modal-close" aria-label="Close dialog">&times;</button>
            </div>

            <form id="api-key-form" method="POST">
                <?= $csrf->field() ?>

                <div class="modal-body">
                    <div class="form-group">
                        <label for="key-name" class="form-label required">Key Name</label>
                        <input
                            type="text"
                            id="key-name"
                            name="key_name"
                            class="input-field"
                            placeholder="e.g., SNAM Production Server"
                            maxlength="255"
                            required
                        >
                        <p class="form-hint">A descriptive name to identify this key</p>
                    </div>

                    <div class="form-group">
                        <label for="key-description" class="form-label">Description (Optional)</label>
                        <textarea
                            id="key-description"
                            name="description"
                            class="input-field"
                            rows="3"
                            placeholder="What is this key used for? E.g., 'Used by SNAM to pull RSS feeds'"
                            maxlength="500"
                        ></textarea>
                        <p class="form-hint">Note what this key is for and who uses it</p>
                    </div>

                    <div class="form-group">
                        <label class="checkbox">
                            <input
                                type="checkbox"
                                id="key-enabled"
                                name="enabled"
                                value="1"
                                checked
                            >
                            <span>Enable this key immediately</span>
                        </label>
                        <p class="form-hint">Uncheck to create the key in disabled state</p>
                    </div>
                </div>

                <div id="generated-key-section" style="display: none;">
                    <div class="alert alert-success">
                        <strong>API Key Generated!</strong>
                        <p class="text-small" style="margin-top: var(--space-2);">
                            Save this key securely. It won't be shown again.
                        </p>
                    </div>

                    <div class="form-group">
                        <label for="generated-key" class="form-label">Generated Key</label>
                        <div class="key-display-container">
                            <input
                                type="text"
                                id="generated-key"
                                class="input-field"
                                readonly
                            >
                            <button
                                type="button"
                                class="btn btn-secondary btn-small"
                                id="copy-key"
                                aria-label="Copy API key to clipboard"
                            >
                                📋 Copy
                            </button>
                        </div>
                    </div>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary modal-close-btn">Cancel</button>
                    <button type="submit" id="save-key-btn" class="btn btn-primary">Create Key</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Show Full Key Modal -->
    <div id="show-key-modal" class="modal" style="display: none;">
        <div class="modal-overlay"></div>
        <div class="modal-content">
            <div class="modal-header">
                <h3>API Key Details</h3>
                <button type="button" class="modal-close" aria-label="Close dialog">&times;</button>
            </div>

            <div class="modal-body">
                <div class="form-group">
                    <label for="full-key" class="form-label">Full Key Value</label>
                    <div class="key-display-container">
                        <input
                            type="text"
                            id="full-key"
                            class="input-field"
                            readonly
                        >
                        <button
                            type="button"
                            class="btn btn-secondary btn-small"
                            id="copy-full-key"
                            aria-label="Copy full API key"
                        >
                            📋 Copy
                        </button>
                    </div>
                </div>

                <div class="alert alert-warning">
                    <strong>Keep this key secure!</strong> Anyone with this key can access your Unfurl API.
                </div>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary modal-close-btn">Close</button>
            </div>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div id="confirm-modal" class="modal" style="display: none;">
        <div class="modal-overlay"></div>
        <div class="modal-content" style="max-width: 450px;">
            <div class="modal-header">
                <h3 id="confirm-title">Confirm Action</h3>
                <button type="button" class="modal-close" aria-label="Close dialog">&times;</button>
            </div>

            <div class="modal-body">
                <p id="confirm-message"></p>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary modal-close-btn">Cancel</button>
                <button type="button" id="confirm-action-btn" class="btn btn-primary">Confirm</button>
            </div>
        </div>
    </div>


    <!-- Styles -->
    <style>
        .settings-container {
            display: grid;
            gap: var(--space-6);
            margin-top: var(--space-6);
        }

        .settings-group {
            margin-bottom: var(--space-5);
        }

        .settings-group:last-child {
            margin-bottom: 0;
        }

        .group-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--space-3);
        }

        .form-label {
            display: block;
            font-weight: 500;
            margin-bottom: var(--space-2);
            color: var(--color-text);
        }

        .form-label.required::after {
            content: ' *';
            color: var(--color-error);
        }

        .api-endpoint-container,
        .key-display-container {
            display: flex;
            gap: var(--space-2);
        }

        .api-endpoint-container .input-field,
        .key-display-container .input-field {
            flex: 1;
        }

        .api-keys-list {
            display: grid;
            gap: var(--space-4);
        }

        .api-key-card {
            border: 1px solid var(--color-border);
            border-radius: var(--radius-md);
            padding: var(--space-5);
            background-color: var(--color-surface);
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .api-key-card:hover {
            border-color: var(--color-primary);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .api-key-header {
            margin-bottom: var(--space-4);
        }

        .api-key-info {
            flex: 1;
        }

        .api-key-name-row {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            margin-bottom: var(--space-2);
        }

        .api-key-name {
            margin: 0;
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--color-text);
            flex: 1;
        }

        .api-key-description {
            margin: 0;
            font-size: 0.9rem;
            color: var(--color-text-muted);
            line-height: 1.5;
        }

        .key-preview {
            font-family: 'Monaco', 'Menlo', 'Courier New', monospace;
            font-size: 0.85rem;
            background: rgba(0, 0, 0, 0.05);
            padding: 2px 6px;
            border-radius: 4px;
            letter-spacing: 0.5px;
        }

        .code-example {
            position: relative;
            background: var(--color-bg);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-md);
            padding: var(--space-3);
            display: flex;
            align-items: center;
            gap: var(--space-2);
        }

        .code-example code {
            font-family: 'Monaco', 'Menlo', 'Courier New', monospace;
            font-size: 0.85rem;
            flex: 1;
            word-break: break-all;
            color: var(--color-text);
        }

        .btn-copy-code {
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-sm);
            padding: var(--space-2);
            cursor: pointer;
            flex-shrink: 0;
            transition: all 0.2s ease;
            font-size: 1rem;
        }

        .btn-copy-code:hover {
            background: var(--color-primary);
            color: white;
            transform: scale(1.1);
        }

        .api-key-details {
            background-color: var(--color-bg);
            border-radius: var(--radius-md);
            padding: var(--space-4);
            margin-bottom: var(--space-4);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .detail-row {
            display: grid;
            grid-template-columns: 110px 1fr;
            gap: var(--space-3);
            padding: var(--space-2) 0;
            font-size: 0.9rem;
            align-items: center;
        }

        .detail-row:not(:last-child) {
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        .detail-label {
            font-weight: 500;
            color: var(--color-text-muted);
        }

        .detail-value {
            color: var(--color-text);
            word-break: break-all;
        }

        .api-key-actions {
            display: flex;
            gap: var(--space-2);
            flex-wrap: wrap;
        }

        .action-btn {
            display: inline-flex;
            align-items: center;
            gap: var(--space-2);
            font-size: 0.875rem;
            transition: all 0.2s ease;
        }

        .action-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .action-btn svg {
            flex-shrink: 0;
            transition: transform 0.2s ease;
        }

        .action-btn:hover svg {
            transform: scale(1.1);
        }

        .status-indicator {
            padding: var(--space-3);
            background-color: var(--color-bg);
            border-radius: var(--radius-md);
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: var(--space-2);
            padding: var(--space-2) var(--space-3);
            border-radius: var(--radius-full);
            font-size: 0.9rem;
            font-weight: 500;
            background-color: rgba(233, 196, 106, 0.15);
            color: #92400E;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: var(--space-4);
            margin-bottom: var(--space-4);
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .input-with-unit {
            display: flex;
            gap: var(--space-2);
            align-items: center;
        }

        .input-with-unit .input-field {
            flex: 1;
            max-width: 150px;
        }

        .unit {
            font-size: 0.9rem;
            color: var(--color-text-muted);
            white-space: nowrap;
        }

        .form-hint {
            margin-top: var(--space-1);
            font-size: 0.85rem;
            color: var(--color-text-muted);
            line-height: 1.4;
        }

        .form-actions {
            margin-top: var(--space-6);
            padding-top: var(--space-4);
            border-top: 1px solid var(--color-border);
        }

        .button-group {
            display: flex;
            gap: var(--space-2);
            flex-wrap: wrap;
        }

        .modal {
            position: fixed;
            inset: 0;
            display: none;
            z-index: 1000;
            animation: fadeIn 0.2s ease;
        }

        .modal.show {
            display: flex;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        .modal-overlay {
            position: absolute;
            inset: 0;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            position: relative;
            margin: auto;
            background-color: white;
            border-radius: var(--radius-lg);
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: var(--shadow-xl);
            animation: slideUp 0.3s ease;
        }

        @keyframes slideUp {
            from {
                transform: translateY(20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-content.modal-lg {
            max-width: 700px;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: var(--space-5);
            border-bottom: 1px solid var(--color-border);
        }

        .modal-header h3 {
            margin: 0;
            font-size: 1.25rem;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--color-text-muted);
            padding: 0;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-close:hover {
            color: var(--color-text);
        }

        .modal-body {
            padding: var(--space-5);
        }

        .modal-actions {
            display: flex;
            gap: var(--space-3);
            padding: var(--space-5);
            border-top: 1px solid var(--color-border);
            justify-content: flex-end;
        }

        .cron-instructions h4 {
            font-size: 1rem;
            font-weight: 600;
            margin: var(--space-4) 0 var(--space-2) 0;
            color: var(--color-text);
        }

        .cron-instructions p {
            margin: var(--space-2) 0;
            color: var(--color-text);
        }

        .cron-instructions ul {
            margin: var(--space-2) 0 var(--space-2) var(--space-6);
            color: var(--color-text);
        }

        .cron-instructions li {
            margin: var(--space-1) 0;
        }

        .code-block {
            display: flex;
            gap: var(--space-2);
            align-items: center;
            background-color: var(--color-bg);
            padding: var(--space-3);
            border-radius: var(--radius-md);
            margin: var(--space-2) 0;
            overflow-x: auto;
        }

        .code-block code {
            font-family: var(--font-mono);
            font-size: 0.85rem;
            flex: 1;
            overflow-x: auto;
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }

            .api-key-header {
                flex-direction: column;
            }

            .api-key-actions {
                flex-wrap: wrap;
            }

            .modal-content {
                max-height: 80vh;
            }

            .button-group {
                flex-direction: column;
            }
        }
    </style>

    <!-- Scripts -->
    <script type="module">
        import { api } from '/assets/js/api.js';
        import { Notify } from '/assets/js/notifications.js';
        import { DOM } from '/assets/js/utils.js';

        class SettingsController {
            constructor() {
                this.setupModals();
                this.setupCopyButtons();
                this.setupFormSubmissions();
                this.setupActionButtons();
            }

            setupModals() {
                // Modal closing
                DOM.selectAll('.modal-close, .modal-close-btn, .modal-overlay').forEach(el => {
                    DOM.on(el, 'click', (e) => {
                        if (e.target === el || el.classList.contains('modal-close') || el.classList.contains('modal-close-btn')) {
                            this.closeModals();
                        }
                    });
                });

                // Close on Escape
                DOM.on(document, 'keydown', (e) => {
                    if (e.key === 'Escape') this.closeModals();
                });

                // Add API key button
                const addKeyBtn = DOM.select('#add-api-key-btn');
                const createFirstKeyBtn = DOM.select('#create-first-key');
                if (addKeyBtn) {
                    DOM.on(addKeyBtn, 'click', () => this.openApiKeyModal());
                }
                if (createFirstKeyBtn) {
                    DOM.on(createFirstKeyBtn, 'click', () => this.openApiKeyModal());
                }
            }

            setupCopyButtons() {
                // Copy code examples with actual API key
                document.addEventListener('click', (e) => {
                    const btn = e.target.closest('.btn-copy-code');
                    if (!btn) return;

                    const copyType = btn.dataset.copyType;
                    const apiKeys = document.querySelectorAll('.api-key-card');

                    // Find the first enabled key
                    let apiKey = null;
                    apiKeys.forEach(card => {
                        const badge = card.querySelector('.badge-success');
                        if (badge && !apiKey) {
                            const keyValue = card.querySelector('.action-btn[data-action="show"]')?.dataset.keyValue;
                            if (keyValue) apiKey = keyValue;
                        }
                    });

                    if (!apiKey) {
                        Notify.warning('Please create an API key first');
                        return;
                    }

                    const baseUrl = window.location.origin + '/api/feed';
                    let textToCopy = '';

                    if (copyType === 'header') {
                        textToCopy = `curl -H "X-API-Key: ${apiKey}" ${baseUrl}`;
                    } else if (copyType === 'query') {
                        textToCopy = `${baseUrl}?key=${apiKey}`;
                    }

                    this.copyTextToClipboard(textToCopy);
                });

                // Copy full key (in show key modal)
                const copyFullKey = DOM.select('#copy-full-key');
                if (copyFullKey) {
                    DOM.on(copyFullKey, 'click', () => this.copyToClipboard('#full-key'));
                }
            }

            setupActionButtons() {
                // Event delegation for action buttons
                document.addEventListener('click', (e) => {
                    const btn = e.target.closest('.action-btn');
                    if (!btn) return;

                    const action = btn.dataset.action;
                    const keyId = btn.dataset.keyId;

                    switch(action) {
                        case 'show':
                            this.showFullKey(btn.dataset.keyValue);
                            break;
                        case 'toggle':
                            this.toggleKey(keyId, btn.dataset.keyName, btn.dataset.keyEnabled === '1');
                            break;
                        case 'delete':
                            this.deleteKey(keyId, btn.dataset.keyName);
                            break;
                    }
                });
            }

            showFullKey(keyValue) {
                const fullKeyInput = DOM.select('#full-key');
                if (fullKeyInput) {
                    fullKeyInput.value = keyValue;
                    this.openModal('show-key-modal');
                }
            }

            toggleKey(keyId, keyName, isEnabled) {
                const action = isEnabled ? 'disable' : 'enable';
                const title = isEnabled ? 'Disable API Key' : 'Enable API Key';
                const message = `Are you sure you want to ${action} the API key "${keyName}"?`;

                this.showConfirmModal(title, message, () => {
                    this.submitAction(`/settings/api-keys/${keyId}/toggle`);
                });
            }

            deleteKey(keyId, keyName) {
                const title = 'Delete API Key';
                const message = `Are you sure you want to permanently delete the API key "${keyName}"?\n\nThis action cannot be undone.`;

                this.showConfirmModal(title, message, () => {
                    this.submitAction(`/settings/api-keys/${keyId}/delete`);
                }, 'btn-danger');
            }

            showConfirmModal(title, message, onConfirm, confirmBtnClass = 'btn-primary') {
                const modal = DOM.select('#confirm-modal');
                const titleEl = DOM.select('#confirm-title');
                const messageEl = DOM.select('#confirm-message');
                const confirmBtn = DOM.select('#confirm-action-btn');

                if (!modal || !titleEl || !messageEl || !confirmBtn) return;

                // Set content
                titleEl.textContent = title;
                messageEl.textContent = message;

                // Update button style
                confirmBtn.className = `btn ${confirmBtnClass}`;

                // Remove old listener and add new one
                const newBtn = confirmBtn.cloneNode(true);
                confirmBtn.parentNode.replaceChild(newBtn, confirmBtn);

                DOM.on(newBtn, 'click', () => {
                    this.closeModals();
                    onConfirm();
                });

                // Show modal
                this.openModal('confirm-modal');
            }

            submitAction(action) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = action;

                // Add CSRF token
                const csrfToken = document.querySelector('input[name="csrf_token"]')?.value;
                if (csrfToken) {
                    const tokenInput = document.createElement('input');
                    tokenInput.type = 'hidden';
                    tokenInput.name = 'csrf_token';
                    tokenInput.value = csrfToken;
                    form.appendChild(tokenInput);
                }

                document.body.appendChild(form);
                form.submit();
            }

            setupFormSubmissions() {
                // API key form
                const apiKeyForm = DOM.select('#api-key-form');
                if (apiKeyForm) {
                    DOM.on(apiKeyForm, 'submit', (e) => {
                        e.preventDefault();
                        apiKeyForm.action = '/settings/api-keys/create';
                        apiKeyForm.submit();
                    });
                }
            }

            openApiKeyModal() {
                this.openModal('api-key-modal');
                DOM.select('#modal-title').textContent = 'Create API Key';
                const form = DOM.select('#api-key-form');
                if (form) form.reset();
            }

            openModal(modalId) {
                const modal = DOM.select('#' + modalId);
                if (modal) {
                    modal.style.display = 'flex';
                    DOM.addClass(modal, 'show');
                }
            }

            closeModals() {
                DOM.selectAll('.modal.show').forEach(modal => {
                    modal.style.display = 'none';
                    DOM.removeClass(modal, 'show');
                });
            }

            copyToClipboard(selector) {
                const field = DOM.select(selector);
                if (!field) return;

                field.select();
                document.execCommand('copy');
                Notify.success('Copied to clipboard');
            }

            copyTextToClipboard(text) {
                // Create temporary textarea
                const textarea = document.createElement('textarea');
                textarea.value = text;
                textarea.style.position = 'fixed';
                textarea.style.opacity = '0';
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
                Notify.success('Copied to clipboard');
            }

            async handleFormSubmit(e, endpoint) {
                e.preventDefault();
                const form = e.target;
                const formData = new FormData(form);
                const data = Object.fromEntries(formData);

                try {
                    await api.post(endpoint, data);
                    Notify.success('Settings saved successfully');
                } catch (error) {
                    Notify.error('Failed to save settings');
                }
            }
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', () => {
            new SettingsController();
        });
    </script>

<?php include __DIR__ . '/partials/footer.php'; ?>
