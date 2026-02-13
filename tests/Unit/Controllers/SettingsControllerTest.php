<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use PHPUnit\Framework\TestCase;
use Unfurl\Controllers\SettingsController;
use Unfurl\Repositories\ApiKeyRepository;
use Unfurl\Security\CsrfToken;
use Unfurl\Core\Logger;
use Unfurl\Exceptions\SecurityException;

/**
 * Settings Controller Test
 *
 * Comprehensive tests for SettingsController API key management and settings operations.
 *
 * Test Coverage:
 * - Secure API key generation (64 character hex)
 * - Create API key with validation
 * - Edit API key (name, description, enabled)
 * - Delete API key
 * - Show API key (one-time display)
 * - Enable/disable API key
 * - CSRF token validation
 * - API key uniqueness handling
 * - Flash messages
 * - Logging operations
 */
class SettingsControllerTest extends TestCase
{
    private SettingsController $controller;
    private ApiKeyRepository $mockRepository;
    private CsrfToken $mockCsrf;
    private Logger $mockLogger;

    protected function setUp(): void
    {
        parent::setUp();

        // Create mocks
        $this->mockRepository = $this->createMock(ApiKeyRepository::class);
        $this->mockCsrf = $this->createMock(CsrfToken::class);
        $this->mockLogger = $this->createMock(Logger::class);

        // Create controller instance
        $this->controller = new SettingsController(
            $this->mockRepository,
            $this->mockCsrf,
            $this->mockLogger
        );

        // Clear session before each test
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    protected function tearDown(): void
    {
        // Clean up session after each test
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        parent::tearDown();
    }

    /**
     * Test: Generate secure API key returns 64 character hex string
     */
    public function testGenerateApiKeyReturns64CharacterHexString(): void
    {
        $key = $this->controller->generateApiKey();

        $this->assertIsString($key);
        $this->assertEquals(64, strlen($key), 'API key should be exactly 64 characters');
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $key, 'API key should be lowercase hexadecimal');
    }

    /**
     * Test: Generate API key produces unique values
     */
    public function testGenerateApiKeyProducesUniqueValues(): void
    {
        $key1 = $this->controller->generateApiKey();
        $key2 = $this->controller->generateApiKey();

        $this->assertNotEquals($key1, $key2, 'Generated keys should be unique');
    }

    /**
     * Test: Index method returns view data with unmasked API keys
     *
     * Note: Keys are NOT masked on the settings page because users need
     * to see their own keys via the "Show Key" functionality.
     */
    public function testIndexReturnsViewDataWithUnmaskedApiKeys(): void
    {
        $apiKeys = [
            [
                'id' => 1,
                'key_name' => 'Test Key',
                'key_value' => '1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef',
                'description' => 'Test description',
                'enabled' => 1,
            ],
            [
                'id' => 2,
                'key_name' => 'Another Key',
                'key_value' => 'fedcba0987654321fedcba0987654321fedcba0987654321fedcba0987654321',
                'description' => null,
                'enabled' => 0,
            ],
        ];

        $this->mockRepository->expects($this->once())
            ->method('findAll')
            ->willReturn($apiKeys);

        $this->mockLogger->expects($this->once())
            ->method('info')
            ->with('Settings page viewed', ['category' => 'settings']);

        $result = $this->controller->index();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('apiKeys', $result);
        $this->assertCount(2, $result['apiKeys']);

        // Check that keys are NOT masked (full value returned)
        $this->assertEquals('1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef', $result['apiKeys'][0]['key_value']);
        $this->assertEquals('fedcba0987654321fedcba0987654321fedcba0987654321fedcba0987654321', $result['apiKeys'][1]['key_value']);
    }

