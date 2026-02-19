<?php
/**
 * Pathfinder Orchestrator — Sawari
 *
 * Core function: findRoute($startLocationId, $endLocationId, $passengerType)
 *
 * Orchestrates the full route-finding pipeline:
 *   1. Validate inputs
 *   2. Build the transit graph from STORED DATABASE route data
 *   3. Handle locations that are not directly on any route (walk to nearest)
 *   4. Run modified Dijkstra on the DB-sourced graph
 *   5. Parse path into human-readable segments (walking / riding / transfer)
 *   6. Enrich with vehicle info, fares, alerts, conductor instructions
 *   7. Return the complete response JSON structure
 *
 * ARCHITECTURE NOTE — OSRM's role is strictly LIMITED:
 *   - ALL routing decisions (which bus to take, where to transfer, optimal path)
 *     are computed from the stored route data in the database using Dijkstra.
 *   - OSRM is used ONLY for:
 *     (a) Walking directions — foot-profile routing from user to nearest stop
 *     (b) Driving geometry — road-following polylines BETWEEN consecutive stops
 *         for map display (not for routing decisions)
 *   - OSRM calls are per-segment (stop A → stop B) to prevent routing artifacts.
 *   - If OSRM is unavailable, the system falls back to straight-line geometry
 *     without affecting route-finding correctness.
 */

require_once __DIR__ . '/graph.php';
require_once __DIR__ . '/dijkstra.php';
require_once __DIR__ . '/helpers.php';

/**
 * Main route-finding function.
 *
 * @param int        $startLocationId  Starting location_id (nearest bus stop)
 * @param int        $endLocationId    Destination location_id (nearest bus stop)
 * @param string     $passengerType    'regular', 'student', or 'elderly'
 * @param float|null $actualStartLat   User's actual start latitude (for walking guidance)
 * @param float|null $actualStartLng   User's actual start longitude
 * @param float|null $actualEndLat     User's actual destination latitude
 * @param float|null $actualEndLng     User's actual destination longitude
 * @return array  Full response structure (see workflow.md Phase 6.4)
 */
