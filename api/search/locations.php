<?php
/**
 * Location Autocomplete Search — Sawari
 * 
 * GET /api/search/locations.php?q=<term>
 * 
 * Searches approved locations by name for autocomplete.
 * Returns max 10 results ordered by relevance.
 */

require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

// ─── Validate request method ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

// ─── Get search term ─────────────────────────────────────
$query = trim(getParam('q', ''));

if (strlen($query) < 1) {
    jsonResponse(['locations' => []]);
}

// ─── Search approved locations ───────────────────────────
try {
    $db = getDBConnection();

    // Exact prefix matches first, then contains matches
    $stmt = $db->prepare("
        SELECT location_id, name, latitude, longitude, type
        FROM locations
        WHERE status = 'approved' AND name LIKE :search
        ORDER BY 
            CASE WHEN name LIKE :prefix THEN 0 ELSE 1 END,
            name ASC
        LIMIT 10
    ");

    $stmt->execute([
        ':search' => '%' . $query . '%',
        ':prefix' => $query . '%'
    ]);

    $locations = $stmt->fetchAll();

    // Cast numeric fields
    foreach ($locations as &$loc) {
        $loc['location_id'] = (int) $loc['location_id'];
        $loc['latitude'] = (float) $loc['latitude'];
        $loc['longitude'] = (float) $loc['longitude'];
    }

    jsonResponse(['locations' => $locations]);

} catch (PDOException $e) {
    jsonResponse(['error' => 'Search failed'], 500);
}
