<?php

declare(strict_types=1);

namespace Unfurl\Services;

use DOMDocument;
use DOMXPath;

/**
 * Extracts article metadata and content from HTML
 *
 * Extracts:
 * - Open Graph metadata (og:title, og:description, og:image, og:url, og:site_name)
 * - Twitter Card metadata (twitter:image)
 * - Article metadata (author, published_time, section, tags)
 * - Plain text content (HTML stripped)
 * - Word count
 *
 * Handles:
 * - Missing metadata gracefully (returns null)
 * - Malformed HTML
 * - UTF-8 encoding
 * - HTML entities
 */
class ArticleExtractor
{
    /**
     * Extract metadata and content from HTML
     *
     * @param string $html Raw HTML content
     * @return array{
     *   og:title: ?string,
     *   og:description: ?string,
     *   og:image: ?string,
     *   og:url: ?string,
     *   og:site_name: ?string,
     *   twitter:image: ?string,
     *   author: ?string,
     *   published_time: ?string,
     *   section: ?string,
     *   tags: array<string>,
     *   content: string,
     *   word_count: int
     * }
     */
    public function extract(string $html): array
    {
        // Handle empty HTML
        if (empty($html)) {
            return $this->emptyResult();
        }

        // Parse HTML with DOMDocument
        $doc = $this->parseHtml($html);
        $xpath = new DOMXPath($doc);

        // Extract metadata
        $result = [
            'og:title' => $this->extractMetaProperty($xpath, 'og:title')
                ?? $this->extractTitle($doc),
            'og:description' => $this->extractMetaProperty($xpath, 'og:description'),
            'og:image' => $this->extractMetaProperty($xpath, 'og:image'),
            'og:url' => $this->extractMetaProperty($xpath, 'og:url'),
            'og:site_name' => $this->extractMetaProperty($xpath, 'og:site_name'),
            'twitter:image' => $this->extractMetaName($xpath, 'twitter:image'),
            'author' => $this->extractAuthor($xpath),
            'published_time' => $this->extractMetaProperty($xpath, 'article:published_time'),
            'section' => $this->extractMetaProperty($xpath, 'article:section'),
            'tags' => $this->extractTags($xpath),
            'content' => '',
            'word_count' => 0
        ];

        // Extract and clean content
        $content = $this->extractContent($doc);
        $result['content'] = $content;
        $result['word_count'] = $this->calculateWordCount($content);

        return $result;
    }

    /**
     * Parse HTML string into DOMDocument
     * Handles malformed HTML and UTF-8 encoding
     */
    private function parseHtml(string $html): DOMDocument
    {
        $doc = new DOMDocument();

        // Suppress warnings from malformed HTML
        libxml_use_internal_errors(true);

        // Add UTF-8 encoding declaration if not present
        if (!preg_match('/encoding=(["\'])UTF-8\1/i', $html)) {
            $html = '<?xml encoding="UTF-8">' . $html;
        }

        // Load HTML
        $doc->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        // Clear error buffer
        libxml_clear_errors();
        libxml_use_internal_errors(false);

        return $doc;
    }

    /**
     * Extract meta tag by property attribute
     */
    private function extractMetaProperty(DOMXPath $xpath, string $property): ?string
    {
        $nodes = $xpath->query("//meta[@property='$property']");

        if ($nodes && $nodes->length > 0) {
            $content = $nodes->item(0)->getAttribute('content');
            return !empty($content) ? html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8') : null;
        }

        return null;
    }

    /**
     * Extract meta tag by name attribute
     */
    private function extractMetaName(DOMXPath $xpath, string $name): ?string
    {
        $nodes = $xpath->query("//meta[@name='$name']");

        if ($nodes && $nodes->length > 0) {
            $content = $nodes->item(0)->getAttribute('content');
            return !empty($content) ? html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8') : null;
        }

        return null;
    }

    /**
     * Extract page title as fallback
     */
    private function extractTitle(DOMDocument $doc): ?string
    {
        $titleNodes = $doc->getElementsByTagName('title');

        if ($titleNodes->length > 0) {
            $title = $titleNodes->item(0)->textContent;
            return !empty($title) ? trim(html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8')) : null;
        }

        return null;
    }

    /**
     * Extract author from article:author or author meta tag
     */
    private function extractAuthor(DOMXPath $xpath): ?string
    {
        // Try article:author first
        $author = $this->extractMetaProperty($xpath, 'article:author');
        if ($author !== null) {
            return $author;
        }

        // Fall back to author meta tag
        return $this->extractMetaName($xpath, 'author');
    }

    /**
     * Extract all article tags
     * Returns array of tag strings
     */
    private function extractTags(DOMXPath $xpath): array
    {
        $tags = [];
        $nodes = $xpath->query("//meta[@property='article:tag']");

        if ($nodes) {
            foreach ($nodes as $node) {
                $content = $node->getAttribute('content');
                if (!empty($content)) {
                    $tags[] = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                }
            }
        }

        return $tags;
    }

    /**
     * Extract plain text content from HTML
     * Strips HTML tags, scripts, styles
     */
    private function extractContent(DOMDocument $doc): string
    {
        // Clone document to avoid modifying original
        $clone = $doc->cloneNode(true);

        // Remove script tags and their content
        $scripts = $clone->getElementsByTagName('script');
        while ($scripts->length > 0) {
            $scripts->item(0)->parentNode->removeChild($scripts->item(0));
        }

        // Remove style tags and their content
        $styles = $clone->getElementsByTagName('style');
        while ($styles->length > 0) {
            $styles->item(0)->parentNode->removeChild($styles->item(0));
        }

        // Get text content
        $bodyNodes = $clone->getElementsByTagName('body');
        if ($bodyNodes->length > 0) {
            $text = $bodyNodes->item(0)->textContent;
        } else {
            // If no body tag, get all text
            $text = $clone->textContent;
        }

        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Normalize whitespace
        $text = preg_replace('/\s+/u', ' ', $text);
        $text = trim($text);

        return $text;
    }

    /**
     * Calculate word count from text
     */
    private function calculateWordCount(string $text): int
    {
        if (empty($text)) {
            return 0;
        }

        // Split by whitespace and filter empty strings
        $words = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY);

        return is_array($words) ? count($words) : 0;
    }

    /**
     * Return empty result structure
     */
    private function emptyResult(): array
    {
        return [
            'og:title' => null,
            'og:description' => null,
            'og:image' => null,
            'og:url' => null,
            'og:site_name' => null,
            'twitter:image' => null,
            'author' => null,
            'published_time' => null,
            'section' => null,
            'tags' => [],
            'content' => '',
            'word_count' => 0
        ];
    }
}
