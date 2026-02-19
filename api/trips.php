<?php
/**
 * SAWARI — Trips API
 *
 * Actions:
 *   log      – POST – Log a new trip (anonymous, uses session_id)
 *   feedback – POST – Submit rating, review, accuracy for a trip
 *   end      – POST – Mark trip as ended
 *   get      – GET  – Get a single trip by ID
 *   stats    – GET  – Public trip statistics
 */

require_once __DIR__ . '/config.php';

// Ensure session_id exists for anonymous users
if (!session_id()) {
    session_start();
}

$action = getAction();

switch ($action) {

    /* ── Log a new trip ──────────────────────────────────── */
    case 'log':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST')
            jsonError('POST required.', 405);
        // No CSRF for anonymous trip logging (public endpoint)
        $db = getDB();

        $sessionId = session_id();
        $routeId = postInt('route_id');
        $vehicleId = postInt('vehicle_id') ?: null;
        $boardingStopId = postInt('boarding_stop_id') ?: null;
        $destStopId = postInt('destination_stop_id') ?: null;
        $transferStopId = postInt('transfer_stop_id') ?: null;
        $secondRouteId = postInt('second_route_id') ?: null;
        $farePaid = isset($_POST['fare_paid']) ? floatval($_POST['fare_paid']) : null;
        $carbonSaved = isset($_POST['carbon_saved']) ? floatval($_POST['carbon_saved']) : null;

        if (!$routeId)
            jsonError('Route ID is required.');

        $stmt = $db->prepare("INSERT INTO trips
            (session_id, route_id, vehicle_id, boarding_stop_id, destination_stop_id,
             transfer_stop_id, second_route_id, fare_paid, carbon_saved, started_at)
            VALUES
            (:sid, :rid, :vid, :bsid, :dsid, :tsid, :srid, :fare, :carbon, NOW())");

        $stmt->execute([
            ':sid' => $sessionId,
            ':rid' => $routeId,
            ':vid' => $vehicleId,
            ':bsid' => $boardingStopId,
            ':dsid' => $destStopId,
            ':tsid' => $transferStopId,
            ':srid' => $secondRouteId,
            ':fare' => $farePaid,
            ':carbon' => $carbonSaved
        ]);

        $tripId = (int) $db->lastInsertId();

        // Increment departure_count / destination_count
        if ($boardingStopId) {
            $db->prepare("UPDATE locations SET departure_count = departure_count + 1 WHERE location_id = :id")
                ->execute([':id' => $boardingStopId]);
        }
        if ($destStopId) {
            $db->prepare("UPDATE locations SET destination_count = destination_count + 1 WHERE location_id = :id")
                ->execute([':id' => $destStopId]);
        }

        jsonResponse([
            'success' => true,
            'message' => 'Trip logged.',
            'trip_id' => $tripId
        ]);
        break;

    /* ── Submit feedback for an existing trip ─────────────── */
    case 'feedback':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST')
            jsonError('POST required.', 405);
        // Session-bound: verify trip belongs to user's session
        $db = getDB();

        $tripId = postInt('trip_id');
        $rating = postInt('rating');
        $review = postString('review');
        $accuracy = postString('accuracy_feedback');

        if (!$tripId)
            jsonError('Trip ID is required.');

        // Verify this trip belongs to the current session
        $check = $db->prepare("SELECT trip_id FROM trips WHERE trip_id = :id AND session_id = :sid");
        $check->execute([':id' => $tripId, ':sid' => session_id()]);
        if (!$check->fetch())
            jsonError('Trip not found or does not belong to this session.', 404);

        // Validate rating
        if ($rating < 1 || $rating > 5)
            jsonError('Rating must be between 1 and 5.');

        // Validate accuracy
        $validAccuracy = ['accurate', 'slightly_off', 'inaccurate'];
        if ($accuracy && !in_array($accuracy, $validAccuracy)) {
            $accuracy = null;
        }

        $stmt = $db->prepare("UPDATE trips
            SET rating = :rating, review = :review, accuracy_feedback = :accuracy, ended_at = NOW()
            WHERE trip_id = :id");
        $stmt->execute([
            ':rating' => $rating,
            ':review' => $review ?: null,
            ':accuracy' => $accuracy ?: null,
            ':id' => $tripId
        ]);

        jsonSuccess('Thank you for your feedback!');
        break;

    /* ── End a trip (without feedback) ────────────────────── */
    case 'end':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST')
            jsonError('POST required.', 405);
        $db = getDB();

        $tripId = postInt('trip_id');
        if (!$tripId)
            jsonError('Trip ID is required.');

        $stmt = $db->prepare("UPDATE trips SET ended_at = NOW() WHERE trip_id = :id AND session_id = :sid");
        $stmt->execute([':id' => $tripId, ':sid' => session_id()]);

        jsonSuccess('Trip ended.');
        break;

    /* ── Get a trip ──────────────────────────────────────── */
    case 'get':
        $db = getDB();
        $id = getInt('id');
        if (!$id)
            jsonError('Trip ID required.');

        $stmt = $db->prepare("SELECT t.*,
                                     r.name AS route_name,
                                     bl.name AS boarding_name,
                                     dl.name AS destination_name,
                                     tl.name AS transfer_name,
                                     r2.name AS second_route_name
                              FROM trips t
                              LEFT JOIN routes r ON t.route_id = r.route_id
                              LEFT JOIN locations bl ON t.boarding_stop_id = bl.location_id
                              LEFT JOIN locations dl ON t.destination_stop_id = dl.location_id
                              LEFT JOIN locations tl ON t.transfer_stop_id = tl.location_id
                              LEFT JOIN routes r2 ON t.second_route_id = r2.route_id
                              WHERE t.trip_id = :id");
        $stmt->execute([':id' => $id]);
        $trip = $stmt->fetch();
        if (!$trip)
            jsonError('Trip not found.', 404);

        jsonResponse(['success' => true, 'trip' => $trip]);
        break;

    /* ── Public stats ─────────────────────────────────────── */
    case 'stats':
        $db = getDB();

        $totalTrips = (int) $db->query("SELECT COUNT(*) FROM trips")->fetchColumn();
        $avgRating = $db->query("SELECT ROUND(AVG(rating), 1) FROM trips WHERE rating IS NOT NULL")->fetchColumn();
        $totalCarbon = $db->query("SELECT ROUND(SUM(carbon_saved), 2) FROM trips WHERE carbon_saved IS NOT NULL")->fetchColumn();

        // Popular routes
        $popular = $db->query("SELECT r.name, COUNT(*) as trip_count
                               FROM trips t
                               JOIN routes r ON t.route_id = r.route_id
                               GROUP BY t.route_id
                               ORDER BY trip_count DESC
                               LIMIT 5")->fetchAll();

        jsonResponse([
            'success' => true,
            'stats' => [
                'total_trips' => $totalTrips,
                'avg_rating' => $avgRating ? (float) $avgRating : null,
                'total_carbon_saved_kg' => $totalCarbon ? (float) $totalCarbon : 0,
                'popular_routes' => $popular
            ]
        ]);
        break;

    default:
        jsonError('Unknown action.', 400);
}
