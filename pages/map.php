<?php
/**
 * SAWARI — Main User Map Page
 * 
 * Full-screen Leaflet map with:
 * - Point A / Point B search inputs
 * - Route visualization on map
 * - Live vehicle tracking markers
 * - Route result panel (fare, vehicle, stops)
 */

require_once __DIR__ . '/../api/config.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Sawari — Find Your Bus Route</title>

    <!-- Styles -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/global.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/components.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/map.css">

    <!-- Leaflet -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <!-- Feather Icons -->
    <script src="https://unpkg.com/feather-icons"></script>

    <!-- Meta for JS -->
    <meta name="base-url" content="<?= BASE_URL ?>">
</head>

<body class="map-page">

    <!-- Toast Container -->
    <div id="toast-container" class="toast-container"></div>

    <!-- ===== Map Container ===== -->
    <div class="map-container" id="map"></div>

    <!-- ===== Search Panel (top-left overlay) ===== -->
    <div class="search-panel" id="search-panel">
        <div class="search-panel-card">
            <!-- Brand -->
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:var(--space-4);">
                <div style="display:flex;align-items:center;gap:var(--space-2);">
                    <span
                        style="font-size:var(--text-lg);font-weight:var(--font-bold);color:var(--color-neutral-900);letter-spacing:var(--tracking-tight);">Sawari</span>
                    <span class="badge badge-accent" style="font-size:10px;">Beta</span>
                </div>
                <button class="btn btn-ghost btn-icon btn-sm" id="locate-btn" title="My Location">
                    <i data-feather="crosshair" style="width:18px;height:18px;"></i>
                </button>
            </div>

            <!-- Search Inputs -->
            <div class="search-panel-inputs">
                <div class="search-panel-input-row">
                    <div class="search-panel-dot search-panel-dot-a"></div>
                    <div style="flex:1;position:relative;">
                        <input type="text" class="form-input" id="input-a" placeholder="Starting point"
                            autocomplete="off">
                        <div class="search-results" id="results-a"></div>
                    </div>
                    <button class="btn btn-ghost btn-icon btn-sm" id="clear-a" title="Clear" style="display:none;">
                        <i data-feather="x" style="width:14px;height:14px;"></i>
                    </button>
                </div>

                <div style="display:flex;align-items:center;gap:var(--space-3);">
                    <div class="search-panel-connector"></div>
                </div>

                <div class="search-panel-input-row">
                    <div class="search-panel-dot search-panel-dot-b"></div>
                    <div style="flex:1;position:relative;">
                        <input type="text" class="form-input" id="input-b" placeholder="Where to?" autocomplete="off">
                        <div class="search-results" id="results-b"></div>
                    </div>
                    <button class="btn btn-ghost btn-icon btn-sm" id="clear-b" title="Clear" style="display:none;">
                        <i data-feather="x" style="width:14px;height:14px;"></i>
                    </button>
                </div>
            </div>

            <!-- Actions -->
            <div class="search-panel-actions">
                <button class="btn btn-primary btn-block" id="find-route-btn" disabled>
                    <i data-feather="navigation" style="width:16px;height:16px;"></i>
                    Find Route
                </button>
            </div>

            <!-- Map click hint -->
            <p id="map-hint"
                style="font-size:var(--text-xs);color:var(--color-neutral-400);margin:var(--space-3) 0 0;text-align:center;">
                Or click on the map to set points
            </p>
        </div>

        <!-- Active alerts banner (shown if any) -->
        <div class="search-panel-card" id="alerts-banner" style="display:none;padding:var(--space-3) var(--space-4);">
            <div style="display:flex;align-items:center;gap:var(--space-2);font-size:var(--text-sm);">
                <i data-feather="alert-triangle"
                    style="width:16px;height:16px;color:var(--color-warning-500);flex-shrink:0;"></i>
                <span id="alert-text" style="color:var(--color-neutral-700);"></span>
            </div>
        </div>
    </div>

    <!-- ===== Route Result Panel (bottom sheet) ===== -->
    <div class="result-panel" id="result-panel">
        <div class="result-panel-handle">
            <div class="result-panel-handle-bar"></div>
        </div>
        <div class="result-panel-content" id="result-content">
            <!-- Filled dynamically by routing.js -->
        </div>
    </div>

    <!-- ===== Loading Overlay ===== -->
    <div id="loading-overlay"
        style="display:none;position:fixed;inset:0;background:rgba(255,255,255,0.7);z-index:2000;align-items:center;justify-content:center;">
        <div style="text-align:center;">
            <div class="spinner spinner-lg"></div>
            <p style="margin-top:var(--space-3);font-size:var(--text-sm);color:var(--color-neutral-600);">Finding the
                best route...</p>
        </div>
    </div>

    <!-- Scripts -->
    <script src="<?= BASE_URL ?>/assets/js/map.js"></script>
    <script src="<?= BASE_URL ?>/assets/js/search.js"></script>
    <script src="<?= BASE_URL ?>/assets/js/routing.js"></script>
    <script>
        feather.replace({ 'stroke-width': 1.75 });
    </script>
</body>

</html>