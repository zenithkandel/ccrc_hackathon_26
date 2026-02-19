<?php
/**
 * Admin: Routes Management — Sawari
 * 
 * List, add/edit, approve/reject/delete routes.
 * Route creation includes location selector for stop ordering and Leaflet map preview.
 */

$pageTitle = 'Routes — Admin — Sawari';
$pageCss = ['admin.css'];
$bodyClass = 'admin-page';
$pageJs = ['admin.js'];
$useLeaflet = true;
$currentPage = 'routes';

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/validation.php';

requireAuth('admin');

$pdo = getDBConnection();

// ─── Filters ─────────────────────────────────────────────
$statusFilter = getParam('status', '');
$search = getParam('search', '');
$page = getIntParam('page', 1);

$where = [];
$params = [];

if ($statusFilter && in_array($statusFilter, ['pending', 'approved', 'rejected'])) {
    $where[] = 'r.status = :status';
    $params['status'] = $statusFilter;
}

if ($search) {
    $where[] = 'r.name LIKE :search';
    $params['search'] = '%' . $search . '%';
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM routes r $whereClause");
$countStmt->execute($params);
$totalItems = (int) $countStmt->fetchColumn();
$pagination = paginate($totalItems, $page);

$stmt = $pdo->prepare("
    SELECT r.*, a.name AS updater_name, adm.name AS approver_name
    FROM routes r
    LEFT JOIN agents a ON r.updated_by = a.agent_id
    LEFT JOIN admins adm ON r.approved_by = adm.admin_id
    $whereClause
    ORDER BY r.updated_at DESC
    LIMIT :limit OFFSET :offset
");
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->bindValue('limit', $pagination['perPage'], PDO::PARAM_INT);
$stmt->bindValue('offset', $pagination['offset'], PDO::PARAM_INT);
$stmt->execute();
$routes = $stmt->fetchAll();

// Fetch approved locations for selector
$approvedLocations = $pdo->query("
    SELECT location_id, name, latitude, longitude, type
    FROM locations WHERE status = 'approved'
    ORDER BY name ASC
")->fetchAll();

$filterParams = http_build_query(array_filter(['status' => $statusFilter, 'search' => $search]));
$baseUrl = BASE_URL . '/pages/admin/routes.php' . ($filterParams ? '?' . $filterParams : '');
$csrfToken = generateCSRFToken();
?>

<div class="admin-layout">
    <?php require_once __DIR__ . '/../../includes/admin-sidebar.php'; ?>

    <div class="admin-content">
        <div class="admin-page-header">
            <h1>Routes Management</h1>
            <div class="header-actions">
                <button class="btn btn-primary" onclick="openAddModal()">+ Add Route</button>
            </div>
        </div>

        <form class="filter-bar" method="GET">
            <select name="status" class="filter-select">
                <option value="">All Statuses</option>
                <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="approved" <?= $statusFilter === 'approved' ? 'selected' : '' ?>>Approved</option>
                <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
            </select>
            <input type="text" name="search" class="filter-input" placeholder="Search by name..."
                value="<?= sanitize($search) ?>">
            <button type="submit" class="filter-btn">Filter</button>
            <a href="<?= BASE_URL ?>/pages/admin/routes.php" class="filter-btn secondary">Reset</a>
        </form>

        <div class="data-table-wrapper">
            <?php if (empty($routes)): ?>
                <div class="empty-state">
                    <div class="empty-icon"><i class="fa-duotone fa-solid fa-route"></i></div>
                    <h3>No routes found</h3>
                    <p>Try adjusting filters or add a new route.</p>
                </div>
            <?php else: ?>
                <div class="table-scroll">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Status</th>
                                <th># Stops</th>
                                <th>Updated By</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($routes as $route): ?>
                                <?php
                                $locList = json_decode($route['location_list'] ?? '[]', true);
                                $stopCount = is_array($locList) ? count($locList) : 0;
                                ?>
                                <tr>
                                    <td><strong><?= sanitize($route['name']) ?></strong></td>
                                    <td>
                                        <span
                                            class="badge badge-<?= $route['status'] === 'approved' ? 'approved' : ($route['status'] === 'rejected' ? 'rejected' : 'pending') ?>">
                                            <?= sanitize($route['status']) ?>
                                        </span>
                                    </td>
                                    <td><?= $stopCount ?></td>
                                    <td><?= sanitize($route['updater_name'] ?? '—') ?></td>
                                    <td>
                                        <div class="actions">
                                            <button class="btn btn-sm btn-outline"
                                                onclick='openEditModal(<?= json_encode($route) ?>)'>Edit</button>
                                            <?php if ($route['status'] === 'pending'): ?>
                                                <button class="btn btn-sm btn-success"
                                                    onclick="approveRoute(<?= $route['route_id'] ?>)">Approve</button>
                                                <button class="btn btn-sm btn-warning"
                                                    onclick="rejectRoute(<?= $route['route_id'] ?>)">Reject</button>
                                            <?php endif; ?>
                                            <button class="btn btn-sm btn-danger"
                                                onclick="deleteRoute(<?= $route['route_id'] ?>, '<?= sanitize($route['name']) ?>')">Delete</button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($pagination['totalPages'] > 1): ?>
            <div class="pagination">
                <?php if ($pagination['hasPrev']): ?>
                    <a
                        href="<?= $baseUrl . (str_contains($baseUrl, '?') ? '&' : '?') ?>page=<?= $pagination['currentPage'] - 1 ?>">&laquo;
                        Prev</a>
                <?php else: ?>
                    <span class="disabled">&laquo; Prev</span>
                <?php endif; ?>
                <?php for ($i = max(1, $pagination['currentPage'] - 2); $i <= min($pagination['totalPages'], $pagination['currentPage'] + 2); $i++): ?>
                    <?php if ($i === $pagination['currentPage']): ?>
                        <span class="active"><?= $i ?></span>
                    <?php else: ?>
                        <a href="<?= $baseUrl . (str_contains($baseUrl, '?') ? '&' : '?') ?>page=<?= $i ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                <?php if ($pagination['hasNext']): ?>
                    <a
                        href="<?= $baseUrl . (str_contains($baseUrl, '?') ? '&' : '?') ?>page=<?= $pagination['currentPage'] + 1 ?>">Next
                        &raquo;</a>
                <?php else: ?>
                    <span class="disabled">Next &raquo;</span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add/Edit Route Modal -->
<div class="modal-overlay" id="routeModal">
    <div class="modal" style="max-width: 700px;">
        <div class="modal-header">
            <h2 id="routeModalTitle">Add Route</h2>
            <button class="modal-close"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body">
            <form id="routeForm" enctype="multipart/form-data">
                <input type="hidden" name="route_id" id="routeId" value="">

                <div class="form-group">
                    <label for="routeName">Route Name *</label>
                    <input type="text" id="routeName" name="name" class="form-input" required
                        placeholder="e.g., Ratna Park - Kalanki">
                </div>

                <div class="form-group">
                    <label for="routeDescription">Description</label>
                    <textarea id="routeDescription" name="description" class="form-textarea"
                        placeholder="Route description..."></textarea>
                </div>

                <div class="form-group">
                    <label for="routeImage">Route Image</label>
                    <input type="file" id="routeImage" name="image" class="form-input"
                        accept="image/jpeg,image/png,image/webp">
                </div>

                <div class="form-group">
                    <label>Stops (select and order locations)</label>
                    <select id="locationSelector" class="form-select">
                        <option value="">— Select a location to add —</option>
                        <?php foreach ($approvedLocations as $loc): ?>
                            <option value="<?= $loc['location_id'] ?>" data-lat="<?= $loc['latitude'] ?>"
                                data-lng="<?= $loc['longitude'] ?>">
                                <?= sanitize($loc['name']) ?> (<?= $loc['type'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-group" style="margin-top:var(--space-sm);">
                        <label class="form-label"
                            style="font-size:0.8125rem; color:var(--color-text-muted); font-weight:400;">Drag to reorder
                            stops</label>
                        <ul class="stop-list" id="selectedStops">
                            <li class="empty-placeholder"
                                style="text-align:center; padding:var(--space-md); color:var(--color-text-muted); font-size:0.875rem;">
                                No stops added yet. Select locations above.</li>
                        </ul>
                    </div>
                    <input type="hidden" id="locationListInput" name="location_list" value="[]">
                </div>

                <div class="form-group">
                    <label>Route Preview</label>
                    <div id="routeMap" class="map-container"></div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="AdminUtils.closeFormModal('#routeModal')">Cancel</button>
            <button class="btn btn-primary" onclick="saveRoute()">Save Route</button>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal-overlay" id="rejectModal">
    <div class="modal" style="max-width: 450px;">
        <div class="modal-header">
            <h2>Reject Route</h2>
            <button class="modal-close"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label for="rejectReason">Reason for rejection *</label>
                <textarea id="rejectReason" class="form-textarea" required></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="AdminUtils.closeFormModal('#rejectModal')">Cancel</button>
            <button class="btn btn-danger" onclick="submitReject()">Reject</button>
        </div>
    </div>
</div>

<script>
    const BASE = '<?= BASE_URL ?>';
    const allLocations = <?= json_encode($approvedLocations) ?>;
    let routeMap, routePolyline;
    let selectedStops = [];
    let rejectingId = null;

    document.addEventListener('DOMContentLoaded', () => {
        setTimeout(initRouteMap, 100);

        // Add stop when selected
        document.getElementById('locationSelector').addEventListener('change', function () {
            const id = parseInt(this.value);
            if (!id) return;
            if (selectedStops.find(s => s.location_id === id)) {
                SawariUtils.showToast('Location already added', 'warning');
                this.value = '';
                return;
            }
            const loc = allLocations.find(l => l.location_id === id);
            if (loc) {
                selectedStops.push(loc);
                renderStops();
                updateMapPolyline();
            }
            this.value = '';
        });
    });

    function initRouteMap() {
        routeMap = L.map('routeMap').setView([<?= DEFAULT_LAT ?>, <?= DEFAULT_LNG ?>], <?= DEFAULT_ZOOM ?>);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(routeMap);
    }

    function renderStops() {
        const container = document.getElementById('selectedStops');
        if (selectedStops.length === 0) {
            container.innerHTML = '<li class="empty-placeholder" style="text-align:center; padding:var(--space-md); color:var(--color-text-muted); font-size:0.875rem;">No stops added yet. Select locations above.</li>';
        } else {
            container.innerHTML = selectedStops.map((s, i) => `
            <li class="stop-item" draggable="true" data-location-id="${s.location_id}">
                <span class="stop-index">${i + 1}</span>
                <span class="stop-name">${SawariUtils.escapeHTML(s.name)}</span>
                <button type="button" class="stop-remove" onclick="removeStop(${i})" title="Remove"><i class="fa-solid fa-xmark"></i></button>
            </li>
        `).join('');

            // Init drag-and-drop for reordering
            AdminUtils.initDragSort(container, (newOrder) => {
                selectedStops = newOrder.map(id => allLocations.find(l => l.location_id === id)).filter(Boolean);
                updateHiddenInput();
                updateMapPolyline();
            });
        }
        updateHiddenInput();
    }

    function updateHiddenInput() {
        document.getElementById('locationListInput').value = JSON.stringify(
            selectedStops.map((s, i) => ({ index: i + 1, location_id: s.location_id }))
        );
    }

    function removeStop(index) {
        selectedStops.splice(index, 1);
        renderStops();
        updateMapPolyline();
    }

    function updateMapPolyline() {
        if (routePolyline) routeMap.removeLayer(routePolyline);
        routeMap.eachLayer(layer => { if (layer instanceof L.Marker) routeMap.removeLayer(layer); });

        if (selectedStops.length === 0) return;

        const latlngs = selectedStops.map(s => [parseFloat(s.latitude), parseFloat(s.longitude)]);

        // Add markers
        selectedStops.forEach((s, i) => {
            L.marker(latlngs[i]).addTo(routeMap).bindPopup(`${i + 1}. ${s.name}`);
        });

        if (latlngs.length > 1) {
            routePolyline = L.polyline(latlngs, { color: '#2563eb', weight: 3 }).addTo(routeMap);
            routeMap.fitBounds(routePolyline.getBounds().pad(0.1));
        } else {
            routeMap.setView(latlngs[0], 15);
        }
    }

    function openAddModal() {
        document.getElementById('routeModalTitle').textContent = 'Add Route';
        document.getElementById('routeForm').reset();
        document.getElementById('routeId').value = '';
        selectedStops = [];
        renderStops();
        if (routePolyline) { routeMap.removeLayer(routePolyline); }
        routeMap.eachLayer(layer => { if (layer instanceof L.Marker) routeMap.removeLayer(layer); });
        AdminUtils.openFormModal('#routeModal');
        setTimeout(() => routeMap.invalidateSize(), 200);
    }

    function openEditModal(route) {
        document.getElementById('routeModalTitle').textContent = 'Edit Route';
        document.getElementById('routeId').value = route.route_id;
        document.getElementById('routeName').value = route.name;
        document.getElementById('routeDescription').value = route.description || '';

        const locList = JSON.parse(route.location_list || '[]');
        selectedStops = locList.map(id => allLocations.find(l => l.location_id === id)).filter(Boolean);
        renderStops();

        AdminUtils.openFormModal('#routeModal');
        setTimeout(() => {
            routeMap.invalidateSize();
            updateMapPolyline();
        }, 200);
    }

    async function saveRoute() {
        const id = document.getElementById('routeId').value;
        const formData = new FormData(document.getElementById('routeForm'));

        // Remove file if empty
        if (!formData.get('image')?.size) formData.delete('image');

        formData.set('location_list', document.getElementById('locationListInput').value);
        formData.set('status', 'approved');

        const url = id
            ? `${BASE}/api/routes/update.php?id=${id}`
            : `${BASE}/api/routes/create.php`;

        await AdminUtils.apiAction(url, formData, {
            onSuccess: () => location.reload()
        });
    }

    function approveRoute(id) {
        AdminUtils.apiAction(`${BASE}/api/routes/update.php?id=${id}`, { status: 'approved' }, {
            onSuccess: () => location.reload()
        });
    }

    function rejectRoute(id) {
        rejectingId = id;
        document.getElementById('rejectReason').value = '';
        AdminUtils.openFormModal('#rejectModal');
    }

    function submitReject() {
        const reason = document.getElementById('rejectReason').value.trim();
        if (!reason) { SawariUtils.showToast('Please provide a reason', 'warning'); return; }
        AdminUtils.apiAction(`${BASE}/api/routes/update.php?id=${rejectingId}`, { status: 'rejected' }, {
            onSuccess: () => { AdminUtils.closeFormModal('#rejectModal'); location.reload(); }
        });
    }

    function deleteRoute(id, name) {
        AdminUtils.confirmDelete(`${BASE}/api/routes/delete.php?id=${id}`, name);
    }
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>