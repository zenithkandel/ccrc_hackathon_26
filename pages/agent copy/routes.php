<?php
/**
 * Agent: Routes Management — Sawari
 * 
 * View routes submitted by this agent. Create new routes by selecting
 * approved locations, ordering stops, previewing on Leaflet map.
 */

$pageTitle = 'Routes — Agent — Sawari';
$pageCss = ['admin.css', 'agent.css'];
$bodyClass = 'admin-page agent-page';
$pageJs = ['agent/agent.js'];
$useLeaflet = true;
$currentPage = 'routes';

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/validation.php';

requireAuth('agent');

$pdo = getDBConnection();
$agentId = getCurrentUserId();

// ─── Filters ─────────────────────────────────────────────
$statusFilter = getParam('status', '');
$page = getIntParam('page', 1);

$where = ['r.updated_by = :agent_id'];
$params = ['agent_id' => $agentId];

if ($statusFilter && in_array($statusFilter, ['pending', 'approved', 'rejected'])) {
    $where[] = 'r.status = :status';
    $params['status'] = $statusFilter;
}

$whereClause = 'WHERE ' . implode(' AND ', $where);

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM routes r $whereClause");
$countStmt->execute($params);
$totalItems = (int) $countStmt->fetchColumn();
$pagination = paginate($totalItems, $page);

