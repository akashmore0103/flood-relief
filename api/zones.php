<?php
/**
 * Zones REST API Endpoint
 * Flood Risk Prediction & Relief Camp Locator
 */

require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/core/Database.php';
require_once dirname(__DIR__) . '/core/Response.php';
require_once dirname(__DIR__) . '/core/Auth.php';
require_once dirname(__DIR__) . '/core/CSRF.php';
require_once dirname(__DIR__) . '/core/Validator.php';
require_once dirname(__DIR__) . '/services/RiskRuleEngine.php';

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $db = Database::getInstance();
        $onlyActive = !isset($_GET['all']);

        $sql = "SELECT z.*, 
                       rl.rainfall_value, 
                       rl.river_level_value, 
                       rl.score, 
                       rl.category, 
                       rl.observed_at,
                       rl.source
                FROM `zones` z
                LEFT JOIN `risk_levels` rl ON rl.id = (
                    SELECT id FROM `risk_levels` 
                    WHERE zone_id = z.id 
                    ORDER BY observed_at DESC, id DESC 
                    LIMIT 1
                )";

        if ($onlyActive) {
            $sql .= " WHERE z.status = 'active'";
        }

        $sql .= " ORDER BY z.name ASC";
        $zones = $db->fetchAll($sql);

        // Enhance with data freshness flag, badge color, and compatibility aliases
        foreach ($zones as &$zone) {
            $zone['zone_name'] = !empty($zone['zone_name']) ? $zone['zone_name'] : $zone['name'];
            $zone['rainfall_24h_mm'] = isset($zone['rainfall_24h_mm']) && $zone['rainfall_24h_mm'] !== null ? (float)$zone['rainfall_24h_mm'] : (float)($zone['rainfall_value'] ?? 0.0);
            $zone['current_water_level_m'] = isset($zone['current_water_level_m']) && $zone['current_water_level_m'] !== null ? (float)$zone['current_water_level_m'] : (float)($zone['river_level_value'] ?? 0.0);
            $zone['risk_level'] = !empty($zone['risk_level']) ? $zone['risk_level'] : ($zone['category'] ?? 'Low');
            $zone['is_stale'] = RiskRuleEngine::isStale($zone['observed_at']);
            $zone['score'] = $zone['score'] !== null ? (float)$zone['score'] : 0.0;
            $zone['category'] = $zone['category'] ?? $zone['risk_level'] ?? 'Low';
            $zone['guidance'] = RiskRuleEngine::getSafetyGuidance($zone['category']);
        }

        Response::success($zones, 'Risk zones retrieved successfully.');
        break;

    case 'POST':
        // Protected Admin Endpoint
        Auth::requireRole(['Administrator']);

        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $validator = new Validator($input);

        if (!$validator->validate([
            'name' => 'required|min:3|max:100',
            'district' => 'required|min:2|max:100',
            'latitude' => 'required|coordinate_lat',
            'longitude' => 'required|coordinate_lng'
        ])) {
            Response::error($validator->getFirstError(), 422, $validator->getErrors());
        }

        $db = Database::getInstance();
        $zoneId = !empty($input['id']) ? (int)$input['id'] : null;

        $zoneData = [
            'name' => $input['name'],
            'district' => $input['district'],
            'state' => $input['state'] ?? 'Kerala',
            'latitude' => (float)$input['latitude'],
            'longitude' => (float)$input['longitude'],
            'boundary_geojson' => !empty($input['boundary_geojson']) ? $input['boundary_geojson'] : null,
            'status' => in_array($input['status'] ?? '', ['active', 'inactive']) ? $input['status'] : 'active'
        ];

        if ($zoneId) {
            $db->update('zones', $zoneData, 'id = :id', [':id' => $zoneId]);
            $action = 'ZONE_UPDATED';
            $recordId = $zoneId;
        } else {
            $recordId = $db->insert('zones', $zoneData);
            $action = 'ZONE_CREATED';
        }

        // Audit log
        $db->insert('audit_logs', [
            'user_id' => Auth::id(),
            'action_type' => $action,
            'table_affected' => 'zones',
            'record_id' => $recordId,
            'details' => "Zone {$zoneData['name']} ({$zoneData['district']}) saved",
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
        ]);

        Response::success(['id' => $recordId], 'Zone saved successfully.');
        break;

    default:
        Response::error('Method Not Allowed', 405);
        break;
}
