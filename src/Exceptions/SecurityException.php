<?php

declare(strict_types=1);

namespace Unfurl\Exceptions;

/**
 * SecurityException
 *
 * Thrown when security violations are detected:
 * - SSRF attempts (private IP access)
 * - CSRF token validation failures
 * - Invalid URL schemes
 * - Other security policy violations
 *
 * Requirements: Section 7 of REQUIREMENTS.md
 */
class SecurityException extends \Exception
{
    /**
     * Create a new security exception
     *
     * @param string $message Error message describing the security violation
     * @param int $code HTTP status code (default 403 Forbidden)
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