$stmt = $pdo->prepare("
    SELECT r.*
    FROM routes r
    $whereClause
    ORDER BY FIELD(r.status, 'pending', 'approved', 'rejected'), r.route_id DESC
    LIMIT :limit OFFSET :offset
");
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->bindValue('limit', $pagination['perPage'], PDO::PARAM_INT);
$stmt->bindValue('offset', $pagination['offset'], PDO::PARAM_INT);
$stmt->execute();
$routes = $stmt->fetchAll();

// Fetch approved locations for the selector
$approvedLocs = $pdo->query("SELECT location_id, name, latitude, longitude, type FROM locations WHERE status = 'approved' ORDER BY name ASC")->fetchAll();

$csrfToken = generateCSRFToken();
$filterParams = http_build_query(array_filter(['status' => $statusFilter]));
$baseUrl = BASE_URL . '/pages/agent/routes.php' . ($filterParams ? '?' . $filterParams : '');
?>

<div class="admin-layout">
    <?php require_once __DIR__ . '/../../includes/agent-sidebar.php'; ?>

    <div class="admin-content">
        <div class="admin-page-header">
            <h1>My Routes</h1>
            <button class="btn btn-primary" onclick="openAddModal()">+ Create Route</button>
        </div>

        <form class="filter-bar" method="GET">
            <select name="status" class="filter-select">
                <option value="">All Statuses</option>
                <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="approved" <?= $statusFilter === 'approved' ? 'selected' : '' ?>>Approved</option>
                <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
            </select>
            <button type="submit" class="filter-btn">Filter</button>
            <a href="<?= BASE_URL ?>/pages/agent/routes.php" class="filter-btn secondary">Reset</a>
        </form>

        <div class="data-table-wrapper">
            <?php if (empty($routes)): ?>
                <div class="empty-state">
                    <div class="empty-icon"><i class="fa-duotone fa-solid fa-route"></i></div>
                    <h3>No routes yet</h3>
                    <p>Create route maps by connecting approved locations!</p>
                </div>
            <?php else: ?>
                <div class="table-scroll">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Stops</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($routes as $route): ?>
                                <?php
                                $locList = json_decode($route['location_list'] ?? '[]', true);
                                $stopCount = count($locList);
                                ?>
                                <tr>
                                    <td>
                                        <strong><?= sanitize($route['name']) ?></strong>
                                        <?php if ($route['description']): ?>
                                            <div style="font-size:0.8125rem; color:var(--color-text-muted);">
                                                <?= sanitize(mb_strimwidth($route['description'], 0, 60, '...')) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge badge-info"><?= $stopCount ?> stops</span></td>
                                    <td>
                                        <?php
                                        $badgeClass = match ($route['status']) {
                                            'approved' => 'badge-approved',
                                            'rejected' => 'badge-rejected',
                                            default => 'badge-pending',
                                        };
                                        ?>
                                        <span class="badge <?= $badgeClass ?>"><?= sanitize($route['status']) ?></span>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <?php if ($route['status'] === 'pending'): ?>
                                                <button class="btn btn-sm btn-outline"
                                                    onclick='openEditModal(<?= json_encode($route) ?>)'>Edit</button>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-outline"
                                                    onclick='viewRoute(<?= json_encode($route) ?>)'>View</button>
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

<!-- Add/Edit Route Modal -->
<div class="modal-overlay" id="routeModal">
    <div class="modal" style="max-width: 700px;">
        <div class="modal-header">
            <h2 id="routeModalTitle">Create Route</h2>
            <button class="modal-close"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form id="routeForm" enctype="multipart/form-data">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="route_id" id="editRouteId" value="">

                <div class="form-group">
                    <label class="form-label">Route Name * <span
                            style="font-weight:400; font-size:0.8125rem; color:var(--color-text-muted);">(e.g. "Ratna
                            Park - Sundhara")</span></label>
                    <input type="text" name="name" class="form-input" required placeholder="Start - End">
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <input type="text" name="description" class="form-input"
                        placeholder="Short description of this route">
                </div>
                <div class="form-group">
                    <label class="form-label">Route Image (optional)</label>
                    <input type="file" name="image" class="form-input" accept="image/jpeg,image/png,image/webp">
                </div>

                <!-- Location Selector -->
                <div class="form-group">
                    <label class="form-label">Add Stops (approved locations)</label>
                    <div style="display:flex; gap:var(--space-sm);">
                        <select id="locationSelector" class="form-select" style="flex:1;">
                            <option value="">Select a location...</option>
                            <?php foreach ($approvedLocs as $loc): ?>
                                <option value="<?= $loc['location_id'] ?>" data-lat="<?= $loc['latitude'] ?>"
                                    data-lng="<?= $loc['longitude'] ?>" data-name="<?= sanitize($loc['name']) ?>">
                                    <?= sanitize($loc['name']) ?> (<?= $loc['type'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="btn btn-primary" onclick="addStop()">Add</button>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Route Stops <span style="font-weight:400; font-size:0.8125rem;">(drag to
                            reorder)</span></label>
                    <ul class="stop-list" id="stopList">
                        <li class="empty-placeholder"
                            style="text-align:center; padding:var(--space-md); color:var(--color-text-muted); font-size:0.875rem;">
                            No stops added yet. Select locations above.</li>
                    </ul>
                </div>

                <!-- Map Preview -->
                <div class="form-group">
                    <label class="form-label">Route Preview</label>
                    <div id="routeMap"
                        style="height: 280px; border-radius: var(--radius-md); border: 1px solid var(--color-border);">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline"
                    onclick="AgentUtils.closeFormModal('#routeModal')">Cancel</button>
                <button type="submit" class="btn btn-primary" id="routeSubmitBtn">Submit Route</button>
            </div>
        </form>
    </div>
</div>

<!-- View Route Modal -->
<div class="modal-overlay" id="viewRouteModal">
    <div class="modal" style="max-width: 600px;">
        <div class="modal-header">
            <h2>Route Details</h2>
            <button class="modal-close"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body" id="viewRouteBody"></div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="AgentUtils.closeFormModal('#viewRouteModal')">Close</button>
        </div>
    </div>
</div>

<script>
    const BASE = '<?= BASE_URL ?>';
    const LOCATIONS = <?= json_encode($approvedLocs) ?>;

    let routeMap, routePolyline;
    let selectedStops = [];

    // Location lookup map
    const locMap = {};
    LOCATIONS.forEach(l => { locMap[l.location_id] = l; });

    // ─── Stop Management ─────────────────────────────────────
    function addStop() {
        const sel = document.getElementById('locationSelector');
        const locId = parseInt(sel.value);
        if (!locId) return;
        if (selectedStops.includes(locId)) {
            SawariUtils.showToast('This location is already in the route.', 'warning');
            return;
        }

        selectedStops.push(locId);
        renderStopList();
        updateRouteMap();
        sel.value = '';
    }

    function removeStop(locId) {
        selectedStops = selectedStops.filter(id => id !== locId);
        renderStopList();
        updateRouteMap();
    }

    function renderStopList() {
        const list = document.getElementById('stopList');
        if (selectedStops.length === 0) {
            list.innerHTML = '<li class="empty-placeholder" style="text-align:center; padding:var(--space-md); color:var(--color-text-muted); font-size:0.875rem;">No stops added yet. Select locations above.</li>';
            return;
        }

        list.innerHTML = selectedStops.map((locId, i) => {
            const loc = locMap[locId];
            const name = loc ? loc.name : `Location #${locId}`;
            return `<li class="stop-item" draggable="true" data-location-id="${locId}">
            <span class="stop-index">${i + 1}</span>
            <span class="stop-name">${SawariUtils.escapeHTML(name)}</span>
            <button type="button" class="stop-remove" onclick="removeStop(${locId})" title="Remove"><i class="fa-solid fa-xmark"></i></button>
        </li>`;
        }).join('');

        // Init drag-and-drop for reordering
        AgentUtils.initDragSort(list, (newOrder) => {
            selectedStops = newOrder;
            updateRouteMap();
        });
    }

    // ─── Map Preview ─────────────────────────────────────────
    function initRouteMap() {
        if (!routeMap) {
            routeMap = L.map('routeMap').setView([<?= DEFAULT_LAT ?>, <?= DEFAULT_LNG ?>], <?= DEFAULT_ZOOM ?>);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(routeMap);
        }
    }

    function updateRouteMap() {
        if (!routeMap) return;

        // Clear existing
        if (routePolyline) routeMap.removeLayer(routePolyline);
        routeMap.eachLayer(layer => {
            if (layer instanceof L.Marker) routeMap.removeLayer(layer);
        });
        // Re-add tile layer
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(routeMap);

        if (selectedStops.length === 0) return;

        const latlngs = [];
        selectedStops.forEach((locId, i) => {
            const loc = locMap[locId];
            if (!loc) return;

            const lat = parseFloat(loc.latitude);
            const lng = parseFloat(loc.longitude);
            latlngs.push([lat, lng]);

            const marker = L.marker([lat, lng]).addTo(routeMap);
            marker.bindTooltip(`${i + 1}. ${loc.name}`, { permanent: false });
        });

        if (latlngs.length >= 2) {
            routePolyline = L.polyline(latlngs, {
                color: '#2563eb',
                weight: 3,
                opacity: 0.8
            }).addTo(routeMap);
            routeMap.fitBounds(routePolyline.getBounds().pad(0.1));
        } else if (latlngs.length === 1) {
            routeMap.setView(latlngs[0], 15);
        }
    }

    // ─── Modal Handlers ──────────────────────────────────────
    function openAddModal() {
        selectedStops = [];
        document.getElementById('routeModalTitle').textContent = 'Create Route';
        document.getElementById('routeSubmitBtn').textContent = 'Submit Route';
        document.getElementById('editRouteId').value = '';
        document.getElementById('routeForm').reset();
        document.querySelector('#routeForm [name="csrf_token"]').value = '<?= $csrfToken ?>';
        renderStopList();
        AgentUtils.openFormModal('#routeModal');
        setTimeout(() => {
            initRouteMap();
            routeMap.invalidateSize();
            updateRouteMap();
        }, 200);
    }

    function openEditModal(route) {
        const locList = JSON.parse(route.location_list || '[]');
        selectedStops = locList.sort((a, b) => a.index - b.index).map(item => item.location_id);

        document.getElementById('routeModalTitle').textContent = 'Edit Route';
        document.getElementById('routeSubmitBtn').textContent = 'Update Route';
        document.getElementById('editRouteId').value = route.route_id;
        document.querySelector('#routeForm [name="name"]').value = route.name;
        document.querySelector('#routeForm [name="description"]').value = route.description || '';
        document.querySelector('#routeForm [name="csrf_token"]').value = '<?= $csrfToken ?>';
        renderStopList();
        AgentUtils.openFormModal('#routeModal');
        setTimeout(() => {
            initRouteMap();
            routeMap.invalidateSize();
            updateRouteMap();
        }, 200);
    }

    function viewRoute(route) {
        const locList = JSON.parse(route.location_list || '[]');
        const stops = locList.sort((a, b) => a.index - b.index);
        let stopsHtml = stops.map((s, i) => {
            const loc = locMap[s.location_id];
            return `<span class="badge badge-info" style="margin:2px;">${i + 1}. ${loc ? SawariUtils.escapeHTML(loc.name) : '#' + s.location_id}</span>`;
        }).join('');

        const body = document.getElementById('viewRouteBody');
        body.innerHTML = `
        <div class="detail-row"><span class="detail-label">Name</span><span class="detail-value"><strong>${SawariUtils.escapeHTML(route.name)}</strong></span></div>
        <div class="detail-row"><span class="detail-label">Description</span><span class="detail-value">${SawariUtils.escapeHTML(route.description || '—')}</span></div>
        <div class="detail-row"><span class="detail-label">Status</span><span class="detail-value">${SawariUtils.statusBadge(route.status)}</span></div>
        <div class="detail-row"><span class="detail-label">Stops</span><span class="detail-value">${stopsHtml || '—'}</span></div>
    `;
        AgentUtils.openFormModal('#viewRouteModal');
    }

    // ─── Form Submit ─────────────────────────────────────────
    document.getElementById('routeForm').addEventListener('submit', async function (e) {
        e.preventDefault();

        if (selectedStops.length < 2) {
            SawariUtils.showToast('A route needs at least 2 stops.', 'error');
            return;
        }

        const formData = new FormData(this);
        const routeId = formData.get('route_id');

        // Build location_list JSON
        const locationList = selectedStops.map((locId, i) => ({ index: i + 1, location_id: locId }));
        formData.set('location_list', JSON.stringify(locationList));

        const url = routeId
            ? `${BASE}/api/routes/update.php`
            : `${BASE}/api/routes/create.php`;

        await AgentUtils.apiAction(url, formData, {
            onSuccess: () => {
                AgentUtils.closeFormModal('#routeModal');
                setTimeout(() => location.reload(), 500);
            }
        });
    });
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>