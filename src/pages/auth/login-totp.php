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
        Alert::error(__('totp.error.invalid_code'));
    } else {
        $auth->login($userId, false);
        unset($_SESSION['pending_2fa_user'], $_SESSION['totp_attempts']);
        Alert::success(__('totp.success.login'));
        header("Location: /");
        exit;
    }
    $_SESSION['totp_attempts'] = ($_SESSION['totp_attempts'] ?? 0) + 1;
    if ($_SESSION['totp_attempts'] >= 5) {
        unset($_SESSION['pending_2fa_user'], $_SESSION['totp_attempts']);
        Alert::error(__('totp.error.too_many_attempts'));
        header("Location: /login");
        exit;
    }
}
$attemptsLeft = 5 - ($_SESSION['totp_attempts'] ?? 0);
?>
<!DOCTYPE html>
<html lang="<?= $language ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('totp.page_title') ?></title>
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
                        <div class="totp-title"><?= __('totp.title') ?></div>
                        <div class="totp-subtitle"><?= __('totp.enter_code') ?></div>
                    </div>
                    <hr class="totp-divider">
                    <form action="totp" method="POST" class="totp-form">
                        <div>
                            <label class="totp-label" for="code"><?= __('totp.code') ?></label>
                            <input type="text" id="code" name="code" class="totp-code-input" maxlength="8"
                                placeholder="_ _ _ _ _ _" autocomplete="one-time-code" inputmode="numeric" autofocus
                                required>
                            <div class="totp-hint"><?= __('totp.backup_hint') ?></div>
                        </div>
                        <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
                        <button type="submit" class="totp-submit"><?= __('totp.verify') ?></button>
                    </form>
                    <div class="totp-attempts">
                        <?php for ($i = 0; $i < 5; $i++): ?>
                            <div class="attempt-pip <?= $i >= $attemptsLeft ? 'used' : '' ?>"></div>
                        <?php endfor; ?>
                    </div>
                    <div class="backup-hint"><?= $attemptsLeft === 1 ? __('totp.attempt_remaining') : __('totp.attempts_remaining', $attemptsLeft) ?>
                    </div>
                    <div class="totp-footer">
                        <a href="/login"><?= __('totp.back_to_login') ?></a>
                    </div>
                </div>
            </div>
            <?php include $includesDir . '/footer.php'; ?>
        </div>
    </div>
</body>

</html>