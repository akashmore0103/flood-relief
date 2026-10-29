<?php
/**
 * Emergency Help Request Lifecycle & Triage Service
 * Complies with PRD Section 5.4 (FR-13 to FR-16)
 */

require_once dirname(__DIR__) . '/core/Database.php';
require_once dirname(__DIR__) . '/core/Helper.php';

class HelpRequestService {
    /**
     * Create a new emergency help request with an audit log and unique reference code
     */
    public static function createRequest(array $data, ?int $citizenId = null): array {
        $db = Database::getInstance();

        $refCode = Helper::generateReferenceCode();

        $db->beginTransaction();

        try {
            $waterSeverity = in_array($data['water_severity'] ?? '', ['Ankle-Level', 'Waist-Level', 'Roof-Level'], true)
                ? $data['water_severity']
                : 'Waist-Level';

            $insertData = [
                'reference_code' => $refCode,
                'citizen_id' => $citizenId,
                'guest_name' => trim($data['guest_name']),
                'guest_phone' => trim($data['guest_phone']),
                'guest_email' => !empty($data['guest_email']) ? trim($data['guest_email']) : null,
                'request_type' => $data['request_type'] ?? 'Evacuation',
                'urgency' => $data['urgency'] ?? 'High',
                'people_count' => max(1, (int)($data['people_count'] ?? 1)),
                'water_severity' => $waterSeverity,
                'mobile_verified' => true,
                'description' => trim($data['description']),
                'address' => trim($data['address']),
                'latitude' => (float)$data['latitude'],
                'longitude' => (float)$data['longitude'],
                'status' => 'New',
                'assigned_to' => null
            ];

            $requestId = $db->insert('help_requests', $insertData);

            // Create initial lifecycle audit log in help_request_logs
            $db->insert('help_request_logs', [
                'request_id' => $requestId,
                'action' => 'Ticket Created',
                'previous_status' => null,
                'new_status' => 'New',
                'action_by' => $citizenId,
                'notes' => 'Emergency ticket registered by citizen/guest through public portal.'
            ]);

            $db->commit();

            return [
                'id' => $requestId,
                'reference_code' => $refCode,
                'status' => 'New',
                'created_at' => date('Y-m-d H:i:s')
            ];
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Look up request and timeline logs by Reference Code (Public Tracker)
     */
    public static function getByReference(string $referenceCode): ?array {
        $db = Database::getInstance();

        $sql = "SELECT hr.*, 
                       u.full_name AS assigned_coordinator_name,
                       u.phone AS assigned_coordinator_phone
                FROM `help_requests` hr
                LEFT JOIN `users` u ON hr.assigned_to = u.id
                WHERE hr.reference_code = :ref
                LIMIT 1";

        $request = $db->fetchOne($sql, [':ref' => trim($referenceCode)]);

        if (!$request) {
            return null;
        }

        // Fetch lifecycle timeline logs
        $logsSql = "SELECT hl.*, u.full_name AS responder_name 
                    FROM `help_request_logs` hl
                    LEFT JOIN `users` u ON hl.action_by = u.id
                    WHERE hl.request_id = :req_id
                    ORDER BY hl.created_at ASC";

        $request['logs'] = $db->fetchAll($logsSql, [':req_id' => $request['id']]);

        return $request;
    }

    /**
     * Update request status and assignment (Coordinator / Admin action)
     */
    public static function updateStatus(
        int $requestId, 
        string $newStatus, 
        ?int $assignedTo = null, 
        ?string $notes = null, 
        int $actionBy = 1
    ): bool {
        $db = Database::getInstance();

        $current = $db->fetchOne("SELECT * FROM `help_requests` WHERE `id` = :id LIMIT 1", [':id' => $requestId]);
        if (!$current) {
            throw new Exception("Emergency help request not found.");
        }

        $prevStatus = $current['status'];
        $db->beginTransaction();

        try {
            $updateFields = [
                'status' => $newStatus,
                'updated_at' => date('Y-m-d H:i:s')
            ];

            if ($assignedTo !== null) {
                $updateFields['assigned_to'] = $assignedTo > 0 ? $assignedTo : null;
            }

            $db->update('help_requests', $updateFields, 'id = :id', [':id' => $requestId]);

            // Append status transition log
            $db->insert('help_request_logs', [
                'request_id' => $requestId,
                'action' => 'Status Updated to ' . $newStatus,
                'previous_status' => $prevStatus,
                'new_status' => $newStatus,
                'action_by' => $actionBy,
                'notes' => $notes ?: "Status changed from {$prevStatus} to {$newStatus}."
            ]);

            $db->commit();
            return true;
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Get all distress tickets for a logged-in citizen
     */
    public static function getUserRequests(int $citizenId): array {
        $db = Database::getInstance();
        $sql = "SELECT hr.*, 
                       u.full_name AS assigned_coordinator_name,
                       u.phone AS assigned_coordinator_phone
                FROM `help_requests` hr
                LEFT JOIN `users` u ON hr.assigned_to = u.id
                WHERE hr.citizen_id = :cid
                ORDER BY hr.created_at DESC";

        $requests = $db->fetchAll($sql, [':cid' => $citizenId]);

        foreach ($requests as &$req) {
            $req['logs'] = $db->fetchAll(
                "SELECT * FROM `help_request_logs` WHERE `request_id` = :rid ORDER BY `created_at` ASC",
                [':rid' => $req['id']]
            );
        }

        return $requests;
    }

    /**
     * Get all requests for Coordinator Triage queue
     */
    public static function getCoordinatorRequests(?string $status = null, ?string $urgency = null): array {
        $db = Database::getInstance();
        $where = ["1=1"];
        $params = [];

        if (!empty($status)) {
            $where[] = "hr.status = :status";
            $params[':status'] = $status;
        }
        if (!empty($urgency)) {
            $where[] = "hr.urgency = :urgency";
            $params[':urgency'] = $urgency;
        }

        $whereClause = implode(' AND ', $where);

        $sql = "SELECT hr.*, 
                       u.full_name AS assigned_coordinator_name,
                       u.phone AS assigned_coordinator_phone,
                       cit.full_name AS registered_citizen_name
                FROM `help_requests` hr
                LEFT JOIN `users` u ON hr.assigned_to = u.id
                LEFT JOIN `users` cit ON hr.citizen_id = cit.id
                WHERE {$whereClause}
                ORDER BY 
                    CASE hr.urgency 
                        WHEN 'Critical' THEN 1 
                        WHEN 'High' THEN 2 
                        WHEN 'Medium' THEN 3 
                        ELSE 4 
                    END,
                    hr.created_at DESC";

        return $db->fetchAll($sql, $params);
    }
}
