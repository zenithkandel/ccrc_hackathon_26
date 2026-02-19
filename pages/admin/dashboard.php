<?php
/**
 * SAWARI — Admin Dashboard
 * 
 * Overview stats: pending contributions, total locations/vehicles/routes,
 * active alerts, recent activity.
 */

require_once __DIR__ . '/../../includes/auth-admin.php';

$pageTitle   = 'Dashboard';
$currentPage = 'dashboard';
require_once __DIR__ . '/../../includes/admin-header.php';
?>

<!-- Stats Grid (populated via JS) -->
<div class="stats-grid" id="stats-grid">
    <!-- Pending Contributions -->
    <div class="stat-card">
        <div class="stat-card-header">
            <span class="stat-card-label">Pending Review</span>
            <div class="stat-card-icon stat-card-icon-warning">
                <i data-feather="clock"></i>
            </div>
        </div>
        <div class="stat-card-value" id="stat-pending">—</div>
    </div>

    <!-- Approved Locations -->
    <div class="stat-card">
        <div class="stat-card-header">
            <span class="stat-card-label">Locations</span>
            <div class="stat-card-icon stat-card-icon-primary">
                <i data-feather="map-pin"></i>
            </div>
        </div>
        <div class="stat-card-value" id="stat-locations">—</div>
    </div>

    <!-- Approved Vehicles -->
    <div class="stat-card">
        <div class="stat-card-header">
            <span class="stat-card-label">Vehicles</span>
            <div class="stat-card-icon stat-card-icon-accent">
                <i data-feather="truck"></i>
            </div>
        </div>
        <div class="stat-card-value" id="stat-vehicles">—</div>
    </div>

    <!-- Approved Routes -->
    <div class="stat-card">
        <div class="stat-card-header">
            <span class="stat-card-label">Routes</span>
            <div class="stat-card-icon stat-card-icon-success">
                <i data-feather="git-branch"></i>
            </div>
        </div>
        <div class="stat-card-value" id="stat-routes">—</div>
    </div>
</div>

<!-- Secondary Stats Row -->
<div class="stats-grid" style="grid-template-columns: repeat(3, 1fr); margin-bottom: var(--space-8);">
    <div class="stat-card">
        <div class="stat-card-header">
            <span class="stat-card-label">Active Agents</span>
            <div class="stat-card-icon stat-card-icon-primary">
                <i data-feather="users"></i>
            </div>
        </div>
        <div class="stat-card-value" id="stat-agents">—</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-header">
            <span class="stat-card-label">Active Alerts</span>
            <div class="stat-card-icon stat-card-icon-danger">
                <i data-feather="alert-triangle"></i>
            </div>
        </div>
        <div class="stat-card-value" id="stat-alerts">—</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-header">
            <span class="stat-card-label">Pending Suggestions</span>
            <div class="stat-card-icon stat-card-icon-warning">
                <i data-feather="message-square"></i>
            </div>
        </div>
        <div class="stat-card-value" id="stat-suggestions">—</div>
    </div>
</div>

<!-- Recent Activity -->
<div class="card">
    <div class="card-header">
        <h4>Recent Contributions</h4>
        <a href="<?= BASE_URL ?>/pages/admin/contributions.php" class="btn btn-ghost btn-sm">
            View all <i data-feather="arrow-right" style="width:14px;height:14px;"></i>
        </a>
    </div>
    <div class="card-body p-0">
        <div class="table-container" style="border:none;border-radius:0;">
            <table class="table" id="recent-table">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Agent</th>
                        <th>Status</th>
                        <th>Submitted</th>
                    </tr>
                </thead>
                <tbody id="recent-table-body">
                    <tr>
                        <td colspan="4" class="text-center text-muted p-6">Loading...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    loadDashboardStats();
});

async function loadDashboardStats() {
    try {
        const data = await Sawari.api('admins', 'stats');
        
        if (data.success) {
            const s = data.stats;
            
            // Primary stats
            document.getElementById('stat-pending').textContent = s.pending_contributions;
            document.getElementById('stat-locations').textContent = s.total_locations;
            document.getElementById('stat-vehicles').textContent = s.total_vehicles;
            document.getElementById('stat-routes').textContent = s.total_routes;
            
            // Secondary stats
            document.getElementById('stat-agents').textContent = s.total_agents;
            document.getElementById('stat-alerts').textContent = s.active_alerts;
            document.getElementById('stat-suggestions').textContent = s.pending_suggestions;
            
            // Recent contributions table
            renderRecentTable(data.recent_contributions);
        }
    } catch (err) {
        console.error('Failed to load stats:', err);
    }
}

function renderRecentTable(contributions) {
    const tbody = document.getElementById('recent-table-body');
    
    if (!contributions || contributions.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted p-6">No contributions yet.</td></tr>';
        return;
    }
    
    tbody.innerHTML = contributions.map(function(c) {
        const typeIcon = { location: 'map-pin', vehicle: 'truck', route: 'git-branch' }[c.type] || 'file';
        const statusClass = { pending: 'badge-pending', approved: 'badge-approved', rejected: 'badge-rejected' }[c.status] || 'badge-neutral';
        
        return '<tr>' +
            '<td><span class="flex items-center gap-2"><i data-feather="' + typeIcon + '" style="width:14px;height:14px;"></i> ' + Sawari.escape(c.type) + '</span></td>' +
            '<td>' + Sawari.escape(c.agent_name) + '</td>' +
            '<td><span class="badge ' + statusClass + '">' + Sawari.escape(c.status) + '</span></td>' +
            '<td class="text-muted">' + Sawari.escape(c.created_at) + '</td>' +
        '</tr>';
    }).join('');
    
    // Re-render feather icons for dynamically added elements
    feather.replace({ 'stroke-width': 1.75 });
}
</script>

<?php require_once __DIR__ . '/../../includes/admin-footer.php'; ?>
