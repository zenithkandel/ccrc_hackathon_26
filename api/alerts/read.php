<?php
/**
 * API: Read Alerts
 * GET /api/alerts/read.php
 * 
 * Query params:
 *   id        - Fetch single alert
 *   route_id  - Get active alerts affecting a specific route
 *   active    - If "true", only show non-expired alerts
 *   page/limit - Pagination
 * 
 * Public endpoint — everyone can see alerts.
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

// ─── Single alert by ID ─────────────────────────────
$id = getIntParam('id');
if ($id > 0) {
    $stmt = $pdo->prepare('
        SELECT al.*, adm.name AS issued_by_name
        FROM alerts al
        LEFT JOIN admins adm ON al.issued_by = adm.admin_id
        WHERE al.alert_id = :id
    ');
    $stmt->execute(['id' => $id]);
    $alert = $stmt->fetch();

    if (!$alert) {
        jsonResponse(['success' => false, 'message' => 'Alert not found.'], 404);
    }

    $alert['routes_affected'] = json_decode($alert['routes_affected'], true);

    // Expand route names
    if (!empty($alert['routes_affected'])) {
        $rids = $alert['routes_affected'];
        $placeholders = implode(',', array_fill(0, count($rids), '?'));
        $rStmt = $pdo->prepare("SELECT route_id, name FROM routes WHERE route_id IN ($placeholders)");
        $rStmt->execute($rids);
        $alert['routes_expanded'] = $rStmt->fetchAll();
    }

    jsonResponse(['success' => true, 'data' => $alert]);
}

// ─── Active alerts for a specific route ─────────────
$routeIdFilter = getIntParam('route_id');
if ($routeIdFilter > 0) {
    $stmt = $pdo->prepare("
        SELECT al.*, adm.name AS issued_by_name
        FROM alerts al
        LEFT JOIN admins adm ON al.issued_by = adm.admin_id
        WHERE JSON_CONTAINS(al.routes_affected, CAST(:route_id AS JSON))
        AND (al.expires_at IS NULL OR al.expires_at > NOW())
        ORDER BY al.reported_at DESC
    ");
    $stmt->execute(['route_id' => $routeIdFilter]);
    $alerts = $stmt->fetchAll();

    foreach ($alerts as &$a) {
        $a['routes_affected'] = json_decode($a['routes_affected'], true);
    }

    jsonResponse(['success' => true, 'data' => $alerts, 'count' => count($alerts)]);
}

// ─── Filtered list with pagination ──────────────────
$conditions = [];
$params = [];

$active = getParam('active');
if ($active === 'true') {
    $conditions[] = '(al.expires_at IS NULL OR al.expires_at > NOW())';
}

$where = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM alerts al $where");
$countStmt->execute($params);
$totalItems = (int) $countStmt->fetchColumn();

$page = getIntParam('page', 1);
$perPage = getIntParam('limit', ITEMS_PER_PAGE);
$pagination = paginate($totalItems, $page, $perPage);

$stmt = $pdo->prepare("
    SELECT al.*, adm.name AS issued_by_name
    FROM alerts al
    LEFT JOIN admins adm ON al.issued_by = adm.admin_id
    $where
    ORDER BY al.reported_at DESC
    LIMIT :lim OFFSET :off
");

foreach ($params as $key => $val) {
    $stmt->bindValue(":$key", $val);
}
$stmt->bindValue(':lim', $pagination['perPage'], PDO::PARAM_INT);
$stmt->bindValue(':off', $pagination['offset'], PDO::PARAM_INT);
$stmt->execute();
$alerts = $stmt->fetchAll();

foreach ($alerts as &$a) {
    $a['routes_affected'] = json_decode($a['routes_affected'], true);
}

jsonResponse([
    'success' => true,
    'data' => $alerts,
    'pagination' => $pagination,
]);
