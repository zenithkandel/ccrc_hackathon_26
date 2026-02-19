<?php
/**
 * Database Configuration — Sawari
 * 
 * PDO-based MySQL connection with secure defaults.
 * Usage: $pdo = getDBConnection();
 */

// Database credentials
define('DB_HOST', 'localhost');
define('DB_NAME', 'test_sawari_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

/**
 * Get a PDO database connection instance.
 * 
 * Uses a static variable to reuse the same connection within a single request.
 * PDO is configured with:
 *   - ERRMODE_EXCEPTION for error handling
 *   - FETCH_ASSOC as default fetch mode
 *   - Real prepared statements (emulation disabled)
 *
 * @return PDO The database connection
 */
function getDBConnection(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // In production, log the error and show a generic message
            error_log('Database Connection Error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Database connection failed. Please try again later.'
            ]);
            exit;
        }
    }

    return $pdo;
}
