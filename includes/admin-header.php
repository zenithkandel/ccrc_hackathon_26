<?php
/**
 * SAWARI — Admin Layout Header
 * 
 * Opens the admin page HTML structure: doctype, head, body,
 * top header bar, sidebar navigation, and main content area.
 * 
 * Required variables before including this file:
 *   $pageTitle   — (string) Page title for <title> and header
 *   $currentPage — (string) Active sidebar nav item key
 * 
 * Optional:
 *   $pageActions — (string) HTML for action buttons in page header
 *   $hidePageHeader — (bool) If true, don't render the page header
 */

$pageTitle = $pageTitle ?? 'Admin';
$currentPage = $currentPage ?? '';
$pageActions = $pageActions ?? '';
$hidePageHeader = $hidePageHeader ?? false;

$adminName = e($_SESSION['admin_name'] ?? 'Admin');
$adminRole = e($_SESSION['admin_role'] ?? 'moderator');
$adminInitial = strtoupper(substr($adminName, 0, 1));

// Sidebar navigation structure
$navSections = [
    'OVERVIEW' => [
        'dashboard' => ['label' => 'Dashboard', 'icon' => 'grid', 'url' => BASE_URL . '/pages/admin/dashboard.php'],
    ],
    'DATA MANAGEMENT' => [
        'locations' => ['label' => 'Locations', 'icon' => 'map-pin', 'url' => BASE_URL . '/pages/admin/manage-locations.php'],
        'vehicles' => ['label' => 'Vehicles', 'icon' => 'truck', 'url' => BASE_URL . '/pages/admin/manage-vehicles.php'],
        'routes' => ['label' => 'Routes', 'icon' => 'git-branch', 'url' => BASE_URL . '/pages/admin/manage-routes.php'],
    ],
    'REVIEW' => [
        'contributions' => ['label' => 'Contributions', 'icon' => 'inbox', 'url' => BASE_URL . '/pages/admin/contributions.php'],
        'agents' => ['label' => 'Agents', 'icon' => 'users', 'url' => BASE_URL . '/pages/admin/manage-agents.php'],
    ],
    'SYSTEM' => [
        'alerts' => ['label' => 'Alerts', 'icon' => 'alert-triangle', 'url' => BASE_URL . '/pages/admin/manage-alerts.php'],
        'suggestions' => ['label' => 'Suggestions', 'icon' => 'message-square', 'url' => BASE_URL . '/pages/admin/suggestions.php'],
    ],
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> — Sawari Admin</title>

    <!-- Styles -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/global.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/components.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/admin.css">

    <!-- Feather Icons (lightweight SVG icon set) -->
    <script src="https://unpkg.com/feather-icons"></script>

    <!-- Leaflet (for map previews in admin) -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <!-- CSRF token for JS -->
    <meta name="csrf-token" content="<?= e(csrfToken()) ?>">
    <meta name="base-url" content="<?= e(BASE_URL) ?>">

    <!-- Admin JS (loaded early so Sawari namespace is available to inline scripts) -->
    <script src="<?= BASE_URL ?>/assets/js/admin.js"></script>
</head>

<body>

    <!-- ===== Toast Container ===== -->
    <div id="toast-container" class="toast-container"></div>

    <!-- ===== Top Header ===== -->
    <header class="top-header">
        <div class="top-header-brand">
            <!-- Mobile menu toggle -->
            <button class="btn btn-ghost btn-icon sidebar-toggle" id="sidebar-toggle" aria-label="Toggle menu">
                <i data-feather="menu"></i>
            </button>
            <span>Sawari</span>
            <span class="badge badge-neutral" style="font-size: 11px;">Admin</span>
        </div>

        <div class="top-header-actions">
            <div class="dropdown">
                <button class="btn btn-ghost user-info" id="user-dropdown-btn">
                    <div class="avatar"><?= $adminInitial ?></div>
                    <span class="user-info-details" style="text-align:left;">
                        <span class="user-info-name"><?= $adminName ?></span>
                        <span class="user-info-role"><?= ucfirst($adminRole) ?></span>
                    </span>
                    <i data-feather="chevron-down" style="width:16px;height:16px;opacity:0.5;"></i>
                </button>
                <div class="dropdown-menu" id="user-dropdown">
                    <a href="<?= BASE_URL ?>/pages/admin/dashboard.php" class="dropdown-item">
                        <i data-feather="settings"></i> Settings
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="#" class="dropdown-item dropdown-item-danger" id="logout-btn">
                        <i data-feather="log-out"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- ===== Sidebar Backdrop (mobile) ===== -->
    <div class="sidebar-backdrop" id="sidebar-backdrop"></div>

    <!-- ===== Sidebar ===== -->
    <aside class="sidebar" id="sidebar">
        <?php foreach ($navSections as $sectionTitle => $items): ?>
            <div class="nav-section">
                <div class="nav-section-title"><?= e($sectionTitle) ?></div>
                <?php foreach ($items as $key => $item): ?>
                    <a href="<?= e($item['url']) ?>" class="nav-item <?= ($currentPage === $key) ? 'active' : '' ?>">
                        <span class="nav-item-icon"><i data-feather="<?= e($item['icon']) ?>"></i></span>
                        <span><?= e($item['label']) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </aside>

    <!-- ===== Main Content ===== -->
    <main class="main-content">
        <?php if (!$hidePageHeader): ?>
            <div class="admin-page-header">
                <div>
                    <h1 class="admin-page-title"><?= e($pageTitle) ?></h1>
                </div>
                <?php if ($pageActions): ?>
                    <div class="admin-page-header-actions">
                        <?= $pageActions ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>