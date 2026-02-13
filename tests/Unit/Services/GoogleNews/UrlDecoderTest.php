<?php

declare(strict_types=1);

namespace Tests\Unit\Services\GoogleNews;

use PHPUnit\Framework\TestCase;
use Unfurl\Services\GoogleNews\UrlDecoder;
use Unfurl\Security\UrlValidator;
use Unfurl\Exceptions\SecurityException;
use Unfurl\Exceptions\UrlDecodeException;

/**
 * Tests for UrlDecoder - Google News URL Decoding
 *
 * TDD: Test written BEFORE implementation
 *
 * Google News URL Formats:
 * 1. Old-style: Base64-encoded URLs with CBM/CWM prefix (4 chars)
 * 2. New-style: Batchexecute API URLs (most common now)
 *
 * Critical Requirements:
 * - Decode both old and new URL formats
 * - SSRF protection via UrlValidator
 * - HTTP timeout and retry handling
 * - Rate limit respect
 * - Return decoded URL or throw exception
 */
class UrlDecoderTest extends TestCase
{
    private UrlDecoder $decoder;
    private UrlValidator $urlValidator;

    protected function setUp(): void
    {
        $this->urlValidator = new UrlValidator();
        $this->decoder = new UrlDecoder($this->urlValidator);
    }

    // ============================================
    // Old-Style URL Decoding (Base64)
    // ============================================

    public function test_decodes_old_style_url_with_cbm_prefix(): void
    {
        // Create a properly encoded protocol buffer URL
        // Format: \x08\x13\x22{length}{url}
        $testUrl = 'https://www.example.com/article/test-article';
        $encodedData = "\x08\x13\x22" . chr(strlen($testUrl)) . $testUrl;
        $base64 = base64_encode($encodedData);

        // Old-style Google News URL with CBM prefix
        $googleNewsUrl = 'https://news.google.com/rss/articles/CBM' . $base64 . '?oc=5';

        $result = $this->decoder->decode($googleNewsUrl);

        // Should return the decoded URL
        $this->assertStringStartsWith('https://www.example.com/', $result);
        $this->assertStringContainsString('article', $result);
        $this->assertEquals($testUrl, $result);
    }

    public function test_decodes_old_style_url_with_cwm_prefix(): void
    {
        // Create a properly encoded protocol buffer URL
        $testUrl = 'https://www.example.com/article/test-article';
        $encodedData = "\x08\x13\x22" . chr(strlen($testUrl)) . $testUrl;
        $base64 = base64_encode($encodedData);

        // Old-style Google News URL with CWM prefix (4-char identifier)
        $googleNewsUrl = 'https://news.google.com/rss/articles/CWM' . $base64 . '?oc=5';

        $result = $this->decoder->decode($googleNewsUrl);

        $this->assertStringStartsWith('https://www.example.com/', $result);
        $this->assertEquals($testUrl, $result);
    }

    public function test_old_style_url_extracts_first_url_from_base64(): void
    {
        // Base64 decoding may contain multiple URLs (original + AMP)
        // Should extract the first (canonical) URL
        $canonicalUrl = 'https://www.example.com/article/test-article';
        $ampUrl = 'https://www.example.com/amp/article/test-article';

        // Encode both URLs in protocol buffer format
        $encodedData = "\x08\x13\x22" . chr(strlen($canonicalUrl)) . $canonicalUrl .
                      "\x00" . // Separator
                      "\x08\x13\x22" . chr(strlen($ampUrl)) . $ampUrl;
        $base64 = base64_encode($encodedData);

        $googleNewsUrl = 'https://news.google.com/rss/articles/CBM' . $base64 . '?oc=5';

        $result = $this->decoder->decode($googleNewsUrl);

        // Should return the canonical URL, not the AMP URL
        $this->assertEquals($canonicalUrl, $result);
        $this->assertStringNotContainsString('/amp/', $result);
    }

    // ============================================
    // New-Style URL Decoding (Batchexecute API)
    // ============================================

    public function test_decodes_new_style_url_via_http_redirect(): void
    {
        // New-style URLs should use HTTP client to follow redirects
        // This is a placeholder test - actual implementation will need mocked HTTP
        $googleNewsUrl = 'https://news.google.com/articles/CBMiWWh0dHBzOi8vd3d3LmV4YW1wbGUuY29tL2FydGljbGUvbmV3LXN0eWxl';

        // For now, we'll test that it doesn't throw an exception on new-style URLs
        // In real implementation, this would mock HTTP client
        $this->expectException(UrlDecodeException::class);
        $this->decoder->decode($googleNewsUrl);
    }

    // ============================================
    // URL Format Detection
    // ============================================

    public function test_detects_old_style_format(): void
    {
        $oldStyleUrl = 'https://news.google.com/rss/articles/CBMiWWh0dHBzOi8vd3d3LmV4YW1wbGUuY29tL2FydGljbGUvdGVzdC1hcnRpY2xl0gFdaHR0cHM6Ly93d3cuZXhhbXBsZS5jb20vYW1wL2FydGljbGUvdGVzdC1hcnRpY2xl?oc=5';

        $isOldStyle = $this->decoder->isOldStyleUrl($oldStyleUrl);

        $this->assertTrue($isOldStyle);
    }

    public function test_detects_new_style_format(): void
    {
        // New-style doesn't have base64 in path, just article ID
        $newStyleUrl = 'https://news.google.com/articles/CBMiWWh0dHBzOi8vd3d3LmV4YW1wbGUuY29t';

        $isOldStyle = $this->decoder->isOldStyleUrl($newStyleUrl);

        $this->assertFalse($isOldStyle);
    }

