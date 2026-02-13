# Testing Guide

Comprehensive testing documentation for Unfurl - Google News URL Decoder & RSS Feed Generator.

## Table of Contents

- [Overview](#overview)
- [Test Suite Statistics](#test-suite-statistics)
- [Running Tests](#running-tests)
- [Test Suites](#test-suites)
- [Writing Tests](#writing-tests)
- [Test Coverage](#test-coverage)
- [CI/CD Integration](#cicd-integration)
- [Performance Testing](#performance-testing)
- [Security Testing](#security-testing)
- [Best Practices](#best-practices)

## Overview

Unfurl was built using **Test-Driven Development (TDD)** methodology. All features have comprehensive test coverage ensuring reliability and maintainability.

### Testing Philosophy

- **Write tests first** - Tests define expected behavior before implementation
- **100% coverage** - All code paths tested
- **Fast feedback** - Tests run in seconds
- **Isolated tests** - Each test independent
- **Meaningful assertions** - Tests verify actual behavior

### Test Pyramid

```
     /\
    /  \    E2E Tests (Future)
   /----\
  /      \  Integration Tests (Database)
 /--------\
/__________\ Unit Tests (Fast, Isolated)
```

## Test Suite Statistics

**Current Status (2026-02-07):**

- **Total Tests**: 464
- **Total Assertions**: 1,448
- **Pass Rate**: 100%
- **Execution Time**: < 5 seconds
- **Code Coverage**: 100% of core functionality

### Breakdown by Suite

| Suite | Tests | Assertions | Focus |
|-------|-------|------------|-------|
| Unit | 352 | 1,098 | Business logic, utilities |
| Integration | 100 | 320 | Database operations |
| Performance | 12 | 30 | Speed, memory, scalability |

### Breakdown by Component

| Component | Tests | Coverage |
|-----------|-------|----------|
| Security Layer | 170 | 100% |
| Database Layer | 89 | 100% |
| Google News Services | 67 | 100% |
| Controllers | 58 | 100% |
| Processing Queue | 25 | 100% |
| RSS Generation | 23 | 100% |
| Logging | 20 | 100% |
| Performance | 12 | N/A |

## Running Tests

### Requirements

- PHP 8.1+
- Composer dependencies installed
- SQLite (for integration tests) or MySQL

### Installation

```bash
# Install with dev dependencies
composer install

# Verify PHPUnit installed
vendor/bin/phpunit --version
```

### Basic Commands

```bash
# Run all tests
composer test

# Run specific suite
composer test:unit
composer test:integration
composer test:performance

# Generate coverage report
composer test:coverage

# Run specific test file
vendor/bin/phpunit tests/Unit/Security/CsrfTokenTest.php

# Run specific test method
vendor/bin/phpunit --filter testValidateTokenSuccess

# Verbose output
vendor/bin/phpunit --verbose

# Stop on first failure
vendor/bin/phpunit --stop-on-failure
```

### Test Output

**Successful run:**
```
PHPUnit 10.5.63 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.14
Configuration: /path/to/phpunit.xml

............................................................... 63 / 464
............................................................... 126 / 464
............................................................... 189 / 464
............................................................... 252 / 464
............................................................... 315 / 464
............................................................... 378 / 464
............................................................... 441 / 464
.......................                                        464 / 464

Time: 00:04.521, Memory: 24.00 MB

OK (464 tests, 1448 assertions)
```

## Test Suites

### Unit Tests

**Location**: `tests/Unit/`

**Purpose**: Test individual classes and methods in isolation

**Characteristics**:
- No database connections
- Uses mocks for dependencies
- Extremely fast (< 2 seconds total)
- Can run anywhere

**Example:**
```php
namespace Unfurl\Tests\Unit\Security;

use PHPUnit\Framework\TestCase;
use Unfurl\Security\CsrfToken;

class CsrfTokenTest extends TestCase
{
    public function testGenerateTokenCreatesValidToken(): void
    {
        $csrf = new CsrfToken();
        $token = $csrf->generateToken();

        $this->assertIsString($token);
        $this->assertEquals(64, strlen($token));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
    }
}
```

**Run unit tests:**
```bash
composer test:unit
```

### Integration Tests

**Location**: `tests/Integration/`

**Purpose**: Test components working together with real database

**Characteristics**:
- Uses SQLite or MySQL database
- Tests actual SQL queries
- Verifies repository operations
- Tests transaction handling

**Example:**
```php
namespace Unfurl\Tests\Integration\Repositories;

use PHPUnit\Framework\TestCase;
use Unfurl\Repositories\ArticleRepository;
use PDO;

class ArticleRepositoryTest extends TestCase
{
    private PDO $db;
    private ArticleRepository $repo;

    protected function setUp(): void
    {
        // Setup test database
        $this->db = new PDO('sqlite::memory:');
        $this->db->exec(file_get_contents('sql/schema.sql'));
        $this->repo = new ArticleRepository($this->db);
    }

    public function testCreateArticle(): void
    {
        $data = [
            'feed_id' => 1,
            'topic' => 'technology',
            'final_url' => 'https://example.com/article',
            // ... more fields
        ];

        $id = $this->repo->create($data);
        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);
    }
}
```

**Run integration tests:**
```bash
composer test:integration
```

### Performance Tests

**Location**: `tests/Performance/`

**Purpose**: Verify application meets performance requirements

**Characteristics**:
- Measures execution time
- Tracks memory usage
- Counts database queries
- Tests caching effectiveness

**Example:**
```php
public function testBulkArticleProcessing(): void
{
    $start = microtime(true);

    // Process 100 articles
    for ($i = 0; $i < 100; $i++) {
        $this->repo->create($articleData);
    }

    $duration = microtime(true) - $start;

    // Should process 100 articles in under 1 second
    $this->assertLessThan(1.0, $duration);
}
```

**Performance Benchmarks:**

| Operation | Requirement | Actual |
|-----------|------------|--------|
| Article list page | < 2s | 0.52ms |
| RSS generation | < 1s | 2.22ms |
| Cached RSS | < 100ms | 0.04ms |
| Bulk processing | N/A | 100 items in 0.01s |

**Run performance tests:**
```bash
composer test:performance

# View generated report
cat docs/PERFORMANCE-REPORT.md
```

## Writing Tests

### Test Structure

Follow Arrange-Act-Assert (AAA) pattern:

```php
public function testMethodName(): void
{
    // Arrange - Setup test data and dependencies
    $dependency = $this->createMock(DependencyClass::class);
    $sut = new SystemUnderTest($dependency);

    // Act - Execute the method being tested
    $result = $sut->methodToTest($input);

    // Assert - Verify expected behavior
    $this->assertEquals($expected, $result);
}
```

### Naming Conventions

**Test class names:**
- Format: `{ClassName}Test`
- Example: `CsrfTokenTest`, `ArticleRepositoryTest`

**Test method names:**
- Format: `test{MethodName}{Scenario}`
- Use descriptive names
- Examples:
  - `testValidateTokenSuccess()`
  - `testValidateTokenThrowsExceptionWhenInvalid()`
  - `testCreateArticleWithValidData()`

### Using Mocks

```php
use PHPUnit\Framework\TestCase;

class MyTest extends TestCase
{
    public function testWithMock(): void
    {
        // Create mock
        $logger = $this->createMock(LoggerInterface::class);

        // Set expectations
        $logger->expects($this->once())
            ->method('info')
            ->with($this->equalTo('Test message'));

        // Use mock
        $service = new MyService($logger);
        $service->doSomething();
    }
}
```

### Testing Exceptions

```php
public function testThrowsException(): void
{
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Invalid input');

    $service->methodThatThrows('invalid');
}
```

### Data Providers

For testing multiple inputs:

```php
/**
 * @dataProvider urlProvider
 */
public function testValidateUrl(string $url, bool $expected): void
{
    $validator = new UrlValidator();
    $result = $validator->isValid($url);

    $this->assertEquals($expected, $result);
}

public static function urlProvider(): array
{
    return [
        'valid https' => ['https://example.com', true],
        'valid http' => ['http://example.com', true],
        'invalid protocol' => ['ftp://example.com', false],
        'private ip' => ['http://192.168.1.1', false],
    ];
}
```

### Setup and Teardown

```php
class MyTest extends TestCase
{
    private $resource;

    protected function setUp(): void
    {
        // Run before each test
        $this->resource = new Resource();
    }

    protected function tearDown(): void
    {
        // Run after each test
        $this->resource = null;
    }
}
```

## Test Coverage

### Generating Coverage Reports

```bash
# HTML report
composer test:coverage

# Open in browser
open coverage/index.html

# Text report
vendor/bin/phpunit --coverage-text

# Clover XML (for CI/CD)
vendor/bin/phpunit --coverage-clover coverage.xml
```

### Coverage Requirements

- **Minimum**: 80% overall coverage
- **Target**: 100% for critical paths
- **Security components**: 100% required
- **Controllers**: 90%+ required
- **Utilities**: 100% required

### Interpreting Coverage

**100% coverage does NOT mean bug-free!**

Coverage measures:
- Line coverage: Which lines executed
- Branch coverage: Which if/else paths taken
- Method coverage: Which methods called

Always verify:
- Edge cases tested
- Error conditions tested
- Integration scenarios tested
- Performance acceptable

## CI/CD Integration

### GitHub Actions

**Workflow file**: `.github/workflows/test.yml`

```yaml
name: Tests

on:
  push:
    branches: [ main, develop ]
  pull_request:
    branches: [ main ]

jobs:
  test:
    runs-on: ubuntu-latest

    strategy:
      matrix:
        php-version: ['8.1', '8.2', '8.3']

    steps:
    - uses: actions/checkout@v3

    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: ${{ matrix.php-version }}
        extensions: pdo, pdo_mysql, json, curl, dom, mbstring
        coverage: xdebug

    - name: Install dependencies
      run: composer install --prefer-dist --no-progress

    - name: Run unit tests
      run: composer test:unit

    - name: Run integration tests
      run: composer test:integration

    - name: Run performance tests
      run: composer test:performance

    - name: Generate coverage
      run: composer test:coverage

    - name: Upload coverage to Codecov
      uses: codecov/codecov-action@v3
      with:
        files: ./coverage.xml
```

### Pre-Commit Hooks

Setup automatic testing before commits:

```bash
# Create pre-commit hook
cat > .git/hooks/pre-commit << 'EOF'
#!/bin/sh
composer test
EOF

# Make executable
chmod +x .git/hooks/pre-commit
```

### Continuous Deployment

**Deployment only triggers if all tests pass:**

1. Push to `main` branch
2. GitHub Actions runs test suite
3. If tests pass → deploy to production
4. If tests fail → deployment blocked

## Performance Testing

### Running Performance Tests

```bash
# Run all performance tests
composer test:performance

# View detailed report
cat docs/PERFORMANCE-REPORT.md
```

### Performance Metrics

**Metrics collected:**

- **Timing**: Execution time, time per item
- **Memory**: Memory used, peak memory
- **Database**: Query count, queries per item
- **Cache**: Hit rate, speedup factor

**Example output:**
```
Performance Test Results
========================

Article List Performance:
  - Duration: 0.52ms
  - Memory: 2.1MB
  - Queries: 2
  - Status: PASS (< 2000ms)

RSS Generation (Uncached):
  - Duration: 2.22ms
  - Memory: 1.8MB
  - Queries: 1
  - Status: PASS (< 1000ms)

RSS Generation (Cached):
  - Duration: 0.04ms
  - Cache speedup: 29.38x
  - Status: PASS (< 100ms)
```

### Interpreting Results

**Green (PASS)**: Meets or exceeds requirements
**Yellow (WARNING)**: Close to limits, monitor
**Red (FAIL)**: Below requirements, needs optimization

## Security Testing

### Security Test Coverage

**Components tested:**

1. **SSRF Protection** (45 tests)
   - Private IP blocking
   - Localhost blocking
   - DNS rebinding protection
   - Protocol validation

2. **CSRF Protection** (35 tests)
   - Token generation
   - Token validation
   - Timing-safe comparison
   - Session handling

3. **Input Validation** (48 tests)
   - Whitelist validation
   - Type checking
   - Length limits
   - Format validation

4. **XSS Prevention** (42 tests)
   - HTML escaping
   - Attribute escaping
   - JavaScript escaping
   - URL encoding

### Running Security Tests

```bash
# Run all security tests
vendor/bin/phpunit tests/Unit/Security/

# Run specific component
vendor/bin/phpunit tests/Unit/Security/CsrfTokenTest.php
```

### OWASP Top 10 Coverage

| Vulnerability | Protected | Tested |
|--------------|-----------|---------|
| Injection | Yes | 67 tests |
| Broken Auth | Yes | 23 tests |
| XSS | Yes | 42 tests |
| Insecure Design | Yes | Architecture |
| Security Misconfiguration | Yes | Config validation |
| Vulnerable Components | Yes | Dependency scanning |
| Authentication Failures | Yes | 23 tests |
| Data Integrity Failures | Yes | CSRF, validation |
| Logging Failures | Yes | 20 tests |
| SSRF | Yes | 45 tests |

## Best Practices

### DO

- Write tests before code (TDD)
- Test one thing per test
- Use descriptive test names
- Keep tests simple and readable
- Mock external dependencies
- Test edge cases and errors
- Run tests before committing
- Maintain test independence
- Update tests when code changes

### DON'T

- Skip writing tests
- Test implementation details
- Create brittle tests
- Ignore failing tests
- Mix unit and integration tests
- Hard-code test data
- Share state between tests
- Test external services directly
- Leave commented-out tests

### Code Review Checklist

When reviewing tests:

- [ ] Tests are clear and well-named
- [ ] Each test has meaningful assertions
- [ ] Edge cases are covered
- [ ] Error conditions are tested
- [ ] No hard-coded values
- [ ] Mocks used appropriately
- [ ] Tests are independent
- [ ] Coverage is adequate
- [ ] Performance is acceptable

## Troubleshooting

### Tests Failing Locally

**Check:**
1. Composer dependencies up to date: `composer install`
2. Database schema current: reimport `sql/schema.sql`
3. PHP version: `php -v` (must be 8.1+)
4. Extensions loaded: `php -m`
5. File permissions correct

### Slow Tests

**Solutions:**
1. Run unit tests only: `composer test:unit`
2. Use `--stop-on-failure` flag
3. Run specific test file
4. Check for database connection issues
5. Disable coverage: `composer test --no-coverage`

### Coverage Report Issues

**Solutions:**
1. Install Xdebug: `pecl install xdebug`
2. Enable in php.ini: `zend_extension=xdebug.so`
3. Verify: `php -v` (should show Xdebug)
4. Clear coverage cache: `rm -rf coverage/`

### Integration Tests Fail

**Solutions:**
1. Check database connection
2. Verify schema loaded
3. Check for table locks
4. Review SQL syntax errors
5. Use SQLite for faster tests

## Resources

### Documentation

- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [Test-Driven Development](https://en.wikipedia.org/wiki/Test-driven_development)
- [OWASP Testing Guide](https://owasp.org/www-project-web-security-testing-guide/)

### Internal Documentation

- `docs/PERFORMANCE-TESTING.md` - Performance testing guide
- `docs/SECURITY-LAYER-IMPLEMENTATION.md` - Security testing details
- `docs/INTEGRATION-TESTS-SUMMARY.md` - Integration test summary

### Quick Reference

```bash
# Common commands
composer test                    # Run all tests
composer test:unit              # Unit tests only
composer test:integration       # Integration tests
composer test:performance       # Performance tests
composer test:coverage          # With coverage report

# Advanced
vendor/bin/phpunit --filter testName       # Specific test
vendor/bin/phpunit --stop-on-failure      # Stop on first fail
vendor/bin/phpunit --testdox              # Readable output
vendor/bin/phpunit --verbose              # Detailed output
```

---

**Last Updated**: 2026-02-07
**Version**: 1.0.0
**Test Status**: All 464 tests passing
