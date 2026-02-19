<?php
/**
 * SAWARI — Manage Locations (Admin)
 *
 * Filterable data table with map preview, approve/reject workflow,
 * and create/edit modal.
 */

require_once __DIR__ . '/../../includes/auth-admin.php';

$pageTitle = 'Manage Locations';
$currentPage = 'manage-locations';
$pageActions = '<button class="btn btn-primary btn-sm" onclick="openCreateModal()"><i data-feather="plus" style="width:14px;height:14px;"></i> Add Location</button>';
require_once __DIR__ . '/../../includes/admin-header.php';
?>

<!-- Filters Bar -->
<div class="filters-bar">
    <div class="search-bar" style="flex:1;max-width:320px;">
        <i data-feather="search" class="search-bar-icon"></i>
        <input type="text" class="search-bar-input" id="search-input" placeholder="Search locations...">
    </div>
    <select class="form-select" id="filter-status" style="width:auto;">
        <option value="">All Statuses</option>
        <option value="pending">Pending</option>
        <option value="approved">Approved</option>
        <option value="rejected">Rejected</option>
    </select>
    <select class="form-select" id="filter-type" style="width:auto;">
        <option value="">All Types</option>
        <option value="stop">Stop</option>
        <option value="landmark">Landmark</option>
    </select>
</div>

<!-- Map Preview -->
<div class="card" style="margin-bottom:var(--space-6);">
    <div id="locations-map" style="height:280px;border-radius:var(--radius-lg);"></div>
</div>

<!-- Data Table -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-container" style="border:none;border-radius:0;">
            <table class="table" id="locations-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Coordinates</th>
                        <th>Status</th>
                        <th>Submitted by</th>
                        <th style="width:140px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="locations-tbody">
                    <tr>
                        <td colspan="6" class="text-center text-muted p-6">Loading...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Pagination -->
<div id="locations-pagination" style="margin-top:var(--space-4);"></div>

