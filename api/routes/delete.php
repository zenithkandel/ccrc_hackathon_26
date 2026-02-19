<?php
/**
 * API: Delete Route
 * POST /api/routes/delete.php
 * 
 * Deletes a route entry. Requires Admin authentication.
 * 
 * Body params: route_id
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

$routeId = (int) ($input['route_id'] ?? $_GET['id'] ?? 0);
if ($routeId <= 0) {
    jsonResponse(['success' => false, 'message' => 'Valid route_id is required.'], 400);
}

$pdo = getDBConnection();

$stmt = $pdo->prepare('SELECT route_id, image_path FROM routes WHERE route_id = :id');
$stmt->execute(['id' => $routeId]);
$route = $stmt->fetch();

if (!$route) {
    jsonResponse(['success' => false, 'message' => 'Route not found.'], 404);
}

try {
    // Check if any vehicles reference this route
    $stmt = $pdo->prepare("SELECT vehicle_id, name, used_routes FROM vehicles WHERE status = 'approved'");
    $stmt->execute();
    $vehicles = $stmt->fetchAll();

    $usedByVehicles = [];
    foreach ($vehicles as $v) {
        $usedRoutes = json_decode($v['used_routes'], true) ?? [];
        foreach ($usedRoutes as $ur) {
            if (($ur['route_id'] ?? 0) == $routeId) {
                $usedByVehicles[] = $v['name'];
                break;
            }
        }
    }

    if (!empty($usedByVehicles)) {
        jsonResponse([
            'success' => false,
            'message' => 'Cannot delete. Route is used by vehicles: ' . implode(', ', $usedByVehicles),
        ], 409);
    }

    // Delete route image
    deleteImage($route['image_path']);

    $stmt = $pdo->prepare('DELETE FROM routes WHERE route_id = :id');
    $stmt->execute(['id' => $routeId]);

    jsonResponse(['success' => true, 'message' => 'Route deleted successfully.']);

} catch (Exception $e) {
    error_log('Delete Route Error: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Failed to delete route.'], 500);
}
