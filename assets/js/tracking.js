/**
 * SAWARI — Live Tracking Controller
 * 
 * Polls vehicle GPS positions, updates map markers in real-time,
 * calculates ETA, and displays approaching-stop notifications.
 */

const SawariTracking = (function () {
    'use strict';

    const BASE = document.querySelector('meta[name="base-url"]').content;
    const POLL_INTERVAL = 8000; // 8 seconds between polls
    const STALE_THRESHOLD = 120000; // 2 minutes — hide marker if no update

    // Track active vehicle markers: { vehicle_id: L.Marker }
    let vehicleMarkers = {};

    // Tracking layer group (separate from route display)
    let trackingLayer = null;

    // Polling timer
    let pollTimer = null;

    // Whether tracking is enabled
    let isActive = false;

    // Currently shown vehicle count
    let activeCount = 0;

    // Vehicle icon
    const vehicleIcon = L.divIcon({
        className: 'marker-vehicle',
        iconSize: [16, 16],
        iconAnchor: [8, 8]
    });

    // Pulsing vehicle icon (for vehicles approaching a stop < 500m)
    const vehicleIconApproaching = L.divIcon({
        className: 'marker-vehicle marker-vehicle-approaching',
        html: '<div class="marker-vehicle-pulse"></div>',
        iconSize: [20, 20],
        iconAnchor: [10, 10]
    });

    /* ──────────────────────────────────────────────
     *  Init
     * ────────────────────────────────────────────── */
    function init() {
        const map = SawariMap.getMap();
        trackingLayer = L.layerGroup().addTo(map);

        // Toggle button
        const toggleBtn = document.getElementById('tracking-toggle');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', toggle);
        }

        // Start tracking automatically
        start();
    }

    /* ──────────────────────────────────────────────
     *  Start / Stop tracking
     * ────────────────────────────────────────────── */
    function start() {
        if (isActive) return;
        isActive = true;
        pollLiveVehicles(); // immediate first fetch
        pollTimer = setInterval(pollLiveVehicles, POLL_INTERVAL);
        updateToggleUI();
    }

    function stop() {
        isActive = false;
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
        clearAllMarkers();
        updateToggleUI();
    }

    function toggle() {
        if (isActive) stop();
        else start();
    }

    /* ──────────────────────────────────────────────
     *  Poll live vehicles from API
     * ────────────────────────────────────────────── */
    function pollLiveVehicles() {
        fetch(BASE + '/api/vehicles.php?action=live')
            .then(r => r.json())
            .then(data => {
                if (!data.success) return;
                updateVehicleMarkers(data.vehicles);
            })
            .catch(err => console.error('Live tracking poll error:', err));
    }

    /* ──────────────────────────────────────────────
     *  Update markers on map
     * ────────────────────────────────────────────── */
    function updateVehicleMarkers(vehicles) {
        const esc = SawariMap.escHtml;

        // Track which vehicle IDs are still active
        const activeIds = new Set();

        vehicles.forEach(v => {
            const id = parseInt(v.vehicle_id);
            activeIds.add(id);

            const lat = parseFloat(v.latitude);
            const lng = parseFloat(v.longitude);
            if (isNaN(lat) || isNaN(lng)) return;

            // Build popup content
            let popupHtml = `<div class="vehicle-popup">`;
            popupHtml += `<div class="vehicle-popup-name">${esc(v.name)}</div>`;

            if (v.route_name) {
                popupHtml += `<div style="font-size:11px;color:var(--color-neutral-500);">${esc(v.route_name)}</div>`;
            }

            if (v.approaching) {
                popupHtml += `<div class="vehicle-popup-eta">`;
                popupHtml += `Approaching: <strong>${esc(v.approaching.name)}</strong>`;
                if (v.eta_minutes && v.eta_minutes > 0) {
                    popupHtml += `<br>ETA: ~${formatETA(v.eta_minutes)}`;
                }
                popupHtml += `</div>`;
            }

            if (v.velocity > 0) {
                popupHtml += `<div style="font-size:11px;color:var(--color-neutral-400);margin-top:2px;">Speed: ${Math.round(v.velocity)} km/h</div>`;
            }

            popupHtml += `</div>`;

            // Decide icon
            const isApproaching = v.approaching && v.approaching.distance_km < 0.5;
            const icon = isApproaching ? vehicleIconApproaching : vehicleIcon;

            if (vehicleMarkers[id]) {
                // Update existing marker position (smooth)
                vehicleMarkers[id].setLatLng([lat, lng]);
                vehicleMarkers[id].setIcon(icon);
                vehicleMarkers[id].getPopup().setContent(popupHtml);
            } else {
                // Create new marker
                const marker = L.marker([lat, lng], { icon: icon })
                    .bindPopup(popupHtml)
                    .addTo(trackingLayer);

                // Add tooltip with vehicle name
                marker.bindTooltip(esc(v.name), {
                    permanent: false,
                    direction: 'top',
                    offset: [0, -12],
                    className: 'vehicle-tooltip'
                });

                vehicleMarkers[id] = marker;
            }
        });

        // Remove markers for vehicles that are no longer active
        Object.keys(vehicleMarkers).forEach(idStr => {
            const id = parseInt(idStr);
            if (!activeIds.has(id)) {
                trackingLayer.removeLayer(vehicleMarkers[id]);
                delete vehicleMarkers[id];
            }
        });

        activeCount = activeIds.size;
        updateCountBadge();
    }

    /* ──────────────────────────────────────────────
     *  Clear all vehicle markers
     * ────────────────────────────────────────────── */
    function clearAllMarkers() {
        trackingLayer.clearLayers();
        vehicleMarkers = {};
        activeCount = 0;
        updateCountBadge();
    }

    /* ──────────────────────────────────────────────
     *  UI updates
     * ────────────────────────────────────────────── */
    function updateToggleUI() {
        const btn = document.getElementById('tracking-toggle');
        if (!btn) return;

        const iconEl = btn.querySelector('[data-feather]') || btn.querySelector('svg');
        if (isActive) {
            btn.classList.add('tracking-active');
            btn.title = 'Live tracking ON — click to disable';
        } else {
            btn.classList.remove('tracking-active');
            btn.title = 'Live tracking OFF — click to enable';
        }
    }

    function updateCountBadge() {
        const badge = document.getElementById('tracking-count');
        if (!badge) return;

        if (activeCount > 0) {
            badge.textContent = activeCount;
            badge.style.display = '';
        } else {
            badge.style.display = 'none';
        }
    }

    /* ──────────────────────────────────────────────
     *  Utility
     * ────────────────────────────────────────────── */
    function formatETA(minutes) {
        if (minutes < 1) return 'less than 1 min';
        if (minutes < 60) return Math.round(minutes) + ' min';
        const h = Math.floor(minutes / 60);
        const m = Math.round(minutes % 60);
        return h + 'h ' + m + 'min';
    }

    /* ──────────────────────────────────────────────
     *  Public API
     * ────────────────────────────────────────────── */
    return {
        init,
        start,
        stop,
        toggle,
        getActiveCount: () => activeCount,
        isTracking: () => isActive
    };
})();

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', SawariTracking.init);
