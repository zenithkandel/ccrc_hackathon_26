/**
 * ═══════════════════════════════════════════════════════════
 * Sawari — Search Interaction (Enhanced)
 * ═══════════════════════════════════════════════════════════
 *
 * Handles autocomplete, geolocation, map-click point picking,
 * swap locations, and route search.
 */

const SawariSearch = (function () {
    'use strict';

    const BASE_URL = (window.SAWARI_MAP_CONFIG || {}).baseUrl || '';

    // ─── State ──────────────────────────────────────────────
    let startLocation = null;
    let endLocation = null;
    let activeIndex = -1;

    // Map-pick mode: 'start' | 'end' | null
    let pickMode = null;

    // Actual user coordinates (from geolocation or map-click)
    // These may differ from the nearest bus stop's coords
    let actualStartCoords = null;  // { lat, lng }
    let actualEndCoords = null;    // { lat, lng }

    // ─── Initialize ─────────────────────────────────────────
    function init() {
        var startInput = document.getElementById('startInput');
        var endInput = document.getElementById('endInput');
        if (!startInput || !endInput) return;

        initAutocomplete(startInput, 'startDropdown', function (loc, userCoords) { setStart(loc, userCoords); });
        initAutocomplete(endInput, 'endDropdown', function (loc, userCoords) { setEnd(loc, userCoords); });

        var btnSwap = document.getElementById('btnSwap');
        if (btnSwap) btnSwap.addEventListener('click', swapLocations);

        var btnGeo = document.getElementById('btnGeolocate');
        if (btnGeo) btnGeo.addEventListener('click', geolocate);

        var btnPickStart = document.getElementById('btnPickStart');
        var btnPickEnd = document.getElementById('btnPickEnd');
        if (btnPickStart) btnPickStart.addEventListener('click', function () { enterPickMode('start'); });
        if (btnPickEnd) btnPickEnd.addEventListener('click', function () { enterPickMode('end'); });

        var btnPickCancel = document.getElementById('btnPickCancel');
        if (btnPickCancel) btnPickCancel.addEventListener('click', exitPickMode);

        var btnSearch = document.getElementById('btnSearch');
        if (btnSearch) btnSearch.addEventListener('click', triggerSearch);

        // Enter key on inputs
        [startInput, endInput].forEach(function (inp) {
            inp.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    var dd = inp.id === 'startInput'
                        ? document.getElementById('startDropdown')
                        : document.getElementById('endDropdown');
                    var items = dd ? dd.querySelectorAll('.autocomplete-item') : [];
                    if (activeIndex >= 0 && items[activeIndex]) return;
                    if (startLocation && endLocation) {
                        e.preventDefault();
                        triggerSearch();
                    }
                }
            });
        });

        // Close dropdowns on outside click
        document.addEventListener('click', function (e) {
            document.querySelectorAll('.autocomplete-dropdown').forEach(function (dd) {
                if (!dd.parentElement.contains(e.target)) dd.classList.remove('active');
            });
        });

        // Clear fields on edit
        startInput.addEventListener('input', function () {
            if (startLocation && startInput.value !== startLocation.name &&
                startInput.value !== startLocation.name + ' (nearest stop)') {
                startLocation = null;
                actualStartCoords = null;
                document.getElementById('startLocationId').value = '';
            }
        });
        endInput.addEventListener('input', function () {
            if (endLocation && endInput.value !== endLocation.name &&
                endInput.value !== endLocation.name + ' (nearest stop)') {
                endLocation = null;
                actualEndCoords = null;
                document.getElementById('endLocationId').value = '';
            }
        });
    }

    // ─── Set Start / End with visual feedback ───────────────
    function setStart(loc, userCoords) {
        startLocation = loc;
        // userCoords = the actual point the user picked (map click / GPS)
        // loc = the nearest bus stop resolved from that point
        actualStartCoords = userCoords || null;
        document.getElementById('startInput').value = userCoords
            ? loc.name + ' (nearest stop)'
            : loc.name;
        document.getElementById('startLocationId').value = loc.location_id;
        SawariMap.setStartMarker(loc.latitude, loc.longitude, loc.name);
        flashField('startField');
    }

    function setEnd(loc, userCoords) {
        endLocation = loc;
        actualEndCoords = userCoords || null;
        document.getElementById('endInput').value = userCoords
            ? loc.name + ' (nearest stop)'
            : loc.name;
        document.getElementById('endLocationId').value = loc.location_id;
        SawariMap.setEndMarker(loc.latitude, loc.longitude, loc.name);
        flashField('endField');
    }

    function flashField(id) {
        var el = document.getElementById(id);
        if (el) {
            el.classList.add('field-set');
            setTimeout(function () { el.classList.remove('field-set'); }, 700);
        }
    }

    // ─── Map-click Point Picking ────────────────────────────
    function enterPickMode(mode) {
        pickMode = mode;
        var banner = document.getElementById('mapPickBanner');
        var text = document.getElementById('mapPickBannerText');
        if (banner) {
            banner.style.display = 'flex';
            text.textContent = mode === 'start'
                ? 'Click anywhere on the map to set your starting point'
                : 'Click anywhere on the map to set your destination';
        }
        document.body.classList.add('map-picking');
        var map = SawariMap.getMap();
        if (map) map.once('click', onMapPick);
    }

    function exitPickMode() {
        pickMode = null;
        var banner = document.getElementById('mapPickBanner');
        if (banner) banner.style.display = 'none';
        document.body.classList.remove('map-picking');
        var map = SawariMap.getMap();
        if (map) map.off('click', onMapPick);
    }

    function onMapPick(e) {
        var lat = e.latlng.lat;
        var lng = e.latlng.lng;
        var mode = pickMode;
        var userCoords = { lat: lat, lng: lng };

        SawariUtils.apiFetch(
            BASE_URL + '/api/locations/read.php?nearest=1&lat=' + lat +
            '&lng=' + lng + '&radius=50&status=approved&limit=1'
        ).then(function (data) {
            var locs = data.locations || data.data || [];
            if (locs.length > 0) {
                var nearest = {
                    location_id: parseInt(locs[0].location_id),
                    name: locs[0].name,
                    latitude: parseFloat(locs[0].latitude),
                    longitude: parseFloat(locs[0].longitude)
                };
                var dist = Number(locs[0].distance_km).toFixed(1);
                // Pass userCoords so the API gets walking directions from actual point
                if (mode === 'start') setStart(nearest, userCoords);
                else setEnd(nearest, userCoords);
                SawariUtils.showToast('Nearest stop: ' + nearest.name + ' (' + dist + ' km away)', 'success');
            } else {
                SawariUtils.showToast('No bus stops found near that point', 'warning');
            }
        }).catch(function () {
            SawariUtils.showToast('Could not find nearby stops', 'error');
        });

        exitPickMode();
    }

    // ─── Autocomplete Setup ─────────────────────────────────
    function initAutocomplete(input, dropdownId, onSelect) {
        var dropdown = document.getElementById(dropdownId);
        if (!dropdown) return;
        var debounceTimer = null;

        input.addEventListener('input', function () {
            var query = input.value.trim();
            activeIndex = -1;
            if (debounceTimer) clearTimeout(debounceTimer);
            if (query.length < 1) { dropdown.classList.remove('active'); return; }
            debounceTimer = setTimeout(function () {
                fetchLocations(query, dropdown, onSelect);
            }, 250);
        });

        input.addEventListener('keydown', function (e) {
            var items = dropdown.querySelectorAll('.autocomplete-item');
            if (!items.length) return;
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                activeIndex = Math.min(activeIndex + 1, items.length - 1);
                highlightItem(items, activeIndex);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                activeIndex = Math.max(activeIndex - 1, 0);
                highlightItem(items, activeIndex);
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (activeIndex >= 0 && items[activeIndex]) items[activeIndex].click();
            } else if (e.key === 'Escape') {
                dropdown.classList.remove('active');
            }
        });

        input.addEventListener('focus', function () {
            if (dropdown.children.length > 0 && input.value.trim().length > 0) {
                dropdown.classList.add('active');
            }
        });
    }

    function fetchLocations(query, dropdown, onSelect) {
        // Show loading indicator immediately
        dropdown.innerHTML = '<div class="autocomplete-loading"><span class="autocomplete-spinner"></span> Searching...</div>';
        dropdown.classList.add('active');

        SawariUtils.apiFetch(BASE_URL + '/api/search/locations.php?q=' + encodeURIComponent(query))
            .then(function (data) {
                var locals = data.locations || [];
                if (locals.length >= 3) {
                    renderDropdown(dropdown, locals, [], onSelect);
                } else {
                    // Show local results immediately, then fetch Nominatim
                    if (locals.length > 0) {
                        renderDropdown(dropdown, locals, [], onSelect);
                    }
                    // Show a searching-web indicator
                    var loadingEl = document.createElement('div');
                    loadingEl.className = 'autocomplete-loading autocomplete-web-loading';
                    loadingEl.innerHTML = '<span class="autocomplete-spinner"></span> Searching more places...';
                    dropdown.appendChild(loadingEl);

                    fetchNominatim(query, function (webResults) {
                        // Remove the loading indicator
                        var webLoad = dropdown.querySelector('.autocomplete-web-loading');
                        if (webLoad) webLoad.remove();
                        // Re-render with both local and web results
                        renderDropdown(dropdown, locals, webResults, onSelect);
                    });
                }
            })
            .catch(function () {
                dropdown.innerHTML = '<div class="autocomplete-empty">Search failed. Please try again.</div>';
                dropdown.classList.add('active');
            });
    }

    function fetchNominatim(query, callback) {
        SawariUtils.apiFetch(BASE_URL + '/api/search/geocode.php?q=' + encodeURIComponent(query))
            .then(function (data) { callback(data.results || []); })
            .catch(function () { callback([]); });
    }

    function renderDropdown(dropdown, locations, webResults, onSelect) {
        activeIndex = -1;
        if (locations.length === 0 && webResults.length === 0) {
            dropdown.innerHTML = '<div class="autocomplete-empty">No locations found</div>';
            dropdown.classList.add('active');
            return;
        }

        var html = '';

        // Local DB results
        locations.forEach(function (loc) {
            var typeIcon = loc.type === 'landmark'
                ? '<i class="fa-duotone fa-solid fa-location-dot" style="color:#7c3aed;"></i>'
                : '<i class="fa-duotone fa-solid fa-bus-simple" style="color:#2563eb;"></i>';
            html += '<div class="autocomplete-item" data-id="' + loc.location_id + '">' +
                '<span class="autocomplete-item-icon">' + typeIcon + '</span>' +
                '<div class="autocomplete-item-text">' +
                '<span class="autocomplete-item-name">' + SawariUtils.escapeHTML(loc.name) + '</span>' +
                '<span class="autocomplete-item-type">' + SawariUtils.escapeHTML(loc.type) + '</span>' +
                '</div></div>';
        });

        // Nominatim / internet results
        if (webResults.length > 0) {
            if (locations.length > 0) {
                html += '<div class="autocomplete-divider">More places nearby</div>';
            }
            webResults.forEach(function (place, idx) {
                html += '<div class="autocomplete-item autocomplete-web" data-web-idx="' + idx + '">' +
                    '<span class="autocomplete-item-icon"><i class="fa-duotone fa-solid fa-earth-americas" style="color:#059669;"></i></span>' +
                    '<div class="autocomplete-item-text">' +
                    '<span class="autocomplete-item-name">' + SawariUtils.escapeHTML(place.name) + '</span>' +
                    '<span class="autocomplete-item-type autocomplete-web-type">' + SawariUtils.escapeHTML(place.type) + '</span>' +
                    '</div></div>';
            });
        }

        dropdown.innerHTML = html;
        dropdown.classList.add('active');

        // Click handlers for local results
        dropdown.querySelectorAll('.autocomplete-item:not(.autocomplete-web)').forEach(function (item) {
            item.addEventListener('click', function () {
                var id = parseInt(item.getAttribute('data-id'));
                var selected = locations.find(function (l) { return l.location_id === id; });
                if (selected) { onSelect(selected); dropdown.classList.remove('active'); }
            });
        });

        // Click handlers for web results (act like map-pick: find nearest bus stop)
        dropdown.querySelectorAll('.autocomplete-web').forEach(function (item) {
            item.addEventListener('click', function () {
                var idx = parseInt(item.getAttribute('data-web-idx'));
                var place = webResults[idx];
                if (!place) return;
                dropdown.classList.remove('active');

                var lat = place.latitude;
                var lng = place.longitude;
                var userCoords = { lat: lat, lng: lng };

                // Show loading feedback
                SawariUtils.showToast('Finding nearest bus stop to ' + place.name + '...', 'info');

                // Find nearest bus stop to this internet place
                SawariUtils.apiFetch(
                    BASE_URL + '/api/locations/read.php?nearest=1&lat=' + lat +
                    '&lng=' + lng + '&radius=50&status=approved&limit=1'
                ).then(function (data) {
                    var locs = data.locations || data.data || [];
                    if (locs.length > 0) {
                        var nearest = {
                            location_id: parseInt(locs[0].location_id),
                            name: locs[0].name,
                            latitude: parseFloat(locs[0].latitude),
                            longitude: parseFloat(locs[0].longitude)
                        };
                        onSelect(nearest, userCoords);
                        var distKm = Number(locs[0].distance_km).toFixed(1);
                        SawariUtils.showToast('Nearest stop: ' + nearest.name + ' (' + distKm + ' km from ' + place.name + ')', 'success');
                    } else {
                        SawariUtils.showToast('No bus stops found near ' + place.name, 'warning');
                    }
                }).catch(function () {
                    SawariUtils.showToast('Could not find nearby stops', 'error');
                });
            });
        });
    }

    function highlightItem(items, index) {
        items.forEach(function (item, i) { item.classList.toggle('active', i === index); });
        if (items[index]) items[index].scrollIntoView({ block: 'nearest' });
    }

    // ─── Swap ───────────────────────────────────────────────
    function swapLocations() {
        var si = document.getElementById('startInput'), ei = document.getElementById('endInput');
        var sId = document.getElementById('startLocationId'), eId = document.getElementById('endLocationId');
        var tmpT = si.value; si.value = ei.value; ei.value = tmpT;
        var tmpI = sId.value; sId.value = eId.value; eId.value = tmpI;
        var tmp = startLocation; startLocation = endLocation; endLocation = tmp;
        // Also swap actual coordinates
        var tmpC = actualStartCoords; actualStartCoords = actualEndCoords; actualEndCoords = tmpC;
        if (startLocation) SawariMap.setStartMarker(startLocation.latitude, startLocation.longitude, startLocation.name);
        if (endLocation) SawariMap.setEndMarker(endLocation.latitude, endLocation.longitude, endLocation.name);
        var btn = document.getElementById('btnSwap');
        if (btn) { btn.classList.add('swapping'); setTimeout(function () { btn.classList.remove('swapping'); }, 400); }
    }

    // ─── Geolocation ────────────────────────────────────────
    function geolocate() {
        if (!navigator.geolocation) {
            SawariUtils.showToast('Geolocation not supported', 'warning');
            return;
        }
        var btn = document.getElementById('btnGeolocate');
        btn.classList.add('locating');
        btn.disabled = true;

        navigator.geolocation.getCurrentPosition(
            function (pos) {
                var lat = pos.coords.latitude, lng = pos.coords.longitude;
                var userCoords = { lat: lat, lng: lng };
                SawariUtils.apiFetch(
                    BASE_URL + '/api/locations/read.php?nearest=1&lat=' + lat +
                    '&lng=' + lng + '&radius=50&status=approved&limit=1'
                ).then(function (data) {
                    var locs = data.locations || data.data || [];
                    if (locs.length > 0) {
                        var n = {
                            location_id: parseInt(locs[0].location_id),
                            name: locs[0].name,
                            latitude: parseFloat(locs[0].latitude),
                            longitude: parseFloat(locs[0].longitude)
                        };
                        // Pass userCoords so API gets walking from actual GPS position
                        setStart(n, userCoords);
                        SawariUtils.showToast('Nearest stop: ' + n.name + ' (' + Number(locs[0].distance_km).toFixed(1) + ' km)', 'success');
                    } else {
                        SawariUtils.showToast('No bus stops found nearby', 'warning');
                    }
                }).catch(function () {
                    SawariUtils.showToast('Could not find nearby stops', 'error');
                }).finally(function () {
                    btn.classList.remove('locating');
                    btn.disabled = false;
                });
            },
            function (err) {
                var msg = 'Could not get location';
                if (err.code === 1) msg = 'Location access denied';
                SawariUtils.showToast(msg, 'error');
                btn.classList.remove('locating');
                btn.disabled = false;
            },
            { enableHighAccuracy: true, timeout: 15000 }
        );
    }

    // ─── Trigger Route Search ───────────────────────────────
    function triggerSearch() {
        var startId = document.getElementById('startLocationId').value;
        var endId = document.getElementById('endLocationId').value;

        if (!startId) {
            SawariUtils.showToast('Please select a starting point', 'warning');
            document.getElementById('startInput').focus();
            pulseField('startField');
            return;
        }
        if (!endId) {
            SawariUtils.showToast('Please select a destination', 'warning');
            document.getElementById('endInput').focus();
            pulseField('endField');
            return;
        }
        if (startId === endId) {
            SawariUtils.showToast('Start and destination cannot be the same', 'warning');
            return;
        }

        var passengerType = document.getElementById('passengerType').value;
        SawariMap.showLoading();
        var btnSearch = document.getElementById('btnSearch');
        btnSearch.disabled = true;
        btnSearch.querySelector('span').textContent = 'Searching...';
        SawariMap.closeResults();

        var body = new URLSearchParams();
        body.append('start_location_id', startId);
        body.append('destination_location_id', endId);
        body.append('passenger_type', passengerType);

        // Send actual user coordinates for walking guidance
        if (actualStartCoords) {
            body.append('start_lat', actualStartCoords.lat);
            body.append('start_lng', actualStartCoords.lng);
        }
        if (actualEndCoords) {
            body.append('end_lat', actualEndCoords.lat);
            body.append('end_lng', actualEndCoords.lng);
        }

        SawariUtils.apiFetch(BASE_URL + '/api/search/find-route.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        }).then(function (data) {
            SawariMap.displayResults(data);
        }).catch(function () {
            SawariMap.displayResults({ success: false, message: 'Route search failed. Please try again.' });
        }).finally(function () {
            SawariMap.hideLoading();
            btnSearch.disabled = false;
            btnSearch.querySelector('span').textContent = 'Find Route';
        });
    }

    function pulseField(id) {
        var el = document.getElementById(id);
        if (el) { el.classList.add('field-error'); setTimeout(function () { el.classList.remove('field-error'); }, 1500); }
    }

    return {
        init: init,
        enterPickMode: enterPickMode,
        exitPickMode: exitPickMode,
        setStart: setStart,
        setEnd: setEnd
    };
})();

document.addEventListener('DOMContentLoaded', function () {
    SawariSearch.init();
});
