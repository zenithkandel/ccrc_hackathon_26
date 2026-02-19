<?php
/**
 * SAWARI — Add Route (Agent)
 * 
 * Map interface to select ordered stops and build a route's location_list.
 */

require_once __DIR__ . '/../../includes/auth-agent.php';

$pageTitle = 'Add Route';
$currentPage = 'add-route';

require_once __DIR__ . '/../../includes/agent-header.php';
?>

<div style="display:grid;grid-template-columns:1fr 380px;gap:var(--space-6);">

    <!-- Map Preview -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Route Preview</h3>
        </div>
        <div class="card-body" style="padding:0;">
            <div id="route-map" class="data-collection-map" style="height:520px;border-radius:0 0 var(--radius-lg) var(--radius-lg);"></div>
        </div>
    </div>

    <!-- Route Builder -->
    <div>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Route Details</h3>
            </div>
            <div class="card-body">
                <form id="route-form">
                    <!-- Route Name -->
                    <div class="form-group">
                        <label class="form-label" for="route-name">Route Name <span style="color:var(--color-danger-500);">*</span></label>
                        <input type="text" id="route-name" class="form-input" placeholder="e.g. Kalanki – Ratnapark – Buspark" required>
                    </div>

                    <!-- Description -->
                    <div class="form-group" style="margin-top:var(--space-4);">
                        <label class="form-label" for="route-desc">Description</label>
                        <textarea id="route-desc" class="form-input" rows="2" placeholder="Optional route description"></textarea>
                    </div>

                    <!-- Fare -->
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--space-3);margin-top:var(--space-4);">
                        <div class="form-group">
                            <label class="form-label" for="route-fare-base">Base Fare (NPR)</label>
                            <input type="number" id="route-fare-base" class="form-input" placeholder="e.g. 20" step="0.01" min="0">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="route-fare-km">Fare/km (NPR)</label>
                            <input type="number" id="route-fare-km" class="form-input" placeholder="e.g. 2.50" step="0.01" min="0">
                        </div>
                    </div>

                    <!-- Search & Add Stops -->
                    <div class="form-group" style="margin-top:var(--space-5);border-top:1px solid var(--color-neutral-100);padding-top:var(--space-5);">
                        <label class="form-label">Add Stops (in order) <span style="color:var(--color-danger-500);">*</span></label>
                        <div style="position:relative;">
                            <input type="text" id="stop-search" class="form-input" placeholder="Search approved locations..." autocomplete="off">
                            <div id="stop-results" style="display:none;position:absolute;top:100%;left:0;right:0;z-index:10;background:var(--color-white);border:1px solid var(--color-neutral-200);border-top:none;border-radius:0 0 var(--radius-md) var(--radius-md);max-height:200px;overflow-y:auto;box-shadow:var(--shadow-md);"></div>
                        </div>
                    </div>

                    <!-- Stop List -->
                    <div id="stop-list" class="route-builder-list" style="margin-top:var(--space-3);min-height:60px;">
                        <div class="empty-state" style="padding:var(--space-6) 0;text-align:center;">
                            <p style="font-size:var(--text-sm);color:var(--color-neutral-400);margin:0;">Search and add at least 2 stops to build a route.</p>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div class="form-group" style="margin-top:var(--space-4);">
                        <label class="form-label" for="route-notes">Notes for Reviewer</label>
                        <textarea id="route-notes" class="form-input" rows="2" placeholder="Any additional context"></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary" id="submit-btn" style="width:100%;margin-top:var(--space-6);" disabled>
                        <i data-feather="send" style="width:16px;height:16px;"></i>
                        Submit for Review
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
    @media (max-width: 900px) {
        div[style*="grid-template-columns:1fr 380px"] {
            grid-template-columns: 1fr !important;
        }
    }
    .stop-result-item {
        padding: var(--space-2-5) var(--space-3);
        font-size: var(--text-sm);
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: var(--space-2);
        border-bottom: 1px solid var(--color-neutral-50);
    }
    .stop-result-item:hover {
        background: var(--color-neutral-50);
    }
    .stop-result-item:last-child { border-bottom: none; }
