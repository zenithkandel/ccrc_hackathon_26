<?php
/**
 * SAWARI — Agent Authentication Guard
 * 
 * Include this file at the top of every agent page.
 * Redirects to agent login if not authenticated.
 */

require_once __DIR__ . '/../api/config.php';

if (!isAgentLoggedIn()) {
    header('Location: ' . BASE_URL . '/pages/agent/login.php');
    exit;
}