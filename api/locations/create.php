<?php
/**
 * API: Create Location
 * POST /api/locations/create.php
 * 
 * Creates a new location entry + contribution record.
 * Requires: Agent or Admin authentication.
 * 
 * Body params: name, description, latitude, longitude, type (stop|landmark)
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/validation.php';

// Auth check
if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => 'Authentication required.'], 401);
}

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
}

// Parse input (support both JSON body and form data)
$input = $_POST;
if (empty($input)) {
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true) ?? [];
}

// Validate required fields
$missing = validateRequired(['name', 'latitude', 'longitude'], $input);
if (!empty($missing)) {
    jsonResponse(['success' => false, 'message' => 'Missing required fields: ' . implode(', ', $missing)], 400);
}

// Validate coordinates
if (!validateLatitude($input['latitude'])) {
    jsonResponse(['success' => false, 'message' => 'Invalid latitude value.'], 400);
}
if (!validateLongitude($input['longitude'])) {
    jsonResponse(['success' => false, 'message' => 'Invalid longitude value.'], 400);
}

// Validate type
$type = $input['type'] ?? 'stop';
if (!validateEnum($type, ['stop', 'landmark'])) {
    jsonResponse(['success' => false, 'message' => 'Invalid location type. Must be "stop" or "landmark".'], 400);
}

$pdo = getDBConnection();

try {
    $pdo->beginTransaction();

    // Determine status: admin-created entries are auto-approved
    $status = isAdmin() ? 'approved' : 'pending';
    $approvedBy = isAdmin() ? getCurrentUserId() : null;

    // Insert the location
    $stmt = $pdo->prepare('
        INSERT INTO locations (name, description, latitude, longitude, type, status, updated_by, approved_by, updated_at)
        VALUES (:name, :description, :latitude, :longitude, :type, :status, :updated_by, :approved_by, NOW())
    ');
    $stmt->execute([
        'name' => trim($input['name']),
        'description' => trim($input['description'] ?? ''),
        'latitude' => (float) $input['latitude'],
        'longitude' => (float) $input['longitude'],
        'type' => $type,
        'status' => $status,
        'updated_by' => getCurrentUserId(),
        'approved_by' => $approvedBy,
    ]);

    $locationId = (int) $pdo->lastInsertId();

    // Create contribution record
    $contribStatus = isAdmin() ? 'accepted' : 'pending';
    $stmt = $pdo->prepare('
        INSERT INTO contributions (type, associated_entry_id, proposed_by, accepted_by, status, proposed_at, responded_at)
        VALUES ("location", :entry_id, :proposed_by, :accepted_by, :status, NOW(), :responded_at)
    ');
    $stmt->execute([
        'entry_id' => $locationId,
        'proposed_by' => isAgent() ? getCurrentUserId() : null,
        'accepted_by' => isAdmin() ? getCurrentUserId() : null,
        'status' => $contribStatus,
        'responded_at' => isAdmin() ? date('Y-m-d H:i:s') : null,
    ]);

    $contributionId = (int) $pdo->lastInsertId();

    // Link contribution to location
    $stmt = $pdo->prepare('UPDATE locations SET contribution_id = :cid WHERE location_id = :lid');
    $stmt->execute(['cid' => $contributionId, 'lid' => $locationId]);

    $pdo->commit();

    jsonResponse([
        'success' => true,
        'message' => $status === 'approved' ? 'Location added and approved.' : 'Location submitted for review.',
        'location_id' => $locationId,
        'contribution_id' => $contributionId,
        'status' => $status,
    ], 201);

} catch (Exception $e) {
    $pdo->rollBack();
    error_log('Create Location Error: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Failed to create location.'], 500);
}