    /**
     * Test: Create API key successfully
     */
    public function testCreateApiKeySuccessfully(): void
    {
        $data = [
            'csrf_token' => 'valid_token',
            'key_name' => 'Production API Key',
            'description' => 'Used for production cron job',
            'enabled' => '1',
        ];

        $this->mockCsrf->expects($this->once())
            ->method('validate')
            ->with('valid_token');

        $this->mockRepository->expects($this->once())
            ->method('create')
            ->with($this->callback(function ($apiKeyData) {
                return $apiKeyData['key_name'] === 'Production API Key'
                    && $apiKeyData['description'] === 'Used for production cron job'
                    && $apiKeyData['enabled'] === 1
                    && strlen($apiKeyData['key_value']) === 64
                    && preg_match('/^[a-f0-9]{64}$/', $apiKeyData['key_value']);
            }))
            ->willReturn(1);

        $this->mockLogger->expects($this->once())
            ->method('info')
            ->with('API key created', $this->callback(function ($context) {
                return $context['category'] === 'settings'
                    && $context['key_id'] === 1
                    && $context['key_name'] === 'Production API Key';
            }));

        $result = $this->controller->createApiKey($data);

        $this->assertArrayHasKey('redirect', $result);
        $this->assertEquals('/settings', $result['redirect']);

        // Verify full key is stored in session
        $this->assertArrayHasKey('new_api_key', $_SESSION);
        $this->assertEquals(64, strlen($_SESSION['new_api_key']));

        // Verify flash message
        $this->assertArrayHasKey('flash_message', $_SESSION);
        $this->assertEquals('success', $_SESSION['flash_message']['type']);
        $this->assertStringContainsString('created successfully', $_SESSION['flash_message']['message']);
    }

    /**
     * Test: Create API key fails with missing name
     */
    public function testCreateApiKeyFailsWithMissingName(): void
    {
        $data = [
            'csrf_token' => 'valid_token',
            'description' => 'Test description',
        ];

        $this->mockCsrf->expects($this->once())
            ->method('validate')
            ->with('valid_token');

        $this->mockRepository->expects($this->never())
            ->method('create');

        $result = $this->controller->createApiKey($data);

        $this->assertArrayHasKey('redirect', $result);
        $this->assertEquals('/settings', $result['redirect']);

        // Verify error flash message
        $this->assertArrayHasKey('flash_message', $_SESSION);
        $this->assertEquals('error', $_SESSION['flash_message']['type']);
        $this->assertStringContainsString('name is required', $_SESSION['flash_message']['message']);
    }

    /**
     * Test: Create API key with CSRF validation failure
     */
    public function testCreateApiKeyWithCsrfValidationFailure(): void
    {
        $data = [
            'csrf_token' => 'invalid_token',
            'key_name' => 'Test Key',
        ];

        $this->mockCsrf->expects($this->once())
            ->method('validate')
            ->with('invalid_token')
            ->willThrowException(new SecurityException('CSRF token validation failed'));

        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('CSRF token validation failed');

        $this->controller->createApiKey($data);
    }

    /**
     * Test: Create API key with enabled = 0
     */
    public function testCreateApiKeyDisabled(): void
    {
        $data = [
            'csrf_token' => 'valid_token',
            'key_name' => 'Disabled Key',
            'enabled' => '0',
        ];

        $this->mockCsrf->expects($this->once())
            ->method('validate');

        $this->mockRepository->expects($this->once())
            ->method('create')
            ->with($this->callback(function ($apiKeyData) {
                return $apiKeyData['enabled'] === 0;
            }))
            ->willReturn(1);

        $result = $this->controller->createApiKey($data);

        $this->assertArrayHasKey('redirect', $result);
    }

    /**
     * Test: Create API key without description
     */
    public function testCreateApiKeyWithoutDescription(): void
    {
        $data = [
            'csrf_token' => 'valid_token',
            'key_name' => 'Simple Key',
        ];

        $this->mockCsrf->expects($this->once())
            ->method('validate');

        $this->mockRepository->expects($this->once())
            ->method('create')
            ->with($this->callback(function ($apiKeyData) {
                return $apiKeyData['description'] === null;
            }))
            ->willReturn(1);

        $result = $this->controller->createApiKey($data);

        $this->assertArrayHasKey('redirect', $result);
    }

    /**
     * Test: Edit API key successfully
     */
    public function testEditApiKeySuccessfully(): void
    {
        $existingKey = [
            'id' => 1,
            'key_name' => 'Old Name',
            'key_value' => '1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef',
            'description' => 'Old description',
            'enabled' => 1,
        ];

        $data = [
            'csrf_token' => 'valid_token',
            'key_name' => 'New Name',
            'description' => 'New description',
            'enabled' => '0',
        ];

        $this->mockCsrf->expects($this->once())
            ->method('validate')
            ->with('valid_token');

        $this->mockRepository->expects($this->once())
            ->method('findById')
            ->with(1)
            ->willReturn($existingKey);

        $this->mockRepository->expects($this->once())
            ->method('update')
            ->with(1, [
                'key_name' => 'New Name',
                'description' => 'New description',
                'enabled' => 0,
            ])
            ->willReturn(true);

        $this->mockLogger->expects($this->once())
            ->method('info')
            ->with('API key updated', $this->callback(function ($context) {
                return $context['category'] === 'settings'
                    && $context['key_id'] === 1
                    && $context['key_name'] === 'New Name';
            }));

        $result = $this->controller->editApiKey(1, $data);

        $this->assertArrayHasKey('redirect', $result);
        $this->assertEquals('/settings', $result['redirect']);

        // Verify flash message
        $this->assertArrayHasKey('flash_message', $_SESSION);
        $this->assertEquals('success', $_SESSION['flash_message']['type']);
        $this->assertStringContainsString('updated successfully', $_SESSION['flash_message']['message']);
    }

