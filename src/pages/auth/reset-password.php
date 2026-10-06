<?php
/**
 * @var array $parts
 * @var PDO $pdo
 * @var string $includesDir
 * @var string $cssDir
 * @var string $jsDir
 */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        Alert::error(__('reset.error.email'));
        header('Location: /reset-password');
        exit;
    }

    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // always show success even if email not found - prevents user enumeration
    if ($user) {
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);

        $pdo->prepare('
            INSERT INTO password_reset_tokens (user_id, token_hash, expires_at)
            VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))
        ')->execute([$user['id'], $tokenHash]);

        $mailer = new Mailer();
        $mailer->sendPasswordResetEmail($email, $token);
    }

    Alert::info(__('reset.info.sent'));
    header('Location: /reset-password');
    exit;
}
?>
<!-- <form method="POST" action="/reset-password">
    <label for="email"><?= __('reset.email') ?></label>
    <input type="email" id="email" name="email" required>
    <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
    <button type="submit"><?= __('reset.submit') ?></button>
</form>
<p><a href="/login"><?= __('reset.back_to_login') ?></a></p> -->

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('reset.page_title') ?></title>
    <link rel="stylesheet" href="<?= $cssDir ?>">
    <link rel="shortcut icon" href="/assets/images/favicon.png" type="image/png">
    <script src="<?= $jsDir ?>" defer></script>
</head>
<body>
    <div class="page">
        <div class="pixel-scanlines"></div>
        <div class="content">
            <?php include $includesDir . '/navbar.php'; ?>
            <div class="alerts-wrapper">
                <?php include $includesDir . '/alerts.php'; ?>
            </div>
            <div class="main">
                <h1><?= __('reset.title') ?></h1>
                <form method="POST" action="/reset-password" class="settings-card">
                    <label for="email"><?= __('reset.email') ?></label>
                    <input type="email" id="email" name="email" required>
                    <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
                    <button type="submit"><?= __('reset.submit') ?></button>
                </form>
                <p><a href="/login"><?= __('reset.back_to_login') ?></a></p>
            </div>
            <?php include $includesDir . '/footer.php'; ?>
        </div>
    </div>
</body>
</html>