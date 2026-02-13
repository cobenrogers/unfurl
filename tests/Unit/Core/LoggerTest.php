<?php

declare(strict_types=1);

namespace Unfurl\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use Unfurl\Core\Logger;

class LoggerTest extends TestCase
{
    private string $logDir;
    private Logger $logger;

    protected function setUp(): void
    {
        // Use temp directory for tests
        $this->logDir = sys_get_temp_dir() . '/unfurl_logs_' . uniqid();
        mkdir($this->logDir, 0755, true);

        $this->logger = new Logger($this->logDir);
    }

    protected function tearDown(): void
    {
        // Clean up test logs
        if (is_dir($this->logDir)) {
            $this->removeDirectory($this->logDir);
        }
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    /**
     * Test that log directory is created if it doesn't exist
     */
    public function testLogDirectoryCreation(): void
    {
        $newLogDir = sys_get_temp_dir() . '/unfurl_new_logs_' . uniqid();

        $this->assertFalse(is_dir($newLogDir));

        $logger = new Logger($newLogDir);

        $this->assertTrue(is_dir($newLogDir));

        // Cleanup
        rmdir($newLogDir);
    }

    /**
     * Test emergency level logging
     */
    public function testEmergencyLogging(): void
    {
        $this->logger->emergency('Emergency message', ['error' => 'critical', 'category' => 'system']);

        $this->assertLogFileExists('system');
        $this->assertLogContains('system', '[EMERGENCY]');
        $this->assertLogContains('system', 'Emergency message');
    }

    /**
     * Test alert level logging
     */
    public function testAlertLogging(): void
    {
        $this->logger->alert('Alert message', ['alert_type' => 'security', 'category' => 'system']);

        $this->assertLogFileExists('system');
        $this->assertLogContains('system', '[ALERT]');
    }

    /**
     * Test critical level logging
     */
    public function testCriticalLogging(): void
    {
        $this->logger->critical('Critical message', ['severity' => 'high', 'category' => 'system']);

        $this->assertLogFileExists('system');
        $this->assertLogContains('system', '[CRITICAL]');
    }

    /**
     * Test error level logging
     */
    public function testErrorLogging(): void
    {
        $this->logger->error('Error message', ['code' => 500, 'category' => 'system']);

        $this->assertLogFileExists('system');
        $this->assertLogContains('system', '[ERROR]');
        $this->assertLogContains('system', 'Error message');
    }

    /**
     * Test warning level logging
     */
    public function testWarningLogging(): void
    {
        $this->logger->warning('Warning message', ['count' => 5, 'category' => 'system']);

        $this->assertLogFileExists('system');
        $this->assertLogContains('system', '[WARNING]');
    }

    /**
     * Test notice level logging
     */
    public function testNoticeLogging(): void
    {
        $this->logger->notice('Notice message', ['status' => 'pending', 'category' => 'system']);

        $this->assertLogFileExists('system');
        $this->assertLogContains('system', '[NOTICE]');
    }

    /**
     * Test info level logging
     */
    public function testInfoLogging(): void
    {
        $this->logger->info('Info message', ['action' => 'created', 'category' => 'system']);

        $this->assertLogFileExists('system');
        $this->assertLogContains('system', '[INFO]');
        $this->assertLogContains('system', 'Info message');
    }

    /**
     * Test debug level logging
     */
    public function testDebugLogging(): void
    {
        $this->logger->debug('Debug message', ['variable' => 'value', 'category' => 'system']);

        $this->assertLogFileExists('system');
        $this->assertLogContains('system', '[DEBUG]');
    }

    /**
     * Test logging with category
     */
    public function testLoggingWithCategory(): void
    {
        $this->logger->info('Processing started', ['id' => 123, 'category' => 'processing']);

        $this->assertLogContains('processing', '[processing]');
        $this->assertLogContains('processing', 'Processing started');
    }

    /**
     * Test multiple categories
     */
    public function testMultipleCategories(): void
    {
        $this->logger->info('API call', ['endpoint' => '/users', 'category' => 'api']);
        $this->logger->warning('Security event', ['event' => 'login', 'category' => 'security']);
        $this->logger->error('System error', ['error' => 'db', 'category' => 'system']);

        $this->assertLogFileExists('api');
        $this->assertLogFileExists('security');
        $this->assertLogFileExists('system');
    }

    /**
     * Test structured data logging (context as JSON)
     */
    public function testStructuredDataLogging(): void
    {
        $context = [
            'user_id' => 42,
            'action' => 'update',
            'timestamp' => 1234567890,
            'nested' => [
                'key' => 'value'
            ],
            'category' => 'user_activity'
        ];

        $this->logger->info('User action', $context);

        $content = $this->getLogFileContent('user_activity');

        // Check that context is logged as JSON
        $this->assertStringContainsString('"user_id":42', $content);
        $this->assertStringContainsString('"action":"update"', $content);
        $this->assertStringContainsString('"nested"', $content);
    }

    /**
     * Test log format
     */
    public function testLogFormat(): void
    {
        $this->logger->info('Test message', ['key' => 'value', 'category' => 'format']);

        $content = $this->getLogFileContent('format');

        // Log format: [timestamp] [LEVEL] [category] message {json_context}
        // Pattern: [YYYY-MM-DD HH:MM:SS] [LEVEL] [category] message {json}
        $this->assertMatchesRegularExpression(
            '/\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\] \[INFO\]/',
            $content
        );
    }

    /**
     * Test log level filtering
     */
    public function testLogLevelFiltering(): void
    {
        $warningLogger = new Logger($this->logDir, Logger::WARNING);

        // These should be logged
        $warningLogger->error('Error message', ['category' => 'filtering']);
        $warningLogger->warning('Warning message', ['category' => 'filtering']);
        $warningLogger->critical('Critical message', ['category' => 'filtering']);

        // These should not be logged
        $warningLogger->info('Info message', ['category' => 'filtering']);
        $warningLogger->debug('Debug message', ['category' => 'filtering']);

        // Check that only the expected levels were logged
        $content = $this->getLogFileContent('filtering');

        $this->assertStringContainsString('Error message', $content);
        $this->assertStringContainsString('Warning message', $content);
        $this->assertStringContainsString('Critical message', $content);
        $this->assertStringNotContainsString('Info message', $content);
        $this->assertStringNotContainsString('Debug message', $content);
    }

    /**
     * Test default log level is DEBUG
     */
    public function testDefaultLogLevel(): void
    {
        $logger = new Logger($this->logDir);

        $logger->debug('Debug message', ['category' => 'default_level']);
        $logger->info('Info message', ['category' => 'default_level']);

        $this->assertLogFileExists('default_level');

        $content = $this->getLogFileContent('default_level');
        $this->assertStringContainsString('Debug message', $content);
        $this->assertStringContainsString('Info message', $content);
    }

    /**
     * Test context preservation
     */
    public function testContextPreservation(): void
    {
        $context = [
            'string' => 'value',
            'number' => 42,
            'float' => 3.14,
            'boolean' => true,
            'null' => null,
            'array' => [1, 2, 3],
            'object' => (object)['key' => 'value'],
            'category' => 'preservation'
        ];

        $this->logger->info('Context test', $context);

        $content = $this->getLogFileContent('preservation');

        // Verify context is preserved in JSON
        $this->assertStringContainsString('"string":"value"', $content);
        $this->assertStringContainsString('"number":42', $content);
        $this->assertStringContainsString('"boolean":true', $content);
    }

    /**
     * Test log interpolation with placeholders
     */
    public function testLogInterpolation(): void
    {
        $this->logger->info('User {user_id} performed {action}', [
            'user_id' => 123,
            'action' => 'login',
            'category' => 'interpolation'
        ]);

        $content = $this->getLogFileContent('interpolation');

        // Message should have placeholders replaced
        $this->assertStringContainsString('User 123 performed login', $content);
    }

    /**
     * Test exception logging
     */
    public function testExceptionLogging(): void
    {
        $exception = new \Exception('Test exception', 123);

        $this->logger->error('Exception occurred', [
            'exception_message' => $exception->getMessage(),
            'exception_code' => $exception->getCode(),
            'trace' => $exception->getTraceAsString(),
            'category' => 'exceptions'
        ]);

        $content = $this->getLogFileContent('exceptions');

        // Check that exception details are logged
        $this->assertStringContainsString('Test exception', $content);
        $this->assertStringContainsString('123', $content);
    }

    /**
     * Test multiple log entries in same file
     */
    public function testMultipleLogEntries(): void
    {
        $this->logger->info('First message', ['category' => 'multiple']);
        $this->logger->info('Second message', ['category' => 'multiple']);
        $this->logger->info('Third message', ['category' => 'multiple']);

        $content = $this->getLogFileContent('multiple');

        $lines = array_filter(explode("\n", trim($content)));

        $this->assertCount(3, $lines);
        $this->assertStringContainsString('First message', $content);
        $this->assertStringContainsString('Second message', $content);
        $this->assertStringContainsString('Third message', $content);
    }

    /**
     * Test log file naming convention
     */
    public function testLogFileNaming(): void
    {
        $this->logger->info('Info', ['category' => 'processing']);
        $this->logger->error('Error', ['category' => 'processing']);

        // Both should be in same file: processing-YYYY-MM-DD.log
        $files = glob($this->logDir . '/processing-*.log');

        $this->assertNotEmpty($files, 'Log file not created');
    }

    /**
     * Test PSR-3 interface compliance
     */
    public function testPsr3Compliance(): void
    {
        // Verify the logger implements PSR-3 LoggerInterface
        $reflection = new \ReflectionClass($this->logger);
        $interfaces = $reflection->getInterfaceNames();

        // Should implement our LoggerInterface
        $this->assertContains('Unfurl\Core\LoggerInterface', $interfaces);
    }

    /**
     * Test logging with empty context
     */
    public function testLoggingWithEmptyContext(): void
    {
        $this->logger->info('Message without context', ['category' => 'empty']);

        $content = $this->getLogFileContent('empty');

        $this->assertStringContainsString('Message without context', $content);
    }

    /**
     * Test log file date pattern
     */
    public function testLogFileDatePattern(): void
    {
        $this->logger->info('Test message', ['category' => 'datetest']);

        $files = glob($this->logDir . '/datetest-*.log');

        $this->assertNotEmpty($files);

        // File should be named like: category-YYYY-MM-DD.log
        $filename = basename($files[0]);
        $this->assertMatchesRegularExpression('/^datetest-\d{4}-\d{2}-\d{2}\.log$/', $filename);
    }

    /**
     * Test fallback category
     */
    public function testDefaultCategory(): void
    {
        // When no category provided, should default to 'system'
        $this->logger->info('System log entry');

        $this->assertLogFileExists('system');
    }

    // Helper methods

    private function assertLogFileExists(string $category): void
    {
        $files = glob($this->logDir . '/' . $category . '-*.log');
        $this->assertNotEmpty($files, "Log file for category '{$category}' does not exist");
    }

    private function assertLogFileDoesNotExist(string $category): void
    {
        $files = glob($this->logDir . '/' . $category . '-*.log');
        $this->assertEmpty($files, "Log file for category '{$category}' exists but should not");
    }

    private function assertLogContains(string $category, string $needle): void
    {
        $content = $this->getLogFileContent($category);
        $this->assertStringContainsString($needle, $content);
    }

    private function getLogFileContent(string $category): string
    {
        $files = glob($this->logDir . '/' . $category . '-*.log');

        $this->assertNotEmpty($files, "Log file for category '{$category}' not found");

        return file_get_contents($files[0]);
    }
}
