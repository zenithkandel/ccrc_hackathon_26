<?php
/**
 * Route Search API — Sawari
 *
 * POST /api/search/find-route.php
 *
 * Accepts:
 *   start_location_id      (int, required)
 *   destination_location_id (int, required)
 *   passenger_type          (string, optional: regular|student|elderly)
 *   start_lat, start_lng    (float, optional: user's actual position for walking guidance)
 *   end_lat, end_lng        (float, optional: user's actual destination for walking guidance)
 *
 * Calls the pathfinder engine, logs the trip, increments location counts,
 * and returns the full route result JSON.
 */

require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../algorithms/pathfinder.php';

header('Content-Type: application/json');

// ─── Method check ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
}

// ─── Parse inputs ────────────────────────────────────────
$startId = getIntParam('start_location_id');
$destId = getIntParam('destination_location_id');
$passengerType = getParam('passenger_type', 'regular');

// Actual user coordinates (for walk-to-stop / walk-from-stop guidance)
$startLat = getParam('start_lat') ? (float) getParam('start_lat') : null;
$startLng = getParam('start_lng') ? (float) getParam('start_lng') : null;
$endLat = getParam('end_lat') ? (float) getParam('end_lat') : null;
$endLng = getParam('end_lng') ? (float) getParam('end_lng') : null;

// ─── Validate ────────────────────────────────────────────
if (!$startId || $startId < 1) {
    jsonResponse(['success' => false, 'error' => 'Valid start_location_id is required.'], 400);
}
if (!$destId || $destId < 1) {
    jsonResponse(['success' => false, 'error' => 'Valid destination_location_id is required.'], 400);
}
if (!in_array($passengerType, ['regular', 'student', 'elderly'], true)) {
    $passengerType = 'regular';
}

// ─── Run the pathfinder ──────────────────────────────────
try {
    $result = findRoute($startId, $destId, $passengerType, $startLat, $startLng, $endLat, $endLng);
} catch (Exception $e) {
    jsonResponse([
        'success' => false,
        'error' => 'An error occurred while finding the route.',
    ], 500);
}

// ─── Log the trip & update location counts ───────────────
if ($result['success']) {
    try {
        $db = getDBConnection();

        // Insert trip record
        $routesUsed = $result['routes_used'] ?? [];
        $stmt = $db->prepare("
            INSERT INTO trips (ip_address, start_location_id, destination_location_id, routes_used, queried_at)
            VALUES (:ip, :start, :dest, :routes, NOW())
        ");
        $stmt->execute([
            ':ip' => getClientIP(),
            ':start' => $startId,
            ':dest' => $destId,
            ':routes' => json_encode(array_map('intval', $routesUsed)),
        ]);

        // Increment departure_count on start location
        $stmt = $db->prepare("
            UPDATE locations SET departure_count = departure_count + 1 WHERE location_id = :id
        ");
        $stmt->execute([':id' => $startId]);

        // Increment destination_count on end location
        $stmt = $db->prepare("
            UPDATE locations SET destination_count = destination_count + 1 WHERE location_id = :id
        ");
        $stmt->execute([':id' => $destId]);

    } catch (Exception $e) {
        // Trip logging is non-critical — don't fail the response
    }
}

// ─── Return result ───────────────────────────────────────
jsonResponse($result, $result['success'] ? 200 : 404);
