<?php
/**
 * Risk Rules & Formula Configuration API
 * Complies with PRD FR-05 (Configurable rule thresholds and weights)
 */

require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/core/Database.php';
require_once dirname(__DIR__) . '/core/Response.php';
require_once dirname(__DIR__) . '/core/Auth.php';
require_once dirname(__DIR__) . '/core/CSRF.php';
require_once dirname(__DIR__) . '/core/Validator.php';

Auth::requireRole(['Administrator']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method Not Allowed. Use POST.', 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

$validator = new Validator($input);
if (!$validator->validate([
    'rule_name' => 'required|min:3|max:100',
    'rainfall_weight' => 'required|numeric',
    'river_weight' => 'required|numeric',
    'max_rainfall_baseline' => 'required|numeric',
    'max_river_baseline' => 'required|numeric',
    'low_max_score' => 'required|numeric',
    'mod_max_score' => 'required|numeric',
    'high_max_score' => 'required|numeric'
])) {
    Response::error($validator->getFirstError(), 422, $validator->getErrors());
}

// Ensure weights roughly sum to 1.0
$wRain = (float)$input['rainfall_weight'];
$wRiver = (float)$input['river_weight'];
if (abs(($wRain + $wRiver) - 1.0) > 0.05) {
    Response::error('Formula weights must sum to 1.0 (e.g. Rainfall: 0.50, River: 0.50).', 422);
}

$db = Database::getInstance();
$adminId = Auth::id();

$ruleData = [
    'rule_name' => trim($input['rule_name']),
    'rainfall_weight' => $wRain,
    'river_weight' => $wRiver,
    'max_rainfall_baseline' => (float)$input['max_rainfall_baseline'],
    'max_river_baseline' => (float)$input['max_river_baseline'],
    'low_max_score' => (float)$input['low_max_score'],
    'mod_max_score' => (float)$input['mod_max_score'],
    'high_max_score' => (float)$input['high_max_score'],
    'is_active' => true,
    'updated_by' => $adminId
];

$ruleId = !empty($input['id']) ? (int)$input['id'] : null;

if ($ruleId) {
    $db->update('risk_rules', $ruleData, 'id = :id', [':id' => $ruleId]);
} else {
    // Deactivate previous active rules
    $db->query("UPDATE `risk_rules` SET `is_active` = FALSE");
    $ruleId = $db->insert('risk_rules', $ruleData);
}

// Log audit trail
$db->insert('audit_logs', [
    'user_id' => $adminId,
    'action_type' => 'RISK_RULES_UPDATE',
    'table_affected' => 'risk_rules',
    'record_id' => $ruleId,
    'details' => "Updated risk formula: Rain Weight={$wRain}, River Weight={$wRiver}. Low<={$ruleData['low_max_score']}, Mod<={$ruleData['mod_max_score']}, High<={$ruleData['high_max_score']}",
    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
]);

Response::success(['id' => $ruleId], 'Risk scoring formula and thresholds updated successfully.');
