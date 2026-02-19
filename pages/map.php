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
    <meta name="csrf-token" content="<?= e(csrfToken()) ?>">
</head>

<body class="map-page">

    <!-- Toast Container -->
    <div id="toast-container" class="toast-container"></div>

    <!-- ===== Map Container ===== -->
    <div class="map-container" id="map"></div>

    <!-- ===== Settings Panel (top-right gear) ===== -->
    <div class="settings-fab" id="settings-fab">
        <button class="settings-fab-btn" id="settings-btn" title="Map settings">
            <i data-feather="settings" style="width:20px;height:20px;"></i>
        </button>
        <div class="settings-dropdown" id="settings-dropdown">
            <div class="settings-dropdown-header">Map Layers</div>
            <label class="settings-toggle">
                <input type="checkbox" id="toggle-stops" checked>
                <span class="settings-toggle-slider"></span>
                <span class="settings-toggle-label">Bus Stops</span>
            </label>
            <label class="settings-toggle">
                <input type="checkbox" id="toggle-vehicles" checked>
                <span class="settings-toggle-slider"></span>
                <span class="settings-toggle-label">Live Vehicles</span>
            </label>
            <label class="settings-toggle">
                <input type="checkbox" id="toggle-alerts" checked>
                <span class="settings-toggle-slider"></span>
                <span class="settings-toggle-label">Warnings</span>
            </label>
            <label class="settings-toggle">
                <input type="checkbox" id="toggle-routes" checked>
                <span class="settings-toggle-slider"></span>
                <span class="settings-toggle-label">Route Lines</span>
            </label>
        </div>
    </div>

    <!-- ===== Search Panel (top-left overlay) ===== -->
    <div class="search-panel" id="search-panel">
        <div class="search-panel-card">
            <!-- Brand -->
            <div class="search-panel-header" id="search-panel-header">
                <div style="display:flex;align-items:center;gap:var(--space-2);">
                    <span class="search-panel-brand">Sawari</span>
                    <span class="badge badge-accent" style="font-size:10px;">Beta</span>
                </div>
                <div style="display:flex;gap:var(--space-1);">
                    <button class="btn btn-ghost btn-icon btn-sm" id="tracking-toggle" title="Live tracking"
                        style="position:relative;">
                        <i data-feather="radio" style="width:18px;height:18px;"></i>
                        <span id="tracking-count" class="tracking-badge" style="display:none;"></span>
                    </button>
                    <button class="btn btn-ghost btn-icon btn-sm" id="locate-btn" title="My Location">
                        <i data-feather="crosshair" style="width:18px;height:18px;"></i>
                    </button>
                    <button class="btn btn-ghost btn-icon btn-sm search-panel-collapse-btn" id="search-collapse-btn" title="Collapse">
                        <i data-feather="chevron-up" style="width:18px;height:18px;"></i>
                    </button>
                </div>
            </div>

            <!-- Search Inputs (collapsible body) -->
            <div class="search-panel-body" id="search-panel-body">
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
            </div><!-- /search-panel-body -->
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
        <div class="result-panel-handle" id="result-panel-handle">
            <div class="result-panel-handle-bar"></div>
        </div>
        <div class="result-panel-peek" id="result-panel-peek">
            <!-- Peek summary filled by routing.js -->
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

    <!-- ===== Feedback Modal ===== -->
    <div id="feedback-modal" class="modal-overlay" style="display:none;">
        <div class="feedback-modal-card">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:var(--space-4);">
                <h3
                    style="font-size:var(--text-lg);font-weight:var(--font-bold);color:var(--color-neutral-900);margin:0;">
                    Rate Your Trip</h3>
                <button class="btn btn-ghost btn-icon btn-sm" id="feedback-close">
                    <i data-feather="x" style="width:18px;height:18px;"></i>
                </button>
            </div>

            <input type="hidden" id="feedback-trip-id" value="">

            <!-- Star Rating -->
            <div style="margin-bottom:var(--space-4);">
                <label
                    style="font-size:var(--text-sm);font-weight:var(--font-medium);color:var(--color-neutral-700);display:block;margin-bottom:var(--space-2);">How
                    was your ride?</label>
                <div class="star-rating" id="star-rating">
                    <span class="star" data-value="1">★</span>
                    <span class="star" data-value="2">★</span>
                    <span class="star" data-value="3">★</span>
                    <span class="star" data-value="4">★</span>
                    <span class="star" data-value="5">★</span>
                </div>
            </div>

            <!-- Accuracy -->
            <div style="margin-bottom:var(--space-4);">
                <label
                    style="font-size:var(--text-sm);font-weight:var(--font-medium);color:var(--color-neutral-700);display:block;margin-bottom:var(--space-2);">Route
                    accuracy?</label>
                <div style="display:flex;gap:var(--space-2);">
                    <button class="btn btn-secondary btn-sm accuracy-btn" data-val="accurate">Accurate</button>
                    <button class="btn btn-secondary btn-sm accuracy-btn" data-val="slightly_off">Slightly Off</button>
                    <button class="btn btn-secondary btn-sm accuracy-btn" data-val="inaccurate">Inaccurate</button>
                </div>
            </div>

            <!-- Review -->
            <div style="margin-bottom:var(--space-4);">
                <label
                    style="font-size:var(--text-sm);font-weight:var(--font-medium);color:var(--color-neutral-700);display:block;margin-bottom:var(--space-2);">Comments
                    (optional)</label>
                <textarea id="feedback-review" class="form-input" rows="3" placeholder="Share your experience..."
                    style="resize:vertical;"></textarea>
            </div>

            <button class="btn btn-primary btn-block" id="feedback-submit-btn">
                <i data-feather="send" style="width:16px;height:16px;"></i>
                Submit Feedback
            </button>
        </div>
    </div>

    <!-- ===== Community Suggestion Modal ===== -->
    <div id="suggestion-modal" class="modal-overlay" style="display:none;">
        <div class="feedback-modal-card">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:var(--space-4);">
                <h3
                    style="font-size:var(--text-lg);font-weight:var(--font-bold);color:var(--color-neutral-900);margin:0;">
                    Suggest an Improvement</h3>
                <button class="btn btn-ghost btn-icon btn-sm" id="suggestion-close">
                    <i data-feather="x" style="width:18px;height:18px;"></i>
                </button>
            </div>

            <div style="margin-bottom:var(--space-4);">
                <label
                    style="font-size:var(--text-sm);font-weight:var(--font-medium);color:var(--color-neutral-700);display:block;margin-bottom:var(--space-2);">Type</label>
                <select id="suggestion-type" class="form-input">
                    <option value="missing_stop">Missing Bus Stop</option>
                    <option value="route_correction">Route Correction</option>
                    <option value="new_route">New Route</option>
                    <option value="general">General Feedback</option>
                </select>
            </div>

            <div style="margin-bottom:var(--space-4);">
                <label
                    style="font-size:var(--text-sm);font-weight:var(--font-medium);color:var(--color-neutral-700);display:block;margin-bottom:var(--space-2);">Title</label>
                <input type="text" id="suggestion-title" class="form-input" placeholder="Brief title">
            </div>

            <div style="margin-bottom:var(--space-4);">
                <label
                    style="font-size:var(--text-sm);font-weight:var(--font-medium);color:var(--color-neutral-700);display:block;margin-bottom:var(--space-2);">Description</label>
                <textarea id="suggestion-desc" class="form-input" rows="3"
                    placeholder="Describe the issue or suggestion..." style="resize:vertical;"></textarea>
            </div>

            <button class="btn btn-primary btn-block" id="suggestion-submit-btn">
                <i data-feather="send" style="width:16px;height:16px;"></i>
                Submit Suggestion
            </button>
        </div>
    </div>

    <!-- ===== Bottom Toolbar (suggestion + info) ===== -->
    <div class="map-bottom-toolbar">
        <button class="btn btn-secondary btn-sm" id="suggestion-open-btn" title="Suggest an improvement">
            <i data-feather="message-square" style="width:16px;height:16px;"></i>
            Suggest
        </button>
    </div>

    <!-- Scripts -->
    <script src="<?= BASE_URL ?>/assets/js/map.js"></script>
    <script src="<?= BASE_URL ?>/assets/js/search.js"></script>
    <script src="<?= BASE_URL ?>/assets/js/routing.js"></script>
    <script src="<?= BASE_URL ?>/assets/js/tracking.js"></script>
    <script>
        feather.replace({ 'stroke-width': 1.75 });

        /* Feedback modal logic */
        (function () {
            const BASE = document.querySelector('meta[name="base-url"]').content;
            const modal = document.getElementById('feedback-modal');
            const closeBtn = document.getElementById('feedback-close');
            const submitBtn = document.getElementById('feedback-submit-btn');
            let selectedRating = 0;
            let selectedAccuracy = '';

            // Star rating
            document.querySelectorAll('#star-rating .star').forEach(star => {
                star.addEventListener('click', () => {
                    selectedRating = parseInt(star.dataset.value);
                    document.querySelectorAll('#star-rating .star').forEach((s, i) => {
                        s.classList.toggle('active', i < selectedRating);
                    });
                });
            });

            // Accuracy buttons
            document.querySelectorAll('.accuracy-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    document.querySelectorAll('.accuracy-btn').forEach(b => b.classList.remove('btn-primary'));
                    document.querySelectorAll('.accuracy-btn').forEach(b => b.classList.add('btn-secondary'));
                    btn.classList.remove('btn-secondary');
                    btn.classList.add('btn-primary');
                    selectedAccuracy = btn.dataset.val;
                });
            });

            closeBtn.addEventListener('click', () => { modal.style.display = 'none'; });
            modal.addEventListener('click', (e) => { if (e.target === modal) modal.style.display = 'none'; });

            submitBtn.addEventListener('click', () => {
                const tripId = document.getElementById('feedback-trip-id').value;
                if (!tripId || !selectedRating) {
                    SawariMap.showToast('Please select a rating.', 'warning');
                    return;
                }
                const fd = new FormData();
                fd.append('trip_id', tripId);
                fd.append('rating', selectedRating);
                fd.append('accuracy_feedback', selectedAccuracy);
                fd.append('review', document.getElementById('feedback-review').value);

                submitBtn.disabled = true;
                submitBtn.textContent = 'Submitting...';

                fetch(BASE + '/api/trips.php?action=feedback', { method: 'POST', body: fd, credentials: 'same-origin' })
                    .then(r => r.json())
                    .then(res => {
                        if (res.success) {
                            SawariMap.showToast('Thank you for your feedback!', 'success');
                            modal.style.display = 'none';
                        } else {
                            SawariMap.showToast(res.message || 'Failed to submit.', 'error');
                        }
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<i data-feather="send" style="width:16px;height:16px;"></i> Submit Feedback';
                        feather.replace({ 'stroke-width': 1.75 });
                    })
                    .catch(() => {
                        SawariMap.showToast('Network error.', 'error');
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<i data-feather="send" style="width:16px;height:16px;"></i> Submit Feedback';
                        feather.replace({ 'stroke-width': 1.75 });
                    });
            });

            /* Suggestion modal logic */
            const sugModal = document.getElementById('suggestion-modal');
            const sugOpen = document.getElementById('suggestion-open-btn');
            const sugClose = document.getElementById('suggestion-close');
            const sugSubmit = document.getElementById('suggestion-submit-btn');

            sugOpen.addEventListener('click', () => { sugModal.style.display = 'flex'; });
            sugClose.addEventListener('click', () => { sugModal.style.display = 'none'; });
            sugModal.addEventListener('click', (e) => { if (e.target === sugModal) sugModal.style.display = 'none'; });

            sugSubmit.addEventListener('click', () => {
                const title = document.getElementById('suggestion-title').value.trim();
                const desc = document.getElementById('suggestion-desc').value.trim();
                const type = document.getElementById('suggestion-type').value;

                if (!title) { SawariMap.showToast('Title is required.', 'warning'); return; }
                if (!desc) { SawariMap.showToast('Description is required.', 'warning'); return; }

                const fd = new FormData();
                fd.append('title', title);
                fd.append('description', desc);
                fd.append('type', type);

                sugSubmit.disabled = true;
                sugSubmit.textContent = 'Submitting...';

                fetch(BASE + '/api/suggestions.php?action=submit', { method: 'POST', body: fd, credentials: 'same-origin' })
                    .then(r => r.json())
                    .then(res => {
                        if (res.success) {
                            SawariMap.showToast('Suggestion submitted. Thank you!', 'success');
                            sugModal.style.display = 'none';
                            document.getElementById('suggestion-title').value = '';
                            document.getElementById('suggestion-desc').value = '';
                        } else {
                            SawariMap.showToast(res.message || 'Failed.', 'error');
                        }
                        sugSubmit.disabled = false;
                        sugSubmit.innerHTML = '<i data-feather="send" style="width:16px;height:16px;"></i> Submit Suggestion';
                        feather.replace({ 'stroke-width': 1.75 });
                    })
                    .catch(() => {
                        SawariMap.showToast('Network error.', 'error');
                        sugSubmit.disabled = false;
                        sugSubmit.innerHTML = '<i data-feather="send" style="width:16px;height:16px;"></i> Submit Suggestion';
                        feather.replace({ 'stroke-width': 1.75 });
                    });
            });
        })();
    </script>
</body>

</html>