<?php
/**
 * API: Read Locations
 * GET /api/locations/read.php
 * 
 * Fetch locations with optional filters.
 * 
 * Query params:
 *   id        - Fetch single location by ID
 *   status    - Filter by status (pending|approved|rejected)
 *   type      - Filter by type (stop|landmark)
 *   search    - Search by name (LIKE)
 *   page      - Page number (default: 1)
 *   limit     - Items per page (default: ITEMS_PER_PAGE)
 *   agent_id  - Filter by agent who submitted
 *   nearest   - If "1", requires lat & lng params to find nearest stops
 *   lat       - Latitude for nearest search
 *   lng       - Longitude for nearest search
 *   radius    - Radius in km for nearest search (default: NEAREST_STOP_RADIUS_KM)
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$pdo = getDBConnection();

// ─── Single location by ID ────────────────────────────────
$id = getIntParam('id');
if ($id > 0) {
    $stmt = $pdo->prepare('
        SELECT l.*, 
               a.name AS updated_by_name, 
               adm.name AS approved_by_name
        FROM locations l
        LEFT JOIN agents a ON l.updated_by = a.agent_id
        LEFT JOIN admins adm ON l.approved_by = adm.admin_id
        WHERE l.location_id = :id
    ');
    $stmt->execute(['id' => $id]);
    $location = $stmt->fetch();

    if (!$location) {
        jsonResponse(['success' => false, 'message' => 'Location not found.'], 404);
    }

    jsonResponse(['success' => true, 'data' => $location]);
}

// ─── Nearest locations search ─────────────────────────────
// Accept both ?nearest=1&lat=X&lng=Y and ?nearest_lat=X&nearest_lng=Y
$nearestLat = getParam('lat') ?: getParam('nearest_lat');
$nearestLng = getParam('lng') ?: getParam('nearest_lng');
$isNearest = getParam('nearest') === '1' || ($nearestLat && $nearestLng);

if ($isNearest) {
    $lat = (float) $nearestLat;
    $lng = (float) $nearestLng;
    $radius = (float) (getParam('radius') ?: getParam('nearest_radius') ?: NEAREST_STOP_RADIUS_KM);
    $limit = getIntParam('limit', 10);

    if (!$lat || !$lng) {
        jsonResponse(['success' => false, 'message' => 'Latitude and longitude required for nearest search.'], 400);
    }

    // Haversine formula in SQL (returns distance in km)
    $stmt = $pdo->prepare("
        SELECT *, 
            (6371 * ACOS(
                COS(RADIANS(:lat1)) * COS(RADIANS(latitude)) * 
                COS(RADIANS(longitude) - RADIANS(:lng1)) + 
                SIN(RADIANS(:lat2)) * SIN(RADIANS(latitude))
            )) AS distance_km
        FROM locations
        WHERE status = 'approved'
        HAVING distance_km <= :radius
        ORDER BY distance_km ASC
        LIMIT :lim
    ");
    $stmt->bindValue(':lat1', $lat, PDO::PARAM_STR);
    $stmt->bindValue(':lng1', $lng, PDO::PARAM_STR);
    $stmt->bindValue(':lat2', $lat, PDO::PARAM_STR);
    $stmt->bindValue(':radius', $radius, PDO::PARAM_STR);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $locations = $stmt->fetchAll();

    jsonResponse(['success' => true, 'data' => $locations, 'locations' => $locations, 'count' => count($locations)]);
}

// ─── Filtered list with pagination ────────────────────────
$conditions = [];
$params = [];

// Status filter (public users only see approved; agents/admins see all)
$status = getParam('status');
if ($status && validateEnum($status, ['pending', 'approved', 'rejected'])) {
    $conditions[] = 'l.status = :status';
    $params['status'] = $status;
} elseif (!isLoggedIn()) {
    // Public users only see approved locations
    $conditions[] = "l.status = 'approved'";
}

// Type filter
$type = getParam('type');
if ($type && validateEnum($type, ['stop', 'landmark'])) {
    $conditions[] = 'l.type = :type';
    $params['type'] = $type;
}

// Search by name
$search = getParam('search');
if ($search) {
    $conditions[] = 'l.name LIKE :search';
    $params['search'] = '%' . trim($search) . '%';
}

// Agent filter
$agentId = getIntParam('agent_id');
if ($agentId > 0) {
    $conditions[] = 'l.updated_by = :agent_id';
    $params['agent_id'] = $agentId;
}

$where = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Count total items
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM locations l $where");
$countStmt->execute($params);
$totalItems = (int) $countStmt->fetchColumn();

// Pagination
$page = getIntParam('page', 1);
$perPage = getIntParam('limit', ITEMS_PER_PAGE);
$pagination = paginate($totalItems, $page, $perPage);

// Fetch data
$stmt = $pdo->prepare("
    SELECT l.*, 
           a.name AS updated_by_name,
           adm.name AS approved_by_name
    FROM locations l
    LEFT JOIN agents a ON l.updated_by = a.agent_id
    LEFT JOIN admins adm ON l.approved_by = adm.admin_id
    $where
    ORDER BY l.location_id DESC
    LIMIT :lim OFFSET :off
");

foreach ($params as $key => $val) {
    $stmt->bindValue(":$key", $val);
}
$stmt->bindValue(':lim', $pagination['perPage'], PDO::PARAM_INT);
$stmt->bindValue(':off', $pagination['offset'], PDO::PARAM_INT);
$stmt->execute();
$locations = $stmt->fetchAll();

jsonResponse([
    'success' => true,
    'data' => $locations,
    'pagination' => $pagination,
]);
