<?php
/**
 * API: Read Trips (Analytics)
 * GET /api/trips/read.php
 * 
 * Admin-only endpoint. Provides trip query analytics.
 * 
 * Query params:
 *   popular_routes     - If "true", return most-searched routes
 *   popular_locations  - If "true", return most-searched start/dest locations
 *   date_from / date_to - Filter by date range
 *   page/limit          - Pagination for raw trip list
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!isAdmin()) {
    jsonResponse(['success' => false, 'message' => 'Admin access required.'], 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$pdo = getDBConnection();

// ─── Popular locations (most searched start/dest) ───
if (getParam('popular_locations') === 'true') {
    $limit = getIntParam('limit', 10);

    $stmt = $pdo->prepare("
        SELECT l.location_id, l.name, l.type, COUNT(*) AS search_count
        FROM (
            SELECT start_location_id AS lid FROM trips
            UNION ALL
            SELECT destination_location_id AS lid FROM trips
        ) AS all_locs
        JOIN locations l ON l.location_id = all_locs.lid
        GROUP BY l.location_id
        ORDER BY search_count DESC
        LIMIT :lim
    ");
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();

    jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);
}

// ─── Popular origin-destination pairs ───────────────
if (getParam('popular_routes') === 'true') {
    $limit = getIntParam('limit', 10);

    $stmt = $pdo->prepare("
        SELECT t.start_location_id, sl.name AS start_name,
               t.destination_location_id, dl.name AS dest_name,
               COUNT(*) AS trip_count
        FROM trips t
        JOIN locations sl ON sl.location_id = t.start_location_id
        JOIN locations dl ON dl.location_id = t.destination_location_id
        GROUP BY t.start_location_id, t.destination_location_id
        ORDER BY trip_count DESC
        LIMIT :lim
    ");
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();

    jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);
}

// ─── Raw trip list with pagination ──────────────────
$conditions = [];
$params = [];

$dateFrom = getParam('date_from');
if ($dateFrom) {
    $conditions[] = 't.queried_at >= :date_from';
    $params['date_from'] = $dateFrom;
}

$dateTo = getParam('date_to');
if ($dateTo) {
    $conditions[] = 't.queried_at <= :date_to';
    $params['date_to'] = $dateTo . ' 23:59:59';
}

$where = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM trips t $where");
$countStmt->execute($params);
$totalItems = (int) $countStmt->fetchColumn();

$page = getIntParam('page', 1);
$perPage = getIntParam('limit', ITEMS_PER_PAGE);
$pagination = paginate($totalItems, $page, $perPage);

$stmt = $pdo->prepare("
    SELECT t.*, sl.name AS start_name, dl.name AS dest_name
    FROM trips t
    JOIN locations sl ON sl.location_id = t.start_location_id
    JOIN locations dl ON dl.location_id = t.destination_location_id
    $where
    ORDER BY t.queried_at DESC
    LIMIT :lim OFFSET :off
");

foreach ($params as $key => $val) {
    $stmt->bindValue(":$key", $val);
}
$stmt->bindValue(':lim', $pagination['perPage'], PDO::PARAM_INT);
$stmt->bindValue(':off', $pagination['offset'], PDO::PARAM_INT);
$stmt->execute();
$trips = $stmt->fetchAll();

foreach ($trips as &$trip) {
    $trip['routes_used'] = json_decode($trip['routes_used'], true);
}

jsonResponse([
    'success' => true,
    'data' => $trips,
    'pagination' => $pagination,
]);
