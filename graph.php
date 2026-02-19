<?php
/**
 * Transit Graph Construction — Sawari
 *
 * Builds a weighted adjacency-list graph from approved routes and locations.
 *
 * Each node  = a location_id
 * Each edge  = connection between consecutive stops on a route
 * Edge weight = Haversine distance (km) between the two locations
 * Edge meta  = route_id (which route the connection belongs to)
 *
 * Routes are bidirectional — edges are added in BOTH directions.
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../config/database.php';

/**
 * Build the full transit graph from the database.
 *
 * Steps:
 *   1. Load all approved locations into a coordinate lookup table
 *   2. Load all approved routes with their location_list JSON
 *   3. For each route, sort stops by index, then create bidirectional edges
 *      between every pair of consecutive stops
 *   4. Also compute the total distance of each route (needed for wait-time estimates)
 *
 * @return array {
 *   'graph'          => array  Adjacency list: graph[locId] = [ ['to'=>int, 'weight'=>float, 'route_id'=>int], ... ]
 *   'locationCoords' => array  locationCoords[locId] = ['lat'=>float, 'lng'=>float, 'name'=>string, 'type'=>string]
 *   'routeDetails'   => array  routeDetails[routeId] = ['name'=>string, 'total_distance_km'=>float, 'stop_ids'=>int[]]
 * }
 */
function buildTransitGraph(): array
{
    $db = getDBConnection();

    // ─── 1. Load all approved locations ─────────────────────
    $stmt = $db->query("
        SELECT location_id, name, latitude, longitude, type
        FROM locations
        WHERE status = 'approved'
    ");
    $locRows = $stmt->fetchAll();

    $locationCoords = [];
    foreach ($locRows as $loc) {
        $locationCoords[(int) $loc['location_id']] = [
            'lat' => (float) $loc['latitude'],
            'lng' => (float) $loc['longitude'],
            'name' => $loc['name'],
            'type' => $loc['type'],
        ];
    }

    // ─── 2. Load all approved routes ────────────────────────
    $stmt = $db->query("
        SELECT route_id, name, location_list
        FROM routes
        WHERE status = 'approved'
    ");
    $routes = $stmt->fetchAll();

    // ─── 3. Build adjacency list ────────────────────────────
    $graph = [];
    $routeDetails = [];

    foreach ($routes as $route) {
        $routeId = (int) $route['route_id'];

        // Parse the location_list JSON
        $locationList = json_decode($route['location_list'], true);
        if (!is_array($locationList) || count($locationList) < 2) {
            // Route without enough stops — skip
            $routeDetails[$routeId] = [
                'name' => $route['name'],
                'total_distance_km' => 0,
                'stop_ids' => [],
            ];
            continue;
        }

        // Sort by index to ensure correct stop order
        usort($locationList, function ($a, $b) {
            return ((int) ($a['index'] ?? 0)) - ((int) ($b['index'] ?? 0));
        });

        // Extract ordered location IDs, filtering out any missing ones
        $orderedStopIds = [];
        foreach ($locationList as $entry) {
            $locId = (int) $entry['location_id'];
            if (isset($locationCoords[$locId])) {
                $orderedStopIds[] = $locId;
            }
        }

        if (count($orderedStopIds) < 2) {
            $routeDetails[$routeId] = [
                'name' => $route['name'],
                'total_distance_km' => 0,
                'stop_ids' => $orderedStopIds,
            ];
            continue;
        }

        // Create edges between consecutive stops
        $routeTotalDistance = 0.0;

        for ($i = 0; $i < count($orderedStopIds) - 1; $i++) {
            $fromId = $orderedStopIds[$i];
            $toId = $orderedStopIds[$i + 1];

            $weight = haversineDistance(
                $locationCoords[$fromId]['lat'],
                $locationCoords[$fromId]['lng'],
                $locationCoords[$toId]['lat'],
                $locationCoords[$toId]['lng']
            );

            $routeTotalDistance += $weight;

            // Ensure adjacency lists exist
            if (!isset($graph[$fromId])) {
                $graph[$fromId] = [];
            }
            if (!isset($graph[$toId])) {
                $graph[$toId] = [];
            }

            // Bidirectional edges
            $graph[$fromId][] = [
                'to' => $toId,
                'weight' => $weight,
                'route_id' => $routeId,
            ];
            $graph[$toId][] = [
                'to' => $fromId,
                'weight' => $weight,
                'route_id' => $routeId,
            ];
        }

        $routeDetails[$routeId] = [
            'name' => $route['name'],
            'total_distance_km' => round($routeTotalDistance, 3),
            'stop_ids' => $orderedStopIds,
        ];
    }

    return [
        'graph' => $graph,
        'locationCoords' => $locationCoords,
        'routeDetails' => $routeDetails,
    ];
}
