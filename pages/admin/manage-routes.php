<?php
/**
 * SAWARI — Manage Routes (Admin)
 *
 * Filterable table, map preview of stops, approve/reject, create/edit modal
 * with route builder (add stops from approved locations).
 */

require_once __DIR__ . '/../../includes/auth-admin.php';

$pageTitle   = 'Manage Routes';
$currentPage = 'manage-routes';
$pageActions = '<button class="btn btn-primary btn-sm" onclick="openCreateRoute()"><i data-feather="plus" style="width:14px;height:14px;"></i> Add Route</button>';
require_once __DIR__ . '/../../includes/admin-header.php';
?>

<!-- Filters -->
<div class="filters-bar">
    <div class="search-bar" style="flex:1;max-width:320px;">
        <i data-feather="search" class="search-bar-icon"></i>
        <input type="text" class="search-bar-input" id="search-input" placeholder="Search routes...">
    </div>
    <select class="form-select" id="filter-status" style="width:auto;">
        <option value="">All Statuses</option>
        <option value="pending">Pending</option>
        <option value="approved">Approved</option>
        <option value="rejected">Rejected</option>
    </select>
</div>

<!-- Map Preview -->
<div class="card" style="margin-bottom:var(--space-6);">
    <div id="routes-map" style="height:280px;border-radius:var(--radius-lg);"></div>
</div>

<!-- Table -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-container" style="border:none;border-radius:0;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Route Name</th>
                        <th>Stops</th>
                        <th>Fare</th>
                        <th>Status</th>
                        <th>Submitted by</th>
                        <th style="width:140px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="routes-tbody">
                    <tr><td colspan="6" class="text-center text-muted p-6">Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
<div id="routes-pagination" style="margin-top:var(--space-4);"></div>

