<?php

declare(strict_types=1);

namespace Unfurl\Controllers;

use Unfurl\Repositories\LogRepository;
use Unfurl\Security\OutputEscaper;
use Unfurl\Core\Logger;

/**
 * Log Controller
 *
 * Handles viewing and filtering of application logs.
 */
class LogController
{
    private LogRepository $logRepo;
    private OutputEscaper $escaper;
    private Logger $logger;

    public function __construct(LogRepository $logRepo, OutputEscaper $escaper, Logger $logger)
    {
        $this->logRepo = $logRepo;
        $this->escaper = $escaper;
        $this->logger = $logger;
    }

    /**
     * Display logs list with filtering and pagination
     *
     * @return array View data
     */
    public function index(): array
    {
        // Get filter parameters
        $filters = [
            'log_type' => $_GET['log_type'] ?? null,
            'log_level' => $_GET['log_level'] ?? null,
            'search' => $_GET['search'] ?? null,
            'date_from' => $_GET['date_from'] ?? null,
            'date_to' => $_GET['date_to'] ?? null,
        ];

        // Remove empty filters
        $filters = array_filter($filters, function ($value) {
            return $value !== null && $value !== '';
        });

        // Pagination
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 20;
        $offset = ($page - 1) * $perPage;

        // Fetch logs
        $logs = $this->logRepo->findWithFilters($filters, $perPage, $offset);
        $totalCount = $this->logRepo->countWithFilters($filters);
        $pageCount = (int)ceil($totalCount / $perPage);

        // Log type and level options for filter dropdown
        $logTypes = ['processing', 'user', 'feed', 'api', 'system'];
        $logLevels = ['DEBUG', 'INFO', 'WARNING', 'ERROR', 'CRITICAL'];

        return [
            'logs' => $logs,
            'filters' => $filters,
            'log_types' => $logTypes,
            'log_levels' => $logLevels,
            'current_page' => $page,
            'per_page' => $perPage,
            'total_count' => $totalCount,
            'page_count' => $pageCount,
        ];
    }

    /**
     * Display individual log details
     *
     * @param int $id Log ID
     * @return array|null View data or null if not found
     */
    public function view(int $id): ?array
    {
        $log = $this->logRepo->findById($id);

        if (!$log) {
            return null;
        }

        return [
            'log' => $log,
        ];
    }
}
