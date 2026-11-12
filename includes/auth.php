<?php
// includes/auth.php

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
} elseif (!headers_sent()) {
    $cookieParams = session_get_cookie_params();
    $cookieOptions = [
        'expires' => 0,
        'path' => $cookieParams['path'] !== '' ? $cookieParams['path'] : '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ];
    if ($cookieParams['domain'] !== '') {
        $cookieOptions['domain'] = $cookieParams['domain'];
    }
    setcookie(session_name(), session_id(), $cookieOptions);
}

/**
 * Check whether a database user has the requested role.
 */
function hasRole(int|string $userId, string $role): bool
{
    global $pdo;

    if (!isset($pdo) || !($pdo instanceof PDO) || !in_array($role, ['admin', 'staff', 'customer'], true)) {
        return false;
    }

    $stmt = $pdo->prepare('SELECT userRole FROM `user` WHERE userId = ? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    return isset($user['userRole']) && $user['userRole'] === $role;
}

function isAdmin(int|string $userId): bool
{
    return hasRole($userId, 'admin');
}

function isStaff(int|string $userId): bool
{
    return hasRole($userId, 'staff');
}

/**
 * Generate a CSRF token for forms.
 */
function generateCSRFToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify a submitted CSRF token.
 */
function verifyCSRFToken(mixed $token): bool
{
    if (!is_string($token) || $token === '' || !isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function requireValidCSRFToken(): void
{
    if (!verifyCSRFToken($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        exit('Invalid security token. Refresh the page and try again.');
    }
}