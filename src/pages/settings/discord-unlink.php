<?php
Guards::requireLogin();

$pdo->prepare('DELETE FROM oauth_connections WHERE user_id = ? AND provider = "discord"')
    ->execute([$_SESSION['user_id']]);

$pdo->prepare('UPDATE users SET discord_id = NULL WHERE id = ?')
    ->execute([$_SESSION['user_id']]);

Alert::success(__('settings.discord.success.unlinked'));
header('Location: /settings');
exit;