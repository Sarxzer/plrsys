<?php
$rootDir = __DIR__ . '/..';

require_once $rootDir . '/vendor/autoload.php';
require_once $rootDir . '/src/php/database.php';
require_once $rootDir . '/src/php/auth.php';
require_once $rootDir . '/src/php/totp.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable($rootDir);
$dotenv->load();

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

header('Content-Type: application/json; charset=utf-8');

function api_json(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

$database = new Database();
$pdo = $database->getPdo();

$apiDir = __DIR__ . '/../src/api/';

$uri = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$parts = explode('/', $uri);

if (!preg_match('/v[x0-9]/i', $parts[1])) {
    http_response_code(404);
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid API version',
    ]);
    exit;
}

match($parts[2]) {
    // Misc
    "ping" => require $apiDir . 'misc/ping.php',
    "echo" => require $apiDir . 'misc/echo.php',

    // Auth
    "auth" => match($parts[3] ?? '') {
        "login" => require $apiDir . 'auth/login.php',
        "register" => require $apiDir . 'auth/register.php',
        "refresh" => require $apiDir . 'auth/refresh.php',
        "health" => require $apiDir . 'auth/health.php',
        "2fa" => match($parts[4] ?? '') {
            "verify" => require $apiDir . 'auth/verify-2fa.php',
            default => require $apiDir . 'misc/404.php',
        },
        default => require $apiDir . 'misc/404.php',
    },
    default => require $apiDir . 'misc/404.php',
};