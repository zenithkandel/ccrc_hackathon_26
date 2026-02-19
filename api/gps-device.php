<?php
/**
 * SAWARI — GPS Device Receiver API
 *
 * Receives live GPS data from physical GPS hardware devices
 * and feeds it into the Sawari vehicle tracking system.
 *
 * Expected JSON payload:
 * {
 *     "data": {
 *         "bus_id": 1,
 *         "latitude": 27.673159,
 *         "longitude": 85.343842,
 *         "speed": 1.8,
 *         "direction": 0,
 *         "altitude": 1208.1,
 *         "satellites": 7,
 *         "hdop": 2,
 *         "timestamp": "2026-02-19T09:06:53Z"
 *     }
 * }
 *
 * Field mapping:
 *   bus_id    → vehicle_id (in vehicles table)
 *   latitude  → latitude
 *   longitude → longitude
 *   speed     → velocity (km/h)
 *   direction → heading (stored for future use)
 *
 * The endpoint also maintains a rolling debug log at logs/gps-device.json
 * (last 500 entries).
 */

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method not allowed. Use POST."]);
    exit;
}

// ── Parse Input ─────────────────────────────────────────────
$rawBody = file_get_contents("php://input");
$input = json_decode($rawBody, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid JSON: " . json_last_error_msg()]);
    exit;
}

if (!isset($input['data']) || !is_array($input['data'])) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Missing 'data' field"]);
    exit;
}

$data = $input['data'];

// ── Validate Required Fields ────────────────────────────────
$busId = isset($data['bus_id']) ? (int) $data['bus_id'] : 0;
$latitude = isset($data['latitude']) ? (float) $data['latitude'] : null;
$longitude = isset($data['longitude']) ? (float) $data['longitude'] : null;
$speed = isset($data['speed']) ? (float) $data['speed'] : 0;
$direction = isset($data['direction']) ? (float) $data['direction'] : null;
$altitude = isset($data['altitude']) ? (float) $data['altitude'] : null;
$satellites = isset($data['satellites']) ? (int) $data['satellites'] : null;
$hdop = isset($data['hdop']) ? (float) $data['hdop'] : null;
$deviceTs = isset($data['timestamp']) ? $data['timestamp'] : null;

if (!$busId) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Missing or invalid 'bus_id'"]);
    exit;
}

if ($latitude === null || $longitude === null) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Missing 'latitude' and/or 'longitude'"]);
    exit;
}

// Basic coordinate sanity check (Nepal bounding box: ~26-31°N, 80-89°E)
if ($latitude < 25 || $latitude > 32 || $longitude < 79 || $longitude > 90) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Coordinates out of Nepal range"]);
    exit;
}

// GPS quality check — if HDOP is too high, the fix is unreliable
if ($hdop !== null && $hdop > 10) {
    // Still accept but flag it
    $gpsQuality = 'poor';
} elseif ($hdop !== null && $hdop > 5) {
    $gpsQuality = 'moderate';
} else {
    $gpsQuality = 'good';
}

// ── Connect to Database ─────────────────────────────────────
require_once __DIR__ . '/config.php';

try {
    $db = getDB();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    exit;
}

// ── Verify Vehicle Exists and Is Approved ───────────────────
$check = $db->prepare("SELECT vehicle_id, name FROM vehicles WHERE vehicle_id = :id AND status = 'approved'");
$check->execute([':id' => $busId]);
$vehicle = $check->fetch(PDO::FETCH_ASSOC);

if (!$vehicle) {
    http_response_code(404);
    echo json_encode([
        "status" => "error",
        "message" => "Vehicle (bus_id: $busId) not found or not approved"
    ]);
    exit;
}

// ── Update Vehicle GPS Position ─────────────────────────────
$stmt = $db->prepare("UPDATE vehicles
                      SET latitude = :lat,
                          longitude = :lng,
                          velocity = :vel,
                          gps_active = 1,
                          last_gps_update = NOW()
                      WHERE vehicle_id = :id");

$stmt->execute([
    ':lat' => $latitude,
    ':lng' => $longitude,
    ':vel' => $speed,
    ':id' => $busId
]);

// ── Log to JSON File (rolling, last 500 entries) ────────────
$logDir = __DIR__ . '/../logs';
$logFile = $logDir . '/gps-device.json';

if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}

$logEntry = [
    "received_at" => date("Y-m-d H:i:s"),
    "vehicle_id" => $busId,
    "vehicle_name" => $vehicle['name'],
    "latitude" => $latitude,
    "longitude" => $longitude,
    "speed" => $speed,
    "direction" => $direction,
    "altitude" => $altitude,
    "satellites" => $satellites,
    "hdop" => $hdop,
    "gps_quality" => $gpsQuality,
    "device_ts" => $deviceTs
];

$existingLogs = [];
if (file_exists($logFile)) {
    $existingLogs = json_decode(file_get_contents($logFile), true);
    if (!is_array($existingLogs)) {
        $existingLogs = [];
    }
}

$existingLogs[] = $logEntry;

// Keep only the last 500 entries to prevent unbounded growth
if (count($existingLogs) > 500) {
    $existingLogs = array_slice($existingLogs, -500);
}

file_put_contents($logFile, json_encode($existingLogs, JSON_PRETTY_PRINT));

// ── Respond Success ─────────────────────────────────────────
echo json_encode([
    "status" => "success",
    "message" => "GPS position updated for '{$vehicle['name']}' (ID: $busId)",
    "vehicle_id" => $busId,
    "gps_quality" => $gpsQuality,
    "server_time" => date("Y-m-d H:i:s")
]);
