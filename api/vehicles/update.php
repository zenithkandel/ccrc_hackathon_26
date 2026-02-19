<?php
/**
 * API: Update Vehicle
 * POST /api/vehicles/update.php
 * 
 * Updates an existing vehicle entry.
 * Requires: Agent (own pending) or Admin.
 * 
 * Body params: vehicle_id, name, description, used_routes (JSON), starts_at, stops_at, image (file, optional)
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/validation.php';

if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => 'Authentication required.'], 401);
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

$stmt = $pdo->prepare('SELECT * FROM vehicles WHERE vehicle_id = :id');
$stmt->execute(['id' => $vehicleId]);
$vehicle = $stmt->fetch();

if (!$vehicle) {
    jsonResponse(['success' => false, 'message' => 'Vehicle not found.'], 404);
}

if (isAgent() && ($vehicle['updated_by'] != getCurrentUserId() || $vehicle['status'] !== 'pending')) {
    jsonResponse(['success' => false, 'message' => 'You can only edit your own pending vehicles.'], 403);
}

// Validate used_routes if provided
$usedRoutesJson = $vehicle['used_routes'];
if (isset($input['used_routes'])) {
    $newRoutes = $input['used_routes'];
    if (is_string($newRoutes)) {
        $newRoutes = json_decode($newRoutes, true);
    }
    if (!is_array($newRoutes) || count($newRoutes) < 1) {
        jsonResponse(['success' => false, 'message' => 'used_routes must list at least one route.'], 400);
    }

    $routeIds = array_column($newRoutes, 'route_id');
    $placeholders = implode(',', array_fill(0, count($routeIds), '?'));
    $check = $pdo->prepare("SELECT route_id FROM routes WHERE route_id IN ($placeholders) AND status = 'approved'");
    $check->execute($routeIds);
    $validIds = $check->fetchAll(PDO::FETCH_COLUMN);
    $invalidIds = array_diff($routeIds, $validIds);
    if (!empty($invalidIds)) {
        jsonResponse(['success' => false, 'message' => 'Invalid route IDs: ' . implode(', ', $invalidIds)], 400);
    }

    $normalizedRoutes = [];
    foreach ($newRoutes as $ur) {
        $normalizedRoutes[] = ['route_id' => (int) $ur['route_id'], 'count' => (int) ($ur['count'] ?? 1)];
    }
    $usedRoutesJson = json_encode($normalizedRoutes);
}

// Validate times
if (isset($input['starts_at']) && !validateTime($input['starts_at'])) {
    jsonResponse(['success' => false, 'message' => 'Invalid starts_at time.'], 400);
}
if (isset($input['stops_at']) && !validateTime($input['stops_at'])) {
    jsonResponse(['success' => false, 'message' => 'Invalid stops_at time.'], 400);
}

// Handle image upload
$imagePath = $vehicle['image_path'];
if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
    $imageValidation = validateImageUpload($_FILES['image']);
    if ($imageValidation !== true) {
        jsonResponse(['success' => false, 'message' => $imageValidation], 400);
    }
    $newImage = uploadImage($_FILES['image'], 'vehicles');
    if ($newImage !== false) {
        deleteImage($vehicle['image_path']);
        $imagePath = $newImage;
    }
}

try {
    $pdo->beginTransaction();

    $status = $vehicle['status'];
    $approvedBy = $vehicle['approved_by'];
    if (isAdmin() && isset($input['status']) && validateEnum($input['status'], ['pending', 'approved', 'rejected'])) {
        $status = $input['status'];
        if ($status === 'approved')
            $approvedBy = getCurrentUserId();
    }

    $stmt = $pdo->prepare('
        UPDATE vehicles SET 
            name = :name, description = :description, image_path = :image_path,
            status = :status, updated_by = :updated_by, approved_by = :approved_by,
            updated_at = NOW(), starts_at = :starts_at, stops_at = :stops_at,
            used_routes = :used_routes
        WHERE vehicle_id = :id
    ');
    $stmt->execute([
        'name' => trim($input['name'] ?? $vehicle['name']),
        'description' => trim($input['description'] ?? $vehicle['description']),
        'image_path' => $imagePath,
        'status' => $status,
        'updated_by' => getCurrentUserId(),
        'approved_by' => $approvedBy,
        'starts_at' => $input['starts_at'] ?? $vehicle['starts_at'],
        'stops_at' => $input['stops_at'] ?? $vehicle['stops_at'],
        'used_routes' => is_string($usedRoutesJson) ? $usedRoutesJson : json_encode(json_decode($usedRoutesJson, true)),
        'id' => $vehicleId,
    ]);

    if (isAgent()) {
        $stmt = $pdo->prepare('
            INSERT INTO contributions (type, associated_entry_id, proposed_by, status, proposed_at)
            VALUES ("vehicle", :entry_id, :proposed_by, "pending", NOW())
        ');
        $stmt->execute(['entry_id' => $vehicleId, 'proposed_by' => getCurrentUserId()]);
        $cid = (int) $pdo->lastInsertId();
        $pdo->prepare('UPDATE vehicles SET contribution_id = :cid WHERE vehicle_id = :vid')
            ->execute(['cid' => $cid, 'vid' => $vehicleId]);
    }

    $pdo->commit();
    jsonResponse(['success' => true, 'message' => 'Vehicle updated successfully.', 'vehicle_id' => $vehicleId]);

} catch (Exception $e) {
    $pdo->rollBack();
    error_log('Update Vehicle Error: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Failed to update vehicle.'], 500);
}
