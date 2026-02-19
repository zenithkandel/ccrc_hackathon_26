/**
 * SAWARI — Map Controller
 * 
 * Handles Leaflet map initialization, tile layers, markers,
 * polyline rendering, and map interaction events.
 */

const SawariMap = (function () {
    'use strict';

    const BASE = document.querySelector('meta[name="base-url"]').content;
    const KATHMANDU = [27.7172, 85.3240];
    const DEFAULT_ZOOM = 13;
    const STOP_ZOOM = 15;

    // Leaflet map instance
    let map;

    // Layer groups
    let stopMarkersLayer;
    let routeLayerGroup;
    let userMarkersLayer;
    let alertLayerGroup;

    // User-placed markers for Point A / Point B
    let markerA = null;
    let markerB = null;

    // All approved stops [{ location_id, name, lat, lng, type }]
    let allStops = [];

    // State: which point are we setting next? ('a' or 'b')
    let nextPoint = 'a';

    // Pin-pick mode target (null, 'a', or 'b')
    let pinPickTarget = null;

    // My Location state
    let myLocationMarker = null;
    let myLocationCircle = null;
    let myLocationWatcher = null;

    // Custom icons — unique SVG-based markers
    const iconA = L.divIcon({
        className: 'marker-icon-a',
        html: '<div class="marker-pin marker-pin-origin"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="10" r="3"/><path d="M12 2a8 8 0 0 0-8 8c0 1.892.402 3.13 1.5 4.5L12 22l6.5-7.5c1.098-1.37 1.5-2.608 1.5-4.5a8 8 0 0 0-8-8z"/></svg></div>',
        iconSize: [32, 40],
        iconAnchor: [16, 40],
        popupAnchor: [0, -40]
    });

    const iconB = L.divIcon({
        className: 'marker-icon-b',
        html: '<div class="marker-pin marker-pin-dest"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/></svg></div>',
        iconSize: [32, 40],
        iconAnchor: [16, 40],
        popupAnchor: [0, -40]
    });

    const iconStop = L.divIcon({
        className: 'marker-icon-stop',
        html: '<div class="marker-dot marker-dot-stop"><svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18"/><path d="M9 21V9"/></svg></div>',
        iconSize: [22, 22],
        iconAnchor: [11, 11]
    });

    /* ──────────────────────────────────────────────
     *  Init
     * ────────────────────────────────────────────── */
    function init() {
        map = L.map('map', {
            center: KATHMANDU,
            zoom: DEFAULT_ZOOM,
            zoomControl: false
        });

        // Zoom control — bottom right
        L.control.zoom({ position: 'bottomright' }).addTo(map);

        // OpenStreetMap tiles
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://openstreetmap.org/copyright">OpenStreetMap</a>',
            maxZoom: 19
        }).addTo(map);

        // Layer groups
        stopMarkersLayer = L.layerGroup().addTo(map);
        routeLayerGroup = L.layerGroup().addTo(map);
        userMarkersLayer = L.layerGroup().addTo(map);
        alertLayerGroup = L.layerGroup().addTo(map);

        // Map click handler — set Point A or B
        map.on('click', onMapClick);

        // Load approved stops
        loadStops();

        // Locate user
        document.getElementById('locate-btn').addEventListener('click', locateUser);
    }

    /* ──────────────────────────────────────────────
     *  Load all approved stops and display markers
     * ────────────────────────────────────────────── */
    function loadStops() {
        fetch(BASE + '/api/locations.php?action=approved')
            .then(r => {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            })
            .then(data => {
                if (!data.success) return;
                allStops = data.locations.map(l => ({
                    location_id: parseInt(l.location_id),
                    name: l.name,
                    lat: parseFloat(l.latitude),
                    lng: parseFloat(l.longitude),
                    type: l.type
                }));
                renderStopMarkers();
            })
            .catch(err => {
                console.error('Failed to load stops:', err);
                showToast('Could not load bus stops. Check your connection.', 'warning');
            });
    }

    function renderStopMarkers() {
        stopMarkersLayer.clearLayers();
        allStops.forEach(s => {
            const m = L.marker([s.lat, s.lng], { icon: iconStop })
                .bindPopup(`<strong>${escHtml(s.name)}</strong><br><span style="font-size:12px;color:#64748B;">${s.type}</span>`);
            stopMarkersLayer.addLayer(m);
        });
    }

    /* ──────────────────────────────────────────────
     *  Map click → set Point A or B
     * ────────────────────────────────────────────── */
    function onMapClick(e) {
        const { lat, lng } = e.latlng;

        if (pinPickTarget) {
            const which = pinPickTarget;
            cancelPinPick();
            reverseGeocode(lat, lng).then(name => {
                if (which === 'a') {
                    setPointA(lat, lng, name);
                    nextPoint = 'b';
                } else {
                    setPointB(lat, lng, name);
                    nextPoint = 'a';
                }
            });
            return;
        }

        reverseGeocode(lat, lng).then(name => {
            if (nextPoint === 'a') {
                setPointA(lat, lng, name);
                nextPoint = 'b';
            } else {
                setPointB(lat, lng, name);
                nextPoint = 'a';
            }
        });
    }

    function setPointA(lat, lng, name) {
        if (markerA) userMarkersLayer.removeLayer(markerA);
        markerA = L.marker([lat, lng], { icon: iconA, draggable: true })
            .bindPopup('Start: ' + escHtml(name))
            .addTo(userMarkersLayer);

        markerA.on('dragend', function () {
            const pos = markerA.getLatLng();
            reverseGeocode(pos.lat, pos.lng).then(name => {
                if (window.SawariSearch) {
                    SawariSearch.setInputA(pos.lat, pos.lng, name);
                }
                markerA.setPopupContent('Start: ' + escHtml(name));
            });
        });

        // Update input
        const inputA = document.getElementById('input-a');
        inputA.value = name;
        inputA.dataset.lat = lat;
        inputA.dataset.lng = lng;
        document.getElementById('clear-a').style.display = '';
        checkFindRouteReady();
    }

    function setPointB(lat, lng, name) {
        if (markerB) userMarkersLayer.removeLayer(markerB);
        markerB = L.marker([lat, lng], { icon: iconB, draggable: true })
            .bindPopup('Destination: ' + escHtml(name))
            .addTo(userMarkersLayer);

        markerB.on('dragend', function () {
            const pos = markerB.getLatLng();
            reverseGeocode(pos.lat, pos.lng).then(name => {
                if (window.SawariSearch) {
                    SawariSearch.setInputB(pos.lat, pos.lng, name);
                }
                markerB.setPopupContent('Destination: ' + escHtml(name));
            });
        });

        const inputB = document.getElementById('input-b');
        inputB.value = name;
        inputB.dataset.lat = lat;
        inputB.dataset.lng = lng;
        document.getElementById('clear-b').style.display = '';
        checkFindRouteReady();
    }

    function clearPointA() {
        if (markerA) {
            userMarkersLayer.removeLayer(markerA);
            markerA = null;
        }
        const inputA = document.getElementById('input-a');
        inputA.value = '';
        delete inputA.dataset.lat;
        delete inputA.dataset.lng;
        document.getElementById('clear-a').style.display = 'none';
        nextPoint = 'a';
        checkFindRouteReady();
    }

    function clearPointB() {
        if (markerB) {
            userMarkersLayer.removeLayer(markerB);
            markerB = null;
        }
        const inputB = document.getElementById('input-b');
        inputB.value = '';
        delete inputB.dataset.lat;
        delete inputB.dataset.lng;
        document.getElementById('clear-b').style.display = 'none';
        nextPoint = 'b';
        checkFindRouteReady();
    }

    /* ──────────────────────────────────────────────
     *  Enable/disable "Find Route" button
     * ────────────────────────────────────────────── */
    function checkFindRouteReady() {
        const btn = document.getElementById('find-route-btn');
        const inputA = document.getElementById('input-a');
        const inputB = document.getElementById('input-b');
        btn.disabled = !(inputA.dataset.lat && inputB.dataset.lat);
    }

    /* ──────────────────────────────────────────────
     *  Reverse Geocoding (Nominatim)
     * ────────────────────────────────────────────── */
    function reverseGeocode(lat, lng) {
        return fetch(`https://nominatim.openstreetmap.org/reverse?lat=${lat}&lon=${lng}&format=json&zoom=18&addressdetails=1`, {
            headers: { 'Accept-Language': 'en' }
        })
            .then(r => r.json())
            .then(data => {
                if (data && data.display_name) {
                    const parts = data.display_name.split(',').slice(0, 3);
                    return parts.map(p => p.trim()).join(', ');
                }
                return `${lat.toFixed(4)}, ${lng.toFixed(4)}`;
            })
            .catch(() => `${lat.toFixed(4)}, ${lng.toFixed(4)}`);
    }

    /* ──────────────────────────────────────────────
     *  Pin-Pick Mode
     * ────────────────────────────────────────────── */
    function startPinPick(which) {
        pinPickTarget = which;
        document.getElementById('map').classList.add('pin-pick-active');
        const banner = document.getElementById('pin-pick-banner');
        const text = document.getElementById('pin-pick-text');
        text.textContent = which === 'a'
            ? 'Tap the map to set your starting point'
            : 'Tap the map to set your destination';
        banner.style.display = '';
        feather.replace({ 'stroke-width': 1.75 });

        // Highlight active pin button
        document.querySelectorAll('.pin-pick-btn').forEach(b => b.classList.remove('active'));
        const btn = document.getElementById('pin-' + which);
        if (btn) btn.classList.add('active');

        // On mobile, collapse search to show more map
        if (window.innerWidth <= 640) {
            const panel = document.getElementById('search-panel');
            if (!panel.classList.contains('collapsed')) {
                panel.classList.add('collapsed');
                const collapseBtn = document.getElementById('search-collapse-btn');
                const icon = collapseBtn.querySelector('[data-feather], svg');
                if (icon) {
                    const newIcon = document.createElement('i');
                    newIcon.setAttribute('data-feather', 'chevron-down');
                    newIcon.style.width = '18px';
                    newIcon.style.height = '18px';
                    icon.replaceWith(newIcon);
                    feather.replace({ 'stroke-width': 1.75 });
                }
            }
        }
    }

    function cancelPinPick() {
        pinPickTarget = null;
        document.getElementById('map').classList.remove('pin-pick-active');
        document.getElementById('pin-pick-banner').style.display = 'none';
        document.querySelectorAll('.pin-pick-btn').forEach(b => b.classList.remove('active'));
    }

    /* ──────────────────────────────────────────────
     *  Swap Points A ↔ B
     * ────────────────────────────────────────────── */
    function swapPoints() {
        const inputA = document.getElementById('input-a');
        const inputB = document.getElementById('input-b');

        const aVal = inputA.value;
        const aLat = inputA.dataset.lat;
        const aLng = inputA.dataset.lng;
        const bVal = inputB.value;
        const bLat = inputB.dataset.lat;
        const bLng = inputB.dataset.lng;

        // Clear both
        clearPointA();
        clearPointB();

        // Set swapped
        if (bLat && bLng) {
            setPointA(parseFloat(bLat), parseFloat(bLng), bVal);
        }
        if (aLat && aLng) {
            setPointB(parseFloat(aLat), parseFloat(aLng), aVal);
        }
    }

    /* ──────────────────────────────────────────────
     *  My Location (persistent blue dot)
     * ────────────────────────────────────────────── */
    function toggleMyLocation(show) {
        if (show) {
            if (!navigator.geolocation) {
                showToast('Geolocation is not supported by your browser.', 'warning');
                return;
            }
            myLocationWatcher = navigator.geolocation.watchPosition(
                pos => {
                    const { latitude, longitude, accuracy } = pos.coords;
                    const latlng = [latitude, longitude];

                    if (myLocationMarker) {
                        myLocationMarker.setLatLng(latlng);
                        myLocationCircle.setLatLng(latlng);
                        myLocationCircle.setRadius(Math.min(accuracy, 500));
                    } else {
                        const myIcon = L.divIcon({
                            className: 'my-location-marker',
                            html: '<div class="my-location-dot"><div class="my-location-pulse"></div></div>',
                            iconSize: [20, 20],
                            iconAnchor: [10, 10]
                        });
                        myLocationMarker = L.marker(latlng, { icon: myIcon, interactive: false, zIndexOffset: 1000 }).addTo(map);
                        myLocationCircle = L.circle(latlng, {
                            radius: Math.min(accuracy, 500),
                            color: '#3B82F6',
                            fillColor: '#3B82F6',
                            fillOpacity: 0.08,
                            weight: 1,
                            interactive: false
                        }).addTo(map);
                        map.setView(latlng, 15);
                    }
                },
                () => {
                    showToast('Could not track your location.', 'warning');
                },
                { enableHighAccuracy: true, timeout: 10000, maximumAge: 5000 }
            );
        } else {
            if (myLocationWatcher !== null) {
                navigator.geolocation.clearWatch(myLocationWatcher);
                myLocationWatcher = null;
            }
            if (myLocationMarker) {
                map.removeLayer(myLocationMarker);
                myLocationMarker = null;
            }
            if (myLocationCircle) {
                map.removeLayer(myLocationCircle);
                myLocationCircle = null;
            }
        }
    }

    /* ──────────────────────────────────────────────
     *  Geolocation (one-shot, sets Point A)
     * ────────────────────────────────────────────── */
    function locateUser() {
        if (!navigator.geolocation) {
            showToast('Geolocation is not supported by your browser.', 'warning');
            return;
        }

        const btn = document.getElementById('locate-btn');
        btn.disabled = true;

        navigator.geolocation.getCurrentPosition(
            pos => {
                const { latitude, longitude } = pos.coords;
                map.setView([latitude, longitude], STOP_ZOOM);
                setPointA(latitude, longitude, 'My Location');
                nextPoint = 'b';
                btn.disabled = false;
            },
            err => {
                showToast('Could not get your location. Please allow location access.', 'warning');
                btn.disabled = false;
            },
            { enableHighAccuracy: true, timeout: 10000 }
        );
    }

    /* ──────────────────────────────────────────────
     *  Route display helpers — used by routing.js
     * ────────────────────────────────────────────── */

    /** Clear any drawn route polylines */
    function clearRouteDisplay() {
        routeLayerGroup.clearLayers();
    }

    /** 
     * Draw a polyline on the map using a GeoJSON LineString from OSRM.
     * @param {Array} coords — [[lng,lat], ...] from OSRM geometry
     * @param {string} color — CSS color
     * @param {number} weight — line width
     * @param {string} dashArray — e.g. '5,10' for walking paths
     * @returns {L.Polyline}
     */
    function drawPolyline(coords, color, weight, dashArray) {
        // OSRM returns [lng,lat], Leaflet needs [lat,lng]
        const latlngs = coords.map(c => [c[1], c[0]]);
        const line = L.polyline(latlngs, {
            color: color,
            weight: weight || 4,
            opacity: 0.8,
            dashArray: dashArray || null
        }).addTo(routeLayerGroup);
        return line;
    }

    /**
     * Draw a simple straight-line polyline.
     * @param {Array} points — [[lat,lng], ...]
     * @param {string} color
     * @param {number} weight
     * @param {string} dashArray
     */
    function drawStraightLine(points, color, weight, dashArray) {
        const line = L.polyline(points, {
            color: color,
            weight: weight || 3,
            opacity: 0.6,
            dashArray: dashArray || '6,8'
        }).addTo(routeLayerGroup);
        return line;
    }

    /**
     * Add a circle marker at a stop with a popup.
     */
    function addRouteStopMarker(lat, lng, name, cssClass) {
        // Choose icon based on marker type
        let iconHtml, size, anchor;
        if (cssClass === 'marker-stop-boarding') {
            iconHtml = '<div class="marker-pin marker-pin-board"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg></div>';
            size = [28, 36]; anchor = [14, 36];
        } else if (cssClass === 'marker-stop-destination') {
            iconHtml = '<div class="marker-pin marker-pin-dest"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/></svg></div>';
            size = [28, 36]; anchor = [14, 36];
        } else if (cssClass === 'marker-stop-transfer') {
            iconHtml = '<div class="marker-pin marker-pin-transfer"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg></div>';
            size = [28, 36]; anchor = [14, 36];
        } else {
            // Regular intermediate stop — small dot
            iconHtml = '<div class="marker-dot marker-dot-intermediate"></div>';
            size = [10, 10]; anchor = [5, 5];
        }
        const icon = L.divIcon({
            className: cssClass || 'marker-stop',
            html: iconHtml,
            iconSize: size,
            iconAnchor: anchor
        });
        const m = L.marker([lat, lng], { icon })
            .bindPopup(`<strong>${escHtml(name)}</strong>`)
            .addTo(routeLayerGroup);
        return m;
    }

    /**
     * Fit map bounds to show the entire route.
     */
    function fitRouteBounds(allPoints) {
        if (allPoints.length === 0) return;
        const bounds = L.latLngBounds(allPoints);
        map.fitBounds(bounds, { padding: [60, 60], maxZoom: 16 });
    }

    /* ──────────────────────────────────────────────
     *  Toast notifications (simple)
     * ────────────────────────────────────────────── */
    function showToast(message, type) {
        const container = document.getElementById('toast-container');
        if (!container) return;
        // Map common aliases
        const typeMap = { 'error': 'danger', 'success': 'success', 'warning': 'warning', 'info': 'info' };
        const cssType = typeMap[type] || 'info';
        const t = document.createElement('div');
        t.className = 'toast toast-' + cssType;
        t.innerHTML = `<span>${escHtml(message)}</span>`;
        container.appendChild(t);
        setTimeout(() => { t.style.opacity = '0'; t.style.transition = 'opacity 0.3s'; }, 3500);
        setTimeout(() => { t.remove(); }, 4000);
    }

    /* ──────────────────────────────────────────────
     *  Utility
     * ────────────────────────────────────────────── */
    function escHtml(str) {
        const d = document.createElement('div');
        d.textContent = str || '';
        return d.innerHTML;
    }

    function getMap() { return map; }
    function getStops() { return allStops; }
    function getAlertLayer() { return alertLayerGroup; }

    /** Toggle a named layer on/off */
    function toggleLayer(name, visible) {
        const layerMap = {
            stops: stopMarkersLayer,
            routes: routeLayerGroup,
            alerts: alertLayerGroup
        };
        const layer = layerMap[name];
        if (layer) {
            if (visible) { if (!map.hasLayer(layer)) map.addLayer(layer); }
            else { if (map.hasLayer(layer)) map.removeLayer(layer); }
        }
        // Vehicles handled via SawariTracking
        if (name === 'vehicles') {
            if (window.SawariTracking) {
                if (visible) SawariTracking.start();
                else SawariTracking.stop();
            }
        }
    }

    /* ──────────────────────────────────────────────
     *  Public API
     * ────────────────────────────────────────────── */
    return {
        init,
        getMap,
        getStops,
        getAlertLayer,
        toggleLayer,
        setPointA,
        setPointB,
        clearPointA,
        clearPointB,
        clearRouteDisplay,
        drawPolyline,
        drawStraightLine,
        addRouteStopMarker,
        fitRouteBounds,
        showToast,
        checkFindRouteReady,
        escHtml,
        startPinPick,
        cancelPinPick,
        swapPoints,
        toggleMyLocation,
        reverseGeocode,
        BASE
    };
})();

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', SawariMap.init);
