<?php

// Turn off display of notices that break JSON responses
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

header('Content-Type: application/json; charset=utf-8');
ob_start();
/**
 * Dams & Reservoirs Telemetry REST API
 * Flood Risk Prediction & Relief Camp Locator
 */

require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/core/Database.php';
require_once dirname(__DIR__) . '/core/Response.php';
require_once dirname(__DIR__) . '/services/DamDataService.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $action = $_GET['action'] ?? 'list';
    $damId = !empty($_GET['id']) ? (int)$_GET['id'] : null;
    $riverId = !empty($_GET['river_id']) ? (int)$_GET['river_id'] : null;

    try {
        if ($action === 'sync' && $damId) {
            $data = DamDataService::syncTelemetry($damId);
            Response::success($data, 'Dam telemetry refreshed successfully.');
        } elseif ($action === 'downstream' && $damId) {
            $data = DamDataService::getDownstreamImpact($damId);
            Response::success($data, 'Downstream risk impact computed.');
        } elseif ($damId) {
            $dam = DamDataService::getDamById($damId);
            if (!$dam) {
                Response::error('Dam not found.', 404);
            }
            Response::success($dam, 'Dam details retrieved.');
        } else {
            $dams = DamDataService::getAllDams($riverId);
            Response::success($dams, 'Dams retrieved successfully.');
        }
    } catch (Throwable $e) {
        Response::error($e->getMessage(), 500);
    }
} else {
    Response::error('Method not allowed.', 405);
}
