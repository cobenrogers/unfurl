# Google News URL Decoder

## Overview

The `UrlDecoder` service decodes obfuscated Google News URLs to reveal the actual article sources. It supports both old-style (base64-encoded) and new-style (HTTP redirect) URL formats.

## Features

- **Old-Style URLs**: Base64-encoded protocol buffer data with CBM/CWM prefix
- **New-Style URLs**: HTTP redirect following with timeout and retry logic
- **SSRF Protection**: Validates decoded URLs to prevent server-side request forgery attacks
- **Rate Limiting**: Respects configurable delays between requests
- **Retry Logic**: Automatic retry with exponential backoff on failures

## Usage

### Basic Usage

```php
use Unfurl\Services\GoogleNews\UrlDecoder;
use Unfurl\Security\UrlValidator;

// Create dependencies
$urlValidator = new UrlValidator();

// Create decoder with default configuration
$decoder = new UrlDecoder($urlValidator);

// Decode a Google News URL
$googleNewsUrl = 'https://news.google.com/rss/articles/CBM...';
$actualUrl = $decoder->decode($googleNewsUrl);

echo "Actual article URL: " . $actualUrl;
```

### Custom Configuration

```php
// Configure timeout, redirects, and rate limiting
$config = [
    'timeout' => 15,              // HTTP timeout in seconds (default: 10)
    'max_redirects' => 20,        // Maximum redirects to follow (default: 10)
    'rate_limit_delay' => 1.0,    // Delay between requests in seconds (default: 0.5)
    'max_retries' => 5,           // Maximum retry attempts (default: 3)
    'user_agent' => 'MyBot/1.0',  // Custom user agent
];

$decoder = new UrlDecoder($urlValidator, $config);
```

### Error Handling

```php
use Unfurl\Exceptions\UrlDecodeException;
use Unfurl\Exceptions\SecurityException;

try {
    $actualUrl = $decoder->decode($googleNewsUrl);
    echo "Success: " . $actualUrl;
} catch (SecurityException $e) {
    // SSRF attempt detected (private IP, invalid scheme, etc.)
    echo "Security violation: " . $e->getMessage();
} catch (UrlDecodeException $e) {
    // Decoding failed (invalid format, timeout, HTTP error, etc.)
    echo "Decode failed: " . $e->getMessage();
}
```

## URL Formats

### Old-Style Format (Base64)

Old-style Google News URLs contain base64-encoded protocol buffer data in the path:

```
https://news.google.com/rss/articles/CBM{base64_data}?oc=5
https://news.google.com/rss/articles/CWM{base64_data}?oc=5
```

The decoder:
1. Extracts the base64 data after the prefix (CBM/CWM)
2. Decodes the base64 string
3. Extracts the URL from protocol buffer format
4. Validates against SSRF attacks
5. Returns the decoded URL

### New-Style Format (HTTP Redirect)

New-style URLs require following HTTP redirects:

```
https://news.google.com/articles/{article_id}
```

The decoder:
1. Makes an HTTP GET request with redirect following enabled
2. Captures the final URL after all redirects
3. Validates against SSRF attacks
4. Returns the final article URL

## Format Detection

You can check if a URL is old-style format:

```php
$isOldStyle = $decoder->isOldStyleUrl($googleNewsUrl);

if ($isOldStyle) {
    echo "This uses base64 encoding";
} else {
    echo "This uses HTTP redirects";
}
```

## Security

The decoder validates all decoded URLs against SSRF attacks:

### Blocked Targets
- Private IP ranges (10.x.x.x, 192.168.x.x, 172.16-31.x.x)
- Localhost (127.x.x.x)
- Link-local addresses (169.254.x.x)
- IPv6 special addresses (::1, fc00::/7, fe80::/10)
- Invalid URL schemes (only HTTP/HTTPS allowed)

### Example Security Violations

```php
// These will throw SecurityException:

// Private IP
$url = 'https://192.168.1.1/internal-api';

// Localhost
$url = 'https://127.0.0.1/admin';

// AWS metadata endpoint
$url = 'http://169.254.169.254/latest/meta-data/';

// Invalid scheme
$url = 'ftp://example.com/file';
```

