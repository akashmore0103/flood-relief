<?php
/**
 * Relief Camp Locator & Geospatial Proximity Service
 * Implements Haversine distance calculations and capacity management
 * Complies with PRD Section 5.3 (FR-09 to FR-12)
 */

require_once dirname(__DIR__) . '/core/Database.php';

class CampLocatorService {
    /**
     * Search nearby relief camps using spherical Haversine trigonometric formula
     * 
     * @param float $userLat User current or queried latitude
     * @param float $userLng User current or queried longitude
     * @param float $radiusKm Search radius in kilometers (default 25km)
     * @param string|null $statusFilter Optional filter ('Operational', 'Available', 'All')
     * @param int|null $zoneId Optional zone constraint
     * @return array Camps sorted by nearest distance
     */
    /**
     * Search nearby relief camps using spherical Haversine trigonometric formula
     * 
     * @param float|null $userLat User current or queried latitude
     * @param float|null $userLng User current or queried longitude
     * @param float|string|null $radiusKm Search radius in kilometers, or 'all' / null for Pan-India
     * @param string|null $statusFilter Optional filter ('Operational', 'Available', 'All')
     * @param int|null $zoneId Optional zone constraint
     * @return array Camps sorted by nearest distance (or state/name if no coordinates)
     */
    public static function getNearbyCamps(
        ?float $userLat = null, 
        ?float $userLng = null, 
        mixed $radiusKm = 25.0, 
        ?string $statusFilter = null, 
        ?int $zoneId = null,
        ?int $damId = null
    ): array {
        $db = Database::getInstance();
        $hasCoords = ($userLat !== null && $userLng !== null && $userLat >= -90 && $userLat <= 90 && $userLng >= -180 && $userLng <= 180);
        $isAllRadius = ($radiusKm === null || $radiusKm === 'all' || $radiusKm === '' || (is_numeric($radiusKm) && (float)$radiusKm <= 0));

        $distanceSelect = "";
        $params = [];

        if ($hasCoords) {
            $distanceSelect = ", ROUND(
                CAST(6371 * 2 * ASIN(SQRT(
                    POWER(SIN((RADIANS(c.latitude) - RADIANS(:lat1)) / 2), 2) +
                    COS(RADIANS(:lat2)) * COS(RADIANS(c.latitude)) *
                    POWER(SIN((RADIANS(c.longitude) - RADIANS(:lng)) / 2), 2)
                )) AS numeric), 2
            ) AS distance_km";
            $params[':lat1'] = $userLat;
            $params[':lat2'] = $userLat;
            $params[':lng'] = $userLng;
        } else {
            $distanceSelect = ", NULL AS distance_km";
        }

        $sql = "SELECT c.*, 
                       z.name AS zone_name,
                       z.district AS zone_district,
                       z.state AS zone_state,
                       (c.total_capacity - c.current_occupancy) AS available_beds
                       {$distanceSelect}
                FROM `camps` c
                LEFT JOIN `zones` z ON c.zone_id = z.id
                WHERE c.active = TRUE";

        if ($damId) {
            $sql .= " AND c.dam_id = :dam_id";
            $params[':dam_id'] = $damId;
        }

        if ($zoneId) {
            $sql .= " AND c.zone_id = :zone_id";
            $params[':zone_id'] = $zoneId;
        }

        if ($statusFilter === 'Operational') {
            $sql .= " AND c.status = 'Operational'";
        } elseif ($statusFilter === 'Available') {
            $sql .= " AND c.status = 'Operational' AND (c.total_capacity - c.current_occupancy) > 0";
        }

        if ($hasCoords && !$isAllRadius) {
            $sql = "SELECT * FROM ({$sql}) AS camp_results WHERE distance_km <= :radius ORDER BY distance_km ASC";
            $params[':radius'] = (float)$radiusKm;
        } elseif ($hasCoords) {
            $sql = "SELECT * FROM ({$sql}) AS camp_results ORDER BY distance_km ASC";
        } else {
            $sql .= " ORDER BY z.state ASC, c.district ASC, c.name ASC";
        }

        $camps = $db->fetchAll($sql, $params);

        // Enhance with occupancy percentage, UI color tags, and compatibility fields
        foreach ($camps as &$camp) {
            $camp['camp_name'] = !empty($camp['camp_name']) ? $camp['camp_name'] : $camp['name'];
            $cap = (int)$camp['total_capacity'];
            $occ = (int)$camp['current_occupancy'];
            $camp['vacant_beds'] = max(0, $cap - $occ);
            $camp['available_beds'] = $camp['vacant_beds'];
            $camp['occupancy_percentage'] = $cap > 0 ? round(($occ / $cap) * 100, 1) : 100;

            if ($camp['status'] === 'Full' || $camp['vacant_beds'] <= 0) {
                $camp['marker_color'] = 'red';
                $camp['status_badge'] = 'danger';
            } elseif ($camp['occupancy_percentage'] >= 85) {
                $camp['marker_color'] = 'orange';
                $camp['status_badge'] = 'warning text-dark';
            } else {
                $camp['marker_color'] = 'green';
                $camp['status_badge'] = 'success';
            }
        }

        return $camps;
    }

    /**
     * Retrieve all active relief camps across India or specific zone
     */
    public static function getAllCamps(
        ?int $zoneId = null, 
        ?string $statusFilter = null, 
        ?float $userLat = null, 
        ?float $userLng = null
    ): array {
        return self::getNearbyCamps($userLat, $userLng, 'all', $statusFilter, $zoneId);
    }

    /**
     * Update camp live capacity and operating status (Coordinator / Admin action)
     */
    public static function updateOccupancy(
        int $campId, 
        int $newOccupancy, 
        string $newStatus, 
        int $updatedBy, 
        ?string $remarks = null
    ): bool {
        $db = Database::getInstance();
        $camp = $db->fetchOne("SELECT * FROM `camps` WHERE `id` = :id LIMIT 1", [':id' => $campId]);

        if (!$camp) {
            throw new Exception("Relief camp not found.");
        }

        $prevOccupancy = (int)$camp['current_occupancy'];
        $prevStatus = $camp['status'];

        $db->beginTransaction();

        try {
            // Update camp record
            $db->update('camps', [
                'current_occupancy' => $newOccupancy,
                'status' => $newStatus,
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = :id', [':id' => $campId]);

            // Record update history in camp_updates table
            $db->insert('camp_updates', [
                'camp_id' => $campId,
                'previous_occupancy' => $prevOccupancy,
                'new_occupancy' => $newOccupancy,
                'previous_status' => $prevStatus,
                'new_status' => $newStatus,
                'updated_by' => $updatedBy,
                'remarks' => $remarks
            ]);

            // Record audit log
            $db->insert('audit_logs', [
                'user_id' => $updatedBy,
                'action_type' => 'CAMP_STATUS_UPDATE',
                'table_affected' => 'camps',
                'record_id' => $campId,
                'details' => "Camp '{$camp['name']}' occupancy updated from {$prevOccupancy} to {$newOccupancy}. Status: {$newStatus}",
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
            ]);

            $db->commit();
            return true;
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }
}
