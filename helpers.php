<?php
/**
 * Algorithm Helper Functions — Sawari
 * 
 * Utility functions used by the route-finding engine:
 * - Haversine distance calculation
 * - Nearest stop finder
 * - Fare calculation
 * - Walking directions (OSRM with fallback)
 * - Wait time estimation
 */

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';

/**
 * Calculate the Haversine distance between two geographic points.
 *
 * Uses the spherical law of cosines via the Haversine formula,
 * which is accurate enough for distances in the Kathmandu Valley
 * (typically < 30 km).
 *
 * @param float $lat1 Latitude of point 1 (degrees)
 * @param float $lng1 Longitude of point 1 (degrees)
 * @param float $lat2 Latitude of point 2 (degrees)
 * @param float $lng2 Longitude of point 2 (degrees)
 * @return float Distance in kilometres
 */
function haversineDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
{
    $earthRadiusKm = 6371.0;

    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);

    $a = sin($dLat / 2) ** 2
        + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

    return $earthRadiusKm * $c;
}

/**
 * Find the nearest approved stops/landmarks to a given coordinate.
 *
 * Uses the Haversine formula in SQL for efficient distance filtering.
 *
 * @param float $lat  Latitude (degrees)
 * @param float $lng  Longitude (degrees)
 * @param float $radiusKm  Search radius in km (default from constant)
 * @param int   $limit     Maximum results to return
 * @return array  Array of locations ordered by distance (nearest first),
 *                each with location_id, name, latitude, longitude, type, distance_km
 */
function findNearestStops(float $lat, float $lng, float $radiusKm = NEAREST_STOP_RADIUS_KM, int $limit = 5): array
{
    $db = getDBConnection();

    // Haversine formula in SQL — calculates distance in km
    $sql = "
        SELECT
            location_id, name, latitude, longitude, type,
            (
                6371.0 * acos(
                    LEAST(1.0, GREATEST(-1.0,
                        cos(radians(:lat1)) * cos(radians(latitude)) *
                        cos(radians(longitude) - radians(:lng1))
                        + sin(radians(:lat2)) * sin(radians(latitude))
                    ))
                )
            ) AS distance_km
        FROM locations
        WHERE status = 'approved'
        HAVING distance_km <= :radius
        ORDER BY distance_km ASC
        LIMIT :lim
    ";

    $stmt = $db->prepare($sql);
    $stmt->bindValue(':lat1', $lat, PDO::PARAM_STR);
    $stmt->bindValue(':lng1', $lng, PDO::PARAM_STR);
    $stmt->bindValue(':lat2', $lat, PDO::PARAM_STR);
    $stmt->bindValue(':radius', $radiusKm, PDO::PARAM_STR);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();

    $results = $stmt->fetchAll();

    // Cast numeric types
    foreach ($results as &$row) {
        $row['location_id'] = (int) $row['location_id'];
        $row['latitude'] = (float) $row['latitude'];
        $row['longitude'] = (float) $row['longitude'];
        $row['distance_km'] = round((float) $row['distance_km'], 3);
    }

    return $results;
}

/**
 * Calculate fare for a bus ride segment.
 *
 * Formula:
 *   fare = FARE_BASE_RATE + (distanceKm × FARE_PER_KM)
 *   Round UP to the nearest FARE_ROUND_TO (default 5 NPR)
 *   Apply discount for student/elderly passengers
 *
 * @param float  $distanceKm    Distance of the segment in km
 * @param string $passengerType  'regular', 'student', or 'elderly'
 * @return int  Fare in NPR (integer)
 */
function calculateFare(float $distanceKm, string $passengerType = 'regular'): int
{
    // Base calculation
    $fare = FARE_BASE_RATE + ($distanceKm * FARE_PER_KM);

    // Round UP to nearest FARE_ROUND_TO
    $fare = ceil($fare / FARE_ROUND_TO) * FARE_ROUND_TO;

    // Apply discount
    if ($passengerType === 'student') {
        $fare *= (1 - STUDENT_DISCOUNT);
    } elseif ($passengerType === 'elderly') {
        $fare *= (1 - ELDERLY_DISCOUNT);
    }

    return max(1, (int) round($fare));
}