    /**
     * Test: Edit non-existent API key
     */
    public function testEditNonExistentApiKey(): void
    {
        $data = [
            'csrf_token' => 'valid_token',
            'key_name' => 'New Name',
        ];

        $this->mockCsrf->expects($this->once())
            ->method('validate');

        $this->mockRepository->expects($this->once())
            ->method('findById')
            ->with(999)
            ->willReturn(null);

        $this->mockRepository->expects($this->never())
            ->method('update');

        $result = $this->controller->editApiKey(999, $data);

        $this->assertArrayHasKey('redirect', $result);

        // Verify error flash message
        $this->assertArrayHasKey('flash_message', $_SESSION);
        $this->assertEquals('error', $_SESSION['flash_message']['type']);
        $this->assertStringContainsString('not found', $_SESSION['flash_message']['message']);
    }

    /**
     * Test: Edit API key with missing name
     */
    public function testEditApiKeyWithMissingName(): void
    {
        $existingKey = [
            'id' => 1,
            'key_name' => 'Old Name',
            'key_value' => '1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef',
            'enabled' => 1,
        ];

        $data = [
            'csrf_token' => 'valid_token',
            'key_name' => '',
        ];

        $this->mockCsrf->expects($this->once())
            ->method('validate');

        $this->mockRepository->expects($this->once())
            ->method('findById')
            ->with(1)
            ->willReturn($existingKey);

        $this->mockRepository->expects($this->never())
            ->method('update');

        $result = $this->controller->editApiKey(1, $data);

        $this->assertArrayHasKey('redirect', $result);

        // Verify error flash message
        $this->assertArrayHasKey('flash_message', $_SESSION);
        $this->assertEquals('error', $_SESSION['flash_message']['type']);
        $this->assertStringContainsString('name is required', $_SESSION['flash_message']['message']);
    }

    /**
     * Test: Delete API key successfully
     */
    public function testDeleteApiKeySuccessfully(): void
    {
        $existingKey = [
            'id' => 1,
            'key_name' => 'Key to Delete',
            'key_value' => '1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef',
            'enabled' => 1,
        ];

        $data = [
            'csrf_token' => 'valid_token',
        ];

        $this->mockCsrf->expects($this->once())
            ->method('validate')
            ->with('valid_token');

        $this->mockRepository->expects($this->once())
            ->method('findById')
            ->with(1)
            ->willReturn($existingKey);

        $this->mockRepository->expects($this->once())
            ->method('delete')
            ->with(1)
            ->willReturn(true);

        $this->mockLogger->expects($this->once())
            ->method('warning')
            ->with('API key deleted', $this->callback(function ($context) {
                return $context['category'] === 'settings'
                    && $context['key_id'] === 1
                    && $context['key_name'] === 'Key to Delete';
            }));

        $result = $this->controller->deleteApiKey(1, $data);

        $this->assertArrayHasKey('redirect', $result);
        $this->assertEquals('/settings', $result['redirect']);

        // Verify flash message
        $this->assertArrayHasKey('flash_message', $_SESSION);
        $this->assertEquals('success', $_SESSION['flash_message']['type']);
        $this->assertStringContainsString('deleted successfully', $_SESSION['flash_message']['message']);
    }

    /**
     * Test: Delete non-existent API key
     */
    public function testDeleteNonExistentApiKey(): void
    {
        $data = [
            'csrf_token' => 'valid_token',
        ];

        $this->mockCsrf->expects($this->once())
            ->method('validate');

        $this->mockRepository->expects($this->once())
            ->method('findById')
            ->with(999)
            ->willReturn(null);

        $this->mockRepository->expects($this->never())
            ->method('delete');

        $result = $this->controller->deleteApiKey(999, $data);

        $this->assertArrayHasKey('redirect', $result);

        // Verify error flash message
        $this->assertArrayHasKey('flash_message', $_SESSION);
        $this->assertEquals('error', $_SESSION['flash_message']['type']);
        $this->assertStringContainsString('not found', $_SESSION['flash_message']['message']);
    }

