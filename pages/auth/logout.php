<?php
/**
 * Logout Page — Sawari
 * 
 * Destroys the current session and redirects to the landing page.
 */

require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/session.php';

destroySession();

// Start a new session to set flash message
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$_SESSION['flash'] = [
    'type' => 'success',
    'message' => 'You have been logged out successfully.'
];

header('Location: ' . BASE_URL . '/');
exit;
