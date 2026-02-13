<?php

declare(strict_types=1);

namespace Unfurl\Exceptions;

/**
 * UrlDecodeException
 *
 * Thrown when Google News URL decoding fails:
 * - Invalid URL format
 * - Base64 decode errors
 * - HTTP request failures
 * - Timeout errors
 * - Empty or malformed decoded URLs
 */
class UrlDecodeException extends \Exception
{
    /**
     * Create a new URL decode exception
     *
     * @param string $message Error message describing the decode failure
     * @param int $code HTTP status code or error code
     * @param \Throwable|null $previous Previous exception for chaining
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
