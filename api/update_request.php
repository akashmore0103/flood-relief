<?php
/**
 * Coordinator / Admin Help Request Status Update API
 * Complies with PRD Endpoint: PATCH /api/help-requests/{id}
 */

require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/core/Database.php';
require_once dirname(__DIR__) . '/core/Response.php';
require_once dirname(__DIR__) . '/core/Auth.php';
require_once dirname(__DIR__) . '/core/CSRF.php';
require_once dirname(__DIR__) . '/core/Validator.php';
require_once dirname(__DIR__) . '/services/HelpRequestService.php';

// Only Coordinators and Administrators can update help requests
Auth::requireRole(['Relief Coordinator', 'Administrator']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method Not Allowed. Use POST.', 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

$validator = new Validator($input);
if (!$validator->validate([
    'request_id' => 'required|numeric',
    'status' => 'required|in:New,Acknowledged,In Progress,Resolved,Closed'
])) {
    Response::error($validator->getFirstError(), 422, $validator->getErrors());
}

try {
    $requestId = (int)$input['request_id'];
    $newStatus = $input['status'];
    $assignedTo = isset($input['assigned_to']) ? (int)$input['assigned_to'] : null;
    $notes = !empty($input['notes']) ? trim($input['notes']) : null;
    $actionBy = Auth::id();

    HelpRequestService::updateStatus($requestId, $newStatus, $assignedTo, $notes, $actionBy);

    Response::success([
        'request_id' => $requestId,
        'status' => $newStatus,
        'updated_at' => date('Y-m-d H:i:s')
    ], "Ticket status updated to '{$newStatus}' successfully.");

} catch (Throwable $e) {
    Response::error('Failed to update request: ' . $e->getMessage(), 500);
}
