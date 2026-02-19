<?php
/**
 * API: Update Location
 * POST /api/locations/update.php
 * 
 * Updates an existing location entry + creates a new contribution record.
 * Requires: Agent (own pending entries) or Admin authentication.
 * 
 * Body params: location_id, name, description, latitude, longitude, type
 * Admin-only: status (approve/reject via this endpoint or contributions/respond.php)
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

$locationId = (int) ($input['location_id'] ?? $_GET['id'] ?? 0);
if ($locationId <= 0) {
    jsonResponse(['success' => false, 'message' => 'Valid location_id is required.'], 400);
}

$pdo = getDBConnection();

// Fetch existing location
$stmt = $pdo->prepare('SELECT * FROM locations WHERE location_id = :id');
$stmt->execute(['id' => $locationId]);
$location = $stmt->fetch();

if (!$location) {
    jsonResponse(['success' => false, 'message' => 'Location not found.'], 404);
}

// Permission check: agents can only edit their own pending entries
if (isAgent() && ($location['updated_by'] != getCurrentUserId() || $location['status'] !== 'pending')) {
    jsonResponse(['success' => false, 'message' => 'You can only edit your own pending locations.'], 403);
}

// Validate inputs if provided
if (isset($input['latitude']) && !validateLatitude($input['latitude'])) {
    jsonResponse(['success' => false, 'message' => 'Invalid latitude value.'], 400);
}
if (isset($input['longitude']) && !validateLongitude($input['longitude'])) {
    jsonResponse(['success' => false, 'message' => 'Invalid longitude value.'], 400);
}
if (isset($input['type']) && !validateEnum($input['type'], ['stop', 'landmark'])) {
    jsonResponse(['success' => false, 'message' => 'Invalid location type.'], 400);
}

try {
    $pdo->beginTransaction();

    // Build update fields
    $name = trim($input['name'] ?? $location['name']);
    $description = trim($input['description'] ?? $location['description']);
    $latitude = isset($input['latitude']) ? (float) $input['latitude'] : $location['latitude'];
    $longitude = isset($input['longitude']) ? (float) $input['longitude'] : $location['longitude'];
    $type = $input['type'] ?? $location['type'];

    // Admin can also update status directly
    $status = $location['status'];
    if (isAdmin() && isset($input['status']) && validateEnum($input['status'], ['pending', 'approved', 'rejected'])) {
        $status = $input['status'];
    }

    $approvedBy = $location['approved_by'];
    if (isAdmin() && $status === 'approved') {
        $approvedBy = getCurrentUserId();
    }

    $stmt = $pdo->prepare('
        UPDATE locations SET 
            name = :name, 
            description = :description, 
            latitude = :latitude, 
            longitude = :longitude, 
            type = :type, 
            status = :status,
            updated_by = :updated_by, 
            approved_by = :approved_by,
            updated_at = NOW()
        WHERE location_id = :id
    ');
    $stmt->execute([
        'name' => $name,
        'description' => $description,
        'latitude' => $latitude,
        'longitude' => $longitude,
        'type' => $type,
        'status' => $status,
        'updated_by' => getCurrentUserId(),
        'approved_by' => $approvedBy,
        'id' => $locationId,
    ]);

    // Create contribution record for the update (only if agent)
    if (isAgent()) {
        $stmt = $pdo->prepare('
            INSERT INTO contributions (type, associated_entry_id, proposed_by, status, proposed_at)
            VALUES ("location", :entry_id, :proposed_by, "pending", NOW())
        ');
        $stmt->execute([
            'entry_id' => $locationId,
            'proposed_by' => getCurrentUserId(),
        ]);

        $contributionId = (int) $pdo->lastInsertId();
        $stmt = $pdo->prepare('UPDATE locations SET contribution_id = :cid WHERE location_id = :lid');
        $stmt->execute(['cid' => $contributionId, 'lid' => $locationId]);
    }

    $pdo->commit();

    jsonResponse([
        'success' => true,
        'message' => 'Location updated successfully.',
        'location_id' => $locationId,
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    error_log('Update Location Error: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Failed to update location.'], 500);
}
