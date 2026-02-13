<?php

/**
 * Article Extractor Example
 *
 * Demonstrates how to use ArticleExtractor to extract metadata and content from HTML
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Unfurl\Services\ArticleExtractor;

// Sample HTML from a news article
$html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Breaking: AI Breakthrough Announced - TechNews</title>
    <meta name="author" content="Dr. Sarah Johnson">
    <meta property="og:title" content="Major AI Breakthrough Announced by Research Team">
    <meta property="og:description" content="Researchers have unveiled a groundbreaking AI model that demonstrates unprecedented reasoning capabilities, marking a significant milestone in artificial intelligence development.">
    <meta property="og:image" content="https://example.com/images/ai-breakthrough.jpg">
    <meta property="og:url" content="https://example.com/articles/ai-breakthrough-2026">
    <meta property="og:site_name" content="TechNews Daily">
    <meta property="og:type" content="article">
    <meta property="article:published_time" content="2026-02-07T09:30:00Z">
    <meta property="article:author" content="Dr. Sarah Johnson">
    <meta property="article:section" content="Artificial Intelligence">
    <meta property="article:tag" content="AI">
    <meta property="article:tag" content="Machine Learning">
    <meta property="article:tag" content="Research">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:image" content="https://example.com/images/twitter-ai.jpg">
</head>
<body>
    <header>
        <nav>
            <a href="/">Home</a> | <a href="/tech">Technology</a>
        </nav>
    </header>

    <main>
        <article>
            <h1>Major AI Breakthrough Announced by Research Team</h1>

            <div class="meta">
                <span>By Dr. Sarah Johnson</span>
                <span>February 7, 2026</span>
            </div>

            <img src="https://example.com/images/ai-breakthrough.jpg" alt="AI Research Lab">

            <p>In a landmark announcement today, a team of researchers from leading institutions
            unveiled a new artificial intelligence model that demonstrates unprecedented reasoning
            and problem-solving capabilities.</p>

            <p>The breakthrough represents years of collaborative work across multiple disciplines,
            combining advances in neural architecture, training methodologies, and computational
            efficiency. Early tests show the model performing complex tasks that were previously
            thought to require human-level understanding.</p>

            <h2>Key Innovations</h2>

            <p>The research team highlighted several key innovations that contributed to the
            breakthrough, including novel attention mechanisms, improved training stability,
            and better generalization across domains.</p>

            <p>"This work opens up entirely new possibilities for AI applications," said lead
            researcher Dr. Sarah Johnson. "We're seeing capabilities that go beyond pattern
            matching to genuine reasoning and understanding."</p>

            <script>
                // Analytics code
                trackPageView('/articles/ai-breakthrough');
            </script>

            <p>The implications for fields ranging from healthcare to scientific research are
            profound. The team plans to publish their findings in a peer-reviewed journal next
            month and will release portions of their training methodology to the research community.</p>

            <style>
                .advertisement { border: 1px solid #ccc; }
            </style>
        </article>

        <aside>
            <div class="advertisement">
                <p>Advertisement</p>
            </div>
        </aside>
    </main>

    <footer>
        <p>&copy; 2026 TechNews Daily. All rights reserved.</p>
    </footer>
</body>
</html>
HTML;

// Create extractor instance
$extractor = new ArticleExtractor();

// Extract metadata and content
$result = $extractor->extract($html);

// Display results
echo "=== Article Metadata Extraction ===\n\n";

echo "Open Graph Metadata:\n";
echo "- Title: " . ($result['og:title'] ?? 'N/A') . "\n";
echo "- Description: " . ($result['og:description'] ?? 'N/A') . "\n";
echo "- Image: " . ($result['og:image'] ?? 'N/A') . "\n";
echo "- URL: " . ($result['og:url'] ?? 'N/A') . "\n";
echo "- Site Name: " . ($result['og:site_name'] ?? 'N/A') . "\n\n";

echo "Article Metadata:\n";
echo "- Author: " . ($result['author'] ?? 'N/A') . "\n";
echo "- Published: " . ($result['published_time'] ?? 'N/A') . "\n";
echo "- Section: " . ($result['section'] ?? 'N/A') . "\n";
echo "- Tags: " . implode(', ', $result['tags']) . "\n\n";

echo "Social Media:\n";
echo "- Twitter Image: " . ($result['twitter:image'] ?? 'N/A') . "\n\n";

echo "Content Analysis:\n";
echo "- Word Count: " . $result['word_count'] . "\n";
echo "- Content Preview (first 200 chars): " . substr($result['content'], 0, 200) . "...\n\n";

echo "Full Plain Text Content:\n";
echo "----------------------------------------\n";
echo wordwrap($result['content'], 80) . "\n";
echo "----------------------------------------\n\n";

// Demonstrate handling of missing metadata
echo "\n=== Handling Missing Metadata ===\n\n";

$minimalHtml = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>Basic Article</title>
</head>
<body>
    <p>This is a simple article with minimal metadata.</p>
</body>
</html>
HTML;

$minimalResult = $extractor->extract($minimalHtml);

echo "Missing metadata returns null (not errors):\n";
echo "- og:description: " . var_export($minimalResult['og:description'], true) . "\n";
echo "- og:image: " . var_export($minimalResult['og:image'], true) . "\n";
echo "- author: " . var_export($minimalResult['author'], true) . "\n";
echo "- tags: " . var_export($minimalResult['tags'], true) . "\n";
echo "- Title (fallback): " . $minimalResult['og:title'] . "\n";
echo "- Content: " . $minimalResult['content'] . "\n";
echo "- Word count: " . $minimalResult['word_count'] . "\n\n";

echo "✓ ArticleExtractor successfully handles all scenarios!\n";
