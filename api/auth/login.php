<?php
/**
 * Login API — Sawari
 * 
 * POST: Authenticate an agent or admin.
 * Params: email, password, role (agent|admin)
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

// Determine if this is an AJAX (JSON) or form request
$isAjax = isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json');

// Get inputs
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$role = trim($_POST['role'] ?? 'agent');

// ─── Validation ──────────────────────────────────────────
$errors = [];

if (empty($email)) {
    $errors[] = 'Email is required.';
} elseif (!validateEmail($email)) {
    $errors[] = 'Please enter a valid email address.';
}

if (empty($password)) {
    $errors[] = 'Password is required.';
}

if (!in_array($role, ['agent', 'admin'])) {
    $errors[] = 'Invalid role selected.';
}

// CSRF check for form submissions
if (!$isAjax && !verifyCSRFToken($_POST['csrf_token'] ?? null)) {
    $errors[] = 'Invalid form submission. Please try again.';
}

if (!empty($errors)) {
    if ($isAjax) {
        jsonResponse(['success' => false, 'message' => implode(' ', $errors)], 400);
    }
    setFlashMessage('error', implode(' ', $errors));
    redirect('pages/auth/login.php');
}

// ─── Attempt Login ───────────────────────────────────────
if ($role === 'admin') {
    $result = loginAdmin($email, $password);
} else {
    $result = loginAgent($email, $password);
}

if ($result['success']) {
    if ($isAjax) {
        jsonResponse([
            'success' => true,
            'message' => $result['message'],
            'redirect' => $role === 'admin'
                ? BASE_URL . '/pages/admin/dashboard.php'
                : BASE_URL . '/pages/agent/dashboard.php',
        ]);
    }
    setFlashMessage('success', 'Welcome back, ' . getCurrentUserName() . '!');
    redirect($role === 'admin' ? 'pages/admin/dashboard.php' : 'pages/agent/dashboard.php');
} else {
    if ($isAjax) {
        jsonResponse(['success' => false, 'message' => $result['message']], 401);
    }
    setFlashMessage('error', $result['message']);
    redirect('pages/auth/login.php');
}
