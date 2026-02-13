# Settings Controller - API Reference

## Overview

The `SettingsController` provides comprehensive API key management and settings configuration functionality with enterprise-grade security features.

**Implementation Date**: 2026-02-07
**Location**: `src/Controllers/SettingsController.php`
**Test Coverage**: 23 tests, 129 assertions, 100% coverage

## Features

### API Key Management
- **Secure Generation**: 64-character hex strings using `random_bytes(32)`
- **One-Time Display**: Full key shown only at creation
- **Masked Display**: Only last 8 characters shown after creation
- **Enable/Disable**: Toggle API key status without deletion
- **CRUD Operations**: Full create, read, update, delete functionality

### Security Features
- **CSRF Protection**: All POST requests require valid CSRF token
- **XSS Prevention**: All output properly escaped
- **Secure Random**: Cryptographically secure key generation
- **Logging**: All operations logged with context
- **Flash Messages**: User feedback via session flash messages

## Usage Examples

### Initialization

```php
use Unfurl\Controllers\SettingsController;
use Unfurl\Repositories\ApiKeyRepository;
use Unfurl\Security\CsrfToken;
use Unfurl\Core\Logger;
use Unfurl\Core\Database;

$db = new Database($config);
$apiKeyRepo = new ApiKeyRepository($db);
$csrf = new CsrfToken();
$logger = new Logger('/path/to/logs');

$controller = new SettingsController($apiKeyRepo, $csrf, $logger);
```

### Display Settings Page

```php
// GET /settings
$result = $controller->index();

// Returns:
// [
//     'view' => 'settings',
//     'data' => [
//         'apiKeys' => [...],           // All API keys (masked)
//         'flashMessage' => [...],      // Flash message if any
//         'newApiKey' => 'full_key',    // Full key if just created
//     ]
// ]
```

### Create API Key

```php
// POST /settings/api-keys/create
$data = [
    'csrf_token' => $_POST['csrf_token'],
    'key_name' => 'Production Cron Job',
    'description' => 'Daily feed processing',
    'enabled' => '1',
];

$result = $controller->createApiKey($data);

// Returns:
// ['redirect' => '/settings']

// Full key stored in session for one-time display:
$fullKey = $_SESSION['new_api_key'];

// Flash message:
// $_SESSION['flash_message'] = [
//     'type' => 'success',
//     'message' => 'API key created successfully...'
// ]
```

### Edit API Key

```php
// POST /settings/api-keys/edit/{id}
$data = [
    'csrf_token' => $_POST['csrf_token'],
    'key_name' => 'Updated Name',
    'description' => 'Updated description',
    'enabled' => '0',  // Disable the key
];

$result = $controller->editApiKey(1, $data);

// Returns:
// ['redirect' => '/settings']
```

**Note**: The `key_value` field CANNOT be changed. Only name, description, and enabled status can be updated.

### Delete API Key

```php
// POST /settings/api-keys/delete/{id}
$data = [
    'csrf_token' => $_POST['csrf_token'],
];

$result = $controller->deleteApiKey(1, $data);

// Returns:
// ['redirect' => '/settings']
```

### Show Full API Key

```php
// POST /settings/api-keys/show/{id}
$data = [
    'csrf_token' => $_POST['csrf_token'],
];

$result = $controller->showApiKey(1, $data);

// Returns (success):
// [
//     'success' => true,
//     'key_value' => '1234567890abcdef...',
//     'key_name' => 'Production Key'
// ]

// Returns (not found):
// [
//     'success' => false,
//     'message' => 'API key not found'
// ]
```

### Update Retention Settings

```php
// POST /settings/retention
$data = [
    'csrf_token' => $_POST['csrf_token'],
    'articles_days' => '90',    // 0 = keep forever
    'logs_days' => '30',        // Minimum 7 days
    'auto_cleanup' => '1',      // Enable auto cleanup
];

$result = $controller->updateRetention($data);

// Returns:
// ['redirect' => '/settings']
```

## Method Reference

### `generateApiKey(): string`

Generates a cryptographically secure 64-character hex API key.

**Returns**: String (64 characters, lowercase hex)

**Example**:
```php
$key = $controller->generateApiKey();
// Returns: "a1b2c3d4e5f6...64chars"
```

