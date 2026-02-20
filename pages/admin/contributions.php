<?php
/**
 * SAWARI — Contributions Review (Admin)
 *
 * Unified queue showing all contributions with status/type filters,
 * inline approve/reject, and detail preview modal.
 */

require_once __DIR__ . '/../../includes/auth-admin.php';

$pageTitle = 'Contributions';
$currentPage = 'contributions';
require_once __DIR__ . '/../../includes/admin-header.php';
?>

<!-- Filters -->
<div class="filters-bar">
    <div class="search-bar" style="flex:1;max-width:240px;">
        <i data-feather="search" class="search-icon"></i>
        <input type="text" class="form-input" id="search-input" placeholder="Search...">
    </div>
    <select class="form-select" id="filter-status" style="width:auto;">
        <option value="pending" selected>Pending</option>
        <option value="">All Statuses</option>
        <option value="approved">Approved</option>
        <option value="rejected">Rejected</option>
    </select>
    <select class="form-select" id="filter-type" style="width:auto;">
        <option value="">All Types</option>
        <option value="location">Location</option>
        <option value="vehicle">Vehicle</option>
        <option value="route">Route</option>
    </select>
</div>

<!-- Table -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-container" style="border:none;border-radius:0;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Item</th>
                        <th>Agent</th>
                        <th>Notes</th>
                        <th>Status</th>
                        <th>Submitted</th>
                        <th style="width:140px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="contrib-tbody">
                    <tr>
                        <td colspan="7" class="text-center text-muted p-6">Loading...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
<div id="contrib-pagination" style="margin-top:var(--space-4);"></div>

