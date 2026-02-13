<?php

declare(strict_types=1);

namespace Unfurl\Tests\Integration\Repositories;

use PHPUnit\Framework\TestCase;
use Unfurl\Core\Database;
use Unfurl\Core\TimezoneHelper;
use Unfurl\Repositories\ApiKeyRepository;
use PDO;

class ApiKeyRepositoryTest extends TestCase
{
    private Database $db;
    private ApiKeyRepository $repository;
    private TimezoneHelper $timezone;

    protected function setUp(): void
    {
        $config = [
            'database' => [
                'host' => 'localhost',
                'name' => ':memory:',
                'user' => '',
                'pass' => '',
                'charset' => 'utf8mb4',
                'options' => [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ],
            ],
        ];

        $this->db = new Database($config);
        $this->timezone = new TimezoneHelper('America/Chicago');
        $this->repository = new ApiKeyRepository($this->db, $this->timezone);

        $this->createApiKeysTable();
    }

    private function createApiKeysTable(): void
    {
        $sql = "
            CREATE TABLE api_keys (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                key_name TEXT NOT NULL,
                key_value TEXT NOT NULL UNIQUE,
                description TEXT,
                enabled INTEGER DEFAULT 1,
                last_used_at TEXT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ";

        $this->db->execute($sql);
    }

    public function testCreateApiKey(): void
    {
        $keyData = [
            'key_name' => 'Test API Key',
            'key_value' => 'test_key_12345',
            'description' => 'Test key for unit testing',
            'enabled' => 1,
        ];

        $keyId = $this->repository->create($keyData);

        $this->assertIsInt($keyId);
        $this->assertGreaterThan(0, $keyId);

        $key = $this->repository->findById($keyId);
        $this->assertEquals('Test API Key', $key['key_name']);
        $this->assertEquals('test_key_12345', $key['key_value']);
    }

    public function testFindById(): void
    {
        $keyId = $this->repository->create([
            'key_name' => 'Find Test',
            'key_value' => 'find_test_key',
        ]);

        $key = $this->repository->findById($keyId);

        $this->assertIsArray($key);
        $this->assertEquals('Find Test', $key['key_name']);
        $this->assertEquals('find_test_key', $key['key_value']);
    }

    public function testFindByIdReturnsNullForNonExistent(): void
    {
        $key = $this->repository->findById(99999);

        $this->assertNull($key);
    }

    public function testFindByKeyValue(): void
    {
        $this->repository->create([
            'key_name' => 'Value Test',
            'key_value' => 'unique_value_123',
        ]);

        $key = $this->repository->findByKeyValue('unique_value_123');

        $this->assertIsArray($key);
        $this->assertEquals('Value Test', $key['key_name']);
    }

    public function testFindByKeyValueReturnsNullForNonExistent(): void
    {
        $key = $this->repository->findByKeyValue('nonexistent_key');

        $this->assertNull($key);
    }

    public function testFindAll(): void
    {
        $this->repository->create([
            'key_name' => 'Key 1',
            'key_value' => 'key_value_1',
        ]);

        $this->repository->create([
            'key_name' => 'Key 2',
            'key_value' => 'key_value_2',
        ]);

        $keys = $this->repository->findAll();

        $this->assertIsArray($keys);
        $this->assertCount(2, $keys);
    }

    public function testFindEnabled(): void
    {
        $this->repository->create([
            'key_name' => 'Enabled Key',
            'key_value' => 'enabled_key',
            'enabled' => 1,
        ]);

        $this->repository->create([
            'key_name' => 'Disabled Key',
            'key_value' => 'disabled_key',
            'enabled' => 0,
        ]);

        $enabledKeys = $this->repository->findEnabled();

        $this->assertCount(1, $enabledKeys);
        $this->assertEquals('Enabled Key', $enabledKeys[0]['key_name']);
    }

    public function testUpdate(): void
    {
        $keyId = $this->repository->create([
            'key_name' => 'Original Name',
            'key_value' => 'original_value',
            'enabled' => 1,
        ]);

        $updated = $this->repository->update($keyId, [
            'key_name' => 'Updated Name',
            'description' => 'New description',
            'enabled' => 0,
        ]);

        $this->assertTrue($updated);

        $key = $this->repository->findById($keyId);
        $this->assertEquals('Updated Name', $key['key_name']);
        $this->assertEquals('New description', $key['description']);
        $this->assertEquals(0, $key['enabled']);
    }

    public function testUpdateReturnsFalseForNonExistent(): void
    {
        $updated = $this->repository->update(99999, [
            'key_name' => 'Updated Name',
        ]);

        $this->assertFalse($updated);
    }

    public function testDelete(): void
    {
        $keyId = $this->repository->create([
            'key_name' => 'To Delete',
            'key_value' => 'delete_me',
        ]);

        $deleted = $this->repository->delete($keyId);

        $this->assertTrue($deleted);
        $this->assertNull($this->repository->findById($keyId));
    }

    public function testDeleteReturnsFalseForNonExistent(): void
    {
        $deleted = $this->repository->delete(99999);

        $this->assertFalse($deleted);
    }

    public function testUniqueKeyValueConstraint(): void
    {
        $this->repository->create([
            'key_name' => 'First Key',
            'key_value' => 'duplicate_value',
        ]);

        $this->expectException(\PDOException::class);

        $this->repository->create([
            'key_name' => 'Second Key',
            'key_value' => 'duplicate_value',
        ]);
    }

    public function testUpdateLastUsedAt(): void
    {
        $keyId = $this->repository->create([
            'key_name' => 'Usage Test',
            'key_value' => 'usage_test_key',
        ]);

        sleep(1); // Ensure timestamp differs

        $updated = $this->repository->updateLastUsedAt($keyId);

        $this->assertTrue($updated);

        $key = $this->repository->findById($keyId);
        $this->assertNotNull($key['last_used_at']);
    }

    public function testValidateApiKey(): void
    {
        $this->repository->create([
            'key_name' => 'Valid Key',
            'key_value' => 'valid_key_123',
            'enabled' => 1,
        ]);

        $this->repository->create([
            'key_name' => 'Invalid Key',
            'key_value' => 'invalid_key_123',
            'enabled' => 0,
        ]);

        $validResult = $this->repository->validateApiKey('valid_key_123');
        $invalidResult = $this->repository->validateApiKey('invalid_key_123');
        $nonexistentResult = $this->repository->validateApiKey('nonexistent_key');

        $this->assertTrue($validResult);
        $this->assertFalse($invalidResult);
        $this->assertFalse($nonexistentResult);
    }

    public function testValidateApiKeyUpdatesLastUsedAt(): void
    {
        $keyId = $this->repository->create([
            'key_name' => 'Track Usage',
            'key_value' => 'track_usage_key',
            'enabled' => 1,
        ]);

        // Initial last_used_at should be null
        $keyBefore = $this->repository->findById($keyId);
        $this->assertNull($keyBefore['last_used_at']);

        sleep(1);

        // Validate key
        $this->repository->validateApiKey('track_usage_key');

        // last_used_at should now be set
        $keyAfter = $this->repository->findById($keyId);
        $this->assertNotNull($keyAfter['last_used_at']);
    }

    public function testCreateUsesDefaultValues(): void
    {
        $keyId = $this->repository->create([
            'key_name' => 'Minimal Key',
            'key_value' => 'minimal_key',
        ]);

        $key = $this->repository->findById($keyId);

        $this->assertEquals(1, $key['enabled']); // Default value
        $this->assertNull($key['last_used_at']);
    }
}
