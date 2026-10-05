<?php
/**
 * Handles the user login process.
 * 
 * @var PDO $pdo The PDO instance for database interactions.
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_json(['error' => ['code' => 'method_not_allowed', 'message' => 'Method not allowed']], 405);
}

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body) || !is_string($body['email'] ?? null) || !is_string($body['password'] ?? null)) {
    api_json(['error' => ['code' => 'invalid_request', 'message' => 'Email and password are required']], 422);
}

$stmt = $pdo->prepare('SELECT id, password_hash, totp_enabled FROM users WHERE email = ? LIMIT 1');
$stmt->execute([trim($body['email'])]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || !password_verify($body['password'], $user['password_hash'])) {
    api_json(['error' => ['code' => 'unauthorized', 'message' => 'Invalid email or password']], 401);
}

if ((bool) $user['totp_enabled']) {
    $tempToken = 'plr_2fa_' . bin2hex(random_bytes(32));
    $_SESSION['api_2fa'] = [
        'user_id' => (int) $user['id'],
        'token_hash' => hash('sha256', $tempToken),
        'expires_at' => time() + 300,
        'attempts' => 0,
    ];

    api_json(['two_factor_required' => true, 'temp_token' => $tempToken]);
}

require_once __DIR__ . '/tokens.php';
api_json((new ApiTokens($pdo))->issuePair((int) $user['id']));
