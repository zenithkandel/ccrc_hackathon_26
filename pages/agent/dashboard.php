<?php
/**
 * SAWARI — Agent Dashboard
 * 
 * Overview of agent stats, points, rank, and quick actions.
 */

require_once __DIR__ . '/../../includes/auth-agent.php';

$pageTitle = 'Dashboard';
$currentPage = 'dashboard';

require_once __DIR__ . '/../../includes/agent-header.php';
?>

<!-- Stats Grid -->
<div class="stats-grid" id="stats-grid">
    <div class="stat-card">
        <div class="stat-card-icon" style="background:var(--color-primary-50);color:var(--color-primary-600);">
            <i data-feather="award"></i>
        </div>
        <div class="stat-card-info">
            <span class="stat-card-value" id="stat-points">—</span>
            <span class="stat-card-label">Points</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon" style="background:var(--color-success-50);color:var(--color-success-600);">
            <i data-feather="check-circle"></i>
        </div>
        <div class="stat-card-info">
            <span class="stat-card-value" id="stat-approved">—</span>
            <span class="stat-card-label">Approved</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon" style="background:var(--color-warning-50);color:var(--color-warning-600);">
            <i data-feather="clock"></i>
        </div>
        <div class="stat-card-info">
            <span class="stat-card-value" id="stat-pending">—</span>
            <span class="stat-card-label">Pending</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon" style="background:var(--color-neutral-100);color:var(--color-neutral-600);">
            <i data-feather="hash"></i>
        </div>
        <div class="stat-card-info">
            <span class="stat-card-value" id="stat-rank">—</span>
            <span class="stat-card-label">Rank</span>
        </div>
    </div>
</div>

<!-- Two Column Layout -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--space-6);margin-top:var(--space-6);">

    <!-- Quick Actions -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Quick Actions</h3>
        </div>
        <div class="card-body" style="display:flex;flex-direction:column;gap:var(--space-3);">
            <a href="<?= BASE_URL ?>/pages/agent/add-location.php" class="btn btn-secondary" style="justify-content:flex-start;gap:var(--space-3);">
                <i data-feather="map-pin" style="width:18px;height:18px;"></i>
                Add a Bus Stop / Landmark
            </a>
            <a href="<?= BASE_URL ?>/pages/agent/add-vehicle.php" class="btn btn-secondary" style="justify-content:flex-start;gap:var(--space-3);">
                <i data-feather="truck" style="width:18px;height:18px;"></i>
                Register a Vehicle
            </a>
            <a href="<?= BASE_URL ?>/pages/agent/add-route.php" class="btn btn-secondary" style="justify-content:flex-start;gap:var(--space-3);">
                <i data-feather="git-branch" style="width:18px;height:18px;"></i>
                Map a Route
            </a>
        </div>
    </div>

    <!-- Contribution Breakdown -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Contributions by Type</h3>
        </div>
        <div class="card-body">
            <div style="display:flex;flex-direction:column;gap:var(--space-4);" id="breakdown-chart">
                <div class="breakdown-row">
                    <div style="display:flex;align-items:center;gap:var(--space-2);">
                        <span style="width:10px;height:10px;border-radius:var(--radius-full);background:var(--color-primary-500);"></span>
                        <span style="font-size:var(--text-sm);color:var(--color-neutral-700);">Locations</span>
                    </div>
                    <span class="badge badge-primary" id="type-locations">0</span>
                </div>
                <div class="breakdown-row">
                    <div style="display:flex;align-items:center;gap:var(--space-2);">
                        <span style="width:10px;height:10px;border-radius:var(--radius-full);background:var(--color-accent-500);"></span>
                        <span style="font-size:var(--text-sm);color:var(--color-neutral-700);">Vehicles</span>
                    </div>
                    <span class="badge badge-accent" id="type-vehicles">0</span>
                </div>
                <div class="breakdown-row">
                    <div style="display:flex;align-items:center;gap:var(--space-2);">
                        <span style="width:10px;height:10px;border-radius:var(--radius-full);background:var(--color-success-500);"></span>
                        <span style="font-size:var(--text-sm);color:var(--color-neutral-700);">Routes</span>
                    </div>
                    <span class="badge badge-success" id="type-routes">0</span>
                </div>
            </div>

            <!-- Simple bar chart -->
            <div style="display:flex;gap:var(--space-2);align-items:flex-end;height:80px;margin-top:var(--space-5);" id="bar-chart">
                <div class="bar-col" style="flex:1;">
                    <div class="bar" id="bar-locations" style="background:var(--color-primary-500);height:0;border-radius:var(--radius-sm) var(--radius-sm) 0 0;transition:height 0.5s ease;"></div>
                </div>
                <div class="bar-col" style="flex:1;">
                    <div class="bar" id="bar-vehicles" style="background:var(--color-accent-500);height:0;border-radius:var(--radius-sm) var(--radius-sm) 0 0;transition:height 0.5s ease;"></div>
                </div>
                <div class="bar-col" style="flex:1;">
                    <div class="bar" id="bar-routes" style="background:var(--color-success-500);height:0;border-radius:var(--radius-sm) var(--radius-sm) 0 0;transition:height 0.5s ease;"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Contributions -->