<script>
(function() {
    var currentPage = 1;
    var map, polylineLayer, markersLayer;
    var debounceTimer;

    document.addEventListener('DOMContentLoaded', function() {
        initMap();
        loadRoutes();
        document.getElementById('search-input').addEventListener('input', function() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function() { currentPage = 1; loadRoutes(); }, 350);
        });
        document.getElementById('filter-status').addEventListener('change', function() { currentPage = 1; loadRoutes(); });
    });

    function initMap() {
        map = L.map('routes-map').setView([27.7172, 85.3240], 12);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OSM contributors', maxZoom: 19
        }).addTo(map);
        polylineLayer = L.layerGroup().addTo(map);
        markersLayer  = L.layerGroup().addTo(map);
    }

    function loadRoutes() {
        var p = { page: currentPage };
        var s = document.getElementById('filter-status').value;
        var q = document.getElementById('search-input').value.trim();
        if (s) p.status = s;
        if (q) p.q = q;

        Sawari.api('routes', 'list', p, 'GET').then(function(data) {
            if (!data.success) return;
            renderTable(data.routes);
            Sawari.pagination(document.getElementById('routes-pagination'), data.pagination.page, data.pagination.total_pages, function(pg) { currentPage = pg; loadRoutes(); });
        });
    }

    function renderTable(routes) {
        var tbody = document.getElementById('routes-tbody');
        if (!routes.length) {
            tbody.innerHTML = Sawari.emptyRow(6, 'No routes found.');
            polylineLayer.clearLayers();
            markersLayer.clearLayers();
            feather.replace({ 'stroke-width': 1.75 });
            return;
        }

        tbody.innerHTML = routes.map(function(r) {
            var fare = r.fare_base ? 'Rs ' + parseFloat(r.fare_base).toFixed(0) : '—';
            if (r.fare_per_km) fare += ' + Rs ' + parseFloat(r.fare_per_km).toFixed(0) + '/km';

            var actions = '';
            if (r.status === 'pending') {
                actions += '<button class="btn btn-sm btn-success" onclick="approveRoute(' + r.route_id + ')" title="Approve"><i data-feather="check" style="width:14px;height:14px;"></i></button> ';
                actions += '<button class="btn btn-sm btn-danger" onclick="rejectRoute(' + r.route_id + ')" title="Reject"><i data-feather="x" style="width:14px;height:14px;"></i></button> ';
            }
            actions += '<button class="btn btn-sm btn-ghost" onclick="viewRoute(' + r.route_id + ')" title="View on map"><i data-feather="map" style="width:14px;height:14px;"></i></button> ';
            actions += '<button class="btn btn-sm btn-ghost" onclick="editRoute(' + r.route_id + ')" title="Edit"><i data-feather="edit-2" style="width:14px;height:14px;"></i></button> ';
            actions += '<button class="btn btn-sm btn-ghost" onclick="deleteRoute(' + r.route_id + ', \'' + Sawari.escape(r.name).replace(/'/g, "\\'") + '\')" title="Delete"><i data-feather="trash-2" style="width:14px;height:14px;"></i></button>';

            return '<tr>' +
                '<td><strong>' + Sawari.escape(r.name) + '</strong>' + (r.description ? '<br><small class="text-muted">' + Sawari.escape(r.description).substring(0,60) + '</small>' : '') + '</td>' +
                '<td><span class="badge badge-neutral">' + r.stop_count + ' stops</span></td>' +
                '<td class="text-muted">' + fare + '</td>' +
                '<td><span class="badge badge-' + r.status + '">' + r.status + '</span></td>' +
                '<td class="text-muted">' + Sawari.escape(r.agent_name || '—') + '</td>' +
                '<td>' + actions + '</td>' +
            '</tr>';
        }).join('');

        feather.replace({ 'stroke-width': 1.75 });
    }

    /* ── View Route on Map ────────────────────────────── */
    window.viewRoute = function(id) {
        Sawari.api('routes', 'get', { id: id }, 'GET').then(function(data) {
            if (!data.success) return;
            plotRoute(data.route.location_list_parsed || []);
        });
    };

    function plotRoute(stops) {
        polylineLayer.clearLayers();
        markersLayer.clearLayers();
        if (!stops.length) return;

        var latlngs = [];
        stops.forEach(function(s, i) {
            var lat = parseFloat(s.latitude);
            var lng = parseFloat(s.longitude);
            if (isNaN(lat) || isNaN(lng)) return;
            latlngs.push([lat, lng]);

            var marker = L.circleMarker([lat, lng], {
                radius: 7, fillColor: i === 0 ? '#059669' : (i === stops.length - 1 ? '#dc2626' : '#1A56DB'),
                color: '#fff', weight: 2, fillOpacity: 0.9
            }).bindPopup('<strong>' + (i+1) + '.</strong> ' + Sawari.escape(s.name));
            markersLayer.addLayer(marker);
        });

        if (latlngs.length > 1) {
            var polyline = L.polyline(latlngs, { color: '#1A56DB', weight: 3, opacity: 0.7, dashArray: '8 4' });
            polylineLayer.addLayer(polyline);
        }
        if (latlngs.length) map.fitBounds(latlngs, { padding: [30, 30], maxZoom: 14 });
    }

    /* ── CRUD ─────────────────────────────────────────── */
    window.approveRoute = function(id) {
        Sawari.confirm('Approve this route?', function() {
            Sawari.api('routes', 'approve', { id: id }).then(function(d) {
                if (d.success) { Sawari.toast('Route approved.', 'success'); loadRoutes(); }
            });
        }, 'Approve', 'btn-success');
    };

    window.rejectRoute = function(id) {
        Sawari.rejectPrompt(function(reason) {
            Sawari.api('routes', 'reject', { id: id, reason: reason }).then(function(d) {
                if (d.success) { Sawari.toast('Route rejected.', 'success'); loadRoutes(); }
            });
        });
    };

    window.deleteRoute = function(id, name) {
        Sawari.confirm('Delete route "' + name + '"?', function() {
            Sawari.api('routes', 'delete', { id: id }).then(function(d) {
                if (d.success) { Sawari.toast('Route deleted.', 'success'); loadRoutes(); }
            });
        }, 'Delete', 'btn-danger');
    };

    window.editRoute = function(id) {
        Sawari.api('routes', 'get', { id: id }, 'GET').then(function(d) {
            if (d.success) openRouteForm('Edit Route', d.route);
        });
    };

    window.openCreateRoute = function() {
        openRouteForm('Add Route', null);
    };

    /* ── Route Form Modal ─────────────────────────────── */
    function openRouteForm(title, route) {
        var isEdit = !!route;
        var stops  = isEdit ? (route.location_list_parsed || []) : [];

        var body =
            '<form id="route-form" class="form-stack">' +
            (isEdit ? '<input type="hidden" name="route_id" value="' + route.route_id + '">' : '') +
            '<div class="form-group">' +
                '<label class="form-label">Route Name *</label>' +
                '<input type="text" name="name" class="form-input" value="' + (isEdit ? Sawari.escape(route.name) : '') + '" required>' +
            '</div>' +
            '<div class="form-group">' +
                '<label class="form-label">Description</label>' +
                '<textarea name="description" class="form-input" rows="2">' + (isEdit && route.description ? Sawari.escape(route.description) : '') + '</textarea>' +
            '</div>' +
            '<div class="form-row">' +
                '<div class="form-group" style="flex:1;">' +
                    '<label class="form-label">Base Fare (Rs)</label>' +
                    '<input type="number" name="fare_base" class="form-input" step="0.01" value="' + (isEdit && route.fare_base ? route.fare_base : '') + '">' +
                '</div>' +
                '<div class="form-group" style="flex:1;">' +
                    '<label class="form-label">Fare per km (Rs)</label>' +
                    '<input type="number" name="fare_per_km" class="form-input" step="0.01" value="' + (isEdit && route.fare_per_km ? route.fare_per_km : '') + '">' +
                '</div>' +
            '</div>' +
            '<div class="form-group">' +
                '<label class="form-label">Stops — search and add locations in order</label>' +
                '<div class="search-bar" style="margin-bottom:var(--space-2);">' +
                    '<i data-feather="search" class="search-bar-icon"></i>' +
                    '<input type="text" class="search-bar-input" id="stop-search" placeholder="Search approved locations...">' +
                '</div>' +
                '<div id="stop-search-results" style="max-height:120px;overflow-y:auto;margin-bottom:var(--space-2);"></div>' +
                '<div id="selected-stops" class="route-stops-list"></div>' +
            '</div>' +
            '<input type="hidden" name="location_list" id="location-list-input">' +
            '</form>';

        var footer =
            '<button class="btn btn-secondary" onclick="Sawari.modal()">Cancel</button>' +
            '<button class="btn btn-primary" id="route-save-btn">' + (isEdit ? 'Update' : 'Create') + '</button>';

        Sawari.modal(title, body, footer);

        // State for stop list
        var selectedStops = stops.slice();
        renderSelectedStops();

        // Search locations
        var searchTimer;
        document.getElementById('stop-search').addEventListener('input', function() {
            var val = this.value.trim();
            clearTimeout(searchTimer);
            if (val.length < 2) { document.getElementById('stop-search-results').innerHTML = ''; return; }
            searchTimer = setTimeout(function() {
                Sawari.api('locations', 'search', { q: val }, 'GET').then(function(d) {
                    if (!d.success) return;
                    var results = d.locations.filter(function(loc) {
                        return !selectedStops.some(function(s) { return s.location_id == loc.location_id; });
                    });
                    var html = results.map(function(loc) {
                        return '<div class="stop-search-item" style="padding:var(--space-2);cursor:pointer;border-bottom:1px solid var(--color-neutral-100);font-size:var(--text-sm);" data-loc=\'' + JSON.stringify(loc) + '\'>' +
                            '<strong>' + Sawari.escape(loc.name) + '</strong> <span class="text-muted">(' + loc.type + ')</span></div>';
                    }).join('');
                    document.getElementById('stop-search-results').innerHTML = html || '<p class="text-muted" style="padding:var(--space-2);font-size:var(--text-sm);">No results</p>';

                    document.querySelectorAll('.stop-search-item').forEach(function(el) {
                        el.addEventListener('click', function() {
                            var loc = JSON.parse(this.dataset.loc);
                            selectedStops.push(loc);
                            renderSelectedStops();
                            document.getElementById('stop-search').value = '';
                            document.getElementById('stop-search-results').innerHTML = '';
                        });
                    });
                });
            }, 300);
        });

        function renderSelectedStops() {
            var container = document.getElementById('selected-stops');
            if (!selectedStops.length) {
                container.innerHTML = '<p class="text-muted" style="font-size:var(--text-sm);">No stops added yet. Search and click to add.</p>';
                document.getElementById('location-list-input').value = '';
                return;
            }

            container.innerHTML = selectedStops.map(function(s, i) {
                return '<div class="route-stop-chip" style="display:flex;align-items:center;gap:var(--space-2);padding:var(--space-2);background:var(--color-neutral-50);border-radius:var(--radius-md);margin-bottom:var(--space-1);font-size:var(--text-sm);">' +
                    '<span class="badge badge-neutral" style="min-width:24px;text-align:center;">' + (i+1) + '</span>' +
                    '<span style="flex:1;">' + Sawari.escape(s.name) + '</span>' +
                    (i > 0 ? '<button type="button" class="btn btn-ghost btn-xs" onclick="moveStop(' + i + ',-1)" title="Move up"><i data-feather="arrow-up" style="width:12px;height:12px;"></i></button>' : '') +
                    (i < selectedStops.length - 1 ? '<button type="button" class="btn btn-ghost btn-xs" onclick="moveStop(' + i + ',1)" title="Move down"><i data-feather="arrow-down" style="width:12px;height:12px;"></i></button>' : '') +
                    '<button type="button" class="btn btn-ghost btn-xs" onclick="removeStop(' + i + ')" title="Remove"><i data-feather="x" style="width:12px;height:12px;"></i></button>' +
                '</div>';
            }).join('');

            document.getElementById('location-list-input').value = JSON.stringify(selectedStops);
            feather.replace({ 'stroke-width': 1.75 });
        }

        window.moveStop = function(idx, dir) {
            var newIdx = idx + dir;
            if (newIdx < 0 || newIdx >= selectedStops.length) return;
            var temp = selectedStops[idx];
            selectedStops[idx]    = selectedStops[newIdx];
            selectedStops[newIdx] = temp;
            renderSelectedStops();
        };

        window.removeStop = function(idx) {
            selectedStops.splice(idx, 1);
            renderSelectedStops();
        };

        // Save
        document.getElementById('route-save-btn').addEventListener('click', function() {
            var form = document.getElementById('route-form');
            var fd   = new FormData(form);

            if (!fd.get('name')) { Sawari.toast('Route name is required.', 'warning'); return; }
            if (selectedStops.length < 2) { Sawari.toast('Route needs at least 2 stops.', 'warning'); return; }

            fd.set('location_list', JSON.stringify(selectedStops));

            var btn = this;
            Sawari.setLoading(btn, true);
            Sawari.api('routes', isEdit ? 'update' : 'create', fd).then(function(d) {
                Sawari.setLoading(btn, false);
                if (d.success) { Sawari.modal(); Sawari.toast(d.message, 'success'); loadRoutes(); }
            }).catch(function() { Sawari.setLoading(btn, false); });
        });
    }

})();
</script>

<?php require_once __DIR__ . '/../../includes/admin-footer.php'; ?>