    /**
     * Test: Show API key returns full key value
     */
    public function testShowApiKeyReturnsFullKeyValue(): void
    {
        $apiKey = [
            'id' => 1,
            'key_name' => 'Test Key',
            'key_value' => '1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef',
            'description' => 'Test',
            'enabled' => 1,
        ];

        $data = [
            'csrf_token' => 'valid_token',
        ];

        $this->mockCsrf->expects($this->once())
            ->method('validate')
            ->with('valid_token');

        $this->mockRepository->expects($this->once())
            ->method('findById')
            ->with(1)
            ->willReturn($apiKey);

        $this->mockLogger->expects($this->once())
            ->method('info')
            ->with('API key viewed', $this->callback(function ($context) {
                return $context['category'] === 'settings'
                    && $context['key_id'] === 1
                    && $context['key_name'] === 'Test Key';
            }));

        $result = $this->controller->showApiKey(1, $data);

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertEquals('1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef', $result['key_value']);
        $this->assertEquals('Test Key', $result['key_name']);
    }

    /**
     * Test: Show non-existent API key
     */
    public function testShowNonExistentApiKey(): void
    {
        $data = [
            'csrf_token' => 'valid_token',
        ];

        $this->mockCsrf->expects($this->once())
            ->method('validate');

        $this->mockRepository->expects($this->once())
            ->method('findById')
            ->with(999)
            ->willReturn(null);

        $result = $this->controller->showApiKey(999, $data);

        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertEquals('API key not found', $result['message']);
    }

    /**
     * Test: Update retention settings successfully
     */
    public function testUpdateRetentionSettingsSuccessfully(): void
    {
        $data = [
            'csrf_token' => 'valid_token',
            'articles_days' => '90',
            'logs_days' => '30',
            'auto_cleanup' => '1',
        ];

        $this->mockCsrf->expects($this->once())
            ->method('validate')
            ->with('valid_token');

        $this->mockLogger->expects($this->once())
            ->method('info')
            ->with('Retention settings updated', $this->callback(function ($context) {
                return $context['category'] === 'settings'
                    && $context['articles_days'] === 90
                    && $context['logs_days'] === 30
                    && $context['auto_cleanup'] === true;
            }));

        $result = $this->controller->updateRetention($data);

        $this->assertArrayHasKey('redirect', $result);
        $this->assertEquals('/settings', $result['redirect']);

        // Verify flash message
        $this->assertArrayHasKey('flash_message', $_SESSION);
        $this->assertEquals('success', $_SESSION['flash_message']['type']);
    }

    /**
     * Test: Update retention with minimum logs days
     */
    public function testUpdateRetentionWithMinimumLogsDays(): void
    {
        $data = [
            'csrf_token' => 'valid_token',
            'articles_days' => '0',
            'logs_days' => '7',
            'auto_cleanup' => '1',
        ];

        $this->mockCsrf->expects($this->once())
            ->method('validate');

        $this->mockLogger->expects($this->once())
            ->method('info')
            ->with('Retention settings updated', $this->callback(function ($context) {
                return $context['logs_days'] === 7 && $context['articles_days'] === 0;
            }));

        $result = $this->controller->updateRetention($data);

        $this->assertArrayHasKey('redirect', $result);
    }

    /**
     * Test: Update retention fails with logs days below minimum
     */
    public function testUpdateRetentionFailsWithLogsDaysBelowMinimum(): void
    {
        $data = [
            'csrf_token' => 'valid_token',
            'articles_days' => '90',
            'logs_days' => '5',
        ];

        $this->mockCsrf->expects($this->once())
            ->method('validate');

        $this->mockLogger->expects($this->never())
            ->method('info');

        $result = $this->controller->updateRetention($data);

        $this->assertArrayHasKey('redirect', $result);

        // Verify error flash message
        $this->assertArrayHasKey('flash_message', $_SESSION);
        $this->assertEquals('error', $_SESSION['flash_message']['type']);
        $this->assertStringContainsString('at least 7 days', $_SESSION['flash_message']['message']);
    }

