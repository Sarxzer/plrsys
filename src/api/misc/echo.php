<?php
// POST /v1/echo
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method not allowed']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);

if ($body === null) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid json']);
    exit;
}

echo json_encode(['you_sent' => $body]);