<?php
require_once __DIR__ . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit();
}
requireValidCSRFToken();

session_unset();
session_destroy();
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    $cookieOptions = [
        'expires' => time() - 42000,
        'path' => $params['path'] !== '' ? $params['path'] : '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ];
    if ($params['domain'] !== '') {
        $cookieOptions['domain'] = $params['domain'];
    }
    setcookie(session_name(), '', $cookieOptions);
}
header('Location: index.php');
exit;
?>