function findRoute(
    int $startLocationId,
    int $endLocationId,
    string $passengerType = 'regular',
    ?float $actualStartLat = null,
    ?float $actualStartLng = null,
    ?float $actualEndLat = null,
    ?float $actualEndLng = null
): array {
    // ─── 1. Same-location check ─────────────────────────────
    if ($startLocationId === $endLocationId) {
        return [
            'success' => false,
            'message' => 'Start and destination are the same location.',
        ];
    }

    // ─── 2. Build the transit graph ─────────────────────────
    $graphData = buildTransitGraph();
    $graph = $graphData['graph'];
    $locationCoords = $graphData['locationCoords'];
    $routeDetails = $graphData['routeDetails'];

    // ─── 3. Validate both locations exist ───────────────────
    if (!isset($locationCoords[$startLocationId])) {
        return [
            'success' => false,
            'message' => 'Starting location not found or not approved.',
        ];
    }
    if (!isset($locationCoords[$endLocationId])) {
        return [
            'success' => false,
            'message' => 'Destination location not found or not approved.',
        ];
    }

    // ─── 4. Handle locations not on any route ───────────────
    // If a location has no edges in the graph (e.g. an isolated landmark),
    // find the nearest reachable stop and add a walking segment.
    $actualStartId = $startLocationId;
    $actualEndId = $endLocationId;
    $walkToStart = null;  // walking segment to add BEFORE the ride
    $walkFromEnd = null;  // walking segment to add AFTER the ride

    if (!isset($graph[$startLocationId])) {
        $nearest = findNearestInGraph(
            $locationCoords[$startLocationId]['lat'],
            $locationCoords[$startLocationId]['lng'],
            $graph,
            $locationCoords
        );

        if ($nearest === null) {
            return [
                'success' => false,
                'message' => 'No bus routes found near your starting point.',
            ];
        }

        $actualStartId = $nearest['location_id'];
        $walkToStart = getWalkingDirections(
            $locationCoords[$startLocationId]['lat'],
            $locationCoords[$startLocationId]['lng'],
            $locationCoords[$actualStartId]['lat'],
            $locationCoords[$actualStartId]['lng']
        );
        $walkToStart['from_name'] = $locationCoords[$startLocationId]['name'];
        $walkToStart['to_name'] = $locationCoords[$actualStartId]['name'];
        $walkToStart['from_id'] = $startLocationId;
        $walkToStart['to_id'] = $actualStartId;
    }

    if (!isset($graph[$endLocationId])) {
        $nearest = findNearestInGraph(
            $locationCoords[$endLocationId]['lat'],
            $locationCoords[$endLocationId]['lng'],
            $graph,
            $locationCoords
        );

        if ($nearest === null) {
            return [
                'success' => false,
                'message' => 'No bus routes found near your destination.',
            ];
        }

        $actualEndId = $nearest['location_id'];
        $walkFromEnd = getWalkingDirections(
            $locationCoords[$actualEndId]['lat'],
            $locationCoords[$actualEndId]['lng'],
            $locationCoords[$endLocationId]['lat'],
            $locationCoords[$endLocationId]['lng']
        );
        $walkFromEnd['from_name'] = $locationCoords[$actualEndId]['name'];
        $walkFromEnd['to_name'] = $locationCoords[$endLocationId]['name'];
        $walkFromEnd['from_id'] = $actualEndId;
        $walkFromEnd['to_id'] = $endLocationId;
    }

    // ─── 5. Run Dijkstra ────────────────────────────────────
    $result = findShortestPath($graph, $actualStartId, $actualEndId);

    if ($result === null) {
        // No route found — suggest nearby connected stops
        $nearbyStart = findNearestInGraph(
            $locationCoords[$startLocationId]['lat'],
            $locationCoords[$startLocationId]['lng'],
            $graph,
            $locationCoords,
            5
        );
        $nearbyEnd = findNearestInGraph(
            $locationCoords[$endLocationId]['lat'],
            $locationCoords[$endLocationId]['lng'],
            $graph,
            $locationCoords,
            5
        );

        return [
            'success' => false,
            'message' => 'No route found between these locations. They may not be connected by any bus route.',
            'nearby_start' => $nearbyStart ? [$nearbyStart] : [],
            'nearby_end' => $nearbyEnd ? [$nearbyEnd] : [],
        ];
    }

    // ─── 6. Parse path into segments ────────────────────────
    $path = $result['path'];
    $segments = parsePathIntoSegments($path, $locationCoords, $routeDetails, $passengerType);

    // Prepend walking-to-start segment if needed (location not on any route)
    if ($walkToStart !== null) {
        array_unshift($segments, buildWalkingSegment($walkToStart, $locationCoords, $startLocationId, $actualStartId));
    }

    // Append walking-from-end segment if needed (location not on any route)
    if ($walkFromEnd !== null) {
        $segments[] = buildWalkingSegment($walkFromEnd, $locationCoords, $actualEndId, $endLocationId);
    }

    // ─── 6b. Add walking from user's actual position ────────
    // When the user provides their exact coordinates (geolocation / map click),
    // these may differ from the nearest bus stop. Add walking segments so the
    // user sees: "Walk from your location → nearest bus stop" at the start,
    // and "Walk from last bus stop → your destination" at the end.

    // Determine the first boarding stop
    $firstBoardingId = $actualStartId;
    // Determine the last alighting stop
    $lastAlightingId = $actualEndId;

    // Walk from actual start position → first boarding stop
    if ($actualStartLat !== null && $actualStartLng !== null) {
        $stopLat = $locationCoords[$firstBoardingId]['lat'];
        $stopLng = $locationCoords[$firstBoardingId]['lng'];
        $distToStop = haversineDistance($actualStartLat, $actualStartLng, $stopLat, $stopLng);

        // Only add if the user is meaningfully far from the stop (> 30 metres)
        if ($distToStop > 0.03) {
            $walkToStop = getWalkingDirections($actualStartLat, $actualStartLng, $stopLat, $stopLng);
            $walkSeg = [
                'type' => 'walking',
                'from' => [
                    'name' => 'Your Location',
                    'location_id' => null,
                    'lat' => $actualStartLat,
                    'lng' => $actualStartLng,
                ],
                'to' => [
                    'name' => $locationCoords[$firstBoardingId]['name'],
                    'location_id' => $firstBoardingId,
                    'lat' => $stopLat,
                    'lng' => $stopLng,
                ],
                'distance_m' => $walkToStop['distance_m'],
                'duration_min' => $walkToStop['duration_min'],
                'directions' => 'Walk ' . $walkToStop['distance_m'] . 'm to ' . $locationCoords[$firstBoardingId]['name'] . ' bus stop',
                'geometry' => $walkToStop['geometry'] ?? [],
            ];
            array_unshift($segments, $walkSeg);
        }
    }

    // Walk from last alighting stop → actual destination
    if ($actualEndLat !== null && $actualEndLng !== null) {
        $stopLat = $locationCoords[$lastAlightingId]['lat'];
        $stopLng = $locationCoords[$lastAlightingId]['lng'];
        $distFromStop = haversineDistance($stopLat, $stopLng, $actualEndLat, $actualEndLng);

        // Only add if meaningfully far (> 30 metres)
        if ($distFromStop > 0.03) {
            $walkFromStop = getWalkingDirections($stopLat, $stopLng, $actualEndLat, $actualEndLng);
            $walkSeg = [
                'type' => 'walking',
                'from' => [
                    'name' => $locationCoords[$lastAlightingId]['name'],
                    'location_id' => $lastAlightingId,
                    'lat' => $stopLat,
                    'lng' => $stopLng,
                ],
                'to' => [
                    'name' => 'Your Destination',
                    'location_id' => null,
                    'lat' => $actualEndLat,
                    'lng' => $actualEndLng,
                ],
                'distance_m' => $walkFromStop['distance_m'],
                'duration_min' => $walkFromStop['duration_min'],
                'directions' => 'Walk ' . $walkFromStop['distance_m'] . 'm to your destination',
                'geometry' => $walkFromStop['geometry'] ?? [],
            ];
            $segments[] = $walkSeg;
        }
    }

    // ─── 7. Collect route IDs used in this path ─────────────
    $routeIdsUsed = [];
    foreach ($path as $step) {
        if ($step['route_id'] !== 0) {
            $routeIdsUsed[$step['route_id']] = true;
        }
    }
    $routeIdsUsed = array_keys($routeIdsUsed);

    // ─── 8. Check for active alerts ─────────────────────────
    $alerts = getActiveAlertsForRoutes($routeIdsUsed);

    // ─── 9. Calculate summary ───────────────────────────────
    $totalFare = 0;
    $totalDistance = $result['total_distance'];
    $totalDuration = 0;
    $transferCount = 0;

    foreach ($segments as $seg) {
        if ($seg['type'] === 'riding') {
            $totalFare += $seg['fare'] ?? 0;
            $totalDuration += ($seg['distance_km'] ?? 0) / AVG_BUS_SPEED_KMH * 60;
        } elseif ($seg['type'] === 'walking') {
            $totalDuration += $seg['duration_min'] ?? 0;
            $totalDistance += ($seg['distance_m'] ?? 0) / 1000;
        } elseif ($seg['type'] === 'transfer') {
            $transferCount++;
            // Add estimated wait time at transfer point
            if (isset($seg['wait_time_min']) && $seg['wait_time_min'] !== null) {
                $totalDuration += $seg['wait_time_min'];
            } else {
                $totalDuration += 5; // Default 5 min wait estimate
            }
        }
    }

    // ─── 10. Build response ─────────────────────────────────
    return [
        'success' => true,
        'summary' => [
            'total_distance_km' => round($totalDistance, 1),
            'total_fare' => $totalFare,
            'estimated_duration_min' => max(1, (int) round($totalDuration)),
            'transfers' => $transferCount,
            'alerts' => $alerts,
        ],
        'segments' => $segments,
        'routes_used' => $routeIdsUsed,
    ];
}

