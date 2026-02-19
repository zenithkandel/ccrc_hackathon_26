<?php
/**
 * Admin: Alerts Management — Sawari
 * 
 * Create, edit, delete alerts. Active vs Expired tabs.
 * Alerts have routes_affected JSON for multi-select route links.
 */

$pageTitle = 'Alerts — Admin — Sawari';
$pageCss = ['admin.css'];
$bodyClass = 'admin-page';
$pageJs = ['admin.js'];
$currentPage = 'alerts';

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/validation.php';

requireAuth('admin');

$pdo = getDBConnection();

// ─── Tab: active or expired ──────────────────────────────
$tab = getParam('tab', 'active');
$page = getIntParam('page', 1);

if ($tab === 'expired') {
    $whereClause = 'WHERE a.expires_at <= NOW()';
} else {
    $whereClause = 'WHERE a.expires_at > NOW()';
}

$countStmt = $pdo->query("SELECT COUNT(*) FROM alerts a $whereClause");
$totalItems = (int) $countStmt->fetchColumn();
$pagination = paginate($totalItems, $page);

$stmt = $pdo->prepare("
    SELECT a.*, adm.name AS issuer_name
    FROM alerts a
    LEFT JOIN admins adm ON a.issued_by = adm.admin_id
    $whereClause
    ORDER BY a.reported_at DESC
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue('limit', $pagination['perPage'], PDO::PARAM_INT);
$stmt->bindValue('offset', $pagination['offset'], PDO::PARAM_INT);
$stmt->execute();
$alerts = $stmt->fetchAll();

// Approved routes for multi-select
$approvedRoutes = $pdo->query("
    SELECT route_id, name FROM routes WHERE status = 'approved' ORDER BY name ASC
")->fetchAll();

// Build route name lookup
$routeNames = [];
foreach ($approvedRoutes as $r) {
    $routeNames[$r['route_id']] = $r['name'];
}

$baseUrl = BASE_URL . '/pages/admin/alerts.php?tab=' . $tab;
$csrfToken = generateCSRFToken();
?>

<div class="admin-layout">
    <?php require_once __DIR__ . '/../../includes/admin-sidebar.php'; ?>

    <div class="admin-content">
        <div class="admin-page-header">
            <h1>Alerts Management</h1>
            <div class="header-actions">
                <button class="btn btn-primary" onclick="openAddModal()">+ Create Alert</button>
            </div>
        </div>

        <!-- Tabs -->
        <div class="tab-nav">
            <a href="?tab=active" class="tab-btn <?= $tab === 'active' ? 'active' : '' ?>">Active</a>
            <a href="?tab=expired" class="tab-btn <?= $tab === 'expired' ? 'active' : '' ?>">Expired</a>
        </div>

        <div class="data-table-wrapper">
            <?php if (empty($alerts)): ?>
                <div class="empty-state">
                    <div class="empty-icon"><i class="fa-duotone fa-solid fa-triangle-exclamation"></i></div>
                    <h3>No <?= $tab ?> alerts</h3>
                    <p><?= $tab === 'active' ? 'Create an alert to notify users about disruptions.' : 'No expired alerts found.' ?>
                    </p>
                </div>
            <?php else: ?>
                <div class="table-scroll">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Description</th>
                                <th>Routes Affected</th>
                                <th>Reported</th>
                                <th>Expires</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($alerts as $alert): ?>
                                <?php
                                $affectedRoutes = json_decode($alert['routes_affected'] ?? '[]', true);
                                $routeLabels = [];
                                if (is_array($affectedRoutes)) {
                                    foreach ($affectedRoutes as $rid) {
                                        $routeLabels[] = $routeNames[$rid] ?? "Route #$rid";
                                    }
                                }
                                ?>
                                <tr>
                                    <td><strong><?= sanitize($alert['name']) ?></strong></td>
                                    <td class="truncate"><?= sanitize($alert['description'] ?? '') ?></td>
                                    <td>
                                        <?php if (empty($routeLabels)): ?>
                                            <span style="color:var(--color-text-muted)">None</span>
                                        <?php else: ?>
                                            <?php foreach ($routeLabels as $label): ?>
                                                <span class="badge badge-info" style="margin: 1px 0;"><?= sanitize($label) ?></span>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-size:0.8125rem;"><?= timeAgo($alert['reported_at']) ?></td>
                                    <td style="font-size:0.8125rem;"><?= formatDateTime($alert['expires_at']) ?></td>
                                    <td>
                                        <div class="actions">
                                            <button class="btn btn-sm btn-outline"
                                                onclick='openEditModal(<?= json_encode($alert) ?>)'>Edit</button>
                                            <button class="btn btn-sm btn-danger"
                                                onclick="deleteAlert(<?= $alert['alert_id'] ?>, '<?= sanitize($alert['name']) ?>')">Delete</button>
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
                    <a href="<?= $baseUrl ?>&page=<?= $pagination['currentPage'] - 1 ?>">&laquo; Prev</a>
                <?php else: ?>
                    <span class="disabled">&laquo; Prev</span>
                <?php endif; ?>
                <?php for ($i = max(1, $pagination['currentPage'] - 2); $i <= min($pagination['totalPages'], $pagination['currentPage'] + 2); $i++): ?>
                    <?php if ($i === $pagination['currentPage']): ?>
                        <span class="active"><?= $i ?></span>
                    <?php else: ?>
                        <a href="<?= $baseUrl ?>&page=<?= $i ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                <?php if ($pagination['hasNext']): ?>
                    <a href="<?= $baseUrl ?>&page=<?= $pagination['currentPage'] + 1 ?>">Next &raquo;</a>
                <?php else: ?>
                    <span class="disabled">Next &raquo;</span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Create/Edit Alert Modal -->
<div class="modal-overlay" id="alertModal">
    <div class="modal" style="max-width: 600px;">
        <div class="modal-header">
            <h2 id="alertModalTitle">Create Alert</h2>
            <button class="modal-close"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body">
            <form id="alertForm">
                <input type="hidden" name="alert_id" id="alertId" value="">

                <div class="form-group">
                    <label for="alertName">Alert Name *</label>
                    <input type="text" id="alertName" name="name" class="form-input" required
                        placeholder="e.g., Road closure at Kalimati">
                </div>

                <div class="form-group">
                    <label for="alertDescription">Description</label>
                    <textarea id="alertDescription" name="description" class="form-textarea"
                        placeholder="Describe the alert..."></textarea>
                </div>

                <div class="form-group">
                    <label for="alertExpires">Expires At *</label>
                    <input type="datetime-local" id="alertExpires" name="expires_at" class="form-input" required>
                </div>

                <div class="form-group">
                    <label>Routes Affected</label>
                    <select id="alertRouteSelector" class="form-select">
                        <option value="">— Select route —</option>
                        <?php foreach ($approvedRoutes as $r): ?>
                            <option value="<?= $r['route_id'] ?>"><?= sanitize($r['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div id="alertSelectedRoutes" class="tag-list"></div>
                    <input type="hidden" id="routesAffectedInput" name="routes_affected" value="[]">
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="AdminUtils.closeFormModal('#alertModal')">Cancel</button>
            <button class="btn btn-primary" onclick="saveAlert()">Save Alert</button>
        </div>
    </div>
</div>

<script>
    const BASE = '<?= BASE_URL ?>';
    const allRoutes = <?= json_encode($approvedRoutes) ?>;
    let alertRoutes = [];

    document.addEventListener('DOMContentLoaded', () => {
        document.getElementById('alertRouteSelector').addEventListener('change', function () {
            const id = parseInt(this.value);
            if (!id) return;
            if (alertRoutes.find(r => r.route_id === id)) {
                SawariUtils.showToast('Route already added', 'warning');
                this.value = '';
                return;
            }
            const route = allRoutes.find(r => r.route_id === id);
            if (route) {
                alertRoutes.push(route);
                renderAlertRoutes();
            }
            this.value = '';
        });
    });

    function renderAlertRoutes() {
        const container = document.getElementById('alertSelectedRoutes');
        container.innerHTML = alertRoutes.map((r, i) => `
        <span class="tag">
            ${SawariUtils.escapeHTML(r.name)}
            <button type="button" class="tag-remove" onclick="removeAlertRoute(${i})"><i class="fa-solid fa-xmark"></i></button>
        </span>
    `).join('');
        document.getElementById('routesAffectedInput').value = JSON.stringify(alertRoutes.map(r => r.route_id));
    }

    function removeAlertRoute(index) {
        alertRoutes.splice(index, 1);
        renderAlertRoutes();
    }

    function openAddModal() {
        document.getElementById('alertModalTitle').textContent = 'Create Alert';
        document.getElementById('alertForm').reset();
        document.getElementById('alertId').value = '';
        alertRoutes = [];
        renderAlertRoutes();
        AdminUtils.openFormModal('#alertModal');
    }

    function openEditModal(alert) {
        document.getElementById('alertModalTitle').textContent = 'Edit Alert';
        document.getElementById('alertId').value = alert.alert_id;
        document.getElementById('alertName').value = alert.name;
        document.getElementById('alertDescription').value = alert.description || '';

        // Format datetime-local value
        if (alert.expires_at) {
            const dt = new Date(alert.expires_at);
            const local = dt.toISOString().slice(0, 16);
            document.getElementById('alertExpires').value = local;
        }

        const affected = JSON.parse(alert.routes_affected || '[]');
        alertRoutes = affected.map(id => allRoutes.find(r => r.route_id === id)).filter(Boolean);
        renderAlertRoutes();

        AdminUtils.openFormModal('#alertModal');
    }

    async function saveAlert() {
        const id = document.getElementById('alertId').value;
        const data = {
            name: document.getElementById('alertName').value,
            description: document.getElementById('alertDescription').value,
            expires_at: document.getElementById('alertExpires').value,
            routes_affected: document.getElementById('routesAffectedInput').value
        };

        const url = id
            ? `${BASE}/api/alerts/update.php?id=${id}`
            : `${BASE}/api/alerts/create.php`;

        await AdminUtils.apiAction(url, data, {
            onSuccess: () => location.reload()
        });
    }

    function deleteAlert(id, name) {
        AdminUtils.confirmDelete(`${BASE}/api/alerts/delete.php?id=${id}`, name);
    }
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>