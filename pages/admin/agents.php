<?php
/**
 * Admin: Agent Management — Sawari
 * 
 * View all agents, their profiles, contribution history, and stats.
 */

$pageTitle = 'Agents — Admin — Sawari';
$pageCss = ['admin.css'];
$bodyClass = 'admin-page';
$pageJs = ['admin.js'];
$currentPage = 'agents';

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/validation.php';

requireAuth('admin');

$pdo = getDBConnection();

// ─── Filters ─────────────────────────────────────────────
$search = getParam('search', '');
$page = getIntParam('page', 1);

$where = [];
$params = [];

if ($search) {
    $where[] = '(a.name LIKE :search OR a.email LIKE :search2)';
    $params['search'] = '%' . $search . '%';
    $params['search2'] = '%' . $search . '%';
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM agents a $whereClause");
$countStmt->execute($params);
$totalItems = (int) $countStmt->fetchColumn();
$pagination = paginate($totalItems, $page);

$stmt = $pdo->prepare("
    SELECT a.agent_id, a.name, a.email, a.phone_number, a.image_path,
           a.joined_at, a.last_login, a.contributions_summary
    FROM agents a
    $whereClause
    ORDER BY a.joined_at DESC
    LIMIT :limit OFFSET :offset
");
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->bindValue('limit', $pagination['perPage'], PDO::PARAM_INT);
$stmt->bindValue('offset', $pagination['offset'], PDO::PARAM_INT);
$stmt->execute();
$agents = $stmt->fetchAll();

$filterParams = http_build_query(array_filter(['search' => $search]));
$baseUrl = BASE_URL . '/pages/admin/agents.php' . ($filterParams ? '?' . $filterParams : '');
?>

<div class="admin-layout">
    <?php require_once __DIR__ . '/../../includes/admin-sidebar.php'; ?>

    <div class="admin-content">
        <div class="admin-page-header">
            <h1>Agents Management</h1>
        </div>

        <form class="filter-bar" method="GET">
            <input type="text" name="search" class="filter-input" placeholder="Search by name or email..."
                value="<?= sanitize($search) ?>">
            <button type="submit" class="filter-btn">Search</button>
            <a href="<?= BASE_URL ?>/pages/admin/agents.php" class="filter-btn secondary">Reset</a>
        </form>

        <div class="data-table-wrapper">
            <?php if (empty($agents)): ?>
                <div class="empty-state">
                    <div class="empty-icon"><i class="fa-duotone fa-solid fa-users"></i></div>
                    <h3>No agents found</h3>
                    <p>Registered agents will appear here.</p>
                </div>
            <?php else: ?>
                <div class="table-scroll">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Joined</th>
                                <th>Contributions</th>
                                <th>Last Login</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($agents as $agent): ?>
                                <?php
                                $summary = json_decode($agent['contributions_summary'] ?? '{}', true);
                                $totalContribs = ($summary['location'] ?? 0) + ($summary['route'] ?? 0) + ($summary['vehicle'] ?? 0);
                                ?>
                                <tr>
                                    <td>
                                        <div style="display:flex; align-items:center; gap:8px;">
                                            <?php if ($agent['image_path']): ?>
                                                <img src="<?= BASE_URL ?>/<?= sanitize($agent['image_path']) ?>" alt=""
                                                    style="width:32px;height:32px;border-radius:50%;object-fit:cover;">
                                            <?php else: ?>
                                                <div
                                                    style="width:32px;height:32px;border-radius:50%;background:var(--color-primary-light);display:flex;align-items:center;justify-content:center;font-size:0.875rem;">
                                                    <?= strtoupper(substr($agent['name'], 0, 1)) ?>
                                                </div>
                                            <?php endif; ?>
                                            <strong><?= sanitize($agent['name']) ?></strong>
                                        </div>
                                    </td>
                                    <td style="font-size:0.875rem;"><?= sanitize($agent['email']) ?></td>
                                    <td style="font-size:0.875rem;"><?= sanitize($agent['phone_number'] ?: '—') ?></td>
                                    <td style="font-size:0.8125rem;"><?= formatDateTime($agent['joined_at'], 'M d, Y') ?></td>
                                    <td>
                                        <span class="badge badge-info"><?= $totalContribs ?> total</span>
                                        <?php if ($totalContribs > 0): ?>
                                            <div style="font-size:0.75rem; color:var(--color-text-muted); margin-top:2px;">
                                                <i class="fa-duotone fa-solid fa-location-dot"></i><?= $summary['location'] ?? 0 ?>
                                                <i class="fa-duotone fa-solid fa-route"></i><?= $summary['route'] ?? 0 ?>
                                                <i class="fa-duotone fa-solid fa-bus"></i><?= $summary['vehicle'] ?? 0 ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-size:0.8125rem;">
                                        <?= $agent['last_login'] ? timeAgo($agent['last_login']) : '<span style="color:var(--color-text-muted)">Never</span>' ?>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <button class="btn btn-sm btn-outline"
                                                onclick='viewAgent(<?= json_encode($agent) ?>)'>View</button>
                                            <button class="btn btn-sm btn-primary"
                                                onclick="viewContributions(<?= $agent['agent_id'] ?>)">History</button>
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

<!-- View Agent Modal -->
<div class="modal-overlay" id="agentModal">
    <div class="modal" style="max-width: 500px;">
        <div class="modal-header">
            <h2>Agent Profile</h2>
            <button class="modal-close"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body" id="agentBody">
            <!-- filled by JS -->
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="AdminUtils.closeFormModal('#agentModal')">Close</button>
        </div>
    </div>
</div>

<!-- Contribution History Modal -->
<div class="modal-overlay" id="historyModal">
    <div class="modal" style="max-width: 650px;">
        <div class="modal-header">
            <h2>Contribution History</h2>
            <button class="modal-close"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body" id="historyBody">
            <div style="text-align:center; padding:var(--space-xl);">
                <div class="spinner" style="width:32px;height:32px;border-width:3px;margin:0 auto;"></div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="AdminUtils.closeFormModal('#historyModal')">Close</button>
        </div>
    </div>
</div>

<script>
    const BASE = '<?= BASE_URL ?>';

    function viewAgent(agent) {
        const summary = JSON.parse(agent.contributions_summary || '{}');
        const total = (summary.location || 0) + (summary.route || 0) + (summary.vehicle || 0);

        let avatarHtml = '';
        if (agent.image_path) {
            avatarHtml = `<img src="${BASE}/${SawariUtils.escapeHTML(agent.image_path)}" style="width:80px;height:80px;border-radius:50%;object-fit:cover;margin:0 auto var(--space-md);display:block;">`;
        } else {
            avatarHtml = `<div style="width:80px;height:80px;border-radius:50%;background:var(--color-primary-light);display:flex;align-items:center;justify-content:center;margin:0 auto var(--space-md);font-size:2rem;color:var(--color-primary);">${agent.name.charAt(0).toUpperCase()}</div>`;
        }

        const body = document.getElementById('agentBody');
        body.innerHTML = `
        ${avatarHtml}
        <div class="detail-row"><span class="detail-label">Name</span><span class="detail-value"><strong>${SawariUtils.escapeHTML(agent.name)}</strong></span></div>
        <div class="detail-row"><span class="detail-label">Email</span><span class="detail-value">${SawariUtils.escapeHTML(agent.email)}</span></div>
        <div class="detail-row"><span class="detail-label">Phone</span><span class="detail-value">${SawariUtils.escapeHTML(agent.phone_number || '—')}</span></div>
        <div class="detail-row"><span class="detail-label">Joined</span><span class="detail-value">${SawariUtils.formatDate(agent.joined_at)}</span></div>
        <div class="detail-row"><span class="detail-label">Last Login</span><span class="detail-value">${agent.last_login ? SawariUtils.formatDate(agent.last_login) : 'Never'}</span></div>
        <div class="detail-row"><span class="detail-label">Total Contributions</span><span class="detail-value">${total}</span></div>
        <div class="detail-row"><span class="detail-label">Breakdown</span><span class="detail-value"><i class="fa-duotone fa-solid fa-location-dot"></i> ${summary.location || 0} locations, <i class="fa-duotone fa-solid fa-route"></i> ${summary.route || 0} routes, <i class="fa-duotone fa-solid fa-bus"></i> ${summary.vehicle || 0} vehicles</span></div>
    `;
        AdminUtils.openFormModal('#agentModal');
    }

    async function viewContributions(agentId) {
        AdminUtils.openFormModal('#historyModal');
        const body = document.getElementById('historyBody');
        body.innerHTML = '<div style="text-align:center;padding:var(--space-xl);"><div class="spinner" style="width:32px;height:32px;border-width:3px;margin:0 auto;"></div></div>';

        try {
            const data = await SawariUtils.apiFetch(`api/contributions/read.php?agent_id=${agentId}&per_page=50`);
            const contributions = data.data || data.contributions || [];

            if (contributions.length === 0) {
                body.innerHTML = '<div class="empty-state"><p>No contributions found for this agent.</p></div>';
                return;
            }

            let html = `<table class="data-table" style="margin:0;">
            <thead><tr><th>Type</th><th>Status</th><th>Proposed</th><th>Rejection Reason</th></tr></thead>
            <tbody>`;

            contributions.forEach(c => {
                html += `<tr>
                <td>${SawariUtils.statusBadge(c.type)}</td>
                <td>${SawariUtils.statusBadge(c.status)}</td>
                <td style="font-size:0.8125rem;">${SawariUtils.formatDate(c.proposed_at)}</td>
                <td style="font-size:0.8125rem;">${SawariUtils.escapeHTML(c.rejection_reason || '—')}</td>
            </tr>`;
            });

            html += '</tbody></table>';
            body.innerHTML = html;
        } catch (err) {
            body.innerHTML = '<div class="empty-state"><p>Failed to load contributions.</p></div>';
        }
    }
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>