/**
 * Get walking directions between two points using the OSRM public API.
 *
 * Calls the foot-routing profile on OSRM and returns distance, duration,
 * and geometry (as [lat, lng] pairs for Leaflet).
 *
 * Falls back to straight-line Haversine + estimated walk time on failure.
 *
 * @param float $fromLat  Start latitude
 * @param float $fromLng  Start longitude
 * @param float $toLat    End latitude
 * @param float $toLng    End longitude
 * @return array  Walking segment data: distance_m, duration_min, geometry, source
 */
function getWalkingDirections(float $fromLat, float $fromLng, float $toLat, float $toLng): array
{
    // Always compute fallback values first
    $straightLineKm = haversineDistance($fromLat, $fromLng, $toLat, $toLng);
    $straightLineM = (int) round($straightLineKm * 1000);
    $walkTimeMin = max(1, (int) round(($straightLineKm / WALK_SPEED_KMH) * 60));

    // Attempt OSRM API call
    // OSRM coordinate order: lng,lat (NOT lat,lng!)
    $url = OSRM_API_URL . sprintf(
        '/route/v1/foot/%.6f,%.6f;%.6f,%.6f?overview=full&geometries=geojson',
        $fromLng,
        $fromLat,
        $toLng,
        $toLat
    );

    $context = stream_context_create([
        'http' => [
            'timeout' => 5,
            'ignore_errors' => true,
            'method' => 'GET',
            'header' => "User-Agent: Sawari/1.0\r\n",
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ],
    ]);

    $response = @file_get_contents($url, false, $context);

    if ($response !== false) {
        $data = json_decode($response, true);

        if (
            isset($data['code']) && $data['code'] === 'Ok'
            && !empty($data['routes'][0])
        ) {
            $route = $data['routes'][0];

            $distanceM = (int) round($route['distance']);           // metres
            $durationMin = max(1, (int) round($route['duration'] / 60)); // seconds → minutes

            // Convert GeoJSON [lng, lat] → [lat, lng] for Leaflet
            $geometry = [];
            if (isset($route['geometry']['coordinates'])) {
                foreach ($route['geometry']['coordinates'] as $coord) {
                    $geometry[] = [(float) $coord[1], (float) $coord[0]];
                }
            }

            return [
                'distance_m' => $distanceM,
                'duration_min' => $durationMin,
                'geometry' => $geometry,
                'source' => 'osrm',
            ];
        }
    }

    // Fallback: straight-line estimate
    return [
        'distance_m' => $straightLineM,
        'duration_min' => $walkTimeMin,
        'geometry' => [[$fromLat, $fromLng], [$toLat, $toLng]],
        'source' => 'fallback',
    ];
}

/**
 * Get road-following geometry between a sequence of stops using OSRM driving API.
 *
 * Sends all waypoints in a single OSRM request so the returned geometry
 * traces the actual roads on OpenStreetMap between the bus stops.
 *
 * IMPORTANT — Route-finding decisions are made ENTIRELY from stored DB data
 * (Dijkstra on the route graph). OSRM is used ONLY here — to fetch the
 * realistic road geometry between each pair of adjacent stops so the
 * polyline on the map traces actual roads instead of straight lines.
 *
 * Strategy:
 *   For each pair of consecutive stops (A→B), make an individual OSRM
 *   driving request. This avoids the problem where a single multi-waypoint
 *   request with closely-spaced stops causes OSRM to route through wrong
 *   roads or create U-turn loops.
 *
 * @param array $stops  Array of [lat, lng] pairs (the bus stops in order)
 * @return array  Array of [lat, lng] pairs tracing the road through all stops
 */
