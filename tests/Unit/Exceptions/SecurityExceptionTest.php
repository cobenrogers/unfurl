<?php

declare(strict_types=1);

namespace Tests\Unit\Exceptions;

use PHPUnit\Framework\TestCase;
use Unfurl\Exceptions\SecurityException;

/**
 * Tests for SecurityException
 *
 * TDD: Test written BEFORE implementation
 * Requirements: Section 7.3+ of REQUIREMENTS.md
 */
class SecurityExceptionTest extends TestCase
{
    public function test_exception_can_be_thrown(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Security violation');

        throw new SecurityException('Security violation');
    }

    public function test_exception_extends_exception(): void
    {
        $exception = new SecurityException('Test');

        $this->assertInstanceOf(\Exception::class, $exception);
    }

    public function test_exception_preserves_message(): void
    {
        $message = 'SSRF attempt blocked';
        $exception = new SecurityException($message);

        $this->assertEquals($message, $exception->getMessage());
    }

    public function test_exception_preserves_code(): void
    {
        $exception = new SecurityException('Test', 403);

        $this->assertEquals(403, $exception->getCode());
    }

    public function test_exception_preserves_previous_exception(): void
    {
        $previous = new \Exception('Previous error');
        $exception = new SecurityException('Security error', 0, $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }
}
