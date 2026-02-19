<?php
/**
 * API: Delete Vehicle
 * POST /api/vehicles/delete.php
 * 
 * Deletes a vehicle entry. Requires Admin authentication.
 * 
 * Body params: vehicle_id
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

$vehicleId = (int) ($input['vehicle_id'] ?? $_GET['id'] ?? 0);
if ($vehicleId <= 0) {
    jsonResponse(['success' => false, 'message' => 'Valid vehicle_id is required.'], 400);
}

$pdo = getDBConnection();

$stmt = $pdo->prepare('SELECT vehicle_id, image_path FROM vehicles WHERE vehicle_id = :id');
$stmt->execute(['id' => $vehicleId]);
$vehicle = $stmt->fetch();

if (!$vehicle) {
    jsonResponse(['success' => false, 'message' => 'Vehicle not found.'], 404);
}

try {
    deleteImage($vehicle['image_path']);

    $stmt = $pdo->prepare('DELETE FROM vehicles WHERE vehicle_id = :id');
    $stmt->execute(['id' => $vehicleId]);

    jsonResponse(['success' => true, 'message' => 'Vehicle deleted successfully.']);

} catch (Exception $e) {
    error_log('Delete Vehicle Error: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Failed to delete vehicle.'], 500);
}
