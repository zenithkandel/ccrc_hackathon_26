<?php
/**
 * API: Delete Alert
 * POST /api/alerts/delete.php
 * 
 * Admin deletes an alert.
 * 
 * Body params: alert_id
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../includes/functions.php';

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

$alertId = (int) ($input['alert_id'] ?? $_GET['id'] ?? 0);
if ($alertId <= 0) {
    jsonResponse(['success' => false, 'message' => 'Valid alert_id is required.'], 400);
}

$pdo = getDBConnection();

$stmt = $pdo->prepare('SELECT alert_id FROM alerts WHERE alert_id = :id');
$stmt->execute(['id' => $alertId]);
if (!$stmt->fetch()) {
    jsonResponse(['success' => false, 'message' => 'Alert not found.'], 404);
}

try {
    $stmt = $pdo->prepare('DELETE FROM alerts WHERE alert_id = :id');
    $stmt->execute(['id' => $alertId]);

    jsonResponse(['success' => true, 'message' => 'Alert deleted successfully.']);

} catch (Exception $e) {
    error_log('Delete Alert Error: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Failed to delete alert.'], 500);
}
