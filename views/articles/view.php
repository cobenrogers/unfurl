<?php
/**
 * Article View - Single article detail page
 * Layout matches edit page for consistency
 */

use Unfurl\Security\OutputEscaper;

$escaper = new OutputEscaper();
$page_title = $article['rss_title'] ?? $article['page_title'] ?? 'Article';
?>
<?php include __DIR__ . '/../partials/header.php'; ?>

<div class="container py-6">
    <!-- Breadcrumb -->
    <nav class="flex gap-2 mb-6 text-sm">
        <a href="/articles" class="text-primary hover:underline">Articles</a>
        <span class="text-muted">/</span>
        <span class="text-muted">Article #<?= $article['id'] ?></span>
    </nav>

    <!-- Page Header -->
    <div class="mb-6">
        <div class="flex justify-between items-start">
            <h1 class="text-2xl font-bold">Article Details</h1>
            <div class="flex gap-2">
                <a href="/articles/<?= $article['id'] ?>/edit" class="btn btn-secondary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                    </svg>
                    Edit
                </a>
                <?php if (!empty($article['final_url'])): ?>
                    <a href="<?= $escaper->attribute($article['final_url']) ?>" class="btn btn-primary" target="_blank" rel="noopener">
                        View Original
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="card">
        <div class="card-body">
            <!-- RSS Title -->
            <div class="form-group">
                <label class="font-semibold text-sm text-muted">RSS Title</label>
                <p class="mt-1"><?= $escaper->html($article['rss_title'] ?? '(empty)') ?></p>
            </div>

            <!-- Page Title -->
            <div class="form-group">
                <label class="font-semibold text-sm text-muted">Page Title</label>
                <p class="mt-1"><?= $escaper->html($article['page_title'] ?? '(empty)') ?></p>
            </div>

            <!-- RSS Description -->
            <div class="form-group">
                <label class="font-semibold text-sm text-muted">RSS Description</label>
                <p class="mt-1 text-sm"><?= $escaper->html(strip_tags($article['rss_description'] ?? '(empty)')) ?></p>
            </div>

            <!-- OG Description -->
            <div class="form-group">
                <label class="font-semibold text-sm text-muted">OG Description</label>
                <p class="mt-1 text-sm"><?= $escaper->html($article['og_description'] ?? '(empty)') ?></p>
            </div>

            <!-- Author -->
            <div class="form-group">
                <label class="font-semibold text-sm text-muted">Author</label>
                <p class="mt-1"><?= $escaper->html($article['author'] ?? '(empty)') ?></p>
            </div>

            <!-- Categories -->
            <div class="form-group">
                <label class="font-semibold text-sm text-muted">Categories</label>
                <p class="mt-1"><?= $escaper->html($article['categories'] ?? '(empty)') ?></p>
            </div>

            <!-- OG Image URL -->
            <div class="form-group">
                <label class="font-semibold text-sm text-muted">OG Image URL</label>
                <?php if (!empty($article['og_image'])): ?>
                    <p class="mt-1"><a href="<?= $escaper->attribute($article['og_image']) ?>" class="text-primary hover:underline text-sm break-all" target="_blank"><?= $escaper->html($article['og_image']) ?></a></p>
                <?php else: ?>
                    <p class="mt-1 text-sm">(empty)</p>
                <?php endif; ?>
            </div>

            <!-- Twitter Image URL -->
            <div class="form-group">
                <label class="font-semibold text-sm text-muted">Twitter Image URL</label>
                <?php if (!empty($article['twitter_image'])): ?>
                    <p class="mt-1"><a href="<?= $escaper->attribute($article['twitter_image']) ?>" class="text-primary hover:underline text-sm break-all" target="_blank"><?= $escaper->html($article['twitter_image']) ?></a></p>
                <?php else: ?>
                    <p class="mt-1 text-sm">(empty)</p>
                <?php endif; ?>
            </div>

            <!-- Processing Status -->
            <div class="form-group">
                <label class="font-semibold text-sm text-muted">Processing Status</label>
                <div class="mt-1">
                    <?php
                    $status_class = match($article['status']) {
                        'success' => 'success',
                        'failed' => 'error',
                        'pending' => 'warning',
                        default => 'info'
                    };
                    ?>
                    <span class="badge <?= $status_class ?>"><?= $escaper->html($article['status']) ?></span>
                </div>
            </div>

            <!-- Additional Fields -->
            <div class="border-t pt-6 mt-6">
                <h3 class="font-semibold mb-4">Additional Information</h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Topic -->
                    <div>
                        <label class="font-semibold text-sm text-muted">Topic</label>
                        <p class="mt-1"><?= $escaper->html($article['topic'] ?? '(empty)') ?></p>
                    </div>

                    <!-- RSS Source -->
                    <div>
                        <label class="font-semibold text-sm text-muted">RSS Source</label>
                        <p class="mt-1"><?= $escaper->html($article['rss_source'] ?? '(empty)') ?></p>
                    </div>

                    <!-- OG Title -->
                    <div>
                        <label class="font-semibold text-sm text-muted">OG Title</label>
                        <p class="mt-1"><?= $escaper->html($article['og_title'] ?? '(empty)') ?></p>
                    </div>

                    <!-- OG Site Name -->
                    <div>
                        <label class="font-semibold text-sm text-muted">OG Site Name</label>
                        <p class="mt-1"><?= $escaper->html($article['og_site_name'] ?? '(empty)') ?></p>
                    </div>

                    <!-- OG URL -->
                    <div>
                        <label class="font-semibold text-sm text-muted">OG URL</label>
                        <?php if (!empty($article['og_url'])): ?>
                            <p class="mt-1"><a href="<?= $escaper->attribute($article['og_url']) ?>" class="text-primary hover:underline text-sm break-all" target="_blank"><?= $escaper->html($article['og_url']) ?></a></p>
                        <?php else: ?>
                            <p class="mt-1 text-sm">(empty)</p>
                        <?php endif; ?>
                    </div>

                    <!-- Twitter Card -->
                    <div>
                        <label class="font-semibold text-sm text-muted">Twitter Card</label>
                        <p class="mt-1"><?= $escaper->html($article['twitter_card'] ?? '(empty)') ?></p>
                    </div>

                    <!-- Word Count -->
                    <div>
                        <label class="font-semibold text-sm text-muted">Word Count</label>
                        <p class="mt-1"><?= $article['word_count'] ? number_format($article['word_count']) : '(empty)' ?></p>
                    </div>

                    <!-- Pub Date -->
                    <div>
                        <label class="font-semibold text-sm text-muted">Publication Date</label>
                        <p class="mt-1"><?= $article['pub_date'] ? date('M d, Y H:i', strtotime($article['pub_date'])) : '(empty)' ?></p>
                    </div>

                    <!-- Created At -->
                    <div>
                        <label class="font-semibold text-sm text-muted">Created</label>
                        <p class="mt-1"><?= $article['created_at'] ? date('M d, Y H:i', strtotime($article['created_at'])) : '(empty)' ?></p>
                    </div>

                    <!-- Processed At -->
                    <div>
                        <label class="font-semibold text-sm text-muted">Processed</label>
                        <p class="mt-1"><?= $article['processed_at'] ? date('M d, Y H:i', strtotime($article['processed_at'])) : '(empty)' ?></p>
                    </div>
                </div>
            </div>

            <!-- URLs Section -->
            <div class="border-t pt-6 mt-6">
                <h3 class="font-semibold mb-4">URLs</h3>

                <div class="space-y-4">
                    <!-- Google News URL -->
                    <div>
                        <label class="font-semibold text-sm text-muted">Google News URL</label>
                        <?php if (!empty($article['google_news_url'])): ?>
                            <p class="mt-1"><a href="<?= $escaper->attribute($article['google_news_url']) ?>" class="text-primary hover:underline text-sm break-all" target="_blank"><?= $escaper->html($article['google_news_url']) ?></a></p>
                        <?php else: ?>
                            <p class="mt-1 text-sm">(empty)</p>
                        <?php endif; ?>
                    </div>

                    <!-- Final URL -->
                    <div>
                        <label class="font-semibold text-sm text-muted">Final URL</label>
                        <?php if (!empty($article['final_url'])): ?>
                            <p class="mt-1"><a href="<?= $escaper->attribute($article['final_url']) ?>" class="text-primary hover:underline text-sm break-all" target="_blank"><?= $escaper->html($article['final_url']) ?></a></p>
                        <?php else: ?>
                            <p class="mt-1 text-sm">(empty)</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Article Content -->
            <?php if (!empty($article['article_content'])): ?>
                <div class="border-t pt-6 mt-6">
                    <h3 class="font-semibold mb-4">Article Content</h3>
                    <div class="prose prose-sm max-w-none text-sm leading-relaxed whitespace-pre-wrap p-4 bg-gray-50 rounded">
                        <?= $escaper->html($article['article_content']) ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Error Information -->
            <?php if ($article['status'] === 'failed' && !empty($article['error_message'])): ?>
                <div class="border-t pt-6 mt-6">
                    <h3 class="font-semibold mb-4 text-error">Error Information</h3>
                    <div class="p-4 bg-red-50 border border-red-200 rounded">
                        <p class="text-sm text-error"><?= $escaper->html($article['error_message']) ?></p>
                        <?php if (!empty($article['retry_count'])): ?>
                            <p class="text-sm text-muted mt-2">Retry count: <?= $escaper->html($article['retry_count']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../partials/footer.php'; ?>
