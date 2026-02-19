<?php
/**
 * SAWARI — Admins API
 * 
 * Handles admin authentication and management.
 * 
 * Actions:
 *   POST login    — Authenticate admin (email, password)
 *   POST logout   — Destroy session
 *   GET  stats    — Dashboard statistics (requires auth)
 */

require_once __DIR__ . '/config.php';

$action = getAction();

switch ($action) {

    // =========================================================
    // LOGIN
    // =========================================================
    case 'login':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonError('POST method required', 405);
        }

        $email = postString('email');
        $password = postString('password');

        if (empty($email) || empty($password)) {
            jsonError('Email and password are required.');
        }

        $db = getDB();
        $stmt = $db->prepare('SELECT admin_id, name, email, password, role, status FROM admins WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $admin = $stmt->fetch();

        if (!$admin) {
            jsonError('Invalid email or password.');
        }

        if ($admin['status'] !== 'active') {
            jsonError('This account has been deactivated.');
        }

        if (!password_verify($password, $admin['password'])) {
            jsonError('Invalid email or password.');
        }

        // Set session
        $_SESSION['admin_id'] = (int) $admin['admin_id'];
        $_SESSION['admin_name'] = $admin['name'];
        $_SESSION['admin_email'] = $admin['email'];
        $_SESSION['admin_role'] = $admin['role'];

        // Update last login
        $update = $db->prepare('UPDATE admins SET last_login = NOW() WHERE admin_id = ?');
        $update->execute([$admin['admin_id']]);

        jsonSuccess('Login successful.', [
            'admin' => [
                'id' => (int) $admin['admin_id'],
                'name' => $admin['name'],
                'email' => $admin['email'],
                'role' => $admin['role'],
            ],
            'redirect' => BASE_URL . '/pages/admin/dashboard.php'
        ]);
        break;

    // =========================================================
    // LOGOUT
    // =========================================================
    case 'logout':
        // Clear admin session data
        unset(
            $_SESSION['admin_id'],
            $_SESSION['admin_name'],
            $_SESSION['admin_email'],
            $_SESSION['admin_role']
        );

        // If this is an AJAX request, return JSON
        if (
            !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
        ) {
            jsonSuccess('Logged out.', ['redirect' => BASE_URL . '/pages/admin/login.php']);
        }

        // Otherwise redirect
        header('Location: ' . BASE_URL . '/pages/admin/login.php');
        exit;

    // =========================================================
    // DASHBOARD STATS
    // =========================================================
    case 'stats':
        requireAdminAPI();

        $db = getDB();

        // Count pending items
        $pendingLocations = $db->query("SELECT COUNT(*) FROM locations WHERE status = 'pending'")->fetchColumn();
        $pendingVehicles = $db->query("SELECT COUNT(*) FROM vehicles WHERE status = 'pending'")->fetchColumn();
        $pendingRoutes = $db->query("SELECT COUNT(*) FROM routes WHERE status = 'pending'")->fetchColumn();
        $pendingContributions = $db->query("SELECT COUNT(*) FROM contributions WHERE status = 'pending'")->fetchColumn();

        // Count totals
        $totalLocations = $db->query("SELECT COUNT(*) FROM locations WHERE status = 'approved'")->fetchColumn();
        $totalVehicles = $db->query("SELECT COUNT(*) FROM vehicles WHERE status = 'approved'")->fetchColumn();
        $totalRoutes = $db->query("SELECT COUNT(*) FROM routes WHERE status = 'approved'")->fetchColumn();
        $totalAgents = $db->query("SELECT COUNT(*) FROM agents WHERE status = 'active'")->fetchColumn();

        // Active alerts
        $activeAlerts = $db->query("SELECT COUNT(*) FROM alerts WHERE status = 'active'")->fetchColumn();

        // Pending suggestions
        $pendingSuggestions = $db->query("SELECT COUNT(*) FROM suggestions WHERE status = 'pending'")->fetchColumn();

        // Recent contributions (last 10)
        $recentStmt = $db->query("
            SELECT c.contribution_id, c.type, c.status, c.created_at, a.name AS agent_name
            FROM contributions c
            JOIN agents a ON c.agent_id = a.agent_id
            ORDER BY c.created_at DESC
            LIMIT 10
        ");
        $recentContributions = $recentStmt->fetchAll();

        jsonSuccess('Stats loaded.', [
            'stats' => [
                'pending_locations' => (int) $pendingLocations,
                'pending_vehicles' => (int) $pendingVehicles,
                'pending_routes' => (int) $pendingRoutes,
                'pending_contributions' => (int) $pendingContributions,
                'total_locations' => (int) $totalLocations,
                'total_vehicles' => (int) $totalVehicles,
                'total_routes' => (int) $totalRoutes,
                'total_agents' => (int) $totalAgents,
                'active_alerts' => (int) $activeAlerts,
                'pending_suggestions' => (int) $pendingSuggestions,
            ],
            'recent_contributions' => $recentContributions,
        ]);
        break;

    // =========================================================
    // DEFAULT
    // =========================================================
    default:
        jsonError('Unknown action.', 400);
}
