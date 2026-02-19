<?php
/**
 * Shared Utility Functions — Sawari
 * 
 * General-purpose helper functions used across the application.
 */

require_once __DIR__ . '/../config/constants.php';

/**
 * Sanitize a string for safe HTML output.
 * Prevents XSS by encoding special characters.
 * 
 * @param string|null $input Raw input string
 * @return string Sanitized string
 */
function sanitize(?string $input): string
{
    if ($input === null)
        return '';
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Redirect to a given path and exit.
 * 
 * @param string $path Relative path from BASE_URL or full URL
 */
function redirect(string $path): void
{
    // If path doesn't start with http, prepend BASE_URL
    if (strpos($path, 'http') !== 0) {
        $path = BASE_URL . '/' . ltrim($path, '/');
    }
    header('Location: ' . $path);
    exit;
}

/**
 * Format a datetime string into a human-friendly format.
 * 
 * @param string|null $datetime MySQL datetime string
 * @param string $format PHP date format (default: 'M d, Y h:i A')
 * @return string Formatted date or 'N/A'
 */
function formatDateTime(?string $datetime, string $format = 'M d, Y h:i A'): string
{
    if (empty($datetime))
        return 'N/A';

    try {
        $dt = new DateTime($datetime);
        return $dt->format($format);
    } catch (Exception $e) {
        return 'N/A';
    }
}

/**
 * Convert a datetime into a "time ago" string.
 * e.g., "2 hours ago", "3 days ago", "just now"
 * 
 * @param string|null $datetime MySQL datetime string
 * @return string Human-friendly relative time
 */
function timeAgo(?string $datetime): string
{
    if (empty($datetime))
        return 'N/A';

    try {
        $now = new DateTime();
        $then = new DateTime($datetime);
        $diff = $now->diff($then);

        if ($diff->y > 0)
            return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
        if ($diff->m > 0)
            return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
        if ($diff->d > 0)
            return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
        if ($diff->h > 0)
            return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
        if ($diff->i > 0)
            return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
        return 'just now';
    } catch (Exception $e) {
        return 'N/A';
    }
}

/**
 * Generate a URL-friendly slug from a string.
 * 
 * @param string $string Input string
 * @return string Lowercase, hyphenated slug
 */
function generateSlug(string $string): string
{
    $slug = strtolower(trim($string));
    $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
    $slug = preg_replace('/[\s-]+/', '-', $slug);
    return trim($slug, '-');
}

/**
 * Handle image file upload.
 * 
 * Validates the file, generates a unique name, and moves it to the upload directory.
 * 
 * @param array  $file      The $_FILES['field'] array
 * @param string $subfolder Subfolder within uploads/ (e.g., 'vehicles', 'agents', 'routes')
 * @return string|false     The relative path from project root on success, false on failure
 */
function uploadImage(array $file, string $subfolder = ''): string|false
{
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }

    // Check file size
    if ($file['size'] > MAX_UPLOAD_SIZE) {
        return false;
    }

    // Check MIME type
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    if (!in_array($mimeType, ALLOWED_IMAGE_TYPES)) {
        return false;
    }

    // Check extension
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ALLOWED_IMAGE_EXTENSIONS)) {
        return false;
    }

    // Build target directory
    $targetDir = UPLOAD_DIR;
    if ($subfolder) {
        $targetDir .= '/' . trim($subfolder, '/');
    }

    // Create directory if it doesn't exist
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }

    // Generate unique filename
    $filename = uniqid('img_', true) . '.' . $extension;
    $targetPath = $targetDir . '/' . $filename;

    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        // Return path relative to project root (for storing in DB)
        $relativePath = 'assets/images/uploads';
        if ($subfolder) {
            $relativePath .= '/' . trim($subfolder, '/');
        }
        return $relativePath . '/' . $filename;
    }

    return false;
}

/**
 * Delete an uploaded image file.
 * 
 * @param string|null $relativePath Path relative to project root
 * @return bool True if deleted or didn't exist, false on error
 */
function deleteImage(?string $relativePath): bool
{
    if (empty($relativePath))
        return true;

    $fullPath = BASE_PATH . '/' . ltrim($relativePath, '/');

    if (file_exists($fullPath)) {
        return unlink($fullPath);
    }

    return true; // File doesn't exist, consider it "deleted"
}

/**
 * Send a JSON response and exit.
 * 
 * @param array $data       Response data
 * @param int   $statusCode HTTP status code (default: 200)
 */
function jsonResponse(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Get the client's IP address.
 * Handles proxied requests (X-Forwarded-For).
 * 
 * @return string IP address
 */
function getClientIP(): string
{
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        // Take the first IP if multiple are present
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ips[0]);
    }

    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    }

    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Calculate pagination values.
 * 
 * @param int $totalItems  Total number of items
 * @param int $currentPage Current page number (1-based)
 * @param int $perPage     Items per page (defaults to ITEMS_PER_PAGE)
 * @return array           ['offset', 'totalPages', 'currentPage', 'perPage', 'totalItems', 'hasNext', 'hasPrev']
 */
function paginate(int $totalItems, int $currentPage = 1, int $perPage = ITEMS_PER_PAGE): array
{
    $currentPage = max(1, $currentPage);
    $totalPages = max(1, (int) ceil($totalItems / $perPage));
    $currentPage = min($currentPage, $totalPages);
    $offset = ($currentPage - 1) * $perPage;

    return [
        'offset' => $offset,
        'totalPages' => $totalPages,
        'currentPage' => $currentPage,
        'perPage' => $perPage,
        'totalItems' => $totalItems,
        'hasNext' => $currentPage < $totalPages,
        'hasPrev' => $currentPage > 1,
    ];
}

/**
 * Get a GET/POST parameter with optional default value.
 * 
 * @param string $key     Parameter name
 * @param mixed  $default Default value if not set
 * @return mixed
 */
function getParam(string $key, mixed $default = null): mixed
{
    return $_GET[$key] ?? $_POST[$key] ?? $default;
}

/**
 * Get an integer parameter, ensuring it's a positive int.
 * 
 * @param string $key     Parameter name
 * @param int    $default Default value
 * @return int
 */
function getIntParam(string $key, int $default = 0): int
{
    $value = getParam($key);
    if ($value === null)
        return $default;
    $intVal = (int) $value;
    return $intVal > 0 ? $intVal : $default;
}

/**
 * Generate a CSRF token and store it in the session.
 * 
 * @return string The CSRF token
 */
function generateCSRFToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify a CSRF token against the session.
 * 
 * @param string|null $token The token to verify
 * @return bool
 */
function verifyCSRFToken(?string $token): bool
{
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Render a flash message as HTML if one exists.
 * 
 * @return string HTML for the flash message or empty string
 */
function renderFlashMessage(): string
{
    $flash = getFlashMessage();
    if (!$flash)
        return '';

    $type = sanitize($flash['type']);
    $message = sanitize($flash['message']);

    return '<div class="flash-message flash-' . $type . '" role="alert">
        <span class="flash-text">' . $message . '</span>
        <button class="flash-close" onclick="this.parentElement.remove()" aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>';
}
