<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use PHPUnit\Framework\TestCase;
use Unfurl\Security\InputValidator;
use Unfurl\Exceptions\ValidationException;

/**
 * Tests for InputValidator - Input Validation
 *
 * TDD: Test written BEFORE implementation
 * Requirements: Section 7.6 of REQUIREMENTS.md
 *
 * Critical Security Requirements:
 * - Whitelist validation, not blacklist
 * - Reject invalid/malicious input
 * - Validate feed data (topic, URL, limits)
 * - Return structured error messages
 */
class InputValidatorTest extends TestCase
{
    private InputValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new InputValidator();
    }

    // ============================================
    // Feed Data Validation - Success Cases
    // ============================================

    public function test_validates_complete_feed_data(): void
    {
        $data = [
            'topic' => 'Technology News',
            'url' => 'https://news.google.com/rss/search?q=tech',
            'limit' => 10
        ];

        $validated = $this->validator->validateFeed($data);

        $expected = $data;
        $expected['enabled'] = false; // Validator adds enabled field with default false
        $this->assertEquals($expected, $validated);
    }

    public function test_validates_topic_with_alphanumeric(): void
    {
        $data = [
            'topic' => 'Tech2024',
            'url' => 'https://news.google.com/rss',
            'limit' => 10
        ];

        $validated = $this->validator->validateFeed($data);
        $this->assertEquals('Tech2024', $validated['topic']);
    }

    public function test_validates_topic_with_spaces(): void
    {
        $data = [
            'topic' => 'AI and Machine Learning',
            'url' => 'https://news.google.com/rss',
            'limit' => 10
        ];

        $validated = $this->validator->validateFeed($data);
        $this->assertEquals('AI and Machine Learning', $validated['topic']);
    }

    public function test_validates_topic_with_hyphens_underscores(): void
    {
        $data = [
            'topic' => 'tech-news_2024',
            'url' => 'https://news.google.com/rss',
            'limit' => 10
        ];

        $validated = $this->validator->validateFeed($data);
        $this->assertEquals('tech-news_2024', $validated['topic']);
    }

    public function test_validates_google_news_url(): void
    {
        $data = [
            'topic' => 'News',
            'url' => 'https://news.google.com/rss/search?q=test&hl=en',
            'limit' => 10
        ];

        $validated = $this->validator->validateFeed($data);
        $this->assertEquals($data['url'], $validated['url']);
    }

    public function test_validates_limit_at_minimum(): void
    {
        $data = [
            'topic' => 'News',
            'url' => 'https://news.google.com/rss',
            'limit' => 1
        ];

        $validated = $this->validator->validateFeed($data);
        $this->assertEquals(1, $validated['limit']);
    }

    public function test_validates_limit_at_maximum(): void
    {
        $data = [
            'topic' => 'News',
            'url' => 'https://news.google.com/rss',
            'limit' => 100
        ];

        $validated = $this->validator->validateFeed($data);
        $this->assertEquals(100, $validated['limit']);
    }

    // ============================================
    // Topic Validation - Failure Cases
    // ============================================

    public function test_rejects_empty_topic(): void
    {
        $data = [
            'topic' => '',
            'url' => 'https://news.google.com/rss',
            'limit' => 10
        ];

        $this->expectException(ValidationException::class);

        try {
            $this->validator->validateFeed($data);
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertArrayHasKey('topic', $errors);
            $this->assertStringContainsString('required', $errors['topic']);
            throw $e;
        }
    }

    public function test_rejects_missing_topic(): void
    {
        $data = [
            'url' => 'https://news.google.com/rss',
            'limit' => 10
        ];

        $this->expectException(ValidationException::class);

        try {
            $this->validator->validateFeed($data);
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertArrayHasKey('topic', $errors);
            throw $e;
        }
    }

    public function test_rejects_topic_exceeding_255_characters(): void
    {
        $data = [
            'topic' => str_repeat('a', 256),
            'url' => 'https://news.google.com/rss',
            'limit' => 10
        ];

        $this->expectException(ValidationException::class);

        try {
            $this->validator->validateFeed($data);
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertArrayHasKey('topic', $errors);
            $this->assertStringContainsString('255', $errors['topic']);
            throw $e;
        }
    }

    public function test_rejects_topic_with_special_characters(): void
    {
        $data = [
            'topic' => 'Tech<script>alert(1)</script>',
            'url' => 'https://news.google.com/rss',
            'limit' => 10
        ];

        $this->expectException(ValidationException::class);

        try {
            $this->validator->validateFeed($data);
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertArrayHasKey('topic', $errors);
            $this->assertStringContainsString('invalid characters', $errors['topic']);
            throw $e;
        }
    }

    public function test_rejects_topic_with_html_tags(): void
    {
        $data = [
            'topic' => '<b>Bold Topic</b>',
            'url' => 'https://news.google.com/rss',
            'limit' => 10
        ];

        $this->expectException(ValidationException::class);

        try {
            $this->validator->validateFeed($data);
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertArrayHasKey('topic', $errors);
            throw $e;
        }
    }

    // ============================================
    // URL Validation - Failure Cases
    // ============================================

    public function test_rejects_empty_url(): void
    {
        $data = [
            'topic' => 'News',
            'url' => '',
            'limit' => 10
        ];

        $this->expectException(ValidationException::class);

        try {
            $this->validator->validateFeed($data);
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertArrayHasKey('url', $errors);
            $this->assertStringContainsString('required', $errors['url']);
            throw $e;
        }
    }

    public function test_rejects_invalid_url_format(): void
    {
        $data = [
            'topic' => 'News',
            'url' => 'not-a-valid-url',
            'limit' => 10
        ];

        $this->expectException(ValidationException::class);

        try {
            $this->validator->validateFeed($data);
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertArrayHasKey('url', $errors);
            $this->assertStringContainsString('Invalid URL', $errors['url']);
            throw $e;
        }
    }

    public function test_rejects_non_google_news_url(): void
    {
        $data = [
            'topic' => 'News',
            'url' => 'https://example.com/rss',
            'limit' => 10
        ];

        $this->expectException(ValidationException::class);

        try {
            $this->validator->validateFeed($data);
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertArrayHasKey('url', $errors);
            $this->assertStringContainsString('Google News', $errors['url']);
            throw $e;
        }
    }

    public function test_rejects_subdomain_that_is_not_google(): void
    {
        $data = [
            'topic' => 'News',
            'url' => 'https://fake-google.com/rss',
            'limit' => 10
        ];

        $this->expectException(ValidationException::class);

        try {
            $this->validator->validateFeed($data);
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertArrayHasKey('url', $errors);
            throw $e;
        }
    }

    // ============================================
    // Limit Validation - Failure Cases
    // ============================================

    public function test_rejects_missing_limit(): void
    {
        $data = [
            'topic' => 'News',
            'url' => 'https://news.google.com/rss'
        ];

        $this->expectException(ValidationException::class);

        try {
            $this->validator->validateFeed($data);
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertArrayHasKey('limit', $errors);
            throw $e;
        }
    }

    public function test_rejects_non_numeric_limit(): void
    {
        $data = [
            'topic' => 'News',
            'url' => 'https://news.google.com/rss',
            'limit' => 'ten'
        ];

        $this->expectException(ValidationException::class);

        try {
            $this->validator->validateFeed($data);
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertArrayHasKey('limit', $errors);
            $this->assertStringContainsString('number', $errors['limit']);
            throw $e;
        }
    }

    public function test_rejects_limit_below_minimum(): void
    {
        $data = [
            'topic' => 'News',
            'url' => 'https://news.google.com/rss',
            'limit' => 0
        ];

        $this->expectException(ValidationException::class);

        try {
            $this->validator->validateFeed($data);
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertArrayHasKey('limit', $errors);
            $this->assertStringContainsString('between 1 and 100', $errors['limit']);
            throw $e;
        }
    }

    public function test_rejects_limit_above_maximum(): void
    {
        $data = [
            'topic' => 'News',
            'url' => 'https://news.google.com/rss',
            'limit' => 101
        ];

        $this->expectException(ValidationException::class);

        try {
            $this->validator->validateFeed($data);
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertArrayHasKey('limit', $errors);
            $this->assertStringContainsString('between 1 and 100', $errors['limit']);
            throw $e;
        }
    }

    public function test_rejects_negative_limit(): void
    {
        $data = [
            'topic' => 'News',
            'url' => 'https://news.google.com/rss',
            'limit' => -5
        ];

        $this->expectException(ValidationException::class);

        try {
            $this->validator->validateFeed($data);
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertArrayHasKey('limit', $errors);
            throw $e;
        }
    }

    // ============================================
    // Multiple Errors
    // ============================================

    public function test_collects_multiple_validation_errors(): void
    {
        $data = [
            'topic' => '',  // Invalid
            'url' => 'invalid-url',  // Invalid
            'limit' => 'abc'  // Invalid
        ];

        try {
            $this->validator->validateFeed($data);
            $this->fail('Should throw ValidationException');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();

            $this->assertArrayHasKey('topic', $errors);
            $this->assertArrayHasKey('url', $errors);
            $this->assertArrayHasKey('limit', $errors);
            $this->assertCount(3, $errors);
        }
    }

    // ============================================
    // String Validation Helpers
    // ============================================

    public function test_validates_string_length(): void
    {
        // Test public method validateString if exposed
        $result = $this->validator->validateString('Test', 1, 10);
        $this->assertEquals('Test', $result);
    }

    public function test_validates_string_rejects_too_short(): void
    {
        $this->expectException(ValidationException::class);
        $this->validator->validateString('AB', 3, 10, 'field');
    }

    public function test_validates_string_rejects_too_long(): void
    {
        $this->expectException(ValidationException::class);
        $this->validator->validateString('ABCDEFGHIJK', 1, 10, 'field');
    }

    public function test_validates_string_with_pattern(): void
    {
        $result = $this->validator->validateString(
            'valid123',
            1,
            20,
            'field',
            '/^[a-z0-9]+$/'
        );

        $this->assertEquals('valid123', $result);
    }

    public function test_validates_string_rejects_invalid_pattern(): void
    {
        $this->expectException(ValidationException::class);

        $this->validator->validateString(
            'invalid!@#',
            1,
            20,
            'field',
            '/^[a-z0-9]+$/'
        );
    }

    // ============================================
    // Integer Validation Helpers
    // ============================================

    public function test_validates_integer_in_range(): void
    {
        $result = $this->validator->validateInteger(50, 1, 100);
        $this->assertEquals(50, $result);
    }

    public function test_validates_integer_rejects_below_min(): void
    {
        $this->expectException(ValidationException::class);
        $this->validator->validateInteger(0, 1, 100, 'field');
    }

    public function test_validates_integer_rejects_above_max(): void
    {
        $this->expectException(ValidationException::class);
        $this->validator->validateInteger(101, 1, 100, 'field');
    }

    public function test_validates_integer_rejects_non_numeric(): void
    {
        $this->expectException(ValidationException::class);
        $this->validator->validateInteger('abc', 1, 100, 'field');
    }

    // ============================================
    // URL Validation Helpers
    // ============================================

    public function test_validates_url_format(): void
    {
        $result = $this->validator->validateUrl('https://example.com');
        $this->assertEquals('https://example.com', $result);
    }

    public function test_validates_url_rejects_invalid_format(): void
    {
        $this->expectException(ValidationException::class);
        $this->validator->validateUrl('not a url', 'url');
    }

    public function test_validates_url_with_allowed_hosts(): void
    {
        $result = $this->validator->validateUrl(
            'https://news.google.com/rss',
            'url',
            ['google.com']
        );

        $this->assertEquals('https://news.google.com/rss', $result);
    }

    public function test_validates_url_rejects_disallowed_host(): void
    {
        $this->expectException(ValidationException::class);

        $this->validator->validateUrl(
            'https://evil.com/rss',
            'url',
            ['google.com']
        );
    }

    // ============================================
    // Sanitization
    // ============================================

    public function test_trims_whitespace_from_strings(): void
    {
        $data = [
            'topic' => '  Tech News  ',
            'url' => 'https://news.google.com/rss',
            'limit' => 10
        ];

        $validated = $this->validator->validateFeed($data);

        $this->assertEquals('Tech News', $validated['topic']);
    }

    public function test_converts_numeric_strings_to_integers(): void
    {
        $data = [
            'topic' => 'News',
            'url' => 'https://news.google.com/rss',
            'limit' => '25'  // String number
        ];

        $validated = $this->validator->validateFeed($data);

        $this->assertIsInt($validated['limit']);
        $this->assertEquals(25, $validated['limit']);
    }

    // ============================================
    // SQL Injection Prevention
    // ============================================

    public function test_rejects_sql_injection_in_topic(): void
    {
        $data = [
            'topic' => "'; DROP TABLE feeds; --",
            'url' => 'https://news.google.com/rss',
            'limit' => 10
        ];

        $this->expectException(ValidationException::class);

        try {
            $this->validator->validateFeed($data);
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertArrayHasKey('topic', $errors);
            throw $e;
        }
    }

    public function test_rejects_xss_in_topic(): void
    {
        $data = [
            'topic' => '<script>alert("XSS")</script>',
            'url' => 'https://news.google.com/rss',
            'limit' => 10
        ];

        $this->expectException(ValidationException::class);

        try {
            $this->validator->validateFeed($data);
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertArrayHasKey('topic', $errors);
            throw $e;
        }
    }
}
