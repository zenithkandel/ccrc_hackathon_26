<?php
/**
 * Agent: Dashboard — Sawari
 * 
 * Welcome banner, stats cards, recent contribution statuses, and quick actions.
 */

$pageTitle = 'Dashboard — Agent — Sawari';
$pageCss = ['admin.css', 'agent.css'];
$bodyClass = 'admin-page agent-page';
$pageJs = ['agent/agent.js'];
$currentPage = 'dashboard';

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../config/database.php';

requireAuth('agent');

$pdo = getDBConnection();
$agentId = getCurrentUserId();

// ─── Agent Info ──────────────────────────────────────────
$stmt = $pdo->prepare('SELECT * FROM agents WHERE agent_id = :id');
$stmt->execute(['id' => $agentId]);
$agent = $stmt->fetch();

$summary = json_decode($agent['contributions_summary'] ?? '{}', true);
$locationCount = $summary['location'] ?? 0;
$routeCount = $summary['route'] ?? 0;
$vehicleCount = $summary['vehicle'] ?? 0;
$totalContribs = $locationCount + $routeCount + $vehicleCount;

// ─── Pending count ───────────────────────────────────────
$pendingStmt = $pdo->prepare("SELECT COUNT(*) FROM contributions WHERE proposed_by = :aid AND status = 'pending'");
$pendingStmt->execute(['aid' => $agentId]);
$pendingCount = (int) $pendingStmt->fetchColumn();

// ─── Recent contributions (last 5) ──────────────────────
$recentStmt = $pdo->prepare("
    SELECT c.*, 
        CASE c.type
            WHEN 'location' THEN (SELECT name FROM locations WHERE location_id = c.associated_entry_id)
            WHEN 'route' THEN (SELECT name FROM routes WHERE route_id = c.associated_entry_id)
            WHEN 'vehicle' THEN (SELECT name FROM vehicles WHERE vehicle_id = c.associated_entry_id)
        END AS entry_name
    FROM contributions c
    WHERE c.proposed_by = :aid
    ORDER BY c.proposed_at DESC
    LIMIT 5
");
$recentStmt->execute(['aid' => $agentId]);
$recentContribs = $recentStmt->fetchAll();
?>

<div class="admin-layout">
    <?php require_once __DIR__ . '/../../includes/agent-sidebar.php'; ?>

    <div class="admin-content">
        <!-- Welcome Banner -->
        <div class="welcome-banner">
            <h1>Welcome back, <?= sanitize($agent['name']) ?>!</h1>
            <p>Contribute locations, routes, and vehicles to help people navigate Nepal's public transport.</p>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="fa-duotone fa-solid fa-location-dot"></i></div>
                <div class="stat-value"><?= $locationCount ?></div>
                <div class="stat-label">My Locations</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fa-duotone fa-solid fa-route"></i></div>
                <div class="stat-value"><?= $routeCount ?></div>
                <div class="stat-label">My Routes</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fa-duotone fa-solid fa-bus"></i></div>
                <div class="stat-value"><?= $vehicleCount ?></div>
                <div class="stat-label">My Vehicles</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fa-duotone fa-solid fa-hourglass-half"></i></div>
                <div class="stat-value"><?= $pendingCount ?></div>
                <div class="stat-label">Pending Proposals</div>
            </div>
        </div>

        <!-- Quick Actions -->
        <h2 style="margin-bottom: var(--space-md); font-size: 1.125rem;">Quick Actions</h2>
        <div class="quick-actions-grid">
            <a href="<?= BASE_URL ?>/pages/agent/locations.php" class="quick-action-card">
                <span class="action-icon"><i class="fa-duotone fa-solid fa-location-dot"></i></span>
                <span class="action-label">Add Location</span>
            </a>
            <a href="<?= BASE_URL ?>/pages/agent/routes.php" class="quick-action-card">
                <span class="action-icon"><i class="fa-duotone fa-solid fa-route"></i></span>
                <span class="action-label">Create Route</span>
            </a>
            <a href="<?= BASE_URL ?>/pages/agent/vehicles.php" class="quick-action-card">
                <span class="action-icon"><i class="fa-duotone fa-solid fa-bus"></i></span>
                <span class="action-label">Add Vehicle</span>
            </a>
            <a href="<?= BASE_URL ?>/pages/agent/contributions.php" class="quick-action-card">
                <span class="action-icon"><i class="fa-duotone fa-solid fa-clipboard-list"></i></span>
                <span class="action-label">My Contributions</span>
            </a>
        </div>

        <!-- Recent Contributions -->
        <h2 style="margin-bottom: var(--space-md); font-size: 1.125rem;">Recent Contributions</h2>
        <div class="data-table-wrapper">
            <?php if (empty($recentContribs)): ?>
                <div class="empty-state">
                    <div class="empty-icon"><i class="fa-duotone fa-solid fa-clipboard-list"></i></div>
                    <h3>No contributions yet</h3>
                    <p>Start contributing locations, routes, or vehicles to help your community!</p>
                </div>
            <?php else: ?>
                <div class="table-scroll">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Entry</th>
                                <th>Status</th>
                                <th>Proposed</th>
                                <th>Rejection Reason</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentContribs as $c): ?>
                                <tr>
                                    <td><span class="badge badge-info"><?= sanitize($c['type']) ?></span></td>
                                    <td><?= sanitize($c['entry_name'] ?? 'Deleted entry') ?></td>
                                    <td>
                                        <?php
                                        $badgeClass = match ($c['status']) {
                                            'accepted' => 'badge-approved',
                                            'rejected' => 'badge-rejected',
                                            default => 'badge-pending',
                                        };
                                        ?>
                                        <span class="badge <?= $badgeClass ?>"><?= sanitize($c['status']) ?></span>
                                    </td>
                                    <td style="font-size:0.8125rem;"><?= timeAgo($c['proposed_at']) ?></td>
                                    <td style="font-size:0.8125rem;"><?= sanitize($c['rejection_reason'] ?? '—') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div style="text-align:right; margin-top:var(--space-sm);">
                    <a href="<?= BASE_URL ?>/pages/agent/contributions.php"
                        style="color:var(--color-primary); font-size:0.875rem;">View all contributions <i
                            class="fa-solid fa-arrow-right"></i></a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>