<?php

declare(strict_types=1);

namespace Unfurl\Core;

use PDO;
use PDOException;

/**
 * Database Connection and Query Wrapper
 *
 * Provides a PDO wrapper with prepared statements for secure database operations.
 * All queries MUST use prepared statements to prevent SQL injection.
 */
class Database
{
    private PDO $pdo;
    private array $config;

    /**
     * Initialize database connection
     *
     * @param array $config Configuration array with database settings
     * @throws PDOException If connection fails
     */
    public function __construct(array $config)
    {
        $this->config = $config['database'];
        $this->connect();
    }

    /**
     * Establish PDO connection
     *
     * @throws PDOException If connection fails
     */
    private function connect(): void
    {
        $host = $this->config['host'];
        $dbname = $this->config['name'];
        $charset = $this->config['charset'] ?? 'utf8mb4';

        // Handle SQLite for testing
        if ($dbname === ':memory:') {
            $dsn = 'sqlite::memory:';
        } else {
            $dsn = "mysql:host={$host};dbname={$dbname};charset={$charset}";
        }

        $this->pdo = new PDO(
            $dsn,
            $this->config['user'] ?? '',
            $this->config['pass'] ?? '',
            $this->config['options'] ?? []
        );

        // Set MySQL timezone to UTC for consistent timestamp storage
        // SQLite doesn't support SET timezone, so only run for MySQL
        if ($dbname !== ':memory:') {
            $this->pdo->exec("SET time_zone = '+00:00'");
        }
    }

    /**
     * Get the PDO connection instance
     *
     * @return PDO
     */
    public function getConnection(): PDO
    {
        return $this->pdo;
    }

    /**
     * Execute a query with prepared statement (INSERT, UPDATE, DELETE)
     *
     * @param string $sql SQL query with placeholders
     * @param array $params Parameters to bind
     * @return bool Success status
     * @throws PDOException On query error
     */
    public function execute(string $sql, array $params = []): bool
    {
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Query database and return all matching rows
     *
     * @param string $sql SQL query with placeholders
     * @param array $params Parameters to bind
     * @return array Array of rows
     * @throws PDOException On query error
     */
    public function query(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Query database and return single row
     *
     * @param string $sql SQL query with placeholders
     * @param array $params Parameters to bind
     * @return array|null Single row or null if not found
     * @throws PDOException On query error
     */
    public function querySingle(string $sql, array $params = []): ?array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();

        return $result === false ? null : $result;
    }

    /**
     * Get last inserted ID
     *
     * @return int Last insert ID
     */
    public function getLastInsertId(): int
    {
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Begin transaction
     *
     * @return bool Success status
     */
    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    /**
     * Commit transaction
     *
     * @return bool Success status
     */
    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    /**
     * Rollback transaction
     *
     * @return bool Success status
     */
    public function rollback(): bool
    {
        return $this->pdo->rollBack();
    }
}
