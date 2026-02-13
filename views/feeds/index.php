<?php
/**
 * Feeds List View
 *
 * Displays all feeds with management options (edit, delete, enable/disable).
 * Responsive table/card layout with bulk actions and pagination.
 *
 * Variables provided by controller:
 * - $feeds (array) - List of feed objects
 * - $total_count (int) - Total feeds count
 * - $page (int) - Current page number
 * - $per_page (int) - Items per page
 */

use Unfurl\Security\OutputEscaper;
use Unfurl\Security\CsrfToken;

$page_title = 'Feeds';
$escaper = new OutputEscaper();
$csrf = new CsrfToken();

include __DIR__ . '/../partials/header.php';
?>

<div class="container py-6">
    <?php
    // Display flash messages
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (isset($_SESSION['flash'])):
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
    ?>
    <div class="alert alert-<?= $escaper->attribute($flash['type']) ?> mb-6" role="alert">
        <div style="display: flex; align-items: center; gap: 0.75rem;">
            <?php if ($flash['type'] === 'success'): ?>
            <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor" style="color: var(--color-success);">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
            </svg>
            <?php endif; ?>
            <span><?= $escaper->html($flash['message']) ?></span>
        </div>
    </div>
    <?php endif; ?>

    <!-- Page Header -->
    <div class="flex justify-between items-start md:items-center gap-4 mb-6 flex-col md:flex-row">
        <div>
            <h1 style="font-size: 2rem; font-weight: 700; margin-bottom: var(--space-2);">Feeds</h1>
            <p style="color: var(--color-text-muted);">Manage your Google News feeds</p>
        </div>
        <a href="/feeds/create" class="btn btn-primary" title="Create a new feed">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z"/>
            </svg>
            New Feed
        </a>
    </div>

    <?php if (empty($feeds)): ?>
        <!-- Empty State -->
        <div class="card" style="padding: 3rem; text-align: center;">
            <div style="margin-bottom: var(--space-4);">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"
                     style="color: var(--color-text-muted); margin: 0 auto;">
                    <circle cx="12" cy="12" r="9"></circle>
                    <polyline points="12 7 12 12 16 14"></polyline>
                </svg>
            </div>
            <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: var(--space-2);">No feeds yet</h2>
            <p style="color: var(--color-text-muted); margin-bottom: var(--space-4);">
                Create your first feed to start aggregating Google News articles.
            </p>
            <a href="/feeds/create" class="btn btn-primary">Create Feed</a>
        </div>
    <?php else: ?>
        <!-- Desktop Table View -->
        <div class="overflow-x-auto hidden md:block">
            <table class="feeds-table" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 2px solid var(--color-border);">
                        <th style="text-align: left; padding: var(--space-3); font-weight: 600; color: var(--color-text-muted); font-size: 0.875rem;">Topic</th>
                        <th style="text-align: left; padding: var(--space-3); font-weight: 600; color: var(--color-text-muted); font-size: 0.875rem;">URL</th>
                        <th style="text-align: left; padding: var(--space-3); font-weight: 600; color: var(--color-text-muted); font-size: 0.875rem;">Limit</th>
                        <th style="text-align: left; padding: var(--space-3); font-weight: 600; color: var(--color-text-muted); font-size: 0.875rem;">Status</th>
                        <th style="text-align: left; padding: var(--space-3); font-weight: 600; color: var(--color-text-muted); font-size: 0.875rem;">Last Processed</th>
                        <th style="text-align: right; padding: var(--space-3); font-weight: 600; color: var(--color-text-muted); font-size: 0.875rem;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($feeds as $feed): ?>
                        <tr style="border-bottom: 1px solid var(--color-border); transition: background-color var(--duration-short);"
                            onmouseover="this.style.backgroundColor = 'var(--color-border-light)'"
                            onmouseout="this.style.backgroundColor = 'transparent'">
                            <td style="padding: var(--space-3);">
                                <a href="/feeds/<?= (int)$feed['id'] ?>"
                                   style="color: var(--color-primary); text-decoration: none; font-weight: 500;">
                                    <?= $escaper->html($feed['topic']) ?>
                                </a>
                            </td>
                            <td style="padding: var(--space-3); max-width: 250px;">
                                <code style="font-size: 0.75rem; background: var(--color-border-light); padding: 0.25rem 0.5rem; border-radius: var(--radius-sm);">
                                    <?= $escaper->html(substr($feed['url'], 0, 40)) ?><?= strlen($feed['url']) > 40 ? '...' : '' ?>
                                </code>
                            </td>
                            <td style="padding: var(--space-3); text-align: center;">
                                <span class="badge neutral no-dot" style="padding: 0.25rem 0.5rem;">
                                    <?= (int)$feed['result_limit'] ?>
                                </span>
                            </td>
                            <td style="padding: var(--space-3);">
                                <?php if ($feed['enabled']): ?>
                                    <span class="badge success no-dot">Active</span>
                                <?php else: ?>
                                    <span class="badge neutral no-dot">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: var(--space-3); font-size: 0.875rem; color: var(--color-text-muted);">
                                <?php if (!empty($feed['last_processed_at'])): ?>
                                    <?= $timezone->formatLocal($feed['last_processed_at'], 'M j, g:i A') ?>
                                <?php else: ?>
                                    <em>Never</em>
                                <?php endif; ?>
                            </td>
                            <td style="padding: var(--space-3); text-align: right;">
                                <div class="flex justify-end gap-2">
                                    <a href="/feeds/<?= (int)$feed['id'] ?>/edit"
                                       class="btn btn-sm btn-ghost"
                                       title="Edit feed">
                                        <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor">
                                            <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"/>
                                        </svg>
                                    </a>
                                    <form method="POST" action="/feeds/<?= (int)$feed['id'] ?>/delete" style="display: inline;"
                                          onsubmit="return confirmDelete(this, 'Delete this feed? This action cannot be undone.');">
                                        <?= $csrf->field() ?>
                                        <button type="submit" class="btn btn-sm btn-ghost" style="color: var(--color-error);"
                                                title="Delete feed">
                                            <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z"/>
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Mobile Card View -->
        <div class="grid gap-4 md:hidden">
            <?php foreach ($feeds as $feed): ?>
                <div class="card">
                    <div class="card-body">
                        <div class="flex justify-between items-start gap-3 mb-3">
                            <h3 style="font-size: 1rem; font-weight: 600;">
                                <?= $escaper->html($feed['topic']) ?>
                            </h3>
                            <?php if ($feed['enabled']): ?>
                                <span class="badge success no-dot" style="font-size: 0.75rem;">Active</span>
                            <?php else: ?>
                                <span class="badge neutral no-dot" style="font-size: 0.75rem;">Inactive</span>
                            <?php endif; ?>
                        </div>

                        <div style="margin-bottom: var(--space-3);">
                            <p style="font-size: 0.75rem; color: var(--color-text-muted); margin-bottom: var(--space-1);">URL</p>
                            <code style="font-size: 0.7rem; background: var(--color-border-light); padding: 0.25rem 0.5rem; border-radius: var(--radius-sm); word-break: break-all;">
                                <?= $escaper->html($feed['url']) ?>
                            </code>
                        </div>

                        <div class="grid grid-cols-2 gap-3 mb-4" style="font-size: 0.875rem;">
                            <div>
                                <p style="color: var(--color-text-muted); margin-bottom: var(--space-1);">Limit</p>
                                <p style="font-weight: 500;"><?= (int)$feed['result_limit'] ?></p>
                            </div>
                            <div>
                                <p style="color: var(--color-text-muted); margin-bottom: var(--space-1);">Last Processed</p>
                                <p style="font-weight: 500;">
                                    <?php if (!empty($feed['last_processed_at'])): ?>
                                        <?= $timezone->formatLocal($feed['last_processed_at'], 'M j') ?>
                                    <?php else: ?>
                                        <em>Never</em>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>

                        <div class="flex gap-2">
                            <a href="/feeds/<?= (int)$feed['id'] ?>/edit" class="btn btn-sm btn-primary flex-1">
                                Edit
                            </a>
                            <form method="POST" action="/feeds/<?= (int)$feed['id'] ?>/delete" style="flex: 1;"
                                  onsubmit="return confirmDelete(this, 'Delete this feed? This action cannot be undone.');">
                                <?= $csrf->field() ?>
                                <button type="submit" class="btn btn-sm btn-danger w-full">
                                    Delete
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php
        $total_pages = ceil($total_count / $per_page);
        if ($total_pages > 1):
        ?>
            <div class="pagination flex justify-center gap-2 mt-8">
                <?php if ($page > 1): ?>
                    <a href="/feeds?page=<?= $page - 1 ?>" class="btn btn-sm btn-secondary">Previous</a>
                <?php else: ?>
                    <button class="btn btn-sm btn-secondary" disabled>Previous</button>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i === $page): ?>
                        <button class="btn btn-sm btn-primary" disabled><?= $i ?></button>
                    <?php else: ?>
                        <a href="/feeds?page=<?= $i ?>" class="btn btn-sm btn-secondary"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="/feeds?page=<?= $page + 1 ?>" class="btn btn-sm btn-secondary">Next</a>
                <?php else: ?>
                    <button class="btn btn-sm btn-secondary" disabled>Next</button>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    <?php endif; ?>
</div>

<?php include __DIR__ . '/../partials/footer.php'; ?>
