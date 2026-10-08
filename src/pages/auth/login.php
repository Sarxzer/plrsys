<?php

/**
 * @var PDO $pdo
 * @var string $includesDir
 * @var string $cssDir
 * @var string $jsDir
 */
require_once __DIR__ . '/../../php/totp.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (($_SESSION['login_cooldown'] ?? 0) > time()) {
        $remaining = $_SESSION['login_cooldown'] - time();
        Alert::error(__('login.error.cooldown', $remaining));
        header('Location: /login');
        exit;
    }


    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $userId = $auth->checkCredentials($username, $password);

    if ($userId !== null) {
        // Check if user has TOTP enabled
        if ($auth->hasTotpEnabled($userId)) {
            // Require TOTP verification
            $auth->loginWithTwoFactor($userId);
            header('Location: /login/totp');
            exit;
        }

        // No TOTP, proceed with regular login
        $remember = isset($_POST['remember']);
        $auth->login($userId, $remember);

        unset($_SESSION['login_attempts']);

        Alert::success(__('login.success'));
        header('Location: /');
        exit;
    } else {
        Alert::error(__('login.error.invalid_credentials'));
        $_SESSION['login_cooldown'] = time() + 15; // 15 second cooldown after failed attempt
        $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
        $_SESSION['last_failed_username'] = $username;
    }

    if ($_SESSION['login_attempts'] >= 5) {
        unset($_SESSION['login_attempts']);
        Alert::error(__('login.error.too_many_attempts'));
        header('Location: /login');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $language ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('login.page_title') ?></title>
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
                <div class="login-container">
                    <h1 class="login-title"><?= __('login.title') ?></h1>
                    <div class="login-box">
                        <form action="login" method="post" class="login-form">
                            <div class="auth form">
                                <div>
                                    <label for="username"><?= __('login.username') ?></label>
                                    <input type="text" id="username" name="username"
                                        value="<?= htmlspecialchars($_SESSION['last_failed_username'] ?? '') ?>" required>
                                </div>
                                <div>
                                    <label for="password"><?= __('login.password') ?></label>
                                    <input type="password" id="password" name="password" required><br>
                                </div>
                            </div>
                            <label for="remember" class="checkbox-label">
                                <input type="checkbox" id="remember" name="remember"> <?= __('login.remember_me') ?>
                            </label>

                            <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
                            <input type="submit" class="btn primary" value="<?= __('login.submit') ?>">
                        </form>
                        <p class="auth subtext"><a href="/register"><?= __('login.register_prompt') ?></a></p>
                        <p class="auth subtext"><a href="/reset-password"><?= __('login.forgot_password') ?></a></p>
                    </div>
                </div>
            </div>
            <?php include $includesDir . '/footer.php'; ?>
        </div>
    </div>
</body>

</html>