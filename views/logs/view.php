<?php
/**
 * Log Detail View
 *
 * Displays detailed information about a specific log entry.
 */

// Define helper function at top to avoid undefined function errors
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

if (!function_exists('formatContext')) {
    function formatContext($context): string
    {
        if (empty($context)) {
            return '<em class="text-gray-500">No context data</em>';
        }

        if (is_string($context)) {
            $context = json_decode($context, true);
        }

        if (!is_array($context)) {
            return '<em class="text-gray-500">Invalid context data</em>';
        }

        return '<pre class="bg-gray-50 p-4 rounded border overflow-x-auto"><code>' .
               htmlspecialchars(json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) .
               '</code></pre>';
    }
}

$page_title = 'Log Details';
require __DIR__ . '/../partials/header.php';
?>

<div class="container py-6">
    <!-- Header with Back Button -->
    <div class="mb-6">
        <a href="/logs" class="btn btn-ghost mb-4">
            &larr; Back to Logs
        </a>
        <h1 class="text-3xl font-bold">Log Details</h1>
    </div>

    <!-- Log Details Card -->
    <div class="card">
        <div class="card-body">
            <div class="grid gap-6">
                <!-- Log ID -->
                <div class="field-group">
                    <label class="field-label">Log ID</label>
                    <div class="field-value font-mono">
                        <?= $escaper->html($log['id']) ?>
                    </div>
                </div>

                <!-- Log Level -->
                <div class="field-group">
                    <label class="field-label">Log Level</label>
                    <div class="field-value">
                        <span class="badge <?= getLevelBadgeClass($log['log_level']) ?>">
                            <?= $escaper->html($log['log_level']) ?>
                        </span>
                    </div>
                </div>

                <!-- Log Type -->
                <div class="field-group">
                    <label class="field-label">Log Type</label>
                    <div class="field-value">
                        <span class="badge badge-secondary">
                            <?= $escaper->html(ucfirst($log['log_type'])) ?>
                        </span>
                    </div>
                </div>

                <!-- Message -->
                <div class="field-group">
                    <label class="field-label">Message</label>
                    <div class="field-value">
                        <?= $escaper->html($log['message']) ?>
                    </div>
                </div>

                <!-- Context -->
                <div class="field-group">
                    <label class="field-label">Context</label>
                    <div class="field-value">
                        <?= formatContext($log['context'] ?? null) ?>
                    </div>
                </div>

                <!-- IP Address -->
                <?php if (!empty($log['ip_address'])): ?>
                    <div class="field-group">
                        <label class="field-label">IP Address</label>
                        <div class="field-value font-mono">
                            <?= $escaper->html($log['ip_address']) ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- User Agent -->
                <?php if (!empty($log['user_agent'])): ?>
                    <div class="field-group">
                        <label class="field-label">User Agent</label>
                        <div class="field-value text-sm text-gray-600 break-all">
                            <?= $escaper->html($log['user_agent']) ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Created At -->
                <div class="field-group">
                    <label class="field-label">Created At</label>
                    <div class="field-value">
                        <div class="text-sm">
                            <strong>Local:</strong>
                            <?= $escaper->html($log['created_at_local'] ?? $log['created_at']) ?>
                        </div>
                        <div class="text-sm text-gray-600 mt-1">
                            <strong>UTC:</strong>
                            <?= $escaper->html($log['created_at']) ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Actions -->
    <div class="mt-6 flex gap-3">
        <a href="/logs" class="btn btn-secondary">
            &larr; Back to Logs
        </a>
    </div>
</div>

<style>
.field-group {
    border-bottom: 1px solid #e5e7eb;
    padding-bottom: 1.5rem;
}

.field-group:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

.field-label {
    display: block;
    font-size: 0.875rem;
    font-weight: 600;
    color: #4b5563;
    margin-bottom: 0.5rem;
}

.field-value {
    font-size: 1rem;
    color: #111827;
}
</style>

<?php require __DIR__ . '/../partials/footer.php'; ?>
