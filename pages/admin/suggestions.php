<?php
/**
 * SAWARI — Suggestions Inbox (Admin)
 *
 * Review community suggestions with status workflow:
 * pending → reviewed / implemented / dismissed
 */

require_once __DIR__ . '/../../includes/auth-admin.php';

$pageTitle   = 'Suggestions';
$currentPage = 'suggestions';
require_once __DIR__ . '/../../includes/admin-header.php';
?>

<!-- Filters -->
<div class="filters-bar">
    <select class="form-select" id="filter-status" style="width:auto;">
        <option value="pending" selected>Pending</option>
        <option value="">All</option>
        <option value="reviewed">Reviewed</option>
        <option value="implemented">Implemented</option>
        <option value="dismissed">Dismissed</option>
    </select>
    <select class="form-select" id="filter-type" style="width:auto;">
        <option value="">All Types</option>
        <option value="missing_stop">Missing Stop</option>
        <option value="route_correction">Route Correction</option>
        <option value="new_route">New Route</option>
        <option value="general">General</option>
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
                        <th>Title</th>
                        <th>From</th>
                        <th>Route</th>
                        <th>Status</th>
                        <th>Submitted</th>
                        <th style="width:160px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="sug-tbody">
                    <tr><td colspan="7" class="text-center text-muted p-6">Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
<div id="sug-pagination" style="margin-top:var(--space-4);"></div>

