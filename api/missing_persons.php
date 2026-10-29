<?php
/**
 * Missing & Found Persons Registry REST API
 * Flood Risk Prediction & Relief Camp Locator
 */

require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/core/Database.php';
require_once dirname(__DIR__) . '/core/Response.php';
require_once dirname(__DIR__) . '/core/Auth.php';
require_once dirname(__DIR__) . '/core/Validator.php';
require_once dirname(__DIR__) . '/services/MissingPersonService.php';

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $action = $_GET['action'] ?? '';
        $id = !empty($_GET['id']) ? (int)$_GET['id'] : null;

        try {
            if ($action === 'stats') {
                $stats = MissingPersonService::getStats();
                Response::success($stats, 'Registry statistics retrieved.');
            } elseif ($id) {
                $person = MissingPersonService::getById($id);
                if (!$person) {
                    Response::error('Record not found.', 404);
                }
                Response::success($person, 'Person details retrieved.');
            } else {
                $filters = [
                    'query' => $_GET['query'] ?? '',
                    'status' => $_GET['status'] ?? '',
                    'gender' => $_GET['gender'] ?? '',
                    'camp_id' => !empty($_GET['camp_id']) ? (int)$_GET['camp_id'] : null
                ];
                $records = MissingPersonService::search($filters);
                Response::success($records, 'Registry records retrieved.');
            }
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 500);
        }
        break;

    case 'POST':
        // Publicly accessible for reporting missing or found persons
        $input = $_POST;
        $validator = new Validator($input);

        if (!$validator->validate([
            'person_name' => 'required|min:2|max:100',
            'contact_phone' => 'required|min:10|max:15'
        ])) {
            Response::error($validator->getFirstError(), 422, $validator->getErrors());
        }

        try {
            $reportedBy = Auth::check() ? Auth::id() : null;
            $file = $_FILES['photo'] ?? null;
            $id = MissingPersonService::report($input, $file, $reportedBy);

            Response::success([
                'id' => $id,
                'person_name' => $input['person_name'],
                'status' => $input['status'] ?? 'Missing'
            ], 'Report successfully published to Missing & Found Registry.', 201);
        } catch (Throwable $e) {
            Response::error('Failed to submit report: ' . $e->getMessage(), 500);
        }
        break;

    case 'PATCH':
    case 'PUT':
        // Coordinator / Admin only
        Auth::requireRole(['Relief Coordinator', 'Administrator']);
        $raw = file_get_contents('php://input');
        $input = json_decode($raw, true) ?? [];

        if (empty($input['id']) || empty($input['status'])) {
            Response::error('ID and status are required.', 422);
        }

        try {
            $id = (int)$input['id'];
            $status = $input['status'];
            $campId = !empty($input['sheltered_at_camp_id']) ? (int)$input['sheltered_at_camp_id'] : null;
            $notes = $input['admin_notes'] ?? null;

            MissingPersonService::updateStatus($id, $status, $campId, $notes);
            Response::success(['id' => $id, 'status' => $status], 'Record status updated.');
        } catch (Throwable $e) {
            Response::error('Failed to update status: ' . $e->getMessage(), 500);
        }
        break;

    default:
        Response::error('Method not allowed.', 405);
}
