<?php

declare(strict_types=1);

namespace Unfurl\Core;

/**
 * PSR-3 Logger Interface (Simplified)
 *
 * This is a simplified version of Psr\Log\LoggerInterface for compatibility
 * when the PSR-3 package is not installed.
 *
 * @see https://www.php-fig.org/psr/psr-3/
 */
interface LoggerInterface
{
    /**
     * System is unusable
     *
     * @param string|\Stringable $message
     * @param array $context
     * @return void
     */
    public function emergency(string|\Stringable $message, array $context = []): void;

    /**
     * Action must be taken immediately
     *
     * @param string|\Stringable $message
     * @param array $context
     * @return void
     */
    public function alert(string|\Stringable $message, array $context = []): void;

    /**
     * Critical conditions
     *
     * @param string|\Stringable $message
     * @param array $context
     * @return void
     */
    public function critical(string|\Stringable $message, array $context = []): void;

    /**
     * Runtime errors
     *
     * @param string|\Stringable $message
     * @param array $context
     * @return void
     */
    public function error(string|\Stringable $message, array $context = []): void;

    /**
     * Warning conditions
     *
     * @param string|\Stringable $message
     * @param array $context
     * @return void
     */
    public function warning(string|\Stringable $message, array $context = []): void;

    /**
     * Normal but significant conditions
     *
     * @param string|\Stringable $message
     * @param array $context
     * @return void
     */
    public function notice(string|\Stringable $message, array $context = []): void;

    /**
     * Informational messages
     *
     * @param string|\Stringable $message
     * @param array $context
     * @return void
     */
    public function info(string|\Stringable $message, array $context = []): void;

    /**
     * Debug-level messages
     *
     * @param string|\Stringable $message
     * @param array $context
     * @return void
     */
    public function debug(string|\Stringable $message, array $context = []): void;

    /**
     * Log a message at the given level
     *
     * @param string|int $level
     * @param string|\Stringable $message
     * @param array $context
     * @return void
     */
    public function log($level, string|\Stringable $message, array $context = []): void;
}
