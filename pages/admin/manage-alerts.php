<?php
/**
 * SAWARI — Manage Alerts (Admin)
 *
 * Create/edit/resolve alerts with severity colours and route linking.
 */

require_once __DIR__ . '/../../includes/auth-admin.php';

$pageTitle   = 'Manage Alerts';
$currentPage = 'manage-alerts';
$pageActions = '<button class="btn btn-primary btn-sm" onclick="openCreateAlert()"><i data-feather="plus" style="width:14px;height:14px;"></i> New Alert</button>';
require_once __DIR__ . '/../../includes/admin-header.php';
?>

<!-- Filters -->
<div class="filters-bar">
    <select class="form-select" id="filter-status" style="width:auto;">
        <option value="active" selected>Active</option>
        <option value="">All</option>
        <option value="resolved">Resolved</option>
        <option value="expired">Expired</option>
    </select>
    <select class="form-select" id="filter-severity" style="width:auto;">
        <option value="">All Severities</option>
        <option value="critical">Critical</option>
        <option value="high">High</option>
        <option value="medium">Medium</option>
        <option value="low">Low</option>
    </select>
</div>

<!-- Table -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-container" style="border:none;border-radius:0;">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:8px;"></th>
                        <th>Title</th>
                        <th>Route</th>
                        <th>Severity</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Expires</th>
                        <th style="width:140px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="alerts-tbody">
                    <tr><td colspan="8" class="text-center text-muted p-6">Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
<div id="alerts-pagination" style="margin-top:var(--space-4);"></div>

