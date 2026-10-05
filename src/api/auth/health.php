<?php
/**
 * Check the health of an API token.
 * 
 * @var PDO $pdo The PDO instance for database interactions.
 */

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    api_json(['error' => ['code' => 'method_not_allowed', 'message' => 'Method not allowed']], 405);
}

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body) || !is_string($body['token'] ?? null)) {
    api_json(['error' => ['code' => 'invalid_request', 'message' => 'Token is required']], 422);
}
if (!isset($body['user_id']) || !is_int($body['user_id'])) {
    api_json(['error' => ['code' => 'invalid_request', 'message' => 'User ID is required']], 422);
}

require_once __DIR__ . '/tokens.php';
$health = (new ApiTokens($pdo))->checkHealth($body['token'], $body['user_id']   );

if ($health === false) {
    api_json(['valid' => false]);
}

api_json([
    'valid' => true,
    'token_type' => $health['token_type'],
    'expires_at' => $health['expires_at'],
    'expires_in' => $health['expires_in'],
]);