## Rate Limiting

The decoder automatically respects rate limits to avoid overwhelming Google's servers:

```php
// Configure 2-second delay between requests
$decoder = new UrlDecoder($urlValidator, ['rate_limit_delay' => 2.0]);

// First decode happens immediately
$url1 = $decoder->decode($googleNews1);

// Second decode waits 2 seconds
$url2 = $decoder->decode($googleNews2);
```

## Retry Logic

Failed HTTP requests are automatically retried with exponential backoff:

```php
// Configure max retries
$decoder = new UrlDecoder($urlValidator, ['max_retries' => 5]);

// Will retry up to 5 times with delays: 0.2s, 0.4s, 0.8s, 1.6s, 3.2s
try {
    $url = $decoder->decode($googleNewsUrl);
} catch (UrlDecodeException $e) {
    // All retries failed
    echo "Failed after 5 attempts: " . $e->getMessage();
}
```

## Performance Considerations

### Old-Style URLs
- **Speed**: Very fast (microseconds)
- **No external requests**: Decoding is local
- **Rate limiting**: Not needed for old-style URLs

### New-Style URLs
- **Speed**: 1-5 seconds per URL
- **External requests**: Requires HTTP request to Google
- **Rate limiting**: Recommended to avoid being blocked
- **Timeouts**: Default 10 seconds, configurable

### Best Practices

1. **Batch Processing**: Add delays between bulk decodes
2. **Caching**: Cache decoded URLs to avoid re-processing
3. **Error Handling**: Always catch exceptions
4. **Configuration**: Tune timeouts based on your needs
5. **Monitoring**: Log failures for debugging

## Integration with Feed Processor

```php
use Unfurl\Services\GoogleNews\UrlDecoder;
use Unfurl\Security\UrlValidator;

class FeedProcessor
{
    private UrlDecoder $urlDecoder;

    public function __construct()
    {
        $urlValidator = new UrlValidator();
        $this->urlDecoder = new UrlDecoder($urlValidator, [
            'timeout' => 15,
            'rate_limit_delay' => 1.0,
            'max_retries' => 3,
        ]);
    }

    public function processArticle(array $rssItem): ?array
    {
        $googleNewsUrl = $rssItem['link'];

        try {
            $actualUrl = $this->urlDecoder->decode($googleNewsUrl);

            return [
                'google_news_url' => $googleNewsUrl,
                'final_url' => $actualUrl,
                'status' => 'success',
            ];
        } catch (SecurityException $e) {
            // Log security violation
            error_log("SSRF attempt blocked: " . $e->getMessage());

            return [
                'google_news_url' => $googleNewsUrl,
                'status' => 'blocked',
                'error' => $e->getMessage(),
            ];
        } catch (UrlDecodeException $e) {
            // Log decode failure
            error_log("URL decode failed: " . $e->getMessage());

            return [
                'google_news_url' => $googleNewsUrl,
                'status' => 'failed',
                'error' => $e->getMessage(),
            ];
        }
    }
}
```

## Testing

The `UrlDecoderTest` class provides comprehensive test coverage:

```bash
# Run UrlDecoder tests
./vendor/bin/phpunit tests/Unit/Services/GoogleNews/UrlDecoderTest.php --testdox
```

## Future Enhancements

- **Caching**: Built-in cache to avoid re-decoding same URLs
- **Batch Processing**: Process multiple URLs in parallel
- **Metrics**: Track success rates, latency, and failures
- **Fallback Services**: Use external services when Google changes format

## References

- [Google News RSS Documentation](https://support.google.com/news/publisher-center/answer/9606710)
- [SSRF Prevention](https://cheatsheetseries.owasp.org/cheatsheets/Server_Side_Request_Forgery_Prevention_Cheat_Sheet.html)
- [Requirements Document](../requirements/REQUIREMENTS.md) - Section 8.1

## Changelog

**2026-02-07** - Initial implementation
- Old-style URL decoding (base64)
- New-style URL decoding (HTTP redirects)
- SSRF protection
- Rate limiting
- Retry logic with exponential backoff
