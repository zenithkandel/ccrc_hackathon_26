<?php
/**
 * API: Update Alert
 * POST /api/alerts/update.php
 * 
 * Admin updates an existing alert.
 * 
 * Body params: alert_id, name, description, routes_affected (JSON), expires_at
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

$alertId = (int) ($input['alert_id'] ?? $_GET['id'] ?? 0);
if ($alertId <= 0) {
    jsonResponse(['success' => false, 'message' => 'Valid alert_id is required.'], 400);
}

$pdo = getDBConnection();

$stmt = $pdo->prepare('SELECT * FROM alerts WHERE alert_id = :id');
$stmt->execute(['id' => $alertId]);
$alert = $stmt->fetch();

if (!$alert) {
    jsonResponse(['success' => false, 'message' => 'Alert not found.'], 404);
}

// Parse routes_affected if provided
$routesAffected = $alert['routes_affected'];
if (isset($input['routes_affected'])) {
    $ra = $input['routes_affected'];
    if (is_string($ra)) {
        $ra = json_decode($ra, true);
    }
    if (is_array($ra)) {
        if (!empty($ra)) {
            $placeholders = implode(',', array_fill(0, count($ra), '?'));
            $check = $pdo->prepare("SELECT route_id FROM routes WHERE route_id IN ($placeholders)");
            $check->execute(array_map('intval', $ra));
            $validIds = $check->fetchAll(PDO::FETCH_COLUMN);
            $invalidIds = array_diff(array_map('intval', $ra), $validIds);
            if (!empty($invalidIds)) {
                jsonResponse(['success' => false, 'message' => 'Invalid route IDs: ' . implode(', ', $invalidIds)], 400);
            }
        }
        $routesAffected = json_encode(array_map('intval', $ra));
    }
}

try {
    $stmt = $pdo->prepare('
        UPDATE alerts SET
            name = :name, description = :description,
            routes_affected = :routes_affected, expires_at = :expires_at
        WHERE alert_id = :id
    ');
    $stmt->execute([
        'name' => trim($input['name'] ?? $alert['name']),
        'description' => trim($input['description'] ?? $alert['description']),
        'routes_affected' => $routesAffected,
        'expires_at' => $input['expires_at'] ?? $alert['expires_at'],
        'id' => $alertId,
    ]);

    jsonResponse(['success' => true, 'message' => 'Alert updated successfully.', 'alert_id' => $alertId]);

} catch (Exception $e) {
    error_log('Update Alert Error: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Failed to update alert.'], 500);
}