// ═════════════════════════════════════════════════════════════
// Internal helper functions
// ═════════════════════════════════════════════════════════════

/**
 * Find the single nearest location that HAS edges in the graph.
 *
 * @return array|null  ['location_id'=>int, 'distance_km'=>float, 'name'=>string] or null
 */
function findNearestInGraph(float $lat, float $lng, array $graph, array $locationCoords, int $limit = 1)
{
    $candidates = [];

    foreach (array_keys($graph) as $locId) {
        if (!isset($locationCoords[$locId])) {
            continue;
        }
        $dist = haversineDistance(
            $lat,
            $lng,
            $locationCoords[$locId]['lat'],
            $locationCoords[$locId]['lng']
        );
        $candidates[] = [
            'location_id' => $locId,
            'distance_km' => round($dist, 3),
            'name' => $locationCoords[$locId]['name'],
        ];
    }

    if (empty($candidates)) {
        return null;
    }

    usort($candidates, fn($a, $b) => $a['distance_km'] <=> $b['distance_km']);

    return $limit === 1 ? $candidates[0] : array_slice($candidates, 0, $limit);
}

/**
 * Parse the raw Dijkstra path into human-readable segments.
 *
 * Groups consecutive steps by route_id into riding segments,
 * and inserts transfer segments at route-change points.
 *
 * @param array  $path           Array of ['location_id' => int, 'route_id' => int]
 * @param array  $locationCoords Coordinate lookup
 * @param array  $routeDetails   Route metadata
 * @param string $passengerType  For fare calculation
 * @return array  Array of segment objects
 */
