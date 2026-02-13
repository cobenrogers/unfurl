<?php

declare(strict_types=1);

namespace Unfurl\Core;

/**
 * PSR-3 Compatible Logger
 *
 * Provides file-based and database logging with structured JSON context,
 * log level filtering, and support for multiple categories.
 *
 * Implements PSR-3 LoggerInterface
 */
class Logger implements LoggerInterface
{
    // Log level constants (matching PSR-3 LogLevel)
    public const EMERGENCY = 'emergency';
    public const ALERT = 'alert';
    public const CRITICAL = 'critical';
    public const ERROR = 'error';
    public const WARNING = 'warning';
    public const NOTICE = 'notice';
    public const INFO = 'info';
    public const DEBUG = 'debug';
    /**
     * Log level hierarchy (lower = more severe)
     */
    private const LOG_LEVELS = [
        self::EMERGENCY => 0,
        self::ALERT => 1,
        self::CRITICAL => 2,
        self::ERROR => 3,
        self::WARNING => 4,
        self::NOTICE => 5,
        self::INFO => 6,
        self::DEBUG => 7,
    ];

    /**
     * Directory where logs are stored
     */
    private string $logDir;

    /**
     * Minimum log level to write (compared against LOG_LEVELS)
     */
    private int $minLevel;

    /**
     * Timezone helper for timestamp conversion
     */
    private TimezoneHelper $timezone;

    /**
     * Optional database for log storage
     */
    private ?Database $db = null;

    /**
     * Initialize the logger
     *
     * @param string $logDir Directory for storing log files
     * @param string $minLevel Minimum log level to write (default: DEBUG)
     * @param TimezoneHelper|null $timezone Timezone helper (creates default if null)
     * @param Database|null $db Optional database for log storage
     *
     * @throws \Exception If log directory cannot be created
     */
    public function __construct(string $logDir, string $minLevel = self::DEBUG, ?TimezoneHelper $timezone = null, ?Database $db = null)
    {
        $this->logDir = rtrim($logDir, '/');
        $this->minLevel = self::LOG_LEVELS[$minLevel] ?? self::LOG_LEVELS[self::DEBUG];
        $this->timezone = $timezone ?? new TimezoneHelper();
        $this->db = $db;

        // Create log directory if it doesn't exist
        if (!is_dir($this->logDir)) {
            if (!mkdir($this->logDir, 0755, true)) {
                throw new \Exception("Failed to create log directory: {$this->logDir}");
            }
        }
    }

    /**
     * Log a message at the given level
     *
     * @param string|int $level
     * @param string $message
     * @param array $context
     *
     * @return void
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        // Normalize level to string
        $level = (string)$level;

        // Check if level should be logged
        if (!isset(self::LOG_LEVELS[$level])) {
            throw new \InvalidArgumentException("Invalid log level: {$level}");
        }

        if (self::LOG_LEVELS[$level] > $this->minLevel) {
            return;
        }

        // Extract category from context or use 'system'
        $category = $context['category'] ?? 'system';
        unset($context['category']);

        // Interpolate message with context
        $message = $this->interpolate((string)$message, $context);

        // Format log entry with local timezone for readability
        $timestamp = $this->timezone->formatLocal($this->timezone->nowUtc());
        $levelUpper = strtoupper($level);
        $contextJson = !empty($context) ? ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '';

        $logEntry = "[$timestamp] [$levelUpper] [$category] $message{$contextJson}\n";

        // Write to log file
        $this->writeToFile($category, $logEntry);

        // Write to database if available
        $this->writeToDatabase($category, $levelUpper, $message, $context);
    }

    /**
     * Interpolate context values into message
     *
     * @param string $message
     * @param array $context
     *
     * @return string
     */
    private function interpolate(string $message, array $context): string
    {
        $replace = [];

        foreach ($context as $key => $value) {
            $placeholder = '{' . $key . '}';

            // Skip complex types for interpolation
            if (is_string($value) || is_int($value) || is_float($value)) {
                $replace[$placeholder] = $value;
            } elseif (is_bool($value)) {
                $replace[$placeholder] = $value ? 'true' : 'false';
            } elseif ($value === null) {
                $replace[$placeholder] = 'null';
            }
        }

        return strtr($message, $replace);
    }

