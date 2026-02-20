<?php
/**
 * SAWARI — GPS Simulator
 * 
 * Simulates a vehicle moving along its route by sending
 * GPS updates to the vehicles API endpoint.
 * 
 * Usage (CLI):
 *   php tools/gps-simulator.php --vehicle=1 --speed=25 --interval=3
 * 
 * Usage (Browser):
 *   tools/gps-simulator.php?vehicle_id=1&speed=25&interval=3
 * 
 * Parameters:
 *   vehicle   — Vehicle ID to simulate (must be approved)
 *   speed     — Simulated speed in km/h (default: 20)
 *   interval  — Seconds between GPS pings (default: 3)
 *   reverse   — If set, traverse route in reverse direction
 * 
 * The simulator reads the vehicle's first used_route, extracts the
 * ordered location_list, and interpolates positions between stops
 * to send realistic GPS coordinates.
 */

require_once __DIR__ . '/../api/config.php';

// ── Parse Parameters ────────────────────────────────────────
$isCli = (php_sapi_name() === 'cli');

if ($isCli) {
    $opts = getopt('', ['vehicle:', 'speed:', 'interval:', 'reverse']);
    $vehicleId = isset($opts['vehicle']) ? (int) $opts['vehicle'] : 0;
    $speed = isset($opts['speed']) ? (float) $opts['speed'] : 20;
    $interval = isset($opts['interval']) ? (int) $opts['interval'] : 3;
    $reverse = isset($opts['reverse']);
} else {
    // Browser mode — single step or auto-run
    header('Content-Type: application/json');
    $vehicleId = isset($_GET['vehicle_id']) ? (int) $_GET['vehicle_id'] : 0;
    $speed = isset($_GET['speed']) ? (float) $_GET['speed'] : 20;
    $interval = isset($_GET['interval']) ? (int) $_GET['interval'] : 3;
    $reverse = isset($_GET['reverse']);
    $action = isset($_GET['action']) ? $_GET['action'] : 'info';
}

if (!$vehicleId) {
    output("Error: Vehicle ID required.", true);
}

// ── Load Vehicle & Route ────────────────────────────────────
$db = getDB();

$stmt = $db->prepare("SELECT v.vehicle_id, v.name, v.used_routes, v.latitude, v.longitude
                      FROM vehicles v
                      WHERE v.vehicle_id = :id AND v.status = 'approved'");
$stmt->execute([':id' => $vehicleId]);
$vehicle = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vehicle) {
    output("Error: Vehicle #$vehicleId not found or not approved.", true);
}

$usedRoutes = $vehicle['used_routes'] ? json_decode($vehicle['used_routes'], true) : [];
if (empty($usedRoutes)) {
    output("Error: Vehicle #$vehicleId has no assigned routes.", true);
}

$routeId = $usedRoutes[0]; // Use first assigned route

$rStmt = $db->prepare("SELECT route_id, name, location_list FROM routes WHERE route_id = :rid AND status = 'approved'");
$rStmt->execute([':rid' => $routeId]);
$route = $rStmt->fetch(PDO::FETCH_ASSOC);

if (!$route || !$route['location_list']) {
    output("Error: Route #$routeId not found or has no stops.", true);
}

$stops = json_decode($route['location_list'], true);
if (count($stops) < 2) {
    output("Error: Route needs at least 2 stops.", true);
}

if ($reverse) {
    $stops = array_reverse($stops);
}

// ── Generate Interpolated Path ──────────────────────────────
// Create fine-grained waypoints between stops (every ~50m)
$waypoints = generateWaypoints($stops, 0.05); // 50m intervals

// ── Browser Mode: Info / Step ───────────────────────────────
if (!$isCli) {
    if ($action === 'info') {
        echo json_encode([
            'success' => true,
            'vehicle' => $vehicle['name'],
            'vehicle_id' => $vehicleId,
            'route' => $route['name'],
            'route_id' => $routeId,
            'stop_count' => count($stops),
            'waypoint_count' => count($waypoints),
            'stops' => array_map(fn($s) => $s['name'], $stops),
            'speed_kmh' => $speed,
            'interval_seconds' => $interval,
            'instructions' => 'Use action=step&step=N to send GPS for waypoint N, or action=start for auto-run info.'
        ]);
        exit;
    }

    if ($action === 'step') {
        $stepIdx = isset($_GET['step']) ? (int) $_GET['step'] : 0;
        if ($stepIdx < 0 || $stepIdx >= count($waypoints)) {
            echo json_encode(['success' => false, 'error' => 'Step index out of range (0-' . (count($waypoints) - 1) . ')']);
            exit;
        }

        $wp = $waypoints[$stepIdx];
        sendGpsUpdate($vehicleId, $wp['lat'], $wp['lng'], $speed);

        echo json_encode([
            'success' => true,
            'step' => $stepIdx,
            'total_steps' => count($waypoints),
            'lat' => $wp['lat'],
            'lng' => $wp['lng'],
            'nearest_stop' => $wp['nearest_stop'],
            'speed' => $speed
        ]);
        exit;
    }

    // action=stop — deactivate GPS
    if ($action === 'stop') {
        $db->prepare("UPDATE vehicles SET gps_active = 0 WHERE vehicle_id = :id")
            ->execute([':id' => $vehicleId]);
        echo json_encode(['success' => true, 'message' => 'GPS tracking stopped for vehicle #' . $vehicleId]);
        exit;
    }

    echo json_encode(['error' => 'Unknown action. Use info, step, or stop.']);
    exit;
}

