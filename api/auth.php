<?php
/**
 * Authentication REST API Endpoint
 * Flood Risk Prediction & Relief Camp Locator
 */

if (!ob_get_level()) {
    ob_start();
}

require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/core/Database.php';
require_once dirname(__DIR__) . '/core/Response.php';
require_once dirname(__DIR__) . '/core/Auth.php';
require_once dirname(__DIR__) . '/core/CSRF.php';
require_once dirname(__DIR__) . '/core/Validator.php';

$action = $_GET['action'] ?? 'me';
$method = $_SERVER['REQUEST_METHOD'];

switch ($action) {
    case 'login':
        if ($method !== 'POST') {
            Response::error('Method Not Allowed', 405);
        }

        // Parse JSON or form POST data
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        $validator = new Validator($input);
        if (!$validator->validate([
            'email' => 'required|email',
            'password' => 'required'
        ])) {
            Response::error($validator->getFirstError(), 422, $validator->getErrors());
        }

        $email = $input['email'];
        $password = $input['password'];

        if (Auth::login($email, $password)) {
            $user = Auth::user();
            $redirect = match ($user['role_name']) {
                'Administrator'     => 'views/admin/dashboard.php',
                'Relief Coordinator'=> 'views/coordinator/dashboard.php',
                default             => 'views/citizen/dashboard.php'
            };

            Response::success([
                'user' => $user,
                'redirect' => BASE_URL . '/' . $redirect
            ], 'Authentication successful.');
        } else {
            Response::error('Invalid email address or password.', 401);
        }
        break;

    case 'register':
        if ($method !== 'POST') {
            Response::error('Method Not Allowed', 405);
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        $validator = new Validator($input);
        if (!$validator->validate([
            'full_name' => 'required|min:2|max:100',
            'email' => 'required|email',
            'password' => 'required|min:6',
            'phone' => 'required|min:10|max:15'
        ])) {
            Response::error($validator->getFirstError(), 422, $validator->getErrors());
        }

        $db = Database::getInstance();
        $email = trim($input['email']);
        
        // Strict standard email regex validation
        if (!preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $email)) {
            Response::error('Please enter a valid email address with a valid domain (e.g. name@domain.com).', 422);
        }

        $existing = $db->fetchOne("SELECT id FROM users WHERE email = :email LIMIT 1", [':email' => $email]);
        if ($existing) {
            Response::error('This email address is already registered. Please login.', 409);
        }

        $passwordHash = password_hash($input['password'], PASSWORD_BCRYPT);
        $roleId = 3; // Citizen role

        $damId = !empty($input['monitored_dam_id']) ? (int)$input['monitored_dam_id'] : (!empty($input['dam_id']) ? (int)$input['dam_id'] : null);
        $riverBasinId = !empty($input['monitored_river_id']) ? (int)$input['monitored_river_id'] : (!empty($input['river_basin_id']) ? (int)$input['river_basin_id'] : null);
        $district = !empty($input['district']) ? trim($input['district']) : null;
        $address = !empty($input['address']) ? trim($input['address']) : (!empty($input['residential_address']) ? trim($input['residential_address']) : null);
        $village = !empty($input['village_city']) ? trim($input['village_city']) : (!empty($input['locality']) ? trim($input['locality']) : $district);

        $db->beginTransaction();
        try {
            $userId = $db->insert('users', [
                'full_name' => trim($input['full_name']),
                'email' => $email,
                'password_hash' => $passwordHash,
                'role_id' => $roleId,
                'phone' => trim($input['phone']),
                'district' => $district,
                'river_basin_id' => $riverBasinId,
                'monitored_dam_id' => $damId,
                'address' => $address,
                'status' => 'active'
            ]);

            // Create initial citizen profile bookmarks
            $db->insert('citizen_profiles', [
                'user_id' => $userId,
                'saved_dam_id' => $damId ?: 1,
                'saved_river_id' => $riverBasinId ?: 1,
                'saved_village_city' => $village ?: ($district ?: 'Maharashtra Flood Zone'),
                'alert_notifications_enabled' => true
            ]);

            $db->commit();

            // Auto-login
            Auth::login($email, $input['password']);

            Response::success([
                'user_id' => $userId,
                'redirect' => BASE_URL . '/views/citizen/dashboard.php'
            ], 'Citizen account created successfully. Welcome to Flood Watch!', 201);
        } catch (Throwable $e) {
            $db->rollBack();
            Response::error('Failed to register account: ' . $e->getMessage(), 500);
        }
        break;

    case 'logout':
        Auth::logout();

        // Check if request is an AJAX/Fetch call
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || (str_contains($accept, 'application/json') && !str_contains($accept, 'text/html'))
            || (isset($_SERVER['CONTENT_TYPE']) && str_contains($_SERVER['CONTENT_TYPE'], 'application/json'));

        if ($isAjax) {
            Response::success(null, 'Successfully logged out.');
        } else {
            // Standard browser navigation: Start clean session for flash message and redirect
            if (session_status() === PHP_SESSION_NONE) {
                @session_start();
            }
            Helper::setFlash('info', 'You have been successfully logged out.');

            $redirectTarget = !empty($_REQUEST['redirect']) ? $_REQUEST['redirect'] : BASE_URL . '/views/auth/login.php';
            if (str_starts_with($redirectTarget, '/views/') || str_starts_with($redirectTarget, '/api/')) {
                $redirectTarget = BASE_URL . $redirectTarget;
            }

            header("Location: " . $redirectTarget);
            exit;
        }
        break;

    case 'me':
        if (Auth::check()) {
            Response::success(Auth::user(), 'User session active.');
        } else {
            Response::json(false, 'No active session.', null, 200);
        }
        break;

    default:
        Response::error('Invalid auth action requested.', 400);
        break;
}