function parsePathIntoSegments(array $path, array $locationCoords, array $routeDetails, string $passengerType): array
{
    if (count($path) < 2) {
        return [];
    }

    $segments = [];
    $currentRouteId = null;
    $currentStopIds = [];

    for ($i = 1; $i < count($path); $i++) {
        $prevStep = $path[$i - 1];
        $currStep = $path[$i];
        $routeId = $currStep['route_id'];

        if ($currentRouteId === null) {
            // First edge — start a new riding segment
            $currentRouteId = $routeId;
            $currentStopIds = [$prevStep['location_id'], $currStep['location_id']];

        } elseif ($routeId === $currentRouteId) {
            // Same route — extend current segment
            $currentStopIds[] = $currStep['location_id'];

        } else {
            // Route changed → close current segment, add transfer, start new segment
            $segments[] = buildRidingSegment(
                $currentRouteId,
                $currentStopIds,
                $locationCoords,
                $routeDetails,
                $passengerType
            );

            $segments[] = buildTransferSegment(
                $prevStep['location_id'],
                $locationCoords,
                $currentRouteId,
                $routeId,
                $routeDetails
            );

            $currentRouteId = $routeId;
            $currentStopIds = [$prevStep['location_id'], $currStep['location_id']];
        }
    }

    // Close the last riding segment
    if ($currentRouteId !== null && count($currentStopIds) >= 2) {
        $segments[] = buildRidingSegment(
            $currentRouteId,
            $currentStopIds,
            $locationCoords,
            $routeDetails,
            $passengerType
        );
    }

    return $segments;
}

/**
 * Build a single riding segment from a sequence of stop IDs on one route.
 */
function buildRidingSegment(
    int $routeId,
    array $stopIds,
    array $locationCoords,
    array $routeDetails,
    string $passengerType
): array {
    $fromId = $stopIds[0];
    $toId = $stopIds[count($stopIds) - 1];

    // ── Segment distance (sum of Haversine between consecutive stops)
    $segmentDistKm = 0.0;
    for ($i = 1; $i < count($stopIds); $i++) {
        $segmentDistKm += haversineDistance(
            $locationCoords[$stopIds[$i - 1]]['lat'],
            $locationCoords[$stopIds[$i - 1]]['lng'],
            $locationCoords[$stopIds[$i]]['lat'],
            $locationCoords[$stopIds[$i]]['lng']
        );
    }

    // ── Fare
    $fare = calculateFare($segmentDistKm, $passengerType);

    // ── Vehicle info
    $vehicle = getVehicleForRoute($routeId);

    // ── Path coordinates for the map polyline
    // NOTE: The route itself was already determined by Dijkstra using stored DB data.
    // OSRM is called here ONLY to get road-following geometry for map display.
    // Each pair of consecutive stops gets its own OSRM segment call.
    $rawStopCoords = [];
    foreach ($stopIds as $sid) {
        $rawStopCoords[] = [$locationCoords[$sid]['lat'], $locationCoords[$sid]['lng']];
    }
    $pathCoords = getDrivingRouteGeometry($rawStopCoords);

    // ── Stops in between (excluding boarding and alighting stops)
    $stopsInBetween = [];
    for ($i = 1; $i < count($stopIds) - 1; $i++) {
        $stopsInBetween[] = $locationCoords[$stopIds[$i]]['name'];
    }

    // ── Build segment
    $routeName = $routeDetails[$routeId]['name'] ?? 'Unknown Route';
    $destName = $locationCoords[$toId]['name'];

    $segment = [
        'type' => 'riding',
        'route_id' => $routeId,
        'route_name' => $routeName,
        'vehicle' => $vehicle,
        'from' => [
            'name' => $locationCoords[$fromId]['name'],
            'location_id' => $fromId,
            'lat' => $locationCoords[$fromId]['lat'],
            'lng' => $locationCoords[$fromId]['lng'],
        ],
        'to' => [
            'name' => $destName,
            'location_id' => $toId,
            'lat' => $locationCoords[$toId]['lat'],
            'lng' => $locationCoords[$toId]['lng'],
        ],
        'stops_in_between' => $stopsInBetween,
        'distance_km' => round($segmentDistKm, 2),
        'fare' => $fare,
        'conductor_instruction' => "Tell the conductor: '{$destName} ma rokdinuhos'",
        'path_coordinates' => $pathCoords,
    ];

    // ── Estimated wait time
    $routeDistKm = $routeDetails[$routeId]['total_distance_km'] ?? null;
    $waitTime = estimateWaitTime($routeId, $routeDistKm);
    if ($waitTime !== null) {
        $segment['wait_time_estimate'] = "A bus arrives every ~{$waitTime} min";
    }

    return $segment;
}

