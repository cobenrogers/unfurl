<?php

declare(strict_types=1);

namespace Tests\Unit\Exceptions;

use PHPUnit\Framework\TestCase;
use Unfurl\Exceptions\ValidationException;

/**
 * Tests for ValidationException
 *
 * TDD: Test written BEFORE implementation
 * Requirements: Section 7.6 of REQUIREMENTS.md
 */
class ValidationExceptionTest extends TestCase
{
    public function test_exception_can_be_thrown(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Validation failed');

        throw new ValidationException('Validation failed');
    }

    public function test_exception_extends_exception(): void
    {
        $exception = new ValidationException('Test');

        $this->assertInstanceOf(\Exception::class, $exception);
    }

    public function test_exception_stores_validation_errors(): void
    {
        $errors = [
            'topic' => 'Topic name is required',
            'url' => 'Invalid URL format'
        ];

        $exception = new ValidationException('Validation failed', $errors);

        $this->assertEquals($errors, $exception->getErrors());
    }

    public function test_exception_returns_empty_array_when_no_errors(): void
    {
        $exception = new ValidationException('Validation failed');

        $this->assertEquals([], $exception->getErrors());
    }

    public function test_exception_preserves_message_with_errors(): void
    {
        $message = 'Multiple validation errors';
        $errors = ['field' => 'error'];

        $exception = new ValidationException($message, $errors);

        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals($errors, $exception->getErrors());
    }

    public function test_exception_preserves_code_and_previous(): void
    {
        $previous = new \Exception('Database error');
        $exception = new ValidationException('Validation failed', [], 422, $previous);

        $this->assertEquals(422, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }
}
