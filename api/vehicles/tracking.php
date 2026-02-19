<?php
/**
 * API: Live Vehicle Tracking
 * GET /api/vehicles/tracking.php
 *
 * Returns all approved vehicles that have active GPS data
 * (non-NULL current_lat, current_lng, current_speed).
 *
 * Used by the map page to display live bus positions.
 * Auto-polled every 10 seconds by the frontend.
 *
 * Response: { success: true, vehicles: [ { vehicle_id, name, image_path, current_lat, current_lng, current_speed, gps_updated_at, routes } ] }
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$pdo = getDBConnection();

$stmt = $pdo->query("
    SELECT v.vehicle_id, v.name, v.image_path,
           v.current_lat, v.current_lng, v.current_speed,
           v.gps_updated_at, v.used_routes
    FROM vehicles v
    WHERE v.status = 'approved'
      AND v.current_lat IS NOT NULL
      AND v.current_lng IS NOT NULL
    ORDER BY v.gps_updated_at DESC
");

$vehicles = $stmt->fetchAll();

// Parse used_routes JSON for each vehicle
foreach ($vehicles as &$v) {
    $v['used_routes'] = json_decode($v['used_routes'], true) ?? [];
    $v['current_lat'] = (float) $v['current_lat'];
    $v['current_lng'] = (float) $v['current_lng'];
    $v['current_speed'] = $v['current_speed'] !== null ? (float) $v['current_speed'] : null;
}
unset($v);

jsonResponse([
    'success' => true,
    'vehicles' => $vehicles,
    'count' => count($vehicles),
]);
