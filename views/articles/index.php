<?php
/**
 * Articles Index View - List articles with filters and bulk actions
 *
 * Displays articles in a paginated table with:
 * - Status and topic filters
 * - Date range filtering
 * - Full-text search
 * - Bulk actions (select all, delete selected)
 * - Pagination controls
 *
 * Expected variables:
 * - $articles array - Array of article data
 * - $total_count int - Total articles matching filters
 * - $page_count int - Total pages
 * - $current_page int - Current page number
 * - $per_page int - Items per page
 * - $filters array - Current filter values
 * - $search_query string - Current search query
 * - $topics array - Available topics
 * - $statuses array - Available statuses
 */

use Unfurl\Security\CsrfToken;
use Unfurl\Security\OutputEscaper;

$csrf = new CsrfToken();
$escaper = new OutputEscaper();

$page_title = 'Articles';

// Extract filter values with defaults
$search = isset($filters['search']) ? $filters['search'] : '';
$topic = isset($filters['topic']) ? $filters['topic'] : '';
$status = isset($filters['status']) ? $filters['status'] : '';
$date_from = isset($filters['date_from']) ? $filters['date_from'] : '';
$date_to = isset($filters['date_to']) ? $filters['date_to'] : '';
$sort_by = isset($filters['sort_by']) ? $filters['sort_by'] : 'created_at';
$sort_order = isset($filters['sort_order']) ? $filters['sort_order'] : 'DESC';

// Pagination
$current_page = (int)($current_page ?? 1);
$total_pages = (int)($page_count ?? 1);
$per_page = (int)($per_page ?? 20);
$total_items = (int)($total_count ?? 0);
?>
<?php include __DIR__ . '/../partials/header.php'; ?>

