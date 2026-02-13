# Logging System Documentation

## Overview

The Unfurl logging system provides a PSR-3 compatible file-based logger with structured JSON context, log level filtering, and support for multiple categories.

**Key Features:**
- PSR-3 LoggerInterface compliance
- File-based logging (no database queries)
- Structured JSON context for debugging
- Log level filtering
- Multiple log categories
- Automatic log file rotation by date
- Message interpolation with context variables

## Architecture

### Files

- **`src/Core/Logger.php`** - Main logger implementation
- **`src/Core/LoggerInterface.php`** - PSR-3 compatible interface
- **`tests/Unit/Core/LoggerTest.php`** - Comprehensive test suite (24 tests)
- **`storage/logs/`** - Default log file location

### Log Levels (PSR-3)

Listed from most severe to least severe:

1. **EMERGENCY** (0) - System is unusable
2. **ALERT** (1) - Action must be taken immediately
3. **CRITICAL** (2) - Critical conditions
4. **ERROR** (3) - Runtime errors
5. **WARNING** (4) - Warning conditions
6. **NOTICE** (5) - Normal but significant conditions
7. **INFO** (6) - Informational messages
8. **DEBUG** (7) - Debug-level messages

### Log Categories

Organize logs by category for easier filtering and analysis:

- **system** - Default category, system-level events
- **processing** - Data processing operations
- **api** - API requests and responses
- **security** - Security-related events (logins, access denied, etc.)
- **user_activity** - User actions and interactions
- Custom categories as needed

## Usage

### Basic Logging

```php
use Unfurl\Core\Logger;

// Create logger instance
$logger = new Logger('/path/to/storage/logs');

// Log a message
$logger->info('User logged in', ['user_id' => 42]);
$logger->error('Database connection failed', ['code' => 'DB_CONN_ERROR']);
$logger->warning('Rate limit approaching', ['requests' => 95, 'limit' => 100]);
```

### Log Levels

Use appropriate log level methods:

```php
// System is unusable
$logger->emergency('Server on fire', ['category' => 'system']);

// Action must be taken immediately
$logger->alert('Critical security breach detected', ['category' => 'security']);

// Critical conditions
$logger->critical('Database unavailable', ['category' => 'system']);

// Runtime errors
$logger->error('Failed to process payment', ['payment_id' => 123]);

// Warning conditions
$logger->warning('Slow query detected', ['query_time' => 5.2]);

// Normal but significant conditions
$logger->notice('User account created', ['category' => 'user_activity']);

// Informational messages
$logger->info('Processing started', ['batch_id' => 'ABC123']);

// Debug-level messages (for development)
$logger->debug('Variable dump', ['data' => $variable]);
```

### Context (Structured Data)

Pass context as the second parameter. Context is logged as JSON:

```php
$logger->info('Order placed', [
    'order_id' => 12345,
    'user_id' => 789,
    'total' => 99.99,
    'items' => ['item1', 'item2'],
    'category' => 'user_activity'
]);

// Logged as:
// [2026-02-07 15:02:02] [INFO] [user_activity] Order placed {"order_id":12345,"user_id":789,"total":99.99,"items":["item1","item2"]}
```

### Categories

Specify category via context array. If not provided, defaults to 'system':

```php
// Explicit category
$logger->info('API request received', [
    'endpoint' => '/api/users',
    'method' => 'POST',
    'category' => 'api'
]);

// Default category (system)
$logger->info('Application started');
// Logged to: system-2026-02-07.log
```

### Message Interpolation

Simple placeholders are replaced with context values:

```php
$logger->info('User {user_id} performed {action}', [
    'user_id' => 42,
    'action' => 'login',
    'category' => 'user_activity'
]);

// Logged as:
// [2026-02-07 15:02:02] [INFO] [user_activity] User 42 performed login {"user_id":42,"action":"login"}
```

Complex types (arrays, objects) are not interpolated but included in context JSON:

```php
$logger->info('Data received', [
    'data' => ['nested' => 'value'],
    'category' => 'api'
]);
// Interpolation skips 'data', but it's in the JSON context
```

### Log Level Filtering

Only log messages at the configured level or higher severity:

```php
// Only log WARNING and above (WARNING, ERROR, CRITICAL, ALERT, EMERGENCY)
$logger = new Logger('/path/to/logs', Logger::WARNING);

$logger->error('Error message');      // Logged
$logger->warning('Warning message');  // Logged
$logger->info('Info message');        // NOT logged
$logger->debug('Debug message');      // NOT logged
```

## Log File Format

Log files are named by category and date:

```
system-2026-02-07.log
api-2026-02-07.log
security-2026-02-07.log
```

Each line in a log file follows this format:

```
[YYYY-MM-DD HH:MM:SS] [LEVEL] [category] message {json_context}
```

Example:

```
[2026-02-07 15:02:02] [INFO] [api] Request processed {"endpoint":"/users","status":200}
[2026-02-07 15:02:03] [ERROR] [system] Database error {"code":"ER_DUP_ENTRY","table":"users"}
[2026-02-07 15:02:04] [WARNING] [security] Login attempt with invalid credentials {"user":"admin"}
```

