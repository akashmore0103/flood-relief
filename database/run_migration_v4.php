<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/core/Database.php';

try {
    $db = Database::getInstance();
    $sqlFile = __DIR__ . '/migration_v4_targeted_shelters_alerts.sql';
    $sql = file_get_contents($sqlFile);
    
    // Split queries by semicolon outside quotes or execute multi-query
    $pdo = $db->getConnection();
    $pdo->exec($sql);
    
    echo "Migration v4 executed successfully.\n";
    
    $camps = $db->fetchAll("SELECT id, name, dam_id, river_basin_id, supply_status FROM camps WHERE dam_id IS NOT NULL");
    echo "Dams-linked camps count: " . count($camps) . "\n";
    foreach ($camps as $c) {
        echo "Camp: {$c['id']} | {$c['name']} | DamID: {$c['dam_id']} | BasinID: {$c['river_basin_id']} | Supply: {$c['supply_status']}\n";
    }

    $citizens = $db->fetchAll("SELECT id, full_name, email, monitored_dam_id, river_basin_id FROM users WHERE monitored_dam_id IS NOT NULL");
    echo "\nTargeted citizens count: " . count($citizens) . "\n";
    foreach ($citizens as $u) {
        echo "Citizen: {$u['id']} | {$u['full_name']} | {$u['email']} | Dam: {$u['monitored_dam_id']} | Basin: {$u['river_basin_id']}\n";
    }
} catch (Throwable $e) {
    echo "Error executing migration: " . $e->getMessage() . "\n";
}
