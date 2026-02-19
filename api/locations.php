<?php
/**
 * SAWARI — Locations API
 *
 * Actions:
 *   list     – GET  – Paginated list with status/type/search filters
 *   get      – GET  – Single location by ID
 *   create   – POST – Create new location (admin)
 *   update   – POST – Update existing location (admin)
 *   approve  – POST – Set status = approved
 *   reject   – POST – Set status = rejected (with reason stored as contribution)
 *   delete   – POST – Hard delete
 *   search   – GET  – Search locations by name (public, approved only)
 */

require_once __DIR__ . '/config.php';

$action = getAction();

switch ($action) {

    /* ── List (admin) ───────────────────────────────────── */
    case 'list':
        requireAdminAPI();
        $db = getDB();

        $where = [];
        $params = [];

        // Filters
        $status = getString('status');
        if ($status && in_array($status, ['pending', 'approved', 'rejected'])) {
            $where[] = 'l.status = :status';
            $params[':status'] = $status;
        }

        $type = getString('type');
        if ($type && in_array($type, ['stop', 'landmark'])) {
            $where[] = 'l.type = :type';
            $params[':type'] = $type;
        }

        $q = getString('q');
        if ($q) {
            $where[] = 'l.name LIKE :q';
            $params[':q'] = '%' . $q . '%';
        }

        $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        // Count
        $countStmt = $db->prepare("SELECT COUNT(*) FROM locations l $whereSQL");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $pagination = paginate($total);

        // Fetch
        $sql = "SELECT l.*, a.name AS agent_name, adm.name AS approved_by_name
                FROM locations l
                LEFT JOIN agents a ON l.updated_by = a.agent_id
                LEFT JOIN admins adm ON l.approved_by = adm.admin_id
                $whereSQL
                ORDER BY l.location_id DESC
                LIMIT :offset, :limit";

        $stmt = $db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
        $stmt->bindValue(':limit', $pagination['per_page'], PDO::PARAM_INT);
        $stmt->execute();
        $locations = $stmt->fetchAll();

        jsonResponse([
            'success' => true,
            'locations' => $locations,
            'pagination' => $pagination
        ]);
        break;

    /* ── Get single ─────────────────────────────────────── */
    case 'get':
        $db = getDB();
        $id = getInt('id');
        if (!$id)
            jsonError('Location ID required.');

        $stmt = $db->prepare("SELECT l.*, a.name AS agent_name, adm.name AS approved_by_name
                              FROM locations l
                              LEFT JOIN agents a ON l.updated_by = a.agent_id
                              LEFT JOIN admins adm ON l.approved_by = adm.admin_id
                              WHERE l.location_id = :id");
        $stmt->execute([':id' => $id]);
        $loc = $stmt->fetch();
        if (!$loc)
            jsonError('Location not found.', 404);

        jsonResponse(['success' => true, 'location' => $loc]);
        break;

    /* ── Create (admin) ─────────────────────────────────── */
    case 'create':
        requireAdminAPI();
        validateCsrf();
        $db = getDB();

        $name = postString('name');
        $lat = isset($_POST['latitude']) ? floatval($_POST['latitude']) : null;
        $lng = isset($_POST['longitude']) ? floatval($_POST['longitude']) : null;
        $type = postString('type') ?: 'stop';
        $desc = postString('description');

        if (!$name)
            jsonError('Name is required.');
        if ($lat === null)
            jsonError('Latitude is required.');
        if ($lng === null)
            jsonError('Longitude is required.');

        $stmt = $db->prepare("INSERT INTO locations (name, description, latitude, longitude, type, status, approved_by, updated_at)
                              VALUES (:name, :desc, :lat, :lng, :type, 'approved', :admin, NOW())");
        $stmt->execute([
            ':name' => $name,
            ':desc' => $desc,
            ':lat' => $lat,
            ':lng' => $lng,
            ':type' => $type,
            ':admin' => getAdminId()
        ]);

        jsonSuccess('Location created successfully.');
        break;

    /* ── Update (admin) ─────────────────────────────────── */
    case 'update':
        requireAdminAPI();
        validateCsrf();
        $db = getDB();

        $id = postInt('location_id');
        $name = postString('name');
        $lat = isset($_POST['latitude']) ? floatval($_POST['latitude']) : null;
        $lng = isset($_POST['longitude']) ? floatval($_POST['longitude']) : null;
        $type = postString('type');
        $desc = postString('description');

        if (!$id)
            jsonError('Location ID required.');
        if (!$name)
            jsonError('Name is required.');

        $stmt = $db->prepare("UPDATE locations SET name = :name, description = :desc,
                              latitude = :lat, longitude = :lng, type = :type, updated_at = NOW()
                              WHERE location_id = :id");
        $stmt->execute([
            ':name' => $name,
            ':desc' => $desc,
            ':lat' => $lat,
            ':lng' => $lng,
            ':type' => $type,
            ':id' => $id
        ]);

        jsonSuccess('Location updated.');
        break;

    /* ── Approve ────────────────────────────────────────── */
    case 'approve':
        requireAdminAPI();
        validateCsrf();
        $db = getDB();
        $id = postInt('id');
        if (!$id)
            jsonError('Location ID required.');

        $stmt = $db->prepare("UPDATE locations SET status = 'approved', approved_by = :admin, updated_at = NOW() WHERE location_id = :id");
        $stmt->execute([':admin' => getAdminId(), ':id' => $id]);

        // Also update the linked contribution if any
        $db->prepare("UPDATE contributions SET status = 'approved', reviewed_by = :admin, reviewed_at = NOW()
                      WHERE contribution_id = (SELECT contribution_id FROM locations WHERE location_id = :id)")
            ->execute([':admin' => getAdminId(), ':id' => $id]);

        jsonSuccess('Location approved.');
        break;

    /* ── Reject ─────────────────────────────────────────── */
    case 'reject':
        requireAdminAPI();
        validateCsrf();
        $db = getDB();
        $id = postInt('id');
        $reason = postString('reason');
        if (!$id)
            jsonError('Location ID required.');
        if (!$reason)
            jsonError('Rejection reason required.');

        $stmt = $db->prepare("UPDATE locations SET status = 'rejected', approved_by = :admin, updated_at = NOW() WHERE location_id = :id");
        $stmt->execute([':admin' => getAdminId(), ':id' => $id]);

        // Update linked contribution
        $db->prepare("UPDATE contributions SET status = 'rejected', reviewed_by = :admin, reviewed_at = NOW(), admin_note = :reason
                      WHERE contribution_id = (SELECT contribution_id FROM locations WHERE location_id = :id)")
            ->execute([':admin' => getAdminId(), ':id' => $id, ':reason' => $reason]);

        jsonSuccess('Location rejected.');
        break;

    /* ── Delete ─────────────────────────────────────────── */
    case 'delete':
        requireAdminAPI();
        validateCsrf();
        $db = getDB();
        $id = postInt('id');
        if (!$id)
            jsonError('Location ID required.');

        $db->prepare("DELETE FROM locations WHERE location_id = :id")->execute([':id' => $id]);
        jsonSuccess('Location deleted.');
        break;

    /* ── Public search (approved only) ──────────────────── */
    case 'search':
        $db = getDB();
        $q = getString('q');
        if (!$q || strlen($q) < 2)
            jsonError('Search term too short.');

        $stmt = $db->prepare("SELECT location_id, name, latitude, longitude, type
                              FROM locations WHERE status = 'approved' AND name LIKE :q
                              ORDER BY name ASC LIMIT 20");
        $stmt->execute([':q' => '%' . $q . '%']);

        jsonResponse(['success' => true, 'locations' => $stmt->fetchAll()]);
        break;

    default:
        jsonError('Unknown action.', 400);
}
