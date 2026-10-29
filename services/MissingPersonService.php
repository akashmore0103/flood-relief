<?php
/**
 * Missing & Found Persons Registry Service
 * Community search board and disaster tracing module
 * Flood Risk Prediction & Relief Camp Locator
 */

require_once dirname(__DIR__) . '/core/Database.php';
require_once dirname(__DIR__) . '/core/Helper.php';

class MissingPersonService {
    /**
     * Search and filter missing/found registry
     */
    public static function search(array $filters = []): array {
        $db = Database::getInstance();
        $where = ["1=1"];
        $params = [];

        if (!empty($filters['query'])) {
            $where[] = "(mp.person_name LIKE :q OR mp.last_seen_or_found_location LIKE :q OR mp.notes LIKE :q)";
            $params[':q'] = '%' . trim($filters['query']) . '%';
        }

        if (!empty($filters['status'])) {
            if (in_array($filters['status'], ['Missing', 'Found'], true)) {
                $where[] = "mp.report_type = :rtype";
                $params[':rtype'] = $filters['status'];
            } elseif ($filters['status'] === 'Reunited') {
                $where[] = "mp.status = 'Reunited'";
            } elseif ($filters['status'] === 'Sheltered') {
                $where[] = "mp.camp_id IS NOT NULL";
            }
        }

        if (!empty($filters['gender']) && in_array($filters['gender'], ['Male', 'Female', 'Child', 'Other'], true)) {
            $where[] = "mp.gender = :gender";
            $params[':gender'] = $filters['gender'];
        }

        if (!empty($filters['camp_id'])) {
            $where[] = "mp.camp_id = :camp_id";
            $params[':camp_id'] = (int)$filters['camp_id'];
        }

        $whereClause = implode(' AND ', $where);

        $sql = "SELECT mp.*, 
                       mp.report_type AS status,
                       mp.last_seen_or_found_location AS last_seen_location,
                       mp.last_seen_or_found_location AS found_location,
                       mp.notes AS description,
                       mp.camp_id AS sheltered_at_camp_id,
                       c.name AS camp_name, 
                       c.address AS camp_address,
                       u.full_name AS reporter_user_name
                FROM `missing_persons` mp
                LEFT JOIN `camps` c ON mp.camp_id = c.id
                LEFT JOIN `users` u ON mp.reported_by_user_id = u.id
                WHERE {$whereClause}
                ORDER BY mp.created_at DESC";

        return $db->fetchAll($sql, $params);
    }

    /**
     * Fetch single missing person profile
     */
    public static function getById(int $id): ?array {
        $db = Database::getInstance();
        $sql = "SELECT mp.*, 
                       mp.report_type AS status,
                       mp.last_seen_or_found_location AS last_seen_location,
                       mp.last_seen_or_found_location AS found_location,
                       mp.notes AS description,
                       mp.camp_id AS sheltered_at_camp_id,
                       c.name AS camp_name, 
                       c.address AS camp_address,
                       c.contact_phone AS camp_phone,
                       u.full_name AS reporter_user_name
                FROM `missing_persons` mp
                LEFT JOIN `camps` c ON mp.camp_id = c.id
                LEFT JOIN `users` u ON mp.reported_by_user_id = u.id
                WHERE mp.id = :id
                LIMIT 1";

        return $db->fetchOne($sql, [':id' => $id]);
    }

