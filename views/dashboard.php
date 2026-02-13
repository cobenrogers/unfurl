<?php
/**
 * Dashboard View - Admin Monitoring Dashboard
 *
 * Displays key metrics, recent activity, and system health.
 * Real-time updates via JavaScript for live monitoring.
 */

use Unfurl\Security\OutputEscaper;

$escaper = new OutputEscaper();
$page_title = 'Dashboard';

// Include header
require_once __DIR__ . '/partials/header.php';
?>

<div class="container my-6">
    <!-- Page Header -->
    <div class="mb-6">
        <h1 class="text-3xl font-bold mb-2">Dashboard</h1>
        <p class="text-muted">System overview and key metrics</p>
    </div>

    <!-- System Health Alert -->
    <div id="health-alert" class="alert alert-info mb-4" style="display: none;">
        <div class="alert-icon">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"/>
            </svg>
        </div>
        <div class="alert-content">
            <div class="alert-message" id="health-message">Checking system health...</div>
        </div>
    </div>

    <!-- Key Metrics Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <!-- Total Feeds -->
        <div class="card">
            <div class="card-body">
                <div class="flex items-center justify-between mb-2">
                    <div class="text-muted text-sm">Total Feeds</div>
                    <svg class="w-8 h-8 text-primary opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 5c7.18 0 13 5.82 13 13M6 11a7 7 0 017 7m-6 0a1 1 0 11-2 0 1 1 0 012 0z"/>
                    </svg>
                </div>
                <div class="text-3xl font-bold" id="metric-feeds-total">
                    <?= isset($metrics['feeds_total']) ? $escaper->html($metrics['feeds_total']) : '--' ?>
                </div>
                <div class="text-xs text-muted mt-1">
                    <span id="metric-feeds-enabled">
                        <?= isset($metrics['feeds_enabled']) ? $escaper->html($metrics['feeds_enabled']) : '--' ?>
                    </span> enabled
                </div>
            </div>
        </div>

        <!-- Success Articles -->
        <div class="card">
            <div class="card-body">
                <div class="flex items-center justify-between mb-2">
                    <div class="text-muted text-sm">Successful Articles</div>
                    <svg class="w-8 h-8 text-success opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div class="text-3xl font-bold text-success" id="metric-articles-success">
                    <?= isset($metrics['articles_success']) ? $escaper->html(number_format($metrics['articles_success'])) : '--' ?>
                </div>
                <div class="text-xs text-muted mt-1">
                    <?= isset($metrics['success_rate']) ? $escaper->html($metrics['success_rate']) : '--' ?>% success rate
                </div>
            </div>
        </div>

        <!-- Failed Articles -->
        <div class="card">
            <div class="card-body">
                <div class="flex items-center justify-between mb-2">
                    <div class="text-muted text-sm">Failed Articles</div>
                    <svg class="w-8 h-8 text-error opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div class="text-3xl font-bold text-error" id="metric-articles-failed">
                    <?= isset($metrics['articles_failed']) ? $escaper->html(number_format($metrics['articles_failed'])) : '--' ?>
                </div>
                <div class="text-xs text-muted mt-1">
                    <span id="metric-articles-pending">
                        <?= isset($metrics['articles_pending']) ? $escaper->html(number_format($metrics['articles_pending'])) : '--' ?>
                    </span> pending
                </div>
            </div>
        </div>

        <!-- Processing Queue -->
        <div class="card">
            <div class="card-body">
                <div class="flex items-center justify-between mb-2">
                    <div class="text-muted text-sm">Retry Queue</div>
                    <svg class="w-8 h-8 text-warning opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                </div>
                <div class="text-3xl font-bold text-warning" id="metric-queue-pending">
                    <?= isset($metrics['queue_pending']) ? $escaper->html(number_format($metrics['queue_pending'])) : '--' ?>
                </div>
                <div class="text-xs text-muted mt-1">
                    <span id="metric-queue-ready">
                        <?= isset($metrics['queue_ready']) ? $escaper->html(number_format($metrics['queue_ready'])) : '--' ?>
                    </span> ready to retry
                </div>
            </div>
        </div>
    </div>

    <!-- Two Column Layout -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Recent Activity (2 columns) -->
        <div class="lg:col-span-2">
            <div class="card">
                <div class="card-header">
                    <h2 class="text-xl font-semibold">Recent Activity</h2>
                </div>
                <div class="card-body p-0">
                    <div id="recent-activity" class="divide-y">
                        <?php if (isset($recent_activity) && is_array($recent_activity) && count($recent_activity) > 0): ?>
                            <?php foreach ($recent_activity as $activity): ?>
                                <div class="p-4 hover:bg-gray-50 transition">
                                    <div class="flex items-start gap-3">
                                        <div class="flex-shrink-0">
                                            <?php if ($activity['type'] === 'success'): ?>
                                                <div class="w-10 h-10 rounded-full bg-success-light flex items-center justify-center">
                                                    <svg class="w-5 h-5 text-success" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"/>
                                                    </svg>
                                                </div>
                                            <?php elseif ($activity['type'] === 'error'): ?>
                                                <div class="w-10 h-10 rounded-full bg-error-light flex items-center justify-center">
                                                    <svg class="w-5 h-5 text-error" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"/>
                                                    </svg>
                                                </div>
                                            <?php else: ?>
                                                <div class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center">
                                                    <svg class="w-5 h-5 text-gray-600" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"/>
                                                    </svg>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex-1">
                                            <div class="font-medium">
                                                <?= $escaper->html($activity['message']) ?>
                                            </div>
                                            <div class="text-sm text-muted mt-1">
                                                <?= $escaper->html($activity['time_ago']) ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="p-8 text-center text-muted">
                                <svg class="w-12 h-12 mx-auto mb-2 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                                </svg>
                                <p>No recent activity</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Status Sidebar (1 column) -->
        <div class="lg:col-span-1">
            <!-- System Health -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="text-lg font-semibold">System Health</h3>
                </div>
                <div class="card-body">
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-sm">Database</span>
                            <span id="health-database" class="badge badge-success">Online</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm">File Permissions</span>
                            <span id="health-permissions" class="badge badge-success">OK</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm">Error Rate</span>
                            <span id="health-error-rate" class="text-sm font-medium">
                                <?= isset($metrics['error_rate']) ? $escaper->html($metrics['error_rate']) : '--' ?>%
                            </span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm">Last Processed</span>
                            <span id="health-last-processed" class="text-sm text-muted">
                                <?= isset($metrics['last_processed']) ? $escaper->html($metrics['last_processed']) : 'Never' ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header">
                    <h3 class="text-lg font-semibold">Quick Actions</h3>
                </div>
                <div class="card-body space-y-2">
                    <a href="/feeds/create" class="btn btn-primary w-full">
                        <svg class="w-4 h-4 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        New Feed
                    </a>
                    <a href="/articles" class="btn btn-secondary w-full">
                        <svg class="w-4 h-4 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                        </svg>
                        View Articles
                    </a>
                    <a href="/settings" class="btn btn-secondary w-full">
                        <svg class="w-4 h-4 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        Settings
                    </a>
                    <button id="refresh-metrics" class="btn btn-ghost w-full">
                        <svg class="w-4 h-4 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        Refresh Metrics
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-refresh metrics every 30 seconds
let autoRefreshInterval;

