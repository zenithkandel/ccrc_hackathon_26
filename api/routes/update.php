<?php
/**
 * API: Update Route
 * POST /api/routes/update.php
 * 
 * Updates an existing route entry.
 * Requires: Agent (own pending) or Admin.
 * 
 * Body params: route_id, name, description, location_list (JSON), image (file, optional)
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

$routeId = (int) ($input['route_id'] ?? $_GET['id'] ?? 0);
if ($routeId <= 0) {
    jsonResponse(['success' => false, 'message' => 'Valid route_id is required.'], 400);
}

$pdo = getDBConnection();

$stmt = $pdo->prepare('SELECT * FROM routes WHERE route_id = :id');
$stmt->execute(['id' => $routeId]);
$route = $stmt->fetch();

if (!$route) {
    jsonResponse(['success' => false, 'message' => 'Route not found.'], 404);
}

if (isAgent() && ($route['updated_by'] != getCurrentUserId() || $route['status'] !== 'pending')) {
    jsonResponse(['success' => false, 'message' => 'You can only edit your own pending routes.'], 403);
}

// Validate location_list if provided
$locationList = $route['location_list'];
if (isset($input['location_list'])) {
    $newList = $input['location_list'];
    if (is_string($newList)) {
        $newList = json_decode($newList, true);
    }
    if (!is_array($newList) || count($newList) < 2) {
        jsonResponse(['success' => false, 'message' => 'location_list must have at least 2 locations.'], 400);
    }

    // Support both [{index, location_id}] and flat [id, id, ...] formats
    if (isset($newList[0]) && !is_array($newList[0])) {
        $newList = array_map(fn($id, $i) => ['index' => $i + 1, 'location_id' => (int) $id], $newList, array_keys($newList));
    }

    // Validate locations exist
    $locIds = array_map('intval', array_column($newList, 'location_id'));
    if (empty($locIds)) {
        jsonResponse(['success' => false, 'message' => 'location_list entries must each have a location_id.'], 400);
    }
    $placeholders = implode(',', array_fill(0, count($locIds), '?'));
    $checkStmt = $pdo->prepare("SELECT location_id FROM locations WHERE location_id IN ($placeholders) AND status = 'approved'");
    $checkStmt->execute($locIds);
    $validIds = $checkStmt->fetchAll(PDO::FETCH_COLUMN);
    $invalidIds = array_diff($locIds, $validIds);
    if (!empty($invalidIds)) {
        jsonResponse(['success' => false, 'message' => 'Invalid location IDs: ' . implode(', ', $invalidIds)], 400);
    }

    // Normalize
    $normalizedList = [];
    foreach ($newList as $i => $item) {
        $normalizedList[] = ['index' => $item['index'] ?? ($i + 1), 'location_id' => (int) $item['location_id']];
    }
    usort($normalizedList, fn($a, $b) => $a['index'] - $b['index']);
    $locationList = json_encode($normalizedList);
}

// Handle image upload
$imagePath = $route['image_path'];
if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
    $imageValidation = validateImageUpload($_FILES['image']);
    if ($imageValidation !== true) {
        jsonResponse(['success' => false, 'message' => $imageValidation], 400);
    }
    $newImage = uploadImage($_FILES['image'], 'routes');
    if ($newImage !== false) {
        deleteImage($route['image_path']); // Remove old image
        $imagePath = $newImage;
    }
}

try {
    $pdo->beginTransaction();

    $status = $route['status'];
    $approvedBy = $route['approved_by'];
    if (isAdmin() && isset($input['status']) && validateEnum($input['status'], ['pending', 'approved', 'rejected'])) {
        $status = $input['status'];
        if ($status === 'approved')
            $approvedBy = getCurrentUserId();
    }

    $stmt = $pdo->prepare('
        UPDATE routes SET 
            name = :name, description = :description, image_path = :image_path,
            status = :status, updated_by = :updated_by, approved_by = :approved_by,
            updated_at = NOW(), location_list = :location_list
        WHERE route_id = :id
    ');
    $stmt->execute([
        'name' => trim($input['name'] ?? $route['name']),
        'description' => trim($input['description'] ?? $route['description']),
        'image_path' => $imagePath,
        'status' => $status,
        'updated_by' => getCurrentUserId(),
        'approved_by' => $approvedBy,
        'location_list' => is_string($locationList) ? $locationList : json_encode(json_decode($locationList, true)),
        'id' => $routeId,
    ]);

    if (isAgent()) {
        $stmt = $pdo->prepare('
            INSERT INTO contributions (type, associated_entry_id, proposed_by, status, proposed_at)
            VALUES ("route", :entry_id, :proposed_by, "pending", NOW())
        ');
        $stmt->execute(['entry_id' => $routeId, 'proposed_by' => getCurrentUserId()]);
        $cid = (int) $pdo->lastInsertId();
        $pdo->prepare('UPDATE routes SET contribution_id = :cid WHERE route_id = :rid')
            ->execute(['cid' => $cid, 'rid' => $routeId]);
    }

    $pdo->commit();
    jsonResponse(['success' => true, 'message' => 'Route updated successfully.', 'route_id' => $routeId]);

} catch (Exception $e) {
    $pdo->rollBack();
    error_log('Update Route Error: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Failed to update route.'], 500);
}
