<?php
/**
 * SAWARI — Routes API
 *
 * Actions:
 *   list     – GET  – Paginated list with status/search filters
 *   get      – GET  – Single route by ID (with parsed location_list)
 *   create   – POST – Create route with ordered location_list JSON
 *   update   – POST – Update route
 *   approve  – POST – Approve pending route
 *   reject   – POST – Reject with reason
 *   delete   – POST – Hard delete
 */

require_once __DIR__ . '/config.php';

$action = getAction();

switch ($action) {

    /* ── List ────────────────────────────────────────────── */
    case 'list':
        requireAdminAPI();
        $db = getDB();

        $where = [];
        $params = [];

        $status = getString('status');
        if ($status && in_array($status, ['pending', 'approved', 'rejected'])) {
            $where[] = 'r.status = :status';
            $params[':status'] = $status;
        }

        $q = getString('q');
        if ($q) {
            $where[] = 'r.name LIKE :q';
            $params[':q'] = '%' . $q . '%';
        }

        $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $countStmt = $db->prepare("SELECT COUNT(*) FROM routes r $whereSQL");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();
        $pagination = paginate($total);

        $sql = "SELECT r.*, a.name AS agent_name, adm.name AS approved_by_name
                FROM routes r
                LEFT JOIN agents a ON r.updated_by = a.agent_id
                LEFT JOIN admins adm ON r.approved_by = adm.admin_id
                $whereSQL
                ORDER BY r.route_id DESC
                LIMIT :offset, :limit";

        $stmt = $db->prepare($sql);
        foreach ($params as $k => $val) {
            $stmt->bindValue($k, $val);
        }
        $stmt->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
        $stmt->bindValue(':limit', $pagination['per_page'], PDO::PARAM_INT);
        $stmt->execute();

        $routes = $stmt->fetchAll();
        // Parse location_list JSON for each
        foreach ($routes as &$r) {
            $r['stop_count'] = 0;
            if ($r['location_list']) {
                $parsed = json_decode($r['location_list'], true);
                $r['stop_count'] = is_array($parsed) ? count($parsed) : 0;
            }
        }
        unset($r);

        jsonResponse(['success' => true, 'routes' => $routes, 'pagination' => $pagination]);
        break;

    /* ── Get ─────────────────────────────────────────────── */
    case 'get':
        $db = getDB();
        $id = getInt('id');
        if (!$id)
            jsonError('Route ID required.');

        $stmt = $db->prepare("SELECT r.*, a.name AS agent_name, adm.name AS approved_by_name
                              FROM routes r
                              LEFT JOIN agents a ON r.updated_by = a.agent_id
                              LEFT JOIN admins adm ON r.approved_by = adm.admin_id
                              WHERE r.route_id = :id");
        $stmt->execute([':id' => $id]);
        $route = $stmt->fetch();
        if (!$route)
            jsonError('Route not found.', 404);

        $route['location_list_parsed'] = $route['location_list'] ? json_decode($route['location_list'], true) : [];

        jsonResponse(['success' => true, 'route' => $route]);
        break;

    /* ── Create ──────────────────────────────────────────── */
    case 'create':
        requireAdminAPI();
        validateCsrf();
        $db = getDB();

        $name = postString('name');
        $desc = postString('description');
        $fareBase = isset($_POST['fare_base']) ? floatval($_POST['fare_base']) : null;
        $fareKm = isset($_POST['fare_per_km']) ? floatval($_POST['fare_per_km']) : null;
        $locList = postString('location_list'); // JSON string

        if (!$name)
            jsonError('Route name is required.');
        if (!$locList)
            jsonError('Location list is required.');

        // Validate JSON
        $parsed = json_decode($locList, true);
        if (!is_array($parsed) || count($parsed) < 2) {
            jsonError('Route must have at least 2 stops.');
        }

        $stmt = $db->prepare("INSERT INTO routes (name, description, location_list, fare_base, fare_per_km, status, approved_by, updated_at)
                              VALUES (:name, :desc, :locs, :fb, :fk, 'approved', :admin, NOW())");
        $stmt->execute([
            ':name' => $name,
            ':desc' => $desc,
            ':locs' => $locList,
            ':fb' => $fareBase,
            ':fk' => $fareKm,
            ':admin' => getAdminId()
        ]);

        jsonSuccess('Route created successfully.');
        break;

    /* ── Update ──────────────────────────────────────────── */
    case 'update':
        requireAdminAPI();
        validateCsrf();
        $db = getDB();

        $id = postInt('route_id');
        $name = postString('name');
        $desc = postString('description');
        $fareBase = isset($_POST['fare_base']) ? floatval($_POST['fare_base']) : null;
        $fareKm = isset($_POST['fare_per_km']) ? floatval($_POST['fare_per_km']) : null;
        $locList = postString('location_list');

        if (!$id)
            jsonError('Route ID required.');
        if (!$name)
            jsonError('Route name is required.');

        if ($locList) {
            $parsed = json_decode($locList, true);
            if (!is_array($parsed) || count($parsed) < 2) {
                jsonError('Route must have at least 2 stops.');
            }
        }

        $stmt = $db->prepare("UPDATE routes SET name = :name, description = :desc,
                              location_list = :locs, fare_base = :fb, fare_per_km = :fk, updated_at = NOW()
                              WHERE route_id = :id");
        $stmt->execute([
            ':name' => $name,
            ':desc' => $desc,
            ':locs' => $locList,
            ':fb' => $fareBase,
            ':fk' => $fareKm,
            ':id' => $id
        ]);

        jsonSuccess('Route updated.');
        break;

    /* ── Approve ─────────────────────────────────────────── */
    case 'approve':
        requireAdminAPI();
        validateCsrf();
        $db = getDB();
        $id = postInt('id');
        if (!$id)
            jsonError('Route ID required.');

        $db->prepare("UPDATE routes SET status = 'approved', approved_by = :admin, updated_at = NOW() WHERE route_id = :id")
            ->execute([':admin' => getAdminId(), ':id' => $id]);

        $db->prepare("UPDATE contributions SET status = 'approved', reviewed_by = :admin, reviewed_at = NOW()
                      WHERE contribution_id = (SELECT contribution_id FROM routes WHERE route_id = :id)")
            ->execute([':admin' => getAdminId(), ':id' => $id]);

        jsonSuccess('Route approved.');
        break;

    /* ── Reject ──────────────────────────────────────────── */
    case 'reject':
        requireAdminAPI();
        validateCsrf();
        $db = getDB();
        $id = postInt('id');
        $reason = postString('reason');
        if (!$id)
            jsonError('Route ID required.');
        if (!$reason)
            jsonError('Rejection reason required.');

        $db->prepare("UPDATE routes SET status = 'rejected', approved_by = :admin, updated_at = NOW() WHERE route_id = :id")
            ->execute([':admin' => getAdminId(), ':id' => $id]);

        $db->prepare("UPDATE contributions SET status = 'rejected', reviewed_by = :admin, reviewed_at = NOW(), rejection_reason = :reason
                      WHERE contribution_id = (SELECT contribution_id FROM routes WHERE route_id = :id)")
            ->execute([':admin' => getAdminId(), ':id' => $id, ':reason' => $reason]);

        jsonSuccess('Route rejected.');
        break;

    /* ── Delete ──────────────────────────────────────────── */
    case 'delete':
        requireAdminAPI();
        validateCsrf();
        $db = getDB();
        $id = postInt('id');
        if (!$id)
            jsonError('Route ID required.');

        $db->prepare("DELETE FROM routes WHERE route_id = :id")->execute([':id' => $id]);
        jsonSuccess('Route deleted.');
        break;

    /* ══════════════════════════════════════════════════════
     *  AGENT ENDPOINTS
     * ══════════════════════════════════════════════════════ */

    /* ── Submit Route (Agent) ─────────────────────────────── */
    case 'submit':
        requireAgentAPI();
        validateCsrf();
        $db = getDB();

        $name = postString('name');
        $desc = postString('description');
        $locationList = postString('location_list');
        $fareBase = isset($_POST['fare_base']) ? floatval($_POST['fare_base']) : null;
        $farePerKm = isset($_POST['fare_per_km']) ? floatval($_POST['fare_per_km']) : null;
        $notes = postString('notes');

        if (!$name) jsonError('Route name is required.');
        if (!$locationList) jsonError('At least 2 stops are required.');

        $stops = json_decode($locationList, true);
        if (!is_array($stops) || count($stops) < 2) jsonError('A route must have at least 2 stops.');

        $db->beginTransaction();
        try {
            // Create contribution
            $cStmt = $db->prepare("INSERT INTO contributions (agent_id, type, status, notes) VALUES (:agent, 'route', 'pending', :notes)");
            $cStmt->execute([':agent' => getAgentId(), ':notes' => $notes]);
            $contribId = (int) $db->lastInsertId();

            // Create route
            $rStmt = $db->prepare("INSERT INTO routes (name, description, location_list, fare_base, fare_per_km, status, contribution_id, updated_by)
                                   VALUES (:name, :desc, :locs, :fare_base, :fare_km, 'pending', :cid, :agent)");
            $rStmt->execute([
                ':name' => $name, ':desc' => $desc,
                ':locs' => json_encode($stops),
                ':fare_base' => $fareBase, ':fare_km' => $farePerKm,
                ':cid' => $contribId, ':agent' => getAgentId()
            ]);

            // Update agent count
            $db->prepare("UPDATE agents SET contributions_count = contributions_count + 1 WHERE agent_id = :id")
                ->execute([':id' => getAgentId()]);

            $db->commit();
            jsonSuccess('Route submitted for review.');
        } catch (Exception $e) {
            $db->rollBack();
            error_log('Route submit error: ' . $e->getMessage());
            jsonError('Failed to submit route. Please try again.');
        }
        break;

    default:
        jsonError('Unknown action.', 400);
}
