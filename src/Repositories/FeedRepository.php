<?php

declare(strict_types=1);

namespace Unfurl\Repositories;

use Unfurl\Core\Database;
use Unfurl\Core\TimezoneHelper;

/**
 * Feed Repository
 *
 * Handles all database operations for feeds table.
 * All queries use prepared statements for security.
 */
class FeedRepository
{
    private Database $db;
    private TimezoneHelper $timezone;

    public function __construct(Database $db, TimezoneHelper $timezone)
    {
        $this->db = $db;
        $this->timezone = $timezone;
    }

    /**
     * Create a new feed
     *
     * @param array $data Feed data (topic, url, result_limit, enabled)
     * @return int Feed ID
     * @throws \PDOException On database error (including duplicate topic)
     */
    public function create(array $data): int
    {
        $sql = "
            INSERT INTO feeds (topic, url, result_limit, enabled)
            VALUES (?, ?, ?, ?)
        ";

        $this->db->execute($sql, [
            $data['topic'],
            $data['url'],
            $data['result_limit'] ?? 10,
            $data['enabled'] ?? 1,
        ]);

        return $this->db->getLastInsertId();
    }

    /**
     * Find feed by ID
     *
     * @param int $id Feed ID
     * @return array|null Feed data or null if not found
     */
    public function findById(int $id): ?array
    {
        $sql = "SELECT * FROM feeds WHERE id = ?";
        return $this->db->querySingle($sql, [$id]);
    }

    /**
     * Find feed by topic
     *
     * @param string $topic Feed topic
     * @return array|null Feed data or null if not found
     */
    public function findByTopic(string $topic): ?array
    {
        $sql = "SELECT * FROM feeds WHERE topic = ?";
        return $this->db->querySingle($sql, [$topic]);
    }

    /**
     * Get all feeds
     *
     * @return array Array of feeds
     */
    public function findAll(): array
    {
        $sql = "SELECT * FROM feeds ORDER BY created_at DESC";
        return $this->db->query($sql);
    }

    /**
     * Get all enabled feeds
     *
     * @return array Array of enabled feeds
     */
    public function findEnabled(): array
    {
        $sql = "SELECT * FROM feeds WHERE enabled = 1 ORDER BY created_at DESC";
        return $this->db->query($sql);
    }

    /**
     * Update feed
     *
     * @param int $id Feed ID
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

        $sql = "UPDATE feeds SET " . implode(', ', $fields) . " WHERE id = ?";

        $this->db->execute($sql, $params);

        // Check if any rows were affected
        return $this->findById($id) !== null;
    }

    /**
     * Delete feed
     *
     * @param int $id Feed ID
     * @return bool Success status (false if record didn't exist)
     */
    public function delete(int $id): bool
    {
        // Check if the record exists first
        if ($this->findById($id) === null) {
            return false;
        }

        try {
            // Delete associated articles first (due to foreign key constraint)
            $sqlArticles = "DELETE FROM articles WHERE feed_id = ?";
            $this->db->execute($sqlArticles, [$id]);

            // Now delete the feed
            $sqlFeed = "DELETE FROM feeds WHERE id = ?";
            $this->db->execute($sqlFeed, [$id]);

            // Check if feed was deleted
            return $this->findById($id) === null;
        } catch (\PDOException $e) {
            // Log the error but don't expose it
            error_log("Failed to delete feed {$id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update last processed timestamp
     *
     * @param int $id Feed ID
     * @return bool Success status
     */
    public function updateLastProcessedAt(int $id): bool
    {
        $sql = "UPDATE feeds SET last_processed_at = ? WHERE id = ?";

        $this->db->execute($sql, [
            $this->timezone->nowUtc(),
            $id,
        ]);

        return true;
    }
}
