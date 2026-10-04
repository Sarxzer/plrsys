<?php
header('Content-Type: application/json');

$apiDir = __DIR__ . '/../src/api/';

$uri = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$parts = explode('/', $uri);

if (!preg_match('/v[x0-9]/i', $parts[1])) {
    http_response_code(404);
    echo json_encode([
        "status" => "error",
        "message" => "Invalid API version : " . $parts[1]
    ]);
    exit;
}

match($parts[2]) {
    'users' => require $apiDir . 'users.php',
    'posts' => require $apiDir . 'posts.php',
    default => require $apiDir . '404.php',
};