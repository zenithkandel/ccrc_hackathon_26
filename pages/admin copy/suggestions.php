<?php
/**
 * Admin: Suggestions Management — Sawari
 * 
 * View, filter, and respond to public suggestions/feedback.
 * Supports marking as Reviewed or Resolved.
 */

$pageTitle = 'Suggestions — Admin — Sawari';
$pageCss = ['admin.css'];
$bodyClass = 'admin-page';
$pageJs = ['admin.js'];
$currentPage = 'suggestions';

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

if ($statusFilter && in_array($statusFilter, ['pending', 'reviewed', 'resolved'])) {
    $where[] = 's.status = :status';
    $params['status'] = $statusFilter;
}

if ($typeFilter && in_array($typeFilter, ['complaint', 'suggestion', 'correction', 'appreciation'])) {
    $where[] = 's.type = :type';
    $params['type'] = $typeFilter;
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM suggestions s $whereClause");
$countStmt->execute($params);
$totalItems = (int) $countStmt->fetchColumn();
$pagination = paginate($totalItems, $page);

$stmt = $pdo->prepare("
    SELECT s.*,
           r.name AS route_name,
           v.name AS vehicle_name,
           adm.name AS reviewer_name
    FROM suggestions s
    LEFT JOIN routes r ON s.related_route_id = r.route_id
    LEFT JOIN vehicles v ON s.related_vehicle_id = v.vehicle_id
    LEFT JOIN admins adm ON s.reviewed_by = adm.admin_id
    $whereClause
    ORDER BY s.submitted_at DESC
    LIMIT :limit OFFSET :offset
");
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->bindValue('limit', $pagination['perPage'], PDO::PARAM_INT);
$stmt->bindValue('offset', $pagination['offset'], PDO::PARAM_INT);
$stmt->execute();
$suggestions = $stmt->fetchAll();

// Aggregate stats
$statsQuery = $pdo->query("
    SELECT 
        COUNT(*) AS total,
        ROUND(AVG(rating), 1) AS avg_rating,
        SUM(CASE WHEN type = 'complaint' THEN 1 ELSE 0 END) AS complaints,
        SUM(CASE WHEN type = 'suggestion' THEN 1 ELSE 0 END) AS suggestions_count,
        SUM(CASE WHEN type = 'correction' THEN 1 ELSE 0 END) AS corrections,
        SUM(CASE WHEN type = 'appreciation' THEN 1 ELSE 0 END) AS appreciations
    FROM suggestions
");
$stats = $statsQuery->fetch();

$filterParams = http_build_query(array_filter(['status' => $statusFilter, 'type' => $typeFilter]));
$baseUrl = BASE_URL . '/pages/admin/suggestions.php' . ($filterParams ? '?' . $filterParams : '');
?>

<div class="admin-layout">
    <?php require_once __DIR__ . '/../../includes/admin-sidebar.php'; ?>

    <div class="admin-content">
        <div class="admin-page-header">
            <h1>Suggestions & Feedback</h1>
        </div>

        <!-- Stats -->
        <div class="stats-grid" style="margin-bottom: var(--space-lg);">
            <div class="stat-card">
                <div class="stat-icon info"><i class="fa-duotone fa-solid fa-comments"></i></div>
                <div class="stat-info">
                    <h3><?= $stats['total'] ?? 0 ?></h3>
                    <p>Total Feedback</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon warning"><i class="fa-sharp-duotone fa-solid fa-star"></i></div>
                <div class="stat-info">
                    <h3><?= $stats['avg_rating'] ?? 'N/A' ?></h3>
                    <p>Average Rating</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon danger"><i class="fa-solid fa-circle-exclamation"></i></div>
                <div class="stat-info">
                    <h3><?= $stats['complaints'] ?? 0 ?></h3>
                    <p>Complaints</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon success"><i class="fa-solid fa-heart" style="color:var(--color-success);"></i>
                </div>
                <div class="stat-info">
                    <h3><?= $stats['appreciations'] ?? 0 ?></h3>
                    <p>Appreciations</p>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <form class="filter-bar" method="GET">
            <select name="status" class="filter-select">
                <option value="">All Statuses</option>
                <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="reviewed" <?= $statusFilter === 'reviewed' ? 'selected' : '' ?>>Reviewed</option>
                <option value="resolved" <?= $statusFilter === 'resolved' ? 'selected' : '' ?>>Resolved</option>
            </select>
            <select name="type" class="filter-select">
                <option value="">All Types</option>
                <option value="complaint" <?= $typeFilter === 'complaint' ? 'selected' : '' ?>>Complaint</option>
                <option value="suggestion" <?= $typeFilter === 'suggestion' ? 'selected' : '' ?>>Suggestion</option>
                <option value="correction" <?= $typeFilter === 'correction' ? 'selected' : '' ?>>Correction</option>
                <option value="appreciation" <?= $typeFilter === 'appreciation' ? 'selected' : '' ?>>Appreciation</option>
            </select>
            <button type="submit" class="filter-btn">Filter</button>
            <a href="<?= BASE_URL ?>/pages/admin/suggestions.php" class="filter-btn secondary">Reset</a>
        </form>

        <div class="data-table-wrapper">
            <?php if (empty($suggestions)): ?>
                <div class="empty-state">
                    <div class="empty-icon"><i class="fa-duotone fa-solid fa-comments"></i></div>
                    <h3>No suggestions found</h3>
                    <p>Public feedback will appear here.</p>
                </div>
            <?php else: ?>
                <div class="table-scroll">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Message</th>
                                <th>Rating</th>
                                <th>Related</th>
                                <th>Status</th>
                                <th>Submitted</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($suggestions as $s): ?>
                                <tr>
                                    <td>
                                        <?php
                                        $typeBadge = match ($s['type']) {
                                            'complaint' => 'rejected',
                                            'appreciation' => 'approved',
                                            'correction' => 'info',
                                            default => 'pending',
                                        };
                                        ?>
                                        <span class="badge badge-<?= $typeBadge ?>"><?= sanitize($s['type']) ?></span>
                                    </td>
                                    <td class="truncate"><?= sanitize(mb_strimwidth($s['message'], 0, 80, '...')) ?></td>
                                    <td>
                                        <?php if ($s['rating']): ?>
                                            <?= str_repeat('<i class="fa-sharp-duotone fa-solid fa-star" style="color:#f59e0b;"></i>', (int) $s['rating']) ?>
                                        <?php else: ?>
                                            <span style="color:var(--color-text-muted)">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-size:0.8125rem;">
                                        <?= $s['route_name'] ? '<i class="fa-duotone fa-solid fa-route"></i> ' . sanitize($s['route_name']) : '' ?>
                                        <?= $s['vehicle_name'] ? '<i class="fa-duotone fa-solid fa-bus"></i> ' . sanitize($s['vehicle_name']) : '' ?>
                                        <?= !$s['route_name'] && !$s['vehicle_name'] ? '<span style="color:var(--color-text-muted)">—</span>' : '' ?>
                                    </td>
                                    <td>
                                        <span
                                            class="badge badge-<?= $s['status'] === 'resolved' ? 'approved' : ($s['status'] === 'reviewed' ? 'info' : 'pending') ?>">
                                            <?= sanitize($s['status']) ?>
                                        </span>
                                    </td>
                                    <td style="font-size:0.8125rem;"><?= timeAgo($s['submitted_at']) ?></td>
                                    <td>
                                        <div class="actions">
                                            <button class="btn btn-sm btn-outline"
                                                onclick='viewSuggestion(<?= json_encode($s) ?>)'>View</button>
                                            <?php if ($s['status'] === 'pending'): ?>
                                                <button class="btn btn-sm btn-primary"
                                                    onclick="updateStatus(<?= $s['suggestion_id'] ?>, 'reviewed')">Review</button>
                                            <?php endif; ?>
                                            <?php if ($s['status'] !== 'resolved'): ?>
                                                <button class="btn btn-sm btn-success"
                                                    onclick="updateStatus(<?= $s['suggestion_id'] ?>, 'resolved')">Resolve</button>
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

<!-- View Suggestion Modal -->
<div class="modal-overlay" id="viewModal">
    <div class="modal" style="max-width: 550px;">
        <div class="modal-header">
            <h2>Suggestion Details</h2>
            <button class="modal-close"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body" id="viewBody">
            <!-- filled by JS -->
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="AdminUtils.closeFormModal('#viewModal')">Close</button>
        </div>
    </div>
</div>

<script>
    const BASE = '<?= BASE_URL ?>';

    function viewSuggestion(s) {
        const stars = s.rating ? '<i class="fa-sharp-duotone fa-solid fa-star" style="color:#f59e0b;"></i>'.repeat(parseInt(s.rating)) : 'N/A';
        const body = document.getElementById('viewBody');
        body.innerHTML = `
        <div class="detail-row"><span class="detail-label">Type</span><span class="detail-value">${SawariUtils.escapeHTML(s.type)}</span></div>
        <div class="detail-row"><span class="detail-label">Rating</span><span class="detail-value">${stars}</span></div>
        <div class="detail-row"><span class="detail-label">Message</span><span class="detail-value">${SawariUtils.escapeHTML(s.message)}</span></div>
        <div class="detail-row"><span class="detail-label">Status</span><span class="detail-value">${SawariUtils.statusBadge(s.status)}</span></div>
        ${s.route_name ? `<div class="detail-row"><span class="detail-label">Related Route</span><span class="detail-value"><i class="fa-duotone fa-solid fa-route"></i> ${SawariUtils.escapeHTML(s.route_name)}</span></div>` : ''}
        ${s.vehicle_name ? `<div class="detail-row"><span class="detail-label">Related Vehicle</span><span class="detail-value"><i class="fa-duotone fa-solid fa-bus"></i> ${SawariUtils.escapeHTML(s.vehicle_name)}</span></div>` : ''}
        <div class="detail-row"><span class="detail-label">IP Address</span><span class="detail-value">${SawariUtils.escapeHTML(s.ip_address || 'N/A')}</span></div>
        <div class="detail-row"><span class="detail-label">Submitted</span><span class="detail-value">${SawariUtils.formatDate(s.submitted_at)}</span></div>
        ${s.reviewer_name ? `<div class="detail-row"><span class="detail-label">Reviewed By</span><span class="detail-value">${SawariUtils.escapeHTML(s.reviewer_name)}</span></div>` : ''}
        ${s.reviewed_at ? `<div class="detail-row"><span class="detail-label">Reviewed At</span><span class="detail-value">${SawariUtils.formatDate(s.reviewed_at)}</span></div>` : ''}
    `;
        AdminUtils.openFormModal('#viewModal');
    }

    function updateStatus(id, status) {
        const label = status === 'reviewed' ? 'Mark as reviewed' : 'Mark as resolved';
        if (!SawariUtils.confirmAction(`${label}?`)) return;

        AdminUtils.apiAction(`${BASE}/api/suggestions/respond.php`, {
            suggestion_id: id,
            status: status
        }, {
            onSuccess: () => location.reload()
        });
    }
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>