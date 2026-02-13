# ArticleExtractor Service

## Overview

The `ArticleExtractor` service extracts article metadata and content from HTML documents. It parses Open Graph metadata, Twitter Card data, article-specific metadata, and plain text content while handling malformed HTML, missing metadata, and various character encodings gracefully.

## Features

- **Open Graph Metadata**: Extracts og:title, og:description, og:image, og:url, og:site_name
- **Twitter Card Metadata**: Extracts twitter:image for social media
- **Article Metadata**: Author, published time, section, tags/categories
- **Content Extraction**: Strips HTML to plain text, removes scripts and styles
- **Word Count**: Calculates accurate word count from extracted content
- **Robust Parsing**: Handles malformed HTML, missing metadata, UTF-8 encoding, HTML entities
- **Fallback Logic**: Falls back to `<title>` tag when og:title missing, author meta when article:author missing

## Installation

The ArticleExtractor requires PHP 8.1+ with the following extensions:
- `ext-dom` - For HTML parsing
- `ext-mbstring` - For UTF-8 string handling

```bash
composer require bennernet/unfurl
```

## Basic Usage

```php
use Unfurl\Services\ArticleExtractor;

$extractor = new ArticleExtractor();

// Extract from HTML string
$html = file_get_contents('https://example.com/article');
$result = $extractor->extract($html);

// Access extracted data
echo "Title: " . $result['og:title'] . "\n";
echo "Description: " . $result['og:description'] . "\n";
echo "Word Count: " . $result['word_count'] . "\n";
echo "Tags: " . implode(', ', $result['tags']) . "\n";
```

## Return Structure

The `extract()` method returns an associative array with the following structure:

```php
[
    'og:title' => ?string,         // Open Graph title or page title fallback
    'og:description' => ?string,   // Open Graph description
    'og:image' => ?string,         // Open Graph image URL
    'og:url' => ?string,           // Canonical URL
    'og:site_name' => ?string,     // Site/publication name
    'twitter:image' => ?string,    // Twitter Card image URL
    'author' => ?string,           // Article author (article:author or author meta)
    'published_time' => ?string,   // ISO 8601 timestamp
    'section' => ?string,          // Article section/category
    'tags' => array<string>,       // Array of article tags (empty if none)
    'content' => string,           // Plain text content (scripts/styles removed)
    'word_count' => int            // Word count of content
]
```

**Note**: Missing metadata returns `null`, not errors. This allows graceful handling of incomplete HTML.

## Metadata Extraction

### Open Graph Protocol

```php
$result = $extractor->extract($html);

// Open Graph metadata
$title = $result['og:title'];          // og:title meta property
$description = $result['og:description']; // og:description meta property
$image = $result['og:image'];          // og:image meta property
$url = $result['og:url'];              // og:url meta property
$siteName = $result['og:site_name'];   // og:site_name meta property
```

### Twitter Card Metadata

```php
// Twitter-specific metadata
$twitterImage = $result['twitter:image']; // twitter:image meta name
```

### Article Metadata

```php
// Article-specific metadata
$author = $result['author'];           // article:author or author meta
$published = $result['published_time']; // article:published_time
$section = $result['section'];         // article:section
$tags = $result['tags'];               // article:tag (array of all tags)
```

### Fallback Logic

When primary metadata is missing, ArticleExtractor applies fallback logic:

```php
// Title fallback: og:title → <title> tag → null
if ($result['og:title'] === null) {
    // No Open Graph title found, fell back to <title> tag or null
}

// Author fallback: article:author → author meta → null
if ($result['author'] !== null) {
    // Found author from article:author or author meta tag
}
```

## Content Extraction

### Plain Text Content

The extractor strips all HTML tags, scripts, and styles to provide clean plain text:

```php
$result = $extractor->extract($html);

$content = $result['content'];     // Plain text, no HTML tags
$wordCount = $result['word_count']; // Accurate word count

// Content has:
// ✓ All HTML tags removed
// ✓ Script tags and content removed
// ✓ Style tags and content removed
// ✓ HTML entities decoded
// ✓ Whitespace normalized
// ✗ No markup or formatting
```

### What Gets Removed

