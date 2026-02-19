<?php
/**
 * SAWARI — Routing Engine
 * 
 * Core pathfinding logic for route resolution.
 * - Find nearest stops to given coordinates
 * - Direct route detection via location_list matching
 * - A* algorithm for multi-route transfers
 * - Returns structured route result (stops, vehicle, fare, directions)
 *
 * Actions:
 *   find-route – GET – Main route finding endpoint
 */

require_once __DIR__ . '/config.php';

$action = getAction();

switch ($action) {

    /* ══════════════════════════════════════════════════════
     *  FIND ROUTE
     *  Given origin (lat,lng) and destination (lat,lng),
     *  finds the best bus route(s) connecting them.
     * ══════════════════════════════════════════════════════ */
    case 'find-route':
        $db = getDB();

        $originLat = isset($_GET['origin_lat']) ? floatval($_GET['origin_lat']) : null;
        $originLng = isset($_GET['origin_lng']) ? floatval($_GET['origin_lng']) : null;
        $destLat = isset($_GET['dest_lat']) ? floatval($_GET['dest_lat']) : null;
        $destLng = isset($_GET['dest_lng']) ? floatval($_GET['dest_lng']) : null;

        if (!$originLat || !$originLng || !$destLat || !$destLng) {
            jsonError('Origin and destination coordinates are required.');
        }

        // ----- Step 1: Find nearest approved stops to origin & destination -----
        $nearRadius = 2.0; // km — search radius for nearby stops

        $nearOrigin = findNearbyStops($db, $originLat, $originLng, $nearRadius);
        $nearDest = findNearbyStops($db, $destLat, $destLng, $nearRadius);

        if (empty($nearOrigin)) {
            jsonResponse([
                'success' => false,
                'error' => 'No bus stops found near your starting point. Try a location closer to a main road.',
                'type' => 'no_origin_stops'
            ]);
        }

        if (empty($nearDest)) {
            jsonResponse([
                'success' => false,
                'error' => 'No bus stops found near your destination. Try a location closer to a main road.',
                'type' => 'no_dest_stops'
            ]);
        }

        // ----- Step 2: Load all approved routes with parsed stop lists -----
        $routes = loadApprovedRoutes($db);

        if (empty($routes)) {
            jsonResponse([
                'success' => false,
                'error' => 'No approved routes available yet.',
                'type' => 'no_routes'
            ]);
        }

        // ----- Step 3: Try direct routes first -----
        $directResults = findDirectRoutes($db, $nearOrigin, $nearDest, $routes);

        if (!empty($directResults)) {
            // Sort by total walking distance (origin walk + dest walk)
            usort($directResults, function ($a, $b) {
                return ($a['walk_to_boarding'] + $a['walk_from_dropoff'])
                    - ($b['walk_to_boarding'] + $b['walk_from_dropoff']);
            });

            jsonResponse([
                'success' => true,
                'type' => 'direct',
                'results' => array_slice($directResults, 0, 3), // top 3 options
                'origin' => ['lat' => $originLat, 'lng' => $originLng],
                'destination' => ['lat' => $destLat, 'lng' => $destLng]
            ]);
        }

        // ----- Step 4: Try transfer routes (A*-inspired) -----
        $transferResults = findTransferRoutes($db, $nearOrigin, $nearDest, $routes);

        if (!empty($transferResults)) {
            usort($transferResults, function ($a, $b) {
                return $a['total_distance'] - $b['total_distance'];
            });

            jsonResponse([
                'success' => true,
                'type' => 'transfer',
                'results' => array_slice($transferResults, 0, 3),
                'origin' => ['lat' => $originLat, 'lng' => $originLng],
                'destination' => ['lat' => $destLat, 'lng' => $destLng]
            ]);
        }

        // ----- Step 5: No route found -----
        jsonResponse([
            'success' => false,
            'error' => 'Sorry, we could not find a bus route connecting those locations. The stops may not be on any available route yet.',
            'type' => 'no_route_found',
            'nearby_origin' => array_slice($nearOrigin, 0, 3),
            'nearby_dest' => array_slice($nearDest, 0, 3)
        ]);
        break;

    default:
        jsonError('Unknown action.', 400);
}


