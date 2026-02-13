<?php

declare(strict_types=1);

namespace Unfurl\Security;

use Unfurl\Exceptions\SecurityException;

/**
 * CsrfToken - CSRF Protection
 *
 * Provides Cross-Site Request Forgery protection through secure token generation
 * and validation.
 *
 * Security Features:
 * - Cryptographically secure random tokens (random_bytes)
 * - Timing-attack safe validation (hash_equals)
 * - Automatic token regeneration after validation
 * - Session-based storage (or in-memory for testing)
 *
 * Requirements: Section 7.5 of REQUIREMENTS.md
 */
class CsrfToken
{
    /**
     * Session key for storing CSRF token
     */
    private const SESSION_KEY = 'csrf_token';

    /**
     * Token length in bytes (will be 64 hex characters)
     */
    private const TOKEN_BYTES = 32;

    /**
     * Test mode flag - when true, uses in-memory storage instead of sessions
     */
    private static bool $testMode = false;

    /**
     * In-memory token storage for test mode
     */
    private static array $testTokens = [];

    /**
     * Enable test mode (uses in-memory storage instead of PHP sessions)
     *
     * This is designed for PHPUnit tests where session_start() can cause issues.
     */
    public static function enableTestMode(): void
    {
        self::$testMode = true;
        self::$testTokens = [];
    }

    /**
     * Disable test mode (returns to normal session-based storage)
     */
    public static function disableTestMode(): void
    {
        self::$testMode = false;
        self::$testTokens = [];
    }

    /**
     * Check if test mode is enabled
     *
     * This allows other components (like controllers) to check if they should
     * avoid calling session_start() in test mode.
     *
     * @return bool True if test mode is enabled
     */
    public static function isTestMode(): bool
    {
        return self::$testMode;
    }

    /**
     * Constructor - ensures session is started (unless in test mode)
     */
    public function __construct()
    {
        if (!self::$testMode && session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Generate a new CSRF token
     *
     * Creates a cryptographically secure random token and stores it in the session
     * (or in-memory storage if in test mode).
     *
     * @return string The generated token (64 hex characters)
     */
    public function generate(): string
    {
        $token = bin2hex(random_bytes(self::TOKEN_BYTES));

        if (self::$testMode) {
            self::$testTokens[self::SESSION_KEY] = $token;
        } else {
            $_SESSION[self::SESSION_KEY] = $token;
        }

        return $token;
    }

    /**
     * Get the current CSRF token
     *
     * Returns existing token or generates a new one if none exists.
     *
     * @return string The current token
     */
    public function getToken(): string
    {
        $stored = self::$testMode
            ? (self::$testTokens[self::SESSION_KEY] ?? '')
            : ($_SESSION[self::SESSION_KEY] ?? '');

        if (empty($stored)) {
            return $this->generate();
        }

        return $stored;
    }

    /**
     * Validate a CSRF token
     *
     * Uses timing-attack safe comparison (hash_equals).
     * Automatically regenerates token after successful validation.
     *
     * @param string|null $provided The token to validate
     * @throws SecurityException If validation fails
     */
    public function validate(?string $provided): void
    {
        $expected = self::$testMode
            ? (self::$testTokens[self::SESSION_KEY] ?? '')
            : ($_SESSION[self::SESSION_KEY] ?? '');

        // Ensure both values are strings and not empty
        if (empty($expected) || empty($provided)) {
            throw new SecurityException('CSRF token validation failed');
        }

        // Use hash_equals for timing-attack safe comparison
        if (!hash_equals($expected, $provided)) {
            throw new SecurityException('CSRF token validation failed');
        }

        // Regenerate token after successful validation (prevent replay attacks)
        $this->generate();
    }

    /**
     * Validate CSRF token from POST data
     *
     * Convenience method for form submissions.
     *
     * @throws SecurityException If validation fails
     */
    public function validateFromPost(): void
    {
        $provided = $_POST[self::SESSION_KEY] ?? null;
        $this->validate($provided);
    }

    /**
     * Regenerate the CSRF token
     *
     * Creates a new token and invalidates the old one.
     *
     * @return string The new token
     */
    public function regenerate(): string
    {
        return $this->generate();
    }

    /**
     * Generate HTML hidden input field with CSRF token
     *
     * Use this in forms to include the CSRF token.
     *
     * Example:
     * ```php
     * <form method="POST">
     *     <?= $csrf->field() ?>
     *     <button type="submit">Submit</button>
     * </form>
     * ```
     *
     * @return string HTML input field
     */
    public function field(): string
    {
        $token = $this->getToken();
        $escaped = htmlspecialchars($token, ENT_QUOTES, 'UTF-8');

        return '<input type="hidden" name="' . self::SESSION_KEY . '" value="' . $escaped . '">';
    }
}
