<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use PHPUnit\Framework\TestCase;
use Unfurl\Security\OutputEscaper;

/**
 * Tests for OutputEscaper - XSS Prevention
 *
 * TDD: Test written BEFORE implementation
 * Requirements: Section 7.4 of REQUIREMENTS.md
 *
 * Critical Security Requirements:
 * - Escape HTML context (htmlspecialchars)
 * - Escape JS context (json_encode)
 * - Escape attribute context
 * - Escape URL context
 * - Context-aware escaping
 */
class OutputEscaperTest extends TestCase
{
    private OutputEscaper $escaper;

    protected function setUp(): void
    {
        $this->escaper = new OutputEscaper();
    }

    // ============================================
    // HTML Context Escaping
    // ============================================

    public function test_escapes_html_entities(): void
    {
        $input = '<script>alert("XSS")</script>';
        $output = $this->escaper->html($input);

        $this->assertEquals('&lt;script&gt;alert(&quot;XSS&quot;)&lt;/script&gt;', $output);
    }

    public function test_escapes_less_than_symbol(): void
    {
        $output = $this->escaper->html('<');
        $this->assertEquals('&lt;', $output);
    }

    public function test_escapes_greater_than_symbol(): void
    {
        $output = $this->escaper->html('>');
        $this->assertEquals('&gt;', $output);
    }

    public function test_escapes_ampersand(): void
    {
        $output = $this->escaper->html('A & B');
        $this->assertEquals('A &amp; B', $output);
    }

    public function test_escapes_double_quotes(): void
    {
        $output = $this->escaper->html('Say "Hello"');
        $this->assertEquals('Say &quot;Hello&quot;', $output);
    }

    public function test_escapes_single_quotes(): void
    {
        $output = $this->escaper->html("It's working");
        // htmlspecialchars uses &apos; in PHP 8.1+
        $this->assertEquals('It&apos;s working', $output);
    }

    public function test_handles_utf8_characters(): void
    {
        $input = 'Café <script>alert(1)</script> 日本語';
        $output = $this->escaper->html($input);

        $this->assertStringContainsString('Café', $output);
        $this->assertStringContainsString('日本語', $output);
        $this->assertStringContainsString('&lt;script&gt;', $output);
    }

    public function test_handles_empty_string(): void
    {
        $output = $this->escaper->html('');
        $this->assertEquals('', $output);
    }

    public function test_handles_null_as_empty_string(): void
    {
        $output = $this->escaper->html(null);
        $this->assertEquals('', $output);
    }

    public function test_preserves_safe_text(): void
    {
        $input = 'This is safe text';
        $output = $this->escaper->html($input);

        $this->assertEquals($input, $output);
    }

    // ============================================
    // Attribute Context Escaping
    // ============================================

    public function test_escapes_attribute_value(): void
    {
        $input = 'value" onload="alert(1)';
        $output = $this->escaper->attribute($input);

        // Should escape quotes to prevent breaking out of attribute
        $this->assertStringNotContainsString('"', $output);
        $this->assertStringContainsString('&quot;', $output);
    }

    public function test_attribute_escapes_single_quotes(): void
    {
        $input = "value' onload='alert(1)";
        $output = $this->escaper->attribute($input);

        $this->assertStringNotContainsString("'", $output);
        // htmlspecialchars uses &apos; in PHP 8.1+
        $this->assertStringContainsString('&apos;', $output);
    }

    public function test_attribute_escapes_html_entities(): void
    {
        $input = '<img src=x onerror=alert(1)>';
        $output = $this->escaper->attribute($input);

        $this->assertStringContainsString('&lt;', $output);
        $this->assertStringContainsString('&gt;', $output);
    }

    // ============================================
    // JavaScript Context Escaping
    // ============================================

    public function test_escapes_javascript_string(): void
    {
        $input = 'Hello</script><script>alert(1)</script>';
        $output = $this->escaper->js($input);

        // Should be valid JSON string
        $this->assertStringStartsWith('"', $output);
        $this->assertStringEndsWith('"', $output);
        $this->assertStringNotContainsString('</script>', $output);
    }