/**
 * Build a transfer segment (the moment the rider switches buses).
 */
function buildTransferSegment(
    int $locationId,
    array $locationCoords,
    int $fromRouteId,
    int $toRouteId,
    array $routeDetails
): array {
    $locName = $locationCoords[$locationId]['name'];
    $toRouteName = $routeDetails[$toRouteId]['name'] ?? 'another bus';

    // Estimate wait time for the next bus
    $routeDistKm = $routeDetails[$toRouteId]['total_distance_km'] ?? null;
    $waitTime = estimateWaitTime($toRouteId, $routeDistKm);
    $waitStr = $waitTime !== null ? "~{$waitTime} minutes" : '~5 minutes';

    return [
        'type' => 'transfer',
        'at' => [
            'name' => $locName,
            'location_id' => $locationId,
            'lat' => $locationCoords[$locationId]['lat'],
            'lng' => $locationCoords[$locationId]['lng'],
        ],
        'instruction' => "Get off at {$locName}. Look for a bus on the {$toRouteName} route.",
        'wait_time_estimate' => $waitStr,
        'wait_time_min' => $waitTime ?? 5,
    ];
}

/**
 * Build a walking segment from pre-calculated walking data.
 */
function buildWalkingSegment(array $walkData, array $locationCoords, int $fromLocId, int $toLocId): array
{
    return [
        'type' => 'walking',
        'from' => [
            'name' => $walkData['from_name'] ?? $locationCoords[$fromLocId]['name'],
            'location_id' => $fromLocId,
            'lat' => $locationCoords[$fromLocId]['lat'],
            'lng' => $locationCoords[$fromLocId]['lng'],
        ],
        'to' => [
            'name' => $walkData['to_name'] ?? $locationCoords[$toLocId]['name'],
            'location_id' => $toLocId,
            'lat' => $locationCoords[$toLocId]['lat'],
            'lng' => $locationCoords[$toLocId]['lng'],
        ],
        'distance_m' => $walkData['distance_m'],
        'duration_min' => $walkData['duration_min'],
        'directions' => "Walk {$walkData['distance_m']}m to {$walkData['to_name']}",
        'geometry' => $walkData['geometry'] ?? [],
    ];
}

/**
 * Get the first approved vehicle that operates on a given route.
 *
 * Searches all approved vehicles and checks their used_routes JSON
 * for the matching route_id.
 *
 * @param int $routeId
 * @return array|null  Vehicle data or null
 */
function getVehicleForRoute(int $routeId): ?array
{
    $db = getDBConnection();

    $stmt = $db->query("
        SELECT vehicle_id, name, image_path, starts_at, stops_at, used_routes
        FROM vehicles
        WHERE status = 'approved'
    ");
    $vehicles = $stmt->fetchAll();

    foreach ($vehicles as $v) {
        $usedRoutes = json_decode($v['used_routes'], true);
        if (!is_array($usedRoutes)) {
            continue;
        }

        foreach ($usedRoutes as $ur) {
            if (isset($ur['route_id']) && (int) $ur['route_id'] === $routeId) {
                return [
                    'vehicle_id' => (int) $v['vehicle_id'],
                    'name' => $v['name'],
                    'image' => $v['image_path'],
                    'starts_at' => $v['starts_at'] ? substr($v['starts_at'], 0, 5) : null,
                    'stops_at' => $v['stops_at'] ? substr($v['stops_at'], 0, 5) : null,
                    'count' => (int) ($ur['count'] ?? 1),
                ];
            }
        }
    }

    return null;
}

/**
 * Fetch active alerts that affect any of the given route IDs.
 *
 * An alert is active if its expires_at is NULL or in the future.
 *
 * @param int[] $routeIds
 * @return array  Array of alert info objects
 */
function getActiveAlertsForRoutes(array $routeIds): array
{
    if (empty($routeIds)) {
        return [];
    }

    $db = getDBConnection();
    $stmt = $db->query("
        SELECT alert_id, name, description, routes_affected
        FROM alerts
        WHERE expires_at IS NULL OR expires_at > NOW()
    ");
    $alerts = $stmt->fetchAll();

    $affecting = [];

    foreach ($alerts as $alert) {
        $affected = json_decode($alert['routes_affected'], true);
        if (!is_array($affected)) {
            continue;
        }

        $overlap = array_intersect($affected, $routeIds);
        if (!empty($overlap)) {
            $affecting[] = [
                'alert_id' => (int) $alert['alert_id'],
                'name' => $alert['name'],
                'description' => $alert['description'],
                'affected_routes' => array_values(array_map('intval', $overlap)),
            ];
        }
    }

    return $affecting;
}