    /**
     * Submit a Missing or Found person report with photo upload
     */
    public static function report(array $data, ?array $file = null, ?int $reportedBy = null): int {
        $db = Database::getInstance();

        $personName = trim($data['person_name'] ?? '');
        $reportType = in_array($data['status'] ?? '', ['Missing', 'Found'], true) ? $data['status'] : 'Missing';
        $contactPhone = trim($data['contact_phone'] ?? '');

        if (empty($personName)) {
            throw new InvalidArgumentException("Person name is required.");
        }
        if (empty($contactPhone)) {
            throw new InvalidArgumentException("Emergency contact phone number is required.");
        }

        $photoUrl = null;
        if ($file && !empty($file['name']) && $file['error'] === UPLOAD_ERR_OK) {
            $photoUrl = self::handlePhotoUpload($file);
        }

        $location = !empty($data['last_seen_location']) 
            ? trim($data['last_seen_location']) 
            : (!empty($data['found_location']) ? trim($data['found_location']) : 'Disaster Area');

        $insertData = [
            'report_type' => $reportType,
            'person_name' => $personName,
            'gender' => in_array($data['gender'] ?? '', ['Male', 'Female', 'Child', 'Other'], true) ? $data['gender'] : 'Other',
            'age' => !empty($data['age']) ? (int)$data['age'] : null,
            'last_seen_or_found_location' => $location,
            'last_seen_or_found_date' => date('Y-m-d'),
            'photo_url' => $photoUrl,
            'contact_name' => !empty($data['contact_person']) ? trim($data['contact_person']) : 'Family Member',
            'contact_phone' => $contactPhone,
            'contact_relation' => 'Family/Relative',
            'camp_id' => !empty($data['sheltered_at_camp_id']) ? (int)$data['sheltered_at_camp_id'] : null,
            'status' => 'Reported',
            'reported_by_user_id' => $reportedBy,
            'notes' => !empty($data['description']) ? trim($data['description']) : null
        ];

        return (int)$db->insert('missing_persons', $insertData);
    }

    /**
     * Update missing person status (Coordinator / Admin action)
     */
    public static function updateStatus(
        int $id, 
        string $status, 
        ?int $campId = null, 
        ?string $adminNotes = null
    ): bool {
        $db = Database::getInstance();
        $fields = [
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        if ($campId !== null) {
            $fields['camp_id'] = $campId > 0 ? $campId : null;
        }
        if ($adminNotes !== null) {
            $fields['notes'] = trim($adminNotes);
        }

        return $db->update('missing_persons', $fields, 'id = :id', [':id' => $id]);
    }

    /**
     * Get aggregate statistics for missing registry
     */
    public static function getStats(): array {
        $db = Database::getInstance();
        $sql = "SELECT 
                    COUNT(*) AS total_reports,
                    SUM(CASE WHEN report_type = 'Missing' AND status != 'Reunited' THEN 1 ELSE 0 END) AS missing_count,
                    SUM(CASE WHEN report_type = 'Found' THEN 1 ELSE 0 END) AS found_count,
                    SUM(CASE WHEN camp_id IS NOT NULL THEN 1 ELSE 0 END) AS sheltered_count,
                    SUM(CASE WHEN status = 'Reunited' THEN 1 ELSE 0 END) AS reunited_count
                FROM `missing_persons`";

        $res = $db->fetchOne($sql);
        return [
            'total' => (int)($res['total_reports'] ?? 0),
            'missing' => (int)($res['missing_count'] ?? 0),
            'found' => (int)($res['found_count'] ?? 0),
            'sheltered' => (int)($res['sheltered_count'] ?? 0),
            'reunited' => (int)($res['reunited_count'] ?? 0)
        ];
    }

    /**
     * Handle image upload safely
     */
    private static function handlePhotoUpload(array $file): string {
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowedExtensions, true)) {
            throw new RuntimeException("Invalid file type. Only JPG, PNG, and WebP photos are permitted.");
        }

        if ($file['size'] > 5 * 1024 * 1024) {
            throw new RuntimeException("Photo exceeds maximum 5MB size limit.");
        }

        $filename = 'person_' . uniqid('', true) . '.' . $ext;
        $uploadDir = dirname(__DIR__) . '/uploads/missing_persons/';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $destPath = $uploadDir . $filename;
        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            throw new RuntimeException("Failed to save uploaded photo.");
        }

        $scratchDir = 'C:/Users/Akash/.gemini/antigravity/scratch/flood-relief/uploads/missing_persons/';
        if (is_dir($scratchDir)) {
            @copy($destPath, $scratchDir . $filename);
        }

        return 'uploads/missing_persons/' . $filename;
    }
}
