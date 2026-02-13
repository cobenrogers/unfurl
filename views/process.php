<?php
/**
 * Processing View - Manual Feed Processing Interface
 *
 * Allows users to manually trigger feed processing with real-time progress tracking.
 *
 * Requirements: Section 5.3.3 of REQUIREMENTS.md
 * Security: CSRF tokens, XSS prevention via OutputEscaper
 */

use Unfurl\Security\CsrfToken;
use Unfurl\Security\OutputEscaper;

// Use CSRF instance from router if available, otherwise create new one
$csrf = $csrf ?? new CsrfToken();
$escaper = new OutputEscaper();

$page_title = 'Process Feeds';

// Get list of feeds (would come from controller in real implementation)
$feeds = $feeds ?? [];
$isProcessing = $isProcessing ?? false;
?>
<?php include __DIR__ . '/partials/header.php'; ?>

<div class="container py-6">
        <div class="page-header">
            <h2>Process Feeds</h2>
            <p class="text-muted">Select feeds to process and monitor progress</p>
        </div>

        <!-- Status Messages -->
        <div id="status-messages" role="status" aria-live="polite" aria-atomic="true"></div>

        <div class="process-container">
            <!-- Feed Selection Section -->
            <section class="card" id="feed-selection">
                <div class="card-header">
                    <h3>Select Feeds to Process</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($feeds)): ?>
                        <div class="alert alert-info">
                            <strong>No feeds configured yet.</strong>
                            <a href="/feeds/create">Create a feed</a> to get started.
                        </div>
                    <?php else: ?>
                        <form id="process-form" method="POST" action="/api/process">
                            <?= $csrf->field() ?>

                            <div class="feed-list">
                                <div class="feed-list-header">
                                    <label class="checkbox">
                                        <input type="checkbox" id="select-all-feeds" aria-label="Select all feeds">
                                        <span>Select All</span>
                                    </label>
                                </div>

                                <?php foreach ($feeds as $feed): ?>
                                    <div class="feed-item">
                                        <label class="checkbox">
                                            <input
                                                type="checkbox"
                                                class="feed-checkbox"
                                                name="feed_ids[]"
                                                value="<?= $escaper->attribute($feed['id']) ?>"
                                                data-feed-id="<?= $escaper->attribute($feed['id']) ?>"
                                            >
                                            <span class="feed-name">
                                                <?= $escaper->html($feed['topic']) ?>
                                            </span>
                                        </label>
                                        <div class="feed-meta">
                                            <span class="badge badge-info">
                                                <?= isset($feed['article_count']) ? (int)$feed['article_count'] : 0 ?> articles
                                            </span>
                                            <?php if (!empty($feed['last_processed_at'])): ?>
                                                <span class="text-small text-muted">
                                                    Last run: <time datetime="<?= $escaper->attribute($feed['last_processed_at']) ?>">
                                                        <?= $escaper->html($timezone->formatLocal($feed['last_processed_at'], 'M j, Y g:i A')) ?>
                                                    </time>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-small text-muted">Never processed</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="form-actions">
                                <button
                                    type="submit"
                                    id="process-button"
                                    class="btn btn-primary"
                                    disabled
                                    aria-label="Process selected feeds"
                                >
                                    <span class="btn-text">Process Selected Feeds</span>
                                    <span class="btn-loader" style="display: none;">
                                        <span class="spinner"></span> Processing...
                                    </span>
                                </button>
                                <p class="text-small text-muted">
                                    Select at least one feed to enable processing
                                </p>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Progress Section (Hidden until processing starts) -->
            <section class="card" id="progress-section" style="display: none;">
                <div class="card-header">
                    <h3>Processing Progress</h3>
                </div>
                <div class="card-body">
                    <!-- Overall Progress -->
                    <div class="progress-container">
                        <div class="progress-header">
                            <span class="progress-label">
                                Overall Progress:
                                <strong id="progress-percent">0%</strong>
                            </span>
                            <span class="progress-details">
                                <span id="progress-current">0</span> /
                                <span id="progress-total">0</span> articles
                            </span>
                        </div>
                        <div class="progress-bar">
                            <div id="progress-fill" class="progress-fill" style="width: 0%;" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </div>

                    <!-- Per-Feed Progress -->
                    <div id="feeds-progress" class="feeds-progress">
                        <!-- Dynamically populated -->
                    </div>

                    <!-- Processing Log -->
                    <div class="processing-log">
                        <details>
                            <summary class="log-summary">
                                <span>Processing Details</span>
                                <span class="log-count">
                                    (<span id="log-entry-count">0</span> entries)
                                </span>
                            </summary>
                            <div id="processing-log" class="log-content">
                                <!-- Log entries added dynamically -->
                            </div>
                        </details>
                    </div>
                </div>
            </section>

            <!-- Results Section (Hidden until processing completes) -->
            <section class="card" id="results-section" style="display: none;">
                <div class="card-header">
                    <h3>Processing Results</h3>
                </div>
                <div class="card-body">
                    <div class="results-summary">
                        <div class="result-stat">
                            <div class="result-value" id="result-total">0</div>
                            <div class="result-label">Total Articles</div>
                        </div>
                        <div class="result-stat success">
                            <div class="result-value" id="result-success">0</div>
                            <div class="result-label">Successfully Processed</div>
                        </div>
                        <div class="result-stat warning">
                            <div class="result-value" id="result-duplicates">0</div>
                            <div class="result-label">Duplicates Skipped</div>
                        </div>
                        <div class="result-stat error">
                            <div class="result-value" id="result-failed">0</div>
                            <div class="result-label">Failed</div>
                        </div>
                    </div>

                    <div class="result-timing">
                        <span class="text-small text-muted">
                            Processing took <strong id="result-time">0s</strong>
                        </span>
                    </div>

                    <div class="result-actions">
                        <a href="/articles" class="btn btn-primary">
                            View Processed Articles
                        </a>
                        <button type="button" id="reset-button" class="btn btn-secondary">
                            Process More Feeds
                        </button>
                    </div>
                </div>
            </section>
        </div>
    </main>

    <!-- Styles -->
    <style>
        .process-container {
            display: grid;
            gap: var(--space-6);
            margin-top: var(--space-6);
        }

        .feed-list {
            display: flex;
            flex-direction: column;
            gap: var(--space-3);
        }

        .feed-list-header {
            padding-bottom: var(--space-4);
            border-bottom: 1px solid var(--color-border);
            margin-bottom: var(--space-3);
        }

        .feed-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: var(--space-4);
            border: 1px solid var(--color-border-light);
            border-radius: var(--radius-md);
            transition: all 0.2s ease;
        }

        .feed-item:hover {
            border-color: var(--color-border);
            background-color: var(--color-bg);
        }

        .feed-item.selected {
            background-color: rgba(42, 157, 143, 0.15);
            border-color: var(--color-primary);
            border-width: 2px;
        }

        .feed-item label {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            flex: 1;
            cursor: pointer;
        }

        .feed-name {
            font-weight: 500;
            color: var(--color-text);
        }

        .feed-meta {
            display: flex;
            align-items: center;
            gap: var(--space-4);
            margin-left: auto;
        }

        .checkbox {
            display: flex;
            align-items: center;
            gap: var(--space-2);
            cursor: pointer;
            user-select: none;
        }

        .checkbox input[type="checkbox"] {
            width: 22px;
            height: 22px;
            cursor: pointer;
            accent-color: var(--color-primary);
            flex-shrink: 0;
            margin: 0;
            border: 2px solid var(--color-border);
            border-radius: 4px;
        }

        .checkbox input[type="checkbox"]:checked {
            background-color: var(--color-primary);
            border-color: var(--color-primary);
        }

        .feed-item:has(input[type="checkbox"]:checked) {
            background-color: rgba(42, 157, 143, 0.1);
            border-color: var(--color-primary);
        }

        .form-actions {
            margin-top: var(--space-6);
            padding-top: var(--space-4);
            border-top: 1px solid var(--color-border);
        }

        .progress-container {
            margin-bottom: var(--space-6);
        }

        .progress-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--space-3);
            font-size: 0.95rem;
        }

        .progress-bar {
            height: 24px;
            background-color: var(--color-border-light);
            border-radius: var(--radius-md);
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--color-primary), var(--color-primary-light));
            transition: width 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding-right: var(--space-2);
            color: white;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .feeds-progress {
            margin-bottom: var(--space-6);
        }

        .feed-progress-item {
            margin-bottom: var(--space-4);
            padding: var(--space-3);
            background-color: var(--color-bg);
            border-radius: var(--radius-md);
        }

        .feed-progress-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--space-2);
            font-size: 0.9rem;
        }

        .feed-progress-label {
            font-weight: 500;
            color: var(--color-text);
        }

        .feed-progress-status {
            display: flex;
            gap: var(--space-2);
            font-size: 0.85rem;
        }

        .feed-progress-status .badge {
            padding: var(--space-1) var(--space-3);
        }

        .processing-log {
            margin-top: var(--space-6);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-md);
            overflow: hidden;
        }

        .log-summary {
            padding: var(--space-4);
            background-color: var(--color-bg);
            cursor: pointer;
            user-select: none;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 500;
        }

        .log-summary:hover {
            background-color: var(--color-border-light);
        }

        .log-content {
            max-height: 400px;
            overflow-y: auto;
            padding: var(--space-4);
            background-color: var(--color-surface);
            font-family: var(--font-mono);
            font-size: 0.85rem;
        }

        .log-entry {
            padding: var(--space-2);
            border-left: 3px solid var(--color-border);
            margin-bottom: var(--space-2);
            word-break: break-word;
        }

        .log-entry.info {
            border-left-color: var(--color-info);
            color: var(--color-info);
        }

        .log-entry.success {
            border-left-color: var(--color-success);
            color: var(--color-success);
        }

        .log-entry.warning {
            border-left-color: var(--color-warning);
            color: var(--color-warning);
        }

        .log-entry.error {
            border-left-color: var(--color-error);
            color: var(--color-error);
        }

        .results-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: var(--space-4);
            margin-bottom: var(--space-6);
        }

        .result-stat {
            padding: var(--space-4);
            border: 2px solid var(--color-border);
            border-radius: var(--radius-md);
            text-align: center;
        }

        .result-stat.success {
            border-color: var(--color-success);
            background-color: rgba(42, 157, 143, 0.05);
        }

        .result-stat.warning {
            border-color: var(--color-warning);
            background-color: rgba(233, 196, 106, 0.05);
        }

        .result-stat.error {
            border-color: var(--color-error);
            background-color: rgba(231, 111, 81, 0.05);
        }

        .result-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--color-text);
        }

        .result-label {
            font-size: 0.85rem;
            color: var(--color-text-muted);
            margin-top: var(--space-2);
        }

        .result-timing {
            text-align: center;
            padding: var(--space-4);
            background-color: var(--color-bg);
            border-radius: var(--radius-md);
            margin-bottom: var(--space-6);
        }

        .result-actions {
            display: flex;
            gap: var(--space-3);
            justify-content: center;
        }

        .btn-loader {
            display: inline-flex;
            align-items: center;
            gap: var(--space-2);
        }

        .spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        @media (max-width: 768px) {
            .feed-meta {
                flex-wrap: wrap;
                width: 100%;
                margin-left: 0;
                margin-top: var(--space-3);
            }

            .feed-item {
                flex-direction: column;
                align-items: flex-start;
            }

            .results-summary {
                grid-template-columns: repeat(2, 1fr);
            }

            .result-actions {
                flex-direction: column;
            }

            .result-actions .btn {
                width: 100%;
            }
        }

        /* Keep button text white on hover */
        .btn-primary:hover,
        .btn-secondary:hover {
            color: white !important;
        }
    </style>

    <!-- Scripts -->
    <script type="module">
        import { api } from '/assets/js/api.js';
        import { Notify } from '/assets/js/notifications.js';
        import { DOM } from '/assets/js/utils.js';

        class ProcessController {
            constructor() {
                this.form = DOM.select('#process-form');
                this.selectAllCheckbox = DOM.select('#select-all-feeds');
                this.feedCheckboxes = Array.from(DOM.selectAll('.feed-checkbox'));
                this.processButton = DOM.select('#process-button');
                this.resetButton = DOM.select('#reset-button');
                this.feedSelectionSection = DOM.select('#feed-selection');
                this.progressSection = DOM.select('#progress-section');
                this.resultsSection = DOM.select('#results-section');
                this.startTime = null;

                this.setupListeners();
            }

            setupListeners() {
                // Select all checkbox
                if (this.selectAllCheckbox) {
                    DOM.on(this.selectAllCheckbox, 'change', (e) => this.handleSelectAll(e));
                }

                // Individual feed checkboxes
                this.feedCheckboxes.forEach(checkbox => {
                    DOM.on(checkbox, 'change', () => this.updateProcessButton());
                });

                // Process button
                if (this.form) {
                    DOM.on(this.form, 'submit', (e) => this.handleSubmit(e));
                }

                // Reset button - reload page to get fresh CSRF token
                if (this.resetButton) {
                    DOM.on(this.resetButton, 'click', () => window.location.reload());
                }
            }

            handleSelectAll(e) {
                const checked = e.target.checked;
                this.feedCheckboxes.forEach(checkbox => {
                    checkbox.checked = checked;
                });
                this.updateProcessButton();
            }

            updateProcessButton() {
                const selectedCount = this.feedCheckboxes.filter(cb => cb.checked).length;

                // Update feed item highlighting
                this.feedCheckboxes.forEach(checkbox => {
                    const feedItem = checkbox.closest('.feed-item');
                    if (feedItem) {
                        if (checkbox.checked) {
                            feedItem.classList.add('selected');
                        } else {
                            feedItem.classList.remove('selected');
                        }
                    }
                });

                if (selectedCount > 0) {
                    this.processButton.disabled = false;
                    this.processButton.setAttribute('aria-label',
                        `Process ${selectedCount} selected feed${selectedCount !== 1 ? 's' : ''}`);
                } else {
                    this.processButton.disabled = true;
                }
            }

            async handleSubmit(e) {
                e.preventDefault();

                const selectedIds = Array.from(this.feedCheckboxes)
                    .filter(cb => cb.checked)
                    .map(cb => cb.value);

                if (selectedIds.length === 0) {
                    Notify.error('Please select at least one feed');
                    return;
                }

                // Show progress section, hide selection
                this.feedSelectionSection.style.display = 'none';
                this.progressSection.style.display = 'block';
                this.resultsSection.style.display = 'none';

                this.startTime = Date.now();

                try {
                    await this.processFeeds(selectedIds);
                } catch (error) {
                    Notify.error('Processing failed: ' + error.message);
                    this.reset();
                }
            }

            async processFeeds(feedIds) {
                try {
                    // Step 1: Fetch RSS feeds and create pending articles
                    Notify.info('Fetching RSS feeds...');
                    const fetchResponse = await api.post('/api/feeds/fetch', {
                        feed_ids: feedIds
                    });

                    const articleIds = fetchResponse.article_ids || [];
                    const total = articleIds.length;

                    if (total === 0) {
                        Notify.warning('No new articles found');
                        this.reset();
                        return;
                    }

                    Notify.success(`Found ${total} articles. Processing...`);

                    // Initialize counters and get progress bar element
                    let processed = 0;
                    let succeeded = 0;
                    let failed = 0;
                    const fillEl = DOM.select('#progress-fill');

                    // Step 2: Process each article individually
                    for (let i = 0; i < articleIds.length; i++) {
                        const articleId = articleIds[i];
                        const current = i + 1;

                        // Update progress text
                        DOM.text(DOM.select('#progress-percent'), `Processing ${current}/${total}`);
                        DOM.text(DOM.select('#progress-current'), succeeded);
                        DOM.text(DOM.select('#progress-total'), total);

                        // Update progress bar
                        const progress = Math.round((current / total) * 100);
                        fillEl.style.width = progress + '%';

                        try {
                            // Process single article
                            const result = await api.post(`/api/articles/${articleId}/process`, {});

                            if (result.success) {
                                succeeded++;
                            } else {
                                failed++;
                            }
                            processed++;

                            // Update results in real-time
                            DOM.text(DOM.select('#result-total'), total);
                            DOM.text(DOM.select('#result-success'), succeeded);
                            DOM.text(DOM.select('#result-failed'), failed);
                            const elapsed = Math.round((Date.now() - this.startTime) / 1000);
                            DOM.text(DOM.select('#result-time'), elapsed + 's');

                        } catch (error) {
                            console.error(`Failed to process article ${articleId}:`, error);
                            failed++;
                            processed++;
                        }
                    }

                    // Final progress update
                    DOM.text(DOM.select('#progress-percent'), '100%');
                    fillEl.style.width = '100%';

                    // Show results
                    this.showResults({
                        total: total,
                        success: succeeded,
                        failed: failed,
                        processed: processed
                    });

                } catch (error) {
                    Notify.error('Processing error: ' + error.message);
                    throw error;
                }
            }

            updateResults(data) {
                const elapsed = Math.round((Date.now() - this.startTime) / 1000);

                DOM.text(DOM.select('#progress-percent'), Math.round(data.progress || 0) + '%');
                DOM.text(DOM.select('#progress-current'), data.processed || 0);
                DOM.text(DOM.select('#progress-total'), data.total || 0);

                const fillEl = DOM.select('#progress-fill');
                fillEl.style.width = (data.progress || 0) + '%';

                // Update results
                DOM.text(DOM.select('#result-total'), data.total || 0);
                DOM.text(DOM.select('#result-success'), data.success || 0);
                DOM.text(DOM.select('#result-duplicates'), data.duplicates || 0);
                DOM.text(DOM.select('#result-failed'), data.failed || 0);
                DOM.text(DOM.select('#result-time'), elapsed + 's');
            }

            showResults(data) {
                this.progressSection.style.display = 'none';
                this.resultsSection.style.display = 'block';

                const elapsed = Math.round((Date.now() - this.startTime) / 1000);
                Notify.success(`Processing complete in ${elapsed}s`);
            }

            reset() {
                this.feedSelectionSection.style.display = 'block';
                this.progressSection.style.display = 'none';
                this.resultsSection.style.display = 'none';
                this.form.reset();
                this.selectAllCheckbox.checked = false;
                this.feedCheckboxes.forEach(cb => cb.checked = false);
                this.updateProcessButton();
            }
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', () => {
            new ProcessController();
        });
    </script>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>
