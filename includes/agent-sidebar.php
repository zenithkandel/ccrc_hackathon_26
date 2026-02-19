<?php
/**
 * Agent Sidebar — Sawari
 * 
 * Reusable sidebar navigation for all agent pages.
 * Set $currentPage before including to highlight the active link.
 */

$currentPage = $currentPage ?? 'dashboard';

// Get agent's pending contributions count
$pdo = getDBConnection();
$pendingStmt = $pdo->prepare("SELECT COUNT(*) FROM contributions WHERE proposed_by = :aid AND status = 'pending'");
$pendingStmt->execute(['aid' => getCurrentUserId()]);
$pendingCount = (int) $pendingStmt->fetchColumn();
?>

<aside class="admin-sidebar" id="adminSidebar">
    <div class="sidebar-header">
        <h3>Agent Panel</h3>
    </div>
    <ul class="sidebar-nav">
        <li>
            <a href="<?= BASE_URL ?>/pages/agent/dashboard.php"
                class="sidebar-link <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
                <span class="sidebar-icon"><i class="fa-duotone fa-solid fa-chart-pie"></i></span>
                Dashboard
            </a>
        </li>
        <li>
            <a href="<?= BASE_URL ?>/pages/agent/locations.php"
                class="sidebar-link <?= $currentPage === 'locations' ? 'active' : '' ?>">
                <span class="sidebar-icon"><i class="fa-duotone fa-solid fa-location-dot"></i></span>
                Locations
            </a>
        </li>
        <li>
            <a href="<?= BASE_URL ?>/pages/agent/routes.php"
                class="sidebar-link <?= $currentPage === 'routes' ? 'active' : '' ?>">
                <span class="sidebar-icon"><i class="fa-duotone fa-solid fa-route"></i></span>
                Routes
            </a>
        </li>
        <li>
            <a href="<?= BASE_URL ?>/pages/agent/vehicles.php"
                class="sidebar-link <?= $currentPage === 'vehicles' ? 'active' : '' ?>">
                <span class="sidebar-icon"><i class="fa-duotone fa-solid fa-bus"></i></span>
                Vehicles
            </a>
        </li>
        <li>
            <a href="<?= BASE_URL ?>/pages/agent/contributions.php"
                class="sidebar-link <?= $currentPage === 'contributions' ? 'active' : '' ?>">
                <span class="sidebar-icon"><i class="fa-duotone fa-solid fa-clipboard-list"></i></span>
                My Contributions
                <?php if ($pendingCount > 0): ?>
                    <span class="sidebar-badge"><?= $pendingCount ?></span>
                <?php endif; ?>
            </a>
        </li>
        <li>
            <a href="<?= BASE_URL ?>/pages/agent/profile.php"
                class="sidebar-link <?= $currentPage === 'profile' ? 'active' : '' ?>">
                <span class="sidebar-icon"><i class="fa-duotone fa-solid fa-user"></i></span>
                Profile
            </a>
        </li>
    </ul>
</aside>

<div class="sidebar-overlay" id="sidebarOverlay"></div>
<button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar"><i class="fa-solid fa-bars"></i></button>