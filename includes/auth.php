<?php
/**
 * Authentication Helpers — Sawari
 * 
 * Functions for password hashing, login verification, and user registration.
 * Requires: config/database.php, config/session.php
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/session.php';

/**
 * Hash a password using bcrypt.
 * 
 * @param string $password Plain-text password
 * @return string Bcrypt hash
 */
function hashPassword(string $password): string
{
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Verify a password against a bcrypt hash.
 * 
 * @param string $password Plain-text password
 * @param string $hash     Stored bcrypt hash
 * @return bool
 */
function verifyPassword(string $password, string $hash): bool
{
    return password_verify($password, $hash);
}

/**
 * Attempt to log in an agent.
 * 
 * @param string $email    Agent's email
 * @param string $password Plain-text password
 * @return array ['success' => bool, 'message' => string, 'agent' => array|null]
 */
function loginAgent(string $email, string $password): array
{
    $pdo = getDBConnection();

    $stmt = $pdo->prepare('SELECT agent_id, name, email, password_hash FROM agents WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => trim($email)]);
    $agent = $stmt->fetch();

    if (!$agent) {
        return ['success' => false, 'message' => 'No agent account found with this email.', 'agent' => null];
    }

    if (!verifyPassword($password, $agent['password_hash'])) {
        return ['success' => false, 'message' => 'Incorrect password.', 'agent' => null];
    }

    // Set session
    setLoginSession($agent['agent_id'], 'agent', $agent['name'], $agent['email']);

    // Update last login
    updateLastLogin($agent['agent_id'], 'agent');

    return ['success' => true, 'message' => 'Login successful.', 'agent' => $agent];
}

/**
 * Attempt to log in an admin.
 * 
 * @param string $email    Admin's email
 * @param string $password Plain-text password
 * @return array ['success' => bool, 'message' => string, 'admin' => array|null]
 */
function loginAdmin(string $email, string $password): array
{
    $pdo = getDBConnection();

    $stmt = $pdo->prepare('SELECT admin_id, name, email, password_hash FROM admins WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => trim($email)]);
    $admin = $stmt->fetch();

    if (!$admin) {
        return ['success' => false, 'message' => 'No admin account found with this email.', 'admin' => null];
    }

    if (!verifyPassword($password, $admin['password_hash'])) {
        return ['success' => false, 'message' => 'Incorrect password.', 'admin' => null];
    }

    // Set session
    setLoginSession($admin['admin_id'], 'admin', $admin['name'], $admin['email']);

    // Update last login
    updateLastLogin($admin['admin_id'], 'admin');

    return ['success' => true, 'message' => 'Login successful.', 'admin' => $admin];
}

/**
 * Register a new agent account.
 * 
 * @param array $data ['name', 'email', 'phone_number', 'password', 'image_path' (optional)]
 * @return array ['success' => bool, 'message' => string, 'agent_id' => int|null]
 */
function registerAgent(array $data): array
{
    $pdo = getDBConnection();

    // Check if email already exists
    $stmt = $pdo->prepare('SELECT agent_id FROM agents WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => trim($data['email'])]);
    if ($stmt->fetch()) {
        return ['success' => false, 'message' => 'An account with this email already exists.', 'agent_id' => null];
    }

    // Insert new agent
    $stmt = $pdo->prepare('
        INSERT INTO agents (name, email, phone_number, password_hash, image_path, contributions_summary, joined_at)
        VALUES (:name, :email, :phone_number, :password_hash, :image_path, :contributions_summary, NOW())
    ');

    $stmt->execute([
        'name' => trim($data['name']),
        'email' => trim($data['email']),
        'phone_number' => trim($data['phone_number'] ?? ''),
        'password_hash' => hashPassword($data['password']),
        'image_path' => $data['image_path'] ?? null,
        'contributions_summary' => json_encode(['vehicle' => 0, 'location' => 0, 'route' => 0]),
    ]);

    $agentId = (int) $pdo->lastInsertId();

    return ['success' => true, 'message' => 'Registration successful.', 'agent_id' => $agentId];
}

/**
 * Update the last_login timestamp for a user.
 * 
 * @param int    $userId User's ID
 * @param string $role   'admin' or 'agent'
 */
function updateLastLogin(int $userId, string $role): void
{
    $pdo = getDBConnection();

    if ($role === 'admin') {
        $stmt = $pdo->prepare('UPDATE admins SET last_login = NOW() WHERE admin_id = :id');
    } else {
        $stmt = $pdo->prepare('UPDATE agents SET last_login = NOW() WHERE agent_id = :id');
    }

    $stmt->execute(['id' => $userId]);
}
