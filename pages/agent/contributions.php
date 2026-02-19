<?php
/**
 * Agent: My Contributions — Sawari
 * 
 * Combined view of all contributions (locations, routes, vehicles) by this agent.
 * Filter by type and status, see rejection reasons.
 */

$pageTitle = 'My Contributions — Agent — Sawari';
$pageCss = ['admin.css', 'agent.css'];
$bodyClass = 'admin-page agent-page';
$pageJs = ['agent/agent.js'];
$currentPage = 'contributions';

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/validation.php';

requireAuth('agent');

$pdo = getDBConnection();
$agentId = getCurrentUserId();

// ─── Filters ─────────────────────────────────────────────
$statusFilter = getParam('status', '');
$typeFilter = getParam('type', '');
$page = getIntParam('page', 1);

$where = ['c.proposed_by = :agent_id'];
$params = ['agent_id' => $agentId];

if ($statusFilter && in_array($statusFilter, ['pending', 'accepted', 'rejected'])) {
    $where[] = 'c.status = :status';
    $params['status'] = $statusFilter;
}

if ($typeFilter && in_array($typeFilter, ['location', 'route', 'vehicle'])) {
    $where[] = 'c.type = :type';
    $params['type'] = $typeFilter;
}

$whereClause = 'WHERE ' . implode(' AND ', $where);

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM contributions c $whereClause");
$countStmt->execute($params);
$totalItems = (int) $countStmt->fetchColumn();
$pagination = paginate($totalItems, $page);

$stmt = $pdo->prepare("
    SELECT c.*,
        adm.name AS accepted_by_name,
        CASE c.type
            WHEN 'location' THEN (SELECT name FROM locations WHERE location_id = c.associated_entry_id)
            WHEN 'route' THEN (SELECT name FROM routes WHERE route_id = c.associated_entry_id)
            WHEN 'vehicle' THEN (SELECT name FROM vehicles WHERE vehicle_id = c.associated_entry_id)
        END AS entry_name
    FROM contributions c
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

// Stats
$statsStmt = $pdo->prepare("
    SELECT status, COUNT(*) AS cnt
    FROM contributions
    WHERE proposed_by = :aid
    GROUP BY status
");
$statsStmt->execute(['aid' => $agentId]);
$statsRaw = $statsStmt->fetchAll();
$stats = ['pending' => 0, 'accepted' => 0, 'rejected' => 0];
foreach ($statsRaw as $s) {
    $stats[$s['status']] = (int) $s['cnt'];
}
$totalAll = array_sum($stats);

$filterParams = http_build_query(array_filter(['status' => $statusFilter, 'type' => $typeFilter]));
$baseUrl = BASE_URL . '/pages/agent/contributions.php' . ($filterParams ? '?' . $filterParams : '');
?>

<div class="admin-layout">
    <?php require_once __DIR__ . '/../../includes/agent-sidebar.php'; ?>

    <div class="admin-content">
        <div class="admin-page-header">
            <h1>My Contributions</h1>
        </div>

        <!-- Stats -->
        <div class="stats-grid" style="margin-bottom:var(--space-lg);">
            <div class="stat-card">
                <div class="stat-icon"><i class="fa-duotone fa-solid fa-chart-pie"></i></div>
                <div class="stat-value"><?= $totalAll ?></div>
                <div class="stat-label">Total</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fa-duotone fa-solid fa-hourglass-half"></i></div>
                <div class="stat-value"><?= $stats['pending'] ?></div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fa-duotone fa-solid fa-circle-check"></i></div>
                <div class="stat-value"><?= $stats['accepted'] ?></div>
                <div class="stat-label">Accepted</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fa-duotone fa-solid fa-circle-xmark"></i></div>
                <div class="stat-value"><?= $stats['rejected'] ?></div>
                <div class="stat-label">Rejected</div>
            </div>
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
            <a href="<?= BASE_URL ?>/pages/agent/contributions.php" class="filter-btn secondary">Reset</a>
        </form>

        <div class="data-table-wrapper">
            <?php if (empty($contributions)): ?>
                <div class="empty-state">
                    <div class="empty-icon"><i class="fa-duotone fa-solid fa-clipboard-list"></i></div>
                    <h3>No contributions found</h3>
                    <p>Start contributing locations, routes, and vehicles!</p>
                </div>
            <?php else: ?>
                <div class="table-scroll">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Entry Name</th>
                                <th>Status</th>
                                <th>Proposed</th>
                                <th>Responded</th>
                                <th>Rejection Reason</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($contributions as $c): ?>
                                <tr>
                                    <td><span class="badge badge-info"><?= sanitize($c['type']) ?></span></td>
                                    <td><?= sanitize($c['entry_name'] ?? 'Deleted entry') ?></td>
                                    <td>
                                        <?php
                                        $badgeClass = match ($c['status']) {
                                            'accepted' => 'badge-approved',
                                            'rejected' => 'badge-rejected',
                                            default => 'badge-pending',
                                        };
                                        ?>
                                        <span class="badge <?= $badgeClass ?>"><?= sanitize($c['status']) ?></span>
                                    </td>
                                    <td style="font-size:0.8125rem;"><?= formatDateTime($c['proposed_at'], 'M d, Y') ?></td>
                                    <td style="font-size:0.8125rem;">
                                        <?php if ($c['responded_at']): ?>
                                            <?= formatDateTime($c['responded_at'], 'M d, Y') ?>
                                            <?php if ($c['accepted_by_name']): ?>
                                                <div style="font-size:0.75rem; color:var(--color-text-muted);">by
                                                    <?= sanitize($c['accepted_by_name']) ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span style="color:var(--color-text-muted);">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-size:0.8125rem;">
                                        <?php if ($c['rejection_reason']): ?>
                                            <span style="color: var(--color-danger);"><?= sanitize($c['rejection_reason']) ?></span>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
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

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>