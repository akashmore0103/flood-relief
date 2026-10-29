<?php
/**
 * Emergency Alert Broadcasting & Delivery Service
 * Complies with PRD Section 5.5 (FR-17 to FR-20)
 */

require_once dirname(__DIR__) . '/core/Database.php';
require_once __DIR__ . '/EmailService.php';

class AlertService {
    /**
     * Create and broadcast an emergency or evacuation alert
     */
    public static function broadcastAlert(array $data, int $adminId): array {
        $db = Database::getInstance();

        $targetZoneId = !empty($data['target_zone_id']) ? (int)$data['target_zone_id'] : null;
        $channel = in_array($data['channel'] ?? '', ['In-App', 'SMS', 'Email', 'All']) ? $data['channel'] : 'In-App';
        $severity = in_array($data['severity'] ?? '', ['Advisory', 'Warning', 'Evacuation', 'Critical Danger']) 
            ? $data['severity'] 
            : 'Warning';

        $db->beginTransaction();

        try {
            $alertData = [
                'title' => trim($data['title']),
                'message' => trim($data['message']),
                'severity' => $severity,
                'target_zone_id' => $targetZoneId,
                'channel' => $channel,
                'status' => 'Published',
                'is_active' => 1,
                'created_by' => $adminId,
                'expires_at' => !empty($data['expires_at']) ? date('Y-m-d H:i:s', strtotime($data['expires_at'])) : date('Y-m-d H:i:s', strtotime('+48 hours'))
            ];

            $alertId = $db->insert('alerts', $alertData);

            // Log Simulated Delivery Attempts
            $targetDesc = $targetZoneId ? "Residents in Zone #{$targetZoneId}" : "All Citizens across all monitoring zones";
            
            if ($channel === 'All' || $channel === 'In-App') {
                $db->insert('alert_delivery_logs', [
                    'alert_id' => $alertId,
                    'channel' => 'In-App Broadcast',
                    'recipient_target' => $targetDesc,
                    'delivery_status' => 'Delivered',
                    'details' => 'Broadcasted to public top alert banner across portal.'
                ]);
            }

            if ($channel === 'All' || $channel === 'SMS') {
                $db->insert('alert_delivery_logs', [
                    'alert_id' => $alertId,
                    'channel' => 'SMS Gateway Simulation',
                    'recipient_target' => $targetDesc,
                    'delivery_status' => 'Sent',
                    'details' => 'Emergency SMS notification queued for registered zone phone numbers.'
                ]);
            }

            if ($channel === 'All' || $channel === 'Email') {
                $targetCitizens = $db->fetchAll("SELECT id, full_name, email FROM `users` WHERE `role_id` = 3 AND `email` IS NOT NULL AND `email` != ''");
                if (empty($targetCitizens)) {
                    $targetCitizens = [['id' => 0, 'full_name' => 'Citizen', 'email' => 'citizen@example.com']];
                }

                foreach ($targetCitizens as $cit) {
                    $res = EmailService::sendEmergencyAlert([
                        'name' => $cit['full_name'],
                        'email' => $cit['email']
                    ], [
                        'title' => $alertData['title'],
                        'message' => $alertData['message'],
                        'severity' => $severity,
                        'dam_name' => 'Monitored River Watershed',
                        'basin_name' => 'Regional River Basin'
                    ]);

                    $status = $res['sent'] ? 'Sent' : 'Failed';
                    $detail = $res['sent'] ? "Dispatched via {$res['smtp_host_used']}" : "SMTP Error: {$res['error']} (Host: {$res['smtp_host_used']})";
                    EmailService::logDelivery($alertId, 'Email (SMTP)', $cit['email'], $status, $detail);
                }
            }

            // Record high-level audit log
            $db->insert('audit_logs', [
                'user_id' => $adminId,
                'action_type' => 'ALERT_BROADCAST',
                'table_affected' => 'alerts',
                'record_id' => $alertId,
                'details' => "Broadcasted {$severity} alert: '{$alertData['title']}' to " . ($targetZoneId ? "Zone {$targetZoneId}" : "All Zones"),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
            ]);

            $db->commit();

            return [
                'id' => $alertId,
                'title' => $alertData['title'],
                'severity' => $severity,
                'status' => 'Published',
                'created_at' => date('Y-m-d H:i:s')
            ];
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Get active alerts optionally filtered by zone
     * Only returns alerts where is_active = 1, status = 'Published', and unexpired
     */
    public static function getActiveAlerts(?int $zoneId = null): array {
        $db = Database::getInstance();
        $sql = "SELECT a.*, a.expires_at AS valid_until, z.name AS zone_name, u.full_name AS author_name 
                FROM `alerts` a
                LEFT JOIN `zones` z ON a.target_zone_id = z.id
                LEFT JOIN `users` u ON a.created_by = u.id
                WHERE a.status = 'Published'
                  AND a.is_active = TRUE
                  AND (a.expires_at IS NULL OR a.expires_at > NOW())";

        $params = [];
        if ($zoneId) {
            $sql .= " AND (a.target_zone_id = :zone_id OR a.target_zone_id IS NULL)";
            $params[':zone_id'] = $zoneId;
        }

        $sql .= " ORDER BY CASE a.severity WHEN 'Critical Danger' THEN 1 WHEN 'Evacuation' THEN 2 WHEN 'Warning' THEN 3 WHEN 'Advisory' THEN 4 ELSE 5 END, a.created_at DESC";

        return $db->fetchAll($sql, $params);
    }

    /**
     * Deactivate / revoke an emergency alert (soft-deactivation)
     * Sets is_active = false and status = 'Archived'
     */
    public static function deactivateAlert(int $alertId, ?int $adminId = null): bool {
        $db = Database::getInstance();
        $alert = $db->fetchOne("SELECT * FROM `alerts` WHERE `id` = :id", [':id' => $alertId]);
        if (!$alert) {
            throw new InvalidArgumentException("Emergency alert #{$alertId} not found.");
        }

        $db->beginTransaction();
        try {
            $db->update('alerts', [
                'is_active' => false,
                'status' => 'Archived'
            ], 'id = :id', [':id' => $alertId]);

            if ($adminId) {
                $db->insert('audit_logs', [
                    'user_id' => $adminId,
                    'action_type' => 'ALERT_DEACTIVATED',
                    'table_affected' => 'alerts',
                    'record_id' => $alertId,
                    'details' => "Deactivated emergency alert #{$alertId}: '{$alert['title']}'",
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
                ]);
            }

            $db->commit();
            return true;
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Delete an emergency alert (hard removal)
     * Removes from alerts and cascades delivery logs
     */
    public static function deleteAlert(int $alertId, ?int $adminId = null): bool {
        $db = Database::getInstance();
        $alert = $db->fetchOne("SELECT * FROM `alerts` WHERE `id` = :id", [':id' => $alertId]);
        if (!$alert) {
            throw new InvalidArgumentException("Emergency alert #{$alertId} not found.");
        }

        $db->beginTransaction();
        try {
            // Delete associated delivery logs
            $db->delete('alert_delivery_logs', 'alert_id = :aid', [':aid' => $alertId]);

            // Delete alert record
            $db->delete('alerts', 'id = :id', [':id' => $alertId]);

            if ($adminId) {
                $db->insert('audit_logs', [
                    'user_id' => $adminId,
                    'action_type' => 'ALERT_DELETED',
                    'table_affected' => 'alerts',
                    'record_id' => $alertId,
                    'details' => "Permanently deleted emergency alert #{$alertId}: '{$alert['title']}'",
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
                ]);
            }

            $db->commit();
            return true;
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Retrieve all alerts with author and delivery count
     */
    public static function getAllAlerts(?bool $activeOnly = false): array {
        $db = Database::getInstance();
        $where = "";
        if ($activeOnly) {
            $where = "WHERE a.status = 'Published' AND a.is_active = TRUE AND (a.expires_at IS NULL OR a.expires_at > NOW())";
        }

        return $db->fetchAll("
            SELECT a.*, a.expires_at AS valid_until, z.name AS zone_name, u.full_name AS created_by_name,
                   (SELECT COUNT(*) FROM `alert_delivery_logs` WHERE alert_id = a.id) AS delivery_count
            FROM `alerts` a
            LEFT JOIN `zones` z ON a.target_zone_id = z.id
            LEFT JOIN `users` u ON a.created_by = u.id
            {$where}
            ORDER BY a.created_at DESC
        ");
    }
}
