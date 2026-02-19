<?php
/**
 * Admin Sidebar — Sawari
 * 
 * Reusable sidebar navigation for all admin pages.
 * Set $currentPage before including to highlight the active link.
 */

$currentPage = $currentPage ?? 'dashboard';

// Get pending counts for badges
$pdo = getDBConnection();

$pendingContributions = $pdo->query("SELECT COUNT(*) FROM contributions WHERE status = 'pending'")->fetchColumn();
$pendingSuggestions = $pdo->query("SELECT COUNT(*) FROM suggestions WHERE status = 'pending'")->fetchColumn();
?>

<aside class="admin-sidebar" id="adminSidebar">
    <div class="sidebar-header">
        <h3>Admin Panel</h3>
    </div>
    <ul class="sidebar-nav">
        <li>
            <a href="<?= BASE_URL ?>/pages/admin/dashboard.php"
                class="sidebar-link <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
                <span class="sidebar-icon"><i class="fa-duotone fa-solid fa-chart-pie"></i></span>
                Dashboard
            </a>
        </li>
        <li>
            <a href="<?= BASE_URL ?>/pages/admin/locations.php"
                class="sidebar-link <?= $currentPage === 'locations' ? 'active' : '' ?>">
                <span class="sidebar-icon"><i class="fa-duotone fa-solid fa-location-dot"></i></span>
                Locations
            </a>
        </li>
        <li>
            <a href="<?= BASE_URL ?>/pages/admin/routes.php"
                class="sidebar-link <?= $currentPage === 'routes' ? 'active' : '' ?>">
                <span class="sidebar-icon"><i class="fa-duotone fa-solid fa-route"></i></span>
                Routes
            </a>
        </li>
        <li>
            <a href="<?= BASE_URL ?>/pages/admin/vehicles.php"
                class="sidebar-link <?= $currentPage === 'vehicles' ? 'active' : '' ?>">
                <span class="sidebar-icon"><i class="fa-duotone fa-solid fa-bus"></i></span>
                Vehicles
            </a>
        </li>
        <li>
            <a href="<?= BASE_URL ?>/pages/admin/contributions.php"
                class="sidebar-link <?= $currentPage === 'contributions' ? 'active' : '' ?>">
                <span class="sidebar-icon"><i class="fa-duotone fa-solid fa-clipboard-list"></i></span>
                Contributions
                <?php if ($pendingContributions > 0): ?>
                    <span class="sidebar-badge"><?= $pendingContributions ?></span>
                <?php endif; ?>
            </a>
        </li>
        <li>
            <a href="<?= BASE_URL ?>/pages/admin/alerts.php"
                class="sidebar-link <?= $currentPage === 'alerts' ? 'active' : '' ?>">
                <span class="sidebar-icon"><i class="fa-duotone fa-solid fa-triangle-exclamation"></i></span>
                Alerts
            </a>
        </li>
        <li>
            <a href="<?= BASE_URL ?>/pages/admin/suggestions.php"
                class="sidebar-link <?= $currentPage === 'suggestions' ? 'active' : '' ?>">
                <span class="sidebar-icon"><i class="fa-duotone fa-solid fa-comments"></i></span>
                Suggestions
                <?php if ($pendingSuggestions > 0): ?>
                    <span class="sidebar-badge"><?= $pendingSuggestions ?></span>
                <?php endif; ?>
            </a>
        </li>
        <li>
            <a href="<?= BASE_URL ?>/pages/admin/agents.php"
                class="sidebar-link <?= $currentPage === 'agents' ? 'active' : '' ?>">
                <span class="sidebar-icon"><i class="fa-duotone fa-solid fa-users"></i></span>
                Agents
            </a>
        </li>
    </ul>
</aside>

<div class="sidebar-overlay" id="sidebarOverlay"></div>
<button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar"><i class="fa-solid fa-bars"></i></button>