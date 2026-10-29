<?php
/**
 * Hydrological Telemetry REST API
 * Handles cascading Basin & Dam queries, water level telemetry, and downstream impact
 * Flood Risk Prediction & Relief Camp Locator
 */

require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/core/Database.php';
require_once dirname(__DIR__) . '/core/Response.php';
require_once dirname(__DIR__) . '/services/DamDataService.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $action = $_GET['action'] ?? 'dams';
    $basinId = !empty($_GET['basin_id']) ? (int)$_GET['basin_id'] : (!empty($_GET['river_id']) ? (int)$_GET['river_id'] : null);
    $damId = !empty($_GET['id']) ? (int)$_GET['id'] : (!empty($_GET['dam_id']) ? (int)$_GET['dam_id'] : null);

    try {
        $db = Database::getInstance();

        if ($action === 'basins' || $action === 'rivers') {
            // Retrieve basins from river_basins table with fallback to rivers
            $sql = "SELECT rb.id, 
                           rb.name, 
                           rb.river_id, 
                           COALESCE(r.name, rb.name) AS river_name,
                           rb.description,
                           COUNT(d.id) AS dam_count
                    FROM `river_basins` rb
                    LEFT JOIN `rivers` r ON rb.river_id = r.id
                    LEFT JOIN `dams` d ON d.river_id = rb.river_id OR d.river_id = rb.id
                    GROUP BY rb.id, rb.name, rb.river_id, r.name, rb.description
                    ORDER BY rb.name ASC";
            
            $basins = $db->fetchAll($sql);
            if (empty($basins)) {
                $basins = DamDataService::getAllRivers();
            }
            Response::success($basins, 'River basins retrieved successfully.');
        } elseif ($action === 'dams') {
            $params = [];
            $where = "WHERE 1=1";
            if ($basinId !== null && $basinId > 0) {
                // Basin ID can match river_id or river_basins.id
                $where .= " AND (d.river_id = :basin_id OR d.river_id IN (SELECT river_id FROM river_basins WHERE id = :basin_id_sub))";
                $params[':basin_id'] = $basinId;
                $params[':basin_id_sub'] = $basinId;
            }

            $sql = "SELECT d.id, 
                           d.name, 
                           d.river_id, 
                           d.district, 
                           d.state, 
                           d.latitude, 
                           d.longitude, 
                           d.current_storage_tmc, 
                           d.gross_capacity_tmc, 
                           d.live_storage_pct AS storage_percent, 
                           d.water_level_meters AS water_level_m, 
                           d.fsl_meters AS full_reservoir_level_m, 
                           d.inflow_cusecs, 
                           d.outflow_cusecs, 
                           d.gate_status, 
                           d.last_updated,
                           r.name AS river_name,
                           r.basin_name
                    FROM `dams` d
                    LEFT JOIN `rivers` r ON d.river_id = r.id
                    {$where}
                    ORDER BY d.name ASC";

            $dams = $db->fetchAll($sql, $params);
            Response::success($dams, 'Dams retrieved successfully.');
        } elseif ($action === 'dam' || ($damId !== null && $damId > 0)) {
            $dam = DamDataService::getDamById($damId);
            if (!$dam) {
                Response::error('Dam telemetry record not found.', 404);
            }
            Response::success($dam, 'Dam telemetry details retrieved.');
        } else {
            $dams = DamDataService::getAllDams($basinId);
            Response::success($dams, 'Telemetry data retrieved.');
        }
    } catch (Throwable $e) {
        Response::error($e->getMessage(), 500);
    }
} else {
    Response::error('Method not allowed.', 405);
}