function getDrivingRouteGeometry(array $stops): array
{
    if (count($stops) < 2) {
        return $stops;
    }

    $fullGeometry = [];

    // Process each pair of consecutive stops independently
    for ($i = 0; $i < count($stops) - 1; $i++) {
        $segmentGeometry = getOSRMSegmentGeometry(
            $stops[$i][0],
            $stops[$i][1],
            $stops[$i + 1][0],
            $stops[$i + 1][1]
        );

        if (empty($segmentGeometry)) {
            // Fallback: straight line for this segment
            $segmentGeometry = [$stops[$i], $stops[$i + 1]];
        }

        // Append segment, skipping duplicate junction point
        if (!empty($fullGeometry)) {
            // Remove first point of this segment (same as last point of previous)
            array_shift($segmentGeometry);
        }

        foreach ($segmentGeometry as $point) {
            $fullGeometry[] = $point;
        }
    }

    return !empty($fullGeometry) ? $fullGeometry : $stops;
}

/**
 * Fetch OSRM road geometry between two points (single segment).
 *
 * Makes a simple A→B driving request — no intermediate waypoints,
 * so OSRM always finds the natural shortest road path.
 *
 * @param float $fromLat  Start latitude
 * @param float $fromLng  Start longitude
 * @param float $toLat    End latitude
 * @param float $toLng    End longitude
 * @return array  Array of [lat, lng] pairs, or empty array on failure
 */
function getOSRMSegmentGeometry(float $fromLat, float $fromLng, float $toLat, float $toLng): array
{
    $url = OSRM_API_URL . sprintf(
        '/route/v1/driving/%.6f,%.6f;%.6f,%.6f?overview=full&geometries=geojson&continue_straight=true',
        $fromLng,
        $fromLat,  // OSRM uses lng,lat order
        $toLng,
        $toLat
    );

    $context = stream_context_create([
        'http' => [
            'timeout' => 5,
            'ignore_errors' => true,
            'method' => 'GET',
            'header' => "User-Agent: Sawari/1.0\r\n",
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ],
    ]);

    $response = @file_get_contents($url, false, $context);

    if ($response !== false) {
        $data = json_decode($response, true);

        if (
            isset($data['code']) && $data['code'] === 'Ok'
            && !empty($data['routes'][0]['geometry']['coordinates'])
        ) {
            $geometry = [];
            foreach ($data['routes'][0]['geometry']['coordinates'] as $coord) {
                $geometry[] = [(float) $coord[1], (float) $coord[0]]; // [lat, lng] for Leaflet
            }
            return $geometry;
        }
    }

    return []; // caller handles fallback
}

/**
 * Estimate the wait time (in minutes) for a bus on a given route.
 *
 * Logic:
 *   1. Count total vehicles operating on this route (from vehicles.used_routes JSON)
 *   2. Calculate round-trip time: (2 × routeDistance) / AVG_BUS_SPEED_KMH × 60
 *   3. Frequency = roundTripTime / totalVehicleCount
 *
 * @param int        $routeId          The route to estimate for
 * @param float|null $routeDistanceKm  Pre-calculated route distance (optional)
 * @return int|null  Estimated minutes between buses, or null if unable to estimate
 */
function estimateWaitTime(int $routeId, ?float $routeDistanceKm = null): ?int
{
    if ($routeDistanceKm === null || $routeDistanceKm <= 0) {
        return null;
    }

    $db = getDBConnection();

    // Find all approved vehicles and check if they serve this route
    $stmt = $db->query("SELECT used_routes FROM vehicles WHERE status = 'approved'");
    $vehicles = $stmt->fetchAll();

    $totalCount = 0;

    foreach ($vehicles as $v) {
        $usedRoutes = json_decode($v['used_routes'], true);
        if (!is_array($usedRoutes)) {
            continue;
        }
        foreach ($usedRoutes as $ur) {
            if (isset($ur['route_id']) && (int) $ur['route_id'] === $routeId) {
                $totalCount += (int) ($ur['count'] ?? 1);
            }
        }
    }

    if ($totalCount === 0) {
        return null;
    }

    // Round-trip time in minutes
    $roundTripMin = ($routeDistanceKm * 2 / AVG_BUS_SPEED_KMH) * 60;

    // Frequency = roundTripTime / vehicleCount
    $frequencyMin = $roundTripMin / $totalCount;

    return max(1, (int) round($frequencyMin));
}
