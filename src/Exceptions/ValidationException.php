<?php

declare(strict_types=1);

namespace Unfurl\Exceptions;

/**
 * ValidationException
 *
 * Thrown when input validation fails.
 * Stores structured error messages for each field.
 *
 * Requirements: Section 7.6 of REQUIREMENTS.md
 */
class ValidationException extends \Exception
{
    /**
     * @var array<string, string> Field-level validation errors
     */
    private array $errors = [];

    /**
     * Create a new validation exception
     *
     * @param string $message General error message
     * @param array<string, string> $errors Field-specific error messages
     * @param int $code HTTP status code (default 422 Unprocessable Entity)
     * @param \Throwable|null $previous Previous exception for chaining
     */
    public function __construct(
        string $message = 'Validation failed',
        array $errors = [],
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->errors = $errors;
    }

    /**
     * Get all validation errors
     *
     * @return array<string, string> Field => error message pairs
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
