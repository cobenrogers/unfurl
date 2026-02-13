<?php
/**
 * Create Feed Form View
 *
 * Form for creating a new feed with validation.
 * Includes CSRF protection and client-side validation.
 *
 * Variables provided by controller:
 * - $errors (array) - Validation errors (optional)
 * - $form_data (array) - Previously submitted form data (optional)
 */

use Unfurl\Security\OutputEscaper;
use Unfurl\Security\CsrfToken;

$page_title = 'Create Feed';
$escaper = new OutputEscaper();
$csrf = new CsrfToken();

// Initialize form data
$form_data = $form_data ?? [];
$errors = $errors ?? [];

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

/* Form field enhancements */
.form-field-enhanced {
    position: relative;
}

.form-field-enhanced input:focus {
    outline: none;
    border-color: var(--color-primary);
    box-shadow: 0 0 0 3px rgba(13, 115, 119, 0.1);
}

.field-icon {
    color: var(--color-text-muted);
    transition: color var(--duration-micro);
}

.form-field-enhanced input:focus ~ .field-icon {
    color: var(--color-primary);
}

/* Enhanced submit button */
.btn-primary-enhanced {
    position: relative;
    overflow: hidden;
}

.btn-primary-enhanced::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.2);
    transform: translate(-50%, -50%);
    transition: width var(--duration-base) var(--ease-out),
                height var(--duration-base) var(--ease-out);
}

.btn-primary-enhanced:hover::before {
    width: 300px;
    height: 300px;
}
</style>

<div class="container py-6">
    <!-- Page Header -->
    <div class="mb-8">
        <a href="/feeds" style="color: var(--color-primary); text-decoration: none; font-size: 0.875rem; margin-bottom: var(--space-2); display: inline-flex; align-items: center; gap: 0.375rem; transition: gap var(--duration-micro);">
            <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd"/>
            </svg>
            <span>Back to Feeds</span>
        </a>
        <h1 style="font-size: 2rem; font-weight: 700; margin-top: 0.75rem;">Create New Feed</h1>
        <p style="color: var(--color-text-muted); margin-top: 0.5rem; font-size: 0.9375rem;">Configure a Google News RSS feed for automatic content aggregation</p>
    </div>

    <!-- Form Container -->
    <div class="grid lg:grid-cols-3 gap-6">
        <!-- Main Form -->
        <div class="lg:col-span-2">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="/feeds" id="feed-form" novalidate>
                        <!-- CSRF Token -->
                        <?= $csrf->field() ?>

                        <!-- Topic Field -->
                        <div class="form-group">
                            <label for="topic" class="label-required" style="font-weight: 600; margin-bottom: 0.5rem; display: block;">
                                Topic
                            </label>
                            <input type="text"
                                   id="topic"
                                   name="topic"
                                   class="input-field <?= isset($errors['topic']) ? 'error' : '' ?>"
                                   value="<?= $escaper->attribute($form_data['topic'] ?? '') ?>"
                                   placeholder="e.g., Technology News, Artificial Intelligence, Web Development"
                                   required
                                   data-label="Topic"
                                   minlength="3"
                                   maxlength="255"
                                   style="transition: all var(--duration-short) var(--ease-in-out);">
                            <span class="help-text" style="display: block; margin-top: 0.5rem; font-size: 0.8125rem; color: var(--color-text-muted);">
                                Search topic for Google News feed (3-255 characters)
                            </span>
                            <?php if (isset($errors['topic'])): ?>
                                <div class="error-message" style="margin-top: 0.5rem; color: var(--color-error); font-size: 0.875rem;">
                                    <?= $escaper->html(implode(', ', (array)$errors['topic'])) ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- URL Field (Read-only) -->
                        <div class="form-group">
                            <label for="url" class="label-required" style="font-weight: 600; margin-bottom: 0.5rem; display: block;">
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
                                       placeholder="Auto-generated from topic"
                                       style="background-color: var(--color-border-light); cursor: not-allowed; font-family: var(--font-mono); font-size: 0.875rem;">
                            </div>
                            <span class="help-text" style="display: block; margin-top: 0.5rem; font-size: 0.8125rem; color: var(--color-text-muted);">
                                Generated automatically from your topic for Google News RSS
                            </span>
                            <?php if (isset($errors['url'])): ?>
                                <div class="error-message" style="margin-top: 0.5rem; color: var(--color-error); font-size: 0.875rem;">
                                    <?= $escaper->html(implode(', ', (array)$errors['url'])) ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Result Limit Field -->
                        <div class="form-group">
                            <label for="result_limit" class="label-required" style="font-weight: 600; margin-bottom: 0.5rem; display: block;">
                                Result Limit
                            </label>
                            <input type="number"
                                   id="result_limit"
                                   name="result_limit"
                                   class="input-field <?= isset($errors['result_limit']) ? 'error' : '' ?>"
                                   value="<?= $escaper->attribute($form_data['result_limit'] ?? '10') ?>"
                                   placeholder="10"
                                   required
                                   data-label="Result Limit"
                                   min="1"
                                   max="100"
                                   style="max-width: 200px; transition: all var(--duration-short) var(--ease-in-out);">
                            <span class="help-text" style="display: block; margin-top: 0.5rem; font-size: 0.8125rem; color: var(--color-text-muted);">
                                Maximum articles to fetch per processing (1-100)
                            </span>
                            <?php if (isset($errors['result_limit'])): ?>
                                <div class="error-message" style="margin-top: 0.5rem; color: var(--color-error); font-size: 0.875rem;">
                                    <?= $escaper->html(implode(', ', (array)$errors['result_limit'])) ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Enabled Checkbox - Enhanced -->
                        <div class="form-group">
                            <label class="feed-checkbox-wrapper">
                                <input type="checkbox"
                                       id="enabled"
                                       name="enabled"
                                       value="1"
                                       checked>
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
                        <div style="display: flex; gap: 0.75rem; margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid var(--color-border);">
                            <button type="submit" class="btn btn-primary btn-primary-enhanced" style="display: inline-flex; align-items: center; gap: 0.5rem;">
                                <svg width="18" height="18" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/>
                                </svg>
                                Create Feed
                            </button>
                            <a href="/feeds" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 0.5rem;">
                                Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Sidebar Info -->
        <div class="hidden lg:block">
            <!-- Info Card -->
            <div class="card" style="border-left: 3px solid var(--color-primary);">
                <div class="card-body">
                    <h3 style="font-size: 0.875rem; font-weight: 700; margin-bottom: var(--space-3); color: var(--color-primary); display: flex; align-items: center; gap: 0.5rem;">
                        <svg width="18" height="18" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                        </svg>
                        Quick Help
                    </h3>
                    <div style="font-size: 0.875rem; line-height: 1.6;">
                        <p style="margin-bottom: var(--space-3); font-weight: 600; color: var(--color-text);">
                            Popular Topics:
                        </p>
                        <ul style="list-style: none; padding: 0; margin-bottom: var(--space-3); display: flex; flex-direction: column; gap: 0.5rem;">
                            <li style="padding: 0.5rem 0.75rem; background: var(--color-surface); border-left: 2px solid var(--color-accent); border-radius: 4px; font-size: 0.8125rem;">Technology News</li>
                            <li style="padding: 0.5rem 0.75rem; background: var(--color-surface); border-left: 2px solid var(--color-accent); border-radius: 4px; font-size: 0.8125rem;">Artificial Intelligence</li>
                            <li style="padding: 0.5rem 0.75rem; background: var(--color-surface); border-left: 2px solid var(--color-accent); border-radius: 4px; font-size: 0.8125rem;">Web Development</li>
                            <li style="padding: 0.5rem 0.75rem; background: var(--color-surface); border-left: 2px solid var(--color-accent); border-radius: 4px; font-size: 0.8125rem;">Cloud Computing</li>
                        </ul>

                        <p style="font-size: 0.8125rem; color: var(--color-text-muted); line-height: 1.6;">
                            Each feed automatically processes Google News articles, extracts metadata, and generates clean RSS feeds for aggregation.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Security Info -->
            <div class="card mt-4" style="border-left: 3px solid var(--color-success);">
                <div class="card-body">
                    <h3 style="font-size: 0.875rem; font-weight: 700; margin-bottom: var(--space-2); color: var(--color-success); display: flex; align-items: center; gap: 0.5rem;">
                        <svg width="18" height="18" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        Secure & Private
                    </h3>
                    <p style="font-size: 0.8125rem; color: var(--color-text-muted); line-height: 1.6;">
                        All data encrypted in transit. Feeds stored securely and accessible only through authenticated requests.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Client-side Form Validation & Auto-generation -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    // Auto-generate URL from topic
    const topicField = document.getElementById('topic');
    const urlField = document.getElementById('url');

    if (topicField && urlField) {
        topicField.addEventListener('input', (e) => {
            const topic = e.target.value.trim();
            if (topic) {
                const searchQuery = encodeURIComponent(topic);
                urlField.value = `https://news.google.com/rss/search?q=${searchQuery}&hl=en-US&gl=US&ceid=US:en`;
            } else {
                urlField.value = '';
            }
        });

        // Trigger initial generation if topic exists
        if (topicField.value) {
            topicField.dispatchEvent(new Event('input'));
        }
    }
});
</script>

<?php include __DIR__ . '/../partials/footer.php'; ?>
