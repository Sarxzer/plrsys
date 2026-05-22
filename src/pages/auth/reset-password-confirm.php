<?php
$token = $_GET['token'] ?? null;
$userId = isset($_GET['user']) ? (int) $_GET['user'] : 0;

if (!$token || !$userId) {
    Alert::error('Invalid reset link.');
    header('Location: /login');
    exit;
}

$tokenHash = hash('sha256', $token);

$stmt = $pdo->prepare('
    SELECT * FROM password_reset_tokens 
    WHERE user_id = ? AND token_hash = ? AND used_at IS NULL AND expires_at > NOW()
');
$stmt->execute([$userId, $tokenHash]);
$resetToken = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$resetToken) {
    Alert::error('Reset link is invalid or has expired.');
    header('Location: /login');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['password_confirm'] ?? '';

    if ($password !== $confirm) {
        Alert::error('Passwords do not match.');
        header('Location: /reset-password/confirm?token=' . $token . '&user=' . $userId);
        exit;
    }

    $check = $auth->passwordMeetsCriteria($password);
    if ($check !== true) {
        Alert::error($check);
        header('Location: /reset-password/confirm?token=' . $token . '&user=' . $userId);
        exit;
    }

    $auth->updatePassword($userId, $password);

    // mark token as used
    $pdo->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE id = ?')
        ->execute([$resetToken['id']]);

    Alert::success('Password reset successfully. You can now log in.');
    header('Location: /login');
    exit;
}
?>
<!-- your usual HTML layout -->
<form method="POST" action="/reset-password/confirm?token=<?= htmlspecialchars($token) ?>&user=<?= $userId ?>">
    <label for="password">New password</label>
    <input type="password" id="password" name="password" required>
    <label for="password_confirm">Confirm new password</label>
    <input type="password" id="password_confirm" name="password_confirm" required>
    <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
    <button type="submit">Reset password</button>
</form>