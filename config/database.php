<?php
/**
 * Database Configuration
 * Supports PostgreSQL 15+, 16+, 17+ (Local & Render.com Production)
 * Flood Risk Prediction & Relief Camp Locator
 */

// Define App Root
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

// ----------------------------------------------------------------------------
// 1. Environment Variable Loader (.env)
// ----------------------------------------------------------------------------
$envFile = APP_ROOT . '/.env';
if (!file_exists($envFile)) {
    $envFile = dirname(__DIR__) . '/.env';
}

if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || str_starts_with($line, '#') || str_starts_with($line, ';')) {
            continue;
        }
        if (str_contains($line, '=')) {
            [$key, $val] = explode('=', $line, 2);
            $key = trim($key);
            $val = trim($val);
            // Strip wrapping quotes
            if ((str_starts_with($val, '"') && str_ends_with($val, '"')) ||
                (str_starts_with($val, "'") && str_ends_with($val, "'"))) {
                $val = substr($val, 1, -1);
            }
            if (!array_key_exists($key, $_SERVER) && !array_key_exists($key, $_ENV)) {
                putenv("{$key}={$val}");
                $_ENV[$key] = $val;
                $_SERVER[$key] = $val;
            }
        }
    }
}

// ----------------------------------------------------------------------------
// 2. Resolve Database Connection Settings (Render.com DATABASE_URL vs .env)
// ----------------------------------------------------------------------------
$databaseUrl = getenv('DATABASE_URL') ?: ($_ENV['DATABASE_URL'] ?? ($_SERVER['DATABASE_URL'] ?? null));

if (!empty($databaseUrl)) {
    // Dynamic Render.com / Cloud PostgreSQL URL parsing
    // Format: postgresql://user:password@host:port/dbname?sslmode=require
    $parsedUrl = parse_url($databaseUrl);
    
    $driver   = 'pgsql';
    $host     = $parsedUrl['host'] ?? '127.0.0.1';
    $port     = $parsedUrl['port'] ?? 5432;
    $database = isset($parsedUrl['path']) ? ltrim($parsedUrl['path'], '/') : 'flood_relief_db';
    $username = $parsedUrl['user'] ?? 'postgres';
    $password = $parsedUrl['pass'] ?? '';
    
    // Parse query parameters (e.g. sslmode=require)
    $sslmode = 'require';
    if (!empty($parsedUrl['query'])) {
        parse_str($parsedUrl['query'], $queryParams);
        if (!empty($queryParams['sslmode'])) {
            $sslmode = $queryParams['sslmode'];
        }
    }
} else {
    // Local / Standard environment configuration
    $driver   = getenv('DB_CONNECTION') ?: ($_ENV['DB_CONNECTION'] ?? 'pgsql');
    $host     = getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? '127.0.0.1');
    $port     = getenv('DB_PORT') ?: ($_ENV['DB_PORT'] ?? 5432);
    $database = getenv('DB_NAME') ?: ($_ENV['DB_NAME'] ?? 'flood_relief_db');
    $username = getenv('DB_USER') ?: ($_ENV['DB_USER'] ?? 'postgres');
    $password = getenv('DB_PASS') !== false ? (getenv('DB_PASS') ?: '') : ($_ENV['DB_PASS'] ?? 'postgres');
    $sslmode  = getenv('DB_SSLMODE') ?: ($_ENV['DB_SSLMODE'] ?? 'prefer');
}

// Backwards-compatibility constants
if (!defined('DB_CONNECTION')) define('DB_CONNECTION', $driver);
if (!defined('DB_HOST')) define('DB_HOST', $host);
if (!defined('DB_PORT')) define('DB_PORT', $port);
if (!defined('DB_NAME')) define('DB_NAME', $database);
if (!defined('DB_USER')) define('DB_USER', $username);
if (!defined('DB_PASS')) define('DB_PASS', $password);
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8');

// ----------------------------------------------------------------------------
// 3. Return Configuration Array
// ----------------------------------------------------------------------------
return [
    'driver'   => $driver,
    'host'     => $host,
    'port'     => $port,
    'database' => $database,
    'username' => $username,
    'password' => $password,
    'charset'  => 'utf8',
    'sslmode'  => $sslmode,
    'options'  => [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]
];
