<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use PHPUnit\Framework\TestCase;
use Unfurl\Security\CsrfToken;
use Unfurl\Exceptions\SecurityException;

/**
 * Tests for CsrfToken - CSRF Protection
 *
 * TDD: Test written BEFORE implementation
 * Requirements: Section 7.5 of REQUIREMENTS.md
 *
 * Critical Security Requirements:
 * - Generate cryptographically secure tokens (random_bytes)
 * - Validate tokens with hash_equals (timing-attack safe)
 * - Regenerate tokens after successful validation
 * - Store tokens in session
 */
class CsrfTokenTest extends TestCase
{
    private CsrfToken $csrf;

    protected function setUp(): void
    {
        // Clear session before each test
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        // Start fresh session
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $_SESSION = [];
        $this->csrf = new CsrfToken();
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    // ============================================
    // Token Generation
    // ============================================

    public function test_generates_token(): void
    {
        $token = $this->csrf->generate();

        $this->assertNotEmpty($token);
        $this->assertIsString($token);
    }

    public function test_generated_token_is_64_characters(): void
    {
        // random_bytes(32) -> bin2hex() = 64 hex characters
        $token = $this->csrf->generate();

        $this->assertEquals(64, strlen($token));
    }

    public function test_generated_token_is_hexadecimal(): void
    {
        $token = $this->csrf->generate();

        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
    }

    public function test_generates_different_tokens_each_time(): void
    {
        $token1 = $this->csrf->generate();
        $token2 = $this->csrf->generate();

        $this->assertNotEquals($token1, $token2);
    }

    public function test_stores_token_in_session(): void
    {
        $token = $this->csrf->generate();

        $this->assertArrayHasKey('csrf_token', $_SESSION);
        $this->assertEquals($token, $_SESSION['csrf_token']);
    }

    public function test_returns_existing_token_if_not_regenerating(): void
    {
        $token1 = $this->csrf->generate();
        $token2 = $this->csrf->getToken();

        $this->assertEquals($token1, $token2);
    }

    public function test_get_token_generates_if_none_exists(): void
    {
        unset($_SESSION['csrf_token']);

        $token = $this->csrf->getToken();

        $this->assertNotEmpty($token);
        $this->assertEquals(64, strlen($token));
    }

    // ============================================
    // Token Validation - Success Cases
    // ============================================

    public function test_validates_correct_token(): void
    {
        $token = $this->csrf->generate();

        // Should not throw exception
        $this->csrf->validate($token);
        $this->assertTrue(true);
    }

    public function test_validates_token_from_session(): void
    {
        $token = $this->csrf->generate();
        $_SESSION['csrf_token'] = $token;

        $this->csrf->validate($token);
        $this->assertTrue(true);
    }

    // ============================================
    // Token Validation - Failure Cases
    // ============================================

    public function test_rejects_empty_token(): void
    {
        $this->csrf->generate();

        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('CSRF token validation failed');

        $this->csrf->validate('');
    }

    public function test_rejects_null_token(): void
    {
        $this->csrf->generate();

        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('CSRF token validation failed');

        $this->csrf->validate(null);
    }

    public function test_rejects_incorrect_token(): void
    {
        $this->csrf->generate();

        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('CSRF token validation failed');

        $this->csrf->validate('invalid_token_12345');
    }

    public function test_rejects_modified_token(): void
    {
        $token = $this->csrf->generate();
        $modifiedToken = substr($token, 0, -1) . 'x'; // Change last character

        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('CSRF token validation failed');

        $this->csrf->validate($modifiedToken);
    }

    public function test_rejects_when_no_session_token_exists(): void
    {
        unset($_SESSION['csrf_token']);

        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('CSRF token validation failed');

        $this->csrf->validate('any_token_here');
    }

    // ============================================
    // Token Regeneration
    // ============================================

    public function test_regenerates_token_after_validation(): void
    {
        $originalToken = $this->csrf->generate();

        $this->csrf->validate($originalToken);

        $newToken = $_SESSION['csrf_token'];
        $this->assertNotEquals($originalToken, $newToken);
    }

    public function test_regenerate_creates_new_token(): void
    {
        $token1 = $this->csrf->generate();
        $token2 = $this->csrf->regenerate();

        $this->assertNotEquals($token1, $token2);
        $this->assertEquals($token2, $_SESSION['csrf_token']);
    }

    public function test_old_token_invalid_after_regeneration(): void
    {
        $oldToken = $this->csrf->generate();
        $this->csrf->regenerate();

        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('CSRF token validation failed');

        $this->csrf->validate($oldToken);
    }

    // ============================================
    // HTML Field Generation
    // ============================================

    public function test_field_returns_hidden_input(): void
    {
        $field = $this->csrf->field();

        $this->assertStringContainsString('<input', $field);
        $this->assertStringContainsString('type="hidden"', $field);
        $this->assertStringContainsString('name="csrf_token"', $field);
    }

    public function test_field_contains_current_token(): void
    {
        $token = $this->csrf->generate();
        $field = $this->csrf->field();

        $this->assertStringContainsString($token, $field);
        $this->assertStringContainsString("value=\"$token\"", $field);
    }

    public function test_field_escapes_token_value(): void
    {
        // Even though our tokens are hex and safe, ensure proper escaping
        $field = $this->csrf->field();

        // Should use htmlspecialchars/ENT_QUOTES
        $this->assertStringContainsString('value="', $field);
        $this->assertStringNotContainsString('value=\'', $field);
    }

    public function test_field_generates_token_if_none_exists(): void
    {
        unset($_SESSION['csrf_token']);

        $field = $this->csrf->field();

        $this->assertStringContainsString('<input', $field);
        $this->assertArrayHasKey('csrf_token', $_SESSION);
    }

    // ============================================
    // Timing Attack Protection
    // ============================================

    public function test_uses_timing_safe_comparison(): void
    {
        // This test verifies hash_equals is used (timing-attack safe)
        // We can't directly test timing, but we can verify behavior

        $token = $this->csrf->generate();

        // These should take similar time regardless of how many chars match
        $wrongToken1 = str_repeat('a', 64); // All wrong
        $wrongToken2 = substr($token, 0, 63) . 'x'; // Almost right

        try {
            $this->csrf->validate($wrongToken1);
            $this->fail('Should throw SecurityException');
        } catch (SecurityException $e) {
            $this->assertTrue(true);
        }

        try {
            $this->csrf->validate($wrongToken2);
            $this->fail('Should throw SecurityException');
        } catch (SecurityException $e) {
            $this->assertTrue(true);
        }
    }

    // ============================================
    // Edge Cases
    // ============================================

    public function test_handles_session_not_started(): void
    {
        // Destroy session
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        // CsrfToken should start session if needed
        $csrf = new CsrfToken();
        $token = $csrf->generate();

        $this->assertNotEmpty($token);
        $this->assertEquals(PHP_SESSION_ACTIVE, session_status());
    }

    public function test_validates_from_post_array(): void
    {
        $token = $this->csrf->generate();
        $_POST['csrf_token'] = $token;

        $this->csrf->validateFromPost();
        $this->assertTrue(true);
    }

    public function test_validate_from_post_throws_when_missing(): void
    {
        $this->csrf->generate();
        unset($_POST['csrf_token']);

        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('CSRF token validation failed');

        $this->csrf->validateFromPost();
    }

    public function test_validate_from_post_throws_when_invalid(): void
    {
        $this->csrf->generate();
        $_POST['csrf_token'] = 'invalid';

        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('CSRF token validation failed');

        $this->csrf->validateFromPost();
    }

    // ============================================
    // Integration Scenarios
    // ============================================

    public function test_full_form_submission_workflow(): void
    {
        // 1. Generate token for form display
        $token = $this->csrf->generate();

        // 2. User submits form with token
        $_POST['csrf_token'] = $token;

        // 3. Validate token on submission
        $this->csrf->validateFromPost();

        // 4. Token should be regenerated after validation
        $newToken = $_SESSION['csrf_token'];
        $this->assertNotEquals($token, $newToken);

        // 5. Old token should not work again
        $_POST['csrf_token'] = $token;

        $this->expectException(SecurityException::class);
        $this->csrf->validateFromPost();
    }

    public function test_double_submit_prevention(): void
    {
        // Prevent replay attacks - same token can't be used twice
        $token = $this->csrf->generate();

        $this->csrf->validate($token);

        // Token was regenerated, old one should fail
        $this->expectException(SecurityException::class);
        $this->csrf->validate($token);
    }
}
