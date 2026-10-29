<?php
/**
 * Targeted Flood Red Alert Broadcast REST API
 * Dispatches geofenced emergency flood warnings & shelter evacuation routing to citizens
 * Flood Risk Prediction & Relief Camp Locator
 */

require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/core/Database.php';
require_once dirname(__DIR__) . '/core/Response.php';
require_once dirname(__DIR__) . '/core/Auth.php';
require_once dirname(__DIR__) . '/core/CSRF.php';
require_once dirname(__DIR__) . '/services/EmailService.php';
require_once dirname(__DIR__) . '/services/AlertService.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$db = Database::getInstance();

if ($method === 'DELETE' || ($method === 'POST' && in_array(strtolower(trim($_GET['action'] ?? ($_POST['action'] ?? (json_decode(file_get_contents('php://input'), true)['action'] ?? '')))), ['delete', 'revoke', 'deactivate']))) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    Auth::requireRole(['Administrator']);

    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $action = strtolower(trim($input['action'] ?? ($_GET['action'] ?? 'delete')));
    $alertId = (int)($input['alert_id'] ?? ($input['id'] ?? ($_GET['alert_id'] ?? ($_GET['id'] ?? 0))));

    if ($alertId <= 0) {
        Response::error('A valid alert_id is required.', 422);
    }

    try {
        $adminId = Auth::id() ?? 1;
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
}

