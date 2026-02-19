<?php
/**
 * Registration API — Sawari
 * 
 * POST: Register a new agent account.
 * Params: name, email, phone_number, password, confirm_password, image (optional file)
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/validation.php';
require_once __DIR__ . '/../../includes/auth.php';

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$isAjax = isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json');

// Get inputs
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phoneNumber = trim($_POST['phone_number'] ?? '');
$password = $_POST['password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

// ─── Validation ──────────────────────────────────────────
$errors = [];

// CSRF check for form submissions
if (!$isAjax && !verifyCSRFToken($_POST['csrf_token'] ?? null)) {
    $errors[] = 'Invalid form submission. Please try again.';
}

if (empty($name) || strlen($name) < 2) {
    $errors[] = 'Name is required (minimum 2 characters).';
}

if (empty($email)) {
    $errors[] = 'Email is required.';
} elseif (!validateEmail($email)) {
    $errors[] = 'Please enter a valid email address.';
}

if (!empty($phoneNumber) && !validatePhone($phoneNumber)) {
    $errors[] = 'Please enter a valid Nepali phone number.';
}

if (empty($password)) {
    $errors[] = 'Password is required.';
} elseif (!validatePassword($password)) {
    $errors[] = 'Password must be at least 8 characters with at least 1 letter and 1 number.';
}

if ($password !== $confirmPassword) {
    $errors[] = 'Passwords do not match.';
}

// Handle image upload (optional)
$imagePath = null;
if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
    $imageValidation = validateImageUpload($_FILES['image']);
    if ($imageValidation !== true) {
        $errors[] = $imageValidation;
    } else {
        $imagePath = uploadImage($_FILES['image'], 'agents');
        if ($imagePath === false) {
            $errors[] = 'Failed to upload profile image.';
        }
    }
}

if (!empty($errors)) {
    // Clean up uploaded file on validation failure
    if ($imagePath) {
        deleteImage($imagePath);
    }

    if ($isAjax) {
        jsonResponse(['success' => false, 'message' => implode(' ', $errors), 'errors' => $errors], 400);
    }
    setFlashMessage('error', implode(' ', $errors));
    redirect('pages/auth/register.php');
}

// ─── Register Agent ──────────────────────────────────────
$result = registerAgent([
    'name' => $name,
    'email' => $email,
    'phone_number' => $phoneNumber,
    'password' => $password,
    'image_path' => $imagePath,
]);

if ($result['success']) {
    // Auto-login after registration
    $loginResult = loginAgent($email, $password);

    if ($isAjax) {
        jsonResponse([
            'success' => true,
            'message' => 'Registration successful! Welcome to Sawari.',
            'redirect' => BASE_URL . '/pages/agent/dashboard.php',
        ]);
    }
    setFlashMessage('success', 'Registration successful! Welcome to Sawari, ' . sanitize($name) . '!');
    redirect('pages/agent/dashboard.php');
} else {
    // Clean up uploaded file on registration failure
    if ($imagePath) {
        deleteImage($imagePath);
    }

    if ($isAjax) {
        jsonResponse(['success' => false, 'message' => $result['message']], 409);
    }
    setFlashMessage('error', $result['message']);
    redirect('pages/auth/register.php');
}
