<?php

declare(strict_types=1);

namespace Unfurl\Repositories;

use Unfurl\Core\Database;
use Unfurl\Core\TimezoneHelper;

/**
 * Article Repository
 *
 * Handles all database operations for articles table.
 * All queries use prepared statements for security.
 */
class ArticleRepository
{
    private Database $db;
    private TimezoneHelper $timezone;

    public function __construct(Database $db, TimezoneHelper $timezone)
    {
        $this->db = $db;
        $this->timezone = $timezone;
    }

    /**
     * Create a new article
     *
     * @param array $data Article data
     * @return int Article ID
     * @throws \PDOException On database error (including duplicate final_url)
     */
    public function create(array $data): int
    {
        $sql = "
            INSERT INTO articles (
                feed_id, topic, google_news_url, rss_title, pub_date,
                rss_description, rss_source, final_url, status,
                page_title, og_title, og_description, og_image, og_url,
                og_site_name, twitter_image, twitter_card, author,
                article_content, word_count, categories, error_message,
                retry_count, next_retry_at, last_error, processed_at,
                created_at
            ) VALUES (
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?
            )
        ";

        $this->db->execute($sql, [
            $data['feed_id'],
            $data['topic'],
            $data['google_news_url'],
            $data['rss_title'] ?? null,
            $data['pub_date'] ?? null,
            $data['rss_description'] ?? null,
            $data['rss_source'] ?? null,
            $data['final_url'] ?? null,
            $data['status'] ?? 'pending',
            $data['page_title'] ?? null,
            $data['og_title'] ?? null,
            $data['og_description'] ?? null,
            $data['og_image'] ?? null,
            $data['og_url'] ?? null,
            $data['og_site_name'] ?? null,
            $data['twitter_image'] ?? null,
            $data['twitter_card'] ?? null,
            $data['author'] ?? null,
            $data['article_content'] ?? null,
            $data['word_count'] ?? null,
            $data['categories'] ?? null,
            $data['error_message'] ?? null,
            $data['retry_count'] ?? 0,
            $data['next_retry_at'] ?? null,
            $data['last_error'] ?? null,
            $data['processed_at'] ?? null,
            $data['created_at'] ?? $this->timezone->nowUtc(),
        ]);

        return $this->db->getLastInsertId();
    }

    /**
     * Find article by ID
     *
     * @param int $id Article ID
     * @return array|null Article data or null if not found
     */
    public function findById(int $id): ?array
    {
        $sql = "SELECT * FROM articles WHERE id = ?";
        return $this->db->querySingle($sql, [$id]);
    }

    /**
     * Find articles by feed ID
     *
     * @param int $feedId Feed ID
     * @return array Array of articles
     */
    public function findByFeedId(int $feedId): array
    {
        $sql = "SELECT * FROM articles WHERE feed_id = ? ORDER BY created_at DESC";
        return $this->db->query($sql, [$feedId]);
    }

    /**
     * Find articles by status
     *
     * @param string $status Status (pending, success, failed)
     * @return array Array of articles
     */
    public function findByStatus(string $status): array
    {
        $sql = "SELECT * FROM articles WHERE status = ? ORDER BY created_at DESC";
        return $this->db->query($sql, [$status]);
    }

    /**
     * Find articles by topic
     *
     * @param string $topic Topic
     * @return array Array of articles
     */
    public function findByTopic(string $topic): array
    {
        $sql = "SELECT * FROM articles WHERE topic = ? ORDER BY created_at DESC";
        return $this->db->query($sql, [$topic]);
    }

    /**
     * Find articles pending retry
     *
     * @return array Array of articles ready for retry
     */
    public function findPendingRetries(): array
    {
        $sql = "
            SELECT * FROM articles
            WHERE status = 'failed'
            AND next_retry_at IS NOT NULL
            AND next_retry_at <= ?
            ORDER BY next_retry_at ASC
        ";

        return $this->db->query($sql, [$this->timezone->nowUtc()]);
    }

    /**
     * Update article
     *
     * @param int $id Article ID
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

        $sql = "UPDATE articles SET " . implode(', ', $fields) . " WHERE id = ?";

        $this->db->execute($sql, $params);

        return $this->findById($id) !== null;
    }

    /**
     * Delete article
     *
     * @param int $id Article ID
     * @return bool Success status
     */
    public function delete(int $id): bool
    {
        $sql = "DELETE FROM articles WHERE id = ?";

        $this->db->execute($sql, [$id]);

        return $this->findById($id) === null;
    }

    /**
     * Delete articles older than specified days
     *
     * @param int $days Number of days
     * @return int Number of articles deleted
     */
    public function deleteOlderThan(int $days): int
    {
        $sql = "DELETE FROM articles WHERE created_at < ?";
        // Calculate cutoff in UTC
        $cutoffTimestamp = strtotime("-{$days} days");
        $cutoffDate = date('Y-m-d H:i:s', $cutoffTimestamp);

        // Get count before deletion
        $countSql = "SELECT COUNT(*) as count FROM articles WHERE created_at < ?";
        $result = $this->db->querySingle($countSql, [$cutoffDate]);
        $count = $result['count'] ?? 0;

        $this->db->execute($sql, [$cutoffDate]);

        return (int) $count;
    }

    /**
     * Count articles by status
     *
     * @param string $status Status (pending, success, failed)
     * @return int Count
     */
    public function countByStatus(string $status): int
    {
        $sql = "SELECT COUNT(*) as count FROM articles WHERE status = ?";
        $result = $this->db->querySingle($sql, [$status]);

        return (int) ($result['count'] ?? 0);
    }

