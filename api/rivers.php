<?php
/**
 * Rivers & River Basins REST API
 * Flood Risk Prediction & Relief Camp Locator
 */

require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/core/Database.php';
require_once dirname(__DIR__) . '/core/Response.php';
require_once dirname(__DIR__) . '/services/DamDataService.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    try {
        $rivers = DamDataService::getAllRivers();
        Response::success($rivers, 'Indian river basins retrieved successfully.');
    } catch (Throwable $e) {
        Response::error($e->getMessage(), 500);
    }
} else {
    Response::error('Method not allowed.', 405);
}
