<?php

declare(strict_types=1);

namespace Unfurl\Services\GoogleNews;

use Unfurl\Security\UrlValidator;
use Unfurl\Exceptions\UrlDecodeException;
use Unfurl\Exceptions\SecurityException;

/**
 * UrlDecoder - Google News URL Decoder
 *
 * Decodes obfuscated Google News URLs to reveal actual article sources.
 *
 * Supported Formats:
 * 1. Old-style: Base64-encoded URLs in path (CBM/CWM prefix, 4 chars)
 *    Example: https://news.google.com/rss/articles/CBMiWWh0dHBzOi8v...
 *
 * 2. New-style: Article IDs that require HTTP redirect following
 *    Example: https://news.google.com/articles/CBMiWWh0dHBz...
 *
 * Security:
 * - SSRF protection via UrlValidator
 * - Validates decoded URLs before returning
 * - Prevents access to private IPs and invalid schemes
 *
 * Configuration:
 * - timeout: HTTP request timeout in seconds (default: 10)
 * - max_redirects: Maximum redirects to follow (default: 10)
 * - rate_limit_delay: Delay between requests in seconds (default: 0.5)
 * - max_retries: Maximum retry attempts on failure (default: 3)
 */
class UrlDecoder
{
    private UrlValidator $urlValidator;
    private array $config;
    private float $lastRequestTime = 0;

