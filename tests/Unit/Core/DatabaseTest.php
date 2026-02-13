<?php

declare(strict_types=1);

namespace Unfurl\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use Unfurl\Core\Database;
use PDO;
use PDOException;

class DatabaseTest extends TestCase
{
    private array $config;

    protected function setUp(): void
    {
        $this->config = [
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
    }

    public function testConstructorCreatesValidPDOConnection(): void
    {
        $db = new Database($this->config);
        $pdo = $db->getConnection();

        $this->assertInstanceOf(PDO::class, $pdo);
    }

    public function testGetConnectionReturnsSamePDOInstance(): void
    {
        $db = new Database($this->config);
        $pdo1 = $db->getConnection();
        $pdo2 = $db->getConnection();

        $this->assertSame($pdo1, $pdo2);
    }

    public function testExecuteQueryWithPreparedStatement(): void
    {
        $db = new Database($this->config);
        $pdo = $db->getConnection();

        // Create test table
        $pdo->exec('CREATE TABLE test_users (id INTEGER PRIMARY KEY, name TEXT, email TEXT)');

        // Test INSERT with prepared statement
        $result = $db->execute(
            'INSERT INTO test_users (name, email) VALUES (?, ?)',
            ['John Doe', 'john@example.com']
        );

        $this->assertTrue($result);

        // Verify data was inserted
        $stmt = $pdo->query('SELECT * FROM test_users');
        $rows = $stmt->fetchAll();

        $this->assertCount(1, $rows);
        $this->assertEquals('John Doe', $rows[0]['name']);
        $this->assertEquals('john@example.com', $rows[0]['email']);
    }

    public function testQueryReturnsRows(): void
    {
        $db = new Database($this->config);
        $pdo = $db->getConnection();

        $pdo->exec('CREATE TABLE test_products (id INTEGER PRIMARY KEY, name TEXT, price REAL)');
        $pdo->exec("INSERT INTO test_products (name, price) VALUES ('Product 1', 19.99)");
        $pdo->exec("INSERT INTO test_products (name, price) VALUES ('Product 2', 29.99)");

        $rows = $db->query('SELECT * FROM test_products WHERE price > ?', [15.00]);

        $this->assertIsArray($rows);
        $this->assertCount(2, $rows);
        $this->assertEquals('Product 1', $rows[0]['name']);
        $this->assertEquals('Product 2', $rows[1]['name']);
    }

    public function testQuerySingleReturnsOneRow(): void
    {
        $db = new Database($this->config);
        $pdo = $db->getConnection();

        $pdo->exec('CREATE TABLE test_items (id INTEGER PRIMARY KEY, name TEXT)');
        $pdo->exec("INSERT INTO test_items (name) VALUES ('Item 1')");
        $pdo->exec("INSERT INTO test_items (name) VALUES ('Item 2')");

        $row = $db->querySingle('SELECT * FROM test_items WHERE name = ?', ['Item 1']);

        $this->assertIsArray($row);
        $this->assertEquals('Item 1', $row['name']);
    }

    public function testQuerySingleReturnsNullWhenNoResults(): void
    {
        $db = new Database($this->config);
        $pdo = $db->getConnection();

        $pdo->exec('CREATE TABLE test_items (id INTEGER PRIMARY KEY, name TEXT)');

        $row = $db->querySingle('SELECT * FROM test_items WHERE name = ?', ['NonExistent']);

        $this->assertNull($row);
    }

    public function testGetLastInsertId(): void
    {
        $db = new Database($this->config);
        $pdo = $db->getConnection();

        $pdo->exec('CREATE TABLE test_records (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');

        $db->execute('INSERT INTO test_records (name) VALUES (?)', ['Record 1']);
        $id1 = $db->getLastInsertId();

        $db->execute('INSERT INTO test_records (name) VALUES (?)', ['Record 2']);
        $id2 = $db->getLastInsertId();

        $this->assertEquals(1, $id1);
        $this->assertEquals(2, $id2);
    }

    public function testTransactionCommit(): void
    {
        $db = new Database($this->config);
        $pdo = $db->getConnection();

        $pdo->exec('CREATE TABLE test_accounts (id INTEGER PRIMARY KEY, balance REAL)');

        $db->beginTransaction();
        $db->execute('INSERT INTO test_accounts (balance) VALUES (?)', [100.00]);
        $db->execute('INSERT INTO test_accounts (balance) VALUES (?)', [200.00]);
        $db->commit();

        $rows = $db->query('SELECT * FROM test_accounts');

        $this->assertCount(2, $rows);
    }

    public function testTransactionRollback(): void
    {
        $db = new Database($this->config);
        $pdo = $db->getConnection();

        $pdo->exec('CREATE TABLE test_orders (id INTEGER PRIMARY KEY, amount REAL)');

        $db->beginTransaction();
        $db->execute('INSERT INTO test_orders (amount) VALUES (?)', [50.00]);
        $db->rollback();

        $rows = $db->query('SELECT * FROM test_orders');

        $this->assertCount(0, $rows);
    }

    public function testPreparedStatementsPreventSQLInjection(): void
    {
        $db = new Database($this->config);
        $pdo = $db->getConnection();

        $pdo->exec('CREATE TABLE test_secure (id INTEGER PRIMARY KEY, data TEXT)');
        $pdo->exec("INSERT INTO test_secure (data) VALUES ('safe data')");

        // Attempt SQL injection
        $maliciousInput = "'; DROP TABLE test_secure; --";

        $rows = $db->query('SELECT * FROM test_secure WHERE data = ?', [$maliciousInput]);

        // Should return empty array, not execute DROP TABLE
        $this->assertCount(0, $rows);

        // Verify table still exists
        $allRows = $db->query('SELECT * FROM test_secure');
        $this->assertCount(1, $allRows);
    }

    public function testExecuteThrowsExceptionOnInvalidSQL(): void
    {
        $db = new Database($this->config);

        $this->expectException(PDOException::class);

        $db->execute('INVALID SQL STATEMENT');
    }

    public function testInvalidConnectionThrowsException(): void
    {
        $invalidConfig = [
            'database' => [
                'host' => 'invalid_host_12345',
                'name' => 'invalid_db',
                'user' => 'invalid_user',
                'pass' => 'invalid_pass',
                'charset' => 'utf8mb4',
                'options' => [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ],
            ],
        ];

        $this->expectException(PDOException::class);

        new Database($invalidConfig);
    }
}