<script>
    (function () {
        var currentPage = 1;
        var map, markersLayer;
        var debounceTimer;

        /* ── Init ─────────────────────────────────────────── */
        document.addEventListener('DOMContentLoaded', function () {
            initMap();
            loadLocations();

            document.getElementById('search-input').addEventListener('input', debounceLoad);
            document.getElementById('filter-status').addEventListener('change', function () { currentPage = 1; loadLocations(); });
            document.getElementById('filter-type').addEventListener('change', function () { currentPage = 1; loadLocations(); });
        });

        function debounceLoad() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function () { currentPage = 1; loadLocations(); }, 350);
        }

        /* ── Map ──────────────────────────────────────────── */
        function initMap() {
            map = L.map('locations-map').setView([27.7172, 85.3240], 12);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OSM contributors',
                maxZoom: 19
            }).addTo(map);
            markersLayer = L.layerGroup().addTo(map);
        }

        function plotMarkers(locations) {
            markersLayer.clearLayers();
            if (!locations.length) return;

            var bounds = [];
            locations.forEach(function (loc) {
                var lat = parseFloat(loc.latitude);
                var lng = parseFloat(loc.longitude);
                if (isNaN(lat) || isNaN(lng)) return;

                var color = { pending: '#d97706', approved: '#059669', rejected: '#dc2626' }[loc.status] || '#64748b';
                var marker = L.circleMarker([lat, lng], {
                    radius: 6, fillColor: color, color: '#fff',
                    weight: 1.5, fillOpacity: 0.9
                }).bindPopup('<strong>' + Sawari.escape(loc.name) + '</strong><br>' + loc.type + ' · ' + loc.status);

                markersLayer.addLayer(marker);
                bounds.push([lat, lng]);
            });

            if (bounds.length) map.fitBounds(bounds, { padding: [30, 30], maxZoom: 14 });
        }

        /* ── Load Data ────────────────────────────────────── */
        function loadLocations() {
            var params = { page: currentPage };
            var status = document.getElementById('filter-status').value;
            var type = document.getElementById('filter-type').value;
            var q = document.getElementById('search-input').value.trim();
            if (status) params.status = status;
            if (type) params.type = type;
            if (q) params.q = q;

            Sawari.api('locations', 'list', params, 'GET').then(function (data) {
                if (!data.success) return;
                renderTable(data.locations);
                plotMarkers(data.locations);
                Sawari.pagination(
                    document.getElementById('locations-pagination'),
                    data.pagination.page,
                    data.pagination.total_pages,
                    function (p) { currentPage = p; loadLocations(); }
                );
            });
        }

        /* ── Render Table ─────────────────────────────────── */
        function renderTable(locations) {
            var tbody = document.getElementById('locations-tbody');
            if (!locations.length) {
                tbody.innerHTML = Sawari.emptyRow(6, 'No locations found.');
                feather.replace({ 'stroke-width': 1.75 });
                return;
            }

            tbody.innerHTML = locations.map(function (loc) {
                var statusBadge = '<span class="badge badge-' + loc.status + '">' + loc.status + '</span>';
                var coords = parseFloat(loc.latitude).toFixed(6) + ', ' + parseFloat(loc.longitude).toFixed(6);
                var agent = loc.agent_name || '—';

                var actions = '';
                if (loc.status === 'pending') {
                    actions =
                        '<button class="btn btn-sm btn-success" onclick="approveLocation(' + loc.location_id + ')" title="Approve"><i data-feather="check" style="width:14px;height:14px;"></i></button> ' +
                        '<button class="btn btn-sm btn-danger" onclick="rejectLocation(' + loc.location_id + ')" title="Reject"><i data-feather="x" style="width:14px;height:14px;"></i></button> ';
                }
                actions += '<button class="btn btn-sm btn-ghost" onclick="editLocation(' + loc.location_id + ')" title="Edit"><i data-feather="edit-2" style="width:14px;height:14px;"></i></button> ';
                actions += '<button class="btn btn-sm btn-ghost" onclick="deleteLocation(' + loc.location_id + ', \'' + Sawari.escape(loc.name).replace(/'/g, "\\'") + '\')" title="Delete"><i data-feather="trash-2" style="width:14px;height:14px;"></i></button>';

                return '<tr>' +
                    '<td><strong>' + Sawari.escape(loc.name) + '</strong>' + (loc.description ? '<br><small class="text-muted">' + Sawari.escape(loc.description) + '</small>' : '') + '</td>' +
                    '<td><span class="badge badge-neutral">' + loc.type + '</span></td>' +
                    '<td class="text-muted" style="font-size:var(--text-sm);">' + coords + '</td>' +
                    '<td>' + statusBadge + '</td>' +
                    '<td class="text-muted">' + Sawari.escape(agent) + '</td>' +
                    '<td>' + actions + '</td>' +
                    '</tr>';
            }).join('');

            feather.replace({ 'stroke-width': 1.75 });
        }

        /* ── CRUD Actions ─────────────────────────────────── */

        window.approveLocation = function (id) {
            Sawari.confirm('Approve this location?', function () {
                Sawari.api('locations', 'approve', { id: id }).then(function (data) {
                    if (data.success) { Sawari.toast('Location approved.', 'success'); loadLocations(); }
                });
            }, 'Approve', 'btn-success');
        };

        window.rejectLocation = function (id) {
            Sawari.rejectPrompt(function (reason) {
                Sawari.api('locations', 'reject', { id: id, reason: reason }).then(function (data) {
                    if (data.success) { Sawari.toast('Location rejected.', 'success'); loadLocations(); }
                });
            });
        };

        window.deleteLocation = function (id, name) {
            Sawari.confirm('Delete location "' + name + '"? This cannot be undone.', function () {
                Sawari.api('locations', 'delete', { id: id }).then(function (data) {
                    if (data.success) { Sawari.toast('Location deleted.', 'success'); loadLocations(); }
                });
            }, 'Delete', 'btn-danger');
        };

        window.editLocation = function (id) {
            Sawari.api('locations', 'get', { id: id }, 'GET').then(function (data) {
                if (!data.success) return;
                var loc = data.location;
                openFormModal('Edit Location', loc);
            });
        };

        window.openCreateModal = function () {
            openFormModal('Add Location', null);
        };

        /* ── Form Modal ───────────────────────────────────── */
        function openFormModal(title, loc) {
            var isEdit = !!loc;
            var body =
                '<form id="location-form" class="form-stack">' +
                (isEdit ? '<input type="hidden" name="location_id" value="' + loc.location_id + '">' : '') +
                '<div class="form-row">' +
                '<div class="form-group" style="flex:1;">' +
                '<label class="form-label">Name *</label>' +
                '<input type="text" name="name" class="form-input" value="' + (isEdit ? Sawari.escape(loc.name) : '') + '" required>' +
                '</div>' +
                '<div class="form-group" style="width:140px;">' +
                '<label class="form-label">Type</label>' +
                '<select name="type" class="form-select">' +
                '<option value="stop"' + (isEdit && loc.type === 'stop' ? ' selected' : '') + '>Stop</option>' +
                '<option value="landmark"' + (isEdit && loc.type === 'landmark' ? ' selected' : '') + '>Landmark</option>' +
                '</select>' +
                '</div>' +
                '</div>' +
                '<div class="form-row">' +
                '<div class="form-group" style="flex:1;">' +
                '<label class="form-label">Latitude *</label>' +
                '<input type="text" name="latitude" class="form-input" value="' + (isEdit ? loc.latitude : '') + '" required>' +
                '</div>' +
                '<div class="form-group" style="flex:1;">' +
                '<label class="form-label">Longitude *</label>' +
                '<input type="text" name="longitude" class="form-input" value="' + (isEdit ? loc.longitude : '') + '" required>' +
                '</div>' +
                '</div>' +
                '<div class="form-group">' +
                '<label class="form-label">Description</label>' +
                '<textarea name="description" class="form-input" rows="2">' + (isEdit && loc.description ? Sawari.escape(loc.description) : '') + '</textarea>' +
                '</div>' +
                '<div id="form-map" style="height:200px;border-radius:var(--radius-md);margin-top:var(--space-2);"></div>' +
                '<p class="text-muted" style="font-size:var(--text-xs);margin-top:var(--space-1);">Click on the map to set coordinates</p>' +
                '</form>';

            var footer =
                '<button class="btn btn-secondary" onclick="Sawari.modal()">Cancel</button>' +
                '<button class="btn btn-primary" id="location-save-btn">' + (isEdit ? 'Update' : 'Create') + '</button>';

            Sawari.modal(title, body, footer);

            // Init mini-map inside modal
            setTimeout(function () {
                var lat = isEdit ? parseFloat(loc.latitude) : 27.7172;
                var lng = isEdit ? parseFloat(loc.longitude) : 85.3240;

                var formMap = L.map('form-map').setView([lat, lng], 13);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; OSM',
                    maxZoom: 19
                }).addTo(formMap);

                var marker = L.marker([lat, lng], { draggable: true }).addTo(formMap);

                marker.on('dragend', function () {
                    var pos = marker.getLatLng();
                    document.querySelector('#location-form [name="latitude"]').value = pos.lat.toFixed(8);
                    document.querySelector('#location-form [name="longitude"]').value = pos.lng.toFixed(8);
                });

                formMap.on('click', function (e) {
                    marker.setLatLng(e.latlng);
                    document.querySelector('#location-form [name="latitude"]').value = e.latlng.lat.toFixed(8);
                    document.querySelector('#location-form [name="longitude"]').value = e.latlng.lng.toFixed(8);
                });
            }, 200);

            // Save handler
            document.getElementById('location-save-btn').addEventListener('click', function () {
                var form = document.getElementById('location-form');
                var fd = new FormData(form);
                var action = isEdit ? 'update' : 'create';

                if (!fd.get('name') || !fd.get('latitude') || !fd.get('longitude')) {
                    Sawari.toast('Please fill in all required fields.', 'warning');
                    return;
                }

                var btn = this;
                Sawari.setLoading(btn, true);

                Sawari.api('locations', action, fd).then(function (data) {
                    Sawari.setLoading(btn, false);
                    if (data.success) {
                        Sawari.modal();
                        Sawari.toast(data.message, 'success');
                        loadLocations();
                    }
                }).catch(function () {
                    Sawari.setLoading(btn, false);
                });
            });
        }
    })();
</script>

<?php require_once __DIR__ . '/../../includes/admin-footer.php'; ?>