<?php
/**
 * Dummy Flood Simulation Engine (Admin Viva Demo Feature)
 * Simulates extreme rainfall, high dam discharges, and river overflows
 * Flood Risk Prediction & Relief Camp Locator
 */

require_once dirname(__DIR__) . '/core/Database.php';
require_once dirname(__DIR__) . '/services/DamDataService.php';

class SimulationService {
    /**
     * Trigger simulated flash flood event
     */
    public static function triggerFlashFlood(
        int $damId,
        float $rainfallMm,
        float $dischargeCusecs,
        string $scenarioName = 'Monsoon Cloudburst & Emergency Discharge',
        ?int $triggeredBy = 1
    ): array {
        $db = Database::getInstance();
        $dam = DamDataService::getDamById($damId);

        if (!$dam) {
            throw new InvalidArgumentException("Invalid dam selected for simulation.");
        }

        $db->beginTransaction();

        try {
            // 1. Escalate Dam Telemetry to Critical Flash Flood State
            $gross = (float)$dam['gross_capacity_tmc'];
            $critStorage = round($gross * 0.985, 2);
            $critWaterLevel = round((float)$dam['fsl_meters'] + 0.6, 2);

            $db->execute("UPDATE `dams` SET 
                `current_storage_tmc` = :storage,
                `live_storage_pct` = 98.50,
                `water_level_meters` = :wlevel,
                `inflow_cusecs` = :inflow,
                `outflow_cusecs` = :outflow,
                `gate_status` = 'All 18 Spillway Gates Fully Open (Emergency Discharge)',
                `last_updated` = NOW()
                WHERE `id` = :id", [
                ':storage' => $critStorage,
                ':wlevel' => $critWaterLevel,
                ':inflow' => (int)($dischargeCusecs * 1.15),
                ':outflow' => (int)$dischargeCusecs,
                ':id' => $damId
            ]);

            // 2. Escalate River Water Level past Danger Mark
            $riverId = (int)$dam['river_id'];
            $db->execute("UPDATE `rivers` SET 
                `current_level_meters` = `danger_mark_meters` + 1.85,
                `status` = 'Danger',
                `updated_at` = NOW()
                WHERE `id` = :rid", [':rid' => $riverId]);

            // 3. Find Downstream Zones connected to this Dam / River
            $downstreamZones = $db->fetchAll(
                "SELECT id, name FROM `zones` WHERE `primary_dam_id` = :dam_id OR `river_id` = :river_id",
                [':dam_id' => $damId, ':river_id' => $riverId]
            );

            // Fallback: If no direct zones matched, target first 2 active zones to ensure demo always demonstrates impact
            if (empty($downstreamZones)) {
                $downstreamZones = $db->fetchAll("SELECT id, name FROM `zones` WHERE `status` = 'active' ORDER BY id ASC LIMIT 2");
            }

            $affectedZoneIds = [];
            foreach ($downstreamZones as $z) {
                $affectedZoneIds[] = (int)$z['id'];
                // Insert a Critical risk_level observation
                $db->insert('risk_levels', [
                    'zone_id' => $z['id'],
                    'rainfall_value' => $rainfallMm,
                    'river_level_value' => 3.85,
                    'score' => 96.50,
                    'category' => 'Critical',
                    'source' => 'Flash Flood Simulator (Viva Demo)',
                    'observed_at' => date('Y-m-d H:i:s')
                ]);
            }

            // 4. Generate Emergency Alert Broadcast
            $alertMsg = "URGENT FLASH FLOOD EMERGENCY: {$dam['name']} has initiated massive emergency discharge of " . 
                         number_format($dischargeCusecs) . " cusecs due to {$rainfallMm}mm/hr cloudburst. River {$dam['river_name']} has crossed danger mark. Immediate floodplain evacuation ordered.";

            $alertId = $db->insert('alerts', [
                'target_zone_id' => !empty($affectedZoneIds[0]) ? $affectedZoneIds[0] : null,
                'title' => "RED ALERT: Flash Flood Warning ({$dam['name']})",
                'message' => $alertMsg,
                'severity' => 'Critical Danger',
                'channel' => 'All',
                'status' => 'Published',
                'created_by' => $triggeredBy,
                'expires_at' => date('Y-m-d H:i:s', strtotime('+24 hours'))
            ]);

            // 5. Record Simulation Log
            $logId = $db->insert('simulation_logs', [
                'triggered_by' => $triggeredBy,
                'dam_id' => $damId,
                'simulated_rainfall_mm' => $rainfallMm,
                'simulated_discharge_cusecs' => (int)$dischargeCusecs,
                'action' => 'Triggered'
            ]);

            $db->commit();

            return [
                'success' => true,
                'log_id' => $logId,
                'dam_name' => $dam['name'],
                'river_name' => $dam['river_name'],
                'affected_zone_count' => count($affectedZoneIds),
                'simulated_discharge' => $dischargeCusecs,
                'simulated_rainfall' => $rainfallMm,
                'alert_id' => $alertId
            ];
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Reset simulation to safe normal baseline
     */
    public static function resetSimulation(?int $damId = null, ?int $resetBy = 1): bool {
        $db = Database::getInstance();
        $db->beginTransaction();

        try {
            $damWhere = $damId ? "WHERE `id` = :dam_id" : "";
            $damParams = $damId ? [':dam_id' => $damId] : [];

            $db->execute("UPDATE `dams` SET 
                `current_storage_tmc` = ROUND(`gross_capacity_tmc` * 0.65, 2),
                `live_storage_pct` = 65.00,
                `inflow_cusecs` = 4500,
                `outflow_cusecs` = 3800,
                `gate_status` = 'All Gates Closed (Standard Flow)',
                `last_updated` = NOW()
                {$damWhere}", $damParams);

            $db->execute("UPDATE `rivers` SET 
                `current_level_meters` = `warning_level_meters` - 2.80,
                `status` = 'Normal',
                `updated_at` = NOW()");

            // Reset observations for all zones
            $zones = $db->fetchAll("SELECT id FROM `zones` WHERE `status` = 'active'");
            foreach ($zones as $z) {
                $db->insert('risk_levels', [
                    'zone_id' => $z['id'],
                    'rainfall_value' => 12.00,
                    'river_level_value' => 0.65,
                    'score' => 28.00,
                    'category' => 'Low',
                    'source' => 'Simulation Reset (Baseline)',
                    'observed_at' => date('Y-m-d H:i:s')
                ]);
            }

            $db->execute("UPDATE `alerts` SET `status` = 'Archived' WHERE `title` LIKE '%RED ALERT%'");

            $db->insert('simulation_logs', [
                'triggered_by' => $resetBy,
                'dam_id' => $damId ?: 1,
                'simulated_rainfall_mm' => 12.00,
                'simulated_discharge_cusecs' => 3800,
                'action' => 'Reset'
            ]);

            $db->commit();
            return true;
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Check if a simulation is currently active
     */
    public static function getActiveSimulation(): ?array {
        $db = Database::getInstance();
        $sql = "SELECT sl.*, 
                       'Extreme Monsoon & Emergency Gate Outflow' AS scenario_name,
                       d.name AS dam_name, r.name AS river_name, u.full_name AS triggered_by_name
                FROM `simulation_logs` sl
                LEFT JOIN `dams` d ON sl.dam_id = d.id
                LEFT JOIN `rivers` r ON d.river_id = r.id
                LEFT JOIN `users` u ON sl.triggered_by = u.id
                WHERE sl.action = 'Triggered'
                ORDER BY sl.created_at DESC
                LIMIT 1";

        $active = $db->fetchOne($sql);
        if ($active) {
            $lastLog = $db->fetchOne("SELECT action FROM `simulation_logs` ORDER BY id DESC LIMIT 1");
            if ($lastLog && $lastLog['action'] === 'Reset') {
                return null;
            }
        }
        return $active;
    }

    /**
     * Retrieve recent simulation history logs
     */
    public static function getRecentLogs(int $limit = 10): array {
        $db = Database::getInstance();
        $sql = "SELECT sl.*, 
                       sl.action AS status,
                       'Simulated Cloudburst & Gate Discharge' AS scenario_name,
                       d.name AS dam_name, r.name AS river_name, u.full_name AS triggered_by_name
                FROM `simulation_logs` sl
                LEFT JOIN `dams` d ON sl.dam_id = d.id
                LEFT JOIN `rivers` r ON d.river_id = r.id
                LEFT JOIN `users` u ON sl.triggered_by = u.id
                ORDER BY sl.created_at DESC
                LIMIT {$limit}";

        return $db->fetchAll($sql);
    }
}
