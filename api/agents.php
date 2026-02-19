<?php
/**
 * SAWARI — Agents API
 *
 * Admin-facing actions:
 *   list      – GET  – Paginated agent list with status filter
 *   get       – GET  – Single agent with stats
 *   activate  – POST – Set status = active
 *   suspend   – POST – Set status = suspended
 *   delete    – POST – Hard delete
 *
 * Agent-facing actions:
 *   register  – POST – Create new agent account (pending approval)
 *   login     – POST – Agent login
 *   logout    – POST – Agent logout
 *   profile   – GET  – Own profile data
 */

require_once __DIR__ . '/config.php';

$action = getAction();

switch ($action) {

    /* ══════════════════════════════════════════════════════
     *  ADMIN ENDPOINTS
     * ══════════════════════════════════════════════════════ */

    /* ── List Agents ─────────────────────────────────────── */
    case 'list':
        requireAdminAPI();
        $db = getDB();

        $where = [];
        $params = [];

        $status = getString('status');
        if ($status && in_array($status, ['active', 'suspended', 'inactive'])) {
            $where[] = 'a.status = :status';
            $params[':status'] = $status;
        }

        $q = getString('q');
        if ($q) {
            $where[] = '(a.name LIKE :q OR a.email LIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }

        $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $countStmt = $db->prepare("SELECT COUNT(*) FROM agents a $whereSQL");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();
        $pagination = paginate($total);

        $sql = "SELECT a.agent_id, a.name, a.email, a.phone, a.points,
                       a.contributions_count, a.approved_count, a.status,
                       a.created_at, a.last_login
                FROM agents a
                $whereSQL
                ORDER BY a.points DESC, a.agent_id DESC
                LIMIT :offset, :limit";

        $stmt = $db->prepare($sql);
        foreach ($params as $k => $val) {
            $stmt->bindValue($k, $val);
        }
        $stmt->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
        $stmt->bindValue(':limit', $pagination['per_page'], PDO::PARAM_INT);
        $stmt->execute();

        jsonResponse(['success' => true, 'agents' => $stmt->fetchAll(), 'pagination' => $pagination]);
        break;

    /* ── Get Single Agent ────────────────────────────────── */
    case 'get':
        requireAdminAPI();
        $db = getDB();
        $id = getInt('id');
        if (!$id)
            jsonError('Agent ID required.');

        $stmt = $db->prepare("SELECT agent_id, name, email, phone, points,
                                     contributions_count, approved_count, status,
                                     created_at, last_login
                              FROM agents WHERE agent_id = :id");
        $stmt->execute([':id' => $id]);
        $agent = $stmt->fetch();
        if (!$agent)
            jsonError('Agent not found.', 404);

        // Recent contributions
        $cStmt = $db->prepare("SELECT * FROM contributions WHERE agent_id = :id ORDER BY created_at DESC LIMIT 10");
        $cStmt->execute([':id' => $id]);

        jsonResponse(['success' => true, 'agent' => $agent, 'recent_contributions' => $cStmt->fetchAll()]);
        break;

    /* ── Activate ────────────────────────────────────────── */
    case 'activate':
        requireAdminAPI();
        validateCsrf();
        $db = getDB();
        $id = postInt('id');
        if (!$id)
            jsonError('Agent ID required.');

        $db->prepare("UPDATE agents SET status = 'active', approved_by = :admin WHERE agent_id = :id")
            ->execute([':admin' => getAdminId(), ':id' => $id]);

        jsonSuccess('Agent activated.');
        break;

    /* ── Suspend ─────────────────────────────────────────── */
    case 'suspend':
        requireAdminAPI();
        validateCsrf();
        $db = getDB();
        $id = postInt('id');
        if (!$id)
            jsonError('Agent ID required.');

        $db->prepare("UPDATE agents SET status = 'suspended' WHERE agent_id = :id")
            ->execute([':id' => $id]);

        jsonSuccess('Agent suspended.');
        break;

    /* ── Delete Agent ────────────────────────────────────── */
    case 'delete':
        requireAdminAPI();
        validateCsrf();
        $db = getDB();
        $id = postInt('id');
        if (!$id)
            jsonError('Agent ID required.');

        $db->prepare("DELETE FROM agents WHERE agent_id = :id")->execute([':id' => $id]);
        jsonSuccess('Agent deleted.');
        break;

    /* ══════════════════════════════════════════════════════
     *  AGENT-FACING ENDPOINTS
     * ══════════════════════════════════════════════════════ */

    /* ── Register ────────────────────────────────────────── */
    case 'register':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST')
            jsonError('POST required.', 405);
        $db = getDB();

        $name = postString('name');
        $email = postString('email');
        $pass = postString('password');
        $phone = postString('phone');

        if (!$name)
            jsonError('Name is required.');
        if (!$email)
            jsonError('Email is required.');
        if (!$pass || strlen($pass) < 6)
            jsonError('Password must be at least 6 characters.');

        // Check duplicate
        $check = $db->prepare("SELECT agent_id FROM agents WHERE email = :email");
        $check->execute([':email' => $email]);
        if ($check->fetch())
            jsonError('An account with this email already exists.');

        $hash = password_hash($pass, PASSWORD_BCRYPT);

        $stmt = $db->prepare("INSERT INTO agents (name, email, password, phone, status) VALUES (:name, :email, :pass, :phone, 'active')");
        $stmt->execute([':name' => $name, ':email' => $email, ':pass' => $hash, ':phone' => $phone]);

        jsonSuccess('Registration successful. You can now log in.');
        break;

    /* ── Login ───────────────────────────────────────────── */
    case 'login':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST')
            jsonError('POST required.', 405);
        $db = getDB();

        $email = postString('email');
        $pass = postString('password');

        if (!$email || !$pass)
            jsonError('Email and password required.');

        $stmt = $db->prepare("SELECT * FROM agents WHERE email = :email");
        $stmt->execute([':email' => $email]);
        $agent = $stmt->fetch();

        if (!$agent || !password_verify($pass, $agent['password'])) {
            jsonError('Invalid email or password.');
        }

        if ($agent['status'] === 'suspended') {
            jsonError('Your account has been suspended. Contact admin.');
        }

        // Set session
        $_SESSION['agent_id'] = $agent['agent_id'];
        $_SESSION['agent_name'] = $agent['name'];
        $_SESSION['agent_email'] = $agent['email'];

        $db->prepare("UPDATE agents SET last_login = NOW() WHERE agent_id = :id")
            ->execute([':id' => $agent['agent_id']]);

        jsonResponse([
            'success' => true,
            'message' => 'Login successful.',
            'agent' => [
                'agent_id' => $agent['agent_id'],
                'name' => $agent['name'],
                'email' => $agent['email'],
                'points' => (int) $agent['points']
            ]
        ]);
        break;

    /* ── Logout ──────────────────────────────────────────── */
    case 'logout':
        unset($_SESSION['agent_id'], $_SESSION['agent_name'], $_SESSION['agent_email']);
        session_destroy();

        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            jsonSuccess('Logged out.');
        } else {
            header('Location: ' . BASE_URL . '/pages/agent/login.php');
            exit;
        }
        break;

    /* ── Profile (own) ───────────────────────────────────── */
    case 'profile':
        requireAgentAPI();
        $db = getDB();

        $stmt = $db->prepare("SELECT agent_id, name, email, phone, points,
                                     contributions_count, approved_count, status, created_at
                              FROM agents WHERE agent_id = :id");
        $stmt->execute([':id' => getAgentId()]);

        jsonResponse(['success' => true, 'agent' => $stmt->fetch()]);
        break;

    default:
        jsonError('Unknown action.', 400);
}
