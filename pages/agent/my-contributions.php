<?php
/**
 * SAWARI — My Contributions (Agent)
 * 
 * Lists all contributions by the logged-in agent with status indicators.
 */

require_once __DIR__ . '/../../includes/auth-agent.php';

$pageTitle = 'My Contributions';
$currentPage = 'contributions';

require_once __DIR__ . '/../../includes/agent-header.php';
?>

<!-- Filters -->
<div class="card" style="margin-bottom:var(--space-4);">
    <div class="card-body" style="padding:var(--space-3) var(--space-4);">
        <div style="display:flex;gap:var(--space-3);align-items:center;flex-wrap:wrap;">
            <select id="filter-type" class="form-input" style="width:auto;min-width:140px;">
                <option value="">All Types</option>
                <option value="location">Locations</option>
                <option value="vehicle">Vehicles</option>
                <option value="route">Routes</option>
            </select>
            <select id="filter-status" class="form-input" style="width:auto;min-width:140px;">
                <option value="">All Statuses</option>
                <option value="pending">Pending</option>
                <option value="approved">Approved</option>
                <option value="rejected">Rejected</option>
            </select>
            <span id="result-count"
                style="margin-left:auto;font-size:var(--text-sm);color:var(--color-neutral-500);"></span>
        </div>
    </div>
</div>

<!-- Contributions Table -->
<div class="card">
    <div class="card-body" style="padding:0;">
        <table class="table">
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Item Name</th>
                    <th>Status</th>
                    <th>Submitted</th>
                    <th>Reviewed</th>
                    <th style="width:48px;"></th>
                </tr>
            </thead>
            <tbody id="contributions-tbody">
                <tr>
                    <td colspan="6" class="text-center text-muted" style="padding:var(--space-8);">Loading...</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Pagination -->
<div id="pagination-container" style="margin-top:var(--space-4);"></div>

<script>
    (function () {
        'use strict';

        var currentPage = 1;

        function loadList() {
            var type = document.getElementById('filter-type').value;
            var status = document.getElementById('filter-status').value;

            var params = { page: currentPage };
            if (type) params.type = type;
            if (status) params.status = status;

            Sawari.api('contributions', 'my-list', params, 'GET').then(function (res) {
                if (!res.success) return;

                var tbody = document.getElementById('contributions-tbody');
                var contributions = res.contributions;
                var pagination = res.pagination;

                document.getElementById('result-count').textContent = pagination.total + ' contribution' + (pagination.total !== 1 ? 's' : '');

                if (!contributions.length) {
                    tbody.innerHTML = Sawari.emptyRow(6, 'No contributions found.');
                    feather.replace({ 'stroke-width': 1.75 });
                    document.getElementById('pagination-container').innerHTML = '';
                    return;
                }

                var html = '';
                contributions.forEach(function (c) {
                    html += '<tr>';
                    html += '<td>' + Sawari.typeBadge(c.type) + '</td>';
                    html += '<td style="font-weight:var(--font-medium);">' + Sawari.escape(c.item_name) + '</td>';
                    html += '<td>' + Sawari.statusBadge(c.status) + '</td>';
                    html += '<td style="font-size:var(--text-sm);color:var(--color-neutral-500);">' + Sawari.timeAgo(c.created_at) + '</td>';
                    html += '<td style="font-size:var(--text-sm);color:var(--color-neutral-500);">';
                    if (c.reviewed_at) {
                        html += Sawari.timeAgo(c.reviewed_at);
                    } else {
                        html += '<span style="color:var(--color-neutral-300);">—</span>';
                    }
                    html += '</td>';
                    html += '<td>';
                    if (c.status === 'rejected' && c.rejection_reason) {
                        html += '<button class="btn btn-ghost btn-icon btn-sm view-reason" data-reason="' + Sawari.escape(c.rejection_reason) + '" title="View rejection reason">';
                        html += '<i data-feather="message-circle" style="width:14px;height:14px;"></i></button>';
                    }
                    html += '</td>';
                    html += '</tr>';
                });
                tbody.innerHTML = html;
                feather.replace({ 'stroke-width': 1.75 });

                // Bind view reason
                tbody.querySelectorAll('.view-reason').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        Sawari.modal('Rejection Reason', '<p style="margin:0;color:var(--color-neutral-700);">' + Sawari.escape(this.dataset.reason) + '</p>');
                    });
                });

                // Pagination
                Sawari.pagination(
                    document.getElementById('pagination-container'),
                    pagination.page,
                    pagination.total_pages,
                    function (page) {
                        currentPage = page;
                        loadList();
                        window.scrollTo(0, 0);
                    }
                );
            });
        }

        // Filters
        document.getElementById('filter-type').addEventListener('change', function () { currentPage = 1; loadList(); });
        document.getElementById('filter-status').addEventListener('change', function () { currentPage = 1; loadList(); });

        document.addEventListener('DOMContentLoaded', loadList);
    })();
</script>

<?php require_once __DIR__ . '/../../includes/agent-footer.php'; ?>