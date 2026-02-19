<?php
/**
 * Application Constants — Sawari
 * 
 * Centralized configuration values used throughout the app.
 */

// ─── Paths ───────────────────────────────────────────────
define('BASE_PATH', dirname(__DIR__)); // Points to project root

// Auto-detect BASE_URL from server environment (works in any directory)
if (php_sapi_name() !== 'cli' && !empty($_SERVER['DOCUMENT_ROOT'])) {
    $docRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
    $appRoot = rtrim(str_replace('\\', '/', BASE_PATH), '/');
    define('BASE_URL', str_replace($docRoot, '', $appRoot));
} else {
    define('BASE_URL', ''); // CLI mode — no URL context
}

define('UPLOAD_DIR', BASE_PATH . '/assets/images/uploads');
define('UPLOAD_URL', BASE_URL . '/assets/images/uploads');

// ─── File Upload Limits ──────────────────────────────────
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5 MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/webp']);
define('ALLOWED_IMAGE_EXTENSIONS', ['jpg', 'jpeg', 'png', 'webp']);

// ─── Pagination ──────────────────────────────────────────
define('ITEMS_PER_PAGE', 15);

// ─── External APIs ───────────────────────────────────────
define('OSRM_API_URL', 'https://router.project-osrm.org');

// ─── Fare Calculation (Nepal Public Transport) ───────────
define('FARE_BASE_RATE', 15);       // Base fare in NPR
define('FARE_PER_KM', 1.8);        // Per-km rate in NPR
define('FARE_ROUND_TO', 5);        // Round fare to nearest X NPR
define('STUDENT_DISCOUNT', 0.50);  // 50% discount
define('ELDERLY_DISCOUNT', 0.50);  // 50% discount

// ─── Map Defaults (Kathmandu Valley) ─────────────────────
define('DEFAULT_LAT', 27.7172);
define('DEFAULT_LNG', 85.3240);
define('DEFAULT_ZOOM', 13);
define('NEAREST_STOP_RADIUS_KM', 2.0); // Search radius for nearest bus stops

// ─── Pathfinding ─────────────────────────────────────────
define('TRANSFER_PENALTY_KM', 2.0);    // Extra "cost" for switching buses
define('AVG_BUS_SPEED_KMH', 15);       // Average bus speed in city
define('WALK_SPEED_KMH', 5);           // Average walking speed

// ─── Carbon Emission Factors (kg CO₂ per km) ────────────
define('EMISSION_PUBLIC_TRANSPORT', 0.089);
define('EMISSION_BIKE_RIDESHARE', 0.103);
define('EMISSION_CAR_TAXI', 0.192);
