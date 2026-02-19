<?php
/**
 * SAWARI — Add Location (Agent)
 * 
 * Map-based interface to pin new bus stops/landmarks.
 * GPS logging, name input, duplicate check.
 */

require_once __DIR__ . '/../../includes/auth-agent.php';

$pageTitle = 'Add Location';
$currentPage = 'add-location';

require_once __DIR__ . '/../../includes/agent-header.php';
?>

<div style="display:grid;grid-template-columns:1fr 380px;gap:var(--space-6);">

    <!-- Map -->
    <div class="card">
        <div class="card-header"
            style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:var(--space-2);">
            <h3 class="card-title">Pin Location on Map</h3>
            <div style="display:flex;align-items:center;gap:var(--space-3);">
                <label
                    style="display:flex;align-items:center;gap:var(--space-2);cursor:pointer;font-size:var(--text-xs);color:var(--color-neutral-600);user-select:none;">
                    <input type="checkbox" id="toggle-existing"
                        style="accent-color:var(--color-primary-600);width:16px;height:16px;cursor:pointer;">
                    Show existing stops
                </label>
                <button class="btn btn-ghost btn-sm" id="gps-btn" title="Use my GPS location">
                    <i data-feather="crosshair" style="width:16px;height:16px;"></i>
                    Use GPS
                </button>
            </div>
        </div>
        <div class="card-body" style="padding:0;">
            <div id="location-map" class="data-collection-map"
                style="height:480px;border-radius:0 0 var(--radius-lg) var(--radius-lg);"></div>
        </div>
    </div>

    <!-- Form -->
    <div>
        <!-- Duplicate Warning (hidden by default) -->
        <div class="duplicate-warning" id="duplicate-warning" style="display:none;">
            <i data-feather="alert-triangle" style="width:20px;height:20px;flex-shrink:0;"></i>
            <div>
                <strong>Nearby location found</strong>
                <p id="duplicate-text" style="margin:var(--space-1) 0 0;font-size:var(--text-xs);"></p>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Location Details</h3>
            </div>
            <div class="card-body">
                <form id="location-form">
                    <div class="form-group">
                        <label class="form-label" for="loc-name">Name <span
                                style="color:var(--color-danger-500);">*</span></label>
                        <input type="text" id="loc-name" class="form-input" placeholder="e.g. Kalanki Chowk" required>
                    </div>

                    <div class="form-group" style="margin-top:var(--space-4);">
                        <label class="form-label" for="loc-type">Type <span
                                style="color:var(--color-danger-500);">*</span></label>
                        <select id="loc-type" class="form-input">
                            <option value="stop">Bus Stop</option>
                            <option value="landmark">Landmark</option>
                        </select>
                    </div>

                    <div
                        style="display:grid;grid-template-columns:1fr 1fr;gap:var(--space-3);margin-top:var(--space-4);">
                        <div class="form-group">
                            <label class="form-label">Latitude</label>
                            <input type="text" id="loc-lat" class="form-input" readonly placeholder="Click map">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Longitude</label>
                            <input type="text" id="loc-lng" class="form-input" readonly placeholder="Click map">
                        </div>
                    </div>

                    <div class="form-group" style="margin-top:var(--space-4);">
                        <label class="form-label" for="loc-desc">Description</label>
                        <textarea id="loc-desc" class="form-input" rows="2"
                            placeholder="Optional description or landmark details"></textarea>
                    </div>

                    <div class="form-group" style="margin-top:var(--space-4);">
                        <label class="form-label" for="loc-notes">Notes for Reviewer</label>
                        <textarea id="loc-notes" class="form-input" rows="2"
                            placeholder="Any additional context for the admin reviewer"></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary" id="submit-btn"
                        style="width:100%;margin-top:var(--space-6);" disabled>
                        <i data-feather="send" style="width:16px;height:16px;"></i>
                        Submit for Review
                    </button>
                </form>
            </div>
        </div>

        <p style="font-size:var(--text-xs);color:var(--color-neutral-400);margin-top:var(--space-3);text-align:center;">
            Click on the map to place a marker, or use GPS to auto-detect your position.
        </p>
    </div>
</div>

<style>
    @media (max-width: 900px) {
        div[style*="grid-template-columns:1fr 380px"] {
            grid-template-columns: 1fr !important;
        }
    }
</style>

