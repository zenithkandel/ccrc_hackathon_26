<?php
/**
 * API: Read Vehicles
 * GET /api/vehicles/read.php
 * 
 * Query params:
 *   id        - Fetch single vehicle by ID (with expanded routes)
 *   route_id  - Find vehicles operating on a route
 *   status    - Filter by status
 *   search    - Search by name
 *   agent_id  - Filter by agent
 *   page/limit - Pagination
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

// ─── Single vehicle by ID ───────────────────────────
$id = getIntParam('id');
if ($id > 0) {
    $stmt = $pdo->prepare('
        SELECT v.*, a.name AS updated_by_name, adm.name AS approved_by_name
        FROM vehicles v
        LEFT JOIN agents a ON v.updated_by = a.agent_id
        LEFT JOIN admins adm ON v.approved_by = adm.admin_id
        WHERE v.vehicle_id = :id
    ');
    $stmt->execute(['id' => $id]);
    $vehicle = $stmt->fetch();

    if (!$vehicle) {
        jsonResponse(['success' => false, 'message' => 'Vehicle not found.'], 404);
    }

    // Expand used_routes with route names
    $usedRoutes = json_decode($vehicle['used_routes'], true) ?? [];
    $expandedRoutes = [];
    if (!empty($usedRoutes)) {
        $routeIds = array_column($usedRoutes, 'route_id');
        $placeholders = implode(',', array_fill(0, count($routeIds), '?'));
        $rStmt = $pdo->prepare("SELECT route_id, name FROM routes WHERE route_id IN ($placeholders)");
        $rStmt->execute($routeIds);
        $routeMap = [];
        foreach ($rStmt->fetchAll() as $r) {
            $routeMap[$r['route_id']] = $r['name'];
        }
        foreach ($usedRoutes as $ur) {
            $rid = $ur['route_id'];
            $expandedRoutes[] = [
                'route_id' => $rid,
                'route_name' => $routeMap[$rid] ?? 'Unknown',
                'count' => $ur['count'] ?? 1,
            ];
        }
    }
    $vehicle['routes_expanded'] = $expandedRoutes;

    jsonResponse(['success' => true, 'data' => $vehicle]);
}

// ─── Vehicles operating on a specific route ──────────
$routeIdFilter = getIntParam('route_id');
if ($routeIdFilter > 0) {
    $stmt = $pdo->prepare("
        SELECT v.*, a.name AS updated_by_name
        FROM vehicles v
        LEFT JOIN agents a ON v.updated_by = a.agent_id
        WHERE v.status = 'approved'
        AND JSON_CONTAINS(v.used_routes, JSON_OBJECT('route_id', :route_id))
        ORDER BY v.name ASC
    ");
    $stmt->execute(['route_id' => $routeIdFilter]);
    $vehicles = $stmt->fetchAll();

    jsonResponse(['success' => true, 'data' => $vehicles, 'count' => count($vehicles)]);
}

// ─── Filtered list with pagination ──────────────────
$conditions = [];
$params = [];

$status = getParam('status');
if ($status && validateEnum($status, ['pending', 'approved', 'rejected'])) {
    $conditions[] = 'v.status = :status';
    $params['status'] = $status;
} elseif (!isLoggedIn()) {
    $conditions[] = "v.status = 'approved'";
}

$search = getParam('search');
if ($search) {
    $conditions[] = 'v.name LIKE :search';
    $params['search'] = '%' . trim($search) . '%';
}

$agentId = getIntParam('agent_id');
if ($agentId > 0) {
    $conditions[] = 'v.updated_by = :agent_id';
    $params['agent_id'] = $agentId;
}

$where = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM vehicles v $where");
$countStmt->execute($params);
$totalItems = (int) $countStmt->fetchColumn();

$page = getIntParam('page', 1);
$perPage = getIntParam('limit', ITEMS_PER_PAGE);
$pagination = paginate($totalItems, $page, $perPage);

$stmt = $pdo->prepare("
    SELECT v.*, a.name AS updated_by_name, adm.name AS approved_by_name
    FROM vehicles v
    LEFT JOIN agents a ON v.updated_by = a.agent_id
    LEFT JOIN admins adm ON v.approved_by = adm.admin_id
    $where
    ORDER BY v.vehicle_id DESC
    LIMIT :lim OFFSET :off
");

foreach ($params as $key => $val) {
    $stmt->bindValue(":$key", $val);
}
$stmt->bindValue(':lim', $pagination['perPage'], PDO::PARAM_INT);
$stmt->bindValue(':off', $pagination['offset'], PDO::PARAM_INT);
$stmt->execute();
$vehicles = $stmt->fetchAll();

jsonResponse([
    'success' => true,
    'data' => $vehicles,
    'pagination' => $pagination,
]);
