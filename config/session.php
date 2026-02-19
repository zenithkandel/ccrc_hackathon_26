<?php
/**
 * Session Configuration & Helpers — Sawari
 * 
 * Initializes sessions with secure settings and provides
 * helper functions for authentication state management.
 */

// Only start session if not already active
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,           // Until browser closes
        'path' => '/',
        'domain' => '',
        'secure' => false,       // Set true in production with HTTPS
        'httponly' => true,        // Prevent JS access to session cookie
        'samesite' => 'Lax',       // CSRF protection
    ]);
    session_start();
}

/**
 * Check if a user is currently logged in.
 */
function isLoggedIn(): bool
{
    return isset($_SESSION['user_id'], $_SESSION['user_role']);
}

/**
 * Check if the logged-in user is an admin.
 */
function isAdmin(): bool
{
    return isLoggedIn() && $_SESSION['user_role'] === 'admin';
}

/**
 * Check if the logged-in user is an agent.
 */
function isAgent(): bool
{
    return isLoggedIn() && $_SESSION['user_role'] === 'agent';
}

/**
 * Get the current logged-in user's ID.
 */
function getCurrentUserId(): ?int
{
    return isLoggedIn() ? (int) $_SESSION['user_id'] : null;
}

/**
 * Get the current logged-in user's role.
 * 
 * @return string|null 'admin', 'agent', or null
 */
function getCurrentUserRole(): ?string
{
    return $_SESSION['user_role'] ?? null;
}

/**
 * Get the current logged-in user's name.
 */
function getCurrentUserName(): ?string
{
    return $_SESSION['user_name'] ?? null;
}

/**
 * Require authentication with a specific role.
 * Redirects to login page if not authenticated or wrong role.
 * 
 * @param string|null $role Required role ('admin', 'agent') or null for any authenticated user
 */
function requireAuth(?string $role = null): void
{
    if (!isLoggedIn()) {
        setFlashMessage('error', 'Please log in to access this page.');
        header('Location: ' . BASE_URL . '/pages/auth/login.php');
        exit;
    }

    if ($role !== null && $_SESSION['user_role'] !== $role) {
        setFlashMessage('error', 'You do not have permission to access this page.');
        header('Location: ' . BASE_URL . '/pages/auth/login.php');
        exit;
    }
}

/**
 * Set a one-time flash message in the session.
 * 
 * @param string $type Message type: 'success', 'error', 'warning', 'info'
 * @param string $message The message text
 */
function setFlashMessage(string $type, string $message): void
{
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

/**
 * Retrieve and clear the flash message.
 * 
 * @return array|null ['type' => '...', 'message' => '...'] or null
 */
function getFlashMessage(): ?array
{
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Set session data upon successful login.
 * 
 * @param int    $userId  The user's ID (agent_id or admin_id)
 * @param string $role    'admin' or 'agent'
 * @param string $name    The user's display name
 * @param string $email   The user's email
 */
function setLoginSession(int $userId, string $role, string $name, string $email): void
{
    // Regenerate session ID to prevent session fixation attacks
    session_regenerate_id(true);

    $_SESSION['user_id'] = $userId;
    $_SESSION['user_role'] = $role;
    $_SESSION['user_name'] = $name;
    $_SESSION['user_email'] = $email;
    $_SESSION['logged_in_at'] = date('Y-m-d H:i:s');
}

/**
 * Destroy the current session (logout).
 */
function destroySession(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}
