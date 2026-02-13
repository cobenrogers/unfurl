<?php

declare(strict_types=1);

namespace Unfurl\Repositories;

use Unfurl\Core\Database;
use Unfurl\Core\TimezoneHelper;

/**
 * Log Repository
 *
 * Handles database operations for application logs.
 */
class LogRepository
{
    private Database $db;
    private TimezoneHelper $timezone;

    public function __construct(Database $db, TimezoneHelper $timezone)
    {
        $this->db = $db;
        $this->timezone = $timezone;
    }

    /**
     * Create a new log entry
     *
     * @param array $data Log data
     * @return int Log ID
     */
    public function create(array $data): int
    {
        $sql = "INSERT INTO logs (log_type, log_level, message, context, ip_address, user_agent, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?)";

        $params = [
            $data['log_type'] ?? 'system',
            $data['log_level'] ?? 'INFO',
            $data['message'] ?? '',
            isset($data['context']) ? json_encode($data['context']) : null,
            $data['ip_address'] ?? null,
            $data['user_agent'] ?? null,
            $this->timezone->nowUtc(),
        ];

        $this->db->execute($sql, $params);
        return $this->db->getLastInsertId();
    }

    /**
     * Find log by ID
     *
     * @param int $id
     * @return array|null
     */
    public function findById(int $id): ?array
    {
        $sql = "SELECT * FROM logs WHERE id = ?";
        $result = $this->db->querySingle($sql, [$id]);

        if (!$result) {
            return null;
        }

        return $this->formatLog($result);
    }

    /**
     * Find logs with filters and pagination
     *
     * @param array $filters Filters (log_type, log_level, search, date_from, date_to)
     * @param int $limit Results per page
     * @param int $offset Starting offset
     * @return array Array of logs
     */
    public function findWithFilters(array $filters = [], int $limit = 20, int $offset = 0): array
    {
        $where = [];
        $params = [];

        // Filter by log_type
        if (!empty($filters['log_type'])) {
            $where[] = "log_type = ?";
            $params[] = $filters['log_type'];
        }

        // Filter by log_level
        if (!empty($filters['log_level'])) {
            $where[] = "log_level = ?";
            $params[] = $filters['log_level'];
        }

        // Search in message
        if (!empty($filters['search'])) {
            $where[] = "message LIKE ?";
            $params[] = '%' . $filters['search'] . '%';
        }

        // Date range filtering
        if (!empty($filters['date_from'])) {
            $where[] = "created_at >= ?";
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = "created_at <= ?";
            $params[] = $filters['date_to'];
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT * FROM logs
                {$whereClause}
                ORDER BY created_at DESC, id DESC
                LIMIT ? OFFSET ?";

        $params[] = $limit;
        $params[] = $offset;

        $results = $this->db->query($sql, $params);

        return array_map([$this, 'formatLog'], $results);
    }

    /**
     * Count logs with filters
     *
     * @param array $filters Same filters as findWithFilters
     * @return int Total count
     */
    public function countWithFilters(array $filters = []): int
    {
        $where = [];
        $params = [];

        // Filter by log_type
        if (!empty($filters['log_type'])) {
            $where[] = "log_type = ?";
            $params[] = $filters['log_type'];
        }

        // Filter by log_level
        if (!empty($filters['log_level'])) {
            $where[] = "log_level = ?";
            $params[] = $filters['log_level'];
        }

        // Search in message
        if (!empty($filters['search'])) {
            $where[] = "message LIKE ?";
            $params[] = '%' . $filters['search'] . '%';
        }

        // Date range filtering
        if (!empty($filters['date_from'])) {
            $where[] = "created_at >= ?";
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = "created_at <= ?";
            $params[] = $filters['date_to'];
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT COUNT(*) as count FROM logs {$whereClause}";
        $result = $this->db->querySingle($sql, $params);

        return (int)($result['count'] ?? 0);
    }

    /**
     * Format a log entry for display
     *
     * @param array $log Raw log data
     * @return array Formatted log data
     */
    private function formatLog(array $log): array
    {
        // Decode JSON context
        if (isset($log['context']) && is_string($log['context'])) {
            $log['context'] = json_decode($log['context'], true);
        }

        // Convert created_at to local timezone for display
        if (isset($log['created_at'])) {
            $log['created_at_local'] = $this->timezone->formatLocal($log['created_at']);
        }

        return $log;
    }
}