    /**
     * Test: Update retention fails with negative articles days
     */
    public function testUpdateRetentionFailsWithNegativeArticlesDays(): void
    {
        $data = [
            'csrf_token' => 'valid_token',
            'articles_days' => '-10',
            'logs_days' => '30',
        ];

        $this->mockCsrf->expects($this->once())
            ->method('validate');

        $this->mockLogger->expects($this->never())
            ->method('info');

        $result = $this->controller->updateRetention($data);

        $this->assertArrayHasKey('redirect', $result);

        // Verify error flash message
        $this->assertArrayHasKey('flash_message', $_SESSION);
        $this->assertEquals('error', $_SESSION['flash_message']['type']);
        $this->assertStringContainsString('0 or greater', $_SESSION['flash_message']['message']);
    }

    /**
     * Test: CSRF validation on all POST endpoints
     */
    public function testCsrfValidationOnAllPostEndpoints(): void
    {
        $this->mockCsrf->expects($this->exactly(5))
            ->method('validate')
            ->willThrowException(new SecurityException('CSRF token validation failed'));

        // Create
        try {
            $this->controller->createApiKey(['csrf_token' => 'invalid']);
            $this->fail('Expected SecurityException for createApiKey');
        } catch (SecurityException $e) {
            $this->assertEquals('CSRF token validation failed', $e->getMessage());
        }

        // Edit
        $this->mockRepository->method('findById')->willReturn(['id' => 1, 'key_name' => 'Test']);
        try {
            $this->controller->editApiKey(1, ['csrf_token' => 'invalid']);
            $this->fail('Expected SecurityException for editApiKey');
        } catch (SecurityException $e) {
            $this->assertEquals('CSRF token validation failed', $e->getMessage());
        }

        // Delete
        try {
            $this->controller->deleteApiKey(1, ['csrf_token' => 'invalid']);
            $this->fail('Expected SecurityException for deleteApiKey');
        } catch (SecurityException $e) {
            $this->assertEquals('CSRF token validation failed', $e->getMessage());
        }

        // Show
        try {
            $this->controller->showApiKey(1, ['csrf_token' => 'invalid']);
            $this->fail('Expected SecurityException for showApiKey');
        } catch (SecurityException $e) {
            $this->assertEquals('CSRF token validation failed', $e->getMessage());
        }

        // Update Retention
        try {
            $this->controller->updateRetention(['csrf_token' => 'invalid']);
            $this->fail('Expected SecurityException for updateRetention');
        } catch (SecurityException $e) {
            $this->assertEquals('CSRF token validation failed', $e->getMessage());
        }
    }

    /**
     * Test: Enable/disable API key via edit
     */
    public function testEnableDisableApiKeyViaEdit(): void
    {
        $existingKey = [
            'id' => 1,
            'key_name' => 'Test Key',
            'key_value' => '1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef',
            'enabled' => 1,
        ];

        // Test disable
        $data = [
            'csrf_token' => 'valid_token',
            'key_name' => 'Test Key',
            'enabled' => '0',
        ];

        $this->mockCsrf->expects($this->once())
            ->method('validate');

        $this->mockRepository->expects($this->once())
            ->method('findById')
            ->willReturn($existingKey);

        $this->mockRepository->expects($this->once())
            ->method('update')
            ->with(1, $this->callback(function ($updateData) {
                return $updateData['enabled'] === 0;
            }))
            ->willReturn(true);

        $result = $this->controller->editApiKey(1, $data);

        $this->assertArrayHasKey('redirect', $result);
    }

    /**
     * Test: Toggle API key from enabled to disabled
     */
    public function testToggleApiKeyFromEnabledToDisabled(): void
    {
        $existingKey = [
            'id' => 1,
            'key_name' => 'Test Key',
            'key_value' => '1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef',
            'enabled' => 1,
        ];

        $data = [
            'csrf_token' => 'valid_token',
        ];

        $this->mockCsrf->expects($this->once())
            ->method('validate')
            ->with('valid_token');

        $this->mockRepository->expects($this->once())
            ->method('findById')
            ->with(1)
            ->willReturn($existingKey);

        $this->mockRepository->expects($this->once())
            ->method('update')
            ->with(1, ['enabled' => 0])
            ->willReturn(true);

        $result = $this->controller->toggleApiKey(1, $data);

        $this->assertArrayHasKey('redirect', $result);
        $this->assertEquals('/settings', $result['redirect']);

        // Verify flash message
        $this->assertArrayHasKey('flash_message', $_SESSION);
        $this->assertEquals('success', $_SESSION['flash_message']['type']);
        $this->assertStringContainsString('disabled successfully', $_SESSION['flash_message']['message']);
    }