- **HTML tags**: `<p>`, `<div>`, `<span>`, etc.
- **Scripts**: `<script>` tags and their JavaScript content
- **Styles**: `<style>` tags and their CSS content
- **Comments**: HTML comments
- **Navigation**: Headers, footers, aside elements (text extracted but context may be lost)

### What Gets Preserved

- **Text content**: All visible text from the HTML body
- **Whitespace**: Normalized to single spaces
- **Special characters**: Properly decoded from HTML entities
- **Unicode**: Full UTF-8 support for international content

## Advanced Usage

### Handling Missing Metadata

```php
$result = $extractor->extract($minimalHtml);

// Safe null checks
$description = $result['og:description'] ?? 'No description available';
$author = $result['author'] ?? 'Unknown author';

// Tags always returns array (empty if none)
$tags = $result['tags']; // array, never null
$tagCount = count($tags);

// Content and word_count always present (empty string / 0 if no content)
$content = $result['content'];     // string, never null
$wordCount = $result['word_count']; // int, never null
```

### UTF-8 Content

ArticleExtractor fully supports UTF-8 and international characters:

```php
$html = <<<HTML
<html>
<head>
    <meta property="og:title" content="日本語タイトル - Japanese Title">
</head>
<body>
    <p>Content: English, 日本語, Русский, العربية, 中文</p>
</body>
</html>
HTML;

$result = $extractor->extract($html);

// UTF-8 characters preserved correctly
echo $result['og:title'];   // "日本語タイトル - Japanese Title"
echo $result['content'];    // "Content: English, 日本語, Русский, العربية, 中文"
```

### HTML Entity Decoding

HTML entities are automatically decoded:

```php
$html = <<<HTML
<meta property="og:title" content="Testing &quot;Quotes&quot; &amp; Symbols">
<meta property="og:description" content="Price: $100 &lt; $200">
HTML;

$result = $extractor->extract($html);

echo $result['og:title'];       // Testing "Quotes" & Symbols
echo $result['og:description']; // Price: $100 < $200
```

### Malformed HTML

ArticleExtractor handles malformed HTML gracefully:

```php
$malformedHtml = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta property="og:title" content="Malformed Test">
<body>
    <p>Unclosed tags
    <div>More content
HTML;

// Does not throw exception, extracts what it can
$result = $extractor->extract($malformedHtml);

echo $result['og:title'];  // "Malformed Test"
echo $result['content'];   // Extracted text content
```

### Multiple Article Tags

When multiple `article:tag` meta tags exist, all are extracted:

```php
$html = <<<HTML
<head>
    <meta property="article:tag" content="Technology">
    <meta property="article:tag" content="AI">
    <meta property="article:tag" content="Innovation">
</head>
HTML;

$result = $extractor->extract($html);

print_r($result['tags']);
// Array
// (
//     [0] => Technology
//     [1] => AI
//     [2] => Innovation
// )
```

## Use Cases

### 1. RSS Feed Generation

Extract article metadata to populate RSS feed items:

```php
$extractor = new ArticleExtractor();
$result = $extractor->extract($articleHtml);

$rssItem = [
    'title' => $result['og:title'] ?? 'Untitled',
    'description' => $result['og:description'] ?? substr($result['content'], 0, 200),
    'link' => $result['og:url'] ?? $articleUrl,
    'pubDate' => $result['published_time'] ?? date('c'),
    'author' => $result['author'],
    'category' => $result['section'],
];
```

### 2. Article Preview Cards

Generate social media preview cards:

```php
$result = $extractor->extract($html);

echo '<div class="preview-card">';
echo '<img src="' . htmlspecialchars($result['og:image'] ?? '') . '">';
echo '<h3>' . htmlspecialchars($result['og:title'] ?? 'Untitled') . '</h3>';
echo '<p>' . htmlspecialchars($result['og:description'] ?? '') . '</p>';
echo '<span>' . $result['word_count'] . ' words</span>';
echo '</div>';
```

### 3. Content Analysis

Analyze article content and metadata:

```php
$result = $extractor->extract($html);

$readingTime = ceil($result['word_count'] / 200); // ~200 words per minute

echo "Reading time: {$readingTime} min\n";
echo "Topics: " . implode(', ', $result['tags']) . "\n";
echo "Section: " . ($result['section'] ?? 'Uncategorized') . "\n";
```

