<?php
/**
 * API: GPS Position Update
 * POST /api/vehicles/gps-update.php
 *
 * Receives GPS data from vehicle tracking devices (GPS modules).
 * Updates the vehicle's real-time position in the database.
 *
 * Body params (JSON):
 *   vehicle_id  (int, required)   — The vehicle to update
 *   latitude    (float, required) — Current latitude
 *   longitude   (float, required) — Current longitude
 *   speed       (float, optional) — Speed in km/h (default: 0)
 *   api_key     (string, required)— Device authentication key
 *
 * The api_key is a simple shared secret for GPS devices.
 * In production, this should use proper device authentication.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/validation.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
}

// ─── Parse input ─────────────────────────────────────────
$raw = file_get_contents('php://input');
$input = json_decode($raw, true);

if (!$input) {
    jsonResponse(['success' => false, 'message' => 'Invalid JSON body.'], 400);
}

// ─── Simple device authentication ────────────────────────
// In production, replace with a proper per-device token system.
$apiKey = $input['api_key'] ?? '';
if (!defined('GPS_DEVICE_API_KEY')) {
    define('GPS_DEVICE_API_KEY', 'sawari-gps-device-2026');
}
if ($apiKey !== GPS_DEVICE_API_KEY) {
    jsonResponse(['success' => false, 'message' => 'Invalid API key.'], 401);
}

// ─── Validate required fields ────────────────────────────
$vehicleId = (int) ($input['vehicle_id'] ?? 0);
$latitude = $input['latitude'] ?? null;
$longitude = $input['longitude'] ?? null;
$speed = isset($input['speed']) ? (float) $input['speed'] : 0.0;

if ($vehicleId <= 0) {
    jsonResponse(['success' => false, 'message' => 'Valid vehicle_id is required.'], 400);
}

if ($latitude === null || $longitude === null) {
    jsonResponse(['success' => false, 'message' => 'latitude and longitude are required.'], 400);
}

$latitude = (float) $latitude;
$longitude = (float) $longitude;

if (!validateLatitude($latitude)) {
    jsonResponse(['success' => false, 'message' => 'Invalid latitude value.'], 400);
}

if (!validateLongitude($longitude)) {
    jsonResponse(['success' => false, 'message' => 'Invalid longitude value.'], 400);
}

if ($speed < 0 || $speed > 200) {
    jsonResponse(['success' => false, 'message' => 'Speed must be between 0 and 200 km/h.'], 400);
}

// ─── Update vehicle GPS data ─────────────────────────────
$pdo = getDBConnection();

$stmt = $pdo->prepare("
    UPDATE vehicles
    SET current_lat = :lat,
        current_lng = :lng,
        current_speed = :speed,
        gps_updated_at = NOW()
    WHERE vehicle_id = :id
      AND status = 'approved'
");

$stmt->execute([
    'lat' => $latitude,
    'lng' => $longitude,
    'speed' => $speed,
    'id' => $vehicleId,
]);

if ($stmt->rowCount() === 0) {
    jsonResponse(['success' => false, 'message' => 'Vehicle not found or not approved.'], 404);
}

jsonResponse([
    'success' => true,
    'message' => 'GPS position updated.',
    'vehicle_id' => $vehicleId,
    'position' => [
        'latitude' => $latitude,
        'longitude' => $longitude,
        'speed' => $speed,
    ],
]);
