<?php
/**
 * Logs List View
 *
 * Displays application logs with filtering and pagination.
 */

// Define helper function at top to avoid undefined function errors
if (!function_exists('getLevelColor')) {
    function getLevelColor(string $level): string
    {
        return match ($level) {
            'DEBUG' => 'gray',
            'INFO' => 'blue',
            'WARNING' => 'yellow',
            'ERROR' => 'red',
            default => 'gray',
        };
    }
}

if (!function_exists('getLevelBadgeClass')) {
    function getLevelBadgeClass(string $level): string
    {
        return match ($level) {
            'DEBUG' => 'neutral',
            'INFO' => 'info',
            'WARNING' => 'warning',
            'ERROR', 'CRITICAL' => 'error',
            default => 'neutral',
        };
    }
}

$page_title = 'Application Logs';
require __DIR__ . '/../partials/header.php';
?>

<div class="container py-6">
    <div class="page-header mb-6">
        <div class="flex justify-between items-center">
            <h1 class="text-3xl font-bold">Application Logs</h1>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-6">
        <div class="card-body">
            <form method="GET" action="/logs" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4" style="display: grid; align-items: start;">
                <!-- Log Type Filter -->
                <div class="form-group">
                    <label for="log_type" class="label" style="height: 1.5rem; display: block;">Log Type</label>
                    <select name="log_type" id="log_type" class="input-field">
                        <option value="">All Types</option>
                        <?php foreach ($log_types as $type): ?>
                            <option value="<?= $escaper->attribute($type) ?>"
                                    <?= isset($filters['log_type']) && $filters['log_type'] === $type ? 'selected' : '' ?>>
                                <?= $escaper->html(ucfirst($type)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Log Level Filter -->
                <div class="form-group">
                    <label for="log_level" class="label" style="height: 1.5rem; display: block;">Log Level</label>
                    <select name="log_level" id="log_level" class="input-field">
                        <option value="">All Levels</option>
                        <?php foreach ($log_levels as $level): ?>
                            <option value="<?= $escaper->attribute($level) ?>"
                                    <?= isset($filters['log_level']) && $filters['log_level'] === $level ? 'selected' : '' ?>>
                                <?= $escaper->html($level) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Date From -->
                <div class="form-group">
                    <label for="date_from" class="label" style="height: 1.5rem; display: block;">Date From</label>
                    <input type="date"
                           name="date_from"
                           id="date_from"
                           class="input-field"
                           value="<?= $escaper->attribute($filters['date_from'] ?? '') ?>">
                </div>

                <!-- Date To -->
                <div class="form-group">
                    <label for="date_to" class="label" style="height: 1.5rem; display: block;">Date To</label>
                    <input type="date"
                           name="date_to"
                           id="date_to"
                           class="input-field"
                           value="<?= $escaper->attribute($filters['date_to'] ?? '') ?>">
                </div>

                <!-- Search -->
                <div class="form-group">
                    <label for="search" class="label" style="height: 1.5rem; display: block;">Search Message</label>
                    <input type="text"
                           name="search"
                           id="search"
                           class="input-field"
                           placeholder="Search..."
                           value="<?= $escaper->attribute($filters['search'] ?? '') ?>">
                </div>

                <!-- Filter Actions -->
                <div class="form-group">
                    <label class="label" style="height: 1.5rem; display: block;">&nbsp;</label>
                    <div class="flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            Apply Filters
                        </button>
                        <button type="button" onclick="window.location.href='/logs'" class="btn btn-secondary">
                            Clear Filters
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Results Summary -->
    <div class="mb-4 text-sm text-gray-600">
        Showing <?= count($logs) ?> of <?= number_format($total_count) ?> logs
        <?php if (!empty($filters)): ?>
            (filtered)
        <?php endif; ?>
    </div>

    <!-- Logs Table -->
    <div class="card">
        <div class="card-body p-0">
            <?php if (empty($logs)): ?>
                <div class="p-8 text-center text-gray-500">
                    <p>No logs found.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th class="w-20">Level</th>
                                <th class="w-24">Type</th>
                                <th>Message</th>
                                <th class="w-40">Created At</th>
                                <th class="w-20">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                                <tr class="hover:bg-gray-50">
                                    <td>
                                        <span class="badge <?= getLevelBadgeClass($log['log_level']) ?>">
                                            <?= $escaper->html($log['log_level']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="text-sm text-gray-600">
                                            <?= $escaper->html(ucfirst($log['log_type'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="truncate max-w-md" title="<?= $escaper->attribute($log['message']) ?>">
                                            <?= $escaper->html($log['message']) ?>
                                        </div>
                                    </td>
                                    <td class="text-sm text-gray-600">
                                        <?= $escaper->html($log['created_at_local'] ?? $log['created_at']) ?>
                                    </td>
                                    <td>
                                        <a href="/logs/<?= $log['id'] ?>" class="btn btn-sm btn-ghost">
                                            View
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Pagination -->
    <?php if ($page_count > 1): ?>
        <div class="mt-6 flex justify-center">
            <nav class="pagination" role="navigation" aria-label="Pagination">
                <?php
                // Build query string for pagination links
                $queryParams = array_filter([
                    'log_type' => $filters['log_type'] ?? null,
                    'log_level' => $filters['log_level'] ?? null,
                    'search' => $filters['search'] ?? null,
                    'date_from' => $filters['date_from'] ?? null,
                    'date_to' => $filters['date_to'] ?? null,
                ]);
                $queryString = !empty($queryParams) ? '&' . http_build_query($queryParams) : '';
                ?>

                <!-- Previous Page -->
                <?php if ($current_page > 1): ?>
                    <a href="/logs?page=<?= (int)$current_page - 1 ?><?= $queryString ?>"
                       class="pagination-link"
                       aria-label="Previous page">
                        &laquo; Previous
                    </a>
                <?php endif; ?>

                <!-- Page Numbers -->
                <?php
                $start = max(1, (int)$current_page - 2);
                $end = min((int)$page_count, (int)$current_page + 2);
                ?>

                <?php if ($start > 1): ?>
                    <a href="/logs?page=1<?= $queryString ?>" class="pagination-link">1</a>
                    <?php if ($start > 2): ?>
                        <span class="pagination-ellipsis">...</span>
                    <?php endif; ?>
                <?php endif; ?>

                <?php for ($i = $start; $i <= $end; $i++): ?>
                    <?php if ($i === $current_page): ?>
                        <span class="pagination-link pagination-link-active" aria-current="page">
                            <?= $i ?>
                        </span>
                    <?php else: ?>
                        <a href="/logs?page=<?= $i ?><?= $queryString ?>"
                           class="pagination-link">
                            <?= $i ?>
                        </a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($end < $page_count): ?>
                    <?php if ($end < (int)$page_count - 1): ?>
                        <span class="pagination-ellipsis">...</span>
                    <?php endif; ?>
                    <a href="/logs?page=<?= $page_count ?><?= $queryString ?>"
                       class="pagination-link">
                        <?= $page_count ?>
                    </a>
                <?php endif; ?>

                <!-- Next Page -->
                <?php if ($current_page < $page_count): ?>
                    <a href="/logs?page=<?= (int)$current_page + 1 ?><?= $queryString ?>"
                       class="pagination-link"
                       aria-label="Next page">
                        Next &raquo;
                    </a>
                <?php endif; ?>
            </nav>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
