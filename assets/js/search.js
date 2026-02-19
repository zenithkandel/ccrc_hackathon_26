/**
 * SAWARI — Search Controller
 * 
 * Point A / Point B input handling, location autocomplete,
 * geocoding, and search result display.
 */

const SawariSearch = (function () {
    'use strict';

    const BASE = document.querySelector('meta[name="base-url"]').content;

    // Debounce timer
    let debounceA = null;
    let debounceB = null;
    const DEBOUNCE_MS = 300;
    const MIN_CHARS = 2;

    // DOM refs (cached on init)
    let inputA, inputB, resultsA, resultsB, clearA, clearB;

    /* ──────────────────────────────────────────────
     *  Init
     * ────────────────────────────────────────────── */
    function init() {
        inputA = document.getElementById('input-a');
        inputB = document.getElementById('input-b');
        resultsA = document.getElementById('results-a');
        resultsB = document.getElementById('results-b');
        clearA = document.getElementById('clear-a');
        clearB = document.getElementById('clear-b');

        // Input listeners
        inputA.addEventListener('input', () => onInput('a'));
        inputB.addEventListener('input', () => onInput('b'));

        // Focus listeners — show results if we have cached items
        inputA.addEventListener('focus', () => onFocus('a'));
        inputB.addEventListener('focus', () => onFocus('b'));

        // Clear buttons
        clearA.addEventListener('click', () => {
            SawariMap.clearPointA();
            closeResults('a');
            inputA.focus();
        });
        clearB.addEventListener('click', () => {
            SawariMap.clearPointB();
            closeResults('b');
            inputB.focus();
        });

        // Close results when clicking elsewhere
        document.addEventListener('click', (e) => {
            if (!inputA.contains(e.target) && !resultsA.contains(e.target) && !clearA.contains(e.target)) {
                closeResults('a');
            }
            if (!inputB.contains(e.target) && !resultsB.contains(e.target) && !clearB.contains(e.target)) {
                closeResults('b');
            }
        });

        // Keyboard navigation
        inputA.addEventListener('keydown', (e) => onKeydown(e, 'a'));
        inputB.addEventListener('keydown', (e) => onKeydown(e, 'b'));

        // Load alerts
        loadAlerts();
    }

    /* ──────────────────────────────────────────────
     *  Input handler with debounce
     * ────────────────────────────────────────────── */
    function onInput(which) {
        const input = which === 'a' ? inputA : inputB;
        const query = input.value.trim();

        // Clear selected state since user is typing
        delete input.dataset.lat;
        delete input.dataset.lng;
        SawariMap.checkFindRouteReady();

        if (which === 'a') {
            if (debounceA) clearTimeout(debounceA);
            document.getElementById('clear-a').style.display = query ? '' : 'none';
        } else {
            if (debounceB) clearTimeout(debounceB);
            document.getElementById('clear-b').style.display = query ? '' : 'none';
        }

        if (query.length < MIN_CHARS) {
            closeResults(which);
            return;
        }

        const timer = setTimeout(() => searchLocations(query, which), DEBOUNCE_MS);
        if (which === 'a') debounceA = timer;
        else debounceB = timer;
    }

    function onFocus(which) {
        const container = which === 'a' ? resultsA : resultsB;
        // If there are already results, show them
        if (container.children.length > 0) {
            container.classList.add('open');
        }
    }

    /* ──────────────────────────────────────────────
     *  Search API call
     * ────────────────────────────────────────────── */
    function searchLocations(query, which) {
        fetch(BASE + '/api/locations.php?action=search&q=' + encodeURIComponent(query))
            .then(r => {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            })
            .then(data => {
                if (!data.success || !data.locations) return;
                renderResults(data.locations, which);
            })
            .catch(err => {
                console.error('Search error:', err);
            });
    }

    /* ──────────────────────────────────────────────
     *  Render search results dropdown
     * ────────────────────────────────────────────── */
    function renderResults(locations, which) {
        const container = which === 'a' ? resultsA : resultsB;
        container.innerHTML = '';

        if (locations.length === 0) {
            container.innerHTML = '<div style="padding:var(--space-4);text-align:center;font-size:var(--text-sm);color:var(--color-neutral-400);">No locations found</div>';
            container.classList.add('open');
            return;
        }

        locations.forEach((loc, idx) => {
            const item = document.createElement('div');
            item.className = 'search-result-item';
            item.dataset.index = idx;
            item.dataset.id = loc.location_id;
            item.dataset.lat = loc.latitude;
            item.dataset.lng = loc.longitude;
            item.dataset.name = loc.name;

            const typeIcon = loc.type === 'landmark' ? 'map-pin' : 'circle';
            item.innerHTML = `
                <i data-feather="${typeIcon}" style="width:16px;height:16px;color:var(--color-neutral-400);flex-shrink:0;"></i>
                <div>
                    <div style="font-size:var(--text-sm);color:var(--color-neutral-800);">${SawariMap.escHtml(loc.name)}</div>
                    <div style="font-size:var(--text-xs);color:var(--color-neutral-400);">${SawariMap.escHtml(loc.type)}</div>
                </div>
            `;

            item.addEventListener('click', () => selectResult(loc, which));
            container.appendChild(item);
        });

        container.classList.add('open');
        feather.replace({ 'stroke-width': 1.75 });
    }

    /* ──────────────────────────────────────────────
     *  Select a search result
     * ────────────────────────────────────────────── */
    function selectResult(loc, which) {
        const lat = parseFloat(loc.latitude);
        const lng = parseFloat(loc.longitude);
        const name = loc.name;

        if (which === 'a') {
            SawariMap.setPointA(lat, lng, name);
        } else {
            SawariMap.setPointB(lat, lng, name);
        }

        closeResults(which);

        // Focus the map on the selected point
        SawariMap.getMap().setView([lat, lng], 15);
    }

    /* ──────────────────────────────────────────────
     *  Keyboard navigation in results
     * ────────────────────────────────────────────── */
    function onKeydown(e, which) {
        const container = which === 'a' ? resultsA : resultsB;
        if (!container.classList.contains('open')) return;

        const items = container.querySelectorAll('.search-result-item');
        if (items.length === 0) return;

        let activeIdx = -1;
        items.forEach((item, i) => {
            if (item.classList.contains('selected')) activeIdx = i;
        });

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            activeIdx = Math.min(activeIdx + 1, items.length - 1);
            highlightItem(items, activeIdx);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            activeIdx = Math.max(activeIdx - 1, 0);
            highlightItem(items, activeIdx);
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (activeIdx >= 0 && items[activeIdx]) {
                items[activeIdx].click();
            }
        } else if (e.key === 'Escape') {
            closeResults(which);
        }
    }

    function highlightItem(items, idx) {
        items.forEach(i => i.classList.remove('selected'));
        if (items[idx]) {
            items[idx].classList.add('selected');
            items[idx].scrollIntoView({ block: 'nearest' });
        }
    }

    /* ──────────────────────────────────────────────
     *  Close results dropdown
     * ────────────────────────────────────────────── */
    function closeResults(which) {
        const container = which === 'a' ? resultsA : resultsB;
        container.classList.remove('open');
    }

    /* ──────────────────────────────────────────────
     *  Programmatic setters (used by map.js on drag)
     * ────────────────────────────────────────────── */
    function setInputA(lat, lng, name) {
        inputA.value = name;
        inputA.dataset.lat = lat;
        inputA.dataset.lng = lng;
        clearA.style.display = '';
        SawariMap.checkFindRouteReady();
    }

    function setInputB(lat, lng, name) {
        inputB.value = name;
        inputB.dataset.lat = lat;
        inputB.dataset.lng = lng;
        clearB.style.display = '';
        SawariMap.checkFindRouteReady();
    }

    /* ──────────────────────────────────────────────
     *  Load active alerts
     * ────────────────────────────────────────────── */
    function loadAlerts() {
        fetch(BASE + '/api/alerts.php?action=active')
            .then(r => r.json())
            .then(data => {
                if (!data.success || !data.alerts || data.alerts.length === 0) return;

                const banner = document.getElementById('alerts-banner');
                const text = document.getElementById('alert-text');
                if (!banner || !text) return;

                // Show most severe alert in banner
                const alert = data.alerts[0];
                let msg = alert.title;
                if (alert.route_name) {
                    msg += ' — ' + alert.route_name;
                }
                text.textContent = msg;
                banner.style.display = '';
                banner.title = alert.description || '';

                // Show alert indicators on map for route-specific alerts
                showAlertMarkers(data.alerts);
            })
            .catch(err => console.error('Alerts load error:', err));
    }

    /**
     * Show alert markers on the map for route-specific alerts.
     * Fetches each route's first stop and places a warning marker there.
     */
    function showAlertMarkers(alerts) {
        const alertLayer = SawariMap.getAlertLayer();
        if (!alertLayer) return;

        alerts.forEach(alert => {
            if (!alert.route_id) return;

            // Fetch route info to get first stop coords
            fetch(BASE + '/api/routes.php?action=get&id=' + alert.route_id)
                .then(r => r.json())
                .then(data => {
                    if (!data.success || !data.route || !data.route.location_list) return;

                    const stops = typeof data.route.location_list === 'string'
                        ? JSON.parse(data.route.location_list)
                        : data.route.location_list;

                    if (!stops || stops.length === 0) return;

                    // Place alert marker at first stop of affected route
                    const firstStop = stops[0];
                    const severityColors = {
                        critical: '#DC2626',
                        high: '#EA580C',
                        medium: '#D97706',
                        low: '#6B7280'
                    };
                    const color = severityColors[alert.severity] || '#D97706';

                    const icon = L.divIcon({
                        className: 'marker-alert',
                        html: `<div class="marker-pin marker-pin-alert" style="background:${color};"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div>`,
                        iconSize: [28, 36],
                        iconAnchor: [14, 36]
                    });

                    L.marker([parseFloat(firstStop.latitude), parseFloat(firstStop.longitude)], { icon })
                        .bindPopup(`<div style="font-family:var(--font-sans);"><strong style="color:${color};">⚠ ${SawariMap.escHtml(alert.title)}</strong><br><span style="font-size:12px;">${SawariMap.escHtml(alert.description || '')}</span>${alert.route_name ? '<br><span style="font-size:11px;color:#64748B;">Route: ' + SawariMap.escHtml(alert.route_name) + '</span>' : ''}</div>`)
                        .addTo(alertLayer);
                })
                .catch(() => { });
        });
    }

    /* ──────────────────────────────────────────────
     *  Public API
     * ────────────────────────────────────────────── */
    return {
        init,
        setInputA,
        setInputB,
        closeResults
    };
})();

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', SawariSearch.init);
