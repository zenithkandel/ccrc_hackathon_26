<?php
/**
 * SAWARI — Manage Agents (Admin)
 *
 * Agent cards with stats, status toggle, contribution history view.
 */

require_once __DIR__ . '/../../includes/auth-admin.php';

$pageTitle   = 'Manage Agents';
$currentPage = 'manage-agents';
require_once __DIR__ . '/../../includes/admin-header.php';
?>

<!-- Filters -->
<div class="filters-bar">
    <div class="search-bar" style="flex:1;max-width:320px;">
        <i data-feather="search" class="search-bar-icon"></i>
        <input type="text" class="search-bar-input" id="search-input" placeholder="Search agents...">
    </div>
    <select class="form-select" id="filter-status" style="width:auto;">
        <option value="">All Statuses</option>
        <option value="active">Active</option>
        <option value="suspended">Suspended</option>
        <option value="inactive">Inactive</option>
    </select>
</div>

<!-- Table -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-container" style="border:none;border-radius:0;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Agent</th>
                        <th>Points</th>
                        <th>Contributions</th>
                        <th>Approved</th>
                        <th>Status</th>
                        <th>Last Login</th>
                        <th style="width:160px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="agents-tbody">
                    <tr><td colspan="7" class="text-center text-muted p-6">Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
<div id="agents-pagination" style="margin-top:var(--space-4);"></div>