    /**
     * Increment retry count for article
     *
     * @param int $id Article ID
     * @return bool Success status
     */
    public function incrementRetryCount(int $id): bool
    {
        $sql = "UPDATE articles SET retry_count = retry_count + 1 WHERE id = ?";

        $this->db->execute($sql, [$id]);

        return true;
    }

    /**
     * Mark article as successfully processed
     *
     * @param int $id Article ID
     * @return bool Success status
     */
    public function markAsProcessed(int $id): bool
    {
        $sql = "
            UPDATE articles
            SET status = 'success', processed_at = ?
            WHERE id = ?
        ";

        $this->db->execute($sql, [
            $this->timezone->nowUtc(),
            $id,
        ]);

        return true;
    }

    /**
     * Find articles with filters, pagination, and search
     *
     * Supports:
     * - topic: Filter by topic
     * - status: Filter by status (pending, success, failed)
     * - date_from: Filter by pub_date >= date
     * - date_to: Filter by pub_date <= date
     * - search: Fulltext search on titles/descriptions
     *
     * @param array $filters Associative array of filters
     * @param int $limit Results per page
     * @param int $offset Skip N results
     * @return array Array of articles
     */
    public function findWithFilters(array $filters, int $limit = 20, int $offset = 0): array
    {
        $where = [];
        $params = [];

        // Feed ID filter
        if (isset($filters['feed_id']) && !empty($filters['feed_id'])) {
            $where[] = "feed_id = ?";
            $params[] = $filters['feed_id'];
        }

        // Topic filter
        if (isset($filters['topic']) && !empty($filters['topic'])) {
            $where[] = "topic = ?";
            $params[] = $filters['topic'];
        }

        // Status filter
        if (isset($filters['status']) && !empty($filters['status'])) {
            $where[] = "status = ?";
            $params[] = $filters['status'];
        }

        // Google News URL filter (for duplicate checking)
        if (isset($filters['google_news_url']) && !empty($filters['google_news_url'])) {
            $where[] = "google_news_url = ?";
            $params[] = $filters['google_news_url'];
        }

        // Date range filters
        if (isset($filters['date_from']) && !empty($filters['date_from'])) {
            $where[] = "pub_date >= ?";
            $params[] = $filters['date_from'];
        }

        if (isset($filters['date_to']) && !empty($filters['date_to'])) {
            $where[] = "pub_date <= ?";
            $params[] = $filters['date_to'];
        }

        // Fulltext search
        if (isset($filters['search']) && !empty($filters['search'])) {
            $where[] = "MATCH(rss_title, page_title, og_title, og_description, author) AGAINST(? IN NATURAL LANGUAGE MODE)";
            $params[] = $filters['search'];
        }

        // Build WHERE clause
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        // Build ORDER BY clause
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'DESC';

        // Validate sort parameters
        $allowedSortFields = ['created_at', 'pub_date', 'title', 'status', 'word_count', 'rss_title'];
        $allowedSortOrders = ['ASC', 'DESC'];

        if (!in_array($sortBy, $allowedSortFields)) {
            $sortBy = 'created_at';
        }

        if (!in_array(strtoupper($sortOrder), $allowedSortOrders)) {
            $sortOrder = 'DESC';
        }

        // Use rss_title for 'title' sort
        $sortField = $sortBy === 'title' ? 'rss_title' : $sortBy;

        // Build query with pagination
        $sql = "
            SELECT * FROM articles
            {$whereClause}
            ORDER BY {$sortField} {$sortOrder}, created_at DESC
            LIMIT ? OFFSET ?
        ";

        $params[] = $limit;
        $params[] = $offset;

        return $this->db->query($sql, $params);
    }

    /**
     * Count articles with filters
     *
     * Same filters as findWithFilters()
     *
     * @param array $filters Associative array of filters
     * @return int Count of matching articles
     */
    public function countWithFilters(array $filters): int
    {
        $where = [];
        $params = [];

        // Feed ID filter
        if (isset($filters['feed_id']) && !empty($filters['feed_id'])) {
            $where[] = "feed_id = ?";
            $params[] = $filters['feed_id'];
        }

        // Topic filter
        if (isset($filters['topic']) && !empty($filters['topic'])) {
            $where[] = "topic = ?";
            $params[] = $filters['topic'];
        }

        // Status filter
        if (isset($filters['status']) && !empty($filters['status'])) {
            $where[] = "status = ?";
            $params[] = $filters['status'];
        }

        // Google News URL filter (for duplicate checking)
        if (isset($filters['google_news_url']) && !empty($filters['google_news_url'])) {
            $where[] = "google_news_url = ?";
            $params[] = $filters['google_news_url'];
        }

        // Date range filters
        if (isset($filters['date_from']) && !empty($filters['date_from'])) {
            $where[] = "pub_date >= ?";
            $params[] = $filters['date_from'];
        }

        if (isset($filters['date_to']) && !empty($filters['date_to'])) {
            $where[] = "pub_date <= ?";
            $params[] = $filters['date_to'];
        }

        // Fulltext search
        if (isset($filters['search']) && !empty($filters['search'])) {
            $where[] = "MATCH(rss_title, page_title, og_title, og_description, author) AGAINST(? IN NATURAL LANGUAGE MODE)";
            $params[] = $filters['search'];
        }

        // Build WHERE clause
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT COUNT(*) as count FROM articles {$whereClause}";

        $result = $this->db->querySingle($sql, $params);

        return (int) ($result['count'] ?? 0);
    }
}
