<?php
/**
 * SAWARI — Vehicles API
 *
 * Actions:
 *   list     – GET  – Paginated list with status/electric filters
 *   get      – GET  – Single vehicle by ID
 *   create   – POST – Create new vehicle (admin, with optional image)
 *   update   – POST – Update vehicle
 *   approve  – POST – Approve pending vehicle
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
            $where[] = 'v.status = :status';
            $params[':status'] = $status;
        }

        $electric = getString('electric');
        if ($electric !== '' && $electric !== null) {
            $where[] = 'v.electric = :electric';
            $params[':electric'] = (int) $electric;
        }

        $q = getString('q');
        if ($q) {
            $where[] = 'v.name LIKE :q';
            $params[':q'] = '%' . $q . '%';
        }

        $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $countStmt = $db->prepare("SELECT COUNT(*) FROM vehicles v $whereSQL");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();
        $pagination = paginate($total);

        $sql = "SELECT v.*, a.name AS agent_name, adm.name AS approved_by_name
                FROM vehicles v
                LEFT JOIN agents a ON v.updated_by = a.agent_id
                LEFT JOIN admins adm ON v.approved_by = adm.admin_id
                $whereSQL
                ORDER BY v.vehicle_id DESC
                LIMIT :offset, :limit";

        $stmt = $db->prepare($sql);
        foreach ($params as $k => $val) {
            $stmt->bindValue($k, $val);
        }
        $stmt->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
        $stmt->bindValue(':limit', $pagination['per_page'], PDO::PARAM_INT);
        $stmt->execute();

        jsonResponse(['success' => true, 'vehicles' => $stmt->fetchAll(), 'pagination' => $pagination]);
        break;

    /* ── Get ─────────────────────────────────────────────── */
    case 'get':
        $db = getDB();
        $id = getInt('id');
        if (!$id)
            jsonError('Vehicle ID required.');

        $stmt = $db->prepare("SELECT v.*, a.name AS agent_name, adm.name AS approved_by_name
                              FROM vehicles v
                              LEFT JOIN agents a ON v.updated_by = a.agent_id
                              LEFT JOIN admins adm ON v.approved_by = adm.admin_id
                              WHERE v.vehicle_id = :id");
        $stmt->execute([':id' => $id]);
        $v = $stmt->fetch();
        if (!$v)
            jsonError('Vehicle not found.', 404);

        // Decode used_routes JSON
        $v['used_routes_parsed'] = $v['used_routes'] ? json_decode($v['used_routes'], true) : [];

        jsonResponse(['success' => true, 'vehicle' => $v]);
        break;

    /* ── Create ──────────────────────────────────────────── */
    case 'create':
        requireAdminAPI();
        validateCsrf();
        $db = getDB();

        $name = postString('name');
        $desc = postString('description');
        $electric = postInt('electric') ? 1 : 0;
        $startsAt = postString('starts_at');
        $stopsAt = postString('stops_at');
        $usedRoutes = postString('used_routes'); // comma-separated route IDs

        if (!$name)
            jsonError('Vehicle name is required.');

        // Handle image upload
        $imagePath = null;
        if (!empty($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $imagePath = handleImageUpload($_FILES['image']);
        }

        // Parse used_routes
        $routesJson = null;
        if ($usedRoutes) {
            $ids = array_map('intval', explode(',', $usedRoutes));
            $routesJson = json_encode(array_filter($ids));
        }

        $stmt = $db->prepare("INSERT INTO vehicles (name, description, image_path, electric, starts_at, stops_at, used_routes, status, approved_by, updated_at)
                              VALUES (:name, :desc, :img, :elec, :starts, :stops, :routes, 'approved', :admin, NOW())");
        $stmt->execute([
            ':name' => $name,
            ':desc' => $desc,
            ':img' => $imagePath,
            ':elec' => $electric,
            ':starts' => $startsAt ?: null,
            ':stops' => $stopsAt ?: null,
            ':routes' => $routesJson,
            ':admin' => getAdminId()
        ]);

        jsonSuccess('Vehicle created successfully.');
        break;

    /* ── Update ──────────────────────────────────────────── */
    case 'update':
        requireAdminAPI();
        validateCsrf();
        $db = getDB();

        $id = postInt('vehicle_id');
        $name = postString('name');
        $desc = postString('description');
        $electric = postInt('electric') ? 1 : 0;
        $startsAt = postString('starts_at');
        $stopsAt = postString('stops_at');
        $usedRoutes = postString('used_routes');

        if (!$id)
            jsonError('Vehicle ID required.');
        if (!$name)
            jsonError('Vehicle name is required.');

        // Handle image
        $imagePath = null;
        if (!empty($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $imagePath = handleImageUpload($_FILES['image']);
            $db->prepare("UPDATE vehicles SET image_path = :img WHERE vehicle_id = :id")
                ->execute([':img' => $imagePath, ':id' => $id]);
        }

        $routesJson = null;
        if ($usedRoutes) {
            $ids = array_map('intval', explode(',', $usedRoutes));
            $routesJson = json_encode(array_filter($ids));
        }

        $stmt = $db->prepare("UPDATE vehicles SET name = :name, description = :desc,
                              electric = :elec, starts_at = :starts, stops_at = :stops,
                              used_routes = :routes, updated_at = NOW() WHERE vehicle_id = :id");
        $stmt->execute([
            ':name' => $name,
            ':desc' => $desc,
            ':elec' => $electric,
            ':starts' => $startsAt ?: null,
            ':stops' => $stopsAt ?: null,
            ':routes' => $routesJson,
            ':id' => $id
        ]);

        jsonSuccess('Vehicle updated.');
        break;

    /* ── Approve ─────────────────────────────────────────── */
    case 'approve':
        requireAdminAPI();
        validateCsrf();
        $db = getDB();
        $id = postInt('id');
        if (!$id)
            jsonError('Vehicle ID required.');

        $db->prepare("UPDATE vehicles SET status = 'approved', approved_by = :admin, updated_at = NOW() WHERE vehicle_id = :id")
            ->execute([':admin' => getAdminId(), ':id' => $id]);

        $db->prepare("UPDATE contributions SET status = 'approved', reviewed_by = :admin, reviewed_at = NOW()
                      WHERE contribution_id = (SELECT contribution_id FROM vehicles WHERE vehicle_id = :id)")
            ->execute([':admin' => getAdminId(), ':id' => $id]);

        jsonSuccess('Vehicle approved.');
        break;

    /* ── Reject ──────────────────────────────────────────── */
    case 'reject':
        requireAdminAPI();
        validateCsrf();
        $db = getDB();
        $id = postInt('id');
        $reason = postString('reason');
        if (!$id)
            jsonError('Vehicle ID required.');
        if (!$reason)
            jsonError('Rejection reason required.');

        $db->prepare("UPDATE vehicles SET status = 'rejected', approved_by = :admin, updated_at = NOW() WHERE vehicle_id = :id")
            ->execute([':admin' => getAdminId(), ':id' => $id]);

        $db->prepare("UPDATE contributions SET status = 'rejected', reviewed_by = :admin, reviewed_at = NOW(), rejection_reason = :reason
                      WHERE contribution_id = (SELECT contribution_id FROM vehicles WHERE vehicle_id = :id)")
            ->execute([':admin' => getAdminId(), ':id' => $id, ':reason' => $reason]);

        jsonSuccess('Vehicle rejected.');
        break;

    /* ── Delete ──────────────────────────────────────────── */
    case 'delete':
        requireAdminAPI();
        validateCsrf();
        $db = getDB();
        $id = postInt('id');
        if (!$id)
            jsonError('Vehicle ID required.');

        $db->prepare("DELETE FROM vehicles WHERE vehicle_id = :id")->execute([':id' => $id]);
        jsonSuccess('Vehicle deleted.');
        break;

    /* ══════════════════════════════════════════════════════
     *  AGENT ENDPOINTS
     * ══════════════════════════════════════════════════════ */

    /* ── Submit Vehicle (Agent) ──────────────────────────── */
    case 'submit':
        requireAgentAPI();
        validateCsrf();
        $db = getDB();

        $name = postString('name');
        $desc = postString('description');
        $electric = postInt('electric');
        $starts = postString('starts_at');
        $stops = postString('stops_at');
        $notes = postString('notes');

        if (!$name) jsonError('Vehicle name is required.');

        // Handle image
        $imagePath = null;
        if (!empty($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $imagePath = handleImageUpload($_FILES['image']);
        }

        $db->beginTransaction();
        try {
            // Create contribution
            $cStmt = $db->prepare("INSERT INTO contributions (agent_id, type, status, notes) VALUES (:agent, 'vehicle', 'pending', :notes)");
            $cStmt->execute([':agent' => getAgentId(), ':notes' => $notes]);
            $contribId = (int) $db->lastInsertId();

            // Create vehicle
            $vStmt = $db->prepare("INSERT INTO vehicles (name, description, image_path, electric, starts_at, stops_at, status, contribution_id, updated_by)
                                   VALUES (:name, :desc, :img, :elec, :starts, :stops, 'pending', :cid, :agent)");
            $vStmt->execute([
                ':name' => $name, ':desc' => $desc,
                ':img' => $imagePath, ':elec' => $electric,
                ':starts' => $starts ?: null, ':stops' => $stops ?: null,
                ':cid' => $contribId, ':agent' => getAgentId()
            ]);

            // Update agent count
            $db->prepare("UPDATE agents SET contributions_count = contributions_count + 1 WHERE agent_id = :id")
                ->execute([':id' => getAgentId()]);

            $db->commit();
            jsonSuccess('Vehicle submitted for review.');
        } catch (Exception $e) {
            $db->rollBack();
            error_log('Vehicle submit error: ' . $e->getMessage());
            jsonError('Failed to submit vehicle. Please try again.');
        }
        break;

    default:
        jsonError('Unknown action.', 400);
}

/* ── Image Upload Helper ─────────────────────────────────── */
function handleImageUpload($file)
{
    $allowed = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($file['type'], $allowed)) {
        jsonError('Invalid image type. Only JPG, PNG, WEBP allowed.');
    }
    if ($file['size'] > 2 * 1024 * 1024) {
        jsonError('Image must be under 2 MB.');
    }

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $name = 'vehicle_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest = VEHICLE_IMAGE_DIR . '/' . $name;

    if (!is_dir(VEHICLE_IMAGE_DIR)) {
        mkdir(VEHICLE_IMAGE_DIR, 0755, true);
    }

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        jsonError('Failed to save image.');
    }

    return $name;
}
