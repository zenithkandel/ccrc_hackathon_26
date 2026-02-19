<?php
/**
 * API: Update Agent Profile
 * POST /api/agents/update.php
 * 
 * Agent updates own profile (name, phone_number, image).
 * 
 * Body params: name, phone_number, image (file, optional)
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/validation.php';

if (!isAgent()) {
    jsonResponse(['success' => false, 'message' => 'Agent access required.'], 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$input = $_POST;
if (empty($input)) {
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true) ?? [];
}

$agentId = getCurrentUserId();
$pdo = getDBConnection();

$stmt = $pdo->prepare('SELECT * FROM agents WHERE agent_id = :id');
$stmt->execute(['id' => $agentId]);
$agent = $stmt->fetch();

if (!$agent) {
    jsonResponse(['success' => false, 'message' => 'Agent not found.'], 404);
}

// Validate phone if provided
if (isset($input['phone_number']) && $input['phone_number'] !== '' && !validatePhone($input['phone_number'])) {
    jsonResponse(['success' => false, 'message' => 'Invalid Nepali phone number (98XXXXXXXX or 97XXXXXXXX).'], 400);
}

// Handle image upload
$imagePath = $agent['image_path'];
if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
    $imageValidation = validateImageUpload($_FILES['image']);
    if ($imageValidation !== true) {
        jsonResponse(['success' => false, 'message' => $imageValidation], 400);
    }
    $newPic = uploadImage($_FILES['image'], 'profiles');
    if ($newPic !== false) {
        deleteImage($agent['image_path']);
        $imagePath = $newPic;
    }
}

try {
    $stmt = $pdo->prepare('
        UPDATE agents SET 
            name = :name, phone_number = :phone_number, image_path = :image_path
        WHERE agent_id = :id
    ');
    $stmt->execute([
        'name' => trim($input['name'] ?? $agent['name']),
        'phone_number' => $input['phone_number'] ?? $agent['phone_number'],
        'image_path' => $imagePath,
        'id' => $agentId,
    ]);

    // Update session name if changed
    if (isset($input['name']) && trim($input['name']) !== $agent['name']) {
        $_SESSION['user_name'] = trim($input['name']);
    }

    jsonResponse(['success' => true, 'message' => 'Profile updated successfully.']);

} catch (Exception $e) {
    error_log('Update Agent Error: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Failed to update profile.'], 500);
}