</style>

<script>
(function () {
    'use strict';

    var map, polyline;
    var stops = []; // Array of { location_id, name, latitude, longitude }
    var markers = [];
    var searchTimer;

    // Init map
    function initMap() {
        map = L.map('route-map').setView([27.7172, 85.3240], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors',
            maxZoom: 19
        }).addTo(map);
    }

    // Render stops on map
    function renderMap() {
        // Clear markers
        markers.forEach(function (m) { map.removeLayer(m); });
        markers = [];
        if (polyline) { map.removeLayer(polyline); polyline = null; }

        if (stops.length === 0) return;

        var latlngs = [];
        stops.forEach(function (stop, index) {
            var lat = parseFloat(stop.latitude);
            var lng = parseFloat(stop.longitude);
            latlngs.push([lat, lng]);

            var m = L.circleMarker([lat, lng], {
                radius: 8,
                fillColor: index === 0 ? '#16a34a' : (index === stops.length - 1 ? '#dc2626' : '#1A56DB'),
                color: '#fff',
                weight: 2,
                fillOpacity: 1
            }).addTo(map);
            m.bindTooltip((index + 1) + '. ' + stop.name, { permanent: false });
            markers.push(m);
        });

        if (latlngs.length >= 2) {
            polyline = L.polyline(latlngs, {
                color: 'var(--color-primary-600)',
                weight: 3,
                dashArray: '8 6',
                opacity: 0.7
            }).addTo(map);
            map.fitBounds(polyline.getBounds(), { padding: [30, 30] });
        } else {
            map.setView(latlngs[0], 14);
        }
    }

    // Render stop list
    function renderStopList() {
        var list = document.getElementById('stop-list');
        var submitBtn = document.getElementById('submit-btn');

        if (stops.length === 0) {
            list.innerHTML = '<div class="empty-state" style="padding:var(--space-6) 0;text-align:center;"><p style="font-size:var(--text-sm);color:var(--color-neutral-400);margin:0;">Search and add at least 2 stops to build a route.</p></div>';
            submitBtn.disabled = true;
            return;
        }

        submitBtn.disabled = stops.length < 2;

        var html = '';
        stops.forEach(function (stop, index) {
            html += '<div class="route-builder-item" data-index="' + index + '">';
            html += '<span class="route-builder-number">' + (index + 1) + '</span>';
            html += '<span style="flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + Sawari.escape(stop.name) + '</span>';

            // Move buttons
            if (index > 0) {
                html += '<button type="button" class="btn btn-ghost btn-icon move-up" data-index="' + index + '" title="Move up" style="padding:2px;">';
                html += '<i data-feather="chevron-up" style="width:14px;height:14px;"></i></button>';
            }
            if (index < stops.length - 1) {
                html += '<button type="button" class="btn btn-ghost btn-icon move-down" data-index="' + index + '" title="Move down" style="padding:2px;">';
                html += '<i data-feather="chevron-down" style="width:14px;height:14px;"></i></button>';
            }

            html += '<button type="button" class="route-builder-remove remove-stop" data-index="' + index + '" title="Remove">';
            html += '<i data-feather="x" style="width:14px;height:14px;"></i></button>';
            html += '</div>';
        });
        list.innerHTML = html;
        feather.replace({ 'stroke-width': 1.75 });

        // Bind events
        list.querySelectorAll('.remove-stop').forEach(function (btn) {
            btn.addEventListener('click', function () {
                stops.splice(parseInt(this.dataset.index), 1);
                renderStopList();
                renderMap();
            });
        });
        list.querySelectorAll('.move-up').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var i = parseInt(this.dataset.index);
                var temp = stops[i];
                stops[i] = stops[i - 1];
                stops[i - 1] = temp;
                renderStopList();
                renderMap();
            });
        });
        list.querySelectorAll('.move-down').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var i = parseInt(this.dataset.index);
                var temp = stops[i];
                stops[i] = stops[i + 1];
                stops[i + 1] = temp;
                renderStopList();
                renderMap();
            });
        });
    }

    // Search locations
    var searchInput = document.getElementById('stop-search');
    var searchResults = document.getElementById('stop-results');

    searchInput.addEventListener('input', function () {
        var q = this.value.trim();
        clearTimeout(searchTimer);

        if (q.length < 2) {
            searchResults.style.display = 'none';
            return;
        }

        searchTimer = setTimeout(function () {
            Sawari.api('locations', 'search', { q: q }, 'GET').then(function (res) {
                if (!res.success || !res.locations.length) {
                    searchResults.innerHTML = '<div class="stop-result-item" style="color:var(--color-neutral-400);cursor:default;">No matching locations found</div>';
                    searchResults.style.display = 'block';
                    return;
                }

                var html = '';
                res.locations.forEach(function (loc) {
                    // Skip already added
                    var exists = stops.some(function (s) { return s.location_id == loc.location_id; });
                    if (exists) return;

                    var typeIcon = loc.type === 'stop' ? 'map-pin' : 'flag';
                    html += '<div class="stop-result-item" data-loc=\'' + JSON.stringify(loc).replace(/'/g, '&#39;') + '\'>';
                    html += '<i data-feather="' + typeIcon + '" style="width:14px;height:14px;color:var(--color-neutral-400);flex-shrink:0;"></i>';
                    html += '<span>' + Sawari.escape(loc.name) + '</span>';
                    html += '<span class="badge badge-neutral" style="margin-left:auto;font-size:10px;">' + loc.type + '</span>';
                    html += '</div>';
                });

                if (!html) {
                    html = '<div class="stop-result-item" style="color:var(--color-neutral-400);cursor:default;">All matches already added</div>';
                }

                searchResults.innerHTML = html;
                searchResults.style.display = 'block';
                feather.replace({ 'stroke-width': 1.75 });

                // Bind click
                searchResults.querySelectorAll('.stop-result-item[data-loc]').forEach(function (item) {
                    item.addEventListener('click', function () {
                        var loc = JSON.parse(this.dataset.loc);
                        stops.push({
                            location_id: parseInt(loc.location_id),
                            name: loc.name,
                            latitude: parseFloat(loc.latitude),
                            longitude: parseFloat(loc.longitude)
                        });
                        searchInput.value = '';
                        searchResults.style.display = 'none';
                        renderStopList();
                        renderMap();
                    });
                });
            });
        }, 300);
    });

    // Close search on outside click
    document.addEventListener('click', function (e) {
        if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
            searchResults.style.display = 'none';
        }
    });

    // Form submit
    document.getElementById('route-form').addEventListener('submit', function (e) {
        e.preventDefault();

        var name = document.getElementById('route-name').value.trim();
        if (!name) { Sawari.toast('Route name is required.', 'warning'); return; }
        if (stops.length < 2) { Sawari.toast('Add at least 2 stops.', 'warning'); return; }

        var btn = document.getElementById('submit-btn');
        Sawari.setLoading(btn, true);

        Sawari.api('routes', 'submit', {
            name: name,
            description: document.getElementById('route-desc').value.trim(),
            location_list: JSON.stringify(stops),
            fare_base: document.getElementById('route-fare-base').value || '',
            fare_per_km: document.getElementById('route-fare-km').value || '',
            notes: document.getElementById('route-notes').value.trim()
        }).then(function (res) {
            Sawari.setLoading(btn, false);
            if (res.success) {
                Sawari.toast(res.message, 'success');
                // Reset
                document.getElementById('route-form').reset();
                stops = [];
                renderStopList();
                renderMap();
            } else {
                Sawari.toast(res.message || 'Submission failed.', 'danger');
            }
        }).catch(function () {
            Sawari.setLoading(btn, false);
        });
    });

    document.addEventListener('DOMContentLoaded', function () {
        initMap();
        renderStopList();
    });
})();
</script>

<?php require_once __DIR__ . '/../../includes/agent-footer.php'; ?>