// ── CLI Mode: Run Full Simulation ───────────────────────────
output("=== SAWARI GPS Simulator ===");
output("Vehicle: {$vehicle['name']} (#{$vehicleId})");
output("Route: {$route['name']} (#{$routeId})");
output("Stops: " . count($stops) . " | Waypoints: " . count($waypoints));
output("Speed: {$speed} km/h | Interval: {$interval}s");
output("Direction: " . ($reverse ? 'REVERSE' : 'FORWARD'));
output(str_repeat('-', 50));
output("Starting simulation... (Ctrl+C to stop)");
output("");

foreach ($waypoints as $i => $wp) {
    sendGpsUpdate($vehicleId, $wp['lat'], $wp['lng'], $speed);

    $pct = round(($i / (count($waypoints) - 1)) * 100);
    output("[{$pct}%] Step " . ($i + 1) . "/" . count($waypoints)
        . " | Lat: " . number_format($wp['lat'], 6)
        . " | Lng: " . number_format($wp['lng'], 6)
        . " | Near: " . $wp['nearest_stop']);

    if ($i < count($waypoints) - 1) {
        sleep($interval);
    }
}

// Deactivate GPS after simulation
$db->prepare("UPDATE vehicles SET gps_active = 0 WHERE vehicle_id = :id")
    ->execute([':id' => $vehicleId]);

output("");
output("Simulation complete. GPS deactivated.");


/* ══════════════════════════════════════════════════════════
 *  HELPER FUNCTIONS
 * ══════════════════════════════════════════════════════════ */

/**
 * Generate interpolated waypoints between stops.
 * Creates points every $stepKm km along the path.
 */
function generateWaypoints(array $stops, float $stepKm): array
{
    $waypoints = [];

    for ($i = 0; $i < count($stops) - 1; $i++) {
        $fromLat = (float) $stops[$i]['latitude'];
        $fromLng = (float) $stops[$i]['longitude'];
        $toLat = (float) $stops[$i + 1]['latitude'];
        $toLng = (float) $stops[$i + 1]['longitude'];
        $fromName = $stops[$i]['name'];
        $toName = $stops[$i + 1]['name'];

        $dist = haversineSim($fromLat, $fromLng, $toLat, $toLng);
        $numSteps = max(1, (int) ceil($dist / $stepKm));

        for ($s = 0; $s <= $numSteps; $s++) {
            // Don't duplicate the start of next segment
            if ($s === 0 && $i > 0)
                continue;

            $t = $numSteps > 0 ? $s / $numSteps : 0;
            $lat = $fromLat + ($toLat - $fromLat) * $t;
            $lng = $fromLng + ($toLng - $fromLng) * $t;

            // Determine nearest stop name
            $nearestStop = ($t < 0.5) ? $fromName : $toName;

            $waypoints[] = [
                'lat' => round($lat, 8),
                'lng' => round($lng, 8),
                'nearest_stop' => $nearestStop,
                'segment' => $i
            ];
        }
    }

    return $waypoints;
}

/**
 * Send GPS update to the vehicles API.
 */
function sendGpsUpdate(int $vehicleId, float $lat, float $lng, float $speed): void
{
    global $db;

    $stmt = $db->prepare("UPDATE vehicles
                          SET latitude = :lat, longitude = :lng, velocity = :vel,
                              gps_active = 1, last_gps_update = NOW()
                          WHERE vehicle_id = :id");
    $stmt->execute([
        ':lat' => $lat,
        ':lng' => $lng,
        ':vel' => $speed,
        ':id' => $vehicleId
    ]);
}

/**
 * Haversine distance between two coordinates (km).
 */
function haversineSim(float $lat1, float $lng1, float $lat2, float $lng2): float
{
    $R = 6371;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) ** 2
        + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
        * sin($dLng / 2) ** 2;
    return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

/**
 * Output helper — handles CLI vs browser.
 */
function output(string $msg, bool $isError = false): void
{
    global $isCli;

    if ($isCli) {
        if ($isError) {
            fwrite(STDERR, $msg . PHP_EOL);
            exit(1);
        }
        echo $msg . PHP_EOL;
    } else {
        if ($isError) {
            echo json_encode(['success' => false, 'error' => $msg]);
            exit;
        }
        echo $msg . "<br>";
    }
}
