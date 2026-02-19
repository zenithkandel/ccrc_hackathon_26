<?php
/**
 * API: Create Vehicle
 * POST /api/vehicles/create.php
 * 
 * Creates a new vehicle entry + contribution record.
 * Requires: Agent or Admin authentication.
 * 
 * Body params: name, description, used_routes (JSON), 
 *              starts_at (HH:MM), stops_at (HH:MM), image (file, optional)
 * used_routes format: [{"route_id": 1, "count": 6}, {"route_id": 2, "count": 4}]
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

$missing = validateRequired(['name', 'used_routes'], $input);
if (!empty($missing)) {
    jsonResponse(['success' => false, 'message' => 'Missing required fields: ' . implode(', ', $missing)], 400);
}

// Parse & validate used_routes
$usedRoutes = $input['used_routes'];
if (is_string($usedRoutes)) {
    $usedRoutes = json_decode($usedRoutes, true);
}
if (!is_array($usedRoutes) || count($usedRoutes) < 1) {
    jsonResponse(['success' => false, 'message' => 'used_routes must list at least one route.'], 400);
}

$pdo = getDBConnection();

// Validate route IDs exist and are approved
$routeIds = array_column($usedRoutes, 'route_id');
$placeholders = implode(',', array_fill(0, count($routeIds), '?'));
$stmt = $pdo->prepare("SELECT route_id FROM routes WHERE route_id IN ($placeholders) AND status = 'approved'");
$stmt->execute($routeIds);
$validIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
$invalidIds = array_diff($routeIds, $validIds);
if (!empty($invalidIds)) {
    jsonResponse(['success' => false, 'message' => 'Invalid/unapproved route IDs: ' . implode(', ', $invalidIds)], 400);
}

// Validate time fields
if (isset($input['starts_at']) && !validateTime($input['starts_at'])) {
    jsonResponse(['success' => false, 'message' => 'Invalid starts_at time (use HH:MM format).'], 400);
}
if (isset($input['stops_at']) && !validateTime($input['stops_at'])) {
    jsonResponse(['success' => false, 'message' => 'Invalid stops_at time (use HH:MM format).'], 400);
}

// Handle image upload
$imagePath = null;
if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
    $imageValidation = validateImageUpload($_FILES['image']);
    if ($imageValidation !== true) {
        jsonResponse(['success' => false, 'message' => $imageValidation], 400);
    }
    $imagePath = uploadImage($_FILES['image'], 'vehicles');
    if ($imagePath === false) {
        jsonResponse(['success' => false, 'message' => 'Failed to upload image.'], 500);
    }
}

try {
    $pdo->beginTransaction();

    $status = isAdmin() ? 'approved' : 'pending';
    $approvedBy = isAdmin() ? getCurrentUserId() : null;

    // Normalize used_routes
    $normalizedRoutes = [];
    foreach ($usedRoutes as $ur) {
        $normalizedRoutes[] = [
            'route_id' => (int) $ur['route_id'],
            'count' => (int) ($ur['count'] ?? 1),
        ];
    }

    $stmt = $pdo->prepare('
        INSERT INTO vehicles (name, description, image_path, status, updated_by, approved_by, updated_at,
                              starts_at, stops_at, used_routes)
        VALUES (:name, :description, :image_path, :status, :updated_by, :approved_by, NOW(),
                :starts_at, :stops_at, :used_routes)
    ');
    $stmt->execute([
        'name' => trim($input['name']),
        'description' => trim($input['description'] ?? ''),
        'image_path' => $imagePath,
        'status' => $status,
        'updated_by' => getCurrentUserId(),
        'approved_by' => $approvedBy,
        'starts_at' => $input['starts_at'] ?? null,
        'stops_at' => $input['stops_at'] ?? null,
        'used_routes' => json_encode($normalizedRoutes),
    ]);

    $vehicleId = (int) $pdo->lastInsertId();

    $contribStatus = isAdmin() ? 'accepted' : 'pending';
    $stmt = $pdo->prepare('
        INSERT INTO contributions (type, associated_entry_id, proposed_by, accepted_by, status, proposed_at, responded_at)
        VALUES ("vehicle", :entry_id, :proposed_by, :accepted_by, :status, NOW(), :responded_at)
    ');
    $stmt->execute([
        'entry_id' => $vehicleId,
        'proposed_by' => isAgent() ? getCurrentUserId() : null,
        'accepted_by' => isAdmin() ? getCurrentUserId() : null,
        'status' => $contribStatus,
        'responded_at' => isAdmin() ? date('Y-m-d H:i:s') : null,
    ]);

    $contributionId = (int) $pdo->lastInsertId();

    $pdo->prepare('UPDATE vehicles SET contribution_id = :cid WHERE vehicle_id = :vid')
        ->execute(['cid' => $contributionId, 'vid' => $vehicleId]);

    $pdo->commit();

    jsonResponse([
        'success' => true,
        'message' => $status === 'approved' ? 'Vehicle created and approved.' : 'Vehicle submitted for review.',
        'vehicle_id' => $vehicleId,
        'contribution_id' => $contributionId,
    ], 201);

} catch (Exception $e) {
    $pdo->rollBack();
    error_log('Create Vehicle Error: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Failed to create vehicle.'], 500);
}
