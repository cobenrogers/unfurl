# ArticleExtractor - Quick Reference

## One-Line Summary
Extract article metadata and plain text content from HTML with robust error handling.

---

## Quick Start

```php
use Unfurl\Services\ArticleExtractor;

$extractor = new ArticleExtractor();
$result = $extractor->extract($html);

echo $result['og:title'];       // Article title
echo $result['content'];        // Plain text content
echo $result['word_count'];     // Word count
```

---

## What It Extracts

| Field | Description | Type | Fallback |
|-------|-------------|------|----------|
| `og:title` | Article title | `?string` | `<title>` tag |
| `og:description` | Article description | `?string` | None |
| `og:image` | Featured image URL | `?string` | None |
| `og:url` | Canonical URL | `?string` | None |
| `og:site_name` | Site/publication name | `?string` | None |
| `twitter:image` | Twitter Card image | `?string` | None |
| `author` | Article author | `?string` | `author` meta |
| `published_time` | ISO 8601 timestamp | `?string` | None |
| `section` | Article category | `?string` | None |
| `tags` | Article tags | `array` | `[]` |
| `content` | Plain text content | `string` | `''` |
| `word_count` | Word count | `int` | `0` |

---

## Common Patterns

### Safe Access with Fallbacks
```php
$title = $result['og:title'] ?? 'Untitled';
$description = $result['og:description'] ?? substr($result['content'], 0, 200);
```

### Check for Missing Data
```php
if ($result['og:image'] === null) {
    // No image available
}

if (empty($result['tags'])) {
    // No tags
}
```

### RSS Feed Item
```php
$rssItem = [
    'title' => $result['og:title'] ?? 'Untitled',
    'description' => $result['og:description'],
    'link' => $result['og:url'],
    'pubDate' => $result['published_time'],
    'author' => $result['author'],
];
```

### Content Analysis
```php
$readingTime = ceil($result['word_count'] / 200); // ~200 WPM
$hasImage = $result['og:image'] !== null;
$hasTags = !empty($result['tags']);
```

---

## Key Features

✅ **No Exceptions**: Returns null for missing data, never throws
✅ **UTF-8 Safe**: Handles international characters correctly
✅ **HTML Entities**: Automatically decoded (&quot; → ")
✅ **Script Removal**: Scripts and styles completely removed
✅ **Malformed HTML**: Handles broken HTML gracefully
✅ **Zero Dependencies**: Only uses PHP core extensions

---

## What Gets Removed

- HTML tags (`<p>`, `<div>`, etc.)
- Script tags and JavaScript code
- Style tags and CSS rules
- HTML comments
- Excess whitespace

---

## Return Value Guarantees

- `tags` is **always array**, never null
- `content` is **always string**, never null
- `word_count` is **always int**, never null
- Metadata fields are **nullable** (string or null)
- **No exceptions** for missing/malformed data

---

## Example Output

**Input HTML:**
```html
<html>
<head>
    <meta property="og:title" content="Breaking News">
    <meta property="og:description" content="Latest updates">
    <meta property="article:tag" content="Technology">
    <meta property="article:tag" content="AI">
</head>
<body>
    <p>This is the article content.</p>
</body>
</html>
```

**Output:**
```php
[
    'og:title' => 'Breaking News',
    'og:description' => 'Latest updates',
    'og:image' => null,
    'og:url' => null,
    'og:site_name' => null,
    'twitter:image' => null,
    'author' => null,
    'published_time' => null,
    'section' => null,
    'tags' => ['Technology', 'AI'],
    'content' => 'This is the article content.',
    'word_count' => 5
]
```

---

## Best Practices

### ✅ DO
```php
// Check for null before use
if ($result['og:image'] !== null) {
    echo '<img src="' . htmlspecialchars($result['og:image']) . '">';
}

// Use null coalescing for fallbacks
$title = $result['og:title'] ?? $result['og:url'] ?? 'Untitled';

// Sanitize output for HTML
echo htmlspecialchars($result['content'], ENT_QUOTES, 'UTF-8');

// Cache results
$cacheKey = 'article_' . md5($url);
$result = $cache->get($cacheKey) ?? $extractor->extract($html);
```

### ❌ DON'T
```php
// Don't assume metadata exists
echo $result['og:image']; // May be null!

// Don't check tags for null (it's always array)
if ($result['tags'] !== null) { // Unnecessary

// Don't expect exceptions
try {
    $result = $extractor->extract($html); // Never throws
} catch (Exception $e) { // Won't catch anything
```

---

## Performance

- **Time**: O(n) where n = HTML size
- **Memory**: Entire HTML in memory (DOMDocument)
- **Speed**: ~16ms for typical article (PHP 8.4)

**Recommendations:**
- Cache extracted results
- Limit HTML size to < 10MB
- Set appropriate memory limits

---

## Testing

```bash
# Run tests
composer test:unit -- --filter ArticleExtractorTest

# Expected output
Tests: 28, Assertions: 82
```

---

## More Info

- **Full Docs**: `docs/services/article-extractor.md`
- **Example**: `examples/article-extractor-example.php`
- **Tests**: `tests/Unit/Services/ArticleExtractorTest.php`
- **Source**: `src/Services/ArticleExtractor.php`

---

## Common Issues

### No Title Extracted
```php
// Use fallback
$title = $result['og:title'] ?? 'Untitled Article';
```

### Content Includes Navigation
```php
// ArticleExtractor extracts all body text
// Consider using Readability algorithm for better content isolation
```

### Word Count Seems High
```php
// Word count includes all visible text (navigation, footer, etc.)
// For article-only count, use Readability first
```

### UTF-8 Characters Corrupted
```php
// Ensure HTML has UTF-8 charset
// ArticleExtractor handles this automatically
```

---

## Quick Debug

```php
$result = $extractor->extract($html);

// Check what was extracted
var_dump($result['og:title']);       // Title found?
var_dump($result['content']);        // Content extracted?
var_dump($result['word_count']);     // Word count calculated?
var_dump($result['tags']);           // Tags found?
```

---

**Version**: 1.0.0
**PHP**: 8.1+
**Status**: Production Ready
**Tests**: 28 passing, 82 assertions
