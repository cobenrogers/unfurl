<?php

declare(strict_types=1);

namespace Unfurl\Security;

use Unfurl\Exceptions\ValidationException;

/**
 * InputValidator - Input Validation
 *
 * Validates user input using whitelist approach.
 * Prevents SQL injection, XSS, and other injection attacks.
 *
 * Security Features:
 * - Whitelist validation (not blacklist)
 * - Structured error messages
 * - Type enforcement
 * - Pattern matching
 *
 * Requirements: Section 7.6 of REQUIREMENTS.md
 */
class InputValidator
{
    /**
     * Validate feed creation/update data
     *
     * @param array<string, mixed> $data Feed data to validate
     * @return array<string, mixed> Validated and sanitized data
     * @throws ValidationException If validation fails
     */
    public function validateFeed(array $data): array
    {
        $errors = [];

        // Validate topic
        try {
            $data['topic'] = $this->validateString(
                $data['topic'] ?? '',
                1,
                255,
                'topic',
                '/^[a-zA-Z0-9\s\-_]+$/'
            );
        } catch (ValidationException $e) {
            $errors = array_merge($errors, $e->getErrors());
        }

        // Validate URL
        try {
            $data['url'] = $this->validateUrl(
                $data['url'] ?? '',
                'url',
                ['google.com']
            );
        } catch (ValidationException $e) {
            $errors = array_merge($errors, $e->getErrors());
        }

        // Validate limit
        try {
            $data['limit'] = $this->validateInteger(
                $data['limit'] ?? null,
                1,
                100,
                'limit'
            );
        } catch (ValidationException $e) {
            $errors = array_merge($errors, $e->getErrors());
        }

        // Validate enabled (checkbox)
        $data['enabled'] = isset($data['enabled']) && $data['enabled'] === '1';

        // Throw if any errors
        if (!empty($errors)) {
            throw new ValidationException('Validation failed', $errors);
        }

        return $data;
    }

    /**
     * Validate a string field
     *
     * @param mixed $value Value to validate
     * @param int $minLength Minimum length
     * @param int $maxLength Maximum length
     * @param string $fieldName Field name for error messages
     * @param string|null $pattern Optional regex pattern
     * @return string Validated string
     * @throws ValidationException If validation fails
     */
    public function validateString(
        mixed $value,
        int $minLength,
        int $maxLength,
        string $fieldName = 'field',
        ?string $pattern = null
    ): string {
        $errors = [];

        // Convert to string and trim
        $value = trim((string)$value);

        // Check required
        if (empty($value)) {
            $errors[$fieldName] = ucfirst($fieldName) . ' is required';
            throw new ValidationException('Validation failed', $errors);
        }

        // Check length
        $length = strlen($value);
        if ($length < $minLength) {
            $errors[$fieldName] = ucfirst($fieldName) . " must be at least $minLength characters";
        }
        if ($length > $maxLength) {
            $errors[$fieldName] = ucfirst($fieldName) . " too long (max $maxLength characters)";
        }

        // Check pattern
        if ($pattern !== null && !preg_match($pattern, $value)) {
            $errors[$fieldName] = ucfirst($fieldName) . ' contains invalid characters';
        }

        if (!empty($errors)) {
            throw new ValidationException('Validation failed', $errors);
        }

        return $value;
    }

    /**
     * Validate an integer field
     *
     * @param mixed $value Value to validate
     * @param int $min Minimum value
     * @param int $max Maximum value
     * @param string $fieldName Field name for error messages
     * @return int Validated integer
     * @throws ValidationException If validation fails
     */
    public function validateInteger(
        mixed $value,
        int $min,
        int $max,
        string $fieldName = 'field'
    ): int {
        $errors = [];

        // Check if value is provided
        if (!isset($value)) {
            $errors[$fieldName] = ucfirst($fieldName) . ' is required';
            throw new ValidationException('Validation failed', $errors);
        }

        // Check if numeric
        if (!is_numeric($value)) {
            $errors[$fieldName] = ucfirst($fieldName) . ' must be a number';
            throw new ValidationException('Validation failed', $errors);
        }

        // Convert to integer
        $intValue = (int)$value;

        // Check range
        if ($intValue < $min || $intValue > $max) {
            $errors[$fieldName] = ucfirst($fieldName) . " must be between $min and $max";
            throw new ValidationException('Validation failed', $errors);
        }

        return $intValue;
    }

    /**
     * Validate a URL field
     *
     * @param mixed $value URL to validate
     * @param string $fieldName Field name for error messages
     * @param array<string> $allowedHosts Optional list of allowed hostnames
     * @return string Validated URL
     * @throws ValidationException If validation fails
     */
    public function validateUrl(
        mixed $value,
        string $fieldName = 'url',
        array $allowedHosts = []
    ): string {
        $errors = [];

        // Convert to string and trim
        $value = trim((string)$value);

        // Check required
        if (empty($value)) {
            $errors[$fieldName] = ucfirst($fieldName) . ' is required';
            throw new ValidationException('Validation failed', $errors);
        }

        // Validate URL format
        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            $errors[$fieldName] = 'Invalid URL format';
            throw new ValidationException('Validation failed', $errors);
        }

        // Check allowed hosts if specified
        if (!empty($allowedHosts)) {
            $host = parse_url($value, PHP_URL_HOST);

            $isAllowed = false;
            foreach ($allowedHosts as $allowedHost) {
                // Must be exact match or subdomain (e.g., news.google.com)
                if ($host === $allowedHost || str_ends_with($host, '.' . $allowedHost)) {
                    $isAllowed = true;
                    break;
                }
            }

            if (!$isAllowed) {
                $errors[$fieldName] = 'Must be a Google News URL';
                throw new ValidationException('Validation failed', $errors);
            }
        }

        return $value;
    }
}
