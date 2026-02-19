<?php
/**
 * API: Change Agent Password
 * POST /api/agents/change-password.php
 * 
 * Agent changes their own password.
 * 
 * Body params: current_password, new_password, confirm_password
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/validation.php';
require_once __DIR__ . '/../../includes/auth.php';

if (!isAgent()) {
    jsonResponse(['success' => false, 'message' => 'Agent access required.'], 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$input = $_POST;
if (empty($input)) {
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true) ?? [];
}

// Validate required fields
$missing = validateRequired(['current_password', 'new_password', 'confirm_password'], $input);
if (!empty($missing)) {
    jsonResponse(['success' => false, 'message' => 'Missing required fields: ' . implode(', ', $missing)], 400);
}

$currentPassword = $input['current_password'];
$newPassword = $input['new_password'];
$confirmPassword = $input['confirm_password'];

// Check new password matches confirmation
if ($newPassword !== $confirmPassword) {
    jsonResponse(['success' => false, 'message' => 'New password and confirmation do not match.'], 400);
}

// Validate new password strength
if (!validatePassword($newPassword)) {
    jsonResponse(['success' => false, 'message' => 'New password must be at least 8 characters with at least 1 letter and 1 number.'], 400);
}

$pdo = getDBConnection();
$agentId = getCurrentUserId();

// Get current hash
$stmt = $pdo->prepare('SELECT password_hash FROM agents WHERE agent_id = :id');
$stmt->execute(['id' => $agentId]);
$agent = $stmt->fetch();

if (!$agent) {
    jsonResponse(['success' => false, 'message' => 'Agent not found.'], 404);
}

// Verify current password
if (!verifyPassword($currentPassword, $agent['password_hash'])) {
    jsonResponse(['success' => false, 'message' => 'Current password is incorrect.'], 400);
}

// Don't allow same password
if (verifyPassword($newPassword, $agent['password_hash'])) {
    jsonResponse(['success' => false, 'message' => 'New password must be different from current password.'], 400);
}

try {
    $stmt = $pdo->prepare('UPDATE agents SET password_hash = :hash WHERE agent_id = :id');
    $stmt->execute([
        'hash' => hashPassword($newPassword),
        'id' => $agentId,
    ]);

    jsonResponse(['success' => true, 'message' => 'Password changed successfully.']);
} catch (Exception $e) {
    error_log('Change Password Error: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Failed to change password.'], 500);
}
