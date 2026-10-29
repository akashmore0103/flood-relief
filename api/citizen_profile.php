<?php
/**
 * Citizen Profile & Bookmarked Telemetry REST API
 * Flood Risk Prediction & Relief Camp Locator
 */

require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/core/Database.php';
require_once dirname(__DIR__) . '/core/Response.php';
require_once dirname(__DIR__) . '/core/Auth.php';

$method = $_SERVER['REQUEST_METHOD'];

$isAuth = Auth::check();
$userId = $isAuth ? Auth::id() : null;
$db = Database::getInstance();

switch ($method) {
    case 'GET':
        try {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            if ($userId && $db->isConnected()) {
                $sql = "SELECT cp.*, 
                               cp.saved_village_city AS village_city,
                               cp.saved_dam_id AS monitored_dam_id,
                               cp.saved_river_id AS monitored_river_id,
                               d.name AS dam_name, 
                               d.live_storage_pct AS storage_percent, 
                               d.current_storage_tmc, 
                               d.gross_capacity_tmc,
                               d.outflow_cusecs, 
                               d.inflow_cusecs,
                               d.gate_status AS flood_gate_status,
                               d.district,
                               d.state,
                               r.name AS river_name, 
                               r.basin_name,
                               r.current_level_meters AS current_water_level_m, 
                               r.warning_level_meters AS warning_level_m,
                               r.danger_mark_meters AS danger_level_m, 
                               r.status AS river_status
                        FROM `citizen_profiles` cp
                        LEFT JOIN `dams` d ON cp.saved_dam_id = d.id
                        LEFT JOIN `rivers` r ON cp.saved_river_id = r.id
                        WHERE cp.user_id = :uid
                        LIMIT 1";

                $profile = $db->fetchOne($sql, [':uid' => $userId]);

                if (!$profile) {
                    $db->insert('citizen_profiles', [
                        'user_id' => $userId,
                        'saved_dam_id' => 1,
                        'saved_river_id' => 1,
                        'saved_village_city' => 'Nanded City (Godavari Basin)'
                    ]);
                    $profile = $db->fetchOne($sql, [':uid' => $userId]);
                }
            } else {
                $profile = $_SESSION['guest_watch_profile'] ?? [
                    'monitored_dam_id' => 1,
                    'monitored_river_id' => 1,
                    'village_city' => 'Nanded City (Godavari Basin)',
                    'district' => 'Nanded',
                    'state' => 'Maharashtra'
                ];
            }

            Response::success($profile, 'Citizen profile retrieved.');
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 500);
        }
        break;

    case 'POST':
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        try {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }

            $damId = !empty($input['monitored_dam_id']) ? (int)$input['monitored_dam_id'] : 1;
            $riverId = !empty($input['monitored_river_id']) ? (int)$input['monitored_river_id'] : 1;
            $village = trim($input['village_city'] ?? 'My Local Community');
            $district = trim($input['district'] ?? 'Maharashtra District');
            $state = trim($input['state'] ?? 'Maharashtra');

            // Store in session for immediate persistence across views
            $_SESSION['guest_watch_profile'] = [
                'monitored_dam_id' => $damId,
                'monitored_river_id' => $riverId,
                'village_city' => $village,
                'district' => $district,
                'state' => $state
            ];

            if ($userId && $db->isConnected()) {
                $existing = $db->fetchOne("SELECT id FROM `citizen_profiles` WHERE `user_id` = :uid LIMIT 1", [':uid' => $userId]);
                if ($existing) {
                    $db->update('citizen_profiles', [
                        'saved_dam_id' => $damId,
                        'saved_river_id' => $riverId,
                        'saved_village_city' => $village,
                        'updated_at' => date('Y-m-d H:i:s')
                    ], 'user_id = :uid', [':uid' => $userId]);
                } else {
                    $db->insert('citizen_profiles', [
                        'user_id' => $userId,
                        'saved_dam_id' => $damId,
                        'saved_river_id' => $riverId,
                        'saved_village_city' => $village
                    ]);
                }
            }

            Response::success($_SESSION['guest_watch_profile'], 'Citizen watch bookmarks saved successfully.');
        } catch (Throwable $e) {
            Response::error('Failed to update bookmarks: ' . $e->getMessage(), 500);
        }
        break;

    default:
        Response::error('Method not allowed.', 405);
}
