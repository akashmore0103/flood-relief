<?php
/**
 * Relief Camp Capacity & Status Update API
 * Complies with PRD Coordinator Requirements & FR-09
 */

require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/core/Database.php';
require_once dirname(__DIR__) . '/core/Response.php';
require_once dirname(__DIR__) . '/core/Auth.php';
require_once dirname(__DIR__) . '/core/CSRF.php';
require_once dirname(__DIR__) . '/core/Validator.php';
require_once dirname(__DIR__) . '/services/CampLocatorService.php';

Auth::requireRole(['Relief Coordinator', 'Administrator']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method Not Allowed. Use POST.', 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

$validator = new Validator($input);
if (!$validator->validate([
    'camp_id' => 'required|numeric',
    'current_occupancy' => 'required|numeric',
    'status' => 'required|in:Operational,Full,Standby,Closed'
])) {
    Response::error($validator->getFirstError(), 422, $validator->getErrors());
}

try {
    $campId = (int)$input['camp_id'];
    $newOccupancy = max(0, (int)$input['current_occupancy']);
    $newStatus = $input['status'];
    $remarks = !empty($input['remarks']) ? trim($input['remarks']) : 'Field status updated by coordinator';
    $updatedBy = Auth::id();

    CampLocatorService::updateOccupancy($campId, $newOccupancy, $newStatus, $updatedBy, $remarks);

    Response::success([
        'camp_id' => $campId,
        'current_occupancy' => $newOccupancy,
        'status' => $newStatus
    ], 'Relief camp capacity updated successfully.');

} catch (Throwable $e) {
    Response::error('Failed to update camp status: ' . $e->getMessage(), 500);
}