<script>
(function() {
    var currentPage = 1;
    var debounceTimer;

    document.addEventListener('DOMContentLoaded', function() {
        loadAgents();
        document.getElementById('search-input').addEventListener('input', function() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function() { currentPage = 1; loadAgents(); }, 350);
        });
        document.getElementById('filter-status').addEventListener('change', function() { currentPage = 1; loadAgents(); });
    });

    function loadAgents() {
        var p = { page: currentPage };
        var s = document.getElementById('filter-status').value;
        var q = document.getElementById('search-input').value.trim();
        if (s) p.status = s;
        if (q) p.q = q;

        Sawari.api('agents', 'list', p, 'GET').then(function(data) {
            if (!data.success) return;
            renderTable(data.agents);
            Sawari.pagination(document.getElementById('agents-pagination'), data.pagination.page, data.pagination.total_pages, function(pg) { currentPage = pg; loadAgents(); });
        });
    }

    function renderTable(agents) {
        var tbody = document.getElementById('agents-tbody');
        if (!agents.length) {
            tbody.innerHTML = Sawari.emptyRow(7, 'No agents found.');
            feather.replace({ 'stroke-width': 1.75 });
            return;
        }

        tbody.innerHTML = agents.map(function(a) {
            var initials = a.name.split(' ').map(function(w) { return w.charAt(0); }).join('').substring(0,2).toUpperCase();
            var statusMap = { active: 'badge-approved', suspended: 'badge-rejected', inactive: 'badge-neutral' };

            var actions = '';
            if (a.status === 'suspended' || a.status === 'inactive') {
                actions += '<button class="btn btn-sm btn-success" onclick="activateAgent(' + a.agent_id + ')" title="Activate"><i data-feather="check-circle" style="width:14px;height:14px;"></i></button> ';
            }
            if (a.status === 'active') {
                actions += '<button class="btn btn-sm btn-danger" onclick="suspendAgent(' + a.agent_id + ', \'' + Sawari.escape(a.name).replace(/'/g, "\\'") + '\')" title="Suspend"><i data-feather="slash" style="width:14px;height:14px;"></i></button> ';
            }
            actions += '<button class="btn btn-sm btn-ghost" onclick="viewAgent(' + a.agent_id + ')" title="View details"><i data-feather="eye" style="width:14px;height:14px;"></i></button> ';
            actions += '<button class="btn btn-sm btn-ghost" onclick="deleteAgent(' + a.agent_id + ', \'' + Sawari.escape(a.name).replace(/'/g, "\\'") + '\')" title="Delete"><i data-feather="trash-2" style="width:14px;height:14px;"></i></button>';

            return '<tr>' +
                '<td>' +
                    '<div style="display:flex;align-items:center;gap:var(--space-3);">' +
                        '<div class="avatar">' + initials + '</div>' +
                        '<div><strong>' + Sawari.escape(a.name) + '</strong><br><small class="text-muted">' + Sawari.escape(a.email) + '</small></div>' +
                    '</div>' +
                '</td>' +
                '<td><strong>' + a.points + '</strong></td>' +
                '<td>' + a.contributions_count + '</td>' +
                '<td>' + a.approved_count + '</td>' +
                '<td><span class="badge ' + (statusMap[a.status] || 'badge-neutral') + '">' + a.status + '</span></td>' +
                '<td class="text-muted" style="font-size:var(--text-sm);">' + (a.last_login || 'Never') + '</td>' +
                '<td>' + actions + '</td>' +
            '</tr>';
        }).join('');

        feather.replace({ 'stroke-width': 1.75 });
    }

    /* ── Actions ──────────────────────────────────────── */
    window.activateAgent = function(id) {
        Sawari.confirm('Activate this agent?', function() {
            Sawari.api('agents', 'activate', { id: id }).then(function(d) {
                if (d.success) { Sawari.toast('Agent activated.', 'success'); loadAgents(); }
            });
        }, 'Activate', 'btn-success');
    };

    window.suspendAgent = function(id, name) {
        Sawari.confirm('Suspend agent "' + name + '"? They will not be able to log in.', function() {
            Sawari.api('agents', 'suspend', { id: id }).then(function(d) {
                if (d.success) { Sawari.toast('Agent suspended.', 'success'); loadAgents(); }
            });
        }, 'Suspend', 'btn-danger');
    };

    window.deleteAgent = function(id, name) {
        Sawari.confirm('Delete agent "' + name + '"? This will also delete all their contributions. This cannot be undone.', function() {
            Sawari.api('agents', 'delete', { id: id }).then(function(d) {
                if (d.success) { Sawari.toast('Agent deleted.', 'success'); loadAgents(); }
            });
        }, 'Delete', 'btn-danger');
    };

    window.viewAgent = function(id) {
        Sawari.api('agents', 'get', { id: id }, 'GET').then(function(d) {
            if (!d.success) return;
            var a = d.agent;
            var conts = d.recent_contributions || [];

            var body =
                '<div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--space-4);margin-bottom:var(--space-6);">' +
                    '<div><span class="text-muted" style="font-size:var(--text-xs);">Email</span><br>' + Sawari.escape(a.email) + '</div>' +
                    '<div><span class="text-muted" style="font-size:var(--text-xs);">Phone</span><br>' + Sawari.escape(a.phone || '—') + '</div>' +
                    '<div><span class="text-muted" style="font-size:var(--text-xs);">Points</span><br><strong>' + a.points + '</strong></div>' +
                    '<div><span class="text-muted" style="font-size:var(--text-xs);">Joined</span><br>' + Sawari.escape(a.created_at) + '</div>' +
                '</div>';

            if (conts.length) {
                body += '<h5 style="margin-bottom:var(--space-2);">Recent Contributions</h5>';
                body += '<div style="max-height:200px;overflow-y:auto;">';
                conts.forEach(function(c) {
                    var statusClass = { pending: 'badge-pending', approved: 'badge-approved', rejected: 'badge-rejected' }[c.status] || '';
                    body += '<div style="display:flex;justify-content:space-between;align-items:center;padding:var(--space-2) 0;border-bottom:1px solid var(--color-neutral-100);font-size:var(--text-sm);">' +
                        '<span>' + c.type + '</span>' +
                        '<span class="badge ' + statusClass + '">' + c.status + '</span>' +
                    '</div>';
                });
                body += '</div>';
            }

            Sawari.modal(a.name, body, '<button class="btn btn-secondary" onclick="Sawari.modal()">Close</button>');
        });
    };

})();
</script>

<?php require_once __DIR__ . '/../../includes/admin-footer.php'; ?>
