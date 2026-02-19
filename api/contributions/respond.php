<?php
/**
 * API: Respond to Contribution
 * POST /api/contributions/respond.php
 * 
 * Admin accepts or rejects a pending contribution.
 * Updates contribution status, associated entity status, and agent's contributions_summary.
 * 
 * Body params: contribution_id, action (accept|reject)
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/validation.php';

if (!isAdmin()) {
    jsonResponse(['success' => false, 'message' => 'Admin access required.'], 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$input = $_POST;
if (empty($input)) {
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true) ?? [];
}

$contributionId = (int) ($input['contribution_id'] ?? $_GET['id'] ?? 0);
$action = $input['action'] ?? '';

if ($contributionId <= 0) {
    jsonResponse(['success' => false, 'message' => 'Valid contribution_id is required.'], 400);
}
if (!in_array($action, ['accept', 'reject'])) {
    jsonResponse(['success' => false, 'message' => 'Action must be "accept" or "reject".'], 400);
}

$pdo = getDBConnection();

$stmt = $pdo->prepare('SELECT * FROM contributions WHERE contribution_id = :id');
$stmt->execute(['id' => $contributionId]);
$contrib = $stmt->fetch();

if (!$contrib) {
    jsonResponse(['success' => false, 'message' => 'Contribution not found.'], 404);
}

if ($contrib['status'] !== 'pending') {
    jsonResponse(['success' => false, 'message' => 'This contribution has already been ' . $contrib['status'] . '.'], 409);
}

try {
    $pdo->beginTransaction();

    $contribStatus = ($action === 'accept') ? 'accepted' : 'rejected';
    $entityStatus = ($action === 'accept') ? 'approved' : 'rejected';

    // Update contribution status
    $stmt = $pdo->prepare('
        UPDATE contributions SET 
            status = :status, accepted_by = :admin_id, responded_at = NOW()
        WHERE contribution_id = :id
    ');
    $stmt->execute([
        'status' => $contribStatus,
        'admin_id' => getCurrentUserId(),
        'id' => $contributionId,
    ]);

    // Update associated entity status
    $entryId = $contrib['associated_entry_id'];
    $type = $contrib['type'];

    $tableMap = [
        'location' => ['table' => 'locations', 'pk' => 'location_id'],
        'route' => ['table' => 'routes', 'pk' => 'route_id'],
        'vehicle' => ['table' => 'vehicles', 'pk' => 'vehicle_id'],
    ];

    if (isset($tableMap[$type])) {
        $tbl = $tableMap[$type]['table'];
        $pk = $tableMap[$type]['pk'];

        $stmt = $pdo->prepare("
            UPDATE $tbl SET status = :status, approved_by = :admin_id, updated_at = NOW()
            WHERE $pk = :entry_id
        ");
        $stmt->execute([
            'status' => $entityStatus,
            'admin_id' => ($action === 'accept') ? getCurrentUserId() : null,
            'entry_id' => $entryId,
        ]);
    }

    // Update agent's contributions_summary if accepted
    if ($action === 'accept' && $contrib['proposed_by']) {
        $agentId = $contrib['proposed_by'];

        $stmt = $pdo->prepare('SELECT contributions_summary FROM agents WHERE agent_id = :aid');
        $stmt->execute(['aid' => $agentId]);
        $agent = $stmt->fetch();

        if ($agent) {
            $summary = json_decode($agent['contributions_summary'], true) ?? [
                'vehicle' => 0,
                'location' => 0,
                'route' => 0,
            ];
            $summary[$type] = ($summary[$type] ?? 0) + 1;

            $stmt = $pdo->prepare('UPDATE agents SET contributions_summary = :summary WHERE agent_id = :aid');
            $stmt->execute(['summary' => json_encode($summary), 'aid' => $agentId]);
        }
    }

    $pdo->commit();

    jsonResponse([
        'success' => true,
        'message' => "Contribution {$contribStatus} successfully.",
        'contribution_id' => $contributionId,
        'entity_type' => $type,
        'entity_id' => $entryId,
        'new_status' => $entityStatus,
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    error_log('Respond Contribution Error: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Failed to respond to contribution.'], 500);
}
