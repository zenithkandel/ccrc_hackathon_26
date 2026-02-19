<?php
/**
 * API: Create Suggestion
 * POST /api/suggestions/create.php
 * 
 * Public endpoint — anyone can submit a suggestion (no auth required).
 * 
 * Body params: type (complaint|suggestion|correction|appreciation), message (required),
 *              rating (1-5, optional), related_route_id (optional), related_vehicle_id (optional)
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/validation.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$input = $_POST;
if (empty($input)) {
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true) ?? [];
}

$missing = validateRequired(['type', 'message'], $input);
if (!empty($missing)) {
    jsonResponse(['success' => false, 'message' => 'Missing required fields: ' . implode(', ', $missing)], 400);
}

if (!validateEnum($input['type'], ['complaint', 'suggestion', 'correction', 'appreciation'])) {
    jsonResponse(['success' => false, 'message' => 'Type must be complaint, suggestion, correction, or appreciation.'], 400);
}

$message = trim($input['message']);
if (strlen($message) < 10) {
    jsonResponse(['success' => false, 'message' => 'Message must be at least 10 characters.'], 400);
}
if (strlen($message) > 2000) {
    jsonResponse(['success' => false, 'message' => 'Message must not exceed 2000 characters.'], 400);
}

if (isset($input['rating']) && !validateRating((int) $input['rating'])) {
    jsonResponse(['success' => false, 'message' => 'Rating must be between 1 and 5.'], 400);
}

$pdo = getDBConnection();

// Validate related_route_id if provided
$relatedRouteId = null;
if (!empty($input['related_route_id'])) {
    $relatedRouteId = (int) $input['related_route_id'];
    $stmt = $pdo->prepare('SELECT route_id FROM routes WHERE route_id = :id');
    $stmt->execute(['id' => $relatedRouteId]);
    if (!$stmt->fetch()) {
        jsonResponse(['success' => false, 'message' => 'Related route not found.'], 400);
    }
}

// Validate related_vehicle_id if provided
$relatedVehicleId = null;
if (!empty($input['related_vehicle_id'])) {
    $relatedVehicleId = (int) $input['related_vehicle_id'];
    $stmt = $pdo->prepare('SELECT vehicle_id FROM vehicles WHERE vehicle_id = :id');
    $stmt->execute(['id' => $relatedVehicleId]);
    if (!$stmt->fetch()) {
        jsonResponse(['success' => false, 'message' => 'Related vehicle not found.'], 400);
    }
}

try {
    $stmt = $pdo->prepare('
        INSERT INTO suggestions (type, message, rating, related_route_id, related_vehicle_id, ip_address, submitted_at)
        VALUES (:type, :message, :rating, :route_id, :vehicle_id, :ip, NOW())
    ');
    $stmt->execute([
        'type' => $input['type'],
        'message' => $message,
        'rating' => isset($input['rating']) ? (int) $input['rating'] : null,
        'route_id' => $relatedRouteId,
        'vehicle_id' => $relatedVehicleId,
        'ip' => getClientIP(),
    ]);

    $suggestionId = (int) $pdo->lastInsertId();

    jsonResponse([
        'success' => true,
        'message' => 'Thank you! Your suggestion has been submitted.',
        'suggestion_id' => $suggestionId,
    ], 201);

} catch (Exception $e) {
    error_log('Create Suggestion Error: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Failed to submit suggestion.'], 500);
}