<script>
(function() {
    var currentPage = 1;

    document.addEventListener('DOMContentLoaded', function() {
        loadSuggestions();
        document.getElementById('filter-status').addEventListener('change', function() { currentPage = 1; loadSuggestions(); });
        document.getElementById('filter-type').addEventListener('change', function() { currentPage = 1; loadSuggestions(); });
    });

    function loadSuggestions() {
        var p = { page: currentPage };
        var s = document.getElementById('filter-status').value;
        var t = document.getElementById('filter-type').value;
        if (s) p.status = s;
        if (t) p.type = t;

        Sawari.api('suggestions', 'list', p, 'GET').then(function(data) {
            if (!data.success) return;
            renderTable(data.suggestions);
            Sawari.pagination(document.getElementById('sug-pagination'), data.pagination.page, data.pagination.total_pages, function(pg) { currentPage = pg; loadSuggestions(); });
        });
    }

    function renderTable(suggestions) {
        var tbody = document.getElementById('sug-tbody');
        if (!suggestions.length) {
            tbody.innerHTML = Sawari.emptyRow(7, 'No suggestions found.');
            feather.replace({ 'stroke-width': 1.75 });
            return;
        }

        var typeLabels = { missing_stop: 'Missing Stop', route_correction: 'Route Fix', new_route: 'New Route', general: 'General' };
        var statusBadge = { pending: 'badge-pending', reviewed: 'badge-neutral', implemented: 'badge-approved', dismissed: 'badge-rejected' };

        tbody.innerHTML = suggestions.map(function(s) {
            var actions = '<button class="btn btn-sm btn-ghost" onclick="viewSuggestion(' + s.suggestion_id + ')" title="View"><i data-feather="eye" style="width:14px;height:14px;"></i></button> ';

            if (s.status === 'pending') {
                actions += '<button class="btn btn-sm btn-success" onclick="reviewSuggestion(' + s.suggestion_id + ', \'implemented\')" title="Mark Implemented"><i data-feather="check-circle" style="width:14px;height:14px;"></i></button> ';
                actions += '<button class="btn btn-sm btn-ghost" onclick="reviewSuggestion(' + s.suggestion_id + ', \'reviewed\')" title="Mark Reviewed"><i data-feather="eye" style="width:14px;height:14px;"></i></button> ';
                actions += '<button class="btn btn-sm btn-danger" onclick="reviewSuggestion(' + s.suggestion_id + ', \'dismissed\')" title="Dismiss"><i data-feather="x" style="width:14px;height:14px;"></i></button> ';
            }
            actions += '<button class="btn btn-sm btn-ghost" onclick="deleteSuggestion(' + s.suggestion_id + ')" title="Delete"><i data-feather="trash-2" style="width:14px;height:14px;"></i></button>';

            return '<tr>' +
                '<td><span class="badge badge-neutral">' + (typeLabels[s.type] || s.type) + '</span></td>' +
                '<td><strong>' + Sawari.escape(s.title) + '</strong></td>' +
                '<td class="text-muted">' + s.user_type + '</td>' +
                '<td class="text-muted">' + Sawari.escape(s.route_name || '—') + '</td>' +
                '<td><span class="badge ' + (statusBadge[s.status] || '') + '">' + s.status + '</span></td>' +
                '<td class="text-muted" style="font-size:var(--text-sm);">' + Sawari.escape(s.created_at) + '</td>' +
                '<td>' + actions + '</td>' +
            '</tr>';
        }).join('');

        feather.replace({ 'stroke-width': 1.75 });
    }

    window.viewSuggestion = function(id) {
        Sawari.api('suggestions', 'get', { id: id }, 'GET').then(function(d) {
            if (!d.success) return;
            var s = d.suggestion;

            var body = '<div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--space-3);margin-bottom:var(--space-4);">';
            body += '<div><span class="text-muted" style="font-size:var(--text-xs);">Type</span><br>' + s.type + '</div>';
            body += '<div><span class="text-muted" style="font-size:var(--text-xs);">Status</span><br><span class="badge badge-' + s.status + '">' + s.status + '</span></div>';
            body += '<div><span class="text-muted" style="font-size:var(--text-xs);">From</span><br>' + s.user_type + (s.user_identifier ? ' (' + Sawari.escape(s.user_identifier) + ')' : '') + '</div>';
            body += '<div><span class="text-muted" style="font-size:var(--text-xs);">Submitted</span><br>' + Sawari.escape(s.created_at) + '</div>';
            body += '</div>';

            body += '<div style="margin-bottom:var(--space-3);"><span class="text-muted" style="font-size:var(--text-xs);">Description</span><br>' + Sawari.escape(s.description) + '</div>';

            if (s.latitude && s.longitude) {
                body += '<div style="margin-bottom:var(--space-3);"><span class="text-muted" style="font-size:var(--text-xs);">Location</span><br>' + s.latitude + ', ' + s.longitude + '</div>';
            }
            if (s.route_name) {
                body += '<div style="margin-bottom:var(--space-3);"><span class="text-muted" style="font-size:var(--text-xs);">Related Route</span><br>' + Sawari.escape(s.route_name) + '</div>';
            }
            if (s.review_notes) {
                body += '<div style="margin-bottom:var(--space-3);"><span class="text-muted" style="font-size:var(--text-xs);">Review Notes</span><br>' + Sawari.escape(s.review_notes) + '</div>';
            }

            var footer = '<button class="btn btn-secondary" onclick="Sawari.modal()">Close</button>';
            if (s.status === 'pending') {
                footer += ' <button class="btn btn-success" onclick="Sawari.modal();reviewSuggestion(' + s.suggestion_id + ',\'implemented\')">Implement</button>';
                footer += ' <button class="btn btn-danger" onclick="Sawari.modal();reviewSuggestion(' + s.suggestion_id + ',\'dismissed\')">Dismiss</button>';
            }

            Sawari.modal(s.title, body, footer);
        });
    };

    window.reviewSuggestion = function(id, status) {
        var body =
            '<label class="form-label" for="review-notes-input">Notes (optional)</label>' +
            '<textarea id="review-notes-input" class="form-input" rows="2" placeholder="Add any notes..."></textarea>';

        var footer =
            '<button class="btn btn-secondary" onclick="Sawari.modal()">Cancel</button>' +
            '<button class="btn btn-primary" id="review-submit-btn">Submit</button>';

        var label = status.charAt(0).toUpperCase() + status.slice(1);
        Sawari.modal('Mark as ' + label, body, footer);

        document.getElementById('review-submit-btn').addEventListener('click', function() {
            var notes = document.getElementById('review-notes-input').value.trim();
            Sawari.modal();

            Sawari.api('suggestions', 'review', { id: id, status: status, review_notes: notes }).then(function(d) {
                if (d.success) { Sawari.toast(d.message, 'success'); loadSuggestions(); }
            });
        });
    };

    window.deleteSuggestion = function(id) {
        Sawari.confirm('Delete this suggestion permanently?', function() {
            Sawari.api('suggestions', 'delete', { id: id }).then(function(d) {
                if (d.success) { Sawari.toast('Suggestion deleted.', 'success'); loadSuggestions(); }
            });
        }, 'Delete', 'btn-danger');
    };

})();
</script>

<?php require_once __DIR__ . '/../../includes/admin-footer.php'; ?>
