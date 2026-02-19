<?php
/**
 * Common Header Template — Sawari
 * 
 * Include at the top of every page.
 * 
 * Available variables (set before including):
 *   $pageTitle  - Page title (string)
 *   $pageCss    - Additional CSS files (array of paths relative to BASE_URL)
 *   $bodyClass  - Extra class for <body> tag (string)
 */

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/functions.php';

$pageTitle = $pageTitle ?? 'Sawari — Navigate Nepal\'s Public Transport';
$pageCss = $pageCss ?? [];
$bodyClass = $bodyClass ?? '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description"
        content="Sawari helps you navigate Nepal's public transportation system. Find bus routes, fares, and directions.">
    <meta name="theme-color" content="#2563eb">
    <title><?= sanitize($pageTitle) ?></title>

    <!-- Font Awesome Premium v7.1.0 -->
    <link rel="stylesheet" href="https://site-assets.fontawesome.com/releases/v7.1.0/css/all.css">

    <!-- Global Stylesheet -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/global.css">

    <!-- Page-specific Stylesheets -->
    <?php foreach ($pageCss as $css): ?>
        <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/<?= $css ?>">
    <?php endforeach; ?>

    <!-- Leaflet CSS (loaded on map pages) -->
    <?php if (isset($useLeaflet) && $useLeaflet): ?>
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
            integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
    <?php endif; ?>
</head>

<body class="<?= sanitize($bodyClass) ?>">

    <!-- Navigation Bar -->
    <nav class="navbar" id="navbar">
        <div class="nav-container">
            <a href="<?= BASE_URL ?>/" class="nav-brand">
                <span class="brand-icon"><i class="fa-duotone fa-solid fa-bus"></i></span>
                <span class="brand-text">Sawari</span>
            </a>

            <button class="nav-toggle" id="navToggle" aria-label="Toggle navigation">
                <span class="hamburger"></span>
            </button>

            <ul class="nav-links" id="navLinks">
                <li><a href="<?= BASE_URL ?>/" class="nav-link">Home</a></li>
                <li><a href="<?= BASE_URL ?>/pages/map.php" class="nav-link">Find Route</a></li>

                <?php if (isLoggedIn()): ?>
                    <?php if (isAdmin()): ?>
                        <li><a href="<?= BASE_URL ?>/pages/admin/dashboard.php" class="nav-link">Dashboard</a></li>
                    <?php elseif (isAgent()): ?>
                        <li><a href="<?= BASE_URL ?>/pages/agent/dashboard.php" class="nav-link">Dashboard</a></li>
                    <?php endif; ?>
                    <li class="nav-user">
                        <span class="nav-user-name"><?= sanitize(getCurrentUserName()) ?></span>
                        <a href="<?= BASE_URL ?>/pages/auth/logout.php" class="nav-link nav-logout">Logout</a>
                    </li>
                <?php else: ?>
                    <li><a href="<?= BASE_URL ?>/pages/auth/login.php" class="nav-link btn-nav">Login</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>

    <!-- Flash Messages -->
    <?= renderFlashMessage() ?>

    <!-- Main Content Begins -->
    <main class="main-content">