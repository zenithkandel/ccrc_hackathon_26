<?php
/**
 * API: Read Suggestions
 * GET /api/suggestions/read.php
 * 
 * Admin-only endpoint to view user suggestions.
 * 
 * Query params:
 *   id         - Fetch single suggestion
 *   status     - Filter (pending|reviewed|resolved)
 *   type       - Filter (complaint|suggestion|correction|appreciation)
 *   page/limit - Pagination
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/validation.php';

if (!isAdmin()) {
    jsonResponse(['success' => false, 'message' => 'Admin access required.'], 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$pdo = getDBConnection();

// ─── Single suggestion by ID ────────────────────────
$id = getIntParam('id');
if ($id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM suggestions WHERE suggestion_id = :id');
    $stmt->execute(['id' => $id]);
    $suggestion = $stmt->fetch();

    if (!$suggestion) {
        jsonResponse(['success' => false, 'message' => 'Suggestion not found.'], 404);
    }

    jsonResponse(['success' => true, 'data' => $suggestion]);
}

// ─── Filtered list with pagination ──────────────────
$conditions = [];
$params = [];

$status = getParam('status');
if ($status && validateEnum($status, ['pending', 'reviewed', 'resolved'])) {
    $conditions[] = 's.status = :status';
    $params['status'] = $status;
}

$type = getParam('type');
if ($type && validateEnum($type, ['complaint', 'suggestion', 'correction', 'appreciation'])) {
    $conditions[] = 's.type = :type';
    $params['type'] = $type;
}

$where = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM suggestions s $where");
$countStmt->execute($params);
$totalItems = (int) $countStmt->fetchColumn();

$page = getIntParam('page', 1);
$perPage = getIntParam('limit', ITEMS_PER_PAGE);
$pagination = paginate($totalItems, $page, $perPage);

$stmt = $pdo->prepare("
    SELECT * FROM suggestions s
    $where
    ORDER BY s.submitted_at DESC
    LIMIT :lim OFFSET :off
");

foreach ($params as $key => $val) {
    $stmt->bindValue(":$key", $val);
}
$stmt->bindValue(':lim', $pagination['perPage'], PDO::PARAM_INT);
$stmt->bindValue(':off', $pagination['offset'], PDO::PARAM_INT);
$stmt->execute();
$suggestions = $stmt->fetchAll();

jsonResponse([
    'success' => true,
    'data' => $suggestions,
    'pagination' => $pagination,
]);