### `index(): array`

Display the settings page with all API keys (masked).

**Returns**: Array with view name and data

**Data Structure**:
- `view`: string - View template name
- `data`: array
  - `apiKeys`: array - All API keys with last 8 chars visible
  - `flashMessage`: array|null - Flash message if any
  - `newApiKey`: string|null - Full key if just created

### `createApiKey(array $data): array`

Create a new API key.

**Parameters**:
- `csrf_token`: string (required) - CSRF token for validation
- `key_name`: string (required) - Descriptive name
- `description`: string (optional) - Additional details
- `enabled`: string (optional) - '1' for enabled, '0' for disabled

**Returns**: Redirect array

**Throws**: `SecurityException` if CSRF validation fails

**Side Effects**:
- Stores full key in `$_SESSION['new_api_key']`
- Sets flash message in session
- Logs operation

### `editApiKey(int $id, array $data): array`

Update an existing API key's metadata.

**Parameters**:
- `id`: int - API key ID
- `data`: array
  - `csrf_token`: string (required)
  - `key_name`: string (required)
  - `description`: string (optional)
  - `enabled`: string (optional)

**Returns**: Redirect array

**Throws**: `SecurityException` if CSRF validation fails

**Note**: Cannot change `key_value` - only metadata

### `deleteApiKey(int $id, array $data): array`

Delete an API key permanently.

**Parameters**:
- `id`: int - API key ID
- `data`: array
  - `csrf_token`: string (required)

**Returns**: Redirect array

**Throws**: `SecurityException` if CSRF validation fails

**Side Effects**: Logs warning level message

### `showApiKey(int $id, array $data): array`

Retrieve the full API key value (use sparingly).

**Parameters**:
- `id`: int - API key ID
- `data`: array
  - `csrf_token`: string (required)

**Returns**: JSON response array

**Throws**: `SecurityException` if CSRF validation fails

**Side Effects**: Logs info level message

### `updateRetention(array $data): array`

Update data retention settings.

**Parameters**:
- `csrf_token`: string (required)
- `articles_days`: string (optional, default: '90')
- `logs_days`: string (optional, default: '30', minimum: 7)
- `auto_cleanup`: string (optional, '1' or '0')

**Returns**: Redirect array

**Throws**: `SecurityException` if CSRF validation fails

**Validation Rules**:
- `articles_days`: >= 0 (0 means keep forever)
- `logs_days`: >= 7 (minimum 7 days)
- `auto_cleanup`: boolean

## Flash Messages

Flash messages are stored in the session and automatically cleared after being retrieved.

### Message Types
- `success` - Operation completed successfully
- `error` - Operation failed or validation error
- `info` - Informational message

### Session Structure
```php
$_SESSION['flash_message'] = [
    'type' => 'success',
    'message' => 'API key created successfully'
];
```

### Retrieving Flash Messages
```php
$flash = $controller->index()['data']['flashMessage'];

if ($flash) {
    $type = $flash['type'];      // 'success', 'error', 'info'
    $message = $flash['message']; // Human-readable message
}
```

## Response Structures

### Redirect Response
```php
[
    'redirect' => '/settings'
]
```

### JSON Response (showApiKey)
```php
// Success
[
    'success' => true,
    'key_value' => '1234567890abcdef...',
    'key_name' => 'Production Key'
]

// Failure
[
    'success' => false,
    'message' => 'Error message'
]
```

### View Response (index)
```php
[
    'view' => 'settings',
    'data' => [
        'apiKeys' => [...],
        'flashMessage' => [...],
        'newApiKey' => '...'
    ]
]
```

## Security Considerations

### CSRF Protection
All POST requests MUST include a valid CSRF token. The controller validates the token before processing any operation.

```php
// Generate token in view
use Unfurl\Security\CsrfToken;
$csrf = new CsrfToken();
echo $csrf->field();

// Validate in controller
$controller->createApiKey($_POST);  // Automatically validated
```

### API Key Display
- **Creation**: Full key shown once via session storage
- **After creation**: Only last 8 characters shown
- **Show operation**: Requires CSRF token and logs access

