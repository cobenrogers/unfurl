<?php
/**
 * Article Edit View - Edit article data
 *
 * Allows editing of article metadata:
 * - Title (RSS and page title)
 * - Description
 * - Author
 * - Categories/tags
 * - Status
 * - Images
 *
 * Expected variables:
 * - $article array - Article data with all fields
 * - $errors array - Validation errors (if any)
 */

use Unfurl\Security\CsrfToken;
use Unfurl\Security\OutputEscaper;

$csrf = new CsrfToken();
$escaper = new OutputEscaper();

$page_title = 'Edit Article';
$article_title = $article['rss_title'] ?? $article['page_title'] ?? 'Untitled';

// Extract values with defaults
$rss_title = $article['rss_title'] ?? '';
$page_title_value = $article['page_title'] ?? '';
$rss_description = $article['rss_description'] ?? '';
$og_description = $article['og_description'] ?? '';
$author = $article['author'] ?? '';
$status = $article['status'] ?? 'pending';
$og_image = $article['og_image'] ?? '';
$twitter_image = $article['twitter_image'] ?? '';
$categories = [];

if (!empty($article['categories'])) {
    $decoded = json_decode($article['categories'], true);
    if (is_array($decoded)) {
        $categories = $decoded;
    } elseif (is_string($article['categories'])) {
        $categories = array_filter(array_map('trim', explode(',', $article['categories'])));
    }
}

$categories_string = implode(', ', $categories);
?>
<?php include __DIR__ . '/../partials/header.php'; ?>

