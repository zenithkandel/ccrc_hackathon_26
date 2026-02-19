<?php
/**
 * Agent: Locations Management — Sawari
 * 
 * View locations submitted by this agent. Add new locations via modal with
 * Leaflet map pin-drop + Geolocation API. Edit own pending entries.
 */

$pageTitle = 'Locations — Agent — Sawari';
$pageCss = ['admin.css', 'agent.css'];
$bodyClass = 'admin-page agent-page';
$pageJs = ['agent/agent.js'];
$useLeaflet = true;
$currentPage = 'locations';

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/validation.php';

requireAuth('agent');

$pdo = getDBConnection();
$agentId = getCurrentUserId();

// ─── Filters ─────────────────────────────────────────────
$statusFilter = getParam('status', '');
$search = getParam('search', '');
$page = getIntParam('page', 1);

$where = ['l.updated_by = :agent_id'];
$params = ['agent_id' => $agentId];

if ($statusFilter && in_array($statusFilter, ['pending', 'approved', 'rejected'])) {
    $where[] = 'l.status = :status';
    $params['status'] = $statusFilter;
}

if ($search) {
    $where[] = 'l.name LIKE :search';
    $params['search'] = '%' . $search . '%';
}

$whereClause = 'WHERE ' . implode(' AND ', $where);

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM locations l $whereClause");
$countStmt->execute($params);
$totalItems = (int) $countStmt->fetchColumn();
$pagination = paginate($totalItems, $page);

