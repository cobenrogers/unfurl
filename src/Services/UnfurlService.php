<?php

declare(strict_types=1);

namespace Unfurl\Services;

use Unfurl\Core\Logger;

/**
 * Unfurl Service - Playwright Browser Automation Integration
 *
 * Processes Google News URLs using browser automation to:
 * - Follow redirects to final destination URL
 * - Extract Open Graph metadata
 * - Capture article information
 *
 * Uses Node.js + Playwright for reliable redirect handling.
 */
class UnfurlService
{
    private Logger $logger;
    private string $nodeScriptPath;
    private bool $headless;

    public function __construct(Logger $logger, ?string $nodeScriptPath = null, bool $headless = true)
    {
        $this->logger = $logger;

        // Default to scripts/unfurl-service.js in project root
        $this->nodeScriptPath = $nodeScriptPath ?? dirname(__DIR__, 2) . '/scripts/unfurl-service.js';
        $this->headless = $headless;

        // Verify Node.js script exists
        if (!file_exists($this->nodeScriptPath)) {
            throw new \RuntimeException('Unfurl service script not found: ' . $this->nodeScriptPath);
        }
    }

    /**
     * Process a single article URL
     *
     * @param int $id Article ID
     * @param string $googleNewsUrl Google News URL
     * @return array Processed article data
     * @throws \Exception If processing fails
     */
    public function processArticle(int $id, string $googleNewsUrl): array
    {
        $articles = $this->processArticles([
            ['id' => $id, 'url' => $googleNewsUrl]
        ]);

        if (empty($articles)) {
            throw new \Exception('No results returned from unfurl service');
        }

        return $articles[0];
    }

    /**
     * Process multiple articles in batch
     *
     * @param array $articles Array of ['id' => int, 'url' => string]
     * @return array Array of processed results
     * @throws \Exception If processing fails
     */
    public function processArticles(array $articles): array
    {
        // Prepare input JSON
        $inputJson = json_encode($articles, JSON_UNESCAPED_SLASHES);

        if ($inputJson === false) {
            throw new \Exception('Failed to encode articles as JSON');
        }

        // Build command
        $headlessFlag = $this->headless ? '--headless' : '';
        $command = sprintf(
            'echo %s | node %s %s 2>&1',
            escapeshellarg($inputJson),
            escapeshellarg($this->nodeScriptPath),
            $headlessFlag
        );

        $this->logger->debug('Executing unfurl service', [
            'category' => 'unfurl',
            'article_count' => count($articles),
            'headless' => $this->headless,
        ]);

        // Execute command
        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);

        $outputString = implode("\n", $output);

        // Check for errors
        if ($returnCode !== 0) {
            $this->logger->error('Unfurl service failed', [
                'category' => 'unfurl',
                'return_code' => $returnCode,
                'output' => $outputString,
            ]);

            throw new \Exception('Unfurl service failed with code ' . $returnCode . ': ' . $outputString);
        }

        // Parse JSON output
        $results = json_decode($outputString, true);

        if ($results === null) {
            $this->logger->error('Failed to parse unfurl service output', [
                'category' => 'unfurl',
                'output' => $outputString,
                'json_error' => json_last_error_msg(),
            ]);

            throw new \Exception('Failed to parse unfurl service output: ' . json_last_error_msg());
        }

        if (!is_array($results)) {
            throw new \Exception('Unfurl service returned invalid output format');
        }

        $this->logger->info('Unfurl service completed', [
            'category' => 'unfurl',
            'article_count' => count($articles),
            'success_count' => count(array_filter($results, fn($r) => ($r['status'] ?? '') === 'success')),
            'failed_count' => count(array_filter($results, fn($r) => ($r['status'] ?? '') !== 'success')),
        ]);

        return $results;
    }

    /**
     * Check if Node.js and Playwright are available
     *
     * @return array Status information
     */
    public function checkDependencies(): array
    {
        $status = [
            'node' => false,
            'script' => false,
            'playwright' => false,
            'errors' => [],
        ];

        // Check Node.js
        exec('node --version 2>&1', $nodeOutput, $nodeReturnCode);
        if ($nodeReturnCode === 0) {
            $status['node'] = trim(implode("\n", $nodeOutput));
        } else {
            $status['errors'][] = 'Node.js not found';
        }

        // Check script exists
        if (file_exists($this->nodeScriptPath)) {
            $status['script'] = $this->nodeScriptPath;
        } else {
            $status['errors'][] = 'Script not found: ' . $this->nodeScriptPath;
        }

        // Check Playwright (try to require it)
        $projectRoot = dirname(__DIR__, 2);
        $playwrightCheck = $projectRoot . '/node_modules/playwright';
        if (is_dir($playwrightCheck)) {
            $status['playwright'] = true;
        } else {
            $status['errors'][] = 'Playwright not installed. Run: npm install';
        }

        return $status;
    }
}