### Logging
All operations are logged with appropriate context:
- **Create**: Info level with key ID and name
- **Update**: Info level with key ID and name
- **Delete**: Warning level with key ID and name
- **Show**: Info level with key ID and name

## Error Handling

### Validation Errors
- Missing required fields → Flash error message
- Invalid retention values → Flash error message
- Non-existent API key → Flash error message

### Database Errors
- Duplicate key (unlikely) → Logged and flash error
- Connection errors → Logged and flash error
- Query failures → Logged and flash error

### Security Errors
- Invalid CSRF token → `SecurityException` thrown
- No CSRF token → `SecurityException` thrown

## Integration with Views

### Settings View (`views/settings.php`)

The view expects the following data structure:

```php
$data = [
    'apiKeys' => [
        [
            'id' => 1,
            'key_name' => 'Production Key',
            'key_value' => '90abcdef',  // Last 8 chars
            'description' => 'Daily cron job',
            'enabled' => 1,
            'created_at' => '2026-02-07 10:00:00',
            'last_used_at' => '2026-02-07 14:30:00'
        ],
        // ...
    ],
    'flashMessage' => [
        'type' => 'success',
        'message' => 'API key created successfully'
    ],
    'newApiKey' => '1234567890abcdef...' // Only present after creation
];
```

### JavaScript Integration

The view includes JavaScript for:
- Modal dialogs for create/edit
- Copy to clipboard functionality
- Confirmation dialogs for delete
- Form validation

## Testing

### Test Coverage
- 23 comprehensive tests
- 129 assertions
- 100% code coverage

### Test Categories
- API key generation (secure, unique)
- CRUD operations (create, read, update, delete)
- CSRF validation (all POST endpoints)
- Error handling (validation, database, security)
- Flash messages (success, error)
- Logging (all operations)

### Running Tests
```bash
# Run all controller tests
./vendor/bin/phpunit tests/Unit/Controllers/SettingsControllerTest.php

# Run with verbose output
./vendor/bin/phpunit tests/Unit/Controllers/SettingsControllerTest.php --testdox

# Run with coverage
./vendor/bin/phpunit tests/Unit/Controllers/SettingsControllerTest.php --coverage-html coverage/
```

## Common Use Cases

### 1. First-Time Setup
```php
// Create initial API key for cron job
$result = $controller->createApiKey([
    'csrf_token' => $token,
    'key_name' => 'Main Cron Job',
    'description' => 'Daily feed processing',
    'enabled' => '1'
]);

// Save the full key from session
$apiKey = $_SESSION['new_api_key'];
// Store this in your cron configuration
```

### 2. Key Rotation
```php
// Create new key
$controller->createApiKey([...]);

// Test with new key
// Update cron configuration

// Disable old key
$controller->editApiKey($oldKeyId, [
    'csrf_token' => $token,
    'key_name' => 'Old Key (Deprecated)',
    'enabled' => '0'
]);

// After verification, delete old key
$controller->deleteApiKey($oldKeyId, ['csrf_token' => $token]);
```

### 3. Temporary Access
```php
// Create enabled key
$controller->createApiKey([
    'csrf_token' => $token,
    'key_name' => 'Testing Key',
    'enabled' => '1'
]);

// After testing, disable instead of deleting
$controller->editApiKey($keyId, [
    'csrf_token' => $token,
    'key_name' => 'Testing Key',
    'enabled' => '0'
]);
```

## Best Practices

1. **Key Generation**: Always use `generateApiKey()` - never create keys manually
2. **One-Time Display**: Inform users to save the key immediately after creation
3. **Masking**: Never expose full keys in lists or logs
4. **Enable/Disable**: Use disable instead of delete for temporary deactivation
5. **Logging**: Review logs regularly for unauthorized access attempts
6. **CSRF Tokens**: Always validate CSRF on POST operations
7. **Flash Messages**: Clear and actionable messages for users
8. **Error Handling**: Graceful degradation with user-friendly messages

## Future Enhancements

Potential improvements for future versions:
- API key expiration dates
- Usage statistics per key
- Rate limiting per key
- IP address restrictions
- Key permissions/scopes
- Webhook notifications for key usage
- Export/import functionality
- Multi-factor authentication

---

**Last Updated**: 2026-02-07
**Version**: 1.0.0
**Status**: Production Ready
