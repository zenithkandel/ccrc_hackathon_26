<?php
/**
 * API: Read Agents
 * GET /api/agents/read.php
 * 
 * Query params:
 *   id          - Fetch single agent profile
 *   leaderboard - If "true", return agents sorted by total contributions
 *   search      - Search by name/email
 *   page/limit  - Pagination
 * 
 * Admin sees all. Agent sees own profile only (unless leaderboard).
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$pdo = getDBConnection();

// ─── Single agent profile ───────────────────────────
$id = getIntParam('id');
if ($id > 0) {
    // Agents can only view their own profile; admin can view any
    if (isAgent() && $id != getCurrentUserId()) {
        jsonResponse(['success' => false, 'message' => 'Access denied.'], 403);
    }

    $stmt = $pdo->prepare('
        SELECT agent_id, name, email, phone_number, image_path, contributions_summary, 
               last_login, joined_at
        FROM agents WHERE agent_id = :id
    ');
    $stmt->execute(['id' => $id]);
    $agent = $stmt->fetch();

    if (!$agent) {
        jsonResponse(['success' => false, 'message' => 'Agent not found.'], 404);
    }

    $agent['contributions_summary'] = json_decode($agent['contributions_summary'], true) ?? [
        'vehicle' => 0,
        'location' => 0,
        'route' => 0,
    ];

    // Include recent contributions count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM contributions WHERE proposed_by = :aid AND status = 'accepted'
    ");
    $stmt->execute(['aid' => $id]);
    $agent['total_accepted'] = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM contributions WHERE proposed_by = :aid AND status = 'pending'
    ");
    $stmt->execute(['aid' => $id]);
    $agent['total_pending'] = (int) $stmt->fetchColumn();

    jsonResponse(['success' => true, 'data' => $agent]);
}

// ─── Leaderboard (public) ───────────────────────────
$leaderboard = getParam('leaderboard');
if ($leaderboard === 'true') {
    $limit = getIntParam('limit', 20);
    $limit = min($limit, 100);

    // Calculate total contributions from JSON summary
    $stmt = $pdo->prepare("
        SELECT agent_id, name, image_path, contributions_summary,
               (
                   COALESCE(JSON_EXTRACT(contributions_summary, '$.vehicle'), 0) +
                   COALESCE(JSON_EXTRACT(contributions_summary, '$.location'), 0) +
                   COALESCE(JSON_EXTRACT(contributions_summary, '$.route'), 0)
               ) AS total_contributions
        FROM agents
        HAVING total_contributions > 0
        ORDER BY total_contributions DESC
        LIMIT :lim
    ");
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $leaders = $stmt->fetchAll();

    // Parse JSON summaries
    foreach ($leaders as &$l) {
        $l['contributions_summary'] = json_decode($l['contributions_summary'], true);
    }

    jsonResponse(['success' => true, 'data' => $leaders]);
}

// ─── Admin agent list with pagination ───────────────
if (!isAdmin()) {
    jsonResponse(['success' => false, 'message' => 'Admin access required.'], 403);
}

$conditions = [];
$params = [];

$search = getParam('search');
if ($search) {
    $conditions[] = '(a.name LIKE :search OR a.email LIKE :search2)';
    $params['search'] = '%' . trim($search) . '%';
    $params['search2'] = '%' . trim($search) . '%';
}

$where = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM agents a $where");
$countStmt->execute($params);
$totalItems = (int) $countStmt->fetchColumn();

$page = getIntParam('page', 1);
$perPage = getIntParam('limit', ITEMS_PER_PAGE);
$pagination = paginate($totalItems, $page, $perPage);

$stmt = $pdo->prepare("
    SELECT agent_id, name, email, phone_number, image_path, contributions_summary,
           last_login, joined_at
    FROM agents a
    $where
    ORDER BY a.joined_at DESC
    LIMIT :lim OFFSET :off
");

foreach ($params as $key => $val) {
    $stmt->bindValue(":$key", $val);
}
$stmt->bindValue(':lim', $pagination['perPage'], PDO::PARAM_INT);
$stmt->bindValue(':off', $pagination['offset'], PDO::PARAM_INT);
$stmt->execute();
$agents = $stmt->fetchAll();

foreach ($agents as &$a) {
    $a['contributions_summary'] = json_decode($a['contributions_summary'], true);
}

jsonResponse([
    'success' => true,
    'data' => $agents,
    'pagination' => $pagination,
]);
