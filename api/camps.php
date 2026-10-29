<?php
/**
 * Relief Camps REST API Endpoint
 * Complies with PRD Endpoint: GET /api/camps
 * Flood Risk Prediction & Relief Camp Locator
 */

require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/core/Database.php';
require_once dirname(__DIR__) . '/core/Response.php';
require_once dirname(__DIR__) . '/services/CampLocatorService.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method !== 'GET') {
    Response::error('Method Not Allowed. Use GET.', 405);
}

$lat = (isset($_GET['lat']) && is_numeric($_GET['lat'])) ? (float)$_GET['lat'] : null;
$lng = (isset($_GET['lng']) && is_numeric($_GET['lng'])) ? (float)$_GET['lng'] : null;
$radiusRaw = $_GET['radius'] ?? 'all';
$radius = ($radiusRaw === 'all' || $radiusRaw === '' || (float)$radiusRaw <= 0) ? null : (float)$radiusRaw;
$status = $_GET['status'] ?? null;
$zoneId = !empty($_GET['zone_id']) ? (int)$_GET['zone_id'] : null;
$district = !empty($_GET['district']) ? trim($_GET['district']) : null;
$action = $_GET['action'] ?? null;
$damId = !empty($_GET['dam_id']) ? (int)$_GET['dam_id'] : null;
$riverBasinId = !empty($_GET['river_basin_id']) ? (int)$_GET['river_basin_id'] : (!empty($_GET['basin_id']) ? (int)$_GET['basin_id'] : null);

// Action: Retrieve primary / designated emergency relief shelter for a dam or river basin
if ($action === 'primary_shelter' || $action === 'designated') {
    $db = Database::getInstance();
    $shelter = null;

    if ($damId) {
        $sql = "SELECT c.*, 
                       c.id AS camp_id,
                       COALESCE(c.camp_name, c.name) AS display_name,
                       (c.total_capacity - c.current_occupancy) AS available_beds,
                       d.name AS dam_name,
                       d.district AS dam_district,
                       r.name AS river_name,
                       r.basin_name
                FROM `camps` c
                LEFT JOIN `dams` d ON c.dam_id = d.id
                LEFT JOIN `rivers` r ON (c.river_basin_id = r.id OR d.river_id = r.id)
                WHERE c.dam_id = :dam_id AND c.active = TRUE
                ORDER BY c.total_capacity DESC
                LIMIT 1";
        $shelter = $db->fetchOne($sql, [':dam_id' => $damId]);
    }

    if (!$shelter && $riverBasinId) {
        $sql = "SELECT c.*, 
                       c.id AS camp_id,
                       COALESCE(c.camp_name, c.name) AS display_name,
                       (c.total_capacity - c.current_occupancy) AS available_beds,
                       d.name AS dam_name,
                       r.name AS river_name,
                       r.basin_name
                FROM `camps` c
                LEFT JOIN `dams` d ON c.dam_id = d.id
                LEFT JOIN `rivers` r ON (c.river_basin_id = r.id OR d.river_id = r.id)
                WHERE (c.river_basin_id = :basin_id OR d.river_id = :basin_id_alt) AND c.active = TRUE
                ORDER BY c.total_capacity DESC
                LIMIT 1";
        $shelter = $db->fetchOne($sql, [':basin_id' => $riverBasinId, ':basin_id_alt' => $riverBasinId]);
    }

    if (!$shelter) {
        // Fallback: pick the highest capacity operational camp
        $sql = "SELECT c.*, 
                       c.id AS camp_id,
                       COALESCE(c.camp_name, c.name) AS display_name,
                       (c.total_capacity - c.current_occupancy) AS available_beds
                FROM `camps` c
                WHERE c.active = TRUE AND c.status = 'Operational'
                ORDER BY (c.total_capacity - c.current_occupancy) DESC
                LIMIT 1";
        $shelter = $db->fetchOne($sql);
    }

    if ($shelter) {
        $cap = (int)$shelter['total_capacity'];
        $occ = (int)$shelter['current_occupancy'];
        $shelter['vacant_beds'] = max(0, $cap - $occ);
        $shelter['available_beds'] = $shelter['vacant_beds'];
        $shelter['occupancy_percentage'] = $cap > 0 ? round(($occ / $cap) * 100, 1) : 100;
        $shelter['directions_url'] = "https://www.google.com/maps/dir/?api=1&destination=" . $shelter['latitude'] . "," . $shelter['longitude'];
        Response::success($shelter, 'Designated primary relief shelter retrieved.');
    } else {
        Response::error('No designated relief shelter found.', 404);
    }
}

// If geospatial coordinates are supplied, leverage Haversine proximity
if ($lat !== null && $lng !== null) {
    if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
        Response::error('Invalid latitude or longitude coordinate bounds.', 422);
    }
    $camps = CampLocatorService::getNearbyCamps($lat, $lng, $radius, $status, $zoneId);
} else {
    $db = Database::getInstance();
    $sql = "SELECT c.*, 
                   z.name AS zone_name,
                   z.district AS zone_district,
                   z.state AS zone_state,
                   (c.total_capacity - c.current_occupancy) AS available_beds
            FROM `camps` c
            LEFT JOIN `zones` z ON c.zone_id = z.id
            WHERE c.active = TRUE";
    $params = [];

    if ($zoneId) {
        $sql .= " AND c.zone_id = :zone_id";
        $params[':zone_id'] = $zoneId;
    }

    if ($damId) {
        $sql .= " AND c.dam_id = :dam_id";
        $params[':dam_id'] = $damId;
    }

    if (!empty($status) && $status !== 'All') {
        $sql .= " AND c.status = :status";
        $params[':status'] = $status;
    }

    if (!empty($district)) {
        $sql .= " AND (c.district = :district OR z.district = :district)";
        $params[':district'] = $district;
    }

    $sql .= " ORDER BY c.district ASC, c.name ASC";
    $camps = $db->fetchAll($sql, $params);
}

// Enhance records with compatibility aliases and computed badges
foreach ($camps as &$camp) {
    $camp['camp_name'] = !empty($camp['camp_name']) ? $camp['camp_name'] : $camp['name'];
    $cap = (int)$camp['total_capacity'];
    $occ = (int)$camp['current_occupancy'];
    $camp['vacant_beds'] = max(0, $cap - $occ);
    $camp['available_beds'] = $camp['vacant_beds'];
    $camp['occupancy_percentage'] = $cap > 0 ? round(($occ / $cap) * 100, 1) : 100;

    if ($camp['status'] === 'Full' || $camp['vacant_beds'] <= 0) {
        $camp['marker_color'] = 'red';
        $camp['status_badge'] = 'danger';
    } elseif ($camp['occupancy_percentage'] >= 85) {
        $camp['marker_color'] = 'orange';
        $camp['status_badge'] = 'warning text-dark';
    } else {
        $camp['marker_color'] = 'green';
        $camp['status_badge'] = 'success';
    }
}

Response::success([
    'total' => count($camps),
    'camps' => $camps
], 'Relief camps retrieved successfully.');
