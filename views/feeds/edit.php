<?php
/**
 * Edit Feed Form View
 *
 * Form for editing an existing feed.
 * Includes CSRF protection, validation, and feed information.
 *
 * Variables provided by controller:
 * - $feed (array) - Existing feed data (id, topic, url, result_limit, enabled, created_at, last_processed_at)
 * - $errors (array) - Validation errors (optional)
 * - $form_data (array) - Previously submitted form data (optional, falls back to $feed)
 */

use Unfurl\Security\OutputEscaper;
use Unfurl\Security\CsrfToken;

$page_title = 'Edit Feed';
$escaper = new OutputEscaper();
$csrf = new CsrfToken();

// Initialize form data from controller data or feed data
$form_data = $form_data ?? $feed ?? [];
$errors = $errors ?? [];
$feed_id = (int)($feed['id'] ?? 0);

include __DIR__ . '/../partials/header.php';
?>

<style>
/* Custom checkbox styling */
.feed-checkbox-wrapper {
    position: relative;
    padding: 1.25rem;
    background: linear-gradient(135deg, var(--color-surface) 0%, #f8fffe 100%);
    border: 2px solid var(--color-border);
    border-radius: var(--radius-md);
    transition: all var(--duration-short) var(--ease-in-out);
    cursor: pointer;
}

.feed-checkbox-wrapper:hover {
    border-color: var(--color-primary);
    background: linear-gradient(135deg, #ffffff 0%, #f0fffd 100%);
    box-shadow: var(--shadow-md);
    transform: translateY(-1px);
}

.feed-checkbox-wrapper input[type="checkbox"] {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
    pointer-events: none;
}

.checkbox-visual {
    width: 24px;
    height: 24px;
    border: 2px solid var(--color-primary);
    border-radius: 6px;
    background: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all var(--duration-short) var(--ease-out);
    flex-shrink: 0;
    position: relative;
}

.checkbox-visual::before {
    content: '';
    position: absolute;
    inset: -4px;
    border-radius: 8px;
    background: var(--color-primary-light);
    opacity: 0;
    transition: opacity var(--duration-micro);
}

.feed-checkbox-wrapper:hover .checkbox-visual::before {
    opacity: 0.1;
}

.feed-checkbox-wrapper input[type="checkbox"]:checked ~ .checkbox-content .checkbox-visual {
    background: var(--color-primary);
    border-color: var(--color-primary);
    transform: scale(1.05);
}

.feed-checkbox-wrapper input[type="checkbox"]:checked ~ .checkbox-content .checkbox-visual svg {
    opacity: 1;
    transform: scale(1);
}

.checkbox-visual svg {
    opacity: 0;
    transform: scale(0.5);
    transition: all var(--duration-short) var(--ease-out);
}

.checkbox {
    font-weight: 600;
    font-size: 0.9375rem;
    color: var(--color-text);
    margin-bottom: 0.25rem;
    display: block;
    transition: color var(--duration-micro);
}

.feed-checkbox-wrapper:hover .checkbox {
    color: var(--color-primary);
}

.checkbox-help {
    font-size: 0.8125rem;
    color: var(--color-text-muted);
    line-height: 1.5;
}
</style>

<div class="container py-6">
    <!-- Page Header -->
    <div class="mb-8">
        <a href="/feeds" style="color: var(--color-primary); text-decoration: none; font-size: 0.875rem; margin-bottom: var(--space-2); display: inline-block;">
            ← Back to Feeds
        </a>
        <h1 style="font-size: 2rem; font-weight: 700;">Edit Feed</h1>
    </div>

    <!-- Form Container -->
    <div class="grid lg:grid-cols-3 gap-6">
        <!-- Main Form -->
        <div class="lg:col-span-2">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="/feeds/<?= $feed_id ?>/edit" id="feed-form" novalidate>
                        <!-- CSRF Token -->
                        <?= $csrf->field() ?>

                        <!-- Topic Field -->
                        <div class="form-group">
                            <label for="topic" class="label-required">
                                Topic
                            </label>
                            <input type="text"
                                   id="topic"
                                   name="topic"
                                   class="input-field <?= isset($errors['topic']) ? 'error' : '' ?>"
                                   value="<?= $escaper->attribute($form_data['topic'] ?? '') ?>"
                                   placeholder="e.g., Technology News, Artificial Intelligence"
                                   required
                                   data-label="Topic"
                                   minlength="3"
                                   maxlength="255">
                            <span class="help-text">
                                Search topic for Google News feed (3-255 characters)
                            </span>
                            <?php if (isset($errors['topic'])): ?>
                                <div class="error-message">
                                    <?= $escaper->html(implode(', ', (array)$errors['topic'])) ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- URL Field (Read-only) -->
                        <div class="form-group">
                            <label for="url" class="label-required">
                                RSS Feed URL
                            </label>
                            <div class="input-group">
                                <div class="input-group-addon">
                                    <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor">
                                        <path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zm0 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V8zm0 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1v-2zm0 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1v-2z"/>
                                    </svg>
                                </div>
                                <input type="text"
                                       id="url"
                                       name="url"
                                       class="input-field"
                                       value="<?= $escaper->attribute($form_data['url'] ?? '') ?>"
                                       readonly
                                       placeholder="Feed URL"
                                       style="background-color: var(--color-border-light); cursor: not-allowed;">
                            </div>
                            <span class="help-text">
                                URL updates automatically when you change the topic
                            </span>
                        </div>

                        <!-- Result Limit Field -->
                        <div class="form-group">
                            <label for="limit" class="label-required">
                                Result Limit
                            </label>
                            <input type="number"
                                   id="limit"
                                   name="limit"
                                   class="input-field <?= isset($errors['limit']) ? 'error' : '' ?>"
                                   value="<?= $escaper->attribute($form_data['result_limit'] ?? '10') ?>"
                                   placeholder="Number of articles to fetch"
                                   required
                                   data-label="Result Limit"
                                   min="1"
                                   max="100">
                            <span class="help-text">
                                Maximum number of articles to fetch per feed processing (1-100)
                            </span>
                            <?php if (isset($errors['result_limit'])): ?>
                                <div class="error-message">
                                    <?= $escaper->html(implode(', ', (array)$errors['result_limit'])) ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Enabled Checkbox -->
                        <div class="form-group">
                            <label class="feed-checkbox-wrapper">
                                <input type="checkbox"
                                       id="enabled"
                                       name="enabled"
                                       value="1"
                                       <?= (isset($form_data['enabled']) && $form_data['enabled']) ? 'checked' : '' ?>>
                                <div class="checkbox-content" style="display: flex; align-items: flex-start; gap: 0.875rem;">
                                    <div class="checkbox-visual">
                                        <svg width="16" height="16" viewBox="0 0 20 20" fill="none">
                                            <path d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                                  fill="white"
                                                  stroke="white"
                                                  stroke-width="1"/>
                                        </svg>
                                    </div>
                                    <div style="flex: 1;">
                                        <span class="checkbox">
                                            Enable feed for automatic processing
                                        </span>
                                        <span class="checkbox-help">
                                            When enabled, this feed will be processed automatically by scheduled tasks to fetch and aggregate new articles
                                        </span>
                                    </div>
                                </div>
                            </label>
                        </div>

                        <!-- Form Actions -->
                        <div class="flex gap-3 mt-6 pt-4 border-t">
                            <button type="submit" class="btn btn-primary">
                                <svg width="18" height="18" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"/>
                                </svg>
                                Save Changes
                            </button>
                            <a href="/feeds" class="btn btn-secondary">
                                Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Sidebar Info -->
        <div class="hidden lg:block">
            <!-- Feed Info Card -->
            <div class="card">
                <div class="card-header">
                    <h3 style="font-size: 0.875rem; font-weight: 600; margin: 0;">Feed Information</h3>
                </div>
                <div class="card-body">
                    <div style="margin-bottom: var(--space-4);">
                        <p style="font-size: 0.75rem; color: var(--color-text-muted); margin-bottom: var(--space-1);">
                            Feed ID
                        </p>
                        <p style="font-family: var(--font-mono); font-size: 0.875rem; word-break: break-all;">
                            #<?= (int)$feed_id ?>
                        </p>
                    </div>

                    <?php if (!empty($feed['created_at'])): ?>
                        <div style="margin-bottom: var(--space-4);">
                            <p style="font-size: 0.75rem; color: var(--color-text-muted); margin-bottom: var(--space-1);">
                                Created
                            </p>
                            <p style="font-size: 0.875rem;">
                                <?= $timezone->formatLocal($feed['created_at'], 'M j, Y \a\t g:i A') ?>
                            </p>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($feed['last_processed_at'])): ?>
                        <div style="margin-bottom: var(--space-4);">
                            <p style="font-size: 0.75rem; color: var(--color-text-muted); margin-bottom: var(--space-1);">
                                Last Processed
                            </p>
                            <p style="font-size: 0.875rem;">
                                <?= $timezone->formatLocal($feed['last_processed_at'], 'M j, Y \a\t g:i A') ?>
                            </p>
                        </div>
                    <?php else: ?>
                        <div style="margin-bottom: var(--space-4);">
                            <p style="font-size: 0.75rem; color: var(--color-text-muted); margin-bottom: var(--space-1);">
                                Last Processed
                            </p>
                            <p style="font-size: 0.875rem; color: var(--color-text-muted);">
                                <em>Never</em>
                            </p>
                        </div>
                    <?php endif; ?>

                    <hr style="margin: var(--space-3) 0; border: none; border-top: 1px solid var(--color-border);">

                    <div style="display: flex; gap: var(--space-2);">
                        <a href="/feeds/<?= $feed_id ?>/view" class="btn btn-sm btn-secondary flex-1" style="text-align: center;">
                            View Feed
                        </a>
                        <button type="button" class="btn btn-sm btn-ghost" onclick="copyFeedUrl();" title="Copy feed URL to clipboard">
                            <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M8 3a1 1 0 011 1v2H7V4a1 1 0 011-1h10a1 1 0 011 1v10a1 1 0 01-1 1h-2v2h2a3 3 0 003-3V4a3 3 0 00-3-3H8z"/>
                                <path d="M3 5a2 2 0 012-2h6a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2V5z"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Danger Zone -->
            <div class="card mt-4" style="border-color: var(--color-error);">
                <div class="card-header" style="background-color: #FEE2E2;">
                    <h3 style="font-size: 0.875rem; font-weight: 600; margin: 0; color: #991B1B;">Danger Zone</h3>
                </div>
                <div class="card-body">
                    <p style="font-size: 0.875rem; color: var(--color-text-muted); margin-bottom: var(--space-3);">
                        Deleting a feed will remove it and all associated processing history. This action cannot be undone.
                    </p>
                    <form method="POST" action="/feeds/<?= $feed_id ?>/delete" id="delete-form"
                          onsubmit="return confirmDelete(this, 'Are you sure you want to delete this feed? This action cannot be undone.');">
                        <?= $csrf->field() ?>
                        <button type="submit" class="btn btn-danger btn-sm w-full">
                            Delete Feed
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Client-side Form Validation & URL Management Script -->
<script type="module">
    import { FormValidator } from '/assets/js/forms.js';

    document.addEventListener('DOMContentLoaded', () => {
        const form = document.getElementById('feed-form');
        const validator = new FormValidator();

        form.addEventListener('submit', (e) => {
            const rules = {
                topic: ['required', 'min:3', 'max:255'],
                result_limit: ['required', 'number', 'min:1', 'max:100']
            };

            const errors = validator.validate(form, rules);

            if (Object.keys(errors).length > 0) {
                e.preventDefault();
                validator.displayErrors(form, errors);
            }
        });

        // Auto-generate URL from topic
        const topicField = document.getElementById('topic');
        const urlField = document.getElementById('url');

        topicField.addEventListener('input', (e) => {
            const topic = e.target.value;
            if (topic) {
                urlField.value = `/rss/feed?topic=${encodeURIComponent(topic)}`;
            } else {
                urlField.value = '';
            }
        });
    });

    // Copy feed URL to clipboard
    window.copyFeedUrl = function() {
        const urlField = document.getElementById('url');
        if (urlField && urlField.value) {
            navigator.clipboard.writeText(window.location.origin + urlField.value).then(() => {
                // Show temporary success message
                const btn = event.target.closest('button');
                const originalText = btn.innerHTML;
                btn.innerHTML = '✓ Copied!';
                btn.style.color = 'var(--color-success)';
                setTimeout(() => {
                    btn.innerHTML = originalText;
                    btn.style.color = '';
                }, 2000);
            }).catch(err => {
                console.error('Failed to copy:', err);
            });
        }
    };
</script>

<?php include __DIR__ . '/../partials/footer.php'; ?>
