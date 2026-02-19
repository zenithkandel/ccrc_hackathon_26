<?php
/**
 * Admin: Locations Management — Sawari
 * 
 * Lists all locations with filters. Add/Edit via modal, Approve/Reject/Delete actions.
 * Uses Leaflet map for pin-drop in modal.
 */

$pageTitle = 'Locations — Admin — Sawari';
$pageCss = ['admin.css'];
$bodyClass = 'admin-page';
$pageJs = ['admin.js'];
$useLeaflet = true;
$currentPage = 'locations';

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/validation.php';

requireAuth('admin');

$pdo = getDBConnection();

// ─── Filters ─────────────────────────────────────────────
$statusFilter = getParam('status', '');
$typeFilter = getParam('type', '');
$search = getParam('search', '');
$page = getIntParam('page', 1);

$where = [];
$params = [];

if ($statusFilter && in_array($statusFilter, ['pending', 'approved', 'rejected'])) {
    $where[] = 'l.status = :status';
    $params['status'] = $statusFilter;
}

if ($typeFilter && in_array($typeFilter, ['stop', 'landmark'])) {
    $where[] = 'l.type = :type';
    $params['type'] = $typeFilter;
}

if ($search) {
    $where[] = 'l.name LIKE :search';
    $params['search'] = '%' . $search . '%';
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Count total
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM locations l $whereClause");
$countStmt->execute($params);
$totalItems = (int) $countStmt->fetchColumn();

$pagination = paginate($totalItems, $page);

// Fetch locations
$stmt = $pdo->prepare("
    SELECT l.*, a.name AS updater_name, adm.name AS approver_name
    FROM locations l
    LEFT JOIN agents a ON l.updated_by = a.agent_id
    LEFT JOIN admins adm ON l.approved_by = adm.admin_id
    $whereClause
    ORDER BY l.updated_at DESC
    LIMIT :limit OFFSET :offset
");

foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->bindValue('limit', $pagination['perPage'], PDO::PARAM_INT);
$stmt->bindValue('offset', $pagination['offset'], PDO::PARAM_INT);
$stmt->execute();
$locations = $stmt->fetchAll();

$csrfToken = generateCSRFToken();

// Build base URL for pagination
$filterParams = http_build_query(array_filter([
    'status' => $statusFilter,
    'type' => $typeFilter,
    'search' => $search,
]));
$baseUrl = BASE_URL . '/pages/admin/locations.php' . ($filterParams ? '?' . $filterParams : '');
?>

<div class="admin-layout">
    <?php require_once __DIR__ . '/../../includes/admin-sidebar.php'; ?>

    <div class="admin-content">
        <div class="admin-page-header">
            <h1>Locations Management</h1>
            <div class="header-actions">
                <button class="btn btn-primary" onclick="openAddModal()">+ Add Location</button>
            </div>
        </div>

        <!-- Filter Bar -->
        <form class="filter-bar" method="GET">
            <select name="status" class="filter-select">
                <option value="">All Statuses</option>
                <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="approved" <?= $statusFilter === 'approved' ? 'selected' : '' ?>>Approved</option>
                <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
            </select>
            <select name="type" class="filter-select">
                <option value="">All Types</option>
                <option value="stop" <?= $typeFilter === 'stop' ? 'selected' : '' ?>>Stop</option>
                <option value="landmark" <?= $typeFilter === 'landmark' ? 'selected' : '' ?>>Landmark</option>
            </select>
            <input type="text" name="search" class="filter-input" placeholder="Search by name..."
                value="<?= sanitize($search) ?>">
            <button type="submit" class="filter-btn">Filter</button>
            <a href="<?= BASE_URL ?>/pages/admin/locations.php" class="filter-btn secondary">Reset</a>
        </form>

        <!-- Data Table -->
        <div class="data-table-wrapper">
            <?php if (empty($locations)): ?>
                <div class="empty-state">
                    <div class="empty-icon"><i class="fa-duotone fa-solid fa-location-dot"></i></div>
                    <h3>No locations found</h3>
                    <p>Try adjusting your filters or add a new location.</p>
                </div>
            <?php else: ?>
                <div class="table-scroll">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Coordinates</th>
                                <th>Updated By</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($locations as $loc): ?>
                                <tr>
                                    <td><strong><?= sanitize($loc['name']) ?></strong></td>
                                    <td><span class="badge badge-<?= $loc['type'] ?>"><?= sanitize($loc['type']) ?></span></td>
                                    <td>
                                        <span
                                            class="badge badge-<?= $loc['status'] === 'approved' ? 'approved' : ($loc['status'] === 'rejected' ? 'rejected' : 'pending') ?>">
                                            <?= sanitize($loc['status']) ?>
                                        </span>
                                    </td>
                                    <td style="font-size:0.8125rem; color:var(--color-text-light)">
                                        <?= number_format($loc['latitude'], 5) ?>, <?= number_format($loc['longitude'], 5) ?>
                                    </td>
                                    <td><?= sanitize($loc['updater_name'] ?? '—') ?></td>
                                    <td>
                                        <div class="actions">
                                            <button class="btn btn-sm btn-outline"
                                                onclick='openEditModal(<?= json_encode($loc) ?>)'>Edit</button>
                                            <?php if ($loc['status'] === 'pending'): ?>
                                                <button class="btn btn-sm btn-success"
                                                    onclick="approveLocation(<?= $loc['location_id'] ?>)">Approve</button>
                                                <button class="btn btn-sm btn-warning"
                                                    onclick="rejectLocation(<?= $loc['location_id'] ?>)">Reject</button>
                                            <?php endif; ?>
                                            <button class="btn btn-sm btn-danger"
                                                onclick="deleteLocation(<?= $loc['location_id'] ?>, '<?= sanitize($loc['name']) ?>')">Delete</button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
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

<!-- Add/Edit Location Modal -->
<div class="modal-overlay" id="locationModal">
    <div class="modal">
        <div class="modal-header">
            <h2 id="modalTitle">Add Location</h2>
            <button class="modal-close"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body">
            <form id="locationForm">
                <input type="hidden" name="location_id" id="locationId" value="">

                <div class="form-group">
                    <label for="locName">Name *</label>
                    <input type="text" id="locName" name="name" class="form-input" required
                        placeholder="e.g., Ratna Park Bus Stop">
                </div>

                <div class="form-group">
                    <label for="locDescription">Description</label>
                    <textarea id="locDescription" name="description" class="form-textarea"
                        placeholder="Brief description of this location..."></textarea>
                </div>

                <div class="form-group">
                    <label for="locType">Type *</label>
                    <select id="locType" name="type" class="form-select" required>
                        <option value="stop">Bus Stop</option>
                        <option value="landmark">Landmark</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Location (click on map or enter coordinates)</label>
                    <div id="locationMap" class="map-container"></div>
                </div>

                <div class="form-group">
                    <div class="coord-display">
                        <input type="number" id="locLat" name="latitude" class="form-input" step="any"
                            placeholder="Latitude" required>
                        <input type="number" id="locLng" name="longitude" class="form-input" step="any"
                            placeholder="Longitude" required>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="AdminUtils.closeFormModal('#locationModal')">Cancel</button>
            <button class="btn btn-primary" id="saveLocationBtn" onclick="saveLocation()">Save Location</button>
        </div>
    </div>
</div>

<!-- Reject Reason Modal -->
<div class="modal-overlay" id="rejectModal">
    <div class="modal" style="max-width: 450px;">
        <div class="modal-header">
            <h2>Reject Location</h2>
            <button class="modal-close"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label for="rejectReason">Reason for rejection *</label>
                <textarea id="rejectReason" class="form-textarea"
                    placeholder="Explain why this entry is being rejected..." required></textarea>
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
    let map, marker;
    let rejectingId = null;

    document.addEventListener('DOMContentLoaded', () => {
        // Initialize Leaflet map in modal
        setTimeout(initMap, 100);
    });

    function initMap() {
        map = L.map('locationMap').setView([<?= DEFAULT_LAT ?>, <?= DEFAULT_LNG ?>], <?= DEFAULT_ZOOM ?>);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        map.on('click', (e) => {
            setMarker(e.latlng.lat, e.latlng.lng);
        });
    }

    function setMarker(lat, lng) {
        if (marker) map.removeLayer(marker);
        marker = L.marker([lat, lng], { draggable: true }).addTo(map);
        document.getElementById('locLat').value = lat.toFixed(6);
        document.getElementById('locLng').value = lng.toFixed(6);

        marker.on('dragend', () => {
            const pos = marker.getLatLng();
            document.getElementById('locLat').value = pos.lat.toFixed(6);
            document.getElementById('locLng').value = pos.lng.toFixed(6);
        });
    }

    function openAddModal() {
        document.getElementById('modalTitle').textContent = 'Add Location';
        document.getElementById('locationForm').reset();
        document.getElementById('locationId').value = '';
        if (marker) { map.removeLayer(marker); marker = null; }
        AdminUtils.openFormModal('#locationModal');
        setTimeout(() => map.invalidateSize(), 200);
    }

    function openEditModal(loc) {
        document.getElementById('modalTitle').textContent = 'Edit Location';
        document.getElementById('locationId').value = loc.location_id;
        document.getElementById('locName').value = loc.name;
        document.getElementById('locDescription').value = loc.description || '';
        document.getElementById('locType').value = loc.type;
        document.getElementById('locLat').value = loc.latitude;
        document.getElementById('locLng').value = loc.longitude;

        AdminUtils.openFormModal('#locationModal');
        setTimeout(() => {
            map.invalidateSize();
            setMarker(parseFloat(loc.latitude), parseFloat(loc.longitude));
            map.setView([loc.latitude, loc.longitude], 15);
        }, 200);
    }

    async function saveLocation() {
        const id = document.getElementById('locationId').value;
        const formData = new URLSearchParams({
            name: document.getElementById('locName').value,
            description: document.getElementById('locDescription').value,
            type: document.getElementById('locType').value,
            latitude: document.getElementById('locLat').value,
            longitude: document.getElementById('locLng').value,
            status: 'approved'
        });

        const url = id
            ? `${BASE}/api/locations/update.php?id=${id}`
            : `${BASE}/api/locations/create.php`;

        await AdminUtils.apiAction(url, Object.fromEntries(formData), {
            onSuccess: () => location.reload()
        });
    }

    function approveLocation(id) {
        AdminUtils.apiAction(`${BASE}/api/locations/update.php?id=${id}`, { status: 'approved' }, {
            onSuccess: () => location.reload()
        });
    }

    function rejectLocation(id) {
        rejectingId = id;
        document.getElementById('rejectReason').value = '';
        AdminUtils.openFormModal('#rejectModal');
    }

    function submitReject() {
        const reason = document.getElementById('rejectReason').value.trim();
        if (!reason) {
            SawariUtils.showToast('Please provide a rejection reason', 'warning');
            return;
        }

        // Find the contribution for this location and reject it
        AdminUtils.apiAction(`${BASE}/api/locations/update.php?id=${rejectingId}`, { status: 'rejected' }, {
            onSuccess: () => {
                AdminUtils.closeFormModal('#rejectModal');
                location.reload();
            }
        });
    }

    function deleteLocation(id, name) {
        AdminUtils.confirmDelete(`${BASE}/api/locations/delete.php?id=${id}`, name);
    }
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>