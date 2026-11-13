<?php

$localEnvironment = [];
foreach (['.env', '.env.local'] as $environmentFile) {
    $envFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . $environmentFile;
    if (!is_readable($envFile)) {
        continue;
    }

    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }

        $separator = strpos($line, '=');
        if ($separator === false) {
            continue;
        }

        $key = trim(substr($line, 0, $separator));
        $value = trim(substr($line, $separator + 1));
        if ($value !== '' && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))) {
            $value = substr($value, 1, -1);
        }

        if ($key !== '') {
            $localEnvironment[$key] = $value;
        }
    }
}

foreach ($localEnvironment as $key => $value) {
    if (getenv($key) === false) {
        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
    }
}

function motoworks_env(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    return $value === false ? $default : $value;
}

define('APP_ENV', motoworks_env('APP_ENV', 'production'));
define('APP_DEBUG', filter_var(motoworks_env('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN));
$dbHost = motoworks_env('DB_HOST');
$dbPort = motoworks_env('DB_PORT');
$dbUser = motoworks_env('DB_USER');
$dbPass = motoworks_env('DB_PASS');
$dbName = motoworks_env('DB_NAME');

define('DB_HOST', $dbHost ?: '127.0.0.1');
define('DB_PORT', $dbPort ?: motoworks_env('MYSQL_PORT', '3306'));
define('DB_USER', $dbUser ?: motoworks_env('MYSQL_USER', 'root'));
define('DB_PASS', $dbPass !== null ? $dbPass : motoworks_env('MYSQL_PASSWORD', motoworks_env('MYSQL_ROOT_PASSWORD', '')));
define('DB_NAME', $dbName ?: motoworks_env('MYSQL_DATABASE', ''));

define('DEFAULT_ADMIN_USERNAME', motoworks_env('DEFAULT_ADMIN_USERNAME', 'admin'));
define('DEFAULT_ADMIN_FIRSTNAME', motoworks_env('DEFAULT_ADMIN_FIRSTNAME', 'System'));
define('DEFAULT_ADMIN_LASTNAME', motoworks_env('DEFAULT_ADMIN_LASTNAME', 'Administrator'));
define('DEFAULT_ADMIN_EMAIL', motoworks_env('DEFAULT_ADMIN_EMAIL', ''));
define('DEFAULT_ADMIN_PHONE', motoworks_env('DEFAULT_ADMIN_PHONE', ''));
define('DEFAULT_ADMIN_PASSWORD', motoworks_env('DEFAULT_ADMIN_PASSWORD', ''));
define('STAFF_USERNAME', motoworks_env('STAFF_USERNAME', ''));
define('STAFF_PASSWORD', motoworks_env('STAFF_PASSWORD', ''));

if (!preg_match('/^[A-Za-z0-9_]+$/', DB_NAME)) {
    throw new RuntimeException('DB_NAME must contain only letters, numbers, and underscores.');
}

if (!ctype_digit(DB_PORT) || (int) DB_PORT < 1 || (int) DB_PORT > 65535) {
    throw new RuntimeException('DB_PORT must be a valid TCP port.');
}

ini_set('display_errors', APP_DEBUG ? '1' : '0');
error_reporting(APP_DEBUG ? E_ALL : 0);

try {
    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';charset=utf8mb4';
    if (!defined('MOTOWORKS_SETUP')) {
        $dsn .= ';dbname=' . DB_NAME;
    }

    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    http_response_code(503);
    die('The application is temporarily unavailable. Please try again later.');
}