<script>
    (function () {
        var currentPage = 1;

        document.addEventListener('DOMContentLoaded', function () {
            loadContributions();
            document.getElementById('filter-status').addEventListener('change', function () { currentPage = 1; loadContributions(); });
            document.getElementById('filter-type').addEventListener('change', function () { currentPage = 1; loadContributions(); });

            var timer;
            document.getElementById('search-input').addEventListener('input', function () {
                clearTimeout(timer);
                timer = setTimeout(function () { currentPage = 1; loadContributions(); }, 350);
            });
        });

        function loadContributions() {
            var p = { page: currentPage };
            var s = document.getElementById('filter-status').value;
            var t = document.getElementById('filter-type').value;
            if (s) p.status = s;
            if (t) p.type = t;

            Sawari.api('contributions', 'list', p, 'GET').then(function (data) {
                if (!data.success) return;
                renderTable(data.contributions);
                Sawari.pagination(document.getElementById('contrib-pagination'), data.pagination.page, data.pagination.total_pages, function (pg) { currentPage = pg; loadContributions(); });
            });
        }

        function renderTable(contribs) {
            var tbody = document.getElementById('contrib-tbody');
            if (!contribs.length) {
                tbody.innerHTML = Sawari.emptyRow(7, 'No contributions found.');
                feather.replace({ 'stroke-width': 1.75 });
                return;
            }

            var typeIcons = { location: 'map-pin', vehicle: 'truck', route: 'git-branch' };

            tbody.innerHTML = contribs.map(function (c) {
                var icon = typeIcons[c.type] || 'file';

                var actions = '';
                if (c.status === 'pending') {
                    actions += '<button class="btn btn-sm btn-success" onclick="approveContrib(' + c.contribution_id + ')" title="Approve"><i data-feather="check" style="width:14px;height:14px;"></i></button> ';
                    actions += '<button class="btn btn-sm btn-danger" onclick="rejectContrib(' + c.contribution_id + ')" title="Reject"><i data-feather="x" style="width:14px;height:14px;"></i></button> ';
                }
                actions += '<button class="btn btn-sm btn-ghost" onclick="viewContrib(' + c.contribution_id + ')" title="View details"><i data-feather="eye" style="width:14px;height:14px;"></i></button>';

                return '<tr>' +
                    '<td><span style="display:inline-flex;align-items:center;gap:var(--space-1);"><i data-feather="' + icon + '" style="width:14px;height:14px;"></i> ' + c.type + '</span></td>' +
                    '<td><strong>' + Sawari.escape(c.item_name) + '</strong></td>' +
                    '<td>' + Sawari.escape(c.agent_name) + '<br><small class="text-muted">' + Sawari.escape(c.agent_email) + '</small></td>' +
                    '<td class="text-muted" style="font-size:var(--text-sm);max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + Sawari.escape(c.notes || '—') + '</td>' +
                    '<td><span class="badge badge-' + c.status + '">' + c.status + '</span></td>' +
                    '<td class="text-muted" style="font-size:var(--text-sm);">' + Sawari.escape(c.created_at) + '</td>' +
                    '<td>' + actions + '</td>' +
                    '</tr>';
            }).join('');

            feather.replace({ 'stroke-width': 1.75 });
        }

        window.approveContrib = function (id) {
            Sawari.confirm('Approve this contribution? The agent will receive 10 points.', function () {
                Sawari.api('contributions', 'approve', { id: id }).then(function (d) {
                    if (d.success) { Sawari.toast('Contribution approved.', 'success'); loadContributions(); }
                });
            }, 'Approve', 'btn-success');
        };

        window.rejectContrib = function (id) {
            Sawari.rejectPrompt(function (reason) {
                Sawari.api('contributions', 'reject', { id: id, reason: reason }).then(function (d) {
                    if (d.success) { Sawari.toast('Contribution rejected.', 'success'); loadContributions(); }
                });
            });
        };

        window.viewContrib = function (id) {
            Sawari.api('contributions', 'get', { id: id }, 'GET').then(function (d) {
                if (!d.success) return;
                var c = d.contribution;
                var item = c.item;

                var body = '<div style="margin-bottom:var(--space-4);">';
                body += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--space-3);margin-bottom:var(--space-4);">';
                body += '<div><span class="text-muted" style="font-size:var(--text-xs);">Type</span><br>' + c.type + '</div>';
                body += '<div><span class="text-muted" style="font-size:var(--text-xs);">Status</span><br><span class="badge badge-' + c.status + '">' + c.status + '</span></div>';
                body += '<div><span class="text-muted" style="font-size:var(--text-xs);">Agent</span><br>' + Sawari.escape(c.agent_name) + '</div>';
                body += '<div><span class="text-muted" style="font-size:var(--text-xs);">Submitted</span><br>' + Sawari.escape(c.created_at) + '</div>';
                body += '</div>';

                if (c.notes) {
                    body += '<div style="margin-bottom:var(--space-3);"><span class="text-muted" style="font-size:var(--text-xs);">Agent Notes</span><br>' + Sawari.escape(c.notes) + '</div>';
                }
                if (c.rejection_reason) {
                    body += '<div style="margin-bottom:var(--space-3);"><span class="text-muted" style="font-size:var(--text-xs);">Rejection Reason</span><br>' + Sawari.escape(c.rejection_reason) + '</div>';
                }

                if (item) {
                    body += '<hr style="border:none;border-top:1px solid var(--color-neutral-100);margin:var(--space-4) 0;">';
                    body += '<h5 style="margin-bottom:var(--space-2);">Submitted Item: ' + Sawari.escape(item.name || '—') + '</h5>';

                    if (c.type === 'location' && item) {
                        body += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--space-3);">';
                        body += '<div><span class="text-muted" style="font-size:var(--text-xs);">Type</span><br>' + (item.type || '—') + '</div>';
                        body += '<div><span class="text-muted" style="font-size:var(--text-xs);">Coordinates</span><br>' + item.latitude + ', ' + item.longitude + '</div>';
                        body += '</div>';
                        if (item.description) body += '<p style="margin-top:var(--space-2);">' + Sawari.escape(item.description) + '</p>';
                    }

                    if (c.type === 'vehicle' && item) {
                        body += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--space-3);">';
                        body += '<div><span class="text-muted" style="font-size:var(--text-xs);">Electric</span><br>' + (item.electric == 1 ? 'Yes' : 'No') + '</div>';
                        body += '<div><span class="text-muted" style="font-size:var(--text-xs);">Hours</span><br>' + (item.starts_at || '—') + ' – ' + (item.stops_at || '—') + '</div>';
                        body += '</div>';
                    }

                    if (c.type === 'route' && item && item.location_list_parsed) {
                        body += '<div style="margin-top:var(--space-2);">';
                        body += '<span class="text-muted" style="font-size:var(--text-xs);">Stops (' + item.location_list_parsed.length + ')</span><br>';
                        item.location_list_parsed.forEach(function (s, i) {
                            body += '<span class="badge badge-neutral" style="margin:2px;">' + (i + 1) + '. ' + Sawari.escape(s.name) + '</span>';
                        });
                        body += '</div>';
                    }
                }
                body += '</div>';

                var footer = '';
                if (c.status === 'pending') {
                    footer = '<button class="btn btn-secondary" onclick="Sawari.modal()">Close</button>' +
                        '<button class="btn btn-danger" onclick="Sawari.modal();rejectContrib(' + c.contribution_id + ')">Reject</button>' +
                        '<button class="btn btn-success" onclick="Sawari.modal();approveContrib(' + c.contribution_id + ')">Approve</button>';
                } else {
                    footer = '<button class="btn btn-secondary" onclick="Sawari.modal()">Close</button>';
                }

                Sawari.modal('Contribution #' + c.contribution_id, body, footer);
            });
        };

    })();
</script>

<?php require_once __DIR__ . '/../../includes/admin-footer.php'; ?>