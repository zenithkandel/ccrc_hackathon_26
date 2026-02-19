<?php
/**
 * Admin: Contributions Review — Sawari
 * 
 * Review, accept, and reject agent contributions.
 * Shows associated entry details and allows approval or rejection with reason.
 */

$pageTitle = 'Contributions — Admin — Sawari';
$pageCss = ['admin.css'];
$bodyClass = 'admin-page';
$pageJs = ['admin.js'];
$currentPage = 'contributions';

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/validation.php';

requireAuth('admin');

$pdo = getDBConnection();

// ─── Filters ─────────────────────────────────────────────
$statusFilter = getParam('status', '');
$typeFilter = getParam('type', '');
$page = getIntParam('page', 1);

$where = [];
$params = [];

if ($statusFilter && in_array($statusFilter, ['pending', 'accepted', 'rejected'])) {
    $where[] = 'c.status = :status';
    $params['status'] = $statusFilter;
}

if ($typeFilter && in_array($typeFilter, ['vehicle', 'route', 'location'])) {
    $where[] = 'c.type = :type';
    $params['type'] = $typeFilter;
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM contributions c $whereClause");
$countStmt->execute($params);
$totalItems = (int) $countStmt->fetchColumn();
$pagination = paginate($totalItems, $page);

$stmt = $pdo->prepare("
    SELECT c.*, 
           a.name AS agent_name,
           adm.name AS reviewer_name
    FROM contributions c
    LEFT JOIN agents a ON c.proposed_by = a.agent_id
    LEFT JOIN admins adm ON c.accepted_by = adm.admin_id
    $whereClause
    ORDER BY c.proposed_at DESC
    LIMIT :limit OFFSET :offset
");
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->bindValue('limit', $pagination['perPage'], PDO::PARAM_INT);
$stmt->bindValue('offset', $pagination['offset'], PDO::PARAM_INT);
$stmt->execute();
$contributions = $stmt->fetchAll();

// Enrich with associated entry names
foreach ($contributions as &$c) {
    $c['entry_name'] = '—';
    if ($c['associated_entry_id']) {
        $table = match ($c['type']) {
            'location' => 'locations',
            'route' => 'routes',
            'vehicle' => 'vehicles',
            default => null,
        };
        if ($table) {
            $entryStmt = $pdo->prepare("SELECT name FROM $table WHERE {$c['type']}_id = :id LIMIT 1");
            $entryStmt->execute(['id' => $c['associated_entry_id']]);
            $entry = $entryStmt->fetchColumn();
            if ($entry)
                $c['entry_name'] = $entry;
        }
    }
}
unset($c);

$filterParams = http_build_query(array_filter(['status' => $statusFilter, 'type' => $typeFilter]));
$baseUrl = BASE_URL . '/pages/admin/contributions.php' . ($filterParams ? '?' . $filterParams : '');
$csrfToken = generateCSRFToken();
?>

<div class="admin-layout">
    <?php require_once __DIR__ . '/../../includes/admin-sidebar.php'; ?>

    <div class="admin-content">
        <div class="admin-page-header">
            <h1>Contributions Review</h1>
        </div>

        <form class="filter-bar" method="GET">
            <select name="status" class="filter-select">
                <option value="">All Statuses</option>
                <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="accepted" <?= $statusFilter === 'accepted' ? 'selected' : '' ?>>Accepted</option>
                <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
            </select>
            <select name="type" class="filter-select">
                <option value="">All Types</option>
                <option value="location" <?= $typeFilter === 'location' ? 'selected' : '' ?>>Location</option>
                <option value="route" <?= $typeFilter === 'route' ? 'selected' : '' ?>>Route</option>
                <option value="vehicle" <?= $typeFilter === 'vehicle' ? 'selected' : '' ?>>Vehicle</option>
            </select>
            <button type="submit" class="filter-btn">Filter</button>
            <a href="<?= BASE_URL ?>/pages/admin/contributions.php" class="filter-btn secondary">Reset</a>
        </form>

        <div class="data-table-wrapper">
            <?php if (empty($contributions)): ?>
                <div class="empty-state">
                    <div class="empty-icon"><i class="fa-duotone fa-solid fa-clipboard-list"></i></div>
                    <h3>No contributions found</h3>
                    <p>Contributions from agents will appear here for review.</p>
                </div>
            <?php else: ?>
                <div class="table-scroll">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Type</th>
                                <th>Entry</th>
                                <th>Proposed By</th>
                                <th>Status</th>
                                <th>Proposed At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($contributions as $c): ?>
                                <tr>
                                    <td>#<?= $c['contribution_id'] ?></td>
                                    <td>
                                        <span
                                            class="badge badge-<?= $c['type'] === 'location' ? 'stop' : ($c['type'] === 'route' ? 'info' : 'landmark') ?>">
                                            <?= sanitize($c['type']) ?>
                                        </span>
                                    </td>
                                    <td><strong><?= sanitize($c['entry_name']) ?></strong></td>
                                    <td><?= sanitize($c['agent_name'] ?? '—') ?></td>
                                    <td>
                                        <span
                                            class="badge badge-<?= $c['status'] === 'accepted' ? 'accepted' : ($c['status'] === 'rejected' ? 'rejected' : 'pending') ?>">
                                            <?= sanitize($c['status']) ?>
                                        </span>
                                    </td>
                                    <td style="font-size:0.8125rem;"><?= timeAgo($c['proposed_at']) ?></td>
                                    <td>
                                        <div class="actions">
                                            <button class="btn btn-sm btn-outline"
                                                onclick='viewDetails(<?= json_encode($c) ?>)'>View</button>
                                            <?php if ($c['status'] === 'pending'): ?>
                                                <button class="btn btn-sm btn-success"
                                                    onclick="acceptContribution(<?= $c['contribution_id'] ?>)">Accept</button>
                                                <button class="btn btn-sm btn-danger"
                                                    onclick="rejectContribution(<?= $c['contribution_id'] ?>)">Reject</button>
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

<!-- Details Modal -->
<div class="modal-overlay" id="detailsModal">
    <div class="modal" style="max-width: 550px;">
        <div class="modal-header">
            <h2>Contribution Details</h2>
            <button class="modal-close"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body" id="detailsBody">
            <!-- filled by JS -->
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="AdminUtils.closeFormModal('#detailsModal')">Close</button>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal-overlay" id="rejectModal">
    <div class="modal" style="max-width: 450px;">
        <div class="modal-header">
            <h2>Reject Contribution</h2>
            <button class="modal-close"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label for="rejectReason">Reason for rejection *</label>
                <textarea id="rejectReason" class="form-textarea" required
                    placeholder="Explain why this contribution is being rejected..."></textarea>
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
    let rejectingId = null;

    function viewDetails(c) {
        const body = document.getElementById('detailsBody');
        body.innerHTML = `
        <div class="detail-row"><span class="detail-label">ID</span><span class="detail-value">#${c.contribution_id}</span></div>
        <div class="detail-row"><span class="detail-label">Type</span><span class="detail-value">${SawariUtils.statusBadge(c.type)}</span></div>
        <div class="detail-row"><span class="detail-label">Entry</span><span class="detail-value"><strong>${SawariUtils.escapeHTML(c.entry_name)}</strong></span></div>
        <div class="detail-row"><span class="detail-label">Proposed By</span><span class="detail-value">${SawariUtils.escapeHTML(c.agent_name || '—')}</span></div>
        <div class="detail-row"><span class="detail-label">Status</span><span class="detail-value">${SawariUtils.statusBadge(c.status)}</span></div>
        <div class="detail-row"><span class="detail-label">Proposed At</span><span class="detail-value">${SawariUtils.formatDate(c.proposed_at)}</span></div>
        ${c.responded_at ? `<div class="detail-row"><span class="detail-label">Responded At</span><span class="detail-value">${SawariUtils.formatDate(c.responded_at)}</span></div>` : ''}
        ${c.rejection_reason ? `<div class="detail-row"><span class="detail-label">Rejection Reason</span><span class="detail-value">${SawariUtils.escapeHTML(c.rejection_reason)}</span></div>` : ''}
        ${c.reviewer_name ? `<div class="detail-row"><span class="detail-label">Reviewed By</span><span class="detail-value">${SawariUtils.escapeHTML(c.reviewer_name)}</span></div>` : ''}
    `;
        AdminUtils.openFormModal('#detailsModal');
    }

    function acceptContribution(id) {
        if (!SawariUtils.confirmAction('Accept this contribution? The associated entry will be approved.')) return;

        AdminUtils.apiAction(`${BASE}/api/contributions/respond.php`, {
            contribution_id: id,
            action: 'accept'
        }, {
            onSuccess: () => location.reload()
        });
    }

    function rejectContribution(id) {
        rejectingId = id;
        document.getElementById('rejectReason').value = '';
        AdminUtils.openFormModal('#rejectModal');
    }

    function submitReject() {
        const reason = document.getElementById('rejectReason').value.trim();
        if (!reason) { SawariUtils.showToast('Please provide a rejection reason', 'warning'); return; }

        AdminUtils.apiAction(`${BASE}/api/contributions/respond.php`, {
            contribution_id: rejectingId,
            action: 'reject',
            rejection_reason: reason
        }, {
            onSuccess: () => {
                AdminUtils.closeFormModal('#rejectModal');
                location.reload();
            }
        });
    }
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>