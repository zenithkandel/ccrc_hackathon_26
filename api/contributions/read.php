<?php
/**
 * API: Read Contributions
 * GET /api/contributions/read.php
 * 
 * Query params:
 *   id         - Fetch single contribution by ID
 *   status     - Filter (pending|accepted|rejected)
 *   type       - Filter (location|route|vehicle)
 *   agent_id   - Filter by proposing agent
 *   page/limit - Pagination
 * 
 * Admin sees all. Agent sees own.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/validation.php';

if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => 'Authentication required.'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$pdo = getDBConnection();

// ─── Single contribution by ID ──────────────────────
$id = getIntParam('id');
if ($id > 0) {
    $stmt = $pdo->prepare('
        SELECT c.*, ag.name AS proposed_by_name, adm.name AS accepted_by_name
        FROM contributions c
        LEFT JOIN agents ag ON c.proposed_by = ag.agent_id
        LEFT JOIN admins adm ON c.accepted_by = adm.admin_id
        WHERE c.contribution_id = :id
    ');
    $stmt->execute(['id' => $id]);
    $contrib = $stmt->fetch();

    if (!$contrib) {
        jsonResponse(['success' => false, 'message' => 'Contribution not found.'], 404);
    }

    // Agents can only see their own
    if (isAgent() && $contrib['proposed_by'] != getCurrentUserId()) {
        jsonResponse(['success' => false, 'message' => 'Access denied.'], 403);
    }

    // Attach the associated entry summary
    $entry = null;
    if ($contrib['type'] === 'location') {
        $stmt = $pdo->prepare('SELECT location_id, name, type, status FROM locations WHERE location_id = :eid');
        $stmt->execute(['eid' => $contrib['associated_entry_id']]);
        $entry = $stmt->fetch();
    } elseif ($contrib['type'] === 'route') {
        $stmt = $pdo->prepare('SELECT route_id, name, status FROM routes WHERE route_id = :eid');
        $stmt->execute(['eid' => $contrib['associated_entry_id']]);
        $entry = $stmt->fetch();
    } elseif ($contrib['type'] === 'vehicle') {
        $stmt = $pdo->prepare('SELECT vehicle_id, name, type, status FROM vehicles WHERE vehicle_id = :eid');
        $stmt->execute(['eid' => $contrib['associated_entry_id']]);
        $entry = $stmt->fetch();
    }
    $contrib['associated_entry'] = $entry;

    jsonResponse(['success' => true, 'data' => $contrib]);
}

// ─── Filtered list with pagination ──────────────────
$conditions = [];
$params = [];

// Agents can only see their own contributions
if (isAgent()) {
    $conditions[] = 'c.proposed_by = :current_agent';
    $params['current_agent'] = getCurrentUserId();
}

$status = getParam('status');
if ($status && validateEnum($status, ['pending', 'accepted', 'rejected'])) {
    $conditions[] = 'c.status = :status';
    $params['status'] = $status;
}

$type = getParam('type');
if ($type && validateEnum($type, ['location', 'route', 'vehicle'])) {
    $conditions[] = 'c.type = :type';
    $params['type'] = $type;
}

$agentId = getIntParam('agent_id');
if ($agentId > 0 && isAdmin()) {
    // Replace agent filter if admin supplies it
    $conditions = array_filter($conditions, fn($c) => strpos($c, 'current_agent') === false);
    unset($params['current_agent']);
    $conditions[] = 'c.proposed_by = :agent_id';
    $params['agent_id'] = $agentId;
}

$where = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM contributions c $where");
$countStmt->execute($params);
$totalItems = (int) $countStmt->fetchColumn();

$page = getIntParam('page', 1);
$perPage = getIntParam('limit', ITEMS_PER_PAGE);
$pagination = paginate($totalItems, $page, $perPage);

$stmt = $pdo->prepare("
    SELECT c.*, ag.name AS proposed_by_name, adm.name AS accepted_by_name
    FROM contributions c
    LEFT JOIN agents ag ON c.proposed_by = ag.agent_id
    LEFT JOIN admins adm ON c.accepted_by = adm.admin_id
    $where
    ORDER BY c.proposed_at DESC
    LIMIT :lim OFFSET :off
");

foreach ($params as $key => $val) {
    $stmt->bindValue(":$key", $val);
}
$stmt->bindValue(':lim', $pagination['perPage'], PDO::PARAM_INT);
$stmt->bindValue(':off', $pagination['offset'], PDO::PARAM_INT);
$stmt->execute();
$contributions = $stmt->fetchAll();

jsonResponse([
    'success' => true,
    'data' => $contributions,
    'pagination' => $pagination,
]);
