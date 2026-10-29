<?php
/**
 * Application Settings & Constants
 * Flood Risk Prediction & Relief Camp Locator
 */

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

// Application Metadata
define('APP_NAME', 'Flood Risk Prediction & Relief Camp Locator');
define('APP_SHORT_NAME', 'FloodRelief');
define('APP_VERSION', '1.0.0');

// Base URL detection with Render reverse proxy & HTTPS support
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
    || (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower($_SERVER['HTTP_X_FORWARDED_SSL']) === 'on')
    || (($_SERVER['SERVER_PORT'] ?? 80) == 443);

$protocol = $isHttps ? "https://" : "http://";

$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

// Force HTTPS for Render deployments
if (str_contains($host, 'render.com')) {
    $protocol = "https://";
}

$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$basePath = preg_replace('#/(views|api|database|core)(/.*)?$#', '', $scriptDir);
$baseUrl = rtrim($protocol . $host . $basePath, '/');

define('BASE_URL', $baseUrl);

// System Defaults
date_default_timezone_set('Asia/Kolkata');
define('SYSTEM_TIMEZONE', 'Asia/Kolkata');

// Session Settings
define('SESSION_LIFETIME', 86400); // 24 hours
define('CSRF_TOKEN_KEY', 'flood_relief_csrf_token');

// Emergency Hotline Numbers (Configurable for citizen guidance)
define('EMERGENCY_HELPLINE_NATIONAL', '112');
define('DISASTER_MANAGEMENT_HELPLINE', '1077');
define('AMBULANCE_HELPLINE', '108');

// Data Freshness Stale Threshold (in hours)
define('DATA_STALE_HOURS', 24);

return [
    'app_name' => APP_NAME,
    'version' => APP_VERSION,
    'base_url' => BASE_URL,
    'timezone' => SYSTEM_TIMEZONE,
    'stale_hours' => DATA_STALE_HOURS
];