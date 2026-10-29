<?php
/**
 * Hydrological Observation Data Import API Endpoint
 * Complies with PRD Endpoint: POST /api/risk-data/import
 * Supports XML, JSON, and CSV data formats
 */

require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/core/Database.php';
require_once dirname(__DIR__) . '/core/Response.php';
require_once dirname(__DIR__) . '/core/Auth.php';
require_once dirname(__DIR__) . '/core/CSRF.php';
require_once dirname(__DIR__) . '/services/DataImportService.php';

// Only administrators are authorized to import hydrological feeds
Auth::requireRole(['Administrator']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method Not Allowed. Use POST.', 405);
}

try {
    $userId = Auth::id();
    $format = strtolower($_POST['format'] ?? '');
    $content = '';

    // Check if uploaded as a file
    if (!empty($_FILES['import_file']['tmp_name'])) {
        $file = $_FILES['import_file'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("File upload failed with error code: " . $file['error']);
        }

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (empty($format)) {
            $format = $extension;
        }

        $content = file_get_contents($file['tmp_name']);
    } else {
        // Otherwise read raw POST body or form text
        $content = $_POST['raw_data'] ?? file_get_contents('php://input');
        if (empty($format)) {
            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
            if (str_contains($contentType, 'xml')) {
                $format = 'xml';
            } elseif (str_contains($contentType, 'json')) {
                $format = 'json';
            } else {
                // Auto-detect from first character
                $trimmed = ltrim($content);
                if (str_starts_with($trimmed, '<')) {
                    $format = 'xml';
                } elseif (str_starts_with($trimmed, '{') || str_starts_with($trimmed, '[')) {
                    $format = 'json';
                } else {
                    $format = 'csv';
                }
            }
        }
    }

    if (empty(trim($content))) {
        Response::error('No observation data provided to import.', 400);
    }

    // Process import according to format
    $result = match ($format) {
        'xml' => DataImportService::importXml($content, $userId),
        'json' => DataImportService::importJson($content, $userId),
        'csv' => DataImportService::importCsv($content, $userId),
        default => throw new Exception("Unsupported format: '{$format}'. Allowed formats are XML, JSON, CSV.")
    };

    Response::success($result, "Successfully imported {$result['imported_count']} hydrological observation(s).");

} catch (Throwable $e) {
    Response::error($e->getMessage(), 400);
}
