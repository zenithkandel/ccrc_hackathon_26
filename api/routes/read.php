<?php
/**
 * API: Read Routes
 * GET /api/routes/read.php
 * 
 * Fetch routes with optional filters.
 * 
 * Query params:
 *   id         - Fetch single route by ID (with expanded location names)
 *   status     - Filter by status
 *   search     - Search by name
 *   agent_id   - Filter by agent
 *   location_id - Find routes passing through a specific location
 *   page       - Page number
 *   limit      - Items per page
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/validation.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$pdo = getDBConnection();

// ─── Single route by ID (with expanded locations) ─────────
$id = getIntParam('id');
if ($id > 0) {
    $stmt = $pdo->prepare('
        SELECT r.*, 
               a.name AS updated_by_name,
               adm.name AS approved_by_name
        FROM routes r
        LEFT JOIN agents a ON r.updated_by = a.agent_id
        LEFT JOIN admins adm ON r.approved_by = adm.admin_id
        WHERE r.route_id = :id
    ');
    $stmt->execute(['id' => $id]);
    $route = $stmt->fetch();

    if (!$route) {
        jsonResponse(['success' => false, 'message' => 'Route not found.'], 404);
    }

    // Expand location_list with full location details
    $locationList = json_decode($route['location_list'], true) ?? [];
    $expandedLocations = [];

    if (!empty($locationList)) {
        $locIds = array_column($locationList, 'location_id');
        $placeholders = implode(',', array_fill(0, count($locIds), '?'));
        $locStmt = $pdo->prepare("SELECT location_id, name, latitude, longitude, type FROM locations WHERE location_id IN ($placeholders)");
        $locStmt->execute($locIds);
        $locData = $locStmt->fetchAll();
        $locMap = [];
        foreach ($locData as $loc) {
            $locMap[$loc['location_id']] = $loc;
        }

        foreach ($locationList as $item) {
            $locId = $item['location_id'];
            $expandedLocations[] = [
                'index' => $item['index'],
                'location_id' => $locId,
                'name' => $locMap[$locId]['name'] ?? 'Unknown',
                'latitude' => $locMap[$locId]['latitude'] ?? null,
                'longitude' => $locMap[$locId]['longitude'] ?? null,
                'type' => $locMap[$locId]['type'] ?? null,
            ];
        }
    }

    $route['locations_expanded'] = $expandedLocations;
    $route['stop_count'] = count($expandedLocations);

    jsonResponse(['success' => true, 'data' => $route]);
}

// ─── Routes passing through a specific location ──────────
$locationIdFilter = getIntParam('location_id');
if ($locationIdFilter > 0) {
    // JSON_CONTAINS to find routes containing this location_id
    $stmt = $pdo->prepare("
        SELECT r.*, a.name AS updated_by_name
        FROM routes r
        LEFT JOIN agents a ON r.updated_by = a.agent_id
        WHERE r.status = 'approved'
        AND JSON_CONTAINS(r.location_list, JSON_OBJECT('location_id', :loc_id))
        ORDER BY r.name ASC
    ");
    $stmt->execute(['loc_id' => $locationIdFilter]);
    $routes = $stmt->fetchAll();

    jsonResponse(['success' => true, 'data' => $routes, 'count' => count($routes)]);
}

// ─── Filtered list with pagination ────────────────────────
$conditions = [];
$params = [];

$status = getParam('status');
if ($status && validateEnum($status, ['pending', 'approved', 'rejected'])) {
    $conditions[] = 'r.status = :status';
    $params['status'] = $status;
} elseif (!isLoggedIn()) {
    $conditions[] = "r.status = 'approved'";
}

$search = getParam('search');
if ($search) {
    $conditions[] = 'r.name LIKE :search';
    $params['search'] = '%' . trim($search) . '%';
}

$agentId = getIntParam('agent_id');
if ($agentId > 0) {
    $conditions[] = 'r.updated_by = :agent_id';
    $params['agent_id'] = $agentId;
}

$where = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM routes r $where");
$countStmt->execute($params);
$totalItems = (int) $countStmt->fetchColumn();

$page = getIntParam('page', 1);
$perPage = getIntParam('limit', ITEMS_PER_PAGE);
$pagination = paginate($totalItems, $page, $perPage);

$stmt = $pdo->prepare("
    SELECT r.*, 
           a.name AS updated_by_name,
           adm.name AS approved_by_name,
           JSON_LENGTH(r.location_list) AS stop_count
    FROM routes r
    LEFT JOIN agents a ON r.updated_by = a.agent_id
    LEFT JOIN admins adm ON r.approved_by = adm.admin_id
    $where
    ORDER BY r.route_id DESC
    LIMIT :lim OFFSET :off
");

foreach ($params as $key => $val) {
    $stmt->bindValue(":$key", $val);
}
$stmt->bindValue(':lim', $pagination['perPage'], PDO::PARAM_INT);
$stmt->bindValue(':off', $pagination['offset'], PDO::PARAM_INT);
$stmt->execute();
$routes = $stmt->fetchAll();

jsonResponse([
    'success' => true,
    'data' => $routes,
    'pagination' => $pagination,
]);
