<?php
/**
 * Admin: Vehicles Management — Sawari
 * 
 * List, add/edit, approve/reject/delete vehicles.
 * Vehicle form includes route assignment and operating hours.
 */

$pageTitle = 'Vehicles — Admin — Sawari';
$pageCss = ['admin.css'];
$bodyClass = 'admin-page';
$pageJs = ['admin.js'];
$currentPage = 'vehicles';

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
    $where[] = 'v.status = :status';
    $params['status'] = $statusFilter;
}

if ($search) {
    $where[] = 'v.name LIKE :search';
    $params['search'] = '%' . $search . '%';
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM vehicles v $whereClause");
$countStmt->execute($params);
$totalItems = (int) $countStmt->fetchColumn();
$pagination = paginate($totalItems, $page);

$stmt = $pdo->prepare("
    SELECT v.*, a.name AS updater_name
    FROM vehicles v
    LEFT JOIN agents a ON v.updated_by = a.agent_id
    $whereClause
    ORDER BY v.updated_at DESC
    LIMIT :limit OFFSET :offset
");
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->bindValue('limit', $pagination['perPage'], PDO::PARAM_INT);
$stmt->bindValue('offset', $pagination['offset'], PDO::PARAM_INT);
$stmt->execute();
$vehicles = $stmt->fetchAll();

// Approved routes for assignment
$approvedRoutes = $pdo->query("
    SELECT route_id, name FROM routes WHERE status = 'approved' ORDER BY name ASC
")->fetchAll();

$filterParams = http_build_query(array_filter(['status' => $statusFilter, 'search' => $search]));
$baseUrl = BASE_URL . '/pages/admin/vehicles.php' . ($filterParams ? '?' . $filterParams : '');
$csrfToken = generateCSRFToken();
?>

<div class="admin-layout">
    <?php require_once __DIR__ . '/../../includes/admin-sidebar.php'; ?>

    <div class="admin-content">
        <div class="admin-page-header">
            <h1>Vehicles Management</h1>
            <div class="header-actions">
                <button class="btn btn-primary" onclick="openAddModal()">+ Add Vehicle</button>
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
            <a href="<?= BASE_URL ?>/pages/admin/vehicles.php" class="filter-btn secondary">Reset</a>
        </form>

        <div class="data-table-wrapper">
            <?php if (empty($vehicles)): ?>
                <div class="empty-state">
                    <div class="empty-icon"><i class="fa-duotone fa-solid fa-bus"></i></div>
                    <h3>No vehicles found</h3>
                    <p>Try adjusting filters or add a new vehicle.</p>
                </div>
            <?php else: ?>
                <div class="table-scroll">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Status</th>
                                <th>Operating Hours</th>
                                <th># Routes</th>
                                <th>Updated By</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($vehicles as $v): ?>
                                <?php
                                $usedRoutes = json_decode($v['used_routes'] ?? '[]', true);
                                $routeCount = is_array($usedRoutes) ? count($usedRoutes) : 0;
                                ?>
                                <tr>
                                    <td><strong><?= sanitize($v['name']) ?></strong></td>
                                    <td>
                                        <span
                                            class="badge badge-<?= $v['status'] === 'approved' ? 'approved' : ($v['status'] === 'rejected' ? 'rejected' : 'pending') ?>">
                                            <?= sanitize($v['status']) ?>
                                        </span>
                                    </td>
                                    <td style="font-size:0.8125rem;">
                                        <?= $v['starts_at'] ? substr($v['starts_at'], 0, 5) : '—' ?> –
                                        <?= $v['stops_at'] ? substr($v['stops_at'], 0, 5) : '—' ?>
                                    </td>
                                    <td><?= $routeCount ?></td>
                                    <td><?= sanitize($v['updater_name'] ?? '—') ?></td>
                                    <td>
                                        <div class="actions">
                                            <button class="btn btn-sm btn-outline"
                                                onclick='openEditModal(<?= json_encode($v) ?>)'>Edit</button>
                                            <?php if ($v['status'] === 'pending'): ?>
                                                <button class="btn btn-sm btn-success"
                                                    onclick="approveVehicle(<?= $v['vehicle_id'] ?>)">Approve</button>
                                                <button class="btn btn-sm btn-warning"
                                                    onclick="rejectVehicle(<?= $v['vehicle_id'] ?>)">Reject</button>
                                            <?php endif; ?>
                                            <button class="btn btn-sm btn-danger"
                                                onclick="deleteVehicle(<?= $v['vehicle_id'] ?>, '<?= sanitize($v['name']) ?>')">Delete</button>
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
        <div class="modal-body">
            <form id="vehicleForm" enctype="multipart/form-data">
                <input type="hidden" name="vehicle_id" id="vehicleId" value="">

                <div class="form-group">
                    <label for="vName">Vehicle Name *</label>
                    <input type="text" id="vName" name="name" class="form-input" required
                        placeholder="e.g., Sajha Yatayat AC Bus">
                </div>

                <div class="form-group">
                    <label for="vDescription">Description</label>
                    <textarea id="vDescription" name="description" class="form-textarea"
                        placeholder="Vehicle description..."></textarea>
                </div>

                <div class="form-group">
                    <label for="vImage">Vehicle Image</label>
                    <input type="file" id="vImage" name="image" class="form-input"
                        accept="image/jpeg,image/png,image/webp">
                </div>

                <div class="form-group" style="display:flex; gap:var(--space-md);">
                    <div style="flex:1;">
                        <label for="vStartsAt">Starts At</label>
                        <input type="time" id="vStartsAt" name="starts_at" class="form-input">
                    </div>
                    <div style="flex:1;">
                        <label for="vStopsAt">Stops At</label>
                        <input type="time" id="vStopsAt" name="stops_at" class="form-input">
                    </div>
                </div>

                <div class="form-group">
                    <label>Assigned Routes</label>
                    <select id="routeSelector" class="form-select">
                        <option value="">— Select a route to assign —</option>
                        <?php foreach ($approvedRoutes as $r): ?>
                            <option value="<?= $r['route_id'] ?>"><?= sanitize($r['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div id="selectedRoutes" class="tag-list"></div>
                    <input type="hidden" id="usedRoutesInput" name="used_routes" value="[]">
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="AdminUtils.closeFormModal('#vehicleModal')">Cancel</button>
            <button class="btn btn-primary" onclick="saveVehicle()">Save Vehicle</button>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal-overlay" id="rejectModal">
    <div class="modal" style="max-width: 450px;">
        <div class="modal-header">
            <h2>Reject Vehicle</h2>
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
    const allRoutes = <?= json_encode($approvedRoutes) ?>;
    let assignedRoutes = [];
    let rejectingId = null;

    document.addEventListener('DOMContentLoaded', () => {
        document.getElementById('routeSelector').addEventListener('change', function () {
            const id = parseInt(this.value);
            if (!id) return;
            if (assignedRoutes.find(r => r.route_id === id)) {
                SawariUtils.showToast('Route already assigned', 'warning');
                this.value = '';
                return;
            }
            const route = allRoutes.find(r => r.route_id === id);
            if (route) {
                assignedRoutes.push(route);
                renderAssignedRoutes();
            }
            this.value = '';
        });
    });

    function renderAssignedRoutes() {
        const container = document.getElementById('selectedRoutes');
        container.innerHTML = assignedRoutes.map((r, i) => `
        <span class="tag">
            ${SawariUtils.escapeHTML(r.name)}
            <button type="button" class="tag-remove" onclick="removeRoute(${i})"><i class="fa-solid fa-xmark"></i></button>
        </span>
    `).join('');
        document.getElementById('usedRoutesInput').value = JSON.stringify(
            assignedRoutes.map(r => r.route_id)
        );
    }

    function removeRoute(index) {
        assignedRoutes.splice(index, 1);
        renderAssignedRoutes();
    }

    function openAddModal() {
        document.getElementById('vehicleModalTitle').textContent = 'Add Vehicle';
        document.getElementById('vehicleForm').reset();
        document.getElementById('vehicleId').value = '';
        assignedRoutes = [];
        renderAssignedRoutes();
        AdminUtils.openFormModal('#vehicleModal');
    }

    function openEditModal(v) {
        document.getElementById('vehicleModalTitle').textContent = 'Edit Vehicle';
        document.getElementById('vehicleId').value = v.vehicle_id;
        document.getElementById('vName').value = v.name;
        document.getElementById('vDescription').value = v.description || '';
        document.getElementById('vStartsAt').value = v.starts_at ? v.starts_at.substring(0, 5) : '';
        document.getElementById('vStopsAt').value = v.stops_at ? v.stops_at.substring(0, 5) : '';

        const usedRoutes = JSON.parse(v.used_routes || '[]');
        assignedRoutes = usedRoutes.map(id => allRoutes.find(r => r.route_id === id)).filter(Boolean);
        renderAssignedRoutes();

        AdminUtils.openFormModal('#vehicleModal');
    }

    async function saveVehicle() {
        const id = document.getElementById('vehicleId').value;
        const formData = new FormData(document.getElementById('vehicleForm'));

        if (!formData.get('image')?.size) formData.delete('image');
        formData.set('used_routes', document.getElementById('usedRoutesInput').value);
        formData.set('status', 'approved');

        const url = id
            ? `${BASE}/api/vehicles/update.php?id=${id}`
            : `${BASE}/api/vehicles/create.php`;

        await AdminUtils.apiAction(url, formData, {
            onSuccess: () => location.reload()
        });
    }

    function approveVehicle(id) {
        AdminUtils.apiAction(`${BASE}/api/vehicles/update.php?id=${id}`, { status: 'approved' }, {
            onSuccess: () => location.reload()
        });
    }

    function rejectVehicle(id) {
        rejectingId = id;
        document.getElementById('rejectReason').value = '';
        AdminUtils.openFormModal('#rejectModal');
    }

    function submitReject() {
        const reason = document.getElementById('rejectReason').value.trim();
        if (!reason) { SawariUtils.showToast('Please provide a reason', 'warning'); return; }
        AdminUtils.apiAction(`${BASE}/api/vehicles/update.php?id=${rejectingId}`, { status: 'rejected' }, {
            onSuccess: () => { AdminUtils.closeFormModal('#rejectModal'); location.reload(); }
        });
    }

    function deleteVehicle(id, name) {
        AdminUtils.confirmDelete(`${BASE}/api/vehicles/delete.php?id=${id}`, name);
    }
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>