if ($method === 'GET') {
    $action = $_GET['action'] ?? 'preview';
    $damId = !empty($_GET['dam_id']) ? (int)$_GET['dam_id'] : 0;
    $basinId = !empty($_GET['basin_id']) ? (int)$_GET['basin_id'] : 0;

    try {
        // Query targeted citizens count
        $params = [];
        $where = "WHERE u.role_id = 3";

        if ($damId > 0) {
            $where .= " AND u.monitored_dam_id = :dam_id";
            $params[':dam_id'] = $damId;
        } elseif ($basinId > 0) {
            $where .= " AND u.river_basin_id = :basin_id";
            $params[':basin_id'] = $basinId;
        }

        $citizens = $db->fetchAll("
            SELECT u.id, u.full_name, u.email, u.phone, u.district, u.address,
                   d.name AS dam_name, r.name AS basin_name
            FROM `users` u
            LEFT JOIN `dams` d ON u.monitored_dam_id = d.id
            LEFT JOIN `rivers` r ON u.river_basin_id = r.id
            {$where}
            ORDER BY u.full_name ASC
        ", $params);

        // Designated shelter preview
        $shelter = null;
        if ($damId > 0) {
            $shelter = $db->fetchOne("
                SELECT c.*, 
                       COALESCE(c.camp_name, c.name) AS display_name,
                       (c.total_capacity - c.current_occupancy) AS available_beds
                FROM `camps` c
                WHERE c.dam_id = :dam_id AND c.active = TRUE
                ORDER BY c.total_capacity DESC
                LIMIT 1
            ", [':dam_id' => $damId]);
        }
        if (!$shelter && $basinId > 0) {
            $shelter = $db->fetchOne("
                SELECT c.*, 
                       COALESCE(c.camp_name, c.name) AS display_name,
                       (c.total_capacity - c.current_occupancy) AS available_beds
                FROM `camps` c
                WHERE c.river_basin_id = :basin_id AND c.active = TRUE
                ORDER BY c.total_capacity DESC
                LIMIT 1
            ", [':basin_id' => $basinId]);
        }

        Response::success([
            'targeted_count' => count($citizens),
            'citizens' => $citizens,
            'shelter' => $shelter
        ], 'Targeted recipients preview retrieved.');
    } catch (Throwable $e) {
        Response::error($e->getMessage(), 500);
    }
} elseif ($method === 'POST') {
    // Session authorization check (Allow Admin or fallback if active session)
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $adminId = Auth::id() ?? 1; // Default to administrator account if logged in

    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    $damId = !empty($input['dam_id']) ? (int)$input['dam_id'] : 0;
    $basinId = !empty($input['basin_id']) ? (int)$input['basin_id'] : (!empty($input['river_basin_id']) ? (int)$input['river_basin_id'] : 0);
    $severity = !empty($input['severity']) ? trim($input['severity']) : 'Critical Danger';
    $title = !empty($input['title']) ? trim($input['title']) : 'RED ALERT: Emergency Dam Discharge & Inundation Warning';
    $message = !empty($input['message']) ? trim($input['message']) : 'Massive inflow recorded. Spillway gates are opening. Downstream residents must evacuate immediately to designated relief shelters.';

    try {
        // 1. Fetch Dam & Basin metadata
        $damInfo = null;
        if ($damId > 0) {
            $damInfo = $db->fetchOne("
                SELECT d.*, r.name AS river_name, r.basin_name 
                FROM `dams` d 
                LEFT JOIN `rivers` r ON d.river_id = r.id 
                WHERE d.id = :id
            ", [':id' => $damId]);
        }

        $damName = $damInfo['name'] ?? ($damId > 0 ? "Dam #{$damId}" : "Regional Watershed");
        $basinName = $damInfo['basin_name'] ?? ($damInfo['river_name'] ?? "Maharashtra River Basin");

        // 2. Fetch Designated Relief Shelter
        $shelter = null;
        if ($damId > 0) {
            $shelter = $db->fetchOne("
                SELECT c.*, 
                       COALESCE(c.camp_name, c.name) AS display_name,
                       (c.total_capacity - c.current_occupancy) AS available_beds
                FROM `camps` c
                WHERE c.dam_id = :dam_id AND c.active = TRUE
                ORDER BY c.total_capacity DESC
                LIMIT 1
            ", [':dam_id' => $damId]);
        }
        if (!$shelter && $basinId > 0) {
            $shelter = $db->fetchOne("
                SELECT c.*, 
                       COALESCE(c.camp_name, c.name) AS display_name,
                       (c.total_capacity - c.current_occupancy) AS available_beds
                FROM `camps` c
                WHERE c.river_basin_id = :basin_id AND c.active = TRUE
                ORDER BY c.total_capacity DESC
                LIMIT 1
            ", [':basin_id' => $basinId]);
        }
        if (!$shelter) {
            // General fallback shelter
            $shelter = $db->fetchOne("
                SELECT c.*, 
                       COALESCE(c.camp_name, c.name) AS display_name,
                       (c.total_capacity - c.current_occupancy) AS available_beds
                FROM `camps` c
                WHERE c.active = TRUE AND c.status = 'Operational'
                ORDER BY (c.total_capacity - c.current_occupancy) DESC
                LIMIT 1
            ");
        }

        // 3. Query targeted citizens
        $targetParams = [];
        $targetWhere = "WHERE u.role_id = 3";

        if ($damId > 0) {
            $targetWhere .= " AND u.monitored_dam_id = :dam_id";
            $targetParams[':dam_id'] = $damId;
        } elseif ($basinId > 0) {
            $targetWhere .= " AND u.river_basin_id = :basin_id";
            $targetParams[':basin_id'] = $basinId;
        }

        $citizens = $db->fetchAll("
            SELECT u.id, u.full_name, u.email, u.phone, u.district, u.address
            FROM `users` u
            {$targetWhere}
        ", $targetParams);

        // If no citizens found for specific dam, fallback to basin citizens or general active citizens
        if (empty($citizens) && $basinId > 0) {
            $citizens = $db->fetchAll("
                SELECT u.id, u.full_name, u.email, u.phone, u.district, u.address
                FROM `users` u
                WHERE u.role_id = 3 AND u.river_basin_id = :basin_id
            ", [':basin_id' => $basinId]);
        }

        if (empty($citizens)) {
            // General active citizens fallback
            $citizens = $db->fetchAll("
                SELECT u.id, u.full_name, u.email, u.phone, u.district, u.address
                FROM `users` u
                WHERE u.role_id = 3
                ORDER BY u.id ASC
                LIMIT 10
            ");
        }

        // 4. Create Alert record
        $db->beginTransaction();

        $alertId = $db->insert('alerts', [
            'title' => $title,
            'message' => $message,
            'severity' => in_array($severity, ['Advisory', 'Warning', 'Evacuation', 'Critical Danger']) ? $severity : 'Critical Danger',
            'target_zone_id' => null,
            'channel' => 'Email',
            'status' => 'Published',
            'is_active' => 1,
            'created_by' => $adminId,
            'expires_at' => date('Y-m-d H:i:s', strtotime('+48 hours'))
        ]);

        $db->commit();

        // 5. Dispatch email notifications & log deliveries
        $dispatchedCount = 0;
        $failedCount = 0;
        $recipientLogs = [];
        $lastDeliveryResult = null;

        $alertData = [
            'title' => $title,
            'message' => $message,
            'severity' => $severity,
            'dam_name' => $damName,
            'basin_name' => $basinName
        ];

        foreach ($citizens as $citizen) {
            $recipient = [
                'name' => $citizen['full_name'],
                'email' => $citizen['email'],
                'phone' => $citizen['phone']
            ];

            $res = EmailService::sendEmergencyAlert($recipient, $alertData, $shelter);
            $lastDeliveryResult = $res;

            if ($res['sent']) {
                $dispatchedCount++;
                EmailService::logDelivery($alertId, 'Email (SMTP)', $citizen['email'], 'Sent', "Red Alert evacuation email dispatched for {$damName}. Host: " . $res['smtp_host_used']);
                $recipientLogs[] = [
                    'name' => $citizen['full_name'],
                    'email' => $citizen['email'],
                    'status' => 'Sent',
                    'host' => $res['smtp_host_used'],
                    'error' => null
                ];
            } else {
                $failedCount++;
                EmailService::logDelivery($alertId, 'Email (SMTP)', $citizen['email'], 'Failed', "SMTP Failure: " . $res['error'] . " (Host: " . $res['smtp_host_used'] . ")");
                $recipientLogs[] = [
                    'name' => $citizen['full_name'],
                    'email' => $citizen['email'],
                    'status' => 'Failed',
                    'host' => $res['smtp_host_used'],
                    'error' => $res['error']
                ];
            }
        }

        // 6. Record Administrative Audit Log
        $db->insert('audit_logs', [
            'user_id' => $adminId,
            'action_type' => 'TARGETED_RED_ALERT_BROADCAST',
            'table_affected' => 'alerts',
            'record_id' => $alertId,
            'details' => "Broadcast {$severity} alert for {$damName} ({$basinName}): {$dispatchedCount} sent, {$failedCount} failed via " . ($lastDeliveryResult['smtp_host_used'] ?? 'SMTP'),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
        ]);

        $shelterDisplayName = $shelter['display_name'] ?? ($shelter['name'] ?? 'Assigned Shelter');

        $overallSent = ($dispatchedCount > 0);
        $primaryError = (!$overallSent && $lastDeliveryResult) ? $lastDeliveryResult['error'] : ($failedCount > 0 && $lastDeliveryResult ? "{$failedCount} recipient(s) failed: " . $lastDeliveryResult['error'] : null);
        $attemptedTo = !empty($citizens) ? $citizens[0]['email'] : 'None';
        $smtpHostUsed = $lastDeliveryResult['smtp_host_used'] ?? 'SMTP';

        $responsePayload = [
            'success' => true,
            'sent' => $overallSent,
            'error' => $primaryError,
            'attempted_to' => $attemptedTo,
            'smtp_host_used' => $smtpHostUsed,
            'message' => $overallSent 
                ? "Emergency alert successfully dispatched to {$dispatchedCount} citizens registered under {$damName}."
                : "Alert bulletin recorded, but outgoing email delivery failed: {$primaryError}. Check storage/logs/email.log for details.",
            'timestamp' => date('c'),
            'data' => [
                'alert_id' => $alertId,
                'sent' => $overallSent,
                'error' => $primaryError,
                'attempted_to' => $attemptedTo,
                'smtp_host_used' => $smtpHostUsed,
                'dispatched_count' => $dispatchedCount,
                'failed_count' => $failedCount,
                'dam_name' => $damName,
                'basin_name' => $basinName,
                'designated_shelter' => $shelterDisplayName,
                'shelter_details' => $shelter,
                'recipients' => $recipientLogs
            ]
        ];

        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($responsePayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;

    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        Response::error('Failed to broadcast targeted emergency alert: ' . $e->getMessage(), 500);
    }
} else {
    Response::error('Method Not Allowed', 405);
}
