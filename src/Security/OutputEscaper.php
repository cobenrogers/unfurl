<?php

declare(strict_types=1);

namespace Unfurl\Security;

/**
 * OutputEscaper - XSS Prevention
 *
 * Provides context-aware output escaping to prevent Cross-Site Scripting attacks.
 *
 * Security Features:
 * - HTML context escaping
 * - JavaScript context escaping
 * - URL parameter escaping
 * - Attribute context escaping
 * - CSS context escaping
 *
 * Requirements: Section 7.4 of REQUIREMENTS.md
 */
class OutputEscaper
{
    /**
     * Escape output for HTML context
     *
     * Use for displaying user content in HTML body.
     *
     * Example:
     * ```php
     * echo '<div>' . $escaper->html($title) . '</div>';
     * ```
     *
     * @param mixed $value Value to escape
     * @return string Escaped value safe for HTML context
     */
    public function html(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Escape output for HTML attribute context
     *
     * Use for displaying user content in HTML attributes.
     *
     * Example:
     * ```php
     * echo '<img alt="' . $escaper->attribute($altText) . '">';
     * ```
     *
     * @param mixed $value Value to escape
     * @return string Escaped value safe for attribute context
     */
    public function attribute(mixed $value): string
    {
        // Same as HTML escaping with ENT_QUOTES
        return $this->html($value);
    }

    /**
     * Escape output for JavaScript context
     *
     * Use for embedding data in JavaScript code.
     * Returns JSON-encoded string.
     *
     * Example:
     * ```php
     * echo '<script>const title = ' . $escaper->js($title) . ';</script>';
     * ```
     *
     * @param mixed $value Value to escape
     * @return string JSON-encoded value safe for JS context
     */
    public function js(mixed $value): string
    {
        return json_encode($value, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    }

    /**
     * Escape output for URL parameter context
     *
     * Use for encoding values in URL query strings.
     *
     * Example:
     * ```php
     * echo '<a href="/search?q=' . $escaper->url($query) . '">Search</a>';
     * ```
     *
     * @param mixed $value Value to escape
     * @return string URL-encoded value
     */
    public function url(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return urlencode((string)$value);
    }

    /**
     * Escape output for CSS context
     *
     * Use for embedding values in CSS.
     * This is a basic implementation - avoid user input in CSS when possible.
     *
     * @param mixed $value Value to escape
     * @return string Escaped value
     */
    public function css(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        $value = (string)$value;

        // Remove dangerous characters and patterns
        $value = preg_replace('/[^\w\s\-#,.]/', '', $value);

        // Remove javascript: and other dangerous protocols
        $value = preg_replace('/javascript:/i', '', $value);

        return $value;
    }

    /**
     * Default escape method (alias for html)
     *
     * Convenience method - defaults to HTML context escaping.
     *
     * @param mixed $value Value to escape
     * @return string Escaped value
     */
    public function e(mixed $value): string
    {
        return $this->html($value);
    }

    /**
     * Context-aware escaping
     *
     * Escape based on specified context.
     *
     * @param mixed $value Value to escape
     * @param string $context Context type: 'html', 'js', 'url', 'css', 'attribute'
     * @return string Escaped value
     */
    public function escape(mixed $value, string $context = 'html'): string
    {
        return match ($context) {
            'js', 'javascript' => $this->js($value),
            'url' => $this->url($value),
            'css' => $this->css($value),
            'attribute', 'attr' => $this->attribute($value),
            default => $this->html($value),
        };
    }
}
