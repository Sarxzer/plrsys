<?php
Guards::requireLogin();

$params = http_build_query([
    'client_id'     => $_ENV['DISCORD_CLIENT_ID'],
    'redirect_uri'  => $_ENV['DISCORD_REDIRECT_URI'],
    'response_type' => 'code',
    'scope'         => 'identify',
    'state'         => $_SESSION['csrf_token'], // reuse your existing CSRF token
]);

header('Location: https://discord.com/oauth2/authorize?' . $params);
exit;