<div class="card" style="margin-top:var(--space-6);">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
        <h3 class="card-title">Recent Submissions</h3>
        <a href="<?= BASE_URL ?>/pages/agent/my-contributions.php" class="btn btn-ghost btn-sm">View all</a>
    </div>
    <div class="card-body" style="padding:0;">
        <table class="table">
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Item</th>
                    <th>Status</th>
                    <th>Submitted</th>
                </tr>
            </thead>
            <tbody id="recent-tbody">
                <tr><td colspan="4" class="text-center text-muted" style="padding:var(--space-8);">Loading...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<style>
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: var(--space-4);
    }
    @media (max-width: 768px) {
        .stats-grid { grid-template-columns: repeat(2, 1fr); }
        div[style*="grid-template-columns:1fr 1fr"] {
            grid-template-columns: 1fr !important;
        }
    }
    .breakdown-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .bar-col {
        display: flex;
        flex-direction: column;
        justify-content: flex-end;
        height: 100%;
    }
</style>

<script>
(function () {
    'use strict';

    function loadDashboard() {
        Sawari.api('agents', 'dashboard').then(function (res) {
            if (!res.success) return;

            var agent = res.agent;
            var stats = res.stats;
            var recent = res.recent;

            // Stats cards
            document.getElementById('stat-points').textContent = agent.points || 0;
            document.getElementById('stat-approved').textContent = agent.approved_count || 0;
            document.getElementById('stat-pending').textContent = stats.pending || 0;
            document.getElementById('stat-rank').textContent = '#' + stats.rank + ' of ' + stats.total_agents;

            // Type breakdown
            var locs = stats.by_type.location || 0;
            var vehs = stats.by_type.vehicle || 0;
            var rtes = stats.by_type.route || 0;
            document.getElementById('type-locations').textContent = locs;
            document.getElementById('type-vehicles').textContent = vehs;
            document.getElementById('type-routes').textContent = rtes;

            // Bar chart
            var max = Math.max(locs, vehs, rtes, 1);
            setTimeout(function () {
                document.getElementById('bar-locations').style.height = ((locs / max) * 100) + '%';
                document.getElementById('bar-vehicles').style.height = ((vehs / max) * 100) + '%';
                document.getElementById('bar-routes').style.height = ((rtes / max) * 100) + '%';
            }, 100);

            // Recent table
            var tbody = document.getElementById('recent-tbody');
            if (!recent || recent.length === 0) {
                tbody.innerHTML = Sawari.emptyRow(4, 'No contributions yet. Start collecting data!');
                feather.replace({ 'stroke-width': 1.75 });
                return;
            }

            var html = '';
            recent.forEach(function (c) {
                html += '<tr>';
                html += '<td>' + Sawari.typeBadge(c.type) + '</td>';
                html += '<td style="font-weight:var(--font-medium);">' + Sawari.escape(c.item_name) + '</td>';
                html += '<td>' + Sawari.statusBadge(c.status) + '</td>';
                html += '<td style="color:var(--color-neutral-500);font-size:var(--text-sm);">' + Sawari.timeAgo(c.created_at) + '</td>';
                html += '</tr>';
            });
            tbody.innerHTML = html;
            feather.replace({ 'stroke-width': 1.75 });
        });
    }

    document.addEventListener('DOMContentLoaded', loadDashboard);
})();
</script>

<?php require_once __DIR__ . '/../../includes/agent-footer.php'; ?>
