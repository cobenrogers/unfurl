<?php

declare(strict_types=1);

namespace Unfurl\Services\RSS;

use Unfurl\Repositories\ArticleRepository;
use DOMDocument;
use DOMElement;

/**
 * RSS Feed Generator
 *
 * Generates valid RSS 2.0 feeds from stored articles with support for:
 * - Filtering (topic, feed_id, status)
 * - Pagination (limit, offset)
 * - Content namespace (content:encoded) for full article text
 * - Image enclosures
 * - Author information
 * - 5-minute file-based caching
 */
class RssFeedGenerator
{
    private ArticleRepository $articleRepository;
    private string $cacheDir;
    private array $config;
    private int $cacheTimeSeconds;

    private const DEFAULT_LIMIT = 20;
    private const MAX_LIMIT = 100;
    private const CACHE_TIME_SECONDS = 300; // 5 minutes

    public function __construct(
        ArticleRepository $articleRepository,
        string $cacheDir,
        array $config,
        int $cacheTimeSeconds = self::CACHE_TIME_SECONDS
    ) {
        $this->articleRepository = $articleRepository;
        $this->cacheDir = rtrim($cacheDir, '/');
        $this->config = $config;
        $this->cacheTimeSeconds = $cacheTimeSeconds;

        // Ensure cache directory exists
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    /**
     * Generate RSS feed XML
     *
     * @param array $filters Filtering options:
     *   - topic: Filter by topic name
     *   - feed_id: Filter by feed ID
     *   - status: Filter by status (default: 'success')
     *   - limit: Number of articles (default: 20, max: 100)
     *   - offset: Pagination offset (default: 0)
     * @return string RSS 2.0 XML
     */
    public function generate(array $filters = []): string
    {
        // Normalize filters
        $filters = $this->normalizeFilters($filters);

        // Check cache
        $cacheKey = $this->generateCacheKey($filters);
        $cached = $this->getFromCache($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        // Generate fresh feed
        $xml = $this->generateFeed($filters);

        // Cache the result
        $this->saveToCache($cacheKey, $xml);

        return $xml;
    }

    /**
     * Normalize filter parameters
     */
    private function normalizeFilters(array $filters): array
    {
        return [
            'topic' => $filters['topic'] ?? null,
            'feed_id' => $filters['feed_id'] ?? null,
            'status' => $filters['status'] ?? 'success',
            'limit' => min((int)($filters['limit'] ?? self::DEFAULT_LIMIT), self::MAX_LIMIT),
            'offset' => (int)($filters['offset'] ?? 0),
        ];
    }

    /**
     * Generate cache key from filters
     */
    private function generateCacheKey(array $filters): string
    {
        $key = 'rss_feed_' . md5(json_encode($filters));
        return $key;
    }

    /**
     * Get cached feed if valid
     */
    private function getFromCache(string $cacheKey): ?string
    {
        $cacheFile = $this->getCacheFilePath($cacheKey);

        if (!file_exists($cacheFile)) {
            return null;
        }

        $fileAge = time() - filemtime($cacheFile);

        if ($fileAge > $this->cacheTimeSeconds) {
            return null; // Cache expired
        }

        return file_get_contents($cacheFile);
    }

    /**
     * Save feed to cache
     */
    private function saveToCache(string $cacheKey, string $xml): void
    {
        $cacheFile = $this->getCacheFilePath($cacheKey);

        // Write with error suppression in case of filesystem issues
        @file_put_contents($cacheFile, $xml, LOCK_EX);
    }

    /**
     * Get cache file path
     */
    private function getCacheFilePath(string $cacheKey): string
    {
        return $this->cacheDir . '/' . $cacheKey . '.xml';
    }

    /**
     * Generate RSS feed XML
     */
    private function generateFeed(array $filters): string
    {
        // Fetch articles
        $articles = $this->fetchArticles($filters);

        // Create DOM document
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = false; // No whitespace for compact output

        // Create RSS root element
        $rss = $doc->createElement('rss');
        $rss->setAttribute('version', '2.0');
        $rss->setAttribute('xmlns:content', 'http://purl.org/rss/1.0/modules/content/');
        $rss->setAttribute('xmlns:dc', 'http://purl.org/dc/elements/1.1/');
        $doc->appendChild($rss);

        // Create channel
        $channel = $this->createChannel($doc, $filters, $articles);
        $rss->appendChild($channel);

        // Add items
        foreach ($articles as $article) {
            $item = $this->createItem($doc, $article);
            $channel->appendChild($item);
        }

        return $doc->saveXML();
    }

    /**
     * Fetch articles from repository based on filters
     */
    private function fetchArticles(array $filters): array
    {
        // Build query filters - this is a simplified approach
        // In production, ArticleRepository would have a method that accepts filters
        $articles = [];

        // Get articles based on filters
        if ($filters['topic']) {
            $articles = $this->articleRepository->findByTopic($filters['topic']);
        } elseif ($filters['feed_id']) {
            $articles = $this->articleRepository->findByFeedId($filters['feed_id']);
        } else {
            // If no specific filter, get all articles with the specified status
            $articles = $this->articleRepository->findByStatus($filters['status']);
        }

        // Filter by status if we got articles from topic or feed_id
        if ($filters['topic'] || $filters['feed_id']) {
            $articles = array_filter($articles, function ($article) use ($filters) {
                return $article['status'] === $filters['status'];
            });
        }

        // Sort by created_at descending (most recent first)
        usort($articles, function ($a, $b) {
            return strcmp($b['created_at'], $a['created_at']);
        });

        // Apply offset and limit
        $articles = array_slice($articles, $filters['offset'], $filters['limit']);

        return $articles;
    }

    /**
     * Create channel element
     */
    private function createChannel(DOMDocument $doc, array $filters, array $articles): DOMElement
    {
        $channel = $doc->createElement('channel');

        // Title
        $title = $this->getChannelTitle($filters);
        $this->appendElement($channel, 'title', $title);

        // Link
        $baseUrl = $this->config['app']['base_url'] ?? 'https://example.com/unfurl/';
        $this->appendElement($channel, 'link', rtrim($baseUrl, '/'));

        // Description
        $description = $this->getChannelDescription($filters);
        $this->appendElement($channel, 'description', $description);

        // Language
        $this->appendElement($channel, 'language', 'en-us');

        // Last Build Date (current time)
        $this->appendElement($channel, 'lastBuildDate', $this->getRfc2822Date(time()));

        // Generator
        $version = $this->config['app']['version'] ?? '1.0';
        $this->appendElement($channel, 'generator', "Unfurl v{$version}");

        return $channel;
    }

    /**
     * Get channel title
     */
    private function getChannelTitle(array $filters): string
    {
        $siteName = $this->config['app']['site_name'] ?? 'Unfurl';

        if ($filters['topic']) {
            return "{$siteName} - {$filters['topic']}";
        }

        return "{$siteName} - All Articles";
    }

    /**
     * Get channel description
     */
    private function getChannelDescription(array $filters): string
    {
        if ($filters['topic']) {
            return "Curated articles about {$filters['topic']}";
        }

        return "Curated news articles from various sources";
    }

    /**
     * Create item (article) element
     */
    private function createItem(DOMDocument $doc, array $article): DOMElement
    {
        $item = $doc->createElement('item');

        // Title (prefer og:title, then page_title, then rss_title)
        $title = $article['og_title'] ?? $article['page_title'] ?? $article['rss_title'] ?? 'Untitled';
        $this->appendElement($item, 'title', $title);

        // Link (final_url)
        $link = $article['final_url'] ?? $article['google_news_url'] ?? '';
        $this->appendElement($item, 'link', $link);

        // Description (prefer og:description, then rss_description)
        $description = $article['og_description'] ?? $article['rss_description'] ?? '';
        $this->appendElement($item, 'description', $description);

        // Content:encoded (full article content)
        if (!empty($article['article_content'])) {
            $this->appendElementWithCdata($item, 'content:encoded', $article['article_content']);
        }

        // pubDate (RFC 2822 format)
        if ($article['pub_date']) {
            $timestamp = strtotime($article['pub_date']);
            if ($timestamp !== false) {
                $this->appendElement($item, 'pubDate', $this->getRfc2822Date($timestamp));
            }
        }

        // guid (unique identifier with isPermaLink attribute)
        $guid = $article['final_url'] ?? $article['google_news_url'] ?? '';
        if ($guid) {
            $guidElement = $doc->createElement('guid', htmlspecialchars($guid, ENT_XML1));
            $guidElement->setAttribute('isPermaLink', 'true');
            $item->appendChild($guidElement);
        }

        // dc:creator (author)
        if (!empty($article['author'])) {
            $creator = $doc->createElementNS('http://purl.org/dc/elements/1.1/', 'dc:creator');
            $creator->textContent = htmlspecialchars($article['author'], ENT_XML1);
            $item->appendChild($creator);
        }

        // Categories
        $this->appendCategories($item, $article);

        // Enclosure (featured image)
        if (!empty($article['og_image'])) {
            $enclosure = $doc->createElement('enclosure');
            $enclosure->setAttribute('url', htmlspecialchars($article['og_image'], ENT_QUOTES, 'UTF-8'));
            $enclosure->setAttribute('type', 'image/jpeg'); // Simplified; could detect type
            $enclosure->setAttribute('length', '0'); // Length unknown
            $item->appendChild($enclosure);
        }

        return $item;
    }

    /**
     * Append categories from topic and parsed categories
     */
    private function appendCategories(DOMElement $item, array $article): void
    {
        $doc = $item->ownerDocument;
        $categories = [];

        // Add topic as category
        if (!empty($article['topic'])) {
            $categories[] = $article['topic'];
        }

        // Add parsed categories if available
        if (!empty($article['categories'])) {
            try {
                $parsed = json_decode($article['categories'], true);
                if (is_array($parsed)) {
                    $categories = array_merge($categories, $parsed);
                }
            } catch (\Exception $e) {
                // Silently skip invalid JSON
            }
        }

        // Remove duplicates
        $categories = array_unique($categories);

        // Append category elements
        foreach ($categories as $category) {
            if (!empty($category)) {
                $this->appendElement($item, 'category', $category);
            }
        }
    }

    /**
     * Append element with text content
     */
    private function appendElement(DOMElement $parent, string $tagName, string $content): DOMElement
    {
        $doc = $parent->ownerDocument;

        // Handle namespaced elements
        if (strpos($tagName, ':') !== false) {
            [$prefix, $localName] = explode(':', $tagName, 2);
            if ($prefix === 'content') {
                $element = $doc->createElementNS('http://purl.org/rss/1.0/modules/content/', $tagName);
            } elseif ($prefix === 'dc') {
                $element = $doc->createElementNS('http://purl.org/dc/elements/1.1/', $tagName);
            } else {
                $element = $doc->createElement($tagName);
            }
        } else {
            $element = $doc->createElement($tagName);
        }

        $element->appendChild($doc->createTextNode($content));
        $parent->appendChild($element);

        return $element;
    }

    /**
     * Append element with CDATA content (for encoded content)
     */
    private function appendElementWithCdata(DOMElement $parent, string $tagName, string $content): DOMElement
    {
        $doc = $parent->ownerDocument;

        // Handle namespaced elements
        if (strpos($tagName, ':') !== false) {
            [$prefix, $localName] = explode(':', $tagName, 2);
            if ($prefix === 'content') {
                $element = $doc->createElementNS('http://purl.org/rss/1.0/modules/content/', $tagName);
            } else {
                $element = $doc->createElement($tagName);
            }
        } else {
            $element = $doc->createElement($tagName);
        }

        // Create CDATA section for content
        $cdata = $doc->createCDATASection($content);
        $element->appendChild($cdata);
        $parent->appendChild($element);

        return $element;
    }

    /**
     * Convert Unix timestamp to RFC 2822 format
     *
     * Example: Fri, 07 Feb 2026 12:30:45 GMT
     */
    private function getRfc2822Date(int $timestamp): string
    {
        return date('D, d M Y H:i:s', $timestamp) . ' GMT';
    }
}
