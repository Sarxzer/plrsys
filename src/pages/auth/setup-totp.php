<?php
/**
 * @var array $parts
 * @var PDO $pdo
 * @var string $includesDir
 * @var string $cssDir
 * @var string $jsDir

 */
require_once __DIR__ . '/../../php/totp.php';

$userId = $_SESSION['pending_totp_user_id'] ?? null;
$secret = $_SESSION['pending_totp_secret'] ?? null;
$qr = $_SESSION['pending_totp_qr'] ?? null;

if (!$userId || !$secret) {
    header("Location: /register");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code'] ?? '');

    if (!totp_verify($secret, $code)) {
        Alert::error(__('totp.error.invalid_code'));
        header("Location: /register/totp");
        exit;
    } else {
        $auth = new Auth($pdo);
        $auth->enableTotp($userId, $secret);

        $backupCodes = totp_generate_backup_codes($pdo, $userId);

        unset($_SESSION['pending_totp_user_id'], $_SESSION['pending_totp_secret'], $_SESSION['pending_totp_qr']);
        $auth->login($userId, false);
        $_SESSION['show_backup_codes'] = $backupCodes;

        header("Location: /register/backup-codes");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('totp.setup.page_title') ?></title>
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
                <div class="setup-container">
                    <div class="setup-header">
                        <div class="setup-step-badge"><?= __('totp.setup.step_badge') ?></div>
                        <div class="setup-title"><?= __('totp.setup.title') ?></div>
                        <div class="setup-subtitle"><?= __('totp.setup.subtitle') ?></div>
                    </div>

                    <div class="setup-steps">
                        <!-- Step 1: Scan -->
                        <div class="setup-step">
                            <div class="step-number"><?= __('totp.setup.step_one') ?></div>
                            <div class="step-title"><?= __('totp.setup.scan_qr') ?></div>
                            <div class="step-body"><?= __('totp.setup.scan_qr_description') ?></div>
                            <div class="qr-wrapper">
                                <img src="data:image/svg+xml;base64,<?= $qr ?>" alt="<?= __('totp.setup.qr_alt') ?>">
                            </div>
                            <div class="apps-list">
                                <span class="app-tag">Aegis</span>
                                <span class="app-tag">Google Authenticator</span>
                                <span class="app-tag">Authy</span>
                                <span class="app-tag">2FAS</span>
                            </div>
                        </div>

                        <!-- Step 1b: Manual entry -->
                        <div class="setup-step">
                            <div class="step-number"><?= __('totp.setup.optional') ?></div>
                            <div class="step-title"><?= __('totp.setup.cant_scan') ?></div>
                            <div class="step-body"><?= __('totp.setup.manual_entry') ?></div>
                            <button class="secret-toggle" type="button" id="secret-toggle" data-show-label="<?= __('totp.setup.show_secret') ?>" data-hide-label="<?= __('totp.setup.hide_secret') ?>"><?= __('totp.setup.show_secret') ?></button>
                            <div class="secret-box" id="secret-box">
                                <code><?= htmlspecialchars($secret) ?></code>
                            </div>
                        </div>

                        <!-- Step 2: Verify -->
                        <div class="setup-step">
                            <div class="step-number"><?= __('totp.setup.step_two') ?></div>
                            <div class="step-title"><?= __('totp.setup.verify_title') ?></div>
                            <div class="step-body"><?= __('totp.setup.verify_description') ?></div>
                            <br>
                            <form action="totp" method="POST">
                                <label class="verify-label" for="code"><?= __('totp.code') ?></label>
                                <input type="text" id="code" name="code" class="verify-input" maxlength="6"
                                    placeholder="_ _ _ _ _ _" autocomplete="one-time-code" inputmode="numeric" required>
                                <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
                                <button type="submit" class="verify-submit"><?= __('totp.setup.verify_enable') ?></button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <?php include $includesDir . '/footer.php'; ?>
        </div>
    </div>

    <script>
        document.getElementById('secret-toggle').addEventListener('click', function () {
            const box = document.getElementById('secret-box');
            box.classList.toggle('visible');
            this.textContent = box.classList.contains('visible') ? this.dataset.hideLabel : this.dataset.showLabel;
        });
    </script>
</body>

</html>