<?php
/**
 * Map Page — Sawari
 * 
 * Full-screen Leaflet map with search bar, floating results panel,
 * alert banner, and feedback modal.
 */

$pageTitle = 'Find Route — Sawari';
$pageCss = ['map.css'];
$bodyClass = 'page-map';
$useLeaflet = true;
$pageJs = ['search.js', 'map.js'];

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

// ─── Fetch active alerts for banner ──────────────────────
$db = getDBConnection();
$alertStmt = $db->prepare("
    SELECT alert_id, name, description, routes_affected
    FROM alerts
    WHERE expires_at IS NULL OR expires_at > NOW()
    ORDER BY reported_at DESC
    LIMIT 5
");
$alertStmt->execute();
$activeAlerts = $alertStmt->fetchAll();
?>

<!-- ═══ Search Bar ═════════════════════════════════════════ -->
<div class="map-search-bar" id="searchBar">
    <div class="search-container">
        <div class="search-inputs">
            <!-- Starting Point -->
            <div class="search-field" id="startField">
                <span class="search-field-icon search-icon-start">A</span>
                <input type="text" id="startInput" class="search-input" placeholder="Starting point — type or click map"
                    autocomplete="off">
                <input type="hidden" id="startLocationId">
                <div class="search-field-actions">
                    <button type="button" class="btn-icon btn-geolocate" id="btnGeolocate" title="Use my location">
                        <i class="fa-duotone fa-solid fa-crosshairs"></i>
                    </button>
                    <button type="button" class="btn-icon btn-map-pick" id="btnPickStart" title="Pick on map">
                        <i class="fa-duotone fa-solid fa-map-pin"></i>
                    </button>
                </div>
                <div class="autocomplete-dropdown" id="startDropdown"></div>
            </div>

            <!-- Swap Button -->
            <button type="button" class="btn-swap" id="btnSwap" title="Swap locations">
                <i class="fa-sharp fa-solid fa-arrow-up-arrow-down"></i>
            </button>

            <!-- Destination -->
            <div class="search-field" id="endField">
                <span class="search-field-icon search-icon-end">B</span>
                <input type="text" id="endInput" class="search-input" placeholder="Destination — type or click map"
                    autocomplete="off">
                <input type="hidden" id="endLocationId">
                <div class="search-field-actions">
                    <button type="button" class="btn-icon btn-map-pick" id="btnPickEnd" title="Pick on map">
                        <i class="fa-duotone fa-solid fa-map-pin"></i>
                    </button>
                </div>
                <div class="autocomplete-dropdown" id="endDropdown"></div>
            </div>
        </div>

        <!-- Passenger Type & Search -->
        <div class="search-actions">
            <select id="passengerType" class="search-select">
                <option value="regular"><i class="fa-duotone fa-solid fa-user"></i> Regular</option>
                <option value="student"><i class="fa-duotone fa-solid fa-graduation-cap"></i> Student (50% off)</option>
                <option value="elderly"><i class="fa-duotone fa-solid fa-person-cane"></i> Elderly (50% off)</option>
            </select>
            <button type="button" class="btn-search" id="btnSearch">
                <i class="fa-sharp fa-solid fa-magnifying-glass"></i>
                <span>Find Route</span>
            </button>
            <button type="button" class="btn-tourist-toggle" id="btnTouristToggle" title="Toggle Tourist Help Mode">
                <i class="fa-duotone fa-solid fa-earth-americas"></i>
            </button>
        </div>
    </div>

    <!-- Map pick mode banner -->
    <div class="map-pick-banner" id="mapPickBanner" style="display:none;">
        <span class="map-pick-banner-icon"><i class="fa-duotone fa-solid fa-location-dot"></i></span>
        <span id="mapPickBannerText">Click on the map to select your starting point</span>
        <button type="button" class="btn-pick-cancel" id="btnPickCancel">Cancel</button>
    </div>
</div>

<!-- ═══ Alert Banner ═══════════════════════════════════════ -->
<?php if (!empty($activeAlerts)): ?>
    <div class="alert-banner" id="alertBanner">
        <div class="alert-banner-content">
            <span class="alert-banner-icon"><i class="fa-sharp-duotone fa-solid fa-triangle-exclamation"></i></span>
            <div class="alert-banner-text">
                <?php foreach ($activeAlerts as $alert): ?>
                    <div class="alert-banner-item">
                        <strong><?= sanitize($alert['name']) ?></strong>
                        <?php if ($alert['description']): ?>
                            — <?= sanitize(mb_substr($alert['description'], 0, 100)) ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="alert-banner-close" id="alertBannerClose"><i
                    class="fa-solid fa-xmark"></i></button>
        </div>
    </div>
<?php endif; ?>

<!-- ═══ Leaflet Map ════════════════════════════════════════ -->
<div id="map" class="map-container"></div>

<!-- ═══ Floating Results Panel ═════════════════════════════ -->
<div class="results-panel" id="resultsPanel">
    <div class="results-panel-drag" id="resultsPanelDrag">
        <div class="results-drag-handle"></div>
    </div>
    <div class="results-panel-header">
        <h3 class="results-title">
            <i class="fa-sharp fa-solid fa-chevron-right"></i>
            Route Details
        </h3>
        <button type="button" class="results-close" id="resultsPanelClose">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>
    <div class="results-panel-body" id="resultsPanelBody">
        <!-- Populated by map.js -->
    </div>
    <div class="results-panel-footer" id="resultsPanelFooter" style="display:none;">
        <button type="button" class="btn-feedback" id="btnFeedback">
            <i class="fa-duotone fa-solid fa-comment"></i>
            Give Feedback
        </button>
    </div>
</div>

<!-- ═══ Loading Overlay ════════════════════════════════════ -->
<div class="map-loading" id="mapLoading" style="display:none;">
    <div class="map-loading-spinner"></div>
    <p>Finding the best route...</p>
</div>

<!-- ═══ Feedback Modal ═════════════════════════════════════ -->
<div class="modal-backdrop" id="feedbackModal" style="display:none;">
    <div class="modal-content modal-sm">
        <div class="modal-header">
            <h3>Send Feedback</h3>
            <button type="button" class="modal-close" data-close-modal><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body">
            <form id="feedbackForm">
                <div class="form-group">
                    <label for="feedbackType">Type</label>
                    <select id="feedbackType" name="type" class="form-input" required>
                        <option value="">Select type...</option>
                        <option value="suggestion">Suggestion</option>
                        <option value="complaint">Complaint</option>
                        <option value="correction">Correction</option>
                        <option value="appreciation">Appreciation</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="feedbackRating">Rating</label>
                    <div class="star-rating" id="starRating">
                        <span class="star" data-rating="1"><i class="fa-sharp-duotone fa-solid fa-star"></i></span>
                        <span class="star" data-rating="2"><i class="fa-sharp-duotone fa-solid fa-star"></i></span>
                        <span class="star" data-rating="3"><i class="fa-sharp-duotone fa-solid fa-star"></i></span>
                        <span class="star" data-rating="4"><i class="fa-sharp-duotone fa-solid fa-star"></i></span>
                        <span class="star" data-rating="5"><i class="fa-sharp-duotone fa-solid fa-star"></i></span>
                    </div>
                    <input type="hidden" id="feedbackRating" name="rating" value="">
                </div>
                <div class="form-group">
                    <label for="feedbackMessage">Message</label>
                    <textarea id="feedbackMessage" name="message" class="form-input" rows="4"
                        placeholder="Tell us about your experience..." required></textarea>
                </div>
                <input type="hidden" id="feedbackRouteId" name="related_route_id" value="">
                <input type="hidden" id="feedbackVehicleId" name="related_vehicle_id" value="">
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" data-close-modal>Cancel</button>
                    <button type="submit" class="btn btn-primary" id="btnSubmitFeedback">Submit</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Map config passed to JS -->
<script>
    window.SAWARI_MAP_CONFIG = {
        defaultLat: <?= DEFAULT_LAT ?>,
        defaultLng: <?= DEFAULT_LNG ?>,
        defaultZoom: <?= DEFAULT_ZOOM ?>,
        baseUrl: '<?= BASE_URL ?>',
        alerts: <?= json_encode($activeAlerts) ?>
    };

    window.SAWARI_TOURIST_TIPS = {
        boarding: [
            { nepali: "[Destination] jāne bus ho?", english: "Does this bus go to [Destination]?" },
            { nepali: "Yahā̃ basna milchha?", english: "Can I sit here?" }
        ],
        alighting: [
            { nepali: "Rokdinuhos", english: "Please stop here" },
            { nepali: "Dherai dhanyabad", english: "Thank you very much" }
        ],
        precautions: [
            "Keep your belongings close — especially in crowded buses.",
            "Hold on tight during the ride, roads can be bumpy.",
            "Buses may not come to full stop — be ready to hop on/off.",
            "Window seats are more comfortable on longer rides."
        ],
        payment: [
            "Have exact change ready — fares are paid in cash to the conductor.",
            "Fares are typically collected during the ride, not when boarding.",
            "Coins of NPR 1, 2, 5, 10 are commonly used for bus fares.",
            "If unsure of the fare, ask the conductor before boarding."
        ],
        general: [
            "Rush hour is 8-10 AM and 4-6 PM — expect crowded buses.",
            "Micro-buses (Tempo) are smaller but faster than full-size buses.",
            "Sajha Yatayat buses are the most organized and tourist-friendly.",
            "Most buses stop running by 8-9 PM."
        ]
    };
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>