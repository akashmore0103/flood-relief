<?php
/**
 * Nearby Relief Camps API Endpoint
 * Complies with PRD Endpoint: GET /api/camps/nearby
 */

require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/core/Database.php';
require_once dirname(__DIR__) . '/core/Response.php';
require_once dirname(__DIR__) . '/services/CampLocatorService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Method Not Allowed. Use GET.', 405);
}

// User location coordinates (optional)
$lat = (isset($_GET['lat']) && is_numeric($_GET['lat'])) ? (float)$_GET['lat'] : null;
$lng = (isset($_GET['lng']) && is_numeric($_GET['lng'])) ? (float)$_GET['lng'] : null;
$radiusRaw = $_GET['radius'] ?? 'all';
$radius = ($radiusRaw === 'all' || $radiusRaw === '' || (float)$radiusRaw <= 0) ? null : (float)$radiusRaw;
$status = $_GET['status'] ?? 'All';
$zoneId = !empty($_GET['zone_id']) ? (int)$_GET['zone_id'] : null;
$damId = !empty($_GET['dam_id']) ? (int)$_GET['dam_id'] : null;

if ($lat !== null && $lng !== null && ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180)) {
    Response::error('Invalid latitude or longitude coordinate bounds.', 422);
}

$camps = CampLocatorService::getNearbyCamps($lat, $lng, $radius, $status, $zoneId, $damId);

$msg = $radius 
    ? "Found " . count($camps) . " relief camps within {$radius}km radius." 
    : "Found " . count($camps) . " operational relief camps across all regions.";

Response::success([
    'query_coordinates' => ($lat !== null && $lng !== null) ? ['lat' => $lat, 'lng' => $lng] : null,
    'search_radius_km' => $radius ?? 'all',
    'total_found' => count($camps),
    'camps' => $camps
], $msg);
