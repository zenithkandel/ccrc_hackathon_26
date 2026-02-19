/**
 * SAWARI — Routing Controller
 * 
 * Route resolution via AJAX calls to the routing engine API.
 * Handles direct routes, multi-route transfers, and result display.
 * Uses OSRM (public demo) for road-path rendering between stops.
 */

const SawariRouting = (function () {
    'use strict';

    const BASE = document.querySelector('meta[name="base-url"]').content;
    const OSRM_BASE = 'https://router.project-osrm.org/route/v1/driving';

    // Colors
    const COLOR_ROUTE = '#1A56DB';   // primary-600
    const COLOR_ROUTE2 = '#7C3AED';  // purple for leg 2
    const COLOR_WALK = '#64748B';    // neutral-500
    const COLOR_TRANSFER = '#D97706'; // amber

    // DOM refs
    let findBtn, resultPanel, resultContent, loadingOverlay;

    // Current results (so user can switch between options)
    let currentResults = null;
    let currentType = null;

    // Active trip (for feedback flow)
    let activeTripId = null;
    let activeTripData = null;

    // Carbon constants: avg car = 0.21 kg CO2/km, bus = 0.089 kg CO2/km
    const CO2_CAR_PER_KM = 0.21;
    const CO2_BUS_PER_KM = 0.089;
    const CO2_ELECTRIC_BUS_PER_KM = 0.02;

    /* ──────────────────────────────────────────────
     *  Init
     * ────────────────────────────────────────────── */
    function init() {
        findBtn = document.getElementById('find-route-btn');
        resultPanel = document.getElementById('result-panel');
        resultContent = document.getElementById('result-content');
        loadingOverlay = document.getElementById('loading-overlay');

        findBtn.addEventListener('click', onFindRoute);

        // Result panel handle — toggle close
        const handle = resultPanel.querySelector('.result-panel-handle');
        if (handle) {
            handle.addEventListener('click', () => {
                resultPanel.classList.toggle('open');
            });
        }
    }

    /* ──────────────────────────────────────────────
     *  Find Route — main trigger
     * ────────────────────────────────────────────── */
    function onFindRoute() {
        const inputA = document.getElementById('input-a');
        const inputB = document.getElementById('input-b');

        const oLat = parseFloat(inputA.dataset.lat);
        const oLng = parseFloat(inputA.dataset.lng);
        const dLat = parseFloat(inputB.dataset.lat);
        const dLng = parseFloat(inputB.dataset.lng);

        if (isNaN(oLat) || isNaN(oLng) || isNaN(dLat) || isNaN(dLng)) {
            SawariMap.showToast('Please select both a starting point and destination.', 'warning');
            return;
        }

        // Show loading
        showLoading(true);
        SawariMap.clearRouteDisplay();
        resultPanel.classList.remove('open');

        // Close search dropdowns
        SawariSearch.closeResults('a');
        SawariSearch.closeResults('b');

        const url = `${BASE}/api/routing-engine.php?action=find-route&origin_lat=${oLat}&origin_lng=${oLng}&dest_lat=${dLat}&dest_lng=${dLng}`;

        fetch(url)
            .then(r => r.json())
            .then(data => {
                showLoading(false);

                if (!data.success) {
                    showError(data.error || 'No route found.', data.type);
                    return;
                }

                currentResults = data.results;
                currentType = data.type;

                // Display the best result (index 0) by default
                if (data.type === 'direct') {
                    displayDirectRoute(data.results[0], data.origin, data.destination, 0);
                } else if (data.type === 'transfer') {
                    displayTransferRoute(data.results[0], data.origin, data.destination, 0);
                }

                // Show option tabs if multiple results
                if (data.results.length > 1) {
                    renderOptionTabs(data.results, data.type, data.origin, data.destination);
                }
            })
            .catch(err => {
                showLoading(false);
                console.error('Route fetch error:', err);
                SawariMap.showToast('Failed to find route. Please try again.', 'error');
            });
    }

    /* ──────────────────────────────────────────────
     *  Display a DIRECT route result
     * ────────────────────────────────────────────── */
    async function displayDirectRoute(result, origin, dest, idx) {
        SawariMap.clearRouteDisplay();

        // Collect all route points for bounds
        const allPoints = [];

        // 1. Walking path: origin → boarding stop
        if (result.walk_to_boarding > 0.01) {
            SawariMap.drawStraightLine(
                [[origin.lat, origin.lng], [result.boarding_stop.lat, result.boarding_stop.lng]],
                COLOR_WALK, 3, '6,8'
            );
        }
        allPoints.push([origin.lat, origin.lng]);

        // 2. Route path via OSRM between consecutive stops
        const stops = result.intermediate_stops;
        await drawRouteSegment(stops, COLOR_ROUTE, allPoints);

        // 3. Walking path: dropoff → destination
        if (result.walk_from_dropoff > 0.01) {
            SawariMap.drawStraightLine(
                [[result.dropoff_stop.lat, result.dropoff_stop.lng], [dest.lat, dest.lng]],
                COLOR_WALK, 3, '6,8'
            );
        }
        allPoints.push([dest.lat, dest.lng]);

        // 4. Add stop markers
        stops.forEach((s, i) => {
            let cssClass = 'marker-stop';
            if (i === 0) cssClass = 'marker-stop-boarding';
            else if (i === stops.length - 1) cssClass = 'marker-stop-destination';
            SawariMap.addRouteStopMarker(
                parseFloat(s.latitude), parseFloat(s.longitude),
                s.name, cssClass
            );
        });

        // 5. Fit bounds
        SawariMap.fitRouteBounds(allPoints);

        // 6. Render result panel
        renderDirectPanel(result, origin, dest, idx);
    }

    /* ──────────────────────────────────────────────
     *  Display a TRANSFER route result
     * ────────────────────────────────────────────── */
    async function displayTransferRoute(result, origin, dest, idx) {
        SawariMap.clearRouteDisplay();

        const allPoints = [];

        // Walking: origin → leg1 boarding
        if (result.walk_to_boarding > 0.01) {
            SawariMap.drawStraightLine(
                [[origin.lat, origin.lng], [result.leg1.boarding_stop.lat, result.leg1.boarding_stop.lng]],
                COLOR_WALK, 3, '6,8'
            );
        }
        allPoints.push([origin.lat, origin.lng]);

        // Leg 1 route path
        await drawRouteSegment(result.leg1.intermediate_stops, COLOR_ROUTE, allPoints);

        // Transfer walk indicator (leg1 dropoff → leg2 boarding — same stop but show it)
        SawariMap.addRouteStopMarker(
            result.transfer_stop.lat, result.transfer_stop.lng,
            'Transfer: ' + result.transfer_stop.name, 'marker-stop-transfer'
        );

        // Leg 2 route path
        await drawRouteSegment(result.leg2.intermediate_stops, COLOR_ROUTE2, allPoints);

        // Walking: leg2 dropoff → destination
        if (result.walk_from_dropoff > 0.01) {
            SawariMap.drawStraightLine(
                [[result.leg2.dropoff_stop.lat, result.leg2.dropoff_stop.lng], [dest.lat, dest.lng]],
                COLOR_WALK, 3, '6,8'
            );
        }
        allPoints.push([dest.lat, dest.lng]);

        // Stop markers for leg 1
        result.leg1.intermediate_stops.forEach((s, i) => {
            let cssClass = 'marker-stop';
            if (i === 0) cssClass = 'marker-stop-boarding';
            SawariMap.addRouteStopMarker(parseFloat(s.latitude), parseFloat(s.longitude), s.name, cssClass);
        });

        // Stop markers for leg 2
        result.leg2.intermediate_stops.forEach((s, i) => {
            let cssClass = 'marker-stop';
            if (i === result.leg2.intermediate_stops.length - 1) cssClass = 'marker-stop-destination';
            SawariMap.addRouteStopMarker(parseFloat(s.latitude), parseFloat(s.longitude), s.name, cssClass);
        });

        // Fit bounds
        SawariMap.fitRouteBounds(allPoints);

        // Render result panel
        renderTransferPanel(result, origin, dest, idx);
    }

    /* ──────────────────────────────────────────────
     *  Draw route segment via OSRM between stops
     * ────────────────────────────────────────────── */
    async function drawRouteSegment(stops, color, allPointsCollector) {
        for (let i = 0; i < stops.length - 1; i++) {
            const fromLat = parseFloat(stops[i].latitude);
            const fromLng = parseFloat(stops[i].longitude);
            const toLat = parseFloat(stops[i + 1].latitude);
            const toLng = parseFloat(stops[i + 1].longitude);

            allPointsCollector.push([fromLat, fromLng]);

            try {
                const osrmUrl = `${OSRM_BASE}/${fromLng},${fromLat};${toLng},${toLat}?overview=full&geometries=geojson`;
                const resp = await fetch(osrmUrl);
                const data = await resp.json();

                if (data.code === 'Ok' && data.routes && data.routes[0]) {
                    const coords = data.routes[0].geometry.coordinates;
                    SawariMap.drawPolyline(coords, color, 5);
                    // Add OSRM coords to bounds
                    coords.forEach(c => allPointsCollector.push([c[1], c[0]]));
                } else {
                    // Fallback: straight line
                    SawariMap.drawStraightLine([[fromLat, fromLng], [toLat, toLng]], color, 4, null);
                }
            } catch (err) {
                // OSRM failed — fallback
                SawariMap.drawStraightLine([[fromLat, fromLng], [toLat, toLng]], color, 4, null);
            }
        }

        // Add last stop
        if (stops.length > 0) {
            const last = stops[stops.length - 1];
            allPointsCollector.push([parseFloat(last.latitude), parseFloat(last.longitude)]);
        }
    }

    /* ──────────────────────────────────────────────
     *  Render result panel — DIRECT route
     * ────────────────────────────────────────────── */
    function renderDirectPanel(result, origin, dest, activeIdx) {
        const esc = SawariMap.escHtml;
        const stops = result.intermediate_stops;
        const vehicle = result.vehicles && result.vehicles.length > 0 ? result.vehicles[0] : null;

        let html = '';

        // Option tabs placeholder (filled by renderOptionTabs if needed)
        html += '<div id="option-tabs"></div>';

        // Walking hint: origin → boarding
        if (result.walk_to_boarding > 0.05) {
            html += `
                <div class="walk-hint">
                    <i data-feather="navigation" style="width:14px;height:14px;"></i>
                    Walk ${formatDistance(result.walk_to_boarding)} to ${esc(result.boarding_stop.name)}
                </div>`;
        }

        // Vehicle info
        if (vehicle) {
            const imgSrc = vehicle.image_path
                ? SawariMap.BASE + '/' + vehicle.image_path
                : '';
            html += `
                <div class="result-vehicle">
                    ${imgSrc ? `<img class="result-vehicle-image" src="${imgSrc}" alt="${esc(vehicle.name)}">` : ''}
                    <div style="flex:1;">
                        <div class="result-vehicle-name">${esc(vehicle.name)}</div>
                        <div class="result-vehicle-type">${esc(result.route_name)}</div>
                        ${vehicle.electric === '1' || vehicle.electric === 1 ? '<span class="badge badge-success" style="font-size:10px;margin-top:2px;">Electric</span>' : ''}
                    </div>
                    ${result.fare !== null ? `<div class="result-fare">Rs. ${Math.round(result.fare)}</div>` : ''}
                </div>`;
        } else {
            html += `
                <div class="result-vehicle">
                    <div style="flex:1;">
                        <div class="result-vehicle-name">${esc(result.route_name)}</div>
                        <div class="result-vehicle-type">${result.stop_count} stops • ${formatDistance(result.distance_km)}</div>
                    </div>
                    ${result.fare !== null ? `<div class="result-fare">Rs. ${Math.round(result.fare)}</div>` : ''}
                </div>`;
        }

        // Conductor tip
        html += `
            <div class="conductor-tip">
                <i data-feather="message-circle" style="width:16px;height:16px;flex-shrink:0;"></i>
                <div>
                    <strong>Tell the conductor:</strong> "${esc(result.boarding_stop.name)}" to "${esc(result.dropoff_stop.name)}"
                </div>
            </div>`;

        // Stops timeline
        html += '<div class="stops-timeline">';
        stops.forEach((s, i) => {
            let cls = 'stop-item';
            if (i === 0) cls += ' stop-item-boarding';
            else if (i === stops.length - 1) cls += ' stop-item-destination';

            let label = esc(s.name);
            if (i === 0) label = '🟢 Board here — ' + label;
            else if (i === stops.length - 1) label = '🔴 Get off here — ' + label;

            html += `<div class="${cls}">${label}</div>`;
        });
        html += '</div>';

        // Walking hint: dropoff → destination
        if (result.walk_from_dropoff > 0.05) {
            html += `
                <div class="walk-hint">
                    <i data-feather="navigation" style="width:14px;height:14px;"></i>
                    Walk ${formatDistance(result.walk_from_dropoff)} to your destination
                </div>`;
        }

        // Wait time estimate
        html += renderWaitTime(stops.length);

        // Carbon card
        const isElectric = vehicle && (vehicle.electric === '1' || vehicle.electric === 1);
        html += renderCarbonCard(result.distance_km || 0, isElectric);

        // Tourist tips (collapsible)
        html += renderTouristTips();

        // Start trip button
        html += `
            <div style="display:flex;gap:var(--space-3);margin-top:var(--space-4);">
                <button class="btn btn-primary btn-block" id="start-trip-btn">
                    <i data-feather="play" style="width:16px;height:16px;"></i>
                    Start Trip
                </button>
            </div>`;

        resultContent.innerHTML = html;
        resultPanel.classList.add('open');
        feather.replace({ 'stroke-width': 1.75 });

        // Attach start trip handler
        const startBtn = document.getElementById('start-trip-btn');
        if (startBtn) {
            startBtn.addEventListener('click', () => {
                logTrip(result, 'direct');
                startBtn.textContent = 'Trip Started!';
                startBtn.disabled = true;
                startBtn.classList.remove('btn-primary');
                startBtn.classList.add('btn-success');
                // Show feedback button after 5 seconds
                setTimeout(() => {
                    startBtn.outerHTML = `<button class="btn btn-accent btn-block" id="feedback-trip-btn">
                        <i data-feather="star" style="width:16px;height:16px;"></i>
                        Rate This Trip
                    </button>`;
                    feather.replace({ 'stroke-width': 1.75 });
                    document.getElementById('feedback-trip-btn').addEventListener('click', promptFeedback);
                }, 5000);
            });
        }

        // If there are multiple options, render tabs
        if (currentResults && currentResults.length > 1) {
            renderOptionTabs(currentResults, currentType,
                { lat: origin.lat, lng: origin.lng },
                { lat: dest.lat, lng: dest.lng },
                activeIdx
            );
        }
    }

    /* ──────────────────────────────────────────────
     *  Render result panel — TRANSFER route
     * ────────────────────────────────────────────── */
    function renderTransferPanel(result, origin, dest, activeIdx) {
        const esc = SawariMap.escHtml;
        const v1 = result.leg1.vehicles && result.leg1.vehicles.length > 0 ? result.leg1.vehicles[0] : null;
        const v2 = result.leg2.vehicles && result.leg2.vehicles.length > 0 ? result.leg2.vehicles[0] : null;

        let html = '';
        html += '<div id="option-tabs"></div>';

        // Walking to boarding
        if (result.walk_to_boarding > 0.05) {
            html += `
                <div class="walk-hint">
                    <i data-feather="navigation" style="width:14px;height:14px;"></i>
                    Walk ${formatDistance(result.walk_to_boarding)} to ${esc(result.leg1.boarding_stop.name)}
                </div>`;
        }

        // LEG 1 header
        html += `<div style="font-size:var(--text-xs);font-weight:var(--font-semibold);color:var(--color-primary-600);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:var(--space-2);">Leg 1</div>`;

        // Vehicle 1
        html += renderVehicleCard(v1, result.leg1, COLOR_ROUTE);

        // Conductor tip leg 1
        html += `
            <div class="conductor-tip">
                <i data-feather="message-circle" style="width:16px;height:16px;flex-shrink:0;"></i>
                <div><strong>Tell conductor:</strong> "${esc(result.leg1.boarding_stop.name)}" to "${esc(result.leg1.dropoff_stop.name)}"</div>
            </div>`;

        // Stops timeline leg 1
        html += renderStopsTimeline(result.leg1.intermediate_stops, false);

        // Transfer indicator
        html += `
            <div style="display:flex;align-items:center;gap:var(--space-2);padding:var(--space-3) 0;margin:var(--space-2) 0;border-top:1px dashed var(--color-neutral-200);border-bottom:1px dashed var(--color-neutral-200);">
                <div style="width:14px;height:14px;border-radius:50%;background:${COLOR_TRANSFER};border:2px solid #fff;flex-shrink:0;"></div>
                <div>
                    <div style="font-weight:var(--font-semibold);font-size:var(--text-sm);color:var(--color-neutral-800);">Transfer at ${esc(result.transfer_stop.name)}</div>
                    <div style="font-size:var(--text-xs);color:var(--color-neutral-400);">Switch to next vehicle</div>
                </div>
            </div>`;

        // LEG 2 header
        html += `<div style="font-size:var(--text-xs);font-weight:var(--font-semibold);color:#7C3AED;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:var(--space-2);">Leg 2</div>`;

        // Vehicle 2
        html += renderVehicleCard(v2, result.leg2, COLOR_ROUTE2);

        // Conductor tip leg 2
        html += `
            <div class="conductor-tip">
                <i data-feather="message-circle" style="width:16px;height:16px;flex-shrink:0;"></i>
                <div><strong>Tell conductor:</strong> "${esc(result.leg2.boarding_stop.name)}" to "${esc(result.leg2.dropoff_stop.name)}"</div>
            </div>`;

        // Stops timeline leg 2
        html += renderStopsTimeline(result.leg2.intermediate_stops, true);

        // Walking to destination
        if (result.walk_from_dropoff > 0.05) {
            html += `
                <div class="walk-hint">
                    <i data-feather="navigation" style="width:14px;height:14px;"></i>
                    Walk ${formatDistance(result.walk_from_dropoff)} to your destination
                </div>`;
        }

        // Total fare
        if (result.total_fare !== null) {
            html += `
                <div style="text-align:center;margin-top:var(--space-4);padding:var(--space-3);background:var(--color-neutral-50);border-radius:var(--radius-lg);">
                    <div style="font-size:var(--text-xs);color:var(--color-neutral-500);">Total Fare</div>
                    <div class="result-fare">Rs. ${Math.round(result.total_fare)}</div>
                </div>`;
        }

        // Wait time estimate
        html += renderWaitTime((result.leg1.intermediate_stops || []).length);

        // Carbon card
        const isElec1 = v1 && (v1.electric === '1' || v1.electric === 1);
        html += renderCarbonCard(result.total_distance || 0, isElec1);

        // Tourist tips
        html += renderTouristTips();

        // Start trip button
        html += `
            <div style="display:flex;gap:var(--space-3);margin-top:var(--space-4);">
                <button class="btn btn-primary btn-block" id="start-trip-btn">
                    <i data-feather="play" style="width:16px;height:16px;"></i>
                    Start Trip
                </button>
            </div>`;

        resultContent.innerHTML = html;
        resultPanel.classList.add('open');
        feather.replace({ 'stroke-width': 1.75 });

        // Attach start trip handler
        const startBtn = document.getElementById('start-trip-btn');
        if (startBtn) {
            startBtn.addEventListener('click', () => {
                logTrip(result, 'transfer');
                startBtn.textContent = 'Trip Started!';
                startBtn.disabled = true;
                startBtn.classList.remove('btn-primary');
                startBtn.classList.add('btn-success');
                setTimeout(() => {
                    startBtn.outerHTML = `<button class="btn btn-accent btn-block" id="feedback-trip-btn">
                        <i data-feather="star" style="width:16px;height:16px;"></i>
                        Rate This Trip
                    </button>`;
                    feather.replace({ 'stroke-width': 1.75 });
                    document.getElementById('feedback-trip-btn').addEventListener('click', promptFeedback);
                }, 5000);
            });
        }

        if (currentResults && currentResults.length > 1) {
            renderOptionTabs(currentResults, currentType,
                { lat: origin.lat, lng: origin.lng },
                { lat: dest.lat, lng: dest.lng },
                activeIdx
            );
        }
    }

    /* ──────────────────────────────────────────────
     *  Shared render helpers
     * ────────────────────────────────────────────── */
    function renderVehicleCard(vehicle, leg, borderColor) {
        const esc = SawariMap.escHtml;
        if (vehicle) {
            const imgSrc = vehicle.image_path ? SawariMap.BASE + '/' + vehicle.image_path : '';
            return `
                <div class="result-vehicle" style="border-left:3px solid ${borderColor};">
                    ${imgSrc ? `<img class="result-vehicle-image" src="${imgSrc}" alt="${esc(vehicle.name)}">` : ''}
                    <div style="flex:1;">
                        <div class="result-vehicle-name">${esc(vehicle.name)}</div>
                        <div class="result-vehicle-type">${esc(leg.route_name)}</div>
                    </div>
                    ${leg.fare !== null ? `<div class="result-fare">Rs. ${Math.round(leg.fare)}</div>` : ''}
                </div>`;
        }
        return `
            <div class="result-vehicle" style="border-left:3px solid ${borderColor};">
                <div style="flex:1;">
                    <div class="result-vehicle-name">${esc(leg.route_name)}</div>
                    <div class="result-vehicle-type">${formatDistance(leg.distance_km)}</div>
                </div>
                ${leg.fare !== null ? `<div class="result-fare">Rs. ${Math.round(leg.fare)}</div>` : ''}
            </div>`;
    }

    function renderStopsTimeline(stops, isLeg2) {
        const esc = SawariMap.escHtml;
        let html = '<div class="stops-timeline">';
        stops.forEach((s, i) => {
            let cls = 'stop-item';
            if (i === 0 && !isLeg2) cls += ' stop-item-boarding';
            else if (i === 0 && isLeg2) cls += ' stop-item-transfer';
            else if (i === stops.length - 1 && isLeg2) cls += ' stop-item-destination';
            else if (i === stops.length - 1 && !isLeg2) cls += ' stop-item-transfer';

            let label = esc(s.name);
            if (i === 0 && !isLeg2) label = '🟢 Board — ' + label;
            else if (i === stops.length - 1 && isLeg2) label = '🔴 Get off — ' + label;
            else if ((i === stops.length - 1 && !isLeg2) || (i === 0 && isLeg2)) label = '🟡 Transfer — ' + label;

            html += `<div class="${cls}">${label}</div>`;
        });
        html += '</div>';
        return html;
    }

    /* ──────────────────────────────────────────────
     *  Option tabs (when multiple route results)
     * ────────────────────────────────────────────── */
    function renderOptionTabs(results, type, origin, dest, activeIdx) {
        const tabContainer = document.getElementById('option-tabs');
        if (!tabContainer) return;

        activeIdx = activeIdx || 0;

        let html = '<div style="display:flex;gap:var(--space-2);margin-bottom:var(--space-4);overflow-x:auto;">';
        results.forEach((r, i) => {
            const isActive = i === activeIdx;
            const label = type === 'direct'
                ? `Option ${i + 1} • ${formatDistance(r.distance_km)}`
                : `Option ${i + 1} • ${formatDistance(r.total_distance)}`;
            const fare = type === 'direct' ? r.fare : r.total_fare;
            const fareStr = fare !== null ? ` • Rs.${Math.round(fare)}` : '';

            html += `
                <button class="btn ${isActive ? 'btn-primary' : 'btn-secondary'} btn-sm"
                        data-route-idx="${i}"
                        style="white-space:nowrap;flex-shrink:0;">
                    ${label}${fareStr}
                </button>`;
        });
        html += '</div>';
        tabContainer.innerHTML = html;

        // Attach click events
        tabContainer.querySelectorAll('[data-route-idx]').forEach(btn => {
            btn.addEventListener('click', () => {
                const idx = parseInt(btn.dataset.routeIdx);
                if (type === 'direct') {
                    displayDirectRoute(results[idx], origin, dest, idx);
                } else {
                    displayTransferRoute(results[idx], origin, dest, idx);
                }
            });
        });
    }

    /* ──────────────────────────────────────────────
     *  Error display
     * ────────────────────────────────────────────── */
    function showError(message, type) {
        const esc = SawariMap.escHtml;

        let hint = '';
        if (type === 'no_origin_stops') {
            hint = 'Try tapping closer to a main road or searching for a nearby stop.';
        } else if (type === 'no_dest_stops') {
            hint = 'Try a destination closer to a main road.';
        } else if (type === 'no_route_found') {
            hint = 'The stops may not be connected by any routes yet. Try different locations.';
        }

        resultContent.innerHTML = `
            <div style="text-align:center;padding:var(--space-6) var(--space-4);">
                <i data-feather="alert-circle" style="width:40px;height:40px;color:var(--color-neutral-300);margin-bottom:var(--space-3);"></i>
                <p style="font-size:var(--text-sm);color:var(--color-neutral-700);margin-bottom:var(--space-2);">${esc(message)}</p>
                ${hint ? `<p style="font-size:var(--text-xs);color:var(--color-neutral-400);">${esc(hint)}</p>` : ''}
            </div>`;

        resultPanel.classList.add('open');
        feather.replace({ 'stroke-width': 1.75 });
    }

    /* ──────────────────────────────────────────────
     *  Loading overlay
     * ────────────────────────────────────────────── */
    function showLoading(show) {
        if (loadingOverlay) {
            loadingOverlay.style.display = show ? 'flex' : 'none';
        }
        findBtn.disabled = show;
    }

    /* ──────────────────────────────────────────────
     *  Utilities
     * ────────────────────────────────────────────── */
    function formatDistance(km) {
        if (km === null || km === undefined) return '';
        if (km < 1) return Math.round(km * 1000) + ' m';
        return km.toFixed(1) + ' km';
    }

    /* ──────────────────────────────────────────────
     *  Carbon Emission Calculator
     * ────────────────────────────────────────────── */
    function calculateCarbon(distanceKm, isElectric) {
        const busRate = isElectric ? CO2_ELECTRIC_BUS_PER_KM : CO2_BUS_PER_KM;
        const busCO2 = distanceKm * busRate;
        const carCO2 = distanceKm * CO2_CAR_PER_KM;
        const saved = Math.max(0, carCO2 - busCO2);
        return { busCO2: busCO2.toFixed(3), carCO2: carCO2.toFixed(3), saved: saved.toFixed(3) };
    }

    function renderCarbonCard(distanceKm, isElectric) {
        const carbon = calculateCarbon(distanceKm, isElectric);
        return `
            <div class="carbon-card">
                <div class="carbon-card-header">
                    <i data-feather="wind" style="width:16px;height:16px;"></i>
                    <span>Carbon Emissions</span>
                </div>
                <div class="carbon-card-body">
                    <div class="carbon-row">
                        <span>🚌 Bus trip</span>
                        <span>${carbon.busCO2} kg CO₂</span>
                    </div>
                    <div class="carbon-row">
                        <span>🚗 If by car/taxi</span>
                        <span>${carbon.carCO2} kg CO₂</span>
                    </div>
                    <div class="carbon-saved">
                        🌱 You save <strong>${carbon.saved} kg CO₂</strong> by taking the bus!
                    </div>
                </div>
            </div>`;
    }

    /* ──────────────────────────────────────────────
     *  Tourist Help Mode
     * ────────────────────────────────────────────── */
    function renderTouristTips() {
        return `
            <div class="tourist-tips">
                <div class="tourist-tips-header">
                    <i data-feather="help-circle" style="width:16px;height:16px;"></i>
                    <span>Tourist Tips</span>
                </div>
                <ul class="tourist-tips-list">
                    <li><strong>Boarding:</strong> Wave at the bus to stop. Tell the conductor your destination clearly.</li>
                    <li><strong>Fare:</strong> Pay the conductor after boarding. Keep small change ready (NPR notes).</li>
                    <li><strong>Exits:</strong> Shout "Roknu!" (Stop!) when approaching your stop.</li>
                    <li><strong>Safety:</strong> Keep bags on your lap. Watch for pickpockets in crowded buses.</li>
                    <li><strong>Peak Hours:</strong> Avoid 8-10 AM and 4-6 PM if possible — buses are extremely crowded.</li>
                </ul>
            </div>`;
    }

    /* ──────────────────────────────────────────────
     *  Estimated Wait Time
     * ────────────────────────────────────────────── */
    function renderWaitTime(routeStopCount) {
        // Rough estimate: routes with fewer stops run more frequently
        let avgMins;
        if (routeStopCount <= 5) avgMins = '5-10';
        else if (routeStopCount <= 10) avgMins = '10-15';
        else if (routeStopCount <= 20) avgMins = '15-25';
        else avgMins = '20-35';
        return `<div class="wait-time-hint">
                    <i data-feather="clock" style="width:14px;height:14px;"></i>
                    Estimated wait: ~${avgMins} min (based on route frequency)
                </div>`;
    }

    /* ──────────────────────────────────────────────
     *  Trip Logging
     * ────────────────────────────────────────────── */
    function logTrip(result, type) {
        const isElectric = (result.vehicles && result.vehicles[0] && (result.vehicles[0].electric === 1 || result.vehicles[0].electric === '1'));
        const distKm = type === 'direct' ? result.distance_km : result.total_distance;
        const carbon = calculateCarbon(distKm || 0, isElectric);

        const data = {
            route_id: result.route_id || (result.leg1 ? result.leg1.route_id : 0),
            vehicle_id: (result.vehicles && result.vehicles[0]) ? result.vehicles[0].vehicle_id : 0,
            boarding_stop_id: type === 'direct' ? result.boarding_stop.location_id : result.leg1.boarding_stop.location_id,
            destination_stop_id: type === 'direct' ? result.dropoff_stop.location_id : result.leg2.dropoff_stop.location_id,
            fare_paid: type === 'direct' ? (result.fare || 0) : (result.total_fare || 0),
            carbon_saved: carbon.saved
        };

        if (type === 'transfer') {
            data.transfer_stop_id = result.transfer_stop.location_id;
            data.second_route_id = result.leg2.route_id;
        }

        const fd = new FormData();
        Object.keys(data).forEach(k => { if (data[k]) fd.append(k, data[k]); });

        fetch(BASE + '/api/trips.php?action=log', { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(r => r.json())
            .then(res => {
                if (res.success && res.trip_id) {
                    activeTripId = res.trip_id;
                    activeTripData = data;
                }
            })
            .catch(err => console.error('Trip log error:', err));
    }

    /** Show the feedback modal (defined in map.php) */
    function promptFeedback() {
        if (!activeTripId) return;
        const modal = document.getElementById('feedback-modal');
        if (modal) {
            document.getElementById('feedback-trip-id').value = activeTripId;
            modal.style.display = 'flex';
        }
    }

    function getActiveTripId() { return activeTripId; }

    /* ──────────────────────────────────────────────
     *  Public API
     * ────────────────────────────────────────────── */
    return {
        init,
        logTrip,
        promptFeedback,
        getActiveTripId,
        calculateCarbon
    };
})();

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', SawariRouting.init);
