<?php
/**
 * API: Create Route
 * POST /api/routes/create.php
 * 
 * Creates a new route entry + contribution record.
 * Requires: Agent or Admin authentication.
 * 
 * Body params: name, description, location_list (JSON array), image (file, optional)
 * location_list format: [{"index": 1, "location_id": 10}, {"index": 2, "location_id": 15}, ...]
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

// Validate required fields
$missing = validateRequired(['name', 'location_list'], $input);
if (!empty($missing)) {
    jsonResponse(['success' => false, 'message' => 'Missing required fields: ' . implode(', ', $missing)], 400);
}

// Parse and validate location_list
$locationList = $input['location_list'];
if (is_string($locationList)) {
    $locationList = json_decode($locationList, true);
}
if (!is_array($locationList) || count($locationList) < 2) {
    jsonResponse(['success' => false, 'message' => 'location_list must be an array with at least 2 locations.'], 400);
}

// Support both [{index, location_id}] and flat [id, id, ...] formats
if (isset($locationList[0]) && !is_array($locationList[0])) {
    $locationList = array_map(fn($id, $i) => ['index' => $i + 1, 'location_id' => (int) $id], $locationList, array_keys($locationList));
}

// Validate each location exists and is approved
$pdo = getDBConnection();
$locationIds = array_map('intval', array_column($locationList, 'location_id'));
if (empty($locationIds)) {
    jsonResponse(['success' => false, 'message' => 'location_list entries must each have a location_id.'], 400);
}
$placeholders = implode(',', array_fill(0, count($locationIds), '?'));
$stmt = $pdo->prepare("SELECT location_id FROM locations WHERE location_id IN ($placeholders) AND status = 'approved'");
$stmt->execute($locationIds);
$validIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

$invalidIds = array_diff($locationIds, $validIds);
if (!empty($invalidIds)) {
    jsonResponse([
        'success' => false,
        'message' => 'Some location IDs are invalid or not approved: ' . implode(', ', $invalidIds),
    ], 400);
}

// Handle image upload
$imagePath = null;
if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
    $imageValidation = validateImageUpload($_FILES['image']);
    if ($imageValidation !== true) {
        jsonResponse(['success' => false, 'message' => $imageValidation], 400);
    }
    $imagePath = uploadImage($_FILES['image'], 'routes');
    if ($imagePath === false) {
        jsonResponse(['success' => false, 'message' => 'Failed to upload image.'], 500);
    }
}

try {
    $pdo->beginTransaction();

    $status = isAdmin() ? 'approved' : 'pending';
    $approvedBy = isAdmin() ? getCurrentUserId() : null;

    // Normalize location_list with proper indexes
    $normalizedList = [];
    foreach ($locationList as $i => $item) {
        $normalizedList[] = [
            'index' => $item['index'] ?? ($i + 1),
            'location_id' => (int) $item['location_id'],
        ];
    }
    usort($normalizedList, fn($a, $b) => $a['index'] - $b['index']);

    $stmt = $pdo->prepare('
        INSERT INTO routes (name, description, image_path, status, updated_by, approved_by, updated_at, location_list)
        VALUES (:name, :description, :image_path, :status, :updated_by, :approved_by, NOW(), :location_list)
    ');
    $stmt->execute([
        'name' => trim($input['name']),
        'description' => trim($input['description'] ?? ''),
        'image_path' => $imagePath,
        'status' => $status,
        'updated_by' => getCurrentUserId(),
        'approved_by' => $approvedBy,
        'location_list' => json_encode($normalizedList),
    ]);

    $routeId = (int) $pdo->lastInsertId();

    // Create contribution record
    $contribStatus = isAdmin() ? 'accepted' : 'pending';
    $stmt = $pdo->prepare('
        INSERT INTO contributions (type, associated_entry_id, proposed_by, accepted_by, status, proposed_at, responded_at)
        VALUES ("route", :entry_id, :proposed_by, :accepted_by, :status, NOW(), :responded_at)
    ');
    $stmt->execute([
        'entry_id' => $routeId,
        'proposed_by' => isAgent() ? getCurrentUserId() : null,
        'accepted_by' => isAdmin() ? getCurrentUserId() : null,
        'status' => $contribStatus,
        'responded_at' => isAdmin() ? date('Y-m-d H:i:s') : null,
    ]);

    $contributionId = (int) $pdo->lastInsertId();

    $stmt = $pdo->prepare('UPDATE routes SET contribution_id = :cid WHERE route_id = :rid');
    $stmt->execute(['cid' => $contributionId, 'rid' => $routeId]);

    $pdo->commit();

    jsonResponse([
        'success' => true,
        'message' => $status === 'approved' ? 'Route created and approved.' : 'Route submitted for review.',
        'route_id' => $routeId,
        'contribution_id' => $contributionId,
    ], 201);

} catch (Exception $e) {
    $pdo->rollBack();
    error_log('Create Route Error: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Failed to create route.'], 500);
}