function refreshMetrics() {
    fetch('/api/dashboard-metrics', {
        method: 'GET',
        headers: {
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update metrics
            updateMetric('feeds-total', data.metrics.feeds_total);
            updateMetric('feeds-enabled', data.metrics.feeds_enabled);
            updateMetric('articles-success', Number(data.metrics.articles_success).toLocaleString());
            updateMetric('articles-failed', Number(data.metrics.articles_failed).toLocaleString());
            updateMetric('articles-pending', Number(data.metrics.articles_pending).toLocaleString());
            updateMetric('queue-pending', Number(data.metrics.queue_pending).toLocaleString());
            updateMetric('queue-ready', Number(data.metrics.queue_ready).toLocaleString());

            // Update health status
            updateHealth(data.health);

            // Hide health alert if system is OK
            const alertDiv = document.getElementById('health-alert');
            if (data.health.status === 'ok') {
                alertDiv.style.display = 'none';
            }
        }
    })
    .catch(error => {
        console.error('Failed to refresh metrics:', error);
        showHealthAlert('Unable to fetch metrics. Please check your connection.', 'error');
    });
}

function updateMetric(id, value) {
    const element = document.getElementById(`metric-${id}`);
    if (element) {
        element.textContent = value;
    }
}

function updateHealth(health) {
    // Update database status
    const dbBadge = document.getElementById('health-database');
    if (dbBadge) {
        if (health.database === 'ok') {
            dbBadge.className = 'badge badge-success';
            dbBadge.textContent = 'Online';
        } else {
            dbBadge.className = 'badge badge-error';
            dbBadge.textContent = 'Offline';
        }
    }

    // Update permissions status
    const permBadge = document.getElementById('health-permissions');
    if (permBadge) {
        if (health.permissions === 'ok') {
            permBadge.className = 'badge badge-success';
            permBadge.textContent = 'OK';
        } else {
            permBadge.className = 'badge badge-warning';
            permBadge.textContent = 'Issues';
        }
    }

    // Update error rate
    const errorRate = document.getElementById('health-error-rate');
    if (errorRate && health.error_rate !== undefined) {
        errorRate.textContent = `${health.error_rate}%`;
    }

    // Update last processed
    const lastProcessed = document.getElementById('health-last-processed');
    if (lastProcessed && health.last_processed) {
        lastProcessed.textContent = health.last_processed;
    }
}

function showHealthAlert(message, type = 'info') {
    const alertDiv = document.getElementById('health-alert');
    const messageDiv = document.getElementById('health-message');

    if (alertDiv && messageDiv) {
        messageDiv.textContent = message;
        alertDiv.className = `alert alert-${type} mb-4`;
        alertDiv.style.display = 'block';
    }
}

// Manual refresh button
document.getElementById('refresh-metrics')?.addEventListener('click', function() {
    this.disabled = true;
    this.innerHTML = '<svg class="w-4 h-4 inline-block mr-2 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Refreshing...';

    refreshMetrics();

    setTimeout(() => {
        this.disabled = false;
        this.innerHTML = '<svg class="w-4 h-4 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>Refresh Metrics';
    }, 1000);
});

// Start auto-refresh
autoRefreshInterval = setInterval(refreshMetrics, 30000); // Every 30 seconds

// Initial health check
refreshMetrics();

// Clean up on page unload
window.addEventListener('beforeunload', function() {
    if (autoRefreshInterval) {
        clearInterval(autoRefreshInterval);
    }
});
</script>

<?php
// Include footer
require_once __DIR__ . '/partials/footer.php';
?>
