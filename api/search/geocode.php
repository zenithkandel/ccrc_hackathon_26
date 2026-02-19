<?php
/**
 * Geocode Search (Nominatim Proxy) — Sawari
 *
 * GET /api/search/geocode.php?q=<term>
 *
 * Searches OpenStreetMap Nominatim for places not in our local DB.
 * Scoped to the Kathmandu Valley area (~27.6–27.8 lat, ~85.2–85.5 lng).
 * Returns max 5 results with name, lat, lng, type.
 */

require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$query = trim(getParam('q', ''));

if (strlen($query) < 2) {
    jsonResponse(['results' => []]);
}

// Rate-limit: simple file-based throttle (1 request per second per IP)
$cacheDir = __DIR__ . '/../../cache';
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0755, true);
}
$lockFile = $cacheDir . '/nominatim_' . md5(getClientIP()) . '.lock';
if (file_exists($lockFile) && (time() - filemtime($lockFile)) < 1) {
    jsonResponse(['results' => [], 'throttled' => true]);
}
@touch($lockFile);

// Kathmandu Valley bounding box (viewbox + bounded)
$viewbox = '85.20,27.60,85.50,27.80';

$url = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
    'q' => $query,
    'format' => 'jsonv2',
    'addressdetails' => 1,
    'limit' => 5,
    'viewbox' => $viewbox,
    'bounded' => 1,
    'countrycodes' => 'np',
]);

$context = stream_context_create([
    'http' => [
        'timeout' => 5,
        'method' => 'GET',
        'header' => "User-Agent: Sawari/1.0 (student-project)\r\nAccept-Language: en\r\n",
        'ignore_errors' => true,
    ],
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false,
    ],
]);

$response = @file_get_contents($url, false, $context);

if ($response === false) {
    jsonResponse(['results' => [], 'error' => 'Geocoding service unavailable']);
}

$data = json_decode($response, true);

if (!is_array($data)) {
    jsonResponse(['results' => [], 'error' => 'Invalid response from geocoding service']);
}

$results = [];
foreach ($data as $place) {
    $name = $place['display_name'] ?? '';
    // Shorten: take the first 2-3 parts of the display name
    $parts = explode(', ', $name);
    $shortName = implode(', ', array_slice($parts, 0, 3));

    $type = $place['type'] ?? 'place';
    $category = $place['category'] ?? '';

    // Map OSM categories to user-friendly types
    $friendlyType = 'Place';
    if ($category === 'amenity')
        $friendlyType = ucfirst($type);
    elseif ($category === 'education' || $type === 'college' || $type === 'school' || $type === 'university')
        $friendlyType = 'Education';
    elseif ($category === 'building')
        $friendlyType = 'Building';
    elseif ($category === 'tourism' || $type === 'hotel' || $type === 'attraction')
        $friendlyType = 'Tourism';
    elseif ($category === 'healthcare' || $type === 'hospital' || $type === 'clinic')
        $friendlyType = 'Healthcare';
    elseif ($category === 'shop')
        $friendlyType = 'Shop';
    elseif ($type === 'residential' || $type === 'neighbourhood')
        $friendlyType = 'Area';
    else
        $friendlyType = ucfirst(str_replace('_', ' ', $type));

    $results[] = [
        'name' => $shortName,
        'full_name' => $name,
        'latitude' => (float) $place['lat'],
        'longitude' => (float) $place['lon'],
        'type' => $friendlyType,
        'source' => 'nominatim',
    ];
}

jsonResponse(['results' => $results]);
