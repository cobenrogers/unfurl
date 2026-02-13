<?php

declare(strict_types=1);

namespace Unfurl\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use Unfurl\Services\ArticleExtractor;

class ArticleExtractorTest extends TestCase
{
    private ArticleExtractor $extractor;

    protected function setUp(): void
    {
        $this->extractor = new ArticleExtractor();
    }

    /**
     * Sample HTML with full Open Graph metadata
     */
    private function getCompleteHtml(): string
    {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Example Article - News Site</title>
    <meta name="author" content="John Doe">
    <meta property="og:title" content="Breaking News: Technology Advances">
    <meta property="og:description" content="A comprehensive look at recent technology developments and their impact.">
    <meta property="og:image" content="https://example.com/images/tech-article.jpg">
    <meta property="og:url" content="https://example.com/articles/tech-advances">
    <meta property="og:site_name" content="TechNews">
    <meta property="og:type" content="article">
    <meta property="article:published_time" content="2026-02-07T10:00:00Z">
    <meta property="article:author" content="Jane Smith">
    <meta property="article:section" content="Technology">
    <meta property="article:tag" content="AI">
    <meta property="article:tag" content="Innovation">
    <meta property="article:tag" content="Future">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:image" content="https://example.com/images/twitter-card.jpg">
    <meta name="keywords" content="technology, innovation, AI, future">
</head>
<body>
    <header>
        <nav>Navigation menu</nav>
    </header>
    <article>
        <h1>Breaking News: Technology Advances</h1>
        <p>This is the first paragraph of the article content. It contains important information about recent developments.</p>
        <p>The second paragraph continues with more detailed analysis and expert opinions on the matter.</p>
        <script>console.log('ads');</script>
        <p>Final paragraph with concluding thoughts and future implications.</p>
        <style>.ad { display: block; }</style>
    </article>
    <aside>
        <div class="advertisement">Ad content here</div>
    </aside>
    <footer>
        <p>Copyright 2026</p>
    </footer>
</body>
</html>
HTML;
    }

    /**
     * HTML with missing Open Graph metadata (fallback to title tag)
     */
    private function getMinimalHtml(): string
    {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>Simple Article Title</title>
</head>
<body>
    <p>Article content goes here.</p>
</body>
</html>
HTML;
    }

    /**
     * HTML with Twitter metadata but no Open Graph
     */
    private function getTwitterOnlyHtml(): string
    {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta name="twitter:title" content="Twitter Card Title">
    <meta name="twitter:description" content="Twitter card description">
    <meta name="twitter:image" content="https://example.com/twitter-image.jpg">
</head>
<body>
    <p>Content here.</p>
</body>
</html>
HTML;
    }

    /**
     * Malformed HTML to test robustness
     */
    private function getMalformedHtml(): string
    {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta property="og:title" content="Malformed Test">
    <meta property="og:description" content="Testing robustness
<body>
    <p>Unclosed tags everywhere
    <div>More content
    <p>Even more
</html>
HTML;
    }

