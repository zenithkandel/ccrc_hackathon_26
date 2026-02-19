<?php
/**
 * API: Create Alert
 * POST /api/alerts/create.php
 * 
 * Admin creates a service alert (e.g. road closures, schedule changes).
 * 
 * Body params: name, description, routes_affected (JSON array of route_ids),
 *              expires_at (datetime, optional)
 * routes_affected format: [3, 5, 9]
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

$missing = validateRequired(['name'], $input);
if (!empty($missing)) {
    jsonResponse(['success' => false, 'message' => 'Alert name is required.'], 400);
}

// Parse routes_affected
$routesAffected = null;
$pdo = getDBConnection();

if (isset($input['routes_affected'])) {
    $ra = $input['routes_affected'];
    if (is_string($ra)) {
        $ra = json_decode($ra, true);
    }
    if (is_array($ra) && !empty($ra)) {
        $placeholders = implode(',', array_fill(0, count($ra), '?'));
        $stmt = $pdo->prepare("SELECT route_id FROM routes WHERE route_id IN ($placeholders)");
        $stmt->execute(array_map('intval', $ra));
        $validIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $invalidIds = array_diff(array_map('intval', $ra), $validIds);
        if (!empty($invalidIds)) {
            jsonResponse(['success' => false, 'message' => 'Invalid route IDs: ' . implode(', ', $invalidIds)], 400);
        }
        $routesAffected = json_encode(array_map('intval', $ra));
    }
}

try {
    $stmt = $pdo->prepare('
        INSERT INTO alerts (name, description, issued_by, routes_affected, reported_at, expires_at)
        VALUES (:name, :description, :issued_by, :routes_affected, NOW(), :expires_at)
    ');
    $stmt->execute([
        'name' => trim($input['name']),
        'description' => trim($input['description'] ?? ''),
        'issued_by' => getCurrentUserId(),
        'routes_affected' => $routesAffected,
        'expires_at' => $input['expires_at'] ?? null,
    ]);

    $alertId = (int) $pdo->lastInsertId();

    jsonResponse([
        'success' => true,
        'message' => 'Alert created successfully.',
        'alert_id' => $alertId,
    ], 201);

} catch (Exception $e) {
    error_log('Create Alert Error: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Failed to create alert.'], 500);
}
