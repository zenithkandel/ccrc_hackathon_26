<?php
/**
 * SAWARI — Agent Layout Header
 * 
 * Opens the agent page HTML structure: doctype, head, body,
 * top header bar, sidebar navigation, and main content area.
 * 
 * Required variables before including this file:
 *   $pageTitle   — (string) Page title for <title> and header
 *   $currentPage — (string) Active sidebar nav item key
 * 
 * Optional:
 *   $pageActions    — (string) HTML for action buttons in page header
 *   $hidePageHeader — (bool) If true, don't render the page header
 *   $extraHead      — (string) Extra HTML to inject into <head>
 */

$pageTitle = $pageTitle ?? 'Dashboard';
$currentPage = $currentPage ?? '';
$pageActions = $pageActions ?? '';
$hidePageHeader = $hidePageHeader ?? false;
$extraHead = $extraHead ?? '';

$agentName = e($_SESSION['agent_name'] ?? 'Agent');
$agentEmail = e($_SESSION['agent_email'] ?? '');
$agentInitial = strtoupper(substr($agentName, 0, 1));

// Sidebar navigation
$navSections = [
    'OVERVIEW' => [
        'dashboard' => ['label' => 'Dashboard', 'icon' => 'grid', 'url' => BASE_URL . '/pages/agent/dashboard.php'],
    ],
    'COLLECT DATA' => [
        'add-location' => ['label' => 'Add Location', 'icon' => 'map-pin', 'url' => BASE_URL . '/pages/agent/add-location.php'],
        'add-vehicle' => ['label' => 'Add Vehicle', 'icon' => 'truck', 'url' => BASE_URL . '/pages/agent/add-vehicle.php'],
        'add-route' => ['label' => 'Add Route', 'icon' => 'git-branch', 'url' => BASE_URL . '/pages/agent/add-route.php'],
    ],
    'HISTORY' => [
        'contributions' => ['label' => 'My Contributions', 'icon' => 'inbox', 'url' => BASE_URL . '/pages/agent/my-contributions.php'],
    ],
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> — Sawari Agent</title>

    <!-- Styles -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/global.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/components.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/agent.css">

    <!-- Feather Icons -->
    <script src="https://unpkg.com/feather-icons"></script>

    <!-- Leaflet -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <!-- Meta for JS -->
    <meta name="csrf-token" content="<?= e(csrfToken()) ?>">
    <meta name="base-url" content="<?= e(BASE_URL) ?>">
    <meta name="agent-id" content="<?= e((string) ($_SESSION['agent_id'] ?? '')) ?>">

    <!-- Agent JS (loaded early so Sawari namespace is available to inline scripts) -->
    <script src="<?= BASE_URL ?>/assets/js/agent.js"></script>

    <?= $extraHead ?>
</head>

<body class="agent-layout">

    <!-- Toast Container -->
    <div id="toast-container" class="toast-container"></div>

    <!-- Top Header -->
    <header class="top-header">
        <div class="top-header-brand">
            <button class="btn btn-ghost btn-icon sidebar-toggle" id="sidebar-toggle" aria-label="Toggle menu">
                <i data-feather="menu"></i>
            </button>
            <span>Sawari</span>
            <span class="badge badge-primary" style="font-size: 11px;">Agent</span>
        </div>

        <div class="top-header-actions">
            <div class="dropdown">
                <button class="btn btn-ghost user-info" id="user-dropdown-btn">
                    <div class="avatar"><?= $agentInitial ?></div>
                    <span class="user-info-details" style="text-align:left;">
                        <span class="user-info-name"><?= $agentName ?></span>
                        <span class="user-info-role">Agent</span>
                    </span>
                    <i data-feather="chevron-down" style="width:16px;height:16px;opacity:0.5;"></i>
                </button>
                <div class="dropdown-menu" id="user-dropdown">
                    <a href="<?= BASE_URL ?>/pages/agent/dashboard.php" class="dropdown-item">
                        <i data-feather="user"></i> My Profile
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="#" class="dropdown-item dropdown-item-danger" id="logout-btn">
                        <i data-feather="log-out"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Sidebar Backdrop (mobile) -->
    <div class="sidebar-backdrop" id="sidebar-backdrop"></div>

    <!-- Sidebar -->
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

    <!-- Main Content -->
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