    /**
     * Default configuration
     */
    private const DEFAULT_CONFIG = [
        'timeout' => 10,
        'max_redirects' => 10,
        'rate_limit_delay' => 0.5,
        'max_retries' => 3,
        'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    ];

    /**
     * Patterns for detecting old-style URLs
     */
    private const OLD_STYLE_PATTERNS = [
        '/\/rss\/articles\/CBM/i',  // CBM prefix (4 chars total with article ID)
        '/\/rss\/articles\/CWM/i',  // CWM prefix (4 chars total with article ID)
    ];

    /**
     * Create URL decoder
     *
     * @param UrlValidator $urlValidator SSRF protection validator
     * @param array $config Configuration overrides
     */
    public function __construct(UrlValidator $urlValidator, array $config = [])
    {
        $this->urlValidator = $urlValidator;
        $this->config = array_merge(self::DEFAULT_CONFIG, $config);
    }

    /**
     * Decode Google News URL to actual article URL
     *
     * @param string $googleNewsUrl Google News obfuscated URL
     * @return string Decoded article URL
     * @throws UrlDecodeException If decoding fails
     * @throws SecurityException If decoded URL fails SSRF validation
     */
    public function decode(string $googleNewsUrl): string
    {
        // Validate input
        if (empty($googleNewsUrl)) {
            throw new UrlDecodeException('URL cannot be empty');
        }

        // Verify it's a Google News URL
        if (!$this->isGoogleNewsUrl($googleNewsUrl)) {
            throw new UrlDecodeException('Not a Google News URL: ' . $googleNewsUrl);
        }

        // Respect rate limiting
        $this->respectRateLimit();

        // Detect format and decode
        if ($this->isOldStyleUrl($googleNewsUrl)) {
            $decodedUrl = $this->decodeOldStyle($googleNewsUrl);
        } else {
            $decodedUrl = $this->decodeNewStyle($googleNewsUrl);
        }

        // Validate decoded URL for SSRF
        try {
            $this->urlValidator->validate($decodedUrl);
        } catch (SecurityException $e) {
            throw $e; // Re-throw security exceptions
        }

        return $decodedUrl;
    }

    /**
     * Check if URL is old-style format (base64 encoded in path)
     *
     * Old-style URLs have short article IDs (< 100 chars) that can be base64 decoded
     * New-style URLs have long article IDs (> 100 chars) that require redirect following
     *
     * @param string $url URL to check
     * @return bool True if old-style format
     */
    public function isOldStyleUrl(string $url): bool
    {
        // Extract article ID from URL
        $parsed = parse_url($url);
        $path = $parsed['path'] ?? '';

        if (!preg_match('/\/articles\/(.+)/', $path, $matches)) {
            return false;
        }

        $articleId = $matches[1];

        // Remove query parameters
        $questionPos = strpos($articleId, '?');
        if ($questionPos !== false) {
            $articleId = substr($articleId, 0, $questionPos);
        }

        // Old-style URLs have shorter article IDs (typically 20-140 chars)
        // New-style URLs have very long article IDs (150+ chars)
        // Check for CBM/CWM prefix AND reasonable length
        foreach (self::OLD_STYLE_PATTERNS as $pattern) {
            if (preg_match($pattern, $url) && strlen($articleId) < 150) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if URL is a Google News URL
     *
     * @param string $url URL to check
     * @return bool True if Google News URL
     */
    private function isGoogleNewsUrl(string $url): bool
    {
        $parsed = parse_url($url);
        if ($parsed === false || empty($parsed['host'])) {
            return false;
        }

        $host = strtolower($parsed['host']);
        return str_contains($host, 'news.google.com');
    }

    /**
     * Decode old-style URL (base64 encoded)
     *
     * Format: /rss/articles/{PREFIX}{BASE64_DATA}?params
     * The base64 data contains protocol buffer encoded URL(s)
     *
     * @param string $url Google News URL
     * @return string Decoded URL
     * @throws UrlDecodeException If decoding fails
     */
    private function decodeOldStyle(string $url): string
    {
        // Extract the article ID (everything after /articles/)
        $parsed = parse_url($url);
        $path = $parsed['path'] ?? '';

        if (!preg_match('/\/articles\/(.+)/', $path, $matches)) {
            throw new UrlDecodeException('Could not extract article ID from URL');
        }

        $articleId = $matches[1];

        // Remove query parameters if present (stored in $articleId)
        // The article ID is just the path component before any ?
        $questionPos = strpos($articleId, '?');
        if ($questionPos !== false) {
            $articleId = substr($articleId, 0, $questionPos);
        }

        // Remove the 4-char prefix (CBM or CWM)
        if (strlen($articleId) < 4) {
            throw new UrlDecodeException('Article ID too short');
        }

        $base64Data = substr($articleId, 3); // Skip first 3 chars (CBM/CWM)

        // Decode base64
        $decoded = base64_decode($base64Data, true);
        if ($decoded === false) {
            throw new UrlDecodeException('Invalid base64 encoding in article ID');
        }

        // Extract URL from protocol buffer format
        // The data contains: \x08\x13\x22{length}{url}
        // Sometimes contains multiple URLs (original + AMP version)
        $extractedUrl = $this->extractUrlFromProtobuf($decoded);

        if (empty($extractedUrl)) {
            throw new UrlDecodeException('Decoded URL is empty');
        }

        return $extractedUrl;
    }

    /**
     * Extract URL from protocol buffer encoded data
     *
     * Protocol buffer format: \x08\x13\x22{length_byte}{url_string}
     * May contain multiple URLs - we want the first (canonical) one
     *
     * @param string $data Protocol buffer data
     * @return string Extracted URL
     * @throws UrlDecodeException If extraction fails
     */
    private function extractUrlFromProtobuf(string $data): string
    {
        // Look for URL patterns in the decoded data
        // Try to match any URL scheme (http://, https://, ftp://, etc.)
        // This allows SSRF validation to catch invalid schemes later
        if (preg_match('/([a-z][a-z0-9+.-]*:\/\/[^\x00-\x1F\x7F]+?)[\x00-\x1F\x7F]/', $data, $matches)) {
            // Found a URL followed by control character (likely end of URL)
            $url = $matches[1];
        } elseif (preg_match('/([a-z][a-z0-9+.-]*:\/\/[^\s]+)/', $data, $matches)) {
            // Found a URL followed by whitespace
            $url = $matches[1];
        } else {
            // Last resort: look for common URL schemes anywhere in the data
            $schemes = ['https://', 'http://', 'ftp://', 'ftps://'];
            $positions = [];

            foreach ($schemes as $scheme) {
                $pos = stripos($data, $scheme);
                if ($pos !== false) {
                    $positions[$scheme] = $pos;
                }
            }

            if (empty($positions)) {
                throw new UrlDecodeException('No URL found in decoded data');
            }

            // Use the first URL found
            asort($positions);
            $startPos = reset($positions);

            // Find the end of the URL (next control character, null byte, or string end)
            $urlEnd = $startPos;
            $dataLen = strlen($data);

            while ($urlEnd < $dataLen) {
                $char = ord($data[$urlEnd]);
                // Stop at control characters (< 32) or DEL (127)
                if ($char < 32 || $char === 127) {
                    break;
                }
                $urlEnd++;
            }

            $url = substr($data, $startPos, $urlEnd - $startPos);
        }

        // Clean up URL (remove trailing junk)
        $url = rtrim($url, "\x00\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0A\x0B\x0C\x0D\x0E\x0F");

        return $url;
    }

    /**
     * Decode new-style URL (HTTP redirect following)
     *
     * New-style URLs require making an HTTP request and following redirects
     * to reach the final article URL.
     *
     * @param string $url Google News URL
     * @return string Final article URL after redirects
     * @throws UrlDecodeException If HTTP request fails or times out
     */
    private function decodeNewStyle(string $url): string
    {
        $retries = 0;
        $lastError = null;

        while ($retries < $this->config['max_retries']) {
            try {
                return $this->followRedirects($url);
            } catch (UrlDecodeException $e) {
                $lastError = $e;
                $retries++;

                if ($retries < $this->config['max_retries']) {
                    // Exponential backoff
                    usleep(pow(2, $retries) * 100000); // 0.2s, 0.4s, 0.8s...
                }
            }
        }

        throw new UrlDecodeException(
            'Failed to decode after ' . $this->config['max_retries'] . ' retries: ' . $lastError->getMessage(),
            0,
            $lastError
        );
    }

    /**
     * Follow HTTP redirects to get final URL
     *
     * @param string $url Starting URL
     * @return string Final URL after all redirects
     * @throws UrlDecodeException If request fails
     */
    private function followRedirects(string $url): string
    {
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => $this->config['max_redirects'],
            CURLOPT_TIMEOUT => $this->config['timeout'],
            CURLOPT_USERAGENT => $this->config['user_agent'],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_ENCODING => '', // Accept all encodings
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);

        curl_close($ch);

        // Check for cURL errors
        if ($response === false || !empty($error)) {
            throw new UrlDecodeException('HTTP request failed: ' . $error);
        }

        // Check for HTTP errors
        if ($httpCode >= 400) {
            throw new UrlDecodeException('HTTP error ' . $httpCode . ' when fetching URL');
        }

        // Validate final URL
        if (empty($finalUrl) || $finalUrl === $url) {
            throw new UrlDecodeException('No redirect occurred - still on Google News');
        }

        return $finalUrl;
    }

    /**
     * Respect rate limiting between requests
     *
     * Adds delay between consecutive decode() calls
     */
    private function respectRateLimit(): void
    {
        if ($this->lastRequestTime > 0) {
            $elapsed = microtime(true) - $this->lastRequestTime;
            $delay = $this->config['rate_limit_delay'] - $elapsed;

            if ($delay > 0) {
                usleep((int)($delay * 1000000));
            }
        }

        $this->lastRequestTime = microtime(true);
    }
}
