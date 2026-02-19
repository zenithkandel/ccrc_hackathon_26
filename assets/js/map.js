/**
 * ═══════════════════════════════════════════════════════════
 * Sawari — Map & Route Display (Enhanced)
 * ═══════════════════════════════════════════════════════════
 *
 * Leaflet map, markers, polylines, route result rendering,
 * user location marker, and feedback modal.
 */

const SawariMap = (function () {
    'use strict';

    const CONFIG = window.SAWARI_MAP_CONFIG || {};

    let map = null;
    let stopsLayer = null;
    let resultLayers = null;
    let startMarker = null;
    let endMarker = null;
    let userLocMarker = null;
    let currentRouteData = null;
    let touristMode = false;

    const icons = { start: null, end: null, stop: null, landmark: null, userLoc: null };

    const ROUTE_COLORS = [
        '#2563eb', '#dc2626', '#16a34a', '#d97706',
        '#7c3aed', '#0891b2', '#be185d', '#4f46e5'
    ];

    // ─── Initialize ─────────────────────────────────────────
    function init() {
        if (!document.getElementById('map')) return;

        map = L.map('map', { zoomControl: false }).setView(
            [CONFIG.defaultLat || 27.7172, CONFIG.defaultLng || 85.3240],
            CONFIG.defaultZoom || 13
        );

        L.control.zoom({ position: 'topright' }).addTo(map);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
            maxZoom: 19
        }).addTo(map);

        stopsLayer = L.layerGroup().addTo(map);
        resultLayers = L.layerGroup().addTo(map);

        createIcons();
        loadStops();
        initUI();
    }

    // ─── Custom Marker Icons ────────────────────────────────
    function createIcons() {
        icons.start = L.divIcon({
            className: 'sawari-marker',
            html: '<div class="marker-pin marker-start"><span>A</span></div>',
            iconSize: [32, 42],
            iconAnchor: [16, 42],
            popupAnchor: [0, -44]
        });

        icons.end = L.divIcon({
            className: 'sawari-marker',
            html: '<div class="marker-pin marker-end"><span>B</span></div>',
            iconSize: [32, 42],
            iconAnchor: [16, 42],
            popupAnchor: [0, -44]
        });

        icons.stop = L.divIcon({
            className: 'sawari-marker',
            html: '<div class="marker-dot marker-dot-stop"></div>',
            iconSize: [12, 12],
            iconAnchor: [6, 6]
        });

        icons.landmark = L.divIcon({
            className: 'sawari-marker',
            html: '<div class="marker-dot marker-dot-landmark"></div>',
            iconSize: [12, 12],
            iconAnchor: [6, 6]
        });

        icons.userLoc = L.divIcon({
            className: 'sawari-marker',
            html: '<div class="marker-user-loc"><div class="marker-user-pulse"></div><div class="marker-user-dot"></div></div>',
            iconSize: [24, 24],
            iconAnchor: [12, 12]
        });
    }

    // ─── Load All Approved Stops ────────────────────────────
    function loadStops() {
        SawariUtils.apiFetch('api/locations/read.php?status=approved&limit=500')
            .then(function (data) {
                if (!data || !data.data) return;
                data.data.forEach(function (loc) {
                    var icon = loc.type === 'landmark' ? icons.landmark : icons.stop;
                    var marker = L.marker([loc.latitude, loc.longitude], { icon: icon })
                        .bindPopup(
                            '<div class="stop-popup">' +
                            '<strong>' + SawariUtils.escapeHTML(loc.name) + '</strong>' +
                            '<span class="stop-popup-type">' + loc.type + '</span>' +
                            '</div>'
                        );
                    stopsLayer.addLayer(marker);
                });
            })
            .catch(function () { });
    }

    // ─── UI Bindings ────────────────────────────────────────
    function initUI() {
        var closeBtn = document.getElementById('resultsPanelClose');
        if (closeBtn) closeBtn.addEventListener('click', closeResults);

        var alertClose = document.getElementById('alertBannerClose');
        if (alertClose) {
            alertClose.addEventListener('click', function () {
                document.getElementById('alertBanner').style.display = 'none';
            });
        }

        initFeedbackModal();
        initTouristMode();
    }

    // ─── Markers ────────────────────────────────────────────
    function setStartMarker(lat, lng, name) {
        if (startMarker) map.removeLayer(startMarker);
        startMarker = L.marker([lat, lng], { icon: icons.start })
            .addTo(map)
            .bindPopup('<strong>Start:</strong> ' + SawariUtils.escapeHTML(name || 'Your Location'));
    }

    function setEndMarker(lat, lng, name) {
        if (endMarker) map.removeLayer(endMarker);
        endMarker = L.marker([lat, lng], { icon: icons.end })
            .addTo(map)
            .bindPopup('<strong>Destination:</strong> ' + SawariUtils.escapeHTML(name || 'Destination'));
    }

    function setUserLocationMarker(lat, lng) {
        if (userLocMarker) map.removeLayer(userLocMarker);
        userLocMarker = L.marker([lat, lng], { icon: icons.userLoc, zIndexOffset: 1000 })
            .addTo(map)
            .bindPopup('You are here');
        map.setView([lat, lng], 15);
    }

    function fitToPoints(points) {
        if (points.length === 0) return;
        var bounds = L.latLngBounds(points.map(function (p) { return [p[0], p[1]]; }));
        map.fitBounds(bounds, { padding: [80, 80], maxZoom: 15 });
    }

    // ─── Loading ────────────────────────────────────────────
    function showLoading() {
        var el = document.getElementById('mapLoading');
        if (el) el.style.display = 'flex';
    }
    function hideLoading() {
        var el = document.getElementById('mapLoading');
        if (el) el.style.display = 'none';
    }

    // ─── Display Route Results (Enhanced) ───────────────────
    function displayResults(data) {
        currentRouteData = data;
        resultLayers.clearLayers();

        // Remove the user-location marker so it doesn't duplicate with result markers
        if (userLocMarker) { map.removeLayer(userLocMarker); userLocMarker = null; }

        var panel = document.getElementById('resultsPanel');
        var body = document.getElementById('resultsPanelBody');
        var footer = document.getElementById('resultsPanelFooter');

        if (!data || !data.success) {
            body.innerHTML =
                '<div class="no-route">' +
                '<div class="no-route-icon"><i class="fa-duotone fa-solid fa-face-frown-open" style="font-size:48px;color:#94a3b8;"></i></div>' +
                '<h3>No Route Found</h3>' +
                '<p>' + SawariUtils.escapeHTML(data && data.message ? data.message : 'Could not find a route between these locations.') + '</p>' +
                '</div>';
            footer.style.display = 'none';
            panel.classList.add('active');
            return;
        }

        var result = data;
        var html = '';

        // ── Alerts
        if (result.summary && result.summary.alerts && result.summary.alerts.length > 0) {
            html += '<div class="result-alerts">';
            result.summary.alerts.forEach(function (alert) {
                html += '<div class="result-alert">' +
                    '<i class="fa-sharp-duotone fa-solid fa-triangle-exclamation"></i>' +
                    '<div><strong>' + SawariUtils.escapeHTML(alert.name) + '</strong>' +
                    (alert.description ? '<br><small>' + SawariUtils.escapeHTML(alert.description) + '</small>' : '') +
                    '</div></div>';
            });
            html += '</div>';
        }

        // ── Summary cards
        if (result.summary) {
            var s = result.summary;
            html += '<div class="summary-grid">';
            html += summaryCard('Distance', (s.total_distance_km || 0).toFixed(1) + ' km', '#2563eb',
                '<i class="fa-duotone fa-solid fa-location-dot"></i>');
            html += summaryCard('Fare', 'NPR ' + (s.total_fare || 0), '#16a34a',
                '<i class="fa-duotone fa-solid fa-dollar-sign"></i>');
            html += summaryCard('Duration', '~' + (s.estimated_duration_min || 0) + ' min', '#d97706',
                '<i class="fa-duotone fa-solid fa-clock"></i>');
            html += summaryCard('Transfers', (s.transfers || 0).toString(), '#7c3aed',
                '<i class="fa-duotone fa-solid fa-arrows-rotate"></i>');
            html += '</div>';
        }

        // ── Segments timeline
        if (result.segments && result.segments.length > 0) {
            html += '<div class="segments-timeline">';
            var colorIdx = 0;

            result.segments.forEach(function (seg, i) {
                if (seg.type === 'walking') {
                    html += walkingSegmentHTML(seg);
                    if (seg.geometry) drawWalkingLine(seg.geometry);
                    else if (seg.from && seg.to) drawWalkingLine([[seg.from.lat, seg.from.lng], [seg.to.lat, seg.to.lng]]);

                    // Add user location / destination markers for walking segments
                    if (seg.from && seg.from.name === 'Your Location') {
                        addUserMarker(seg.from.lat, seg.from.lng, 'Your Location');
                    }
                    if (seg.to && seg.to.name === 'Your Destination') {
                        addUserMarker(seg.to.lat, seg.to.lng, 'Your Destination');
                    }

                } else if (seg.type === 'riding') {
                    var color = ROUTE_COLORS[colorIdx % ROUTE_COLORS.length];
                    colorIdx++;
                    html += ridingSegmentHTML(seg, color);
                    if (seg.path_coordinates) drawRidingLine(seg.path_coordinates, color);
                    else if (seg.from && seg.to) drawRidingLine([[seg.from.lat, seg.from.lng], [seg.to.lat, seg.to.lng]], color);

                    // Add stop markers for riding segments
                    if (seg.from) addStopMarker(seg.from.lat, seg.from.lng, seg.from.name, color);
                    if (seg.to) addStopMarker(seg.to.lat, seg.to.lng, seg.to.name, color);

                } else if (seg.type === 'transfer') {
                    html += transferSegmentHTML(seg);
                }
            });

            html += '</div>';
        }

        // ── Carbon card
        if (result.summary && result.summary.total_distance_km) {
            html += carbonCardHTML(result.summary.total_distance_km);
        }

        // ── Tourist tips
        if (touristMode) html += buildTouristTips(result);

        body.innerHTML = html;
        footer.style.display = '';
        panel.classList.add('active');

        // Expand/collapse segment details
        body.querySelectorAll('.seg-expand-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var details = btn.closest('.seg-card').querySelector('.seg-details');
                if (details) {
                    var expanded = details.style.display !== 'none';
                    details.style.display = expanded ? 'none' : 'block';
                    btn.classList.toggle('expanded', !expanded);
                }
            });
        });

        collectAndFitBounds(result);
    }

    // ─── Segment HTML Builders ──────────────────────────────

    function summaryCard(label, value, color, iconSvg) {
        return '<div class="summary-card">' +
            '<div class="summary-card-icon" style="color:' + color + '">' + iconSvg + '</div>' +
            '<div class="summary-card-value" style="color:' + color + '">' + value + '</div>' +
            '<div class="summary-card-label">' + label + '</div></div>';
    }

    function walkingSegmentHTML(seg) {
        var fromName = SawariUtils.escapeHTML(seg.from.name);
        var toName = SawariUtils.escapeHTML(seg.to.name);
        var isFromUser = (seg.from.name === 'Your Location');
        var isToUser = (seg.to.name === 'Your Destination');

        var title = isFromUser
            ? 'Walk to ' + toName + ' bus stop'
            : isToUser
                ? 'Walk to your destination'
                : 'Walk to ' + toName;

        return '<div class="seg-card seg-walking">' +
            '<div class="seg-timeline-dot seg-dot-walk"><i class="fa-duotone fa-solid fa-person-walking"></i></div>' +
            '<div class="seg-card-body">' +
            '<div class="seg-header">' +
            '<div class="seg-title">' + title + '</div>' +
            '</div>' +
            '<div class="seg-walk-summary">' +
            '<span class="seg-walk-stat"><i class="fa-solid fa-location-dot"></i> ' + (seg.distance_m || 0) + 'm</span>' +
            (seg.duration_min ? '<span class="seg-walk-stat"><i class="fa-solid fa-clock"></i> ~' + seg.duration_min + ' min</span>' : '') +
            '</div>' +
            '<div class="seg-route-line seg-walk-line">' +
            '<span class="seg-stop-name"><strong>' + fromName + '</strong></span>' +
            '<span class="seg-arrow">↓</span>' +
            '<span class="seg-stop-name"><strong>' + toName + '</strong></span></div>' +
            (seg.directions ? '<div class="seg-directions">' + SawariUtils.escapeHTML(seg.directions) + '</div>' : '') +
            '</div></div>';
    }

    function ridingSegmentHTML(seg, color) {
        var vehicleName = seg.vehicle ? SawariUtils.escapeHTML(seg.vehicle.name) : '';
        var vehicleImage = (seg.vehicle && seg.vehicle.image) ? seg.vehicle.image : '';

        // --- Vehicle identification card (always visible, prominent) ---
        var vehicleCard = '';
        if (seg.vehicle) {
            vehicleCard = '<div class="seg-vehicle-card">';
            if (vehicleImage) {
                vehicleCard += '<img class="seg-vehicle-img" src="' + CONFIG.baseUrl + '/' + SawariUtils.escapeHTML(vehicleImage) + '" alt="' + vehicleName + '" onerror="this.style.display=\'none\'">';
            }
            vehicleCard += '<div class="seg-vehicle-info">' +
                '<div class="seg-vehicle-label">Board this bus</div>' +
                '<div class="seg-vehicle-name">' + vehicleName + '</div>';
            if (seg.vehicle.starts_at && seg.vehicle.stops_at) {
                vehicleCard += '<div class="seg-vehicle-hours"><i class="fa-solid fa-clock"></i> ' + seg.vehicle.starts_at + ' – ' + seg.vehicle.stops_at + '</div>';
            }
            vehicleCard += '</div></div>';
        }

        var html = '<div class="seg-card seg-riding">' +
            '<div class="seg-timeline-dot seg-dot-ride" style="background:' + color + '"><i class="fa-solid fa-bus" style="color:#fff;"></i></div>' +
            '<div class="seg-card-body">' +
            '<div class="seg-header">' +
            '<div class="seg-title">' + SawariUtils.escapeHTML(seg.route_name || 'Bus') + '</div>' +
            '<button class="seg-expand-btn" title="Show details"><i class="fa-solid fa-chevron-down"></i></button>' +
            '</div>' +
            vehicleCard;

        // Conductor instruction — always visible
        if (seg.conductor_instruction) {
            html += '<div class="seg-conductor-visible"><i class="fa-solid fa-circle-info"></i>' +
                '<span>' + SawariUtils.escapeHTML(seg.conductor_instruction) + '</span></div>';
        }

        html += '<div class="seg-route-line" style="border-left-color:' + color + '">' +
            '<span class="seg-stop-dot" style="background:' + color + '"></span>' +
            '<span class="seg-stop-name"><strong>Board at: ' + SawariUtils.escapeHTML(seg.from.name) + '</strong></span>';

        // Intermediate stops
        if (seg.stops_in_between && seg.stops_in_between.length > 0) {
            seg.stops_in_between.forEach(function (s) {
                html += '<span class="seg-stop-dot seg-stop-dot-sm" style="background:' + color + '"></span>' +
                    '<span class="seg-stop-name seg-stop-via">' + SawariUtils.escapeHTML(s) + '</span>';
            });
        }

        html += '<span class="seg-stop-dot" style="background:' + color + '"></span>' +
            '<span class="seg-stop-name"><strong>Drop off at: ' + SawariUtils.escapeHTML(seg.to.name) + '</strong></span>' +
            '</div>';

        if (seg.fare) {
            html += '<span class="seg-fare" style="background:' + color + '15;color:' + color + '">NPR ' + seg.fare + '</span>';
        }

        // Collapsible details (secondary info only)
        html += '<div class="seg-details" style="display:none">';
        if (seg.vehicle) {
            html += '<div class="seg-detail-row"><span class="seg-detail-label">Vehicle</span><span>' + vehicleName + '</span></div>';
        }
        html += '</div>'; // end details

        html += '</div></div>'; // end card
        return html;
    }

    function transferSegmentHTML(seg) {
        return '<div class="seg-card seg-transfer">' +
            '<div class="seg-timeline-dot seg-dot-transfer"><i class="fa-duotone fa-solid fa-arrows-rotate"></i></div>' +
            '<div class="seg-card-body">' +
            '<div class="seg-title">Transfer</div>' +
            (seg.instruction ? '<div class="seg-transfer-info">' + SawariUtils.escapeHTML(seg.instruction) + '</div>' : '') +
            (seg.wait_time_estimate ? '<div class="seg-wait">Est. wait: ' + SawariUtils.escapeHTML(seg.wait_time_estimate) + '</div>' : '') +
            '</div></div>';
    }

    function carbonCardHTML(distKm) {
        var co2Public = (0.089 * distKm).toFixed(2);
        var co2Bike = (0.103 * distKm).toFixed(2);
        var co2Car = (0.192 * distKm).toFixed(2);
        var savings = (co2Car - co2Public).toFixed(2);

        return '<div class="carbon-card">' +
            '<div class="carbon-header"><i class="fa-duotone fa-solid fa-leaf" style="color:#16a34a;"></i> Carbon Footprint</div>' +
            '<div class="carbon-bars">' +
            carbonBar('Public Transport', co2Public, 30, '#16a34a', true) +
            carbonBar('Bike / Rideshare', co2Bike, 40, '#d97706', false) +
            carbonBar('Car / Taxi', co2Car, 60, '#dc2626', false) +
            '</div>' +
            '<div class="carbon-save">You save <strong>' + savings + ' kg CO₂</strong> by choosing public transport!</div>' +
            '</div>';
    }

    function carbonBar(label, value, width, color, highlight) {
        return '<div class="carbon-bar-row' + (highlight ? ' carbon-chosen' : '') + '">' +
            '<span class="carbon-bar-label">' + label + '</span>' +
            '<div class="carbon-bar-track"><div class="carbon-bar-fill" style="width:' + width + '%;background:' + color + '"></div></div>' +
            '<span class="carbon-bar-value">' + value + ' kg</span></div>';
    }

    // ─── Draw Lines ─────────────────────────────────────────
    function drawWalkingLine(coords) {
        if (!coords || coords.length < 2) return;
        var ll = coords.map(function (c) { return Array.isArray(c) ? c : [c.lat, c.lng]; });
        resultLayers.addLayer(L.polyline(ll, { color: '#6b7280', weight: 4, dashArray: '6, 10', opacity: 0.7 }));
    }

    function drawRidingLine(coords, color) {
        if (!coords || coords.length < 2) return;
        var ll = coords.map(function (c) { return Array.isArray(c) ? c : [c.lat, c.lng]; });
        // Border line (thicker, darker)
        resultLayers.addLayer(L.polyline(ll, { color: '#fff', weight: 8, opacity: 0.9 }));
        // Main colored line
        resultLayers.addLayer(L.polyline(ll, { color: color || '#2563eb', weight: 5, opacity: 0.95 }));
    }

    function addStopMarker(lat, lng, name, color) {
        var icon = L.divIcon({
            className: 'sawari-marker',
            html: '<div class="marker-route-stop" style="border-color:' + color + ';background:#fff"></div>',
            iconSize: [14, 14],
            iconAnchor: [7, 7]
        });
        var m = L.marker([lat, lng], { icon: icon }).bindPopup(SawariUtils.escapeHTML(name));
        resultLayers.addLayer(m);
    }

    function addUserMarker(lat, lng, label) {
        var icon = L.divIcon({
            className: 'sawari-marker',
            html: '<div class="marker-user-loc"><i class="fa-solid fa-circle" style="color:#6366f1;font-size:16px;text-shadow:0 0 3px #fff;"></i></div>',
            iconSize: [20, 20],
            iconAnchor: [10, 10]
        });
        var m = L.marker([lat, lng], { icon: icon }).bindPopup(SawariUtils.escapeHTML(label));
        resultLayers.addLayer(m);
    }

    function collectAndFitBounds(result) {
        var points = [];
        if (startMarker) { var ll = startMarker.getLatLng(); points.push([ll.lat, ll.lng]); }
        if (endMarker) { var ll2 = endMarker.getLatLng(); points.push([ll2.lat, ll2.lng]); }
        if (result.segments) {
            result.segments.forEach(function (seg) {
                if (seg.from && seg.from.lat) points.push([seg.from.lat, seg.from.lng]);
                if (seg.to && seg.to.lat) points.push([seg.to.lat, seg.to.lng]);
                if (seg.path_coordinates) {
                    seg.path_coordinates.forEach(function (c) { points.push(Array.isArray(c) ? c : [c.lat, c.lng]); });
                }
            });
        }
        if (points.length > 0) fitToPoints(points);
    }

    // ─── Close Results ──────────────────────────────────────
    function closeResults() {
        var panel = document.getElementById('resultsPanel');
        if (panel) panel.classList.remove('active');
        resultLayers.clearLayers();
        currentRouteData = null;
    }

    // ─── Feedback Modal ─────────────────────────────────────
    function initFeedbackModal() {
        var btnFeedback = document.getElementById('btnFeedback');
        var modal = document.getElementById('feedbackModal');
        var form = document.getElementById('feedbackForm');
        if (!btnFeedback || !modal || !form) return;

        btnFeedback.addEventListener('click', function () {
            if (currentRouteData && currentRouteData.segments) {
                var rIds = [], vIds = [];
                currentRouteData.segments.forEach(function (seg) {
                    if (seg.route_id) rIds.push(seg.route_id);
                    if (seg.vehicle && seg.vehicle.vehicle_id) vIds.push(seg.vehicle.vehicle_id);
                });
                document.getElementById('feedbackRouteId').value = rIds[0] || '';
                document.getElementById('feedbackVehicleId').value = vIds[0] || '';
            }
            modal.style.display = 'flex';
        });

        modal.querySelectorAll('[data-close-modal]').forEach(function (btn) {
            btn.addEventListener('click', function () { modal.style.display = 'none'; });
        });
        modal.addEventListener('click', function (e) { if (e.target === modal) modal.style.display = 'none'; });

        var stars = document.querySelectorAll('#starRating .star');
        var ratingInput = document.getElementById('feedbackRating');
        stars.forEach(function (star) {
            star.addEventListener('click', function () {
                var rating = parseInt(this.getAttribute('data-rating'));
                ratingInput.value = rating;
                stars.forEach(function (s) { s.classList.toggle('active', parseInt(s.getAttribute('data-rating')) <= rating); });
            });
        });

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var fd = new URLSearchParams();
            fd.append('type', document.getElementById('feedbackType').value);
            fd.append('message', document.getElementById('feedbackMessage').value);
            var r = document.getElementById('feedbackRating').value;
            if (r) fd.append('rating', r);
            var ri = document.getElementById('feedbackRouteId').value;
            if (ri) fd.append('related_route_id', ri);
            var vi = document.getElementById('feedbackVehicleId').value;
            if (vi) fd.append('related_vehicle_id', vi);

            var btn = document.getElementById('btnSubmitFeedback');
            btn.disabled = true; btn.textContent = 'Submitting...';

            SawariUtils.apiFetch('api/suggestions/create.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: fd.toString()
            }).then(function () {
                SawariUtils.showToast('Thank you for your feedback!', 'success');
                modal.style.display = 'none'; form.reset();
                stars.forEach(function (s) { s.classList.remove('active'); });
                ratingInput.value = '';
            }).catch(function () {
                SawariUtils.showToast('Failed to submit feedback', 'error');
            }).finally(function () { btn.disabled = false; btn.textContent = 'Submit'; });
        });
    }

    // ─── Tourist Mode ───────────────────────────────────────
    function initTouristMode() {
        var btn = document.getElementById('btnTouristToggle');
        if (!btn) return;
        btn.addEventListener('click', function () {
            touristMode = !touristMode;
            btn.classList.toggle('active', touristMode);
            document.body.classList.toggle('tourist-mode', touristMode);
            if (currentRouteData && currentRouteData.success) displayResults(currentRouteData);
        });
    }

    function buildTouristTips(result) {
        var tips = window.SAWARI_TOURIST_TIPS;
        if (!tips) return '';
        var destName = '';
        if (result && result.segments) {
            for (var i = 0; i < result.segments.length; i++) {
                if (result.segments[i].type === 'riding' && result.segments[i].to) {
                    destName = result.segments[i].to.name; break;
                }
            }
        }

        var html = '<div class="tourist-tips-card">';
        html += '<div class="tourist-tips-title">Tourist Help Guide</div>';

        html += tipSection('Boarding the Bus', tips.boarding.map(function (t) {
            return '<div class="tourist-phrase"><span class="phrase-nepali">"' +
                SawariUtils.escapeHTML(t.nepali.replace('[Destination]', destName || 'your stop')) + '"</span>' +
                '<span class="phrase-english">' + SawariUtils.escapeHTML(t.english.replace('[Destination]', destName || 'your stop')) + '</span></div>';
        }).join(''));

        html += tipSection('Getting Off', tips.alighting.map(function (t) {
            return '<div class="tourist-phrase"><span class="phrase-nepali">"' + SawariUtils.escapeHTML(t.nepali) + '"</span>' +
                '<span class="phrase-english">' + SawariUtils.escapeHTML(t.english) + '</span></div>';
        }).join(''));

        html += tipSection('Payment Tips', '<ul class="tip-list">' + tips.payment.map(function (t) {
            return '<li>' + SawariUtils.escapeHTML(t) + '</li>';
        }).join('') + '</ul>');

        html += tipSection('Safety', '<ul class="tip-list">' + tips.precautions.map(function (t) {
            return '<li>' + SawariUtils.escapeHTML(t) + '</li>';
        }).join('') + '</ul>');

        html += tipSection('Good to Know', '<ul class="tip-list">' + tips.general.map(function (t) {
            return '<li>' + SawariUtils.escapeHTML(t) + '</li>';
        }).join('') + '</ul>');

        html += '</div>';
        return html;
    }

    function tipSection(title, content) {
        return '<div class="tip-section"><div class="tip-heading">' + title + '</div>' + content + '</div>';
    }

    // ─── Public API ─────────────────────────────────────────
    return {
        init: init,
        setStartMarker: setStartMarker,
        setEndMarker: setEndMarker,
        setUserLocationMarker: setUserLocationMarker,
        displayResults: displayResults,
        showLoading: showLoading,
        hideLoading: hideLoading,
        closeResults: closeResults,
        fitToPoints: fitToPoints,
        getMap: function () { return map; }
    };
})();

document.addEventListener('DOMContentLoaded', function () {
    SawariMap.init();
});
