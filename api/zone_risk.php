<?php
ini_set('display_errors', '0');
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');
/**
 * Zone Risk Calculation & Observation Detail API
 * Complies with PRD Endpoint: GET /api/zones/{id}/risk
 */

require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/core/Database.php';
require_once dirname(__DIR__) . '/core/Response.php';
require_once dirname(__DIR__) . '/core/Helper.php';
require_once dirname(__DIR__) . '/services/RiskRuleEngine.php';

$zoneId = (int)($_GET['zone_id'] ?? $_GET['id'] ?? 0);

if ($zoneId <= 0) {
    Response::error('A valid zone_id parameter is required.', 400);
}

$db = Database::getInstance();

// 1. Fetch Zone
$zone = $db->fetchOne("SELECT * FROM `zones` WHERE `id` = :id LIMIT 1", [':id' => $zoneId]);
if (!$zone) {
    Response::error('Disaster zone not found.', 404);
}

// 2. Fetch Latest Hydrological Observation
$latestObs = $db->fetchOne(
    "SELECT * FROM `risk_levels` WHERE `zone_id` = :zone_id ORDER BY `observed_at` DESC, `id` DESC LIMIT 1",
    [':zone_id' => $zoneId]
);

$rainfallMm = $latestObs ? (float)$latestObs['rainfall_value'] : 0.0;
$riverLevelM = $latestObs ? (float)$latestObs['river_level_value'] : 0.0;
$observedAt = $latestObs ? $latestObs['observed_at'] : null;
$source = $latestObs ? $latestObs['source'] : 'No records';

// 3. Calculate dynamic risk indicators using active rule
$calculation = RiskRuleEngine::calculate($rainfallMm, $riverLevelM);
$isStale = RiskRuleEngine::isStale($observedAt);

// 4. Fetch recent observation history (last 5 records)
$history = $db->fetchAll(
    "SELECT `rainfall_value`, `river_level_value`, `score`, `category`, `observed_at`, `source`
     FROM `risk_levels`
     WHERE `zone_id` = :zone_id
     ORDER BY `observed_at` DESC, `id` DESC
     LIMIT 5",
    [':zone_id' => $zoneId]
);

// 5. Fetch any active alert targeting this zone (or all zones)
$activeAlert = $db->fetchOne(
    "SELECT * FROM `alerts` 
     WHERE (`target_zone_id` = :zone_id OR `target_zone_id` IS NULL)
       AND `status` = 'Published'
       AND (`is_active` = TRUE OR `is_active` IS NULL)
       AND (`expires_at` IS NULL OR `expires_at` > NOW())
     ORDER BY `created_at` DESC 
     LIMIT 1",
    [':zone_id' => $zoneId]
);

Response::success([
    'zone' => $zone,
    'current_observation' => [
        'rainfall_mm' => $rainfallMm,
        'river_level_m' => $riverLevelM,
        'observed_at' => $observedAt,
        'observed_at_human' => Helper::formatDateTime($observedAt),
        'time_ago' => Helper::timeAgo($observedAt),
        'source' => $source,
        'is_stale' => $isStale
    ],
    'risk_assessment' => $calculation,
    'active_alert' => $activeAlert,
    'recent_history' => $history
], 'Zone risk analysis calculated successfully.');
