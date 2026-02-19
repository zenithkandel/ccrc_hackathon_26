<?php
/**
 * API: Log Trip
 * POST /api/trips/log.php
 * 
 * Logs a route-finding query for analytics. Called automatically when a user
 * searches for a route or completes a route lookup.
 * 
 * Public endpoint — no auth required.
 * 
 * Body params: start_location_id, destination_location_id, routes_used (JSON array of route_ids)
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/validation.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$input = $_POST;
if (empty($input)) {
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true) ?? [];
}

$startId = (int) ($input['start_location_id'] ?? 0);
$destId = (int) ($input['destination_location_id'] ?? 0);

if ($startId <= 0 || $destId <= 0) {
    jsonResponse(['success' => false, 'message' => 'Both start_location_id and destination_location_id are required.'], 400);
}

// Parse routes_used
$routesUsed = null;
if (isset($input['routes_used'])) {
    $ru = $input['routes_used'];
    if (is_string($ru)) {
        $ru = json_decode($ru, true);
    }
    if (is_array($ru)) {
        $routesUsed = json_encode(array_map('intval', $ru));
    }
}

$pdo = getDBConnection();

// Validate locations exist
$placeholders = '?,?';
$stmt = $pdo->prepare("SELECT location_id FROM locations WHERE location_id IN ($placeholders)");
$stmt->execute([$startId, $destId]);
$found = $stmt->fetchAll(PDO::FETCH_COLUMN);

if (!in_array($startId, $found) || !in_array($destId, $found)) {
    jsonResponse(['success' => false, 'message' => 'One or both location IDs are invalid.'], 400);
}

try {
    $stmt = $pdo->prepare('
        INSERT INTO trips (ip_address, start_location_id, destination_location_id, routes_used, queried_at)
        VALUES (:ip, :start_id, :dest_id, :routes_used, NOW())
    ');
    $stmt->execute([
        'ip' => getClientIP(),
        'start_id' => $startId,
        'dest_id' => $destId,
        'routes_used' => $routesUsed,
    ]);

    jsonResponse(['success' => true, 'message' => 'Trip logged.', 'trip_id' => (int) $pdo->lastInsertId()], 201);

} catch (Exception $e) {
    error_log('Log Trip Error: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Failed to log trip.'], 500);
}
