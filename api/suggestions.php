<?php
/**
 * SAWARI — Suggestions API
 *
 * Actions:
 *   list     – GET  – Paginated list with status/type filters (admin)
 *   get      – GET  – Single suggestion
 *   submit   – POST – Public submit (no auth)
 *   review   – POST – Admin marks as reviewed/implemented/dismissed
 *   delete   – POST – Hard delete
 */

require_once __DIR__ . '/config.php';

$action = getAction();

switch ($action) {

    /* ── List (admin) ────────────────────────────────────── */
    case 'list':
        requireAdminAPI();
        $db = getDB();

        $where = [];
        $params = [];

        $status = getString('status');
        if ($status && in_array($status, ['pending', 'reviewed', 'implemented', 'dismissed'])) {
            $where[] = 's.status = :status';
            $params[':status'] = $status;
        }

        $type = getString('type');
        if ($type && in_array($type, ['missing_stop', 'route_correction', 'new_route', 'general'])) {
            $where[] = 's.type = :type';
            $params[':type'] = $type;
        }

        $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $countStmt = $db->prepare("SELECT COUNT(*) FROM suggestions s $whereSQL");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();
        $pagination = paginate($total);

        $sql = "SELECT s.*, adm.name AS reviewer_name, r.name AS route_name
                FROM suggestions s
                LEFT JOIN admins adm ON s.reviewed_by = adm.admin_id
                LEFT JOIN routes r ON s.related_route_id = r.route_id
                $whereSQL
                ORDER BY s.created_at DESC
                LIMIT :offset, :limit";

        $stmt = $db->prepare($sql);
        foreach ($params as $k => $val) {
            $stmt->bindValue($k, $val);
        }
        $stmt->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
        $stmt->bindValue(':limit', $pagination['per_page'], PDO::PARAM_INT);
        $stmt->execute();

        jsonResponse(['success' => true, 'suggestions' => $stmt->fetchAll(), 'pagination' => $pagination]);
        break;

    /* ── Get ─────────────────────────────────────────────── */
    case 'get':
        requireAdminAPI();
        $db = getDB();
        $id = getInt('id');
        if (!$id)
            jsonError('Suggestion ID required.');

        $stmt = $db->prepare("SELECT s.*, adm.name AS reviewer_name, r.name AS route_name
                              FROM suggestions s
                              LEFT JOIN admins adm ON s.reviewed_by = adm.admin_id
                              LEFT JOIN routes r ON s.related_route_id = r.route_id
                              WHERE s.suggestion_id = :id");
        $stmt->execute([':id' => $id]);
        $sug = $stmt->fetch();
        if (!$sug)
            jsonError('Suggestion not found.', 404);

        jsonResponse(['success' => true, 'suggestion' => $sug]);
        break;

    /* ── Submit (public) ─────────────────────────────────── */
    case 'submit':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST')
            jsonError('POST required.', 405);
        $db = getDB();

        $title = postString('title');
        $desc = postString('description');
        $type = postString('type') ?: 'general';
        $lat = isset($_POST['latitude']) ? floatval($_POST['latitude']) : null;
        $lng = isset($_POST['longitude']) ? floatval($_POST['longitude']) : null;
        $routeId = postInt('related_route_id');

        $userType = 'user';
        $userId = session_id();

        // If agent is logged in
        if (isAgentLoggedIn()) {
            $userType = 'agent';
            $userId = (string) getAgentId();
        }

        if (!$title)
            jsonError('Title is required.');
        if (!$desc)
            jsonError('Description is required.');

        $stmt = $db->prepare("INSERT INTO suggestions (user_type, user_identifier, type, title, description, latitude, longitude, related_route_id)
                              VALUES (:ut, :uid, :type, :title, :desc, :lat, :lng, :rid)");
        $stmt->execute([
            ':ut' => $userType,
            ':uid' => $userId,
            ':type' => $type,
            ':title' => $title,
            ':desc' => $desc,
            ':lat' => $lat,
            ':lng' => $lng,
            ':rid' => $routeId ?: null
        ]);

        jsonSuccess('Suggestion submitted. Thank you!');
        break;

    /* ── Review (admin) ──────────────────────────────────── */
    case 'review':
        requireAdminAPI();
        validateCsrf();
        $db = getDB();

        $id = postInt('id');
        $status = postString('status');
        $notes = postString('review_notes');

        if (!$id)
            jsonError('Suggestion ID required.');
        if (!$status || !in_array($status, ['reviewed', 'implemented', 'dismissed'])) {
            jsonError('Valid status required: reviewed, implemented, or dismissed.');
        }

        $stmt = $db->prepare("UPDATE suggestions SET status = :status, reviewed_by = :admin,
                              reviewed_at = NOW(), review_notes = :notes WHERE suggestion_id = :id");
        $stmt->execute([
            ':status' => $status,
            ':admin' => getAdminId(),
            ':notes' => $notes,
            ':id' => $id
        ]);

        jsonSuccess('Suggestion marked as ' . $status . '.');
        break;

    /* ── Delete ──────────────────────────────────────────── */
    case 'delete':
        requireAdminAPI();
        validateCsrf();
        $db = getDB();
        $id = postInt('id');
        if (!$id)
            jsonError('Suggestion ID required.');

        $db->prepare("DELETE FROM suggestions WHERE suggestion_id = :id")->execute([':id' => $id]);
        jsonSuccess('Suggestion deleted.');
        break;

    default:
        jsonError('Unknown action.', 400);
}
