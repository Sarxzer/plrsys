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
        Alert::error("Please wait $remaining seconds before trying to log in again.");
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

        Alert::success("Login successful! Welcome back.");
        header('Location: /');
        exit;
    } else {
        Alert::error("Invalid username or password.");
        $_SESSION['login_cooldown'] = time() + 15; // 15 second cooldown after failed attempt
        $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
        $_SESSION['last_failed_username'] = $username;
        header('Location: /login');
        exit;
    }

    if ($_SESSION['login_attempts'] >= 5) {
        unset($_SESSION['login_attempts']);
        Alert::error("Too many failed login attempts. Please try again later.");
        header('Location: /login');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Innerspace</title>
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
                    <h1 class="login-title">Login</h1>

                    <form action="login" method="post" class="login-form">
                        <label for="username">Username:</label><br>
                        <input type="text" id="username" name="username"
                            value="<?= htmlspecialchars($_SESSION['last_failed_username'] ?? '') ?>" required><br><br>

                        <label for="password">Password:</label><br>
                        <input type="password" id="password" name="password" required><br><br>

                        <label for="remember" class="checkbox-label">
                            <input type="checkbox" id="remember" name="remember"> Remember me
                        </label>
                        <br><br>

                        <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
                        <input type="submit" value="Login">
                    </form>
                    <p class="login-subtext"><a href="/register">Don't have an account? Register here.</a></p>
                    <p class="login-subtext"><a href="/reset-password">Forgot your password?</a></p>
                </div>
            </div>

            <?php include $includesDir . '/footer.php'; ?>
        </div>
    </div>
</body>

</html>