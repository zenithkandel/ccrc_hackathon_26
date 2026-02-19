<?php
/**
 * Modified Dijkstra's Algorithm — Sawari
 *
 * Finds the shortest path through the transit graph from a start location
 * to an end location, with a transfer penalty that discourages unnecessary
 * bus changes.
 *
 * ─── State-space expansion ────────────────────────────────────────────
 *
 * Standard Dijkstra tracks one distance per node. But in transits, arriving
 * at the same node on DIFFERENT routes has different future costs — continuing
 * on the same route is free, while switching incurs a penalty.
 *
 * Solution: the Dijkstra state is (location_id, route_id), not just location_id.
 * This way a node can be "visited" once per route with potentially different
 * optimal distances.
 *
 * State key format: "locationId_routeId"  (route_id = 0 means "not yet riding")
 *
 * For a city like Kathmandu with ~200 locations and ~50 routes the state
 * space (200 × 50 = 10 000) is trivially small for Dijkstra.
 */

require_once __DIR__ . '/../config/constants.php';

/**
 * Find the shortest path between two locations in the transit graph.
 *
 * @param array $graph          Adjacency list from buildTransitGraph()
 * @param int   $startId        Starting location_id
 * @param int   $endId          Destination location_id
 * @return array|null           Null if no path, otherwise:
 *   'path'           => array of ['location_id' => int, 'route_id' => int]
 *   'total_distance' => float  actual travel distance (km) WITHOUT penalties
 *   'total_weighted' => float  weighted distance WITH penalties (for debug)
 */
function findShortestPath(array $graph, int $startId, int $endId): ?array
{
    // ─── Edge cases ─────────────────────────────────────────
    if ($startId === $endId) {
        return [
            'path' => [['location_id' => $startId, 'route_id' => 0]],
            'total_distance' => 0.0,
            'total_weighted' => 0.0,
        ];
    }

    if (!isset($graph[$startId])) {
        return null; // Start has no edges in the transit network
    }

    // ─── Data structures ────────────────────────────────────
    // Distance map:  state_key => best known distance (with penalties)
    $dist = [];

    // Predecessor map:  state_key => previous state_key
    $prev = [];

    // Actual (unpenalised) distance map: state_key => real travel km
    $realDist = [];

    // Visited set
    $visited = [];

    // Min-priority queue (PHP's SplPriorityQueue is max-heap → negate priorities)
    $pq = new SplPriorityQueue();
    $pq->setExtractFlags(SplPriorityQueue::EXTR_BOTH);

    // ─── Initialise start state ─────────────────────────────
    // Route 0 = "not on any bus yet"
    $startState = $startId . '_0';
    $dist[$startState] = 0.0;
    $realDist[$startState] = 0.0;
    $pq->insert($startState, 0.0); // priority = -distance → 0 is highest

    // We'll store the end state key that was first settled
    $endState = null;

    // ─── Main loop ──────────────────────────────────────────
    while (!$pq->isEmpty()) {
        $item = $pq->extract();
        $currentState = $item['data'];
        $currentDist = -$item['priority']; // un-negate

        // Already settled?
        if (isset($visited[$currentState])) {
            continue;
        }
        $visited[$currentState] = true;

        // Parse composite state
        $sep = strrpos($currentState, '_');
        $currentLocId = (int) substr($currentState, 0, $sep);
        $currentRoute = (int) substr($currentState, $sep + 1);

        // ── Destination reached ─────────────────────────────
        if ($currentLocId === $endId) {
            $endState = $currentState;
            break;
        }

        // ── Expand neighbours ───────────────────────────────
        if (!isset($graph[$currentLocId])) {
            continue;
        }

        foreach ($graph[$currentLocId] as $edge) {
            $neighbourId = $edge['to'];
            $edgeRouteId = $edge['route_id'];
            $edgeWeight = $edge['weight']; // Haversine km

            // ── Transfer penalty ────────────────────────────
            // Applied when we are already on a route (currentRoute != 0)
            // and the edge belongs to a different route.
            $penalty = 0.0;
            if ($currentRoute !== 0 && $currentRoute !== $edgeRouteId) {
                $penalty = TRANSFER_PENALTY_KM;
            }

            $newWeighted = $currentDist + $edgeWeight + $penalty;
            $newReal = $realDist[$currentState] + $edgeWeight;
            $newState = $neighbourId . '_' . $edgeRouteId;

            // Relaxation
            if ($newWeighted < ($dist[$newState] ?? INF)) {
                $dist[$newState] = $newWeighted;
                $realDist[$newState] = $newReal;
                $prev[$newState] = $currentState;

                // Insert with negated priority for min-heap
                $pq->insert($newState, -$newWeighted);
            }
        }
    }

    // ─── No path found ──────────────────────────────────────
    if ($endState === null) {
        return null;
    }

    // ─── Reconstruct path ───────────────────────────────────
    $path = [];
    $state = $endState;

    while ($state !== null) {
        $sep = strrpos($state, '_');
        $locId = (int) substr($state, 0, $sep);
        $rId = (int) substr($state, $sep + 1);

        $path[] = [
            'location_id' => $locId,
            'route_id' => $rId,
        ];

        $state = $prev[$state] ?? null;
    }

    // Path was built tail-to-head; reverse it
    $path = array_reverse($path);

    return [
        'path' => $path,
        'total_distance' => round($realDist[$endState], 4),
        'total_weighted' => round($dist[$endState], 4),
    ];
}
