<?php
/**
 * PostgreSQL Automated Migration & Database Seeder
 * Supports Local PostgreSQL 15+, 16+, 17+ and Render.com Production Deployment
 * Flood Risk Prediction & Relief Camp Locator
 * 
 * Usage:
 *   php database/migrate_pgsql.php
 *   php database/migrate_pgsql.php --fresh
 */

// Enable strict reporting
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Resolve root directory
$appRoot = dirname(__DIR__);
if (!defined('APP_ROOT')) {
    define('APP_ROOT', $appRoot);
}

echo "====================================================================\n";
echo "  FloodRelief DSS - PostgreSQL Migration & Seed Engine (v5.0)\n";
echo "====================================================================\n";

// 1. Load database configuration
$configFile = $appRoot . '/config/database.php';
if (!file_exists($configFile)) {
    fwrite(STDERR, "[FATAL] Missing configuration file: {$configFile}\n");
    exit(1);
}
$config = require $configFile;

$host     = $config['host'];
$port     = $config['port'];
$database = $config['database'];
$user     = $config['username'];
$pass     = $config['password'];
$sslmode  = $config['sslmode'] ?? 'prefer';

echo "[CONFIG] Target DB Host    : {$host}:{$port}\n";
echo "[CONFIG] Database Name     : {$database}\n";
echo "[CONFIG] Username          : {$user}\n";
echo "[CONFIG] SSL Mode          : {$sslmode}\n";

// 2. Check if database exists, create if missing (Local / Admin role)
$isCloud = !empty(getenv('DATABASE_URL')) || !empty($_ENV['DATABASE_URL'] ?? null);

if (!$isCloud) {
    try {
        echo "[1/4] Checking target PostgreSQL database presence...\n";
        $adminDsn = "pgsql:host={$host};port={$port};dbname=postgres;sslmode={$sslmode}";
        $adminPdo = new PDO($adminDsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5
        ]);
        
        $checkStmt = $adminPdo->prepare("SELECT 1 FROM pg_database WHERE datname = :dbname");
        $checkStmt->execute([':dbname' => $database]);
        $exists = (bool)$checkStmt->fetchColumn();
        
        if (!$exists) {
            echo "[INFO] Database '{$database}' does not exist. Creating database...\n";
            // CREATE DATABASE cannot be run in a transaction block
            $adminPdo->exec("CREATE DATABASE \"{$database}\" WITH ENCODING 'UTF8';");
            echo "[SUCCESS] Database '{$database}' created successfully.\n";
        } else {
            echo "[INFO] Database '{$database}' verified existing.\n";
        }
    } catch (Throwable $e) {
        echo "[NOTICE] Maintenance DB check skipped ({$e->getMessage()}). Proceeding directly...\n";
    }
}

// 3. Connect to the target database
try {
    echo "[2/4] Connecting to '{$database}' via pdo_pgsql...\n";
    $targetDsn = "pgsql:host={$host};port={$port};dbname={$database};sslmode={$sslmode}";
    $pdo = new PDO($targetDsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
    echo "[SUCCESS] Connected successfully to PostgreSQL server!\n";
} catch (PDOException $e) {
    fwrite(STDERR, "[ERROR] Unable to connect to database '{$database}': " . $e->getMessage() . "\n");
    exit(1);
}

// 4. Run Schema Migration
$schemaFile = __DIR__ . '/schema_pgsql.sql';
if (!file_exists($schemaFile)) {
    fwrite(STDERR, "[FATAL] Schema file missing at: {$schemaFile}\n");
    exit(1);
}

echo "[3/4] Executing schema DDLs from database/schema_pgsql.sql...\n";
$schemaSql = file_get_contents($schemaFile);

try {
    $pdo->beginTransaction();
    $pdo->exec($schemaSql);
    $pdo->commit();
    echo "[SUCCESS] All PostgreSQL tables, indexes, and views successfully created.\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "[ERROR] Schema execution failed: " . $e->getMessage() . "\n");
    exit(1);
}

// 5. Run Seed Inserts
$seedFile = __DIR__ . '/seed_pgsql.sql';
if (!file_exists($seedFile)) {
    fwrite(STDERR, "[FATAL] Seed file missing at: {$seedFile}\n");
    exit(1);
}

echo "[4/4] Seeding initial telemetry, Maharashtra dams, relief shelters, & test users...\n";
$seedSql = file_get_contents($seedFile);

try {
    $pdo->beginTransaction();
    $pdo->exec($seedSql);
    $pdo->commit();
    echo "[SUCCESS] Seed data loaded and primary key sequences synchronized.\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "[ERROR] Seeding execution failed: " . $e->getMessage() . "\n");
    exit(1);
}

// 6. Verify table row counts
echo "\n====================================================================\n";
echo "                      DATABASE VERIFICATION SUMMARY                 \n";
echo "====================================================================\n";
printf("%-26s | %-12s | %-10s\n", "Table / Entity Name", "Rows Seeded", "Health Status");
echo "---------------------------+--------------+------------\n";

$tablesToVerify = [
    'roles',
    'rivers',
    'river_basins',
    'dams',
    'zones',
    'risk_rules',
    'camps',
    'users',
    'citizen_profiles',
    'help_requests',
    'help_request_logs',
    'alerts',
    'alert_delivery_logs',
    'missing_persons',
    'camp_updates',
    'audit_logs',
    'user_preferences',
    'system_settings'
];

$totalRecords = 0;
foreach ($tablesToVerify as $tbl) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM {$tbl}");
        $count = (int)$stmt->fetchColumn();
        $totalRecords += $count;
        $status = $count > 0 ? "OK" : "EMPTY";
        printf("%-26s | %-12d | %-10s\n", $tbl, $count, $status);
    } catch (Throwable $e) {
        printf("%-26s | %-12s | %-10s\n", $tbl, "ERROR", "FAILED");
    }
}

echo "---------------------------+--------------+------------\n";
printf("%-26s | %-12d | %-10s\n", "TOTAL SEEDED RECORDS", $totalRecords, "ONLINE");
echo "====================================================================\n";
echo " PostgreSQL Migration & Seeding Complete! Application is Ready.\n";
echo "====================================================================\n";