    /**
     * Test: Toggle API key from disabled to enabled
     */
    public function testToggleApiKeyFromDisabledToEnabled(): void
    {
        $existingKey = [
            'id' => 1,
            'key_name' => 'Test Key',
            'key_value' => '1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef',
            'enabled' => 0,
        ];

        $data = [
            'csrf_token' => 'valid_token',
        ];

        $this->mockCsrf->expects($this->once())
            ->method('validate');

        $this->mockRepository->expects($this->once())
            ->method('findById')
            ->with(1)
            ->willReturn($existingKey);

        $this->mockRepository->expects($this->once())
            ->method('update')
            ->with(1, ['enabled' => 1])
            ->willReturn(true);

        $result = $this->controller->toggleApiKey(1, $data);

        $this->assertArrayHasKey('redirect', $result);
        $this->assertEquals('/settings', $result['redirect']);

        // Verify flash message
        $this->assertArrayHasKey('flash_message', $_SESSION);
        $this->assertEquals('success', $_SESSION['flash_message']['type']);
        $this->assertStringContainsString('enabled successfully', $_SESSION['flash_message']['message']);
    }

    /**
     * Test: Toggle non-existent API key
     */
    public function testToggleNonExistentApiKey(): void
    {
        $data = [
            'csrf_token' => 'valid_token',
        ];

        $this->mockCsrf->expects($this->once())
            ->method('validate');

        $this->mockRepository->expects($this->once())
            ->method('findById')
            ->with(999)
            ->willReturn(null);

        $this->mockRepository->expects($this->never())
            ->method('update');

        $result = $this->controller->toggleApiKey(999, $data);

        $this->assertArrayHasKey('redirect', $result);

        // Verify error flash message
        $this->assertArrayHasKey('flash_message', $_SESSION);
        $this->assertEquals('error', $_SESSION['flash_message']['type']);
        $this->assertStringContainsString('not found', $_SESSION['flash_message']['message']);
    }

    /**
     * Test: API key uniqueness handling (duplicate key_value)
     */
    public function testApiKeyUniquenessHandling(): void
    {
        $data = [
            'csrf_token' => 'valid_token',
            'key_name' => 'Test Key',
        ];

        $this->mockCsrf->expects($this->once())
            ->method('validate');

        $pdoException = new \PDOException('SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry');
        $this->mockRepository->expects($this->once())
            ->method('create')
            ->willThrowException($pdoException);

        $this->mockLogger->expects($this->once())
            ->method('error')
            ->with('Duplicate API key generated', $this->callback(function ($context) {
                return $context['category'] === 'settings';
            }));

        $result = $this->controller->createApiKey($data);

        $this->assertArrayHasKey('redirect', $result);

        // Verify error flash message
        $this->assertArrayHasKey('flash_message', $_SESSION);
        $this->assertEquals('error', $_SESSION['flash_message']['type']);
        $this->assertStringContainsString('Please try again', $_SESSION['flash_message']['message']);
    }

    /**
     * Test: Database error handling on update
     */
    public function testDatabaseErrorHandlingOnUpdate(): void
    {
        $existingKey = [
            'id' => 1,
            'key_name' => 'Test Key',
            'key_value' => '1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef',
            'enabled' => 1,
        ];

        $data = [
            'csrf_token' => 'valid_token',
            'key_name' => 'New Name',
        ];

        $this->mockCsrf->expects($this->once())
            ->method('validate');

        $this->mockRepository->expects($this->once())
            ->method('findById')
            ->willReturn($existingKey);

        $pdoException = new \PDOException('Database connection lost');
        $this->mockRepository->expects($this->once())
            ->method('update')
            ->willThrowException($pdoException);

        $this->mockLogger->expects($this->once())
            ->method('error')
            ->with('Failed to update API key', $this->callback(function ($context) {
                return $context['category'] === 'settings'
                    && $context['key_id'] === 1;
            }));

        $result = $this->controller->editApiKey(1, $data);

        $this->assertArrayHasKey('redirect', $result);

        // Verify error flash message
        $this->assertArrayHasKey('flash_message', $_SESSION);
        $this->assertEquals('error', $_SESSION['flash_message']['type']);
        $this->assertStringContainsString('Database error', $_SESSION['flash_message']['message']);
    }
}
