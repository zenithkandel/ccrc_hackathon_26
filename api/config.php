<?php
/**
 * SAWARI — Database Connection & Global Configuration
 * 
 * This file handles:
 * - MySQL database connection (PDO)
 * - Global constants
 * - Helper functions
 * - Session initialization
 * 
 * Include this file at the top of every PHP file in the project.
 */

// ============================================================
// SESSION
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================================
// SECURITY HEADERS
// ============================================================
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// ============================================================
// DATABASE CONFIGURATION
// ============================================================
define('DB_HOST', 'localhost');
define('DB_NAME', 'sawari');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// ============================================================
// APPLICATION CONSTANTS
// ============================================================

// Auto-detect base URL
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
define('BASE_URL', $protocol . '://' . $host . '/CCRC');

// File paths
define('ROOT_DIR', dirname(__DIR__));
define('UPLOAD_DIR', ROOT_DIR . '/uploads');
define('VEHICLE_IMAGE_DIR', UPLOAD_DIR . '/vehicles');

// URL paths for uploaded files
define('VEHICLE_IMAGE_URL', BASE_URL . '/uploads/vehicles');

// Pagination defaults
define('DEFAULT_PAGE_SIZE', 20);
define('MAX_PAGE_SIZE', 100);

// ============================================================
// DATABASE CONNECTION (PDO Singleton)
// ============================================================

/**
 * Get the PDO database connection instance.
 * Uses a static variable to maintain a single connection per request.
 * 
 * @return PDO
 */
function getDB(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
            error_log('DB Connection Error: ' . $e->getMessage());
            exit;
        }
    }

    return $pdo;
}


// ============================================================
// RESPONSE HELPERS
// ============================================================

/**
 * Send a JSON response and exit.
 * 
 * @param array $data  Data to encode as JSON
 * @param int   $code  HTTP status code
 */
function jsonResponse(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Send a success JSON response.
 */
function jsonSuccess(string $message = 'Success', array $extra = []): void
{
    jsonResponse(array_merge(['success' => true, 'message' => $message], $extra));
}

/**
 * Send an error JSON response.
 */
function jsonError(string $message = 'An error occurred', int $code = 400): void
{
    jsonResponse(['success' => false, 'message' => $message], $code);
}


// ============================================================
// AUTHENTICATION HELPERS
// ============================================================

/**
 * Check if an admin is logged in.
 */
function isAdminLoggedIn(): bool
{
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

/**
 * Check if an agent is logged in.
 */
function isAgentLoggedIn(): bool
{
    return isset($_SESSION['agent_id']) && !empty($_SESSION['agent_id']);
}

/**
 * Get the currently logged-in admin's ID.
 */
function getAdminId(): ?int
{
    return $_SESSION['admin_id'] ?? null;
}

/**
 * Get the currently logged-in admin's role.
 */
function getAdminRole(): ?string
{
    return $_SESSION['admin_role'] ?? null;
}

/**
 * Get the currently logged-in agent's ID.
 */
function getAgentId(): ?int
{
    return $_SESSION['agent_id'] ?? null;
}

/**
 * Require admin authentication for API endpoints.
 * Sends 401 JSON response if not authenticated.
 */
function requireAdminAPI(): void
{
    if (!isAdminLoggedIn()) {
        jsonError('Authentication required', 401);
    }
}

/**
 * Require agent authentication for API endpoints.
 */
function requireAgentAPI(): void
{
    if (!isAgentLoggedIn()) {
        jsonError('Authentication required', 401);
    }
}


// ============================================================
// INPUT HELPERS
// ============================================================

/**
 * Get a sanitized string from POST data.
 */
function postString(string $key, string $default = ''): string
{
    return isset($_POST[$key]) ? trim($_POST[$key]) : $default;
}

/**
 * Get an integer from POST data.
 */
function postInt(string $key, int $default = 0): int
{
    return isset($_POST[$key]) ? (int) $_POST[$key] : $default;
}

/**
 * Get a sanitized string from GET data.
 */
function getString(string $key, string $default = ''): string
{
    return isset($_GET[$key]) ? trim($_GET[$key]) : $default;
}

/**
 * Get an integer from GET data.
 */
function getInt(string $key, int $default = 0): int
{
    return isset($_GET[$key]) ? (int) $_GET[$key] : $default;
}

/**
 * Get the requested action from GET parameters.
 */
function getAction(): string
{
    return getString('action');
}


// ============================================================
// PAGINATION HELPER
// ============================================================

/**
 * Calculate pagination from total count.
 * Reads page from GET params automatically.
 * Returns associative array: offset, per_page, page, total, total_pages.
 */
function paginate(int $total, ?int $page = null, int $perPage = DEFAULT_PAGE_SIZE): array
{
    if ($page === null)
        $page = getInt('page', 1);
    $page = max(1, $page);
    $perPage = min(max(1, $perPage), MAX_PAGE_SIZE);
    $totalPages = max(1, (int) ceil($total / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;
    return [
        'offset' => $offset,
        'per_page' => $perPage,
        'page' => $page,
        'total' => $total,
        'total_pages' => $totalPages
    ];
}


// ============================================================
// FORMATTING HELPERS
// ============================================================

/**
 * Format a datetime string for display.
 */
function formatDateTime(?string $datetime): string
{
    if (!$datetime)
        return '—';
    return date('M j, Y g:i A', strtotime($datetime));
}

/**
 * Format a date string for display (no time).
 */
function formatDate(?string $datetime): string
{
    if (!$datetime)
        return '—';
    return date('M j, Y', strtotime($datetime));
}

/**
 * Get relative time string (e.g., "2 hours ago").
 */
function timeAgo(?string $datetime): string
{
    if (!$datetime)
        return '—';

    $now = time();
    $time = strtotime($datetime);
    $diff = $now - $time;

    if ($diff < 60)
        return 'just now';
    if ($diff < 3600)
        return floor($diff / 60) . 'm ago';
    if ($diff < 86400)
        return floor($diff / 3600) . 'h ago';
    if ($diff < 604800)
        return floor($diff / 86400) . 'd ago';
    if ($diff < 2592000)
        return floor($diff / 604800) . 'w ago';

    return formatDate($datetime);
}

/**
 * Escape HTML output to prevent XSS.
 */
function e(?string $value): string
{
    if ($value === null)
        return '';
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * Generate a CSRF token and store in session.
 */
function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token from POST data.
 */
function validateCsrf(): bool
{
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

/**
 * Output a hidden CSRF input field.
 */
function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">';
}
