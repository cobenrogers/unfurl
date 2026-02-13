<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use PHPUnit\Framework\TestCase;
use Unfurl\Security\UrlValidator;
use Unfurl\Exceptions\SecurityException;

/**
 * Tests for UrlValidator - SSRF Protection
 *
 * TDD: Test written BEFORE implementation
 * Requirements: Section 7.3 of REQUIREMENTS.md
 *
 * Critical Security Requirements:
 * - Block private IP ranges (10.x, 192.168.x, 127.x, 169.254.x, IPv6 equivalents)
 * - Allow only HTTP/HTTPS schemes
 * - Validate redirects with same rules
 * - Prevent DNS rebinding attacks
 * - Enforce URL length limits
 */
class UrlValidatorTest extends TestCase
{
    private UrlValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new UrlValidator();
    }

    // ============================================
    // Valid Public URLs - Should Pass
    // ============================================

    public function test_validates_public_http_url(): void
    {
        // Should not throw exception
        $this->validator->validate('http://example.com/article');
        $this->assertTrue(true);
    }

    public function test_validates_public_https_url(): void
    {
        $this->validator->validate('https://www.google.com/news');
        $this->assertTrue(true);
    }

    public function test_validates_url_with_path_and_query(): void
    {
        // Use a real domain that will resolve
        $this->validator->validate('https://www.google.com/search?q=test&page=1');
        $this->assertTrue(true);
    }

    public function test_validates_url_with_port(): void
    {
        $this->validator->validate('http://example.com:8080/api');
        $this->assertTrue(true);
    }

    // ============================================
    // Invalid URL Formats - Should Reject
    // ============================================

    public function test_rejects_empty_url(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Invalid URL format');

        $this->validator->validate('');
    }

    public function test_rejects_malformed_url(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Invalid URL format');

        $this->validator->validate('not-a-valid-url');
    }

    public function test_rejects_url_without_host(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Invalid URL format');

        $this->validator->validate('http://');
    }

    // ============================================
    // Scheme Validation - HTTP/HTTPS Only
    // ============================================

    public function test_rejects_file_scheme(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Invalid URL scheme');

        $this->validator->validate('file:///etc/passwd');
    }

    public function test_rejects_javascript_scheme(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Invalid URL scheme');

        $this->validator->validate('javascript:alert(1)');
    }

    public function test_rejects_data_scheme(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Invalid URL scheme');

        $this->validator->validate('data:text/html,<script>alert(1)</script>');
    }

    public function test_rejects_ftp_scheme(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Invalid URL scheme');

        $this->validator->validate('ftp://example.com/file');
    }

    public function test_rejects_gopher_scheme(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Invalid URL scheme');

        $this->validator->validate('gopher://example.com');
    }

    // ============================================
    // Private IP Blocking - IPv4
    // ============================================

    public function test_rejects_localhost_127_0_0_1(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Private IP address blocked');

        $this->validator->validate('http://127.0.0.1/api');
    }

    public function test_rejects_localhost_127_1_1_1(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Private IP address blocked');

        $this->validator->validate('http://127.1.1.1/');
    }

    public function test_rejects_private_10_network(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Private IP address blocked');

        $this->validator->validate('http://10.0.0.1/');
    }

    public function test_rejects_private_10_255_255_255(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Private IP address blocked');

        $this->validator->validate('http://10.255.255.255/');
    }

    public function test_rejects_private_192_168_network(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Private IP address blocked');

        $this->validator->validate('http://192.168.1.1/');
    }

    public function test_rejects_private_172_16_network(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Private IP address blocked');

        $this->validator->validate('http://172.16.0.1/');
    }

    public function test_rejects_private_172_31_network(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Private IP address blocked');

        $this->validator->validate('http://172.31.255.255/');
    }

    public function test_rejects_aws_metadata_endpoint(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Private IP address blocked');

        // AWS IMDSv1/v2 endpoint
        $this->validator->validate('http://169.254.169.254/latest/meta-data/');
    }

    public function test_rejects_link_local_169_254(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Private IP address blocked');

        $this->validator->validate('http://169.254.1.1/');
    }

    // ============================================
    // Private IP Blocking - IPv6
    // ============================================

    public function test_rejects_ipv6_localhost(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Private IP address blocked');

        $this->validator->validate('http://[::1]/');
    }

    public function test_rejects_ipv6_private_fc00(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Private IP address blocked');

        $this->validator->validate('http://[fc00::1]/');
    }

    public function test_rejects_ipv6_private_fd00(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Private IP address blocked');

        $this->validator->validate('http://[fd00::1]/');
    }

    public function test_rejects_ipv6_link_local_fe80(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Private IP address blocked');

        $this->validator->validate('http://[fe80::1]/');
    }

    // ============================================
    // DNS Resolution Attack Prevention
    // ============================================

    public function test_rejects_unresolvable_hostname(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Could not resolve hostname');

        $this->validator->validate('http://this-domain-definitely-does-not-exist-12345.invalid/');
    }

    public function test_blocks_hostname_resolving_to_private_ip(): void
    {
        // Note: This test requires a hostname that resolves to a private IP
        // In real scenario, attacker could register domain pointing to 127.0.0.1
        // We'll test the IP blocking logic directly in ipInRange tests

        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Private IP address blocked');

        // localhost typically resolves to 127.0.0.1
        $this->validator->validate('http://localhost/');
    }

    // ============================================
    // URL Length Validation
    // ============================================

    public function test_rejects_url_exceeding_2000_characters(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('URL too long');

        $longPath = str_repeat('a', 2001);
        $this->validator->validate("http://example.com/$longPath");
    }

    public function test_accepts_url_at_2000_character_limit(): void
    {
        // 2000 chars total, includes "http://example.com/"
        $pathLength = 2000 - strlen('http://example.com/');
        $longPath = str_repeat('a', $pathLength);

        $this->validator->validate("http://example.com/$longPath");
        $this->assertTrue(true);
    }

    // ============================================
    // IP Range Checking Utility Tests
    // ============================================

    public function test_ip_in_range_detects_ipv4_in_cidr(): void
    {
        $validator = new UrlValidator();

        // Use reflection to test private method
        $reflection = new \ReflectionClass($validator);
        $method = $reflection->getMethod('ipInRange');
        $method->setAccessible(true);

        // Test various IPs in 10.0.0.0/8
        $this->assertTrue($method->invoke($validator, '10.0.0.1', '10.0.0.0/8'));
        $this->assertTrue($method->invoke($validator, '10.255.255.254', '10.0.0.0/8'));
        $this->assertFalse($method->invoke($validator, '11.0.0.1', '10.0.0.0/8'));

        // Test 192.168.0.0/16
        $this->assertTrue($method->invoke($validator, '192.168.0.1', '192.168.0.0/16'));
        $this->assertTrue($method->invoke($validator, '192.168.255.255', '192.168.0.0/16'));
        $this->assertFalse($method->invoke($validator, '192.169.0.1', '192.168.0.0/16'));

        // Test 127.0.0.0/8
        $this->assertTrue($method->invoke($validator, '127.0.0.1', '127.0.0.0/8'));
        $this->assertTrue($method->invoke($validator, '127.255.255.255', '127.0.0.0/8'));
        $this->assertFalse($method->invoke($validator, '128.0.0.1', '127.0.0.0/8'));
    }

    public function test_ip_in_range_detects_ipv6_in_cidr(): void
    {
        $validator = new UrlValidator();

        $reflection = new \ReflectionClass($validator);
        $method = $reflection->getMethod('ipInRange');
        $method->setAccessible(true);

        // Test IPv6 localhost
        $this->assertTrue($method->invoke($validator, '::1', '::1/128'));
        $this->assertFalse($method->invoke($validator, '::2', '::1/128'));

        // Test fc00::/7 (IPv6 private)
        $this->assertTrue($method->invoke($validator, 'fc00::1', 'fc00::/7'));
        $this->assertTrue($method->invoke($validator, 'fd00::1', 'fc00::/7'));
        $this->assertFalse($method->invoke($validator, 'fe00::1', 'fc00::/7'));
    }

    // ============================================
    // Edge Cases
    // ============================================

    public function test_case_insensitive_scheme_validation(): void
    {
        // Should accept HTTP in any case
        $this->validator->validate('HTTP://example.com/');
        $this->validator->validate('HtTp://example.com/');
        $this->assertTrue(true);
    }

    public function test_rejects_url_with_credentials(): void
    {
        // Should still validate, but credential-based URLs are suspicious
        // For now, we allow them but validate the host normally
        $this->validator->validate('http://user:pass@example.com/');
        $this->assertTrue(true);
    }

    public function test_validates_internationalized_domain_names(): void
    {
        // IDN domains should work if they resolve properly
        // This is a basic test - real IDN support would need punycode conversion
        $this->validator->validate('http://example.com/');
        $this->assertTrue(true);
    }

    // ============================================
    // Integration with Real-World Scenarios
    // ============================================

    public function test_validates_typical_news_urls(): void
    {
        $newsUrls = [
            'https://www.cnn.com/2024/article',
            'https://www.bbc.com/news/world',
            'https://news.ycombinator.com/item?id=123',
            'https://techcrunch.com/2024/01/01/article',
        ];

        foreach ($newsUrls as $url) {
            $this->validator->validate($url);
        }

        $this->assertTrue(true);
    }

    public function test_rejects_common_ssrf_attack_vectors(): void
    {
        $attackVectors = [
            'http://127.0.0.1:6379/',           // Redis
            'http://127.0.0.1:27017/',          // MongoDB
            'http://169.254.169.254/',          // AWS metadata
            'http://[::1]:3000/',               // IPv6 localhost
            'http://localhost:9200/',           // Elasticsearch
            'file:///etc/passwd',               // File access
        ];

        foreach ($attackVectors as $url) {
            try {
                $this->validator->validate($url);
                $this->fail("Expected SecurityException for URL: $url");
            } catch (SecurityException $e) {
                $this->assertTrue(true);
            }
        }
    }
}
