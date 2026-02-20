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

    // Smooth animation: store previous positions for interpolation
    let vehiclePrevPos = {}; // { vehicle_id: { lat, lng } }
    const ANIMATE_DURATION = 2000; // 2 seconds smooth slide
    let animationFrames = {};

    // Vehicle icon — SVG bus icon (uses shared SVG library from map.js)
    function getVehicleSvg() {
        return (window.SawariMap && SawariMap.SVG) ? SawariMap.SVG.vehicle :
            '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3h12a2 2 0 012 2v10a2 2 0 01-2 2H6a2 2 0 01-2-2V5a2 2 0 012-2z"/><path d="M4 10h16"/><circle cx="8" cy="20" r="1"/><circle cx="16" cy="20" r="1"/><path d="M8 17v2M16 17v2"/></svg>';
    }

    const vehicleIcon = L.divIcon({
        className: 'marker-vehicle-icon',
        html: '<div class="marker-vehicle-dot"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3h12a2 2 0 012 2v10a2 2 0 01-2 2H6a2 2 0 01-2-2V5a2 2 0 012-2z"/><path d="M4 10h16"/><circle cx="8" cy="20" r="1"/><circle cx="16" cy="20" r="1"/><path d="M8 17v2M16 17v2"/></svg></div>',
        iconSize: [32, 32],
        iconAnchor: [16, 16]
    });

    // Pulsing vehicle icon (for vehicles approaching a stop < 500m)
    const vehicleIconApproaching = L.divIcon({
        className: 'marker-vehicle-icon marker-vehicle-approaching',
        html: '<div class="marker-vehicle-dot marker-vehicle-dot-pulse"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3h12a2 2 0 012 2v10a2 2 0 01-2 2H6a2 2 0 01-2-2V5a2 2 0 012-2z"/><path d="M4 10h16"/><circle cx="8" cy="20" r="1"/><circle cx="16" cy="20" r="1"/><path d="M8 17v2M16 17v2"/></svg></div>',
        iconSize: [36, 36],
        iconAnchor: [18, 18]
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
            .then(r => {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            })
            .then(data => {
                if (!data.success) return;
                updateVehicleMarkers(data.vehicles);
            })
            .catch(err => {
                // Silently ignore poll errors — will retry on next interval
                console.warn('Live tracking poll error:', err.message);
            });
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
                // Smooth slide from old position to new position
                animateMarker(id, vehicleMarkers[id], lat, lng);
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
     *  Smooth marker animation (slide between positions)
     * ────────────────────────────────────────────── */
    function animateMarker(id, marker, toLat, toLng) {
        // Cancel any ongoing animation for this marker
        if (animationFrames[id]) cancelAnimationFrame(animationFrames[id]);

        const from = marker.getLatLng();
        const fromLat = from.lat;
        const fromLng = from.lng;

        // If barely moved, snap immediately
        const dLat = toLat - fromLat;
        const dLng = toLng - fromLng;
        if (Math.abs(dLat) < 0.000001 && Math.abs(dLng) < 0.000001) return;

        const start = performance.now();

        function step(now) {
            let t = (now - start) / ANIMATE_DURATION;
            if (t > 1) t = 1;
            // Ease-out cubic for natural deceleration
            const ease = 1 - Math.pow(1 - t, 3);
            const lat = fromLat + dLat * ease;
            const lng = fromLng + dLng * ease;
            marker.setLatLng([lat, lng]);

            if (t < 1) {
                animationFrames[id] = requestAnimationFrame(step);
            } else {
                delete animationFrames[id];
            }
        }

        animationFrames[id] = requestAnimationFrame(step);
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