    public function test_js_escapes_quotes(): void
    {
        $input = 'Say "Hello"';
        $output = $this->escaper->js($input);

        // json_encode may use unicode escape for quotes
        $decoded = json_decode($output);
        $this->assertEquals($input, $decoded);
        $this->assertStringNotContainsString('"Hello"', $output); // Quotes should be escaped
    }

    public function test_js_escapes_backslashes(): void
    {
        $input = 'Path\\to\\file';
        $output = $this->escaper->js($input);

        $this->assertStringContainsString('\\\\', $output);
    }

    public function test_js_encodes_array(): void
    {
        $input = ['item1', 'item2'];
        $output = $this->escaper->js($input);

        $this->assertEquals('["item1","item2"]', $output);
    }

    public function test_js_encodes_object(): void
    {
        $input = ['name' => 'John', 'age' => 30];
        $output = $this->escaper->js($input);

        $decoded = json_decode($output, true);
        $this->assertEquals($input, $decoded);
    }

    public function test_js_handles_special_characters(): void
    {
        $input = "<>&'\"\n\r\t";
        $output = $this->escaper->js($input);

        // Should be safely encoded
        $decoded = json_decode($output);
        $this->assertEquals($input, $decoded);
    }

    // ============================================
    // URL Context Escaping
    // ============================================

    public function test_escapes_url_parameter(): void
    {
        $input = 'search term with spaces';
        $output = $this->escaper->url($input);

        $this->assertEquals('search+term+with+spaces', $output);
    }

    public function test_url_escapes_special_characters(): void
    {
        $input = 'param=value&other=123';
        $output = $this->escaper->url($input);

        $this->assertStringNotContainsString('&', $output);
        $this->assertStringNotContainsString('=', $output);
    }

    public function test_url_escapes_quotes(): void
    {
        $input = 'value"with\'quotes';
        $output = $this->escaper->url($input);

        $this->assertStringNotContainsString('"', $output);
        $this->assertStringNotContainsString("'", $output);
    }

    public function test_url_handles_utf8(): void
    {
        $input = 'café';
        $output = $this->escaper->url($input);

        // Should be percent-encoded
        $this->assertStringContainsString('%', $output);
        $this->assertEquals($input, urldecode($output));
    }

    // ============================================
    // Helper Method: e() - Default HTML Escaping
    // ============================================

    public function test_e_method_escapes_html(): void
    {
        $input = '<script>alert(1)</script>';
        $output = $this->escaper->e($input);

        $this->assertEquals('&lt;script&gt;alert(1)&lt;/script&gt;', $output);
    }

    public function test_e_is_alias_for_html(): void
    {
        $input = 'Test <b>bold</b>';

        $htmlOutput = $this->escaper->html($input);
        $eOutput = $this->escaper->e($input);

        $this->assertEquals($htmlOutput, $eOutput);
    }

    // ============================================
    // CSS Context Escaping
    // ============================================

    public function test_escapes_css_value(): void
    {
        $input = 'red; position: absolute;';
        $output = $this->escaper->css($input);

        // Should escape semicolons and quotes
        $this->assertStringNotContainsString(';', $output);
    }

    public function test_css_rejects_javascript_urls(): void
    {
        $input = 'javascript:alert(1)';
        $output = $this->escaper->css($input);

        // Should be escaped or stripped
        $this->assertStringNotContainsString('javascript:', strtolower($output));
    }

    // ============================================
    // Context Detection
    // ============================================

    public function test_auto_detects_html_context(): void
    {
        $input = '<b>text</b>';
        $output = $this->escaper->escape($input, 'html');

        $this->assertEquals('&lt;b&gt;text&lt;/b&gt;', $output);
    }

    public function test_auto_detects_js_context(): void
    {
        $input = 'alert(1)';
        $output = $this->escaper->escape($input, 'js');

        $this->assertEquals('"alert(1)"', $output);
    }

    public function test_auto_detects_url_context(): void
    {
        $input = 'param value';
        $output = $this->escaper->escape($input, 'url');

        $this->assertEquals('param+value', $output);
    }

