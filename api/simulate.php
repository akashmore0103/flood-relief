<?php
/**
 * Dummy Flood Simulation REST API Endpoint
 * Admin Viva Demo Tool
 * Flood Risk Prediction & Relief Camp Locator
 */

require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/core/Database.php';
require_once dirname(__DIR__) . '/core/Response.php';
require_once dirname(__DIR__) . '/core/Auth.php';
require_once dirname(__DIR__) . '/core/CSRF.php';
require_once dirname(__DIR__) . '/services/SimulationService.php';

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        try {
            $active = SimulationService::getActiveSimulation();
            $logs = SimulationService::getRecentLogs(5);
            Response::success([
                'active_simulation' => $active,
                'recent_logs' => $logs
            ], 'Simulation status retrieved.');
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 500);
        }
        break;

    case 'POST':
        Auth::requireRole(['Administrator']);

        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $action = $input['action'] ?? 'trigger';

        try {
            $adminId = Auth::id();

            if ($action === 'trigger') {
                $damId = (int)($input['dam_id'] ?? 0);
                $rainfallMm = (float)($input['rainfall_mm'] ?? 120.0);
                $dischargeCusecs = (float)($input['discharge_cusecs'] ?? 100000.0);
                $scenarioName = trim($input['scenario_name'] ?? 'Emergency Gate Discharge & Cloudburst');

                if ($damId <= 0) {
                    Response::error('A valid dam must be selected for simulation.', 422);
                }

                $res = SimulationService::triggerFlashFlood($damId, $rainfallMm, $dischargeCusecs, $scenarioName, $adminId);
                Response::success($res, 'Simulated Flash Flood triggered successfully! Downstream zones shifted to Critical.');
            } elseif ($action === 'reset') {
                $damId = !empty($input['dam_id']) ? (int)$input['dam_id'] : null;
                SimulationService::resetSimulation($damId, $adminId);
                Response::success(null, 'Flood simulation reset. All dams and river telemetry returned to safe baseline.');
            } else {
                Response::error('Invalid simulation action.', 422);
            }
        } catch (Throwable $e) {
            Response::error('Simulation error: ' . $e->getMessage(), 500);
        }
        break;

    default:
        Response::error('Method not allowed.', 405);
}
