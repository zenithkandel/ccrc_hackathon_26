<?php
/**
 * SAWARI — Contributions API
 *
 * Actions:
 *   list     – GET  – Paginated list with status/type/agent filters
 *   get      – GET  – Single contribution with linked item details
 *   approve  – POST – Approve contribution (updates linked item)
 *   reject   – POST – Reject with rejection_reason
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
            $where[] = 'c.status = :status';
            $params[':status'] = $status;
        }

        $type = getString('type');
        if ($type && in_array($type, ['location', 'vehicle', 'route'])) {
            $where[] = 'c.type = :type';
            $params[':type'] = $type;
        }

        $agentId = getInt('agent_id');
        if ($agentId) {
            $where[] = 'c.agent_id = :agent_id';
            $params[':agent_id'] = $agentId;
        }

        $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $countStmt = $db->prepare("SELECT COUNT(*) FROM contributions c $whereSQL");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();
        $pagination = paginate($total);

        $sql = "SELECT c.*, ag.name AS agent_name, ag.email AS agent_email,
                       adm.name AS reviewer_name
                FROM contributions c
                LEFT JOIN agents ag ON c.agent_id = ag.agent_id
                LEFT JOIN admins adm ON c.reviewed_by = adm.admin_id
                $whereSQL
                ORDER BY c.created_at DESC
                LIMIT :offset, :limit";

        $stmt = $db->prepare($sql);
        foreach ($params as $k => $val) {
            $stmt->bindValue($k, $val);
        }
        $stmt->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
        $stmt->bindValue(':limit', $pagination['per_page'], PDO::PARAM_INT);
        $stmt->execute();

        $contributions = $stmt->fetchAll();

        // Fetch linked item name for each contribution
        foreach ($contributions as &$cont) {
            $cont['item_name'] = '—';
            switch ($cont['type']) {
                case 'location':
                    $s = $db->prepare("SELECT name FROM locations WHERE contribution_id = :cid LIMIT 1");
                    $s->execute([':cid' => $cont['contribution_id']]);
                    $row = $s->fetch();
                    if ($row)
                        $cont['item_name'] = $row['name'];
                    break;
                case 'vehicle':
                    $s = $db->prepare("SELECT name FROM vehicles WHERE contribution_id = :cid LIMIT 1");
                    $s->execute([':cid' => $cont['contribution_id']]);
                    $row = $s->fetch();
                    if ($row)
                        $cont['item_name'] = $row['name'];
                    break;
                case 'route':
                    $s = $db->prepare("SELECT name FROM routes WHERE contribution_id = :cid LIMIT 1");
                    $s->execute([':cid' => $cont['contribution_id']]);
                    $row = $s->fetch();
                    if ($row)
                        $cont['item_name'] = $row['name'];
                    break;
            }
        }
        unset($cont);

        jsonResponse(['success' => true, 'contributions' => $contributions, 'pagination' => $pagination]);
        break;

    /* ── Get Single ──────────────────────────────────────── */
    case 'get':
        requireAdminAPI();
        $db = getDB();
        $id = getInt('id');
        if (!$id)
            jsonError('Contribution ID required.');

        $stmt = $db->prepare("SELECT c.*, ag.name AS agent_name, ag.email AS agent_email,
                                     adm.name AS reviewer_name
                              FROM contributions c
                              LEFT JOIN agents ag ON c.agent_id = ag.agent_id
                              LEFT JOIN admins adm ON c.reviewed_by = adm.admin_id
                              WHERE c.contribution_id = :id");
        $stmt->execute([':id' => $id]);
        $cont = $stmt->fetch();
        if (!$cont)
            jsonError('Contribution not found.', 404);

        // Fetch linked item
        $cont['item'] = null;
        switch ($cont['type']) {
            case 'location':
                $s = $db->prepare("SELECT * FROM locations WHERE contribution_id = :cid LIMIT 1");
                $s->execute([':cid' => $id]);
                $cont['item'] = $s->fetch();
                break;
            case 'vehicle':
                $s = $db->prepare("SELECT * FROM vehicles WHERE contribution_id = :cid LIMIT 1");
                $s->execute([':cid' => $id]);
                $cont['item'] = $s->fetch();
                break;
            case 'route':
                $s = $db->prepare("SELECT * FROM routes WHERE contribution_id = :cid LIMIT 1");
                $s->execute([':cid' => $id]);
                $row = $s->fetch();
                if ($row) {
                    $row['location_list_parsed'] = $row['location_list'] ? json_decode($row['location_list'], true) : [];
                }
                $cont['item'] = $row;
                break;
        }

        jsonResponse(['success' => true, 'contribution' => $cont]);
        break;

    /* ── Approve ─────────────────────────────────────────── */
    case 'approve':
        requireAdminAPI();
        validateCsrf();
        $db = getDB();
        $id = postInt('id');
        if (!$id)
            jsonError('Contribution ID required.');

        // Get contribution
        $stmt = $db->prepare("SELECT * FROM contributions WHERE contribution_id = :id");
        $stmt->execute([':id' => $id]);
        $cont = $stmt->fetch();
        if (!$cont)
            jsonError('Contribution not found.');

        // Update contribution
        $db->prepare("UPDATE contributions SET status = 'approved', reviewed_by = :admin, reviewed_at = NOW() WHERE contribution_id = :id")
            ->execute([':admin' => getAdminId(), ':id' => $id]);

        // Update linked item
        $table = $cont['type'] === 'location' ? 'locations' : ($cont['type'] === 'vehicle' ? 'vehicles' : 'routes');
        $db->prepare("UPDATE $table SET status = 'approved', approved_by = :admin, updated_at = NOW() WHERE contribution_id = :id")
            ->execute([':admin' => getAdminId(), ':id' => $id]);

        // Update agent stats
        $db->prepare("UPDATE agents SET approved_count = approved_count + 1, points = points + 10 WHERE agent_id = :aid")
            ->execute([':aid' => $cont['agent_id']]);

        jsonSuccess('Contribution approved.');
        break;

    /* ── Reject ──────────────────────────────────────────── */
    case 'reject':
        requireAdminAPI();
        validateCsrf();
        $db = getDB();
        $id = postInt('id');
        $reason = postString('reason');
        if (!$id)
            jsonError('Contribution ID required.');
        if (!$reason)
            jsonError('Rejection reason required.');

        $stmt = $db->prepare("SELECT * FROM contributions WHERE contribution_id = :id");
        $stmt->execute([':id' => $id]);
        $cont = $stmt->fetch();
        if (!$cont)
            jsonError('Contribution not found.');

        $db->prepare("UPDATE contributions SET status = 'rejected', reviewed_by = :admin, reviewed_at = NOW(), rejection_reason = :reason WHERE contribution_id = :id")
            ->execute([':admin' => getAdminId(), ':id' => $id, ':reason' => $reason]);

        $table = $cont['type'] === 'location' ? 'locations' : ($cont['type'] === 'vehicle' ? 'vehicles' : 'routes');
        $db->prepare("UPDATE $table SET status = 'rejected', approved_by = :admin, updated_at = NOW() WHERE contribution_id = :id")
            ->execute([':admin' => getAdminId(), ':id' => $id]);

        jsonSuccess('Contribution rejected.');
        break;

    /* ══════════════════════════════════════════════════════
     *  AGENT ENDPOINTS
     * ══════════════════════════════════════════════════════ */

    /* ── My Contributions (Agent) ────────────────────────── */
    case 'my-list':
        requireAgentAPI();
        $db = getDB();

        $where = ['c.agent_id = :agent_id'];
        $params = [':agent_id' => getAgentId()];

        $status = getString('status');
        if ($status && in_array($status, ['pending', 'approved', 'rejected'])) {
            $where[] = 'c.status = :status';
            $params[':status'] = $status;
        }

        $type = getString('type');
        if ($type && in_array($type, ['location', 'vehicle', 'route'])) {
            $where[] = 'c.type = :type';
            $params[':type'] = $type;
        }

        $whereSQL = 'WHERE ' . implode(' AND ', $where);

        $countStmt = $db->prepare("SELECT COUNT(*) FROM contributions c $whereSQL");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();
        $pagination = paginate($total);

        $sql = "SELECT c.*
                FROM contributions c
                $whereSQL
                ORDER BY c.created_at DESC
                LIMIT :offset, :limit";

        $stmt = $db->prepare($sql);
        foreach ($params as $k => $val) {
            $stmt->bindValue($k, $val);
        }
        $stmt->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
        $stmt->bindValue(':limit', $pagination['per_page'], PDO::PARAM_INT);
        $stmt->execute();
        $contributions = $stmt->fetchAll();

        // Fetch linked item name
        foreach ($contributions as &$cont) {
            $cont['item_name'] = '—';
            $tbl = $cont['type'] === 'location' ? 'locations' : ($cont['type'] === 'vehicle' ? 'vehicles' : 'routes');
            $s = $db->prepare("SELECT name FROM $tbl WHERE contribution_id = :cid LIMIT 1");
            $s->execute([':cid' => $cont['contribution_id']]);
            $row = $s->fetch();
            if ($row)
                $cont['item_name'] = $row['name'];
        }
        unset($cont);

        jsonResponse(['success' => true, 'contributions' => $contributions, 'pagination' => $pagination]);
        break;

    default:
        jsonError('Unknown action.', 400);
}
