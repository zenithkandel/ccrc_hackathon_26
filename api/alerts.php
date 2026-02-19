<?php
/**
 * SAWARI — Alerts API
 *
 * Actions:
 *   list    – GET  – Paginated list with status/severity filters
 *   get     – GET  – Single alert
 *   create  – POST – Create new alert (admin)
 *   update  – POST – Update alert
 *   resolve – POST – Mark alert resolved
 *   delete  – POST – Hard delete
 *   active  – GET  – Public: all currently active alerts
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
        if ($status && in_array($status, ['active', 'resolved', 'expired'])) {
            $where[] = 'al.status = :status';
            $params[':status'] = $status;
        }

        $severity = getString('severity');
        if ($severity && in_array($severity, ['low', 'medium', 'high', 'critical'])) {
            $where[] = 'al.severity = :severity';
            $params[':severity'] = $severity;
        }

        $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $countStmt = $db->prepare("SELECT COUNT(*) FROM alerts al $whereSQL");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();
        $pagination = paginate($total);

        $sql = "SELECT al.*, adm.name AS created_by_name, r.name AS route_name
                FROM alerts al
                LEFT JOIN admins adm ON al.created_by = adm.admin_id
                LEFT JOIN routes r ON al.route_id = r.route_id
                $whereSQL
                ORDER BY FIELD(al.severity, 'critical', 'high', 'medium', 'low'), al.created_at DESC
                LIMIT :offset, :limit";

        $stmt = $db->prepare($sql);
        foreach ($params as $k => $val) {
            $stmt->bindValue($k, $val);
        }
        $stmt->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
        $stmt->bindValue(':limit', $pagination['per_page'], PDO::PARAM_INT);
        $stmt->execute();

        jsonResponse(['success' => true, 'alerts' => $stmt->fetchAll(), 'pagination' => $pagination]);
        break;

    /* ── Get ─────────────────────────────────────────────── */
    case 'get':
        requireAdminAPI();
        $db = getDB();
        $id = getInt('id');
        if (!$id)
            jsonError('Alert ID required.');

        $stmt = $db->prepare("SELECT al.*, adm.name AS created_by_name, r.name AS route_name
                              FROM alerts al
                              LEFT JOIN admins adm ON al.created_by = adm.admin_id
                              LEFT JOIN routes r ON al.route_id = r.route_id
                              WHERE al.alert_id = :id");
        $stmt->execute([':id' => $id]);
        $alert = $stmt->fetch();
        if (!$alert)
            jsonError('Alert not found.', 404);

        jsonResponse(['success' => true, 'alert' => $alert]);
        break;

    /* ── Create ──────────────────────────────────────────── */
    case 'create':
        requireAdminAPI();
        validateCsrf();
        $db = getDB();

        $title = postString('title');
        $desc = postString('description');
        $severity = postString('severity') ?: 'medium';
        $routeId = postInt('route_id');
        $expiresAt = postString('expires_at');

        if (!$title)
            jsonError('Title is required.');
        if (!$desc)
            jsonError('Description is required.');

        $stmt = $db->prepare("INSERT INTO alerts (route_id, title, description, severity, created_by, expires_at)
                              VALUES (:route, :title, :desc, :sev, :admin, :exp)");
        $stmt->execute([
            ':route' => $routeId ?: null,
            ':title' => $title,
            ':desc' => $desc,
            ':sev' => $severity,
            ':admin' => getAdminId(),
            ':exp' => $expiresAt ?: null
        ]);

        jsonSuccess('Alert created.');
        break;

    /* ── Update ──────────────────────────────────────────── */
    case 'update':
        requireAdminAPI();
        validateCsrf();
        $db = getDB();

        $id = postInt('alert_id');
        $title = postString('title');
        $desc = postString('description');
        $severity = postString('severity');
        $routeId = postInt('route_id');
        $expiresAt = postString('expires_at');

        if (!$id)
            jsonError('Alert ID required.');
        if (!$title)
            jsonError('Title is required.');

        $stmt = $db->prepare("UPDATE alerts SET route_id = :route, title = :title, description = :desc,
                              severity = :sev, expires_at = :exp WHERE alert_id = :id");
        $stmt->execute([
            ':route' => $routeId ?: null,
            ':title' => $title,
            ':desc' => $desc,
            ':sev' => $severity,
            ':exp' => $expiresAt ?: null,
            ':id' => $id
        ]);

        jsonSuccess('Alert updated.');
        break;

    /* ── Resolve ─────────────────────────────────────────── */
    case 'resolve':
        requireAdminAPI();
        validateCsrf();
        $db = getDB();
        $id = postInt('id');
        if (!$id)
            jsonError('Alert ID required.');

        $db->prepare("UPDATE alerts SET status = 'resolved', resolved_at = NOW() WHERE alert_id = :id")
            ->execute([':id' => $id]);

        jsonSuccess('Alert resolved.');
        break;

    /* ── Delete ──────────────────────────────────────────── */
    case 'delete':
        requireAdminAPI();
        validateCsrf();
        $db = getDB();
        $id = postInt('id');
        if (!$id)
            jsonError('Alert ID required.');

        $db->prepare("DELETE FROM alerts WHERE alert_id = :id")->execute([':id' => $id]);
        jsonSuccess('Alert deleted.');
        break;

    /* ── Active alerts (public) ──────────────────────────── */
    case 'active':
        $db = getDB();
        $stmt = $db->prepare("SELECT al.alert_id, al.route_id, al.title, al.description,
                                     al.severity, al.created_at, al.expires_at, r.name AS route_name
                              FROM alerts al
                              LEFT JOIN routes r ON al.route_id = r.route_id
                              WHERE al.status = 'active'
                              AND (al.expires_at IS NULL OR al.expires_at > NOW())
                              ORDER BY FIELD(al.severity, 'critical', 'high', 'medium', 'low'), al.created_at DESC");
        $stmt->execute();

        jsonResponse(['success' => true, 'alerts' => $stmt->fetchAll()]);
        break;

    default:
        jsonError('Unknown action.', 400);
}
