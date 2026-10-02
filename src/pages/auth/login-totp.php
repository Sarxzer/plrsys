<?php
/**
 * @var PDO $pdo
 * @var string $includesDir
 * @var string $cssDir
 * @var string $jsDir

 */
require_once __DIR__ . '/../../php/totp.php';
if (!isset($_SESSION['pending_2fa_user'])) {
    header("Location: /login");
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = $_POST['code'] ?? '';
    $userId = $_SESSION['pending_2fa_user'];
    $auth = new Auth($pdo);
    $secret = $auth->getTotpSecret($userId);
    if (!$secret || !totp_verify($secret, $code)) {
        Alert::error("Invalid code. Please try again.");
    } else {
        $auth->login($userId, false);
        unset($_SESSION['pending_2fa_user'], $_SESSION['totp_attempts']);
        Alert::success("Login successful! Welcome back.");
        header("Location: /");
        exit;
    }
    $_SESSION['totp_attempts'] = ($_SESSION['totp_attempts'] ?? 0) + 1;
    if ($_SESSION['totp_attempts'] >= 5) {
        unset($_SESSION['pending_2fa_user'], $_SESSION['totp_attempts']);
        Alert::error("Too many failed attempts. Please log in again.");
        header("Location: /login");
        exit;
    }
}
$attemptsLeft = 5 - ($_SESSION['totp_attempts'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Two-Factor Auth | plrsys</title>
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
                <div class="totp-container">
                    <div class="totp-header">
                        <span class="totp-icon">🔐</span>
                        <div class="totp-title">Two-Factor Auth</div>
                        <div class="totp-subtitle">Enter code from your authenticator app</div>
                    </div>
                    <hr class="totp-divider">
                    <form action="totp" method="POST" class="totp-form">
                        <div>
                            <label class="totp-label" for="code">// code</label>
                            <input type="text" id="code" name="code" class="totp-code-input" maxlength="8"
                                placeholder="_ _ _ _ _ _" autocomplete="one-time-code" inputmode="numeric" autofocus
                                required>
                            <div class="totp-hint">or enter a backup code</div>
                        </div>
                        <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
                        <button type="submit" class="totp-submit">Verify →</button>
                    </form>
                    <div class="totp-attempts">
                        <?php for ($i = 0; $i < 5; $i++): ?>
                            <div class="attempt-pip <?= $i >= $attemptsLeft ? 'used' : '' ?>"></div>
                        <?php endfor; ?>
                    </div>
                    <div class="backup-hint"><?= $attemptsLeft ?> attempt<?= $attemptsLeft !== 1 ? 's' : '' ?> remaining
                    </div>
                    <div class="totp-footer">
                        <a href="/login">← back to login</a>
                    </div>
                </div>
            </div>
            <?php include $includesDir . '/footer.php'; ?>
        </div>
    </div>
</body>

</html>