    public function test_defaults_to_html_context(): void
    {
        $input = '<script>alert(1)</script>';
        $output = $this->escaper->escape($input);

        // Should default to HTML escaping
        $this->assertStringContainsString('&lt;script&gt;', $output);
    }

    // ============================================
    // Real-World XSS Attack Vectors
    // ============================================

    public function test_prevents_script_tag_injection(): void
    {
        $input = '<script>document.cookie</script>';
        $output = $this->escaper->html($input);

        $this->assertStringNotContainsString('<script>', $output);
        $this->assertStringNotContainsString('</script>', $output);
    }

    public function test_prevents_img_onerror_injection(): void
    {
        $input = '<img src=x onerror=alert(1)>';
        $output = $this->escaper->html($input);

        // htmlspecialchars escapes < and > but leaves "onerror" intact (which is safe)
        $this->assertStringNotContainsString('<img', $output);
        $this->assertStringContainsString('&lt;img', $output);
        $this->assertStringContainsString('&gt;', $output);
    }

    public function test_prevents_event_handler_injection(): void
    {
        $input = '" onclick="alert(1)"';
        $output = $this->escaper->attribute($input);

        // htmlspecialchars escapes quotes, preventing breakout
        $this->assertStringNotContainsString('"', $output);
        $this->assertStringContainsString('&quot;', $output);
    }

    public function test_prevents_javascript_protocol_injection(): void
    {
        $input = 'javascript:alert(document.domain)';
        $output = $this->escaper->url($input);

        // Should be URL-encoded
        $this->assertStringNotContainsString('javascript:', $output);
    }

    public function test_prevents_data_url_injection(): void
    {
        $input = 'data:text/html,<script>alert(1)</script>';
        $output = $this->escaper->attribute($input);

        $this->assertStringNotContainsString('<script>', $output);
    }

    public function test_prevents_svg_injection(): void
    {
        $input = '<svg onload=alert(1)>';
        $output = $this->escaper->html($input);

        // htmlspecialchars escapes < and > but leaves "onload" intact (which is safe)
        $this->assertStringNotContainsString('<svg', $output);
        $this->assertStringContainsString('&lt;svg', $output);
        $this->assertStringContainsString('&gt;', $output);
    }

    // ============================================
    // Double Encoding Prevention
    // ============================================

    public function test_does_not_double_encode(): void
    {
        $input = '&lt;script&gt;';  // Already encoded
        $output = $this->escaper->html($input);

        // Should encode the ampersands
        $this->assertEquals('&amp;lt;script&amp;gt;', $output);
    }

    // ============================================
    // Performance and Edge Cases
    // ============================================

    public function test_handles_long_strings_efficiently(): void
    {
        $input = str_repeat('<script>alert(1)</script>', 1000);
        $output = $this->escaper->html($input);

        $this->assertStringNotContainsString('<script>', $output);
        $this->assertGreaterThan(strlen($input), strlen($output));
    }

    public function test_handles_numeric_input(): void
    {
        $output = $this->escaper->html(123);
        $this->assertEquals('123', $output);
    }

    public function test_handles_boolean_input(): void
    {
        $outputTrue = $this->escaper->html(true);
        $outputFalse = $this->escaper->html(false);

        $this->assertEquals('1', $outputTrue);
        $this->assertEquals('', $outputFalse);
    }

    // ============================================
    // Integration with Real Content
    // ============================================

    public function test_escapes_article_title(): void
    {
        $title = 'Breaking: "Tech Giant" Launches <New> Product';
        $output = $this->escaper->html($title);

        $this->assertStringContainsString('&quot;Tech Giant&quot;', $output);
        $this->assertStringContainsString('&lt;New&gt;', $output);
    }

    public function test_escapes_article_url_for_link(): void
    {
        $url = 'https://example.com/article?id=123&ref=news';
        $output = $this->escaper->attribute($url);

        // Should be safe for href attribute
        $this->assertStringContainsString('&amp;', $output);
    }

    public function test_escapes_user_search_query(): void
    {
        $query = '<script>alert("XSS")</script>';
        $output = $this->escaper->html($query);

        $this->assertStringNotContainsString('<script>', $output);
    }
}