$stmt = $pdo->prepare("
    SELECT l.*
    FROM locations l
    $whereClause
    ORDER BY FIELD(l.status, 'pending', 'approved', 'rejected'), l.location_id DESC
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

$filterParams = http_build_query(array_filter(['status' => $statusFilter, 'search' => $search]));
$baseUrl = BASE_URL . '/pages/agent/locations.php' . ($filterParams ? '?' . $filterParams : '');
?>

<div class="admin-layout">
    <?php require_once __DIR__ . '/../../includes/agent-sidebar.php'; ?>

    <div class="admin-content">
        <div class="admin-page-header">
            <h1>My Locations</h1>
            <button class="btn btn-primary" onclick="openAddModal()">+ Add Location</button>
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
            <a href="<?= BASE_URL ?>/pages/agent/locations.php" class="filter-btn secondary">Reset</a>
        </form>

        <div class="data-table-wrapper">
            <?php if (empty($locations)): ?>
                <div class="empty-state">
                    <div class="empty-icon"><i class="fa-duotone fa-solid fa-location-dot"></i></div>
                    <h3>No locations yet</h3>
                    <p>Add bus stops and landmarks to help commuters find their way!</p>
                </div>
            <?php else: ?>
                <div class="table-scroll">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Coordinates</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($locations as $loc): ?>
                                <tr>
                                    <td><strong><?= sanitize($loc['name']) ?></strong>
                                        <?php if ($loc['description']): ?>
                                            <div style="font-size:0.8125rem; color:var(--color-text-muted);">
                                                <?= sanitize(mb_strimwidth($loc['description'], 0, 50, '...')) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?= $loc['type'] === 'stop' ? 'stop' : 'landmark' ?>">
                                            <?= sanitize($loc['type']) ?>
                                        </span>
                                    </td>
                                    <td style="font-size:0.8125rem;">
                                        <?= number_format($loc['latitude'], 6) ?>,<br>
                                        <?= number_format($loc['longitude'], 6) ?>
                                    </td>
                                    <td>
                                        <?php
                                        $badgeClass = match ($loc['status']) {
                                            'approved' => 'badge-approved',
                                            'rejected' => 'badge-rejected',
                                            default => 'badge-pending',
                                        };
                                        ?>
                                        <span class="badge <?= $badgeClass ?>"><?= sanitize($loc['status']) ?></span>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <?php if ($loc['status'] === 'pending'): ?>
                                                <button class="btn btn-sm btn-outline"
                                                    onclick='openEditModal(<?= json_encode($loc) ?>)'>Edit</button>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-outline"
                                                    onclick='viewLocation(<?= json_encode($loc) ?>)'>View</button>
                                            <?php endif; ?>
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

<!-- Add/Edit Location Modal -->
<div class="modal-overlay" id="locationModal">
    <div class="modal" style="max-width: 600px;">
        <div class="modal-header">
            <h2 id="modalTitle">Add Location</h2>
            <button class="modal-close"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form id="locationForm">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="location_id" id="editLocationId" value="">

                <div class="form-group">
                    <label class="form-label">Name *</label>
                    <input type="text" name="name" class="form-input" required placeholder="e.g. Ratna Park Bus Stop">
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <input type="text" name="description" class="form-input"
                        placeholder="Short description (5-10 words)">
                </div>
                <div class="form-group">
                    <label class="form-label">Type</label>
                    <select name="type" class="form-select">
                        <option value="stop">Bus Stop</option>
                        <option value="landmark">Landmark</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Location on Map *</label>
                    <button type="button" class="btn-geolocation" onclick="useMyLocation()">
                        <i class="fa-duotone fa-solid fa-location-dot"></i> Use My Location
                    </button>
                    <div id="locationMap"
                        style="height: 300px; margin-top: var(--space-sm); border-radius: var(--radius-md); border: 1px solid var(--color-border);">
                    </div>
                    <div class="coordinate-display"
                        style="display:flex; gap:var(--space-sm); margin-top:var(--space-sm);">
                        <div style="flex:1;">
                            <label class="form-label" style="font-size:0.8125rem;">Latitude</label>
                            <input type="number" name="latitude" id="latInput" class="form-input" step="any" required
                                placeholder="27.7172" style="font-size:0.875rem;">
                        </div>
                        <div style="flex:1;">
                            <label class="form-label" style="font-size:0.8125rem;">Longitude</label>
                            <input type="number" name="longitude" id="lngInput" class="form-input" step="any" required
                                placeholder="85.3240" style="font-size:0.875rem;">
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline"
                    onclick="AgentUtils.closeFormModal('#locationModal')">Cancel</button>
                <button type="submit" class="btn btn-primary" id="locationSubmitBtn">Submit Location</button>
            </div>
        </form>
    </div>
</div>

<!-- View Location Modal -->
<div class="modal-overlay" id="viewModal">
    <div class="modal" style="max-width: 500px;">
        <div class="modal-header">
            <h2>Location Details</h2>
            <button class="modal-close"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body" id="viewBody"></div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="AgentUtils.closeFormModal('#viewModal')">Close</button>
        </div>
    </div>
</div>

<script>
    const BASE = '<?= BASE_URL ?>';
    const DEFAULT_LAT = <?= DEFAULT_LAT ?>;
    const DEFAULT_LNG = <?= DEFAULT_LNG ?>;
    const DEFAULT_ZOOM = <?= DEFAULT_ZOOM ?>;

    let map, marker;
    let isEditing = false;

    // ─── Map Init ──────────────────────────────────────────
    function initMap(lat, lng) {
        if (map) {
            map.setView([lat, lng], 15);
            if (marker) marker.setLatLng([lat, lng]);
            else marker = L.marker([lat, lng], { draggable: true }).addTo(map);
        } else {
            map = L.map('locationMap').setView([lat, lng], DEFAULT_ZOOM);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);

            marker = L.marker([lat, lng], { draggable: true }).addTo(map);

            // Click on map to place marker
            map.on('click', function (e) {
                marker.setLatLng(e.latlng);
                updateCoords(e.latlng.lat, e.latlng.lng);
            });

            // Drag marker
            marker.on('dragend', function () {
                const pos = marker.getLatLng();
                updateCoords(pos.lat, pos.lng);
            });
        }
        updateCoords(lat, lng);
    }

    function updateCoords(lat, lng) {
        document.getElementById('latInput').value = lat.toFixed(8);
        document.getElementById('lngInput').value = lng.toFixed(8);
    }

    // Manual coordinate input → move marker
    document.getElementById('latInput').addEventListener('change', syncMarker);
    document.getElementById('lngInput').addEventListener('change', syncMarker);

    function syncMarker() {
        const lat = parseFloat(document.getElementById('latInput').value);
        const lng = parseFloat(document.getElementById('lngInput').value);
        if (!isNaN(lat) && !isNaN(lng) && map && marker) {
            marker.setLatLng([lat, lng]);
            map.setView([lat, lng], map.getZoom());
        }
    }

    // ─── Geolocation ─────────────────────────────────────────
    function useMyLocation() {
        if (!navigator.geolocation) {
            SawariUtils.showToast('Geolocation is not supported by your browser.', 'error');
            return;
        }
        const btn = document.querySelector('.btn-geolocation');
        btn.disabled = true;
        btn.textContent = '\u2009Locating...';
        btn.insertAdjacentHTML('afterbegin', '<i class="fa-duotone fa-solid fa-location-dot"></i> ');

        navigator.geolocation.getCurrentPosition(
            (pos) => {
                initMap(pos.coords.latitude, pos.coords.longitude);
                map.setView([pos.coords.latitude, pos.coords.longitude], 17);
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-duotone fa-solid fa-location-dot"></i> Use My Location';
                SawariUtils.showToast('Location found!', 'success');
            },
            (err) => {
                SawariUtils.showToast('Could not get your location: ' + err.message, 'error');
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-duotone fa-solid fa-location-dot"></i> Use My Location';
            },
            { enableHighAccuracy: true, timeout: 10000 }
        );
    }

    // ─── Modal Handlers ──────────────────────────────────────
    function openAddModal() {
        isEditing = false;
        document.getElementById('modalTitle').textContent = 'Add Location';
        document.getElementById('locationSubmitBtn').textContent = 'Submit Location';
        document.getElementById('editLocationId').value = '';
        document.getElementById('locationForm').reset();
        document.querySelector('[name="csrf_token"]').value = '<?= $csrfToken ?>';
        AgentUtils.openFormModal('#locationModal');
        setTimeout(() => {
            initMap(DEFAULT_LAT, DEFAULT_LNG);
            map.invalidateSize();
        }, 200);
    }

    function openEditModal(loc) {
        isEditing = true;
        document.getElementById('modalTitle').textContent = 'Edit Location';
        document.getElementById('locationSubmitBtn').textContent = 'Update Location';
        document.getElementById('editLocationId').value = loc.location_id;
        document.querySelector('[name="name"]').value = loc.name;
        document.querySelector('[name="description"]').value = loc.description || '';
        document.querySelector('[name="type"]').value = loc.type;
        document.querySelector('[name="csrf_token"]').value = '<?= $csrfToken ?>';
        AgentUtils.openFormModal('#locationModal');
        setTimeout(() => {
            initMap(parseFloat(loc.latitude), parseFloat(loc.longitude));
            map.setView([parseFloat(loc.latitude), parseFloat(loc.longitude)], 16);
            map.invalidateSize();
        }, 200);
    }

    function viewLocation(loc) {
        const body = document.getElementById('viewBody');
        body.innerHTML = `
        <div class="detail-row"><span class="detail-label">Name</span><span class="detail-value"><strong>${SawariUtils.escapeHTML(loc.name)}</strong></span></div>
        <div class="detail-row"><span class="detail-label">Description</span><span class="detail-value">${SawariUtils.escapeHTML(loc.description || '—')}</span></div>
        <div class="detail-row"><span class="detail-label">Type</span><span class="detail-value">${SawariUtils.statusBadge(loc.type)}</span></div>
        <div class="detail-row"><span class="detail-label">Status</span><span class="detail-value">${SawariUtils.statusBadge(loc.status)}</span></div>
        <div class="detail-row"><span class="detail-label">Coordinates</span><span class="detail-value">${loc.latitude}, ${loc.longitude}</span></div>
    `;
        AgentUtils.openFormModal('#viewModal');
    }

    // ─── Form Submit ─────────────────────────────────────────
    document.getElementById('locationForm').addEventListener('submit', async function (e) {
        e.preventDefault();
        const formData = new FormData(this);
        const locationId = formData.get('location_id');

        const url = locationId
            ? `${BASE}/api/locations/update.php`
            : `${BASE}/api/locations/create.php`;

        await AgentUtils.apiAction(url, formData, {
            onSuccess: () => {
                AgentUtils.closeFormModal('#locationModal');
                setTimeout(() => location.reload(), 500);
            }
        });
    });
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>