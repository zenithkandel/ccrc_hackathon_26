<?php
/**
 * SAWARI — Manage Vehicles (Admin)
 *
 * Filterable table, image preview, approve/reject, create/edit modal with image upload.
 */

require_once __DIR__ . '/../../includes/auth-admin.php';

$pageTitle = 'Manage Vehicles';
$currentPage = 'vehicles';
$pageActions = '<button class="btn btn-primary btn-sm" onclick="openCreateVehicle()"><i data-feather="plus" style="width:14px;height:14px;"></i> Add Vehicle</button>';
require_once __DIR__ . '/../../includes/admin-header.php';
?>

<!-- Filters -->
<div class="filters-bar">
    <div class="search-bar" style="flex:1;max-width:320px;">
        <i data-feather="search" class="search-icon"></i>
        <input type="text" class="form-input" id="search-input" placeholder="Search vehicles...">
    </div>
    <select class="form-select" id="filter-status" style="width:auto;">
        <option value="">All Statuses</option>
        <option value="pending">Pending</option>
        <option value="approved">Approved</option>
        <option value="rejected">Rejected</option>
    </select>
    <select class="form-select" id="filter-electric" style="width:auto;">
        <option value="">All Types</option>
        <option value="1">Electric</option>
        <option value="0">Non-Electric</option>
    </select>
</div>

<!-- Table -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-container" style="border:none;border-radius:0;">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:50px;"></th>
                        <th>Name</th>
                        <th>Operating Hours</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Submitted by</th>
                        <th style="width:140px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="vehicles-tbody">
                    <tr>
                        <td colspan="7" class="text-center text-muted p-6">Loading...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
<div id="vehicles-pagination" style="margin-top:var(--space-4);"></div>

