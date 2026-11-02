<?php
declare(strict_types=1);

namespace AppointPro\REST;

use AppointPro\Permissions\PermissionsManager;
use AppointPro\Reports\ReportsManager;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST Controller for Financial Reports, Workload Metrics, and CSV Export.
 */
class ReportsController {
    protected ReportsManager $manager;

    public function __construct() {
        $this->manager = new ReportsManager();
    }

    public function registerRoutes(): void {
        register_rest_route('appointpro/v1', '/reports', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'getReports'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        register_rest_route('appointpro/v1', '/reports/export', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'exportCsv'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);
    }

    public function checkPermission(): bool {
        return current_user_can(PermissionsManager::CAP_VIEW_REPORTS) || current_user_can('manage_options');
    }

    public function getReports(WP_REST_Request $request): WP_REST_Response {
        $days = (int) ($request->get_param('days') ?: 30);
        $summary = $this->manager->getDashboardSummary();
        $charts = $this->manager->getRevenueChartData($days);

        return new WP_REST_Response([
            'success' => true,
            'data' => [
                'summary' => $summary,
                'chart_data' => $charts,
            ],
        ], 200);
    }

    public function exportCsv(WP_REST_Request $request): WP_REST_Response {
        $startDate = $request->get_param('start_date');
        $endDate = $request->get_param('end_date');

        $csv = $this->manager->exportAppointmentsCsv($startDate, $endDate);

        $response = new WP_REST_Response($csv, 200);
        $response->header('Content-Type', 'text/csv; charset=utf-8');
        $response->header('Content-Disposition', 'attachment; filename=appointpro-appointments-' . date('Y-m-d') . '.csv');

        return $response;
    }
}
