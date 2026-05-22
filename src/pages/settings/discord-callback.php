<?php
Guards::requireLogin();

// verify state to prevent CSRF
if (($_GET['state'] ?? '') !== $_SESSION['csrf_token']) {
    Alert::error('Invalid state. Please try again.');
    header('Location: /settings');
    exit;
}

$code = $_GET['code'] ?? null;
if (!$code) {
    Alert::error('Discord authorization failed.');
    header('Location: /settings');
    exit;
}

// exchange code for token
$response = file_get_contents('https://discord.com/api/oauth2/token', false, stream_context_create([
    'http' => [
        'method'  => 'POST',
        'header'  => 'Content-Type: application/x-www-form-urlencoded',
        'content' => http_build_query([
            'client_id'     => $_ENV['DISCORD_CLIENT_ID'],
            'client_secret' => $_ENV['DISCORD_CLIENT_SECRET'],
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'redirect_uri'  => $_ENV['DISCORD_REDIRECT_URI'],
        ]),
    ],
]));

$token_data = json_decode($response, true);
if (empty($token_data['access_token'])) {
    Alert::error('Failed to get Discord token.');
    header('Location: /settings');
    exit;
}

// get user info
$user_response = file_get_contents('https://discord.com/api/users/@me', false, stream_context_create([
    'http' => [
        'header' => 'Authorization: Bearer ' . $token_data['access_token'],
    ],
]));

$discord_user = json_decode($user_response, true);
if (empty($discord_user['id'])) {
    Alert::error('Failed to get Discord user info.');
    header('Location: /settings');
    exit;
}

$stmt = $pdo->prepare('UPDATE users SET discord_id = ? WHERE id = ?');
$stmt->execute([$discord_user['id'], $_SESSION['user_id']]);

Alert::success('Discord account linked as ' . $discord_user['username'] . '!');
header('Location: /settings');
exit;