/* ══════════════════════════════════════════════════════════
 *  HELPER FUNCTIONS
 * ══════════════════════════════════════════════════════════ */

/**
 * Find approved stops near a coordinate using Haversine formula.
 * Returns array of stops sorted by distance, each with distance_km.
 */
function findNearbyStops(PDO $db, float $lat, float $lng, float $radiusKm): array
{
    $sql = "SELECT location_id, name, latitude, longitude, type,
                   (6371 * acos(
                       cos(radians(:lat1)) * cos(radians(latitude))
                       * cos(radians(longitude) - radians(:lng1))
                       + sin(radians(:lat2)) * sin(radians(latitude))
                   )) AS distance_km
            FROM locations
            WHERE status = 'approved'
            HAVING distance_km < :radius
            ORDER BY distance_km ASC
            LIMIT 15";

    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':lat1' => $lat,
        ':lng1' => $lng,
        ':lat2' => $lat,
        ':radius' => $radiusKm
    ]);

    $stops = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Convert all numeric strings
    foreach ($stops as &$s) {
        $s['latitude'] = (float) $s['latitude'];
        $s['longitude'] = (float) $s['longitude'];
        $s['distance_km'] = (float) $s['distance_km'];
    }
    unset($s);

    return $stops;
}

/**
 * Load all approved routes with parsed location_list.
 */
