<?php
/**
 * Hydrological Data Import Service (XML, JSON & CSV Support)
 * Satisfies PRD Section 5.2, Section 7, and Section 9 (Data Formats)
 */

require_once dirname(__DIR__) . '/core/Database.php';
require_once __DIR__ . '/RiskRuleEngine.php';

class DataImportService {
    /**
     * Import observations from XML content
     */
    public static function importXml(string $xmlContent, ?int $userId = null): array {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlContent);

        if ($xml === false) {
            $errors = [];
            foreach (libxml_get_errors() as $error) {
                $errors[] = trim($error->message);
            }
            libxml_clear_errors();
            throw new Exception("Malformed XML document: " . implode("; ", $errors));
        }

        $observations = [];

        // Check structure: root <hydrology_feed> or direct <observations>
        $items = $xml->observations->observation ?? $xml->observation ?? [];

        foreach ($items as $obs) {
            $observations[] = [
                'zone_id' => (int)($obs->zone_id ?? 0),
                'rainfall_value' => (float)($obs->rainfall_mm ?? $obs->rainfall ?? 0.0),
                'river_level_value' => (float)($obs->river_level_m ?? $obs->river_level ?? 0.0),
                'observed_at' => !empty($obs->observed_at) ? (string)$obs->observed_at : date('Y-m-d H:i:s'),
                'source' => 'XML Import (' . ($obs->station_id ?? 'Feed') . ')'
            ];
        }

        return self::processObservations($observations, 'XML_IMPORT', $userId);
    }

    /**
     * Import observations from JSON content
     */
    public static function importJson(string $jsonContent, ?int $userId = null): array {
        $data = json_decode($jsonContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON format: " . json_last_error_msg());
        }

        $items = $data['observations'] ?? (is_array($data) ? $data : []);
        $observations = [];

        foreach ($items as $obs) {
            $observations[] = [
                'zone_id' => (int)($obs['zone_id'] ?? 0),
                'rainfall_value' => (float)($obs['rainfall_mm'] ?? $obs['rainfall_value'] ?? 0.0),
                'river_level_value' => (float)($obs['river_level_m'] ?? $obs['river_level_value'] ?? 0.0),
                'observed_at' => !empty($obs['observed_at']) ? $obs['observed_at'] : date('Y-m-d H:i:s'),
                'source' => 'JSON Import'
            ];
        }

        return self::processObservations($observations, 'JSON_IMPORT', $userId);
    }

    /**
     * Import observations from CSV content
     */
    public static function importCsv(string $csvContent, ?int $userId = null): array {
        $lines = explode("\n", trim($csvContent));
        if (empty($lines)) {
            throw new Exception("CSV file is empty.");
        }

        $header = str_getcsv(array_shift($lines));
        $header = array_map('trim', array_map('strtolower', $header));

        $observations = [];
        foreach ($lines as $line) {
            if (empty(trim($line))) continue;
            $row = str_getcsv($line);
            if (count($row) < count($header)) continue;
            $record = array_combine($header, $row);

            $observations[] = [
                'zone_id' => (int)($record['zone_id'] ?? 0),
                'rainfall_value' => (float)($record['rainfall_mm'] ?? $record['rainfall'] ?? 0.0),
                'river_level_value' => (float)($record['river_level_m'] ?? $record['river_level'] ?? 0.0),
                'observed_at' => !empty($record['observed_at']) ? trim($record['observed_at']) : date('Y-m-d H:i:s'),
                'source' => 'CSV Import'
            ];
        }

        return self::processObservations($observations, 'CSV_IMPORT', $userId);
    }

    /**
     * Persist parsed observations into database and recalculate risk
     */
    private static function processObservations(array $observations, string $actionType, ?int $userId): array {
        $db = Database::getInstance();
        $importedCount = 0;
        $errors = [];

        // Preload valid zone IDs
        $validZones = $db->fetchAll("SELECT `id`, `name` FROM `zones`");
        $validZoneIds = array_column($validZones, 'id');

        $db->beginTransaction();

        try {
            foreach ($observations as $idx => $obs) {
                $zoneId = $obs['zone_id'];

                if (!in_array($zoneId, $validZoneIds, true)) {
                    $errors[] = "Observation #{$idx}: Zone ID '{$zoneId}' does not exist in database.";
                    continue;
                }

                // Calculate risk score and category via the Rule Engine
                $calc = RiskRuleEngine::calculate($obs['rainfall_value'], $obs['river_level_value']);

                // Insert into risk_levels table
                $db->insert('risk_levels', [
                    'zone_id' => $zoneId,
                    'rainfall_value' => $obs['rainfall_value'],
                    'river_level_value' => $obs['river_level_value'],
                    'score' => $calc['score'],
                    'category' => $calc['category'],
                    'source' => $obs['source'],
                    'observed_at' => date('Y-m-d H:i:s', strtotime($obs['observed_at']))
                ]);

                $importedCount++;
            }

            // Log administrative audit entry
            $db->insert('audit_logs', [
                'user_id' => $userId,
                'action_type' => $actionType,
                'table_affected' => 'risk_levels',
                'record_id' => null,
                'details' => "Batch imported {$importedCount} observations. Errors: " . count($errors),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
            ]);

            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        return [
            'total_received' => count($observations),
            'imported_count' => $importedCount,
            'errors' => $errors
        ];
    }
}
