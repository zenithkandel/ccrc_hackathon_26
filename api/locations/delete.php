<?php
/**
 * API: Delete Location
 * POST /api/locations/delete.php
 * 
 * Deletes a location entry.
 * Requires: Admin authentication.
 * 
 * Body params: location_id
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

$locationId = (int) ($input['location_id'] ?? $_GET['id'] ?? 0);
if ($locationId <= 0) {
    jsonResponse(['success' => false, 'message' => 'Valid location_id is required.'], 400);
}

$pdo = getDBConnection();

// Check if location exists
$stmt = $pdo->prepare('SELECT location_id FROM locations WHERE location_id = :id');
$stmt->execute(['id' => $locationId]);
if (!$stmt->fetch()) {
    jsonResponse(['success' => false, 'message' => 'Location not found.'], 404);
}

try {
    // Check if location is used in any routes
    $stmt = $pdo->prepare("
        SELECT route_id, name, location_list FROM routes WHERE status = 'approved'
    ");
    $stmt->execute();
    $routes = $stmt->fetchAll();

    $usedInRoutes = [];
    foreach ($routes as $route) {
        $locationList = json_decode($route['location_list'], true) ?? [];
        foreach ($locationList as $loc) {
            if (($loc['location_id'] ?? 0) == $locationId) {
                $usedInRoutes[] = $route['name'];
                break;
            }
        }
    }

    if (!empty($usedInRoutes)) {
        jsonResponse([
            'success' => false,
            'message' => 'Cannot delete. This location is used in routes: ' . implode(', ', $usedInRoutes),
        ], 409);
    }

    $stmt = $pdo->prepare('DELETE FROM locations WHERE location_id = :id');
    $stmt->execute(['id' => $locationId]);

    jsonResponse(['success' => true, 'message' => 'Location deleted successfully.']);

} catch (Exception $e) {
    error_log('Delete Location Error: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Failed to delete location.'], 500);
}