<div class="container py-6">
    <!-- Page Header -->
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold mb-2">Articles</h1>
            <p class="text-muted">Manage and filter articles from your feeds</p>
        </div>
        <a href="/articles/process" class="btn btn-primary">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="12 5 19 12 12 19"></polyline>
                <polyline points="5 12 12 5 12 19"></polyline>
            </svg>
            Process Articles
        </a>
    </div>

    <!-- Filter Panel -->
    <div class="card mb-6">
        <div class="card-body">
            <form method="GET" action="/articles" class="flex flex-col gap-4" id="filter-form">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Search Input -->
                    <div class="form-group">
                        <label for="search" class="label-required">Search</label>
                        <input
                            type="text"
                            id="search"
                            name="search"
                            class="input-field"
                            placeholder="Title, author, or content..."
                            value="<?= $escaper->attribute($search) ?>">
                        <span class="help-text">Full-text search across title, author, and content</span>
                    </div>

                    <!-- Topic Filter -->
                    <div class="form-group">
                        <label for="topic">Topic</label>
                        <select id="topic" name="topic" class="input-field">
                            <option value="">All Topics</option>
                            <?php foreach ($topics as $topic_name): ?>
                                <option value="<?= $escaper->attribute($topic_name) ?>"
                                    <?= $topic === $topic_name ? 'selected' : '' ?>>
                                    <?= $escaper->html($topic_name) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Status Filter -->
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status" class="input-field">
                            <option value="">All Statuses</option>
                            <?php foreach ($statuses as $status_name => $label): ?>
                                <option value="<?= $escaper->attribute($status_name) ?>"
                                    <?= $status === $status_name ? 'selected' : '' ?>>
                                    <?= $escaper->html($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Date From -->
                    <div class="form-group">
                        <label for="date_from">Date From</label>
                        <input
                            type="date"
                            id="date_from"
                            name="date_from"
                            class="input-field"
                            value="<?= $escaper->attribute($date_from) ?>">
                    </div>
                </div>

                <!-- Second row of filters -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Date To -->
                    <div class="form-group">
                        <label for="date_to">Date To</label>
                        <input
                            type="date"
                            id="date_to"
                            name="date_to"
                            class="input-field"
                            value="<?= $escaper->attribute($date_to) ?>">
                    </div>

                    <!-- Sort By -->
                    <div class="form-group">
                        <label for="sort_by">Sort By</label>
                        <select id="sort_by" name="sort_by" class="input-field">
                            <option value="created_at" <?= $sort_by === 'created_at' ? 'selected' : '' ?>>Created Date</option>
                            <option value="pub_date" <?= $sort_by === 'pub_date' ? 'selected' : '' ?>>Published Date</option>
                            <option value="title" <?= $sort_by === 'title' ? 'selected' : '' ?>>Title</option>
                            <option value="status" <?= $sort_by === 'status' ? 'selected' : '' ?>>Status</option>
                        </select>
                    </div>

                    <!-- Sort Order -->
                    <div class="form-group">
                        <label for="sort_order">Order</label>
                        <select id="sort_order" name="sort_order" class="input-field">
                            <option value="DESC" <?= $sort_order === 'DESC' ? 'selected' : '' ?>>Descending</option>
                            <option value="ASC" <?= $sort_order === 'ASC' ? 'selected' : '' ?>>Ascending</option>
                        </select>
                    </div>

                    <!-- Actions -->
                    <div class="form-group flex flex-col justify-end gap-2">
                        <button type="submit" class="btn btn-primary">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="11" cy="11" r="8"></circle>
                                <path d="m21 21-4.35-4.35"></path>
                            </svg>
                            Search
                        </button>
                        <a href="/articles" class="btn btn-secondary text-center">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="1 4 1 10 7 10"></polyline>
                                <polyline points="23 20 23 14 17 14"></polyline>
                                <path d="M20.49 9A9 9 0 0 0 5.64 5.64M3.51 15A9 9 0 0 0 18.36 18.36"></path>
                            </svg>
                            Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Results Summary -->
    <div class="flex justify-between items-center mb-4">
        <div>
            <p class="text-sm text-muted">
                <?php
                    $start = ((int)$current_page - 1) * (int)$per_page + 1;
                    $end = min((int)$current_page * (int)$per_page, (int)$total_items);
                    if ((int)$total_items === 0) {
                        echo 'No articles found';
                    } else {
                        echo "Showing <strong>$start</strong> to <strong>$end</strong> of <strong>$total_items</strong> articles";
                    }
                ?>
            </p>
        </div>
        <div class="badge neutral">
            <span data-selection-count>0</span> selected
        </div>
    </div>

    <!-- Bulk Actions Bar (hidden by default) -->
    <div class="bulk-actions mb-4 hidden">
        <div class="card card-body flex justify-between items-center bg-primary-light">
            <div class="flex items-center gap-2">
                <span class="text-sm">Bulk Actions:</span>
            </div>
            <div class="flex gap-2">
                <button type="button" data-bulk-action="delete" class="btn btn-danger btn-sm" disabled>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="3 6 5 6 21 6"></polyline>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                        <line x1="10" y1="11" x2="10" y2="17"></line>
                        <line x1="14" y1="11" x2="14" y2="17"></line>
                    </svg>
                    Delete Selected
                </button>
            </div>
        </div>
    </div>

    <!-- Articles Table -->
    <?php if (count($articles) > 0): ?>
        <div class="card overflow-x-auto">
            <table class="w-full" data-bulk>
                <thead>
                    <tr class="border-b">
                        <th class="p-4 text-left">
                            <div class="checkbox">
                                <input type="checkbox" data-select-all id="select-all" aria-label="Select all articles">
                                <label for="select-all" class="sr-only">Select all articles</label>
                            </div>
                        </th>
                        <th class="p-4 text-left font-semibold text-sm">Title</th>
                        <th class="p-4 text-left font-semibold text-sm">Topic</th>
                        <th class="p-4 text-left font-semibold text-sm">Status</th>
                        <th class="p-4 text-left font-semibold text-sm">Date</th>
                        <th class="p-4 text-left font-semibold text-sm">Words</th>
                        <th class="p-4 text-center font-semibold text-sm">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($articles as $article): ?>
                        <tr class="border-b hover:bg-border-light transition-colors">
                            <td class="p-4">
                                <div class="checkbox">
                                    <input type="checkbox" data-item="<?= $escaper->attribute($article['id']) ?>"
                                        id="article-<?= $escaper->attribute($article['id']) ?>"
                                        aria-label="Select article: <?= $escaper->attribute($article['rss_title'] ?? $article['page_title']) ?>">
                                    <label for="article-<?= $escaper->attribute($article['id']) ?>" class="sr-only">
                                        Select article: <?= $escaper->attribute($article['rss_title'] ?? $article['page_title']) ?>
                                    </label>
                                </div>
                            </td>
                            <td class="p-4">
                                <div>
                                    <a href="/articles/<?= $escaper->attribute($article['id']) ?>"
                                       class="font-semibold text-primary hover:underline break-words">
                                        <?= $escaper->html(substr($article['rss_title'] ?? $article['page_title'] ?? 'Untitled', 0, 80)) ?>
                                    </a>
                                    <?php if (strlen($article['rss_title'] ?? $article['page_title'] ?? '') > 80): ?>
                                        <span class="text-muted text-sm">...</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="p-4 text-sm">
                                <span class="badge neutral no-dot">
                                    <?= $escaper->html($article['topic']) ?>
                                </span>
                            </td>
                            <td class="p-4 text-sm">
                                <?php
                                $status_class = match($article['status']) {
                                    'success' => 'success',
                                    'failed' => 'error',
                                    'pending' => 'warning',
                                    default => 'info'
                                };
                                $status_label = match($article['status']) {
                                    'success' => 'Processed',
                                    'failed' => 'Failed',
                                    'pending' => 'Pending',
                                    default => 'Unknown'
                                };
                                ?>
                                <span class="badge <?= $status_class ?>">
                                    <?= $escaper->html($status_label) ?>
                                </span>
                            </td>
                            <td class="p-4 text-sm text-muted">
                                <?= !empty($article['created_at']) ? $escaper->html($timezone->formatLocal($article['created_at'], 'M d, Y')) : '(empty)' ?>
                            </td>
                            <td class="p-4 text-sm text-muted text-right">
                                <?= $escaper->html($article['word_count'] ?? 0) ?>
                            </td>
                            <td class="p-4 text-center">
                                <div class="flex justify-center gap-2">
                                    <a href="/articles/<?= $escaper->attribute($article['id']) ?>"
                                       class="btn btn-ghost btn-sm"
                                       title="View article">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                            <circle cx="12" cy="12" r="3"></circle>
                                        </svg>
                                    </a>
                                    <a href="/articles/<?= $escaper->attribute($article['id']) ?>/edit"
                                       class="btn btn-ghost btn-sm"
                                       title="Edit article">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                        </svg>
                                    </a>
                                    <button type="button"
                                            class="btn btn-ghost btn-sm text-error hover:bg-red-50"
                                            title="Delete article"
                                            onclick="confirmDeleteArticle(<?= $escaper->attribute($article['id']) ?>)">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <polyline points="3 6 5 6 21 6"></polyline>
                                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                            <line x1="10" y1="11" x2="10" y2="17"></line>
                                            <line x1="14" y1="11" x2="14" y2="17"></line>
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="card">
            <div class="card-body text-center py-12">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" class="mx-auto mb-4 text-muted opacity-50">
                    <path d="M10 21H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v7"></path>
                    <polyline points="17 21 21 17 25 21"></polyline>
                    <polyline points="21 13 21 21"></polyline>
                </svg>
                <h3 class="text-lg font-semibold mb-2">No articles found</h3>
                <p class="text-muted mb-4">
                    <?php if (!empty($search) || !empty($topic) || !empty($status)): ?>
                        No articles match your search criteria. Try adjusting your filters.
                    <?php else: ?>
                        No articles have been processed yet. Process your feeds to get started.
                    <?php endif; ?>
                </p>
                <a href="/feeds" class="btn btn-primary inline-block">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="1" width="7" height="7"></rect>
                        <rect x="14" y="1" width="7" height="7"></rect>
                        <rect x="14" y="14" width="7" height="7"></rect>
                        <rect x="3" y="14" width="7" height="7"></rect>
                    </svg>
                    Go to Feeds
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="flex justify-center items-center gap-2 mt-6">
            <?php if ($current_page > 1): ?>
                <a href="?page=1&<?= http_build_query($filters) ?>" class="btn btn-ghost btn-sm">First</a>
                <a href="?page=<?= (int)$current_page - 1 ?>&<?= http_build_query($filters) ?>" class="btn btn-ghost btn-sm">Previous</a>
            <?php else: ?>
                <button class="btn btn-ghost btn-sm" disabled>First</button>
                <button class="btn btn-ghost btn-sm" disabled>Previous</button>
            <?php endif; ?>

            <div class="flex gap-1">
                <?php
                $start = max(1, (int)$current_page - 2);
                $end = min((int)$total_pages, (int)$current_page + 2);

                if ($start > 1):
                    echo '<span class="px-2">...</span>';
                endif;

                for ($i = $start; $i <= $end; $i++):
                    if ($i === $current_page):
                        echo '<button class="btn btn-primary btn-sm" disabled>' . $i . '</button>';
                    else:
                        echo '<a href="?page=' . $i . '&' . http_build_query($filters) . '" class="btn btn-ghost btn-sm">' . $i . '</a>';
                    endif;
                endfor;

                if ($end < $total_pages):
                    echo '<span class="px-2">...</span>';
                endif;
                ?>
            </div>

            <?php if ($current_page < $total_pages): ?>
                <a href="?page=<?= (int)$current_page + 1 ?>&<?= http_build_query($filters) ?>" class="btn btn-ghost btn-sm">Next</a>
                <a href="?page=<?= $total_pages ?>&<?= http_build_query($filters) ?>" class="btn btn-ghost btn-sm">Last</a>
            <?php else: ?>
                <button class="btn btn-ghost btn-sm" disabled>Next</button>
                <button class="btn btn-ghost btn-sm" disabled>Last</button>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Individual Delete Function -->
<script>
    function confirmDeleteArticle(articleId) {
        showConfirmModal({
            title: 'Delete Article',
            message: 'Are you sure you want to delete this article? This action cannot be undone.',
            confirmText: 'Delete Article',
            dangerButton: true,
            onConfirm: () => {
                // Create and submit delete form
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = `/articles/${articleId}/delete`;
                form.innerHTML = `<?= $csrf->field() ?>`;
                document.body.appendChild(form);
                form.submit();
            }
        });
    }
</script>

<!-- Bulk Actions JavaScript -->
<script type="module">
    import { BulkActions } from '/assets/js/bulk-actions.js';

    const bulkActions = new BulkActions({
        tableSelector: 'table[data-bulk]',
        selectAllSelector: 'input[data-select-all]',
        itemCheckboxSelector: 'input[data-item]',
        bulkActionButtons: '[data-bulk-action]',
        bulkActionContainers: '.bulk-actions',
        confirmAction: true,
        onBulkAction: (data) => {
            if (data.action === 'delete') {
                // Create form and submit
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '/articles/bulk-delete';
                form.innerHTML = `
                    <?= $csrf->field() ?>
                    <input type="hidden" name="ids" value="">
                `;
                form.querySelector('input[name="ids"]').value = JSON.stringify(data.ids);
                document.body.appendChild(form);
                form.submit();
            }
        }
    });
</script>

<?php include __DIR__ . '/../partials/footer.php'; ?>
