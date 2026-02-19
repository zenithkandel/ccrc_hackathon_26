<?php
/**
 * Agent: Vehicles Management — Sawari
 * 
 * View vehicles submitted by this agent. Add new vehicles with image,
 * operating hours, and route assignment with count per route.
 */

$pageTitle = 'Vehicles — Agent — Sawari';
$pageCss = ['admin.css', 'agent.css'];
$bodyClass = 'admin-page agent-page';
$pageJs = ['agent/agent.js'];
$currentPage = 'vehicles';

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/validation.php';

requireAuth('agent');

$pdo = getDBConnection();
$agentId = getCurrentUserId();

// ─── Filters ─────────────────────────────────────────────
$statusFilter = getParam('status', '');
$page = getIntParam('page', 1);

$where = ['v.updated_by = :agent_id'];
$params = ['agent_id' => $agentId];

if ($statusFilter && in_array($statusFilter, ['pending', 'approved', 'rejected'])) {
    $where[] = 'v.status = :status';
    $params['status'] = $statusFilter;
}

$whereClause = 'WHERE ' . implode(' AND ', $where);

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM vehicles v $whereClause");
$countStmt->execute($params);
$totalItems = (int) $countStmt->fetchColumn();
$pagination = paginate($totalItems, $page);

$stmt = $pdo->prepare("
    SELECT v.*
    FROM vehicles v
    $whereClause
    ORDER BY FIELD(v.status, 'pending', 'approved', 'rejected'), v.vehicle_id DESC
    LIMIT :limit OFFSET :offset
");
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->bindValue('limit', $pagination['perPage'], PDO::PARAM_INT);
$stmt->bindValue('offset', $pagination['offset'], PDO::PARAM_INT);
$stmt->execute();
$vehicles = $stmt->fetchAll();

// Fetch approved routes for assignment
$approvedRoutes = $pdo->query("SELECT route_id, name FROM routes WHERE status = 'approved' ORDER BY name ASC")->fetchAll();

$csrfToken = generateCSRFToken();
$filterParams = http_build_query(array_filter(['status' => $statusFilter]));
$baseUrl = BASE_URL . '/pages/agent/vehicles.php' . ($filterParams ? '?' . $filterParams : '');
?>

<div class="admin-layout">
    <?php require_once __DIR__ . '/../../includes/agent-sidebar.php'; ?>

    <div class="admin-content">
        <div class="admin-page-header">
            <h1>My Vehicles</h1>
            <button class="btn btn-primary" onclick="openAddModal()">+ Add Vehicle</button>
        </div>

        <form class="filter-bar" method="GET">
            <select name="status" class="filter-select">
                <option value="">All Statuses</option>
                <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="approved" <?= $statusFilter === 'approved' ? 'selected' : '' ?>>Approved</option>
                <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
            </select>
            <button type="submit" class="filter-btn">Filter</button>
            <a href="<?= BASE_URL ?>/pages/agent/vehicles.php" class="filter-btn secondary">Reset</a>
        </form>

        <div class="data-table-wrapper">
            <?php if (empty($vehicles)): ?>
                <div class="empty-state">
                    <div class="empty-icon"><i class="fa-duotone fa-solid fa-bus"></i></div>
                    <h3>No vehicles yet</h3>
                    <p>Register public transport vehicles to help commuters identify them!</p>
                </div>
            <?php else: ?>
                <div class="table-scroll">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Vehicle</th>
                                <th>Operating Hours</th>
                                <th>Routes</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($vehicles as $v): ?>
                                <?php $usedRoutes = json_decode($v['used_routes'] ?? '[]', true); ?>
                                <tr>
                                    <td>
                                        <div style="display:flex; align-items:center; gap:8px;">
                                            <?php if ($v['image_path']): ?>
                                                <img src="<?= BASE_URL ?>/<?= sanitize($v['image_path']) ?>" alt=""
                                                    style="width:40px; height:40px; border-radius:var(--radius-md); object-fit:cover;">
                                            <?php endif; ?>
                                            <div>
                                                <strong><?= sanitize($v['name']) ?></strong>
                                                <?php if ($v['description']): ?>
                                                    <div style="font-size:0.8125rem; color:var(--color-text-muted);">
                                                        <?= sanitize(mb_strimwidth($v['description'], 0, 40, '...')) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="font-size:0.875rem;">
                                        <?php if ($v['starts_at'] && $v['stops_at']): ?>
                                            <?= date('g:i A', strtotime($v['starts_at'])) ?> —
                                            <?= date('g:i A', strtotime($v['stops_at'])) ?>
                                        <?php else: ?>
                                            <span style="color:var(--color-text-muted);">Not set</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge badge-info"><?= count($usedRoutes) ?> routes</span></td>
                                    <td>
                                        <?php
                                        $badgeClass = match ($v['status']) {
                                            'approved' => 'badge-approved',
                                            'rejected' => 'badge-rejected',
                                            default => 'badge-pending',
                                        };
                                        ?>
                                        <span class="badge <?= $badgeClass ?>"><?= sanitize($v['status']) ?></span>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <?php if ($v['status'] === 'pending'): ?>
                                                <button class="btn btn-sm btn-outline"
                                                    onclick='openEditModal(<?= json_encode($v) ?>)'>Edit</button>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-outline"
                                                    onclick='viewVehicle(<?= json_encode($v) ?>)'>View</button>
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

<!-- Add/Edit Vehicle Modal -->
<div class="modal-overlay" id="vehicleModal">
    <div class="modal" style="max-width: 600px;">
        <div class="modal-header">
            <h2 id="vehicleModalTitle">Add Vehicle</h2>
            <button class="modal-close"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form id="vehicleForm" enctype="multipart/form-data">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="vehicle_id" id="editVehicleId" value="">

                <div class="form-group">
                    <label class="form-label">Vehicle / Yatayat Name *</label>
                    <input type="text" name="name" class="form-input" required placeholder="e.g. Sajha Yatayat">
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <input type="text" name="description" class="form-input"
                        placeholder="Short description for identification">
                </div>
                <div class="form-group">
                    <label class="form-label">Vehicle Photo (for user identification)</label>
                    <input type="file" name="image" class="form-input" accept="image/jpeg,image/png,image/webp">
                </div>

                <!-- Operating Hours -->
                <div style="display:flex; gap:var(--space-md);">
                    <div class="form-group" style="flex:1;">
                        <label class="form-label">Starts At</label>
                        <input type="time" name="starts_at" class="form-input">
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label class="form-label">Stops At</label>
                        <input type="time" name="stops_at" class="form-input">
                    </div>
                </div>

                <!-- Route Assignment -->
                <div class="form-group">
                    <label class="form-label">Assign Routes</label>
                    <div style="display:flex; gap:var(--space-sm);">
                        <select id="routeSelector" class="form-select" style="flex:1;">
                            <option value="">Select a route...</option>
                            <?php foreach ($approvedRoutes as $rt): ?>
                                <option value="<?= $rt['route_id'] ?>"><?= sanitize($rt['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="btn btn-primary" onclick="addRoute()">Add</button>
                    </div>
                </div>

                <div id="routeAssignments"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline"
                    onclick="AgentUtils.closeFormModal('#vehicleModal')">Cancel</button>
                <button type="submit" class="btn btn-primary" id="vehicleSubmitBtn">Submit Vehicle</button>
            </div>
        </form>
    </div>
</div>

<!-- View Vehicle Modal -->
<div class="modal-overlay" id="viewVehicleModal">
    <div class="modal" style="max-width: 500px;">
        <div class="modal-header">
            <h2>Vehicle Details</h2>
            <button class="modal-close"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body" id="viewVehicleBody"></div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="AgentUtils.closeFormModal('#viewVehicleModal')">Close</button>
        </div>
    </div>
</div>

<script>
    const BASE = '<?= BASE_URL ?>';
    const ROUTES = <?= json_encode($approvedRoutes) ?>;

    // Route lookup
    const routeMap = {};
    ROUTES.forEach(r => { routeMap[r.route_id] = r; });

    let assignedRoutes = []; // [{route_id, count}]

    function addRoute() {
        const sel = document.getElementById('routeSelector');
        const routeId = parseInt(sel.value);
        if (!routeId) return;
        if (assignedRoutes.some(r => r.route_id === routeId)) {
            SawariUtils.showToast('This route is already assigned.', 'warning');
            return;
        }
        assignedRoutes.push({ route_id: routeId, count: 1 });
        renderRouteAssignments();
        sel.value = '';
    }

    function removeRoute(routeId) {
        assignedRoutes = assignedRoutes.filter(r => r.route_id !== routeId);
        renderRouteAssignments();
    }

    function renderRouteAssignments() {
        const container = document.getElementById('routeAssignments');
        if (assignedRoutes.length === 0) {
            container.innerHTML = '<p style="font-size:0.875rem; color:var(--color-text-muted);">No routes assigned yet.</p>';
            return;
        }
        container.innerHTML = assignedRoutes.map(ar => {
            const route = routeMap[ar.route_id];
            const name = route ? route.name : `Route #${ar.route_id}`;
            return `<div class="route-assignment">
            <span class="route-name">${SawariUtils.escapeHTML(name)}</span>
            <label style="font-size:0.8125rem; color:var(--color-text-muted); white-space:nowrap;">Vehicles:</label>
            <input type="number" class="route-count-input" min="1" value="${ar.count}" onchange="updateRouteCount(${ar.route_id}, this.value)">
            <button type="button" class="route-remove" onclick="removeRoute(${ar.route_id})" title="Remove"><i class="fa-solid fa-xmark"></i></button>
        </div>`;
        }).join('');
    }

    function updateRouteCount(routeId, count) {
        const ar = assignedRoutes.find(r => r.route_id === routeId);
        if (ar) ar.count = Math.max(1, parseInt(count) || 1);
    }

    // ─── Modal Handlers ──────────────────────────────────────
    function openAddModal() {
        assignedRoutes = [];
        document.getElementById('vehicleModalTitle').textContent = 'Add Vehicle';
        document.getElementById('vehicleSubmitBtn').textContent = 'Submit Vehicle';
        document.getElementById('editVehicleId').value = '';
        document.getElementById('vehicleForm').reset();
        document.querySelector('#vehicleForm [name="csrf_token"]').value = '<?= $csrfToken ?>';
        renderRouteAssignments();
        AgentUtils.openFormModal('#vehicleModal');
    }

    function openEditModal(vehicle) {
        assignedRoutes = JSON.parse(vehicle.used_routes || '[]');
        document.getElementById('vehicleModalTitle').textContent = 'Edit Vehicle';
        document.getElementById('vehicleSubmitBtn').textContent = 'Update Vehicle';
        document.getElementById('editVehicleId').value = vehicle.vehicle_id;
        document.querySelector('#vehicleForm [name="name"]').value = vehicle.name;
        document.querySelector('#vehicleForm [name="description"]').value = vehicle.description || '';
        document.querySelector('#vehicleForm [name="starts_at"]').value = vehicle.starts_at ? vehicle.starts_at.substring(0, 5) : '';
        document.querySelector('#vehicleForm [name="stops_at"]').value = vehicle.stops_at ? vehicle.stops_at.substring(0, 5) : '';
        document.querySelector('#vehicleForm [name="csrf_token"]').value = '<?= $csrfToken ?>';
        renderRouteAssignments();
        AgentUtils.openFormModal('#vehicleModal');
    }

    function viewVehicle(v) {
        const routes = JSON.parse(v.used_routes || '[]');
        const routesBadges = routes.map(r => {
            const route = routeMap[r.route_id];
            return `<span class="badge badge-info" style="margin:2px;">${route ? SawariUtils.escapeHTML(route.name) : '#' + r.route_id} (×${r.count})</span>`;
        }).join('') || '—';

        const body = document.getElementById('viewVehicleBody');
        let imgHtml = '';
        if (v.image_path) {
            imgHtml = `<img src="${BASE}/${SawariUtils.escapeHTML(v.image_path)}" style="width:100%;max-height:200px;object-fit:cover;border-radius:var(--radius-md);margin-bottom:var(--space-md);">`;
        }
        body.innerHTML = `
        ${imgHtml}
        <div class="detail-row"><span class="detail-label">Name</span><span class="detail-value"><strong>${SawariUtils.escapeHTML(v.name)}</strong></span></div>
        <div class="detail-row"><span class="detail-label">Description</span><span class="detail-value">${SawariUtils.escapeHTML(v.description || '—')}</span></div>
        <div class="detail-row"><span class="detail-label">Status</span><span class="detail-value">${SawariUtils.statusBadge(v.status)}</span></div>
        <div class="detail-row"><span class="detail-label">Hours</span><span class="detail-value">${v.starts_at && v.stops_at ? SawariUtils.escapeHTML(v.starts_at) + ' — ' + SawariUtils.escapeHTML(v.stops_at) : '—'}</span></div>
        <div class="detail-row"><span class="detail-label">Routes</span><span class="detail-value">${routesBadges}</span></div>
        <div class="detail-row"><span class="detail-label">GPS Tracking</span><span class="detail-value">${v.current_lat && v.current_lng ? '<span class="badge badge-approved"><i class="fa-solid fa-satellite-dish"></i> Live</span> ' + (v.current_speed !== null ? parseFloat(v.current_speed).toFixed(1) + ' km/h' : '') : '<span style="color:var(--color-text-muted);">No GPS data</span>'}</span></div>
    `;
        AgentUtils.openFormModal('#viewVehicleModal');
    }

    // ─── Form Submit ─────────────────────────────────────────
    document.getElementById('vehicleForm').addEventListener('submit', async function (e) {
        e.preventDefault();

        if (assignedRoutes.length < 1) {
            SawariUtils.showToast('Please assign at least one route.', 'error');
            return;
        }

        const formData = new FormData(this);
        const vehicleId = formData.get('vehicle_id');
        formData.set('used_routes', JSON.stringify(assignedRoutes));

        const url = vehicleId
            ? `${BASE}/api/vehicles/update.php`
            : `${BASE}/api/vehicles/create.php`;

        await AgentUtils.apiAction(url, formData, {
            onSuccess: () => {
                AgentUtils.closeFormModal('#vehicleModal');
                setTimeout(() => location.reload(), 500);
            }
        });
    });
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>