<script>
    (function () {
        var currentPage = 1;
        var debounceTimer;
        var BASE = Sawari.baseUrl;

        document.addEventListener('DOMContentLoaded', function () {
            loadVehicles();
            document.getElementById('search-input').addEventListener('input', function () {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(function () { currentPage = 1; loadVehicles(); }, 350);
            });
            document.getElementById('filter-status').addEventListener('change', function () { currentPage = 1; loadVehicles(); });
            document.getElementById('filter-electric').addEventListener('change', function () { currentPage = 1; loadVehicles(); });
        });

        function loadVehicles() {
            var p = { page: currentPage };
            var s = document.getElementById('filter-status').value;
            var e = document.getElementById('filter-electric').value;
            var q = document.getElementById('search-input').value.trim();
            if (s) p.status = s;
            if (e !== '') p.electric = e;
            if (q) p.q = q;

            Sawari.api('vehicles', 'list', p, 'GET').then(function (data) {
                if (!data.success) return;
                renderTable(data.vehicles);
                Sawari.pagination(document.getElementById('vehicles-pagination'), data.pagination.page, data.pagination.total_pages, function (pg) { currentPage = pg; loadVehicles(); });
            });
        }

        function renderTable(vehicles) {
            var tbody = document.getElementById('vehicles-tbody');
            if (!vehicles.length) {
                tbody.innerHTML = Sawari.emptyRow(7, 'No vehicles found.');
                feather.replace({ 'stroke-width': 1.75 });
                return;
            }

            tbody.innerHTML = vehicles.map(function (v) {
                var imgSrc = v.image_path ? BASE + '/uploads/vehicles/' + v.image_path : '';
                var imgCell = imgSrc
                    ? '<img src="' + imgSrc + '" alt="" style="width:40px;height:40px;object-fit:cover;border-radius:var(--radius-md);">'
                    : '<div style="width:40px;height:40px;background:var(--color-neutral-100);border-radius:var(--radius-md);display:flex;align-items:center;justify-content:center;"><i data-feather="truck" style="width:16px;height:16px;color:var(--color-neutral-400);"></i></div>';

                var hours = (v.starts_at && v.stops_at) ? v.starts_at.substring(0, 5) + ' – ' + v.stops_at.substring(0, 5) : '—';
                var typeLabel = v.electric == 1 ? '<span class="badge badge-approved">Electric</span>' : '<span class="badge badge-neutral">Standard</span>';
                var statusBadge = '<span class="badge badge-' + v.status + '">' + v.status + '</span>';

                var actions = '';
                if (v.status === 'pending') {
                    actions += '<button class="btn btn-sm btn-success" onclick="approveVehicle(' + v.vehicle_id + ')" title="Approve"><i data-feather="check" style="width:14px;height:14px;"></i></button> ';
                    actions += '<button class="btn btn-sm btn-danger" onclick="rejectVehicle(' + v.vehicle_id + ')" title="Reject"><i data-feather="x" style="width:14px;height:14px;"></i></button> ';
                }
                actions += '<button class="btn btn-sm btn-ghost" onclick="editVehicle(' + v.vehicle_id + ')" title="Edit"><i data-feather="edit-2" style="width:14px;height:14px;"></i></button> ';
                actions += '<button class="btn btn-sm btn-ghost" onclick="deleteVehicle(' + v.vehicle_id + ', \'' + Sawari.escape(v.name).replace(/'/g, "\\'") + '\')" title="Delete"><i data-feather="trash-2" style="width:14px;height:14px;"></i></button>';

                return '<tr>' +
                    '<td>' + imgCell + '</td>' +
                    '<td><strong>' + Sawari.escape(v.name) + '</strong>' + (v.description ? '<br><small class="text-muted">' + Sawari.escape(v.description).substring(0, 60) + '</small>' : '') + '</td>' +
                    '<td class="text-muted">' + hours + '</td>' +
                    '<td>' + typeLabel + '</td>' +
                    '<td>' + statusBadge + '</td>' +
                    '<td class="text-muted">' + Sawari.escape(v.agent_name || '—') + '</td>' +
                    '<td>' + actions + '</td>' +
                    '</tr>';
            }).join('');

            feather.replace({ 'stroke-width': 1.75 });
        }

        /* ── CRUD ─────────────────────────────────────────── */
        window.approveVehicle = function (id) {
            Sawari.confirm('Approve this vehicle?', function () {
                Sawari.api('vehicles', 'approve', { id: id }).then(function (d) {
                    if (d.success) { Sawari.toast('Vehicle approved.', 'success'); loadVehicles(); }
                });
            }, 'Approve', 'btn-success');
        };

        window.rejectVehicle = function (id) {
            Sawari.rejectPrompt(function (reason) {
                Sawari.api('vehicles', 'reject', { id: id, reason: reason }).then(function (d) {
                    if (d.success) { Sawari.toast('Vehicle rejected.', 'success'); loadVehicles(); }
                });
            });
        };

        window.deleteVehicle = function (id, name) {
            Sawari.confirm('Delete vehicle "' + name + '"?', function () {
                Sawari.api('vehicles', 'delete', { id: id }).then(function (d) {
                    if (d.success) { Sawari.toast('Vehicle deleted.', 'success'); loadVehicles(); }
                });
            }, 'Delete', 'btn-danger');
        };

        window.editVehicle = function (id) {
            Sawari.api('vehicles', 'get', { id: id }, 'GET').then(function (d) {
                if (d.success) openVehicleForm('Edit Vehicle', d.vehicle);
            });
        };

        window.openCreateVehicle = function () {
            openVehicleForm('Add Vehicle', null);
        };

        /* ── Form Modal ───────────────────────────────────── */
        function openVehicleForm(title, v) {
            var isEdit = !!v;
            var routes = isEdit && v.used_routes_parsed ? v.used_routes_parsed.join(',') : '';

            var body =
                '<form id="vehicle-form" class="form-stack" enctype="multipart/form-data">' +
                (isEdit ? '<input type="hidden" name="vehicle_id" value="' + v.vehicle_id + '">' : '') +
                '<div class="form-group">' +
                '<label class="form-label">Name *</label>' +
                '<input type="text" name="name" class="form-input" value="' + (isEdit ? Sawari.escape(v.name) : '') + '" required>' +
                '</div>' +
                '<div class="form-group">' +
                '<label class="form-label">Description</label>' +
                '<textarea name="description" class="form-input" rows="2">' + (isEdit && v.description ? Sawari.escape(v.description) : '') + '</textarea>' +
                '</div>' +
                '<div class="form-row">' +
                '<div class="form-group" style="flex:1;">' +
                '<label class="form-label">Starts At</label>' +
                '<input type="time" name="starts_at" class="form-input" value="' + (isEdit && v.starts_at ? v.starts_at.substring(0, 5) : '') + '">' +
                '</div>' +
                '<div class="form-group" style="flex:1;">' +
                '<label class="form-label">Stops At</label>' +
                '<input type="time" name="stops_at" class="form-input" value="' + (isEdit && v.stops_at ? v.stops_at.substring(0, 5) : '') + '">' +
                '</div>' +
                '</div>' +
                '<div class="form-row">' +
                '<div class="form-group" style="flex:1;">' +
                '<label class="form-label">Route IDs (comma-separated)</label>' +
                '<input type="text" name="used_routes" class="form-input" value="' + routes + '" placeholder="e.g. 1,5,12">' +
                '</div>' +
                '<div class="form-group">' +
                '<label class="form-label">&nbsp;</label>' +
                '<label class="form-checkbox"><input type="checkbox" name="electric" value="1"' + (isEdit && v.electric == 1 ? ' checked' : '') + '> Electric vehicle</label>' +
                '</div>' +
                '</div>' +
                '<div class="form-group">' +
                '<label class="form-label">Vehicle Image</label>' +
                '<input type="file" name="image" class="form-input" accept="image/jpeg,image/png,image/webp">' +
                (isEdit && v.image_path ? '<small class="text-muted">Current: ' + Sawari.escape(v.image_path) + '</small>' : '') +
                '</div>' +
                '</form>';

            var footer =
                '<button class="btn btn-secondary" onclick="Sawari.modal()">Cancel</button>' +
                '<button class="btn btn-primary" id="vehicle-save-btn">' + (isEdit ? 'Update' : 'Create') + '</button>';

            Sawari.modal(title, body, footer);

            document.getElementById('vehicle-save-btn').addEventListener('click', function () {
                var form = document.getElementById('vehicle-form');
                var fd = new FormData(form);
                if (!fd.get('name')) { Sawari.toast('Name is required.', 'warning'); return; }

                // Checkbox won't be in FormData if unchecked
                if (!form.querySelector('[name="electric"]').checked) {
                    fd.set('electric', '0');
                }

                var btn = this;
                Sawari.setLoading(btn, true);
                Sawari.api('vehicles', isEdit ? 'update' : 'create', fd).then(function (d) {
                    Sawari.setLoading(btn, false);
                    if (d.success) { Sawari.modal(); Sawari.toast(d.message, 'success'); loadVehicles(); }
                }).catch(function () { Sawari.setLoading(btn, false); });
            });
        }

    })();
</script>

<?php require_once __DIR__ . '/../../includes/admin-footer.php'; ?>