<?php
/**
 * Maps & Geolocation Configuration
 * Flood Risk Prediction & Relief Camp Locator
 */

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

// Google Maps API Key
// Replace 'YOUR_GOOGLE_MAPS_API_KEY' with your actual key if available.
// If left empty or invalid, the frontend MapAdapter automatically falls back to Leaflet + OpenStreetMap!
define('GOOGLE_MAPS_API_KEY', getenv('GOOGLE_MAPS_API_KEY') ?: '');

// Map Provider Strategy: 'auto' | 'google' | 'leaflet'
// 'auto': uses Google Maps if API key is provided and valid, otherwise uses Leaflet.js + OpenStreetMap.
define('MAP_PROVIDER', 'auto');

// Regional Default Map Center (Default: Central Flood Monitoring Region / Ernakulam Basin)
define('MAP_DEFAULT_LAT', 10.0159);
define('MAP_DEFAULT_LNG', 76.3419);
define('MAP_DEFAULT_ZOOM', 11);

// Radius Filter Options (in Kilometers)
define('MAP_RADIUS_OPTIONS', [5, 10, 20, 50, 100]);

return [
    'google_maps_api_key' => GOOGLE_MAPS_API_KEY,
    'provider' => MAP_PROVIDER,
    'default_lat' => MAP_DEFAULT_LAT,
    'default_lng' => MAP_DEFAULT_LNG,
    'default_zoom' => MAP_DEFAULT_ZOOM,
    'radius_options' => MAP_RADIUS_OPTIONS
];