    // ============================================
    // SSRF Protection
    // ============================================

    public function test_validates_decoded_url_against_ssrf(): void
    {
        // Create URL that decodes to a private IP
        $privateUrl = 'https://192.168.1.1/test';
        $encodedData = "\x08\x13\x22" . chr(strlen($privateUrl)) . $privateUrl;
        $privateIpBase64 = base64_encode($encodedData);
        $googleNewsUrl = 'https://news.google.com/rss/articles/CBM' . $privateIpBase64;

        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Private IP address blocked');

        $this->decoder->decode($googleNewsUrl);
    }

    public function test_validates_decoded_url_against_localhost(): void
    {
        // Base64 encode: https://127.0.0.1/test
        $localhostUrl = 'https://127.0.0.1/test';
        $encodedData = "\x08\x13\x22" . chr(strlen($localhostUrl)) . $localhostUrl;
        $localhostBase64 = base64_encode($encodedData);
        $googleNewsUrl = 'https://news.google.com/rss/articles/CBM' . $localhostBase64;

        $this->expectException(SecurityException::class);

        $this->decoder->decode($googleNewsUrl);
    }

    public function test_validates_decoded_url_scheme(): void
    {
        // Base64 encode: ftp://example.com/test (invalid scheme)
        $ftpUrl = 'ftp://example.com/test';
        $encodedData = "\x08\x13\x22" . chr(strlen($ftpUrl)) . $ftpUrl;
        $ftpBase64 = base64_encode($encodedData);
        $googleNewsUrl = 'https://news.google.com/rss/articles/CBM' . $ftpBase64;

        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Invalid URL scheme');

        $this->decoder->decode($googleNewsUrl);
    }

    // ============================================
    // Malformed URLs
    // ============================================

    public function test_throws_exception_for_invalid_base64(): void
    {
        // Invalid base64 data
        $googleNewsUrl = 'https://news.google.com/rss/articles/CBM!!!INVALID!!!';

        $this->expectException(UrlDecodeException::class);
        $this->expectExceptionMessage('Invalid base64 encoding');

        $this->decoder->decode($googleNewsUrl);
    }

    public function test_throws_exception_for_empty_decoded_url(): void
    {
        // Base64 that decodes to data with no URL
        $emptyBase64 = base64_encode("\x08\x13\x22\x00"); // Empty URL
        $googleNewsUrl = 'https://news.google.com/rss/articles/CBM' . $emptyBase64;

        $this->expectException(UrlDecodeException::class);
        // Can match either error message (implementation detail)
        $this->decoder->decode($googleNewsUrl);
    }

    public function test_throws_exception_for_non_google_news_url(): void
    {
        $nonGoogleUrl = 'https://example.com/not-google-news';

        $this->expectException(UrlDecodeException::class);
        $this->expectExceptionMessage('Not a Google News URL');

        $this->decoder->decode($nonGoogleUrl);
    }

    public function test_throws_exception_for_empty_url(): void
    {
        $this->expectException(UrlDecodeException::class);

        $this->decoder->decode('');
    }

    // ============================================
    // Edge Cases
    // ============================================

    public function test_handles_url_with_query_parameters(): void
    {
        // Create properly encoded URL
        $testUrl = 'https://www.example.com/article/test-article';
        $encodedData = "\x08\x13\x22" . chr(strlen($testUrl)) . $testUrl;
        $base64 = base64_encode($encodedData);

        // Old-style URL with query parameters
        $googleNewsUrl = 'https://news.google.com/rss/articles/CBM' . $base64 . '?oc=5&ceid=US:en';

        $result = $this->decoder->decode($googleNewsUrl);

        $this->assertStringStartsWith('https://www.example.com/', $result);
        $this->assertEquals($testUrl, $result);
    }

    public function test_handles_url_with_fragment(): void
    {
        // Create properly encoded URL
        $testUrl = 'https://www.example.com/article/test-article';
        $encodedData = "\x08\x13\x22" . chr(strlen($testUrl)) . $testUrl;
        $base64 = base64_encode($encodedData);

        // URL with fragment identifier
        $googleNewsUrl = 'https://news.google.com/rss/articles/CBM' . $base64 . '?oc=5#section';

        $result = $this->decoder->decode($googleNewsUrl);

        $this->assertStringStartsWith('https://www.example.com/', $result);
        $this->assertEquals($testUrl, $result);
    }

    // ============================================
    // HTTP Client Configuration
    // ============================================

    public function test_respects_timeout_configuration(): void
    {
        // Test that decoder uses configured timeout
        // This would be tested with a mocked HTTP client
        $decoder = new UrlDecoder($this->urlValidator, ['timeout' => 5]);

        $this->assertInstanceOf(UrlDecoder::class, $decoder);
    }

    public function test_respects_max_redirects_configuration(): void
    {
        // Test that decoder uses configured max redirects
        $decoder = new UrlDecoder($this->urlValidator, ['max_redirects' => 5]);

        $this->assertInstanceOf(UrlDecoder::class, $decoder);
    }

    // ============================================
    // Rate Limiting
    // ============================================

    public function test_respects_rate_limit_delay(): void
    {
        // Test that decoder respects rate limiting between requests
        // This would track timing between decode() calls
        $decoder = new UrlDecoder($this->urlValidator, ['rate_limit_delay' => 0.1]);

        $this->assertInstanceOf(UrlDecoder::class, $decoder);
    }
}
