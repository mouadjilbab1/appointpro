<?php
declare(strict_types=1);

namespace AppointPro\REST;

use AppointPro\Permissions\PermissionsManager;
use AppointPro\Reports\ReportsManager;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST Controller for Admin Dashboard Metrics and Live Aggregates.
 */
class DashboardController {
    public function registerRoutes(): void {
        register_rest_route('appointpro/v1', '/dashboard', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'getSummary'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);
    }

    public function checkPermission(): bool {
        return current_user_can('read') && (
            current_user_can(PermissionsManager::CAP_MANAGE_SETTINGS) ||
            current_user_can(PermissionsManager::CAP_VIEW_REPORTS) ||
            current_user_can(PermissionsManager::CAP_VIEW_OWN_APPOINTMENTS)
        );
    }

    public function getSummary(WP_REST_Request $request): WP_REST_Response {
        $reportsManager = new ReportsManager();
        $summary = $reportsManager->getDashboardSummary();
        $chartData = $reportsManager->getRevenueChartData(30);
        $summary['chart_data'] = $chartData;

        return new WP_REST_Response([
            'success' => true,
            'data' => $summary,
        ], 200);
    }
}
