<?php
/**
 * Emergency Help Requests REST API Endpoint
 * Complies with PRD Endpoint: POST /api/help-requests and Public Tracking
 */

require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/core/Database.php';
require_once dirname(__DIR__) . '/core/Response.php';
require_once dirname(__DIR__) . '/core/Auth.php';
require_once dirname(__DIR__) . '/core/CSRF.php';
require_once dirname(__DIR__) . '/core/Validator.php';
require_once dirname(__DIR__) . '/services/HelpRequestService.php';

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'POST':
        // Public submission of emergency help request
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        $validator = new Validator($input);
        if (!$validator->validate([
            'guest_name' => 'required|min:2|max:100',
            'guest_phone' => 'required|min:10|max:15',
            'request_type' => 'required|in:Evacuation,Medical Support,Food & Water,Shelter,Missing Person,Other',
            'urgency' => 'required|in:Low,Medium,High,Critical',
            'description' => 'required|min:5',
            'address' => 'required|min:5',
            'latitude' => 'required|coordinate_lat',
            'longitude' => 'required|coordinate_lng'
        ])) {
            Response::error($validator->getFirstError(), 422, $validator->getErrors());
        }

        try {
            $citizenId = Auth::check() ? Auth::id() : null;
            $result = HelpRequestService::createRequest($input, $citizenId);

            Response::success([
                'reference_code' => $result['reference_code'],
                'status' => $result['status'],
                'tracking_url' => BASE_URL . "/views/citizen/track_request.php?ref=" . urlencode($result['reference_code'])
            ], 'Emergency assistance request submitted successfully. Please save your reference code.', 201);

        } catch (Throwable $e) {
            Response::error('Failed to submit emergency request: ' . $e->getMessage(), 500);
        }
        break;

    case 'GET':
        // If reference code is provided, return public tracking detail
        if (!empty($_GET['ref'])) {
            $ref = trim($_GET['ref']);
            $ticket = HelpRequestService::getByReference($ref);

            if (!$ticket) {
                Response::error('No emergency ticket found matching reference code: ' . $ref, 404);
            }

            Response::success($ticket, 'Ticket status retrieved.');
        }

        // If logged in citizen requesting their own tickets
        if (!empty($_GET['mine']) && Auth::check()) {
            $tickets = HelpRequestService::getUserRequests(Auth::id());
            Response::success($tickets, 'Citizen tickets retrieved.');
        }

        // If requesting queue (Coordinator or Admin access required)
        Auth::requireRole(['Relief Coordinator', 'Administrator']);

        $db = Database::getInstance();
        $status = $_GET['status'] ?? '';
        $urgency = $_GET['urgency'] ?? '';

        $sql = "SELECT hr.*, 
                       u.full_name AS assigned_coordinator_name 
                FROM `help_requests` hr
                LEFT JOIN `users` u ON hr.assigned_to = u.id
                WHERE 1=1";
        $params = [];

        if (!empty($status) && $status !== 'All') {
            $sql .= " AND hr.status = :status";
            $params[':status'] = $status;
        }

        if (!empty($urgency) && $urgency !== 'All') {
            $sql .= " AND hr.urgency = :urgency";
            $params[':urgency'] = $urgency;
        }

        // Critical urgency first, then newest
        $sql .= " ORDER BY CASE hr.urgency WHEN 'Critical' THEN 1 WHEN 'High' THEN 2 WHEN 'Medium' THEN 3 WHEN 'Low' THEN 4 ELSE 5 END, hr.created_at DESC";

        $requests = $db->fetchAll($sql, $params);
        Response::success($requests, 'Help requests retrieved.');
        break;

    default:
        Response::error('Method Not Allowed', 405);
        break;
}
