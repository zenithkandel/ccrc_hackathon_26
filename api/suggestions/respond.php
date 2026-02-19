<?php
/**
 * API: Respond to Suggestion
 * POST /api/suggestions/respond.php
 * 
 * Admin updates suggestion status.
 * 
 * Body params: suggestion_id, status (reviewed|resolved), admin_response (optional text)
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/validation.php';

if (!isAdmin()) {
    jsonResponse(['success' => false, 'message' => 'Admin access required.'], 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$input = $_POST;
if (empty($input)) {
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true) ?? [];
}

$suggestionId = (int) ($input['suggestion_id'] ?? $_GET['id'] ?? 0);
$status = $input['status'] ?? '';

if ($suggestionId <= 0) {
    jsonResponse(['success' => false, 'message' => 'Valid suggestion_id is required.'], 400);
}
if (!validateEnum($status, ['reviewed', 'resolved'])) {
    jsonResponse(['success' => false, 'message' => 'Status must be "reviewed" or "resolved".'], 400);
}

$pdo = getDBConnection();

$stmt = $pdo->prepare('SELECT * FROM suggestions WHERE suggestion_id = :id');
$stmt->execute(['id' => $suggestionId]);
$suggestion = $stmt->fetch();

if (!$suggestion) {
    jsonResponse(['success' => false, 'message' => 'Suggestion not found.'], 404);
}

try {
    $stmt = $pdo->prepare('
        UPDATE suggestions SET 
            status = :status, reviewed_by = :admin_id, reviewed_at = NOW()
        WHERE suggestion_id = :id
    ');
    $stmt->execute([
        'status' => $status,
        'admin_id' => getCurrentUserId(),
        'id' => $suggestionId,
    ]);

    jsonResponse([
        'success' => true,
        'message' => 'Suggestion marked as ' . $status . '.',
        'suggestion_id' => $suggestionId,
    ]);

} catch (Exception $e) {
    error_log('Respond Suggestion Error: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Failed to update suggestion.'], 500);
}