<script>
(function() {
    var currentPage = 1;

    document.addEventListener('DOMContentLoaded', function() {
        loadAlerts();
        document.getElementById('filter-status').addEventListener('change', function() { currentPage = 1; loadAlerts(); });
        document.getElementById('filter-severity').addEventListener('change', function() { currentPage = 1; loadAlerts(); });
    });

    function loadAlerts() {
        var p = { page: currentPage };
        var s = document.getElementById('filter-status').value;
        var sv = document.getElementById('filter-severity').value;
        if (s)  p.status = s;
        if (sv) p.severity = sv;

        Sawari.api('alerts', 'list', p, 'GET').then(function(data) {
            if (!data.success) return;
            renderTable(data.alerts);
            Sawari.pagination(document.getElementById('alerts-pagination'), data.pagination.page, data.pagination.total_pages, function(pg) { currentPage = pg; loadAlerts(); });
        });
    }

    function renderTable(alerts) {
        var tbody = document.getElementById('alerts-tbody');
        if (!alerts.length) {
            tbody.innerHTML = Sawari.emptyRow(8, 'No alerts found.');
            feather.replace({ 'stroke-width': 1.75 });
            return;
        }

        var sevColors = { critical: '#dc2626', high: '#ea580c', medium: '#d97706', low: '#2563eb' };
        var sevBadge  = { critical: 'badge-rejected', high: 'badge-pending', medium: 'badge-neutral', low: 'badge-approved' };

        tbody.innerHTML = alerts.map(function(al) {
            var color = sevColors[al.severity] || '#64748b';
            var statusMap = { active: 'badge-approved', resolved: 'badge-neutral', expired: 'badge-rejected' };

            var actions = '';
            if (al.status === 'active') {
                actions += '<button class="btn btn-sm btn-success" onclick="resolveAlert(' + al.alert_id + ')" title="Resolve"><i data-feather="check-circle" style="width:14px;height:14px;"></i></button> ';
            }
            actions += '<button class="btn btn-sm btn-ghost" onclick="editAlert(' + al.alert_id + ')" title="Edit"><i data-feather="edit-2" style="width:14px;height:14px;"></i></button> ';
            actions += '<button class="btn btn-sm btn-ghost" onclick="deleteAlert(' + al.alert_id + ')" title="Delete"><i data-feather="trash-2" style="width:14px;height:14px;"></i></button>';

            return '<tr>' +
                '<td><div style="width:4px;height:32px;border-radius:2px;background:' + color + ';"></div></td>' +
                '<td><strong>' + Sawari.escape(al.title) + '</strong><br><small class="text-muted">' + Sawari.escape(al.description).substring(0,80) + '</small></td>' +
                '<td class="text-muted">' + Sawari.escape(al.route_name || 'System-wide') + '</td>' +
                '<td><span class="badge ' + (sevBadge[al.severity] || '') + '">' + al.severity + '</span></td>' +
                '<td><span class="badge ' + (statusMap[al.status] || 'badge-neutral') + '">' + al.status + '</span></td>' +
                '<td class="text-muted" style="font-size:var(--text-sm);">' + Sawari.escape(al.created_at) + '</td>' +
                '<td class="text-muted" style="font-size:var(--text-sm);">' + Sawari.escape(al.expires_at || 'Never') + '</td>' +
                '<td>' + actions + '</td>' +
            '</tr>';
        }).join('');

        feather.replace({ 'stroke-width': 1.75 });
    }

    window.resolveAlert = function(id) {
        Sawari.confirm('Mark this alert as resolved?', function() {
            Sawari.api('alerts', 'resolve', { id: id }).then(function(d) {
                if (d.success) { Sawari.toast('Alert resolved.', 'success'); loadAlerts(); }
            });
        }, 'Resolve', 'btn-success');
    };

    window.deleteAlert = function(id) {
        Sawari.confirm('Delete this alert permanently?', function() {
            Sawari.api('alerts', 'delete', { id: id }).then(function(d) {
                if (d.success) { Sawari.toast('Alert deleted.', 'success'); loadAlerts(); }
            });
        }, 'Delete', 'btn-danger');
    };

    window.editAlert = function(id) {
        Sawari.api('alerts', 'get', { id: id }, 'GET').then(function(d) {
            if (d.success) openAlertForm('Edit Alert', d.alert);
        });
    };

    window.openCreateAlert = function() {
        openAlertForm('New Alert', null);
    };

    function openAlertForm(title, al) {
        var isEdit = !!al;

        var body =
            '<form id="alert-form" class="form-stack">' +
            (isEdit ? '<input type="hidden" name="alert_id" value="' + al.alert_id + '">' : '') +
            '<div class="form-group">' +
                '<label class="form-label">Title *</label>' +
                '<input type="text" name="title" class="form-input" value="' + (isEdit ? Sawari.escape(al.title) : '') + '" required>' +
            '</div>' +
            '<div class="form-group">' +
                '<label class="form-label">Description *</label>' +
                '<textarea name="description" class="form-input" rows="3" required>' + (isEdit ? Sawari.escape(al.description) : '') + '</textarea>' +
            '</div>' +
            '<div class="form-row">' +
                '<div class="form-group" style="flex:1;">' +
                    '<label class="form-label">Severity</label>' +
                    '<select name="severity" class="form-select">' +
                        '<option value="low"' + (isEdit && al.severity === 'low' ? ' selected' : '') + '>Low</option>' +
                        '<option value="medium"' + (!isEdit || al.severity === 'medium' ? ' selected' : '') + '>Medium</option>' +
                        '<option value="high"' + (isEdit && al.severity === 'high' ? ' selected' : '') + '>High</option>' +
                        '<option value="critical"' + (isEdit && al.severity === 'critical' ? ' selected' : '') + '>Critical</option>' +
                    '</select>' +
                '</div>' +
                '<div class="form-group" style="flex:1;">' +
                    '<label class="form-label">Route ID <small class="text-muted">(optional)</small></label>' +
                    '<input type="number" name="route_id" class="form-input" value="' + (isEdit && al.route_id ? al.route_id : '') + '" placeholder="Leave empty for system-wide">' +
                '</div>' +
            '</div>' +
            '<div class="form-group">' +
                '<label class="form-label">Expires At <small class="text-muted">(optional)</small></label>' +
                '<input type="datetime-local" name="expires_at" class="form-input" value="' + (isEdit && al.expires_at ? al.expires_at.replace(' ', 'T').substring(0,16) : '') + '">' +
            '</div>' +
            '</form>';

        var footer =
            '<button class="btn btn-secondary" onclick="Sawari.modal()">Cancel</button>' +
            '<button class="btn btn-primary" id="alert-save-btn">' + (isEdit ? 'Update' : 'Create') + '</button>';

        Sawari.modal(title, body, footer);

        document.getElementById('alert-save-btn').addEventListener('click', function() {
            var form = document.getElementById('alert-form');
            var fd = new FormData(form);
            if (!fd.get('title') || !fd.get('description')) { Sawari.toast('Title and description are required.', 'warning'); return; }

            var btn = this;
            Sawari.setLoading(btn, true);
            Sawari.api('alerts', isEdit ? 'update' : 'create', fd).then(function(d) {
                Sawari.setLoading(btn, false);
                if (d.success) { Sawari.modal(); Sawari.toast(d.message, 'success'); loadAlerts(); }
            }).catch(function() { Sawari.setLoading(btn, false); });
        });
    }

})();
</script>

<?php require_once __DIR__ . '/../../includes/admin-footer.php'; ?>
