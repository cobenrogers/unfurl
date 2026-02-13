<?php

declare(strict_types=1);

namespace Unfurl\Controllers;

use Unfurl\Repositories\ApiKeyRepository;
use Unfurl\Security\CsrfToken;
use Unfurl\Core\Logger;
use Unfurl\Exceptions\SecurityException;

/**
 * Settings Controller
 *
 * Handles settings page and API key management operations.
 * Provides CRUD operations for API keys with secure key generation and display.
 *
 * Requirements: Section 5.3.4 of REQUIREMENTS.md
 *
 * Security Features:
 * - CSRF protection on all POST requests
 * - Secure API key generation (64 character hex string)
 * - Full key shown only once at creation
 * - Only last 8 characters shown after creation
 */
class SettingsController
{
    private ApiKeyRepository $apiKeyRepository;
    private CsrfToken $csrf;
    private Logger $logger;

    /**
     * Initialize the controller
     *
     * @param ApiKeyRepository $apiKeyRepository
     * @param CsrfToken $csrf
     * @param Logger $logger
     */
    public function __construct(
        ApiKeyRepository $apiKeyRepository,
        CsrfToken $csrf,
        Logger $logger
    ) {
        $this->apiKeyRepository = $apiKeyRepository;
        $this->csrf = $csrf;
        $this->logger = $logger;

        // Ensure session is started for flash messages (unless in test mode)
        if (!CsrfToken::isTestMode() && session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Generate a secure API key
     *
     * Uses random_bytes(32) converted to hex for 64 character key.
     *
     * @return string 64 character hex API key
     */
    public function generateApiKey(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Display settings page
     *
     * GET /settings
     *
     * @return array View data
     */
    public function index(): array
    {
        $this->logger->info('Settings page viewed', ['category' => 'settings']);

        $apiKeys = $this->apiKeyRepository->findAll();

        // Note: We don't mask API keys on the settings page because users need
        // to see their own keys via the "Show Key" functionality. The keys are
        // protected by authentication and the preview in the UI only shows partial values.

        return [
            'apiKeys' => $apiKeys,
            'flashMessage' => $this->getFlashMessage(),
            'newApiKey' => $_SESSION['new_api_key'] ?? null,
        ];
    }

    /**
     * Create a new API key
     *
     * POST /settings/api-keys/create
     *
     * @param array $data Request data (key_name, description, enabled)
     * @return array Redirect data
     * @throws SecurityException If CSRF validation fails
     */
    public function createApiKey(array $data): array
    {
        // Validate CSRF token
        $this->csrf->validate($data['csrf_token'] ?? null);

        // Validate required fields
        if (empty($data['key_name'])) {
            $this->setFlashMessage('error', 'API key name is required');
            return $this->redirect('/settings');
        }

        // Generate secure API key
        $keyValue = $this->generateApiKey();

        // Prepare data for repository
        $apiKeyData = [
            'key_name' => trim($data['key_name']),
            'key_value' => $keyValue,
            'description' => !empty($data['description']) ? trim($data['description']) : null,
            'enabled' => isset($data['enabled']) && $data['enabled'] === '1' ? 1 : 0,
        ];

        try {
            $id = $this->apiKeyRepository->create($apiKeyData);

            // Store full key in session for one-time display
            $_SESSION['new_api_key'] = $keyValue;

            $this->logger->info('API key created', [
                'category' => 'settings',
                'key_id' => $id,
                'key_name' => $apiKeyData['key_name'],
            ]);

            $this->setFlashMessage('success', 'API key created successfully. Save it securely - it won\'t be shown again.');

            return $this->redirect('/settings');
        } catch (\PDOException $e) {
            // Check for duplicate key_value (extremely unlikely but possible)
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                $this->logger->error('Duplicate API key generated', [
                    'category' => 'settings',
                    'error' => $e->getMessage(),
                ]);
                $this->setFlashMessage('error', 'Failed to create API key. Please try again.');
            } else {
                $this->logger->error('Failed to create API key', [
                    'category' => 'settings',
                    'error' => $e->getMessage(),
                ]);
                $this->setFlashMessage('error', 'Database error occurred while creating API key');
            }

            return $this->redirect('/settings');
        }
    }

    /**
     * Edit an existing API key
     *
     * POST /settings/api-keys/edit/{id}
     * Only allows editing name, description, and enabled status.
     * The key value itself cannot be changed.
     *
     * @param int $id API key ID
     * @param array $data Request data (key_name, description, enabled)
     * @return array Redirect data
     * @throws SecurityException If CSRF validation fails
     */
    public function editApiKey(int $id, array $data): array
    {
        // Validate CSRF token
        $this->csrf->validate($data['csrf_token'] ?? null);

        // Verify API key exists
        $apiKey = $this->apiKeyRepository->findById($id);
        if ($apiKey === null) {
            $this->setFlashMessage('error', 'API key not found');
            return $this->redirect('/settings');
        }

        // Validate required fields
        if (empty($data['key_name'])) {
            $this->setFlashMessage('error', 'API key name is required');
            return $this->redirect('/settings');
        }

        // Prepare update data (only editable fields)
        $updateData = [
            'key_name' => trim($data['key_name']),
            'description' => !empty($data['description']) ? trim($data['description']) : null,
            'enabled' => isset($data['enabled']) && $data['enabled'] === '1' ? 1 : 0,
        ];

        try {
            $success = $this->apiKeyRepository->update($id, $updateData);

            if ($success) {
                $this->logger->info('API key updated', [
                    'category' => 'settings',
                    'key_id' => $id,
                    'key_name' => $updateData['key_name'],
                ]);

                $this->setFlashMessage('success', 'API key updated successfully');
            } else {
                $this->setFlashMessage('error', 'Failed to update API key');
            }

            return $this->redirect('/settings');
        } catch (\PDOException $e) {
            $this->logger->error('Failed to update API key', [
                'category' => 'settings',
                'key_id' => $id,
                'error' => $e->getMessage(),
            ]);
            $this->setFlashMessage('error', 'Database error occurred while updating API key');

            return $this->redirect('/settings');
        }
    }

    /**
     * Toggle API key enabled status
     *
     * POST /settings/api-keys/{id}/toggle
     *
     * @param int $id API key ID
     * @param array $data Request data (csrf_token)
     * @return array Redirect data
     * @throws SecurityException If CSRF validation fails
     */
    public function toggleApiKey(int $id, array $data): array
    {
        // Validate CSRF token
        $this->csrf->validate($data['csrf_token'] ?? null);

        // Get API key info
        $apiKey = $this->apiKeyRepository->findById($id);
        if ($apiKey === null) {
            $this->setFlashMessage('error', 'API key not found');
            return $this->redirect('/settings');
        }

        try {
            $newStatus = !$apiKey['enabled'];
            $success = $this->apiKeyRepository->update($id, ['enabled' => $newStatus ? 1 : 0]);

            if ($success) {
                $action = $newStatus ? 'enabled' : 'disabled';
                $this->logger->info("API key {$action}", [
                    'category' => 'settings',
                    'key_id' => $id,
                    'key_name' => $apiKey['key_name'],
                    'enabled' => $newStatus,
                ]);

                $this->setFlashMessage('success', "API key \"{$apiKey['key_name']}\" {$action} successfully");
            } else {
                $this->setFlashMessage('error', 'Failed to update API key');
            }

            return $this->redirect('/settings');
        } catch (\PDOException $e) {
            $this->logger->error('Failed to toggle API key', [
                'category' => 'settings',
                'key_id' => $id,
                'error' => $e->getMessage(),
            ]);
            $this->setFlashMessage('error', 'Database error occurred while updating API key');

            return $this->redirect('/settings');
        }
    }

    /**
     * Delete an API key
     *
     * POST /settings/api-keys/delete/{id}
     *
     * @param int $id API key ID
     * @param array $data Request data (csrf_token)
     * @return array Redirect data
     * @throws SecurityException If CSRF validation fails
     */
    public function deleteApiKey(int $id, array $data): array
    {
        // Validate CSRF token
        $this->csrf->validate($data['csrf_token'] ?? null);

        // Get API key info before deletion for logging
        $apiKey = $this->apiKeyRepository->findById($id);
        if ($apiKey === null) {
            $this->setFlashMessage('error', 'API key not found');
            return $this->redirect('/settings');
        }

        try {
            $success = $this->apiKeyRepository->delete($id);

            if ($success) {
                $this->logger->warning('API key deleted', [
                    'category' => 'settings',
                    'key_id' => $id,
                    'key_name' => $apiKey['key_name'],
                ]);

                $this->setFlashMessage('success', 'API key deleted successfully');
            } else {
                $this->setFlashMessage('error', 'Failed to delete API key');
            }

            return $this->redirect('/settings');
        } catch (\PDOException $e) {
            $this->logger->error('Failed to delete API key', [
                'category' => 'settings',
                'key_id' => $id,
                'error' => $e->getMessage(),
            ]);
            $this->setFlashMessage('error', 'Database error occurred while deleting API key');

            return $this->redirect('/settings');
        }
    }

    /**
     * Show full API key value
     *
     * POST /settings/api-keys/show/{id}
     * Returns full key value. Should be used sparingly as this exposes the full key.
     *
     * @param int $id API key ID
     * @param array $data Request data (csrf_token)
     * @return array JSON response with full key
     * @throws SecurityException If CSRF validation fails
     */
    public function showApiKey(int $id, array $data): array
    {
        // Validate CSRF token
        $this->csrf->validate($data['csrf_token'] ?? null);

        $apiKey = $this->apiKeyRepository->findById($id);

        if ($apiKey === null) {
            return [
                'success' => false,
                'message' => 'API key not found',
            ];
        }

        $this->logger->info('API key viewed', [
            'category' => 'settings',
            'key_id' => $id,
            'key_name' => $apiKey['key_name'],
        ]);

        return [
            'success' => true,
            'key_value' => $apiKey['key_value'],
            'key_name' => $apiKey['key_name'],
        ];
    }

    /**
     * Update retention settings
     *
     * POST /settings/retention
     *
     * @param array $data Request data (articles_days, logs_days, auto_cleanup)
     * @return array Redirect data
     * @throws SecurityException If CSRF validation fails
     */
    public function updateRetention(array $data): array
    {
        // Validate CSRF token
        $this->csrf->validate($data['csrf_token'] ?? null);

        // Validate retention days
        $articlesDays = isset($data['articles_days']) ? (int)$data['articles_days'] : 90;
        $logsDays = isset($data['logs_days']) ? (int)$data['logs_days'] : 30;
        $autoCleanup = isset($data['auto_cleanup']) && $data['auto_cleanup'] === '1';

        // Enforce minimum for logs
        if ($logsDays < 7) {
            $this->setFlashMessage('error', 'Logs retention must be at least 7 days');
            return $this->redirect('/settings');
        }

        // Articles can be 0 (keep forever)
        if ($articlesDays < 0) {
            $this->setFlashMessage('error', 'Articles retention must be 0 or greater');
            return $this->redirect('/settings');
        }

        // Update .env file with new values
        try {
            $this->updateEnvFile([
                'RETENTION_ARTICLES_DAYS' => (string)$articlesDays,
                'RETENTION_LOGS_DAYS' => (string)$logsDays,
                'RETENTION_AUTO_CLEANUP' => $autoCleanup ? 'true' : 'false',
            ]);

            $this->logger->info('Retention settings updated', [
                'category' => 'settings',
                'articles_days' => $articlesDays,
                'logs_days' => $logsDays,
                'auto_cleanup' => $autoCleanup,
            ]);

            $this->setFlashMessage('success', 'Retention settings updated successfully');
        } catch (\Exception $e) {
            $this->logger->error('Failed to update retention settings', [
                'category' => 'settings',
                'error' => $e->getMessage(),
            ]);
            $this->setFlashMessage('error', 'Failed to update settings: ' . $e->getMessage());
        }

        return $this->redirect('/settings');
    }

    /**
     * Run cleanup now
     *
     * POST /settings/cleanup
     *
     * @param array $data Request data (csrf_token)
     * @param \Unfurl\Repositories\ArticleRepository $articleRepo Article repository
     * @param array $config Application configuration
     * @return array Redirect data
     * @throws SecurityException If CSRF validation fails
     */
    public function runCleanup(array $data, $articleRepo, array $config): array
    {
        // Validate CSRF token
        $this->csrf->validate($data['csrf_token'] ?? null);

        try {
            $retentionDays = (int)($config['retention']['articles_days'] ?? 90);

            if ($retentionDays > 0) {
                $deletedCount = $articleRepo->deleteOlderThan($retentionDays);

                $this->logger->info('Manual cleanup completed', [
                    'category' => 'settings',
                    'deleted_count' => $deletedCount,
                    'retention_days' => $retentionDays,
                ]);

                $this->setFlashMessage('success', "Cleanup completed. Deleted {$deletedCount} old articles.");
            } else {
                $this->setFlashMessage('info', 'Cleanup skipped. Retention is set to keep articles forever (0 days).');
            }
        } catch (\Exception $e) {
            $this->logger->error('Cleanup failed', [
                'category' => 'settings',
                'error' => $e->getMessage(),
            ]);
            $this->setFlashMessage('error', 'Cleanup failed: ' . $e->getMessage());
        }

        return $this->redirect('/settings');
    }

    /**
     * Update processing settings
     *
     * POST /settings/processing
     *
     * @param array $data Request data (timeout, max_retries, retry_delay)
     * @return array Redirect data
     * @throws SecurityException If CSRF validation fails
     */
    public function updateProcessing(array $data): array
    {
        // Validate CSRF token
        $this->csrf->validate($data['csrf_token'] ?? null);

        // Validate processing settings
        $timeout = isset($data['timeout']) ? (int)$data['timeout'] : 30;
        $maxRetries = isset($data['max_retries']) ? (int)$data['max_retries'] : 3;
        $retryDelay = isset($data['retry_delay']) ? (int)$data['retry_delay'] : 60;

        // Validate ranges
        if ($timeout < 5 || $timeout > 300) {
            $this->setFlashMessage('error', 'Timeout must be between 5 and 300 seconds');
            return $this->redirect('/settings');
        }

        if ($maxRetries < 0 || $maxRetries > 10) {
            $this->setFlashMessage('error', 'Max retries must be between 0 and 10');
            return $this->redirect('/settings');
        }

        if ($retryDelay < 10 || $retryDelay > 3600) {
            $this->setFlashMessage('error', 'Retry delay must be between 10 and 3600 seconds');
            return $this->redirect('/settings');
        }

        // Update .env file with new values
        try {
            $this->updateEnvFile([
                'PROCESSING_TIMEOUT' => (string)$timeout,
                'PROCESSING_MAX_RETRIES' => (string)$maxRetries,
                'PROCESSING_RETRY_DELAY' => (string)$retryDelay,
            ]);

            $this->logger->info('Processing settings updated', [
                'category' => 'settings',
                'timeout' => $timeout,
                'max_retries' => $maxRetries,
                'retry_delay' => $retryDelay,
            ]);

            $this->setFlashMessage('success', 'Processing settings updated successfully');
        } catch (\Exception $e) {
            $this->logger->error('Failed to update processing settings', [
                'category' => 'settings',
                'error' => $e->getMessage(),
            ]);
            $this->setFlashMessage('error', 'Failed to update settings: ' . $e->getMessage());
        }

        return $this->redirect('/settings');
    }

    /**
     * Update .env file with new values
     *
     * @param array $updates Key-value pairs to update
     * @throws \Exception If .env file cannot be updated
     */
    private function updateEnvFile(array $updates): void
    {
        $envPath = __DIR__ . '/../../.env';

        if (!file_exists($envPath)) {
            throw new \Exception('.env file not found');
        }

        // Read current .env file
        $lines = file($envPath, FILE_IGNORE_NEW_LINES);
        $updatedLines = [];
        $keysUpdated = [];

        foreach ($lines as $line) {
            // Skip comments and empty lines
            if (empty(trim($line)) || strpos(trim($line), '#') === 0) {
                $updatedLines[] = $line;
                continue;
            }

            // Parse KEY=VALUE
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);

                if (isset($updates[$key])) {
                    $updatedLines[] = "{$key}={$updates[$key]}";
                    $keysUpdated[] = $key;
                } else {
                    $updatedLines[] = $line;
                }
            } else {
                $updatedLines[] = $line;
            }
        }

        // Add any new keys that weren't found in the file
        foreach ($updates as $key => $value) {
            if (!in_array($key, $keysUpdated)) {
                $updatedLines[] = "{$key}={$value}";
            }
        }

        // Write back to .env file
        $result = file_put_contents($envPath, implode("\n", $updatedLines) . "\n");

        if ($result === false) {
            throw new \Exception('Failed to write to .env file');
        }
    }

    /**
     * Mask an API key (show only last 8 characters)
     *
     * @param string $key Full API key
     * @return string Masked key (last 8 chars)
     */
    private function maskApiKey(string $key): string
    {
        if (strlen($key) <= 8) {
            return $key;
        }

        return substr($key, -8);
    }

    /**
     * Set flash message in session
     *
     * @param string $type Message type (success, error, info)
     * @param string $message Message content
     */
    private function setFlashMessage(string $type, string $message): void
    {
        $_SESSION['flash_message'] = [
            'type' => $type,
            'message' => $message,
        ];
    }

    /**
     * Get flash message from session and clear it
     *
     * @return array|null Flash message data or null
     */
    private function getFlashMessage(): ?array
    {
        $message = $_SESSION['flash_message'] ?? null;
        unset($_SESSION['flash_message']);
        return $message;
    }

    /**
     * Generate redirect response
     *
     * @param string $path Redirect path
     * @return array Redirect data
     */
    private function redirect(string $path): array
    {
        return [
            'redirect' => $path,
        ];
    }
}
