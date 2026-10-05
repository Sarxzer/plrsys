<?php
// GET /v1/ping
header('Content-Type: application/json');
echo json_encode([
    'status' => 'ok',
    'message' => 'pong',
    'time' => date('c'),
    'php' => PHP_VERSION,
]);