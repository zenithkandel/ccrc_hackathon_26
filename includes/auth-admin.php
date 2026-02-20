<?php
/**
 * SAWARI — Admin Authentication Guard
 * 
 * Include this file at the top of every admin page.
 * Redirects to admin login if not authenticated.
 */

require_once __DIR__ . '/../api/config.php';

if (!isAdminLoggedIn()) {
    header('Location: ' . BASE_URL . '/pages/admin/login.php');
    exit;
}