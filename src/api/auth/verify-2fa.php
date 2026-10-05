<?php
/**
 * Handles the two-factor authentication verification process.
 * 
 * @var PDO $pdo The PDO instance for database interactions.
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_json(['error' => ['code' => 'method_not_allowed', 'message' => 'Method not allowed']], 405);
}

$body = json_decode(file_get_contents('php://input'), true);
$challenge = $_SESSION['api_2fa'] ?? null;
if (!is_array($body) || !is_string($body['temp_token'] ?? null) || !is_string($body['code'] ?? null)) {
    api_json(['error' => ['code' => 'invalid_request', 'message' => 'Temporary token and code are required']], 422);
}

if (
    !$challenge
    || !hash_equals($challenge['token_hash'], hash('sha256', $body['temp_token']))
    || $challenge['expires_at'] < time()
    || $challenge['attempts'] >= 5
) {
    unset($_SESSION['api_2fa']);
    api_json(['error' => ['code' => 'unauthorized', 'message' => 'Invalid or expired two-factor challenge']], 401);
}

$auth = new Auth($pdo);
$secret = $auth->getTotpSecret((int) $challenge['user_id']);
$valid = $secret && (totp_verify($secret, $body['code']) || totp_verify_backup($pdo, (int) $challenge['user_id'], $body['code']));

if (!$valid) {
    $_SESSION['api_2fa']['attempts']++;
    api_json(['error' => ['code' => 'unauthorized', 'message' => 'Invalid two-factor code']], 401);
}

$userId = (int) $challenge['user_id'];
unset($_SESSION['api_2fa']);
require_once __DIR__ . '/tokens.php';
api_json((new ApiTokens($pdo))->issuePair($userId));