<div class="container py-6">
    <!-- Breadcrumb Navigation -->
    <nav class="flex gap-2 mb-6 text-sm" aria-label="Breadcrumb">
        <a href="/articles" class="text-primary hover:underline">Articles</a>
        <span class="text-muted">/</span>
        <a href="/articles/<?= $escaper->attribute($article['id']) ?>" class="text-primary hover:underline">
            <?= $escaper->html(substr($article_title, 0, 50)) ?>
            <?php if (strlen($article_title) > 50): ?><span>...</span><?php endif; ?>
        </a>
        <span class="text-muted">/</span>
        <span class="text-muted">Edit</span>
    </nav>

    <!-- Header -->
    <div class="mb-6">
        <h1 class="text-3xl font-bold">Edit Article</h1>
        <p class="text-muted mt-2">Modify article metadata and content</p>
    </div>

    <!-- Error Messages -->
    <?php if (!empty($errors) && is_array($errors)): ?>
        <div class="alert alert-error mb-6" role="alert">
            <div class="alert-icon">
                <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"/>
                </svg>
            </div>
            <div class="alert-content">
                <div class="alert-title">Please fix the following errors:</div>
                <ul class="alert-message list-disc list-inside">
                    <?php foreach ($errors as $field => $error): ?>
                        <li><?= $escaper->html($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    <?php endif; ?>

    <!-- Edit Form -->
    <form method="POST" action="/articles/<?= $escaper->attribute($article['id']) ?>" class="max-w-4xl">
        <?= $csrf->field() ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Main Content -->
            <div class="lg:col-span-2">
                <!-- Titles Section -->
                <div class="card mb-6">
                    <div class="card-header">
                        <h2 class="font-semibold">Titles</h2>
                    </div>
                    <div class="card-body space-y-4">
                        <div class="form-group">
                            <label for="rss_title" class="label-required">RSS Title</label>
                            <input
                                type="text"
                                id="rss_title"
                                name="rss_title"
                                class="input-field <?= isset($errors['rss_title']) ? 'error' : '' ?>"
                                value="<?= $escaper->attribute($rss_title) ?>"
                                required>
                            <?php if (isset($errors['rss_title'])): ?>
                                <span class="error-message"><?= $escaper->html($errors['rss_title']) ?></span>
                            <?php endif; ?>
                            <span class="help-text">Title from the RSS feed</span>
                        </div>

                        <div class="form-group">
                            <label for="page_title">Page Title</label>
                            <input
                                type="text"
                                id="page_title"
                                name="page_title"
                                class="input-field <?= isset($errors['page_title']) ? 'error' : '' ?>"
                                value="<?= $escaper->attribute($page_title_value) ?>">
                            <?php if (isset($errors['page_title'])): ?>
                                <span class="error-message"><?= $escaper->html($errors['page_title']) ?></span>
                            <?php endif; ?>
                            <span class="help-text">Page title from OG meta tags</span>
                        </div>
                    </div>
                </div>

                <!-- Source Information Section -->
                <div class="card mb-6">
                    <div class="card-header">
                        <h2 class="font-semibold">Source Information</h2>
                    </div>
                    <div class="card-body space-y-4">
                        <div class="form-group">
                            <label class="font-semibold text-sm text-muted">Google News URL</label>
                            <?php if (!empty($article['google_news_url'])): ?>
                                <div class="url-display-container">
                                    <a href="<?= $escaper->attribute($article['google_news_url']) ?>"
                                       class="url-link"
                                       target="_blank"
                                       rel="noopener"
                                       title="<?= $escaper->attribute($article['google_news_url']) ?>">
                                        <?= $escaper->html($article['google_news_url']) ?>
                                    </a>
                                </div>
                            <?php else: ?>
                                <p class="mt-1 text-sm text-muted">(empty)</p>
                            <?php endif; ?>
                            <span class="help-text">Original Google News link</span>
                        </div>

                        <div class="form-group">
                            <label class="font-semibold text-sm text-muted">Final URL</label>
                            <?php if (!empty($article['final_url'])): ?>
                                <div class="url-display-container">
                                    <a href="<?= $escaper->attribute($article['final_url']) ?>"
                                       class="url-link"
                                       target="_blank"
                                       rel="noopener"
                                       title="<?= $escaper->attribute($article['final_url']) ?>">
                                        <?= $escaper->html($article['final_url']) ?>
                                    </a>
                                </div>
                            <?php else: ?>
                                <p class="mt-1 text-sm text-muted">(empty)</p>
                            <?php endif; ?>
                            <span class="help-text">Resolved article URL</span>
                        </div>

                        <div class="form-group">
                            <label class="font-semibold text-sm text-muted">RSS Source</label>
                            <p class="mt-1"><?= $escaper->html($article['rss_source'] ?? '(empty)') ?></p>
                            <span class="help-text">Source publication name</span>
                        </div>

                        <div class="form-group">
                            <label class="font-semibold text-sm text-muted">Publication Date</label>
                            <p class="mt-1">
                                <?= !empty($article['pub_date']) ? $escaper->html($timezone->formatLocal($article['pub_date'], 'M d, Y H:i')) : '(empty)' ?>
                            </p>
                            <span class="help-text">When the article was published</span>
                        </div>
                    </div>
                </div>

                <!-- Descriptions Section -->
                <div class="card mb-6">
                    <div class="card-header">
                        <h2 class="font-semibold">Descriptions</h2>
                    </div>
                    <div class="card-body space-y-4">
                        <div class="form-group">
                            <label for="rss_description">RSS Description</label>
                            <textarea
                                id="rss_description"
                                name="rss_description"
                                class="input-field <?= isset($errors['rss_description']) ? 'error' : '' ?>"
                                rows="4"><?= $escaper->html($rss_description) ?></textarea>
                            <?php if (isset($errors['rss_description'])): ?>
                                <span class="error-message"><?= $escaper->html($errors['rss_description']) ?></span>
                            <?php endif; ?>
                            <span class="help-text">Description from the RSS feed</span>
                        </div>

                        <div class="form-group">
                            <label for="og_description">OG Description</label>
                            <textarea
                                id="og_description"
                                name="og_description"
                                class="input-field <?= isset($errors['og_description']) ? 'error' : '' ?>"
                                rows="4"><?= $escaper->html($og_description) ?></textarea>
                            <?php if (isset($errors['og_description'])): ?>
                                <span class="error-message"><?= $escaper->html($errors['og_description']) ?></span>
                            <?php endif; ?>
                            <span class="help-text">Description from OG meta tags</span>
                        </div>
                    </div>
                </div>

                <!-- Author & Categories Section -->
                <div class="card mb-6">
                    <div class="card-header">
                        <h2 class="font-semibold">Author & Categories</h2>
                    </div>
                    <div class="card-body space-y-4">
                        <div class="form-group">
                            <label for="author">Author</label>
                            <input
                                type="text"
                                id="author"
                                name="author"
                                class="input-field <?= isset($errors['author']) ? 'error' : '' ?>"
                                value="<?= $escaper->attribute($author) ?>"
                                placeholder="Article author name">
                            <?php if (isset($errors['author'])): ?>
                                <span class="error-message"><?= $escaper->html($errors['author']) ?></span>
                            <?php endif; ?>
                            <span class="help-text">Author of the article</span>
                        </div>

                        <div class="form-group">
                            <label for="categories">Categories</label>
                            <input
                                type="text"
                                id="categories"
                                name="categories"
                                class="input-field <?= isset($errors['categories']) ? 'error' : '' ?>"
                                value="<?= $escaper->attribute($categories_string) ?>"
                                placeholder="Technology, News, Science">
                            <?php if (isset($errors['categories'])): ?>
                                <span class="error-message"><?= $escaper->html($errors['categories']) ?></span>
                            <?php endif; ?>
                            <span class="help-text">Comma-separated list of categories</span>
                        </div>
                    </div>
                </div>

                <!-- Images Section -->
                <div class="card mb-6">
                    <div class="card-header">
                        <h2 class="font-semibold">Images</h2>
                    </div>
                    <div class="card-body space-y-4">
                        <div class="form-group">
                            <label for="og_image">OG Image URL</label>
                            <input
                                type="url"
                                id="og_image"
                                name="og_image"
                                class="input-field <?= isset($errors['og_image']) ? 'error' : '' ?>"
                                value="<?= $escaper->attribute($og_image) ?>"
                                placeholder="https://example.com/image.jpg">
                            <?php if (isset($errors['og_image'])): ?>
                                <span class="error-message"><?= $escaper->html($errors['og_image']) ?></span>
                            <?php endif; ?>
                            <span class="help-text">Featured image URL from OG meta tags</span>
                        </div>

                        <div class="form-group">
                            <label for="twitter_image">Twitter Image URL</label>
                            <input
                                type="url"
                                id="twitter_image"
                                name="twitter_image"
                                class="input-field <?= isset($errors['twitter_image']) ? 'error' : '' ?>"
                                value="<?= $escaper->attribute($twitter_image) ?>"
                                placeholder="https://example.com/image.jpg">
                            <?php if (isset($errors['twitter_image'])): ?>
                                <span class="error-message"><?= $escaper->html($errors['twitter_image']) ?></span>
                            <?php endif; ?>
                            <span class="help-text">Image URL from Twitter meta tags</span>
                        </div>

                        <?php if (!empty($og_image)): ?>
                            <div class="p-4 bg-border-light rounded-md">
                                <p class="text-sm font-semibold mb-2">OG Image Preview</p>
                                <img src="<?= $escaper->attribute($og_image) ?>"
                                     alt="OG Image"
                                     class="max-w-full h-auto max-h-48"
                                     loading="lazy">
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Extended Metadata Section -->
                <div class="card mb-6">
                    <div class="card-header">
                        <h2 class="font-semibold">Extended Metadata</h2>
                    </div>
                    <div class="card-body space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="form-group">
                                <label class="font-semibold text-sm text-muted">OG Title</label>
                                <p class="mt-1"><?= $escaper->html($article['og_title'] ?? '(empty)') ?></p>
                                <span class="help-text">OpenGraph title tag</span>
                            </div>

                            <div class="form-group">
                                <label class="font-semibold text-sm text-muted">OG Site Name</label>
                                <p class="mt-1"><?= $escaper->html($article['og_site_name'] ?? '(empty)') ?></p>
                                <span class="help-text">OpenGraph site name</span>
                            </div>

                            <div class="form-group">
                                <label class="font-semibold text-sm text-muted">Twitter Card Type</label>
                                <p class="mt-1"><?= $escaper->html($article['twitter_card'] ?? '(empty)') ?></p>
                                <span class="help-text">Twitter card format</span>
                            </div>

                            <div class="form-group">
                                <label class="font-semibold text-sm text-muted">Word Count</label>
                                <p class="mt-1">
                                    <?= !empty($article['word_count']) ? $escaper->html(number_format($article['word_count'])) : '(empty)' ?>
                                </p>
                                <span class="help-text">Article word count</span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="font-semibold text-sm text-muted">OG URL</label>
                            <?php if (!empty($article['og_url'])): ?>
                                <div class="url-display-container">
                                    <a href="<?= $escaper->attribute($article['og_url']) ?>"
                                       class="url-link"
                                       target="_blank"
                                       rel="noopener"
                                       title="<?= $escaper->attribute($article['og_url']) ?>">
                                        <?= $escaper->html($article['og_url']) ?>
                                    </a>
                                </div>
                            <?php else: ?>
                                <p class="mt-1 text-sm text-muted">(empty)</p>
                            <?php endif; ?>
                            <span class="help-text">OpenGraph canonical URL</span>
                        </div>
                    </div>
                </div>

                <!-- Article Content Section -->
                <?php if (!empty($article['article_content'])): ?>
                    <div class="card mb-6">
                        <div class="card-header">
                            <h2 class="font-semibold">Article Content</h2>
                        </div>
                        <div class="card-body">
                            <div class="prose prose-sm max-w-none text-sm leading-relaxed whitespace-pre-wrap p-4 bg-gray-50 rounded">
                                <?= $escaper->html($article['article_content']) ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Sidebar -->
            <aside class="lg:col-span-1">
                <!-- Status Card -->
                <div class="card sticky top-4">
                    <div class="card-header">
                        <h3 class="font-semibold">Status</h3>
                    </div>
                    <div class="card-body space-y-4">
                        <div class="form-group">
                            <label for="status">Processing Status</label>
                            <select id="status" name="status" class="input-field">
                                <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="success" <?= $status === 'success' ? 'selected' : '' ?>>Success</option>
                                <option value="failed" <?= $status === 'failed' ? 'selected' : '' ?>>Failed</option>
                            </select>
                            <span class="help-text">Current processing status</span>
                        </div>

                        <!-- Information -->
                        <div class="p-3 bg-border-light rounded-md text-sm space-y-2">
                            <div>
                                <label class="font-semibold text-muted block">Article ID</label>
                                <code class="text-xs"><?= $escaper->html($article['id']) ?></code>
                            </div>
                            <div>
                                <label class="font-semibold text-muted block">Topic</label>
                                <code class="text-xs"><?= $escaper->html($article['topic']) ?></code>
                            </div>
                            <div>
                                <label class="font-semibold text-muted block">Created</label>
                                <code class="text-xs">
                                    <?= !empty($article['created_at']) ? $escaper->html($timezone->formatLocal($article['created_at'], 'M d, Y H:i')) : '(empty)' ?>
                                </code>
                            </div>
                            <?php if (!empty($article['processed_at'])): ?>
                                <div>
                                    <label class="font-semibold text-muted block">Processed</label>
                                    <code class="text-xs">
                                        <?= $escaper->html($timezone->formatLocal($article['processed_at'], 'M d, Y H:i')) ?>
                                    </code>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Error Message -->
                        <?php if ($article['status'] === 'failed' && !empty($article['error_message'])): ?>
                            <div class="p-3 bg-red-50 border border-red-200 rounded-md text-sm">
                                <label class="font-semibold text-error block mb-1">Error Message</label>
                                <p class="text-xs text-error"><?= $escaper->html($article['error_message']) ?></p>
                                <?php if (!empty($article['retry_count'])): ?>
                                    <p class="text-xs text-muted mt-2">Retry count: <?= $escaper->html($article['retry_count']) ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Actions -->
                        <div class="flex flex-col gap-2 pt-4 border-t">
                            <button type="submit" class="btn btn-primary w-full">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                                    <polyline points="17 21 17 13 7 13 7 21"></polyline>
                                    <polyline points="7 3 7 8 15 8"></polyline>
                                </svg>
                                Save Changes
                            </button>

                            <a href="/articles/<?= $escaper->attribute($article['id']) ?>" class="btn btn-secondary w-full text-center">
                                Cancel
                            </a>

                            <button type="button" class="btn btn-danger w-full" onclick="confirmDelete()">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="3 6 5 6 21 6"></polyline>
                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                    <line x1="10" y1="11" x2="10" y2="17"></line>
                                    <line x1="14" y1="11" x2="14" y2="17"></line>
                                </svg>
                                Delete Article
                            </button>
                        </div>
                    </div>
                </div>
            </aside>
        </div>
    </form>
</div>

<!-- URL Display Styles -->
<style>
    /* Mobile-friendly URL display with elegant truncation */
    .url-display-container {
        margin-top: 0.25rem;
        position: relative;
        width: 100%;
        overflow: hidden;
    }

    .url-link {
        display: block;
        font-size: 0.813rem;
        line-height: 1.5;
        color: var(--color-primary);
        text-decoration: none;
        word-wrap: break-word;
        overflow-wrap: break-word;
        hyphens: auto;

        /* Elegant gradient fade for long URLs */
        max-height: 3.375rem; /* ~2.25 lines */
        overflow: hidden;
        position: relative;
        transition: all 0.3s ease;
    }

    .url-link::after {
        content: '';
        position: absolute;
        bottom: 0;
        right: 0;
        width: 40%;
        height: 1.5rem;
        background: linear-gradient(to right, transparent, white);
        pointer-events: none;
        opacity: 1;
        transition: opacity 0.3s ease;
    }

    .url-link:hover {
        text-decoration: underline;
        color: var(--color-primary-dark);
        max-height: none; /* Expand on hover */
    }

    .url-link:hover::after {
        opacity: 0; /* Remove fade on hover */
    }

    /* Mobile optimization */
    @media (max-width: 768px) {
        .url-link {
            font-size: 0.75rem;
            max-height: 3rem; /* Slightly shorter on mobile */
        }

        /* Ensure container doesn't overflow */
        .container {
            overflow-x: hidden;
        }

        /* Add padding to cards on mobile */
        .card-body {
            padding: 1rem;
        }
    }

    /* Dark mode support (if applicable) */
    @media (prefers-color-scheme: dark) {
        .url-link::after {
            background: linear-gradient(to right, transparent, #1a1a1a);
        }
    }
</style>

<!-- Delete Confirmation -->
<script>
    function confirmDelete() {
        showConfirmModal({
            title: 'Delete Article',
            message: 'Are you sure you want to delete this article? This action cannot be undone.',
            confirmText: 'Delete Article',
            dangerButton: true,
            onConfirm: () => {
                // Create and submit delete form
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '/articles/<?= $escaper->attribute($article['id']) ?>/delete';
                form.innerHTML = `<?= $csrf->field() ?>`;
                document.body.appendChild(form);
                form.submit();
            }
        });
    }
</script>

</main>
</body>
</html>