function loadApprovedRoutes(PDO $db): array
{
    $stmt = $db->prepare("SELECT route_id, name, description, location_list,
                                 fare_base, fare_per_km
                          FROM routes
                          WHERE status = 'approved'");
    $stmt->execute();
    $routes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($routes as &$r) {
        $r['stops'] = $r['location_list'] ? json_decode($r['location_list'], true) : [];
        $r['fare_base'] = $r['fare_base'] ? (float) $r['fare_base'] : null;
        $r['fare_per_km'] = $r['fare_per_km'] ? (float) $r['fare_per_km'] : null;
        unset($r['location_list']);
    }
    unset($r);

    return $routes;
}

/**
 * Get vehicles assigned to a route.
 */
function getRouteVehicles(PDO $db, int $routeId): array
{
    $stmt = $db->prepare("SELECT vehicle_id, name, image_path, electric, starts_at, stops_at
                          FROM vehicles
                          WHERE status = 'approved'
                            AND JSON_CONTAINS(used_routes, :rid)");
    $stmt->execute([':rid' => json_encode($routeId)]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Calculate Haversine distance between two coordinates in km.
 */
function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
{
    $R = 6371; // Earth's radius in km
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) * sin($dLat / 2)
        + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
        * sin($dLng / 2) * sin($dLng / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $R * $c;
}

/**
 * Calculate total distance along a sequence of stops (km).
 */
function routeSegmentDistance(array $stops, int $fromIdx, int $toIdx): float
{
    $dist = 0;
    $start = min($fromIdx, $toIdx);
    $end = max($fromIdx, $toIdx);

    for ($i = $start; $i < $end; $i++) {
        $dist += haversine(
            (float) $stops[$i]['latitude'],
            (float) $stops[$i]['longitude'],
            (float) $stops[$i + 1]['latitude'],
            (float) $stops[$i + 1]['longitude']
        );
    }

    return $dist;
}

/**
 * Calculate fare for a route segment (Nepal fare rules).
 * - Minimum fare: 20 NPR
 * - Fare is rounded to the nearest multiple of 5
 */
function calculateFare(?float $fareBase, ?float $farePerKm, float $distKm): ?float
{
    if (!$fareBase)
        return null;
    $fare = $fareBase;
    if ($farePerKm && $distKm > 0) {
        $fare += $farePerKm * $distKm;
    }
    // Round to nearest multiple of 5
    $fare = round($fare / 5) * 5;
    // Minimum fare 20 NPR
    if ($fare < 20)
        $fare = 20;
    return (float) $fare;
}

/**
 * Find direct routes — where both origin stop and dest stop exist on the same route.
 */
function findDirectRoutes(PDO $db, array $nearOrigin, array $nearDest, array $routes): array
{
    $results = [];

    foreach ($routes as $route) {
        $stops = $route['stops'];
        if (count($stops) < 2)
            continue;

        // Build a lookup: location_id → index in this route
        $stopIndex = [];
        foreach ($stops as $idx => $s) {
            $lid = (int) $s['location_id'];
            $stopIndex[$lid] = $idx;
        }

        // Check every combination of nearOrigin × nearDest
        foreach ($nearOrigin as $oStop) {
            $oId = (int) $oStop['location_id'];
            if (!isset($stopIndex[$oId]))
                continue;
            $oIdx = $stopIndex[$oId];

            foreach ($nearDest as $dStop) {
                $dId = (int) $dStop['location_id'];
                if (!isset($stopIndex[$dId]))
                    continue;
                $dIdx = $stopIndex[$dId];

                // Direction check: boarding index must be before destination index
                if ($oIdx >= $dIdx)
                    continue;

                // Build the segment of stops from boarding to destination
                $segmentStops = array_slice($stops, $oIdx, $dIdx - $oIdx + 1);
                $segmentDist = routeSegmentDistance($stops, $oIdx, $dIdx);
                $fare = calculateFare($route['fare_base'], $route['fare_per_km'], $segmentDist);

                // Get vehicles for this route
                $vehicles = getRouteVehicles($db, (int) $route['route_id']);

                $results[] = [
                    'route_id' => (int) $route['route_id'],
                    'route_name' => $route['name'],
                    'route_description' => $route['description'],
                    'boarding_stop' => [
                        'location_id' => $oId,
                        'name' => $oStop['name'],
                        'lat' => $oStop['latitude'],
                        'lng' => $oStop['longitude'],
                        'type' => $oStop['type']
                    ],
                    'dropoff_stop' => [
                        'location_id' => $dId,
                        'name' => $dStop['name'],
                        'lat' => $dStop['latitude'],
                        'lng' => $dStop['longitude'],
                        'type' => $dStop['type']
                    ],
                    'intermediate_stops' => $segmentStops,
                    'stop_count' => count($segmentStops),
                    'distance_km' => round($segmentDist, 2),
                    'fare' => $fare,
                    'walk_to_boarding' => round($oStop['distance_km'], 3),
                    'walk_from_dropoff' => round($dStop['distance_km'], 3),
                    'vehicles' => $vehicles,
                    'boarding_index' => $oIdx,
                    'dropoff_index' => $dIdx
                ];
            }
        }
    }

    return $results;
}

/**
 * Find transfer routes — use one route to reach a transfer stop,
 * then switch to another route to reach the destination.
 * A*-inspired: explores route graph looking for shared stops between routes.
 */
function findTransferRoutes(PDO $db, array $nearOrigin, array $nearDest, array $routes): array
{
    $results = [];

    // Build route-stop index: for each location_id, list which routes pass through it
    $locationToRoutes = [];
    foreach ($routes as $rIdx => $route) {
        foreach ($route['stops'] as $sIdx => $stop) {
            $lid = (int) $stop['location_id'];
            $locationToRoutes[$lid][] = [
                'route_index' => $rIdx,
                'stop_index' => $sIdx
            ];
        }
    }

    // For each origin stop on route A, for each dest stop on route B,
    // find a shared transfer stop
    foreach ($routes as $rAIdx => $routeA) {
        $stopsA = $routeA['stops'];
        $stopIndexA = [];
        foreach ($stopsA as $idx => $s) {
            $stopIndexA[(int) $s['location_id']] = $idx;
        }

        // Check if any nearOrigin stop is on routeA
        foreach ($nearOrigin as $oStop) {
            $oId = (int) $oStop['location_id'];
            if (!isset($stopIndexA[$oId]))
                continue;
            $boardIdx = $stopIndexA[$oId];

            // Now look at every stop AFTER boardIdx on routeA — these are potential transfer points
            for ($tIdx = $boardIdx + 1; $tIdx < count($stopsA); $tIdx++) {
                $transferLid = (int) $stopsA[$tIdx]['location_id'];

                // Check if this transfer location also appears on any other route
                if (!isset($locationToRoutes[$transferLid]))
                    continue;

                foreach ($locationToRoutes[$transferLid] as $entry) {
                    $rBIdx = $entry['route_index'];
                    if ($rBIdx === $rAIdx)
                        continue; // skip same route

                    $routeB = $routes[$rBIdx];
                    $stopsB = $routeB['stops'];
                    $transferIdxB = $entry['stop_index'];

                    // On routeB, check if any nearDest stop appears AFTER the transfer point
                    foreach ($nearDest as $dStop) {
                        $dId = (int) $dStop['location_id'];

                        // Find dId in routeB's stops
                        $destIdxB = null;
                        foreach ($stopsB as $bIdx => $bs) {
                            if ((int) $bs['location_id'] === $dId) {
                                $destIdxB = $bIdx;
                                break;
                            }
                        }

                        if ($destIdxB === null || $destIdxB <= $transferIdxB)
                            continue;

                        // Valid transfer route found!
                        $segA = array_slice($stopsA, $boardIdx, $tIdx - $boardIdx + 1);
                        $segB = array_slice($stopsB, $transferIdxB, $destIdxB - $transferIdxB + 1);

                        $distA = routeSegmentDistance($stopsA, $boardIdx, $tIdx);
                        $distB = routeSegmentDistance($stopsB, $transferIdxB, $destIdxB);

                        $fareA = calculateFare($routeA['fare_base'], $routeA['fare_per_km'], $distA);
                        $fareB = calculateFare($routeB['fare_base'], $routeB['fare_per_km'], $distB);

                        $vehiclesA = getRouteVehicles($db, (int) $routeA['route_id']);
                        $vehiclesB = getRouteVehicles($db, (int) $routeB['route_id']);

                        $results[] = [
                            'leg1' => [
                                'route_id' => (int) $routeA['route_id'],
                                'route_name' => $routeA['name'],
                                'boarding_stop' => [
                                    'location_id' => $oId,
                                    'name' => $oStop['name'],
                                    'lat' => $oStop['latitude'],
                                    'lng' => $oStop['longitude']
                                ],
                                'dropoff_stop' => [
                                    'location_id' => $transferLid,
                                    'name' => $stopsA[$tIdx]['name'],
                                    'lat' => (float) $stopsA[$tIdx]['latitude'],
                                    'lng' => (float) $stopsA[$tIdx]['longitude']
                                ],
                                'intermediate_stops' => $segA,
                                'distance_km' => round($distA, 2),
                                'fare' => $fareA,
                                'vehicles' => $vehiclesA
                            ],
                            'transfer_stop' => [
                                'location_id' => $transferLid,
                                'name' => $stopsA[$tIdx]['name'],
                                'lat' => (float) $stopsA[$tIdx]['latitude'],
                                'lng' => (float) $stopsA[$tIdx]['longitude']
                            ],
                            'leg2' => [
                                'route_id' => (int) $routeB['route_id'],
                                'route_name' => $routeB['name'],
                                'boarding_stop' => [
                                    'location_id' => $transferLid,
                                    'name' => $stopsB[$transferIdxB]['name'],
                                    'lat' => (float) $stopsB[$transferIdxB]['latitude'],
                                    'lng' => (float) $stopsB[$transferIdxB]['longitude']
                                ],
                                'dropoff_stop' => [
                                    'location_id' => $dId,
                                    'name' => $dStop['name'],
                                    'lat' => $dStop['latitude'],
                                    'lng' => $dStop['longitude']
                                ],
                                'intermediate_stops' => $segB,
                                'distance_km' => round($distB, 2),
                                'fare' => $fareB,
                                'vehicles' => $vehiclesB
                            ],
                            'total_distance' => round($distA + $distB, 2),
                            'total_fare' => ($fareA !== null && $fareB !== null) ? round($fareA + $fareB, 2) : null,
                            'walk_to_boarding' => round($oStop['distance_km'], 3),
                            'walk_from_dropoff' => round($dStop['distance_km'], 3)
                        ];
                    }
                }
            }
        }
    }

    // Deduplicate: keep only unique transfer-stop combinations
    $seen = [];
    $unique = [];
    foreach ($results as $r) {
        $key = $r['leg1']['route_id'] . '-' . $r['transfer_stop']['location_id'] . '-' . $r['leg2']['route_id'];
        if (!isset($seen[$key])) {
            $seen[$key] = true;
            $unique[] = $r;
        }
    }

    return $unique;
}
