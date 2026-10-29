<?php
/**
 * Emergency Alerts REST API Endpoint
 * Complies with PRD Endpoint: GET /api/alerts and POST /api/alerts
 */

require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/core/Database.php';
require_once dirname(__DIR__) . '/core/Response.php';
require_once dirname(__DIR__) . '/core/Auth.php';
require_once dirname(__DIR__) . '/core/CSRF.php';
require_once dirname(__DIR__) . '/core/Validator.php';
require_once dirname(__DIR__) . '/services/AlertService.php';

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if (!empty($_GET['all'])) {
            $alerts = AlertService::getAllAlerts(false);
            Response::success($alerts, 'All emergency alerts retrieved.');
        } else {
            $zoneId = !empty($_GET['zone_id']) ? (int)$_GET['zone_id'] : null;
            $alerts = AlertService::getActiveAlerts($zoneId);
            Response::success($alerts, 'Active emergency alerts retrieved.');
        }
        break;

    case 'POST':
        // Protected Admin Endpoint (FR-20: Prevent unauthorized users from modifying alerts)
        Auth::requireRole(['Administrator']);

        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $action = strtolower(trim($input['action'] ?? ($_GET['action'] ?? '')));
        $alertId = (int)($input['alert_id'] ?? ($input['id'] ?? ($_GET['alert_id'] ?? ($_GET['id'] ?? 0))));

        // Handle delete / deactivate / revoke via POST
        if (in_array($action, ['delete', 'revoke', 'deactivate']) || ($alertId > 0 && empty($input['title']))) {
            if ($alertId <= 0) {
                Response::error('A valid alert_id is required.', 422);
            }

            try {
                $adminId = Auth::id();
                if ($action === 'deactivate' || $action === 'revoke') {
                    AlertService::deactivateAlert($alertId, $adminId);
                    Response::success(['alert_id' => $alertId, 'status' => 'Archived', 'is_active' => false], 'Emergency alert revoked successfully.');
                } else {
                    AlertService::deleteAlert($alertId, $adminId);
                    Response::success(['alert_id' => $alertId, 'deleted' => true], 'Emergency alert deleted successfully.');
                }
            } catch (InvalidArgumentException $e) {
                Response::error($e->getMessage(), 404);
            } catch (Throwable $e) {
                Response::error('Failed to remove emergency alert: ' . $e->getMessage(), 500);
            }
            break;
        }

        // Alert Creation / Broadcast
        $validator = new Validator($input);
        if (!$validator->validate([
            'title' => 'required|min:5|max:150',
            'message' => 'required|min:10',
            'severity' => 'required|in:Advisory,Warning,Evacuation,Critical Danger'
        ])) {
            Response::error($validator->getFirstError(), 422, $validator->getErrors());
        }

        try {
            $adminId = Auth::id();
            $alert = AlertService::broadcastAlert($input, $adminId);
            Response::success($alert, 'Emergency alert broadcasted successfully across specified channels.', 201);
        } catch (Throwable $e) {
            Response::error('Failed to broadcast emergency alert: ' . $e->getMessage(), 500);
        }
        break;

    case 'DELETE':
        // Protected Admin Endpoint
        Auth::requireRole(['Administrator']);

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $alertId = (int)($_GET['alert_id'] ?? ($_GET['id'] ?? ($input['alert_id'] ?? ($input['id'] ?? 0))));
        $action = strtolower(trim($_GET['action'] ?? ($input['action'] ?? 'delete')));

        if ($alertId <= 0) {
            Response::error('A valid alert_id is required.', 422);
        }

        try {
            $adminId = Auth::id();
            if ($action === 'deactivate' || $action === 'revoke') {
                AlertService::deactivateAlert($alertId, $adminId);
                Response::success(['alert_id' => $alertId, 'status' => 'Archived', 'is_active' => false], 'Emergency alert revoked successfully.');
            } else {
                AlertService::deleteAlert($alertId, $adminId);
                Response::success(['alert_id' => $alertId, 'deleted' => true], 'Emergency alert deleted successfully.');
            }
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 404);
        } catch (Throwable $e) {
            Response::error('Failed to remove emergency alert: ' . $e->getMessage(), 500);
        }
        break;

    default:
        Response::error('Method Not Allowed', 405);
        break;
}