<script>
    (function () {
        'use strict';

        var map, marker;
        var latInput = document.getElementById('loc-lat');
        var lngInput = document.getElementById('loc-lng');
        var submitBtn = document.getElementById('submit-btn');
        var dupWarn = document.getElementById('duplicate-warning');
        var dupText = document.getElementById('duplicate-text');
        var existingLayer = null;
        var existingLoaded = false;

        // Initialize map centered on Kathmandu
        function initMap() {
            map = L.map('location-map').setView([27.7172, 85.3240], 13);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors',
                maxZoom: 19
            }).addTo(map);

            existingLayer = L.layerGroup();

            // Toggle existing locations
            document.getElementById('toggle-existing').addEventListener('change', function () {
                if (this.checked) {
                    existingLayer.addTo(map);
                    if (!existingLoaded) loadExistingLocations();
                } else {
                    map.removeLayer(existingLayer);
                }
            });

            // Click to place marker
            map.on('click', function (e) {
                setMarker(e.latlng.lat, e.latlng.lng);
            });
        }

        // Load approved locations from API and add to layer
        function loadExistingLocations() {
            existingLoaded = true;
            Sawari.api('locations', 'approved', {}, 'GET').then(function (res) {
                if (!res.success || !res.locations) return;
                res.locations.forEach(function (loc) {
                    var lat = parseFloat(loc.latitude);
                    var lng = parseFloat(loc.longitude);
                    if (isNaN(lat) || isNaN(lng)) return;

                    var color = loc.type === 'stop' ? '#1A56DB' : '#7C3AED';
                    var icon = L.divIcon({
                        className: 'existing-loc-marker',
                        html: '<div style="width:12px;height:12px;background:' + color + ';border:2px solid #fff;border-radius:50%;box-shadow:0 1px 4px rgba(0,0,0,.3);opacity:0.7;"></div>',
                        iconSize: [12, 12],
                        iconAnchor: [6, 6]
                    });

                    var escapeName = loc.name.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
                    var escapeType = loc.type.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

                    L.marker([lat, lng], { icon: icon, interactive: true })
                        .bindPopup('<div style="font-size:13px;"><strong>' + escapeName + '</strong><br><span style="color:#64748B;font-size:11px;">' + escapeType + '</span></div>')
                        .addTo(existingLayer);
                });
            });
        }

        function setMarker(lat, lng) {
            lat = parseFloat(lat.toFixed(8));
            lng = parseFloat(lng.toFixed(8));

            if (marker) {
                marker.setLatLng([lat, lng]);
            } else {
                marker = L.marker([lat, lng], { draggable: true }).addTo(map);
                marker.on('dragend', function () {
                    var pos = marker.getLatLng();
                    updateCoords(pos.lat, pos.lng);
                    checkNearby(pos.lat, pos.lng);
                });
            }

            updateCoords(lat, lng);
            checkNearby(lat, lng);
            map.panTo([lat, lng]);
        }

        function updateCoords(lat, lng) {
            latInput.value = parseFloat(lat).toFixed(8);
            lngInput.value = parseFloat(lng).toFixed(8);
            submitBtn.disabled = false;
        }

        // GPS auto-detect
        document.getElementById('gps-btn').addEventListener('click', function () {
            var btn = this;
            if (!navigator.geolocation) {
                Sawari.toast('GPS not supported by your browser.', 'warning');
                return;
            }

            Sawari.setLoading(btn, true);
            navigator.geolocation.getCurrentPosition(
                function (pos) {
                    setMarker(pos.coords.latitude, pos.coords.longitude);
                    map.setView([pos.coords.latitude, pos.coords.longitude], 16);
                    Sawari.setLoading(btn, false);
                    Sawari.toast('GPS position detected.', 'success');
                },
                function (err) {
                    Sawari.setLoading(btn, false);
                    Sawari.toast('Could not get GPS position: ' + err.message, 'danger');
                },
                { enableHighAccuracy: true, timeout: 10000 }
            );
        });

        // Duplicate check
        var checkTimer;
        function checkNearby(lat, lng) {
            clearTimeout(checkTimer);
            checkTimer = setTimeout(function () {
                Sawari.api('locations', 'nearby', { lat: lat, lng: lng, radius: 0.3 }, 'GET').then(function (res) {
                    if (res.success && res.locations && res.locations.length > 0) {
                        var names = res.locations.map(function (l) {
                            return l.name + ' (' + parseFloat(l.distance * 1000).toFixed(0) + 'm away)';
                        }).join(', ');
                        dupText.textContent = names;
                        dupWarn.style.display = 'flex';
                        feather.replace({ 'stroke-width': 1.75 });
                    } else {
                        dupWarn.style.display = 'none';
                    }
                });
            }, 500);
        }

        // Form submission
        document.getElementById('location-form').addEventListener('submit', function (e) {
            e.preventDefault();

            var name = document.getElementById('loc-name').value.trim();
            var lat = latInput.value;
            var lng = lngInput.value;

            if (!name) { Sawari.toast('Please enter a location name.', 'warning'); return; }
            if (!lat || !lng) { Sawari.toast('Please place a marker on the map.', 'warning'); return; }

            Sawari.setLoading(submitBtn, true);

            Sawari.api('locations', 'submit', {
                name: name,
                latitude: lat,
                longitude: lng,
                type: document.getElementById('loc-type').value,
                description: document.getElementById('loc-desc').value.trim(),
                notes: document.getElementById('loc-notes').value.trim()
            }).then(function (res) {
                Sawari.setLoading(submitBtn, false);
                if (res.success) {
                    Sawari.toast(res.message, 'success');
                    // Reset
                    document.getElementById('location-form').reset();
                    latInput.value = '';
                    lngInput.value = '';
                    submitBtn.disabled = true;
                    if (marker) { map.removeLayer(marker); marker = null; }
                    dupWarn.style.display = 'none';
                } else {
                    Sawari.toast(res.message || 'Submission failed.', 'danger');
                }
            }).catch(function () {
                Sawari.setLoading(submitBtn, false);
            });
        });

        document.addEventListener('DOMContentLoaded', initMap);
    })();
</script>

<?php require_once __DIR__ . '/../../includes/agent-footer.php'; ?>