    /**
     * Write log entry to file
     *
     * @param string $category Log category
     * @param string $entry Log entry line
     *
     * @return void
     */
    private function writeToFile(string $category, string $entry): void
    {
        $date = date('Y-m-d');
        $filename = "{$this->logDir}/{$category}-{$date}.log";

        // Append to log file
        if (file_put_contents($filename, $entry, FILE_APPEND | LOCK_EX) === false) {
            trigger_error("Failed to write to log file: {$filename}", E_USER_WARNING);
        }
    }

    /**
     * Map category to valid log_type ENUM value
     *
     * @param string $category Original category
     * @return string Valid log_type value
     */
    private function mapCategoryToLogType(string $category): string
    {
        // Map various categories to valid log_type ENUM values
        $mapping = [
            'processing_queue' => 'processing',
            'article_controller' => 'user',
            'feed_controller' => 'feed',
            'settings_controller' => 'user',
            'api_controller' => 'api',
            'unfurl_service' => 'processing',
            'rss_generator' => 'feed',
        ];

        // If category matches a mapping, use it
        if (isset($mapping[$category])) {
            return $mapping[$category];
        }

        // If category is already a valid log_type, use it
        $validTypes = ['processing', 'user', 'feed', 'api', 'system'];
        if (in_array($category, $validTypes)) {
            return $category;
        }

        // Default to 'system' for unknown categories
        return 'system';
    }

    /**
     * Write log entry to database
     *
     * @param string $category Log category (maps to log_type)
     * @param string $level Log level (uppercase)
     * @param string $message Log message
     * @param array $context Additional context data
     *
     * @return void
     */
    private function writeToDatabase(string $category, string $level, string $message, array $context): void
    {
        // Skip database logging if no database connection
        if (!$this->db) {
            return;
        }

        try {
            // Get client IP and user agent
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

            // Map category to valid log_type
            $logType = $this->mapCategoryToLogType($category);

            // Add original category to context if it was mapped
            if ($logType !== $category) {
                $context['original_category'] = $category;
            }

            // Prepare log data
            $logData = [
                'log_type' => $logType,
                'log_level' => $level,
                'message' => substr($message, 0, 500), // Truncate to fit VARCHAR(500)
                'context' => !empty($context) ? $context : null,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent ? substr($userAgent, 0, 255) : null,
            ];

            // Insert into database
            $sql = "INSERT INTO logs (log_type, log_level, message, context, ip_address, user_agent, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?)";

            $params = [
                $logData['log_type'],
                $logData['log_level'],
                $logData['message'],
                $logData['context'] ? json_encode($logData['context'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
                $logData['ip_address'],
                $logData['user_agent'],
                $this->timezone->nowUtc(),
            ];

            $this->db->execute($sql, $params);
        } catch (\Exception $e) {
            // Don't let logging failures break the application
            // Silently fail or log to file only
            trigger_error("Failed to write to log database: {$e->getMessage()}", E_USER_WARNING);
        }
    }

    /**
     * PSR-3 Convenience Methods
     */

    /**
     * System is unusable
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function emergency(string|\Stringable $message, array $context = []): void
    {
        $this->log(self::EMERGENCY, $message, $context);
    }

    /**
     * Action must be taken immediately
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function alert(string|\Stringable $message, array $context = []): void
    {
        $this->log(self::ALERT, $message, $context);
    }

    /**
     * Critical conditions
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function critical(string|\Stringable $message, array $context = []): void
    {
        $this->log(self::CRITICAL, $message, $context);
    }

    /**
     * Runtime errors
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function error(string|\Stringable $message, array $context = []): void
    {
        $this->log(self::ERROR, $message, $context);
    }

    /**
     * Warning conditions
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function warning(string|\Stringable $message, array $context = []): void
    {
        $this->log(self::WARNING, $message, $context);
    }

    /**
     * Normal but significant conditions
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function notice(string|\Stringable $message, array $context = []): void
    {
        $this->log(self::NOTICE, $message, $context);
    }

    /**
     * Informational messages
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function info(string|\Stringable $message, array $context = []): void
    {
        $this->log(self::INFO, $message, $context);
    }

    /**
     * Debug-level messages
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function debug(string|\Stringable $message, array $context = []): void
    {
        $this->log(self::DEBUG, $message, $context);
    }
}