    /**
     * HTML with UTF-8 characters
     */
    private function getUtf8Html(): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta property="og:title" content="日本語タイトル - Japanese Title">
    <meta property="og:description" content="こんにちは世界 Hello World Привет мир">
</head>
<body>
    <p>Multi-language content: English, 日本語, Русский, العربية, 中文</p>
</body>
</html>
HTML;
    }

    /**
     * HTML with HTML entities in content
     */
    private function getHtmlWithEntities(): string
    {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta property="og:title" content="Testing &quot;Entities&quot; &amp; Symbols">
    <meta property="og:description" content="Price: $100 &lt; $200 &gt; $50">
</head>
<body>
    <p>Content with entities: &nbsp; &copy; &reg; &trade;</p>
</body>
</html>
HTML;
    }

    public function testExtractOpenGraphTitle(): void
    {
        $result = $this->extractor->extract($this->getCompleteHtml());

        $this->assertEquals('Breaking News: Technology Advances', $result['og:title']);
    }

    public function testExtractOpenGraphDescription(): void
    {
        $result = $this->extractor->extract($this->getCompleteHtml());

        $this->assertEquals(
            'A comprehensive look at recent technology developments and their impact.',
            $result['og:description']
        );
    }

    public function testExtractOpenGraphImage(): void
    {
        $result = $this->extractor->extract($this->getCompleteHtml());

        $this->assertEquals('https://example.com/images/tech-article.jpg', $result['og:image']);
    }

    public function testExtractOpenGraphUrl(): void
    {
        $result = $this->extractor->extract($this->getCompleteHtml());

        $this->assertEquals('https://example.com/articles/tech-advances', $result['og:url']);
    }

    public function testExtractOpenGraphSiteName(): void
    {
        $result = $this->extractor->extract($this->getCompleteHtml());

        $this->assertEquals('TechNews', $result['og:site_name']);
    }

    public function testExtractAuthor(): void
    {
        $result = $this->extractor->extract($this->getCompleteHtml());

        // Should extract from article:author first, fall back to author meta
        $this->assertEquals('Jane Smith', $result['author']);
    }

    public function testExtractTwitterImage(): void
    {
        $result = $this->extractor->extract($this->getCompleteHtml());

        $this->assertEquals('https://example.com/images/twitter-card.jpg', $result['twitter:image']);
    }

    public function testExtractArticleTags(): void
    {
        $result = $this->extractor->extract($this->getCompleteHtml());

        $this->assertIsArray($result['tags']);
        $this->assertCount(3, $result['tags']);
        $this->assertContains('AI', $result['tags']);
        $this->assertContains('Innovation', $result['tags']);
        $this->assertContains('Future', $result['tags']);
    }

    public function testExtractArticleSection(): void
    {
        $result = $this->extractor->extract($this->getCompleteHtml());

        $this->assertEquals('Technology', $result['section']);
    }

    public function testExtractPlainTextContent(): void
    {
        $result = $this->extractor->extract($this->getCompleteHtml());

        $this->assertArrayHasKey('content', $result);
        $this->assertIsString($result['content']);

        // Should contain text from paragraphs
        $this->assertStringContainsString('first paragraph', $result['content']);
        $this->assertStringContainsString('second paragraph', $result['content']);
        $this->assertStringContainsString('Final paragraph', $result['content']);

        // Should NOT contain script content
        $this->assertStringNotContainsString('console.log', $result['content']);

        // Should NOT contain style content
        $this->assertStringNotContainsString('.ad { display: block; }', $result['content']);

        // Should NOT contain HTML tags
        $this->assertStringNotContainsString('<p>', $result['content']);
        $this->assertStringNotContainsString('</p>', $result['content']);
    }

    public function testCalculateWordCount(): void
    {
        $result = $this->extractor->extract($this->getCompleteHtml());

        $this->assertArrayHasKey('word_count', $result);
        $this->assertIsInt($result['word_count']);
        $this->assertGreaterThan(0, $result['word_count']);

        // Rough check - should be around 50-70 words in the article body
        $this->assertGreaterThan(30, $result['word_count']);
        $this->assertLessThan(100, $result['word_count']);
    }

    public function testFallbackToTitleTag(): void
    {
        $result = $this->extractor->extract($this->getMinimalHtml());

        // When og:title is missing, fall back to <title> tag
        $this->assertEquals('Simple Article Title', $result['og:title']);
    }

    public function testHandleMissingMetadata(): void
    {
        $result = $this->extractor->extract($this->getMinimalHtml());

        // Missing metadata should return null, not throw errors
        $this->assertNull($result['og:description']);
        $this->assertNull($result['og:image']);
        $this->assertNull($result['og:url']);
        $this->assertNull($result['og:site_name']);
        $this->assertNull($result['author']);
        $this->assertNull($result['section']);
    }

    public function testHandleEmptyTagsArray(): void
    {
        $result = $this->extractor->extract($this->getMinimalHtml());

        // When no tags exist, should return empty array
        $this->assertIsArray($result['tags']);
        $this->assertCount(0, $result['tags']);
    }

    public function testTwitterMetadataFallback(): void
    {
        $result = $this->extractor->extract($this->getTwitterOnlyHtml());

        // Twitter metadata can be used as fallback
        $this->assertEquals('https://example.com/twitter-image.jpg', $result['twitter:image']);
    }

    public function testHandleMalformedHtml(): void
    {
        // Should not throw exception on malformed HTML
        $result = $this->extractor->extract($this->getMalformedHtml());

        $this->assertIsArray($result);
        $this->assertEquals('Malformed Test', $result['og:title']);

        // DOMDocument should still extract what it can
        $this->assertArrayHasKey('content', $result);
        $this->assertArrayHasKey('word_count', $result);
    }

    public function testHandleUtf8Content(): void
    {
        $result = $this->extractor->extract($this->getUtf8Html());

        // Should properly handle UTF-8 characters
        $this->assertStringContainsString('日本語', $result['og:title']);
        $this->assertStringContainsString('こんにちは', $result['og:description']);
        $this->assertStringContainsString('日本語', $result['content']);
        $this->assertStringContainsString('Русский', $result['content']);
    }

    public function testHandleHtmlEntities(): void
    {
        $result = $this->extractor->extract($this->getHtmlWithEntities());

        // HTML entities should be decoded
        $this->assertStringContainsString('"Entities"', $result['og:title']);
        $this->assertStringContainsString('&', $result['og:title']);
        $this->assertStringContainsString('<', $result['og:description']);
        $this->assertStringContainsString('>', $result['og:description']);
    }

    public function testEmptyHtmlString(): void
    {
        $result = $this->extractor->extract('');

        // Should handle empty string gracefully
        $this->assertIsArray($result);
        $this->assertNull($result['og:title']);
        $this->assertArrayHasKey('content', $result);
        $this->assertEquals(0, $result['word_count']);
    }

    public function testInvalidHtmlString(): void
    {
        // Just random text, not HTML
        $result = $this->extractor->extract('This is just plain text without HTML tags');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('content', $result);
        $this->assertArrayHasKey('word_count', $result);
    }

    public function testExtractPublishedTime(): void
    {
        $result = $this->extractor->extract($this->getCompleteHtml());

        $this->assertEquals('2026-02-07T10:00:00Z', $result['published_time']);
    }

    public function testMultipleArticleTagsExtraction(): void
    {
        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta property="article:tag" content="Tag1">
    <meta property="article:tag" content="Tag2">
    <meta property="article:tag" content="Tag3">
    <meta property="article:tag" content="Tag4">
</head>
<body><p>Content</p></body>
</html>
HTML;

        $result = $this->extractor->extract($html);

        $this->assertCount(4, $result['tags']);
        $this->assertEquals(['Tag1', 'Tag2', 'Tag3', 'Tag4'], $result['tags']);
    }

    public function testStripScriptAndStyleContent(): void
    {
        $html = <<<HTML
<!DOCTYPE html>
<html>
<body>
    <p>Before script</p>
    <script>
        var dangerous = "code";
        alert('hello');
    </script>
    <p>Between</p>
    <style>
        body { background: red; }
        .class { display: none; }
    </style>
    <p>After style</p>
</body>
</html>
HTML;

        $result = $this->extractor->extract($html);

        $this->assertStringContainsString('Before script', $result['content']);
        $this->assertStringContainsString('Between', $result['content']);
        $this->assertStringContainsString('After style', $result['content']);

        $this->assertStringNotContainsString('dangerous', $result['content']);
        $this->assertStringNotContainsString('alert', $result['content']);
        $this->assertStringNotContainsString('background: red', $result['content']);
    }

    public function testWordCountAccuracy(): void
    {
        $html = <<<HTML
<!DOCTYPE html>
<html>
<body>
    <p>One two three four five six seven eight nine ten.</p>
</body>
</html>
HTML;

        $result = $this->extractor->extract($html);

        // Should count exactly 10 words
        $this->assertEquals(10, $result['word_count']);
    }

    public function testReturnStructure(): void
    {
        $result = $this->extractor->extract($this->getCompleteHtml());

        // Verify all expected keys exist in result
        $expectedKeys = [
            'og:title',
            'og:description',
            'og:image',
            'og:url',
            'og:site_name',
            'twitter:image',
            'author',
            'published_time',
            'section',
            'tags',
            'content',
            'word_count'
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $result);
        }
    }

    public function testPreserveWhitespaceInContent(): void
    {
        $html = <<<HTML
<!DOCTYPE html>
<html>
<body>
    <p>First paragraph.</p>
    <p>Second paragraph.</p>
</body>
</html>
HTML;

        $result = $this->extractor->extract($html);

        // Should have some separation between paragraphs
        $this->assertStringContainsString('First paragraph', $result['content']);
        $this->assertStringContainsString('Second paragraph', $result['content']);
    }

    public function testExtractFromArticleAuthorFallback(): void
    {
        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta name="author" content="Fallback Author">
</head>
<body><p>Content</p></body>
</html>
HTML;

        $result = $this->extractor->extract($html);

        // Should fall back to author meta tag when article:author is missing
        $this->assertEquals('Fallback Author', $result['author']);
    }

    public function testNoAuthorReturnsNull(): void
    {
        $html = <<<HTML
<!DOCTYPE html>
<html>
<head><title>Test</title></head>
<body><p>Content</p></body>
</html>
HTML;

        $result = $this->extractor->extract($html);

        $this->assertNull($result['author']);
    }
}