### 4. Search Indexing

Extract content for full-text search indexing:

```php
$result = $extractor->extract($html);

$searchDocument = [
    'title' => $result['og:title'],
    'description' => $result['og:description'],
    'content' => $result['content'],
    'author' => $result['author'],
    'tags' => $result['tags'],
    'word_count' => $result['word_count'],
];

// Index in search engine (Elasticsearch, Algolia, etc.)
$searchEngine->index($searchDocument);
```

## Testing

ArticleExtractor is thoroughly tested with 28 unit tests covering:

- Open Graph metadata extraction
- Twitter Card metadata
- Article metadata (author, tags, section, published time)
- Content extraction and HTML stripping
- Word count calculation
- Missing metadata handling (graceful degradation)
- Malformed HTML parsing
- UTF-8 encoding support
- HTML entity decoding
- Script and style content removal
- Fallback logic for missing data

Run tests:

```bash
composer test:unit -- --filter ArticleExtractorTest
```

See `tests/Unit/Services/ArticleExtractorTest.php` for comprehensive test examples.

## Error Handling

ArticleExtractor does **not** throw exceptions for:
- Missing metadata
- Malformed HTML
- Empty HTML strings
- Invalid HTML structure

Instead, it returns `null` for missing metadata and empty/default values for content and word count. This design ensures graceful degradation.

```php
// Empty HTML
$result = $extractor->extract('');
// Returns: all metadata null, content='', word_count=0

// Malformed HTML
$result = $extractor->extract('<html><head>broken...');
// Returns: extracted metadata where possible, content extracted

// Missing metadata
$result = $extractor->extract('<html><body>Just text</body></html>');
// Returns: metadata fields null, content extracted, word_count calculated
```

## Performance Considerations

- **Memory**: DOMDocument loads entire HTML into memory
- **Processing**: Single-pass extraction, minimal overhead
- **Cloning**: Content extraction clones DOM to avoid mutations
- **libxml errors**: Suppressed during parsing, cleared after

For large HTML documents (>10MB), consider:
- Stream processing for initial content fetching
- Chunking or limiting HTML size before extraction
- Caching extracted results

## Best Practices

1. **Validate URLs before fetching**:
   ```php
   if (filter_var($url, FILTER_VALIDATE_URL)) {
       $html = file_get_contents($url);
       $result = $extractor->extract($html);
   }
   ```

2. **Handle network failures separately**:
   ```php
   try {
       $html = file_get_contents($url);
       $result = $extractor->extract($html);
   } catch (Exception $e) {
       // Handle network/fetch errors, not extraction errors
   }
   ```

3. **Provide fallbacks for UI**:
   ```php
   $title = $result['og:title'] ?? $result['og:url'] ?? 'Untitled';
   $description = $result['og:description'] ?? substr($result['content'], 0, 200);
   ```

4. **Sanitize output for HTML display**:
   ```php
   echo htmlspecialchars($result['og:title'], ENT_QUOTES, 'UTF-8');
   ```

5. **Cache extracted results**:
   ```php
   $cacheKey = 'article_' . md5($url);
   $result = $cache->get($cacheKey) ?? $extractor->extract($html);
   $cache->set($cacheKey, $result, 3600);
   ```

## Limitations

- **Context loss**: Navigation, headers, footers are included in content extraction
- **No DOM structure**: Only plain text, no paragraph or section separation preserved
- **No image analysis**: Only extracts image URLs, not dimensions or alt text
- **No embedded content**: iframes, embeds, videos not extracted
- **Single language**: No automatic language detection or translation

For more advanced HTML parsing needs, consider using ArticleExtractor alongside:
- HTML sanitizers for safe HTML output
- Readability algorithms for better content extraction
- Language detection libraries
- Computer vision for image analysis

## Related Documentation

- [Google News URL Decoder](./google-news-url-decoder.md)
- [RSS Feed Generator](./rss-feed-generator.md)
- [Processing Queue](./processing-queue.md)

## License

MIT License - See LICENSE file for details.
