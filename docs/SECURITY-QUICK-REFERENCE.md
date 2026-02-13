# Security Quick Reference

Quick guide for using Unfurl's security components.

---

## 🛡️ SSRF Protection

**Use before fetching ANY external URL**

```php
use Unfurl\Security\UrlValidator;
use Unfurl\Exceptions\SecurityException;

$validator = new UrlValidator();

try {
    // ALWAYS validate before fetch
    $validator->validate($url);
    $content = file_get_contents($url);
} catch (SecurityException $e) {
    // Log and handle safely
    error_log('SSRF blocked: ' . $e->getMessage());
}
```

**What it blocks:**
- ❌ Private IPs (10.x, 192.168.x, 127.x)
- ❌ AWS metadata (169.254.169.254)
- ❌ IPv6 private addresses
- ❌ file://, javascript:, data: schemes
- ❌ URLs over 2000 characters

**What it allows:**
- ✅ Public HTTP/HTTPS URLs only

---

## 🔐 CSRF Protection

**Use in ALL forms that modify state**

```php
use Unfurl\Security\CsrfToken;

$csrf = new CsrfToken();

// === In View (Form) ===
<form method="POST" action="/feeds/create">
    <?= $csrf->field() ?>
    <input name="topic" required>
    <button>Create</button>
</form>

// === In Controller (Handler) ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $csrf->validateFromPost();
        // Process form safely
    } catch (SecurityException $e) {
        // Reject request
        http_response_code(403);
        echo 'Invalid CSRF token';
    }
}
```

**Key Features:**
- ✅ Auto-generates secure 64-char tokens
- ✅ Timing-attack safe validation
- ✅ Auto-regenerates after validation

---

## ✅ Input Validation

**Use for ALL user input**

```php
use Unfurl\Security\InputValidator;
use Unfurl\Exceptions\ValidationException;

$validator = new InputValidator();

try {
    // Validate feed data
    $validated = $validator->validateFeed($_POST);

    // Use $validated (safe, sanitized, typed)
    $topic = $validated['topic'];   // string, 1-255 chars
    $url = $validated['url'];       // valid Google News URL
    $limit = $validated['limit'];   // int, 1-100

} catch (ValidationException $e) {
    // Get field-specific errors
    $errors = $e->getErrors();
    // ['topic' => 'Topic is required', 'url' => 'Invalid URL']

    // Display errors to user
    foreach ($errors as $field => $message) {
        echo "$field: $message<br>";
    }
}
```

**Helper Methods:**
```php
// String validation
$value = $validator->validateString(
    $_POST['name'],
    1,              // min length
    100,            // max length
    'name',         // field name
    '/^[a-zA-Z]+$/' // optional pattern
);

// Integer validation
$count = $validator->validateInteger(
    $_POST['count'],
    1,              // min value
    100,            // max value
    'count'         // field name
);

// URL validation
$url = $validator->validateUrl(
    $_POST['url'],
    'url',                  // field name
    ['google.com']          // allowed hosts
);
```

---

## 🔒 XSS Prevention (Output Escaping)

**ALWAYS escape user content before display**

```php
use Unfurl\Security\OutputEscaper;

$escaper = new OutputEscaper();

// === HTML Context ===
echo '<div>' . $escaper->html($article['title']) . '</div>';
echo '<h1>' . $escaper->html($userInput) . '</h1>';

// === Attribute Context ===
echo '<img alt="' . $escaper->attribute($altText) . '" src="...">';
echo '<a href="' . $escaper->attribute($url) . '">Link</a>';

// === JavaScript Context ===
echo '<script>';
echo 'const title = ' . $escaper->js($title) . ';';
echo 'const data = ' . $escaper->js($arrayData) . ';';
echo '</script>';

// === URL Context ===
echo '<a href="/search?q=' . $escaper->url($query) . '">Search</a>';

// === Shorthand (defaults to HTML) ===
echo $escaper->e($userInput);
```

**When to Use Each Context:**

| Context | When to Use | Method |
|---------|-------------|--------|
| HTML | Text inside tags | `html()` or `e()` |
| Attribute | Text in attributes | `attribute()` |
| JavaScript | Data in `<script>` | `js()` |
| URL | Query parameters | `url()` |

**❌ Never Do This:**
```php
// DANGEROUS - Raw output
echo $article['title'];
echo "<div>$userInput</div>";
```

**✅ Always Do This:**
```php
// SAFE - Escaped output
echo $escaper->html($article['title']);
echo '<div>' . $escaper->html($userInput) . '</div>';
```

---

## 🚨 Exception Handling

### SecurityException
Thrown for security violations (SSRF, CSRF, etc.)

```php
try {
    $validator->validate($url);
} catch (SecurityException $e) {
    error_log('Security violation: ' . $e->getMessage());
    http_response_code(403);
    echo 'Access denied';
}
```

### ValidationException
Thrown for input validation failures

```php
try {
    $validated = $validator->validateFeed($_POST);
} catch (ValidationException $e) {
    $errors = $e->getErrors();
    // Display field errors to user
}
```

---

## 📋 Checklist for New Features

When adding new functionality, ensure:

### Before Fetching URLs
- [ ] Validate with `UrlValidator` to prevent SSRF

### For All Forms
- [ ] Add CSRF token with `$csrf->field()`
- [ ] Validate token in handler with `$csrf->validateFromPost()`

### For All User Input
- [ ] Validate with `InputValidator`
- [ ] Use validated/sanitized data only
- [ ] Handle `ValidationException` gracefully

### For All Output
- [ ] Escape with `OutputEscaper`
- [ ] Use correct context (html, js, url, attribute)
- [ ] Never output raw user data

---

## 🔍 Testing

Run security tests:

```bash
# All security tests
vendor/bin/phpunit tests/Unit/Security/ --testdox

# Specific component
vendor/bin/phpunit tests/Unit/Security/UrlValidatorTest.php
vendor/bin/phpunit tests/Unit/Security/CsrfTokenTest.php
vendor/bin/phpunit tests/Unit/Security/InputValidatorTest.php
vendor/bin/phpunit tests/Unit/Security/OutputEscaperTest.php
```

---

## 📚 Further Reading

- **REQUIREMENTS.md** - Section 7 (Full security requirements)
- **SECURITY-LAYER-IMPLEMENTATION.md** - Complete implementation details
- **OWASP Top 10** - https://owasp.org/Top10/

---

*Last Updated: 2026-02-07*