## Configuration

### In config.php

The logger path is configured in `config.php`:

```php
'paths' => [
    'logs' => __DIR__ . '/storage/logs',
],
```

### Runtime Configuration

Override during instantiation:

```php
use Unfurl\Core\Logger;

// Custom log directory
$logger = new Logger('/custom/log/path');

// Custom minimum log level
$logger = new Logger('/path/to/logs', Logger::WARNING);
```

## Best Practices

### 1. Use Meaningful Categories

```php
// Good
$logger->info('Payment processed', ['category' => 'api']);

// Less clear
$logger->info('Payment processed', ['category' => 'misc']);
```

### 2. Include Relevant Context

```php
// Good - context helps with debugging
$logger->error('Failed to create user', [
    'email' => $email,
    'validation_errors' => $errors,
    'category' => 'user_activity'
]);

// Poor - not enough information
$logger->error('User creation failed', ['category' => 'system']);
```

### 3. Use Appropriate Log Levels

```php
// Good
$logger->error('Database connection failed', ['category' => 'system']);
$logger->warning('Slow query detected', ['query_time' => 2.5]);
$logger->info('Batch processing completed', ['items_processed' => 100]);
$logger->debug('Variable inspection', ['var' => $debugging_data]);

// Poor - wrong levels
$logger->error('Batch processing started');  // Should be INFO
$logger->info('Critical database error');    // Should be ERROR
```

### 4. Sanitize Sensitive Data

```php
// GOOD - remove sensitive data
$logger->error('Login failed', [
    'username' => $username,
    // Don't log the password!
    'category' => 'security'
]);

// BAD - logging password
$logger->error('Login failed', [
    'username' => $username,
    'password' => $password,  // SECURITY ISSUE
    'category' => 'security'
]);
```

### 5. Use Consistent Naming

```php
// Good - consistent field names
$logger->info('User action', [
    'user_id' => 42,
    'action' => 'update_profile',
    'category' => 'user_activity'
]);

$logger->info('Another action', [
    'user_id' => 789,
    'action' => 'delete_account',
    'category' => 'user_activity'
]);
```

## Testing

The logging system includes comprehensive tests:

```bash
# Run Logger tests only
php vendor/bin/phpunit tests/Unit/Core/LoggerTest.php

# Run with test descriptions
php vendor/bin/phpunit tests/Unit/Core/LoggerTest.php --testdox

# Run full test suite
php vendor/bin/phpunit
```

### Test Coverage

24 tests covering:
- Log directory creation
- All log levels (Emergency through Debug)
- Multiple categories
- Structured data logging (JSON context)
- Log level filtering
- Message interpolation
- Context preservation
- Log file naming and dating
- PSR-3 compliance
- Empty context handling

## Performance Considerations

### File I/O

- Logs are written with `FILE_APPEND | LOCK_EX` for thread-safe append operations
- One write system call per log entry
- No buffering (writes immediately)

### Log File Rotation

- Automatic daily rotation by date in filename
- Old logs are not automatically deleted (implement retention via cron)
- Each log file grows until midnight, then a new file is created

### Optimal Practices

1. **Use appropriate log level for deployment**
   ```php
   // Production: only WARNING and above
   $logger = new Logger($logDir, Logger::WARNING);

   // Development: DEBUG level
   $logger = new Logger($logDir, Logger::DEBUG);
   ```

2. **Avoid logging in tight loops**
   ```php
   // Bad - logs every iteration
   for ($i = 0; $i < 1000000; $i++) {
       $logger->debug('Processing item', ['i' => $i]);
   }

   // Good - log summary
   $logger->info('Batch processing completed', ['items' => 1000000]);
   ```

3. **Monitor log file sizes**
   - Implement log retention/cleanup policy
   - Consider log archival for historical data

## Troubleshooting

### Logs not appearing

1. Check log directory exists and is writable
   ```bash
   ls -la /path/to/storage/logs/
   ```

2. Verify log level isn't filtering messages
   ```php
   $logger = new Logger($logDir, Logger::DEBUG);  // Lower threshold
   ```

3. Check file permissions
   ```bash
   chmod 755 /path/to/storage/logs/
   ```

### Performance issues

1. Reduce log level in production
   ```php
   $logger = new Logger($logDir, Logger::WARNING);
   ```

2. Monitor log file sizes
   ```bash
   du -sh /path/to/storage/logs/
   ```

3. Implement log retention cleanup

## Future Enhancements

Possible improvements for future versions:

- [ ] Asynchronous logging (background queue)
- [ ] Log rotation/compression
- [ ] Multiple output handlers (database, syslog, etc.)
- [ ] Structured logging (ECS format)
- [ ] Performance metrics/timing
- [ ] Request ID tracking across logs

## Related Documentation

- [PSR-3 Logger Interface Specification](https://www.php-fig.org/psr/psr-3/)
- [12 Factor App Logging](https://12factor.net/logs)
- Security practices in CLAUDE.md

## Changes

- **2026-02-07** - Initial implementation with full TDD approach, 24 comprehensive tests
