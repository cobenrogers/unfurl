<?php

declare(strict_types=1);

namespace Unfurl\Repositories;

use Unfurl\Core\Database;
use Unfurl\Core\TimezoneHelper;

/**
 * API Key Repository
 *
 * Handles all database operations for api_keys table.
 * All queries use prepared statements for security.
 */
class ApiKeyRepository
{
    private Database $db;
    private TimezoneHelper $timezone;

    public function __construct(Database $db, TimezoneHelper $timezone)
    {
        $this->db = $db;
        $this->timezone = $timezone;
    }

    /**
     * Create a new API key
     *
     * @param array $data API key data (key_name, key_value, description, enabled)
     * @return int API key ID
     * @throws \PDOException On database error (including duplicate key_value)
     */
    public function create(array $data): int
    {
        $sql = "
            INSERT INTO api_keys (key_name, key_value, description, enabled)
            VALUES (?, ?, ?, ?)
        ";

        $this->db->execute($sql, [
            $data['key_name'],
            $data['key_value'],
            $data['description'] ?? null,
            $data['enabled'] ?? 1,
        ]);

        return $this->db->getLastInsertId();
    }

    /**
     * Find API key by ID
     *
     * @param int $id API key ID
     * @return array|null API key data or null if not found
     */
    public function findById(int $id): ?array
    {
        $sql = "SELECT * FROM api_keys WHERE id = ?";
        return $this->db->querySingle($sql, [$id]);
    }

    /**
     * Find API key by key value
     *
     * @param string $keyValue API key value
     * @return array|null API key data or null if not found
     */
    public function findByKeyValue(string $keyValue): ?array
    {
        $sql = "SELECT * FROM api_keys WHERE key_value = ?";
        return $this->db->querySingle($sql, [$keyValue]);
    }

    /**
     * Get all API keys
     *
     * @return array Array of API keys
     */
    public function findAll(): array
    {
        $sql = "SELECT * FROM api_keys ORDER BY created_at DESC";
        return $this->db->query($sql);
    }

    /**
     * Get all enabled API keys
     *
     * @return array Array of enabled API keys
     */
    public function findEnabled(): array
    {
        $sql = "SELECT * FROM api_keys WHERE enabled = 1 ORDER BY created_at DESC";
        return $this->db->query($sql);
    }

    /**
     * Update API key
     *
     * @param int $id API key ID
     * @param array $data Fields to update
     * @return bool Success status
     */
    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = [];

        foreach ($data as $field => $value) {
            $fields[] = "{$field} = ?";
            $params[] = $value;
        }

        $params[] = $id;

        $sql = "UPDATE api_keys SET " . implode(', ', $fields) . " WHERE id = ?";

        $this->db->execute($sql, $params);

        return $this->findById($id) !== null;
    }

    /**
     * Delete API key
     *
     * @param int $id API key ID
     * @return bool Success status (false if record didn't exist)
     */
    public function delete(int $id): bool
    {
        // Check if the record exists first
        if ($this->findById($id) === null) {
            return false;
        }

        $sql = "DELETE FROM api_keys WHERE id = ?";

        $this->db->execute($sql, [$id]);

        return $this->findById($id) === null;
    }

    /**
     * Update last used timestamp
     *
     * @param int $id API key ID
     * @return bool Success status
     */
    public function updateLastUsedAt(int $id): bool
    {
        $sql = "UPDATE api_keys SET last_used_at = ? WHERE id = ?";

        $this->db->execute($sql, [
            $this->timezone->nowUtc(),
            $id,
        ]);

        return true;
    }

    /**
     * Validate API key (check if exists and enabled)
     *
     * @param string $keyValue API key value
     * @return bool True if valid and enabled, false otherwise
     */
    public function validateApiKey(string $keyValue): bool
    {
        $key = $this->findByKeyValue($keyValue);

        if ($key === null) {
            return false;
        }

        if ($key['enabled'] != 1) {
            return false;
        }

        // Update last used timestamp
        $this->updateLastUsedAt($key['id']);

        return true;
    }
}
