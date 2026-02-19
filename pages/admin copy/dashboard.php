<?php
/**
 * Admin Dashboard — Sawari
 * 
 * Overview page with stats, recent contributions, alerts, and quick actions.
 */

$pageTitle = 'Admin Dashboard — Sawari';
$pageCss = ['admin.css'];
$bodyClass = 'admin-page';
$pageJs = ['admin.js'];

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/validation.php';

requireAuth('admin');

$pdo = getDBConnection();
$currentPage = 'dashboard';

// ─── Fetch Stats ─────────────────────────────────────────
$totalLocations = $pdo->query("SELECT COUNT(*) FROM locations")->fetchColumn();
$totalRoutes = $pdo->query("SELECT COUNT(*) FROM routes")->fetchColumn();
$totalVehicles = $pdo->query("SELECT COUNT(*) FROM vehicles")->fetchColumn();
$pendingContributions = $pdo->query("SELECT COUNT(*) FROM contributions WHERE status = 'pending'")->fetchColumn();
$totalAgents = $pdo->query("SELECT COUNT(*) FROM agents")->fetchColumn();
$activeAlerts = $pdo->query("SELECT COUNT(*) FROM alerts WHERE expires_at > NOW()")->fetchColumn();
$unreviewedSuggestions = $pdo->query("SELECT COUNT(*) FROM suggestions WHERE status = 'pending'")->fetchColumn();
$approvedLocations = $pdo->query("SELECT COUNT(*) FROM locations WHERE status = 'approved'")->fetchColumn();

// ─── Recent Contributions (last 5) ──────────────────────
$recentContributions = $pdo->query("
    SELECT c.contribution_id, c.type, c.status, c.proposed_at,
           a.name AS agent_name
    FROM contributions c
    LEFT JOIN agents a ON c.proposed_by = a.agent_id
    ORDER BY c.proposed_at DESC
    LIMIT 5
")->fetchAll();

// ─── Recent Suggestions (last 5) ────────────────────────
$recentSuggestions = $pdo->query("
    SELECT suggestion_id, type, message, status, submitted_at
    FROM suggestions
    ORDER BY submitted_at DESC
    LIMIT 5
")->fetchAll();
?>

<div class="admin-layout">
    <?php require_once __DIR__ . '/../../includes/admin-sidebar.php'; ?>

    <div class="admin-content">
        <div class="admin-page-header">
            <h1>Dashboard</h1>
            <div class="header-actions">
                <span style="color: var(--color-text-light); font-size: 0.875rem;">
                    Welcome, <?= sanitize(getCurrentUserName()) ?>
                </span>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon primary"><i class="fa-duotone fa-solid fa-location-dot"></i></div>
                <div class="stat-info">
                    <h3><?= $totalLocations ?></h3>
                    <p>Total Locations</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon success"><i class="fa-duotone fa-solid fa-route"></i></div>
                <div class="stat-info">
                    <h3><?= $totalRoutes ?></h3>
                    <p>Total Routes</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon info"><i class="fa-duotone fa-solid fa-bus"></i></div>
                <div class="stat-info">
                    <h3><?= $totalVehicles ?></h3>
                    <p>Total Vehicles</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon warning"><i class="fa-duotone fa-solid fa-clipboard-list"></i></div>
                <div class="stat-info">
                    <h3><?= $pendingContributions ?></h3>
                    <p>Pending Contributions</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon primary"><i class="fa-duotone fa-solid fa-users"></i></div>
                <div class="stat-info">
                    <h3><?= $totalAgents ?></h3>
                    <p>Registered Agents</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon danger"><i class="fa-duotone fa-solid fa-triangle-exclamation"></i></div>
                <div class="stat-info">
                    <h3><?= $activeAlerts ?></h3>
                    <p>Active Alerts</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon warning"><i class="fa-duotone fa-solid fa-comments"></i></div>
                <div class="stat-info">
                    <h3><?= $unreviewedSuggestions ?></h3>
                    <p>Unreviewed Suggestions</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon success"><i class="fa-duotone fa-solid fa-circle-check"></i></div>
                <div class="stat-info">
                    <h3><?= $approvedLocations ?></h3>
                    <p>Approved Locations</p>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <a href="<?= BASE_URL ?>/pages/admin/locations.php" class="quick-action-btn"><i
                    class="fa-duotone fa-solid fa-location-dot"></i> Manage Locations</a>
            <a href="<?= BASE_URL ?>/pages/admin/routes.php" class="quick-action-btn"><i
                    class="fa-duotone fa-solid fa-route"></i> Manage Routes</a>
            <a href="<?= BASE_URL ?>/pages/admin/vehicles.php" class="quick-action-btn"><i
                    class="fa-duotone fa-solid fa-bus"></i> Manage Vehicles</a>
            <a href="<?= BASE_URL ?>/pages/admin/contributions.php" class="quick-action-btn"><i
                    class="fa-duotone fa-solid fa-clipboard-list"></i> Review Contributions</a>
            <a href="<?= BASE_URL ?>/pages/admin/alerts.php" class="quick-action-btn"><i
                    class="fa-duotone fa-solid fa-triangle-exclamation"></i> Issue Alert</a>
        </div>

        <!-- Dashboard Grid: Recent Activity -->
        <div class="dashboard-grid">
            <!-- Recent Contributions -->
            <div class="activity-list">
                <div class="activity-header">Recent Contributions</div>
                <?php if (empty($recentContributions)): ?>
                    <div class="empty-state" style="padding: var(--space-xl);">
                        <p>No contributions yet.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($recentContributions as $c): ?>
                        <div class="activity-item">
                            <div class="activity-icon" style="background: var(--color-primary-light);">
                                <?= $c['type'] === 'location' ? '<i class="fa-duotone fa-solid fa-location-dot"></i>' : ($c['type'] === 'route' ? '<i class="fa-duotone fa-solid fa-route"></i>' : '<i class="fa-duotone fa-solid fa-bus"></i>') ?>
                            </div>
                            <div class="activity-text">
                                <strong><?= sanitize($c['agent_name'] ?? 'Unknown') ?></strong> proposed a
                                <strong><?= sanitize($c['type']) ?></strong>
                                <br>
                                <span
                                    class="badge badge-<?= $c['status'] === 'accepted' ? 'accepted' : ($c['status'] === 'rejected' ? 'rejected' : 'pending') ?>"><?= sanitize($c['status']) ?></span>
                            </div>
                            <div class="activity-time"><?= timeAgo($c['proposed_at']) ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Recent Suggestions -->
            <div class="activity-list">
                <div class="activity-header">Recent Suggestions</div>
                <?php if (empty($recentSuggestions)): ?>
                    <div class="empty-state" style="padding: var(--space-xl);">
                        <p>No suggestions yet.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($recentSuggestions as $s): ?>
                        <div class="activity-item">
                            <div class="activity-icon" style="background: var(--color-info-light);"><i
                                    class="fa-duotone fa-solid fa-comments"></i></div>
                            <div class="activity-text">
                                <span class="badge badge-info"><?= sanitize($s['type']) ?></span>
                                <?= sanitize(mb_strimwidth($s['message'], 0, 60, '...')) ?>
                            </div>
                            <div class="activity-time"><?= timeAgo($s['submitted_at']) ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>