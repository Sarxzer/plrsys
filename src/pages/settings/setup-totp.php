<?php
/**
 * @var array $parts
 * @var PDO $pdo
 * @var string $includesDir
 * @var string $cssDir
 * @var string $jsDir
 */
require_once __DIR__ . '/../../php/totp.php';

Guards::requireLogin();

$userId = $_SESSION['pending_totp_user_id'] ?? null;
$secret = $_SESSION['pending_totp_secret'] ?? null;
$qr = $_SESSION['pending_totp_qr'] ?? null;

if (!$userId || !$secret) {
    Alert::dev("No pending TOTP setup found. Please start the setup process from your settings.");
    header("Location: /settings");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code'] ?? '');

    if (!totp_verify($secret, $code)) {
        Alert::error("Invalid code. Please try again.");
        header("Location: /settings/setup-totp");
        exit;
    } else {
        // Save secret, enable TOTP
        $auth = new Auth($pdo);
        $auth->enableTotp($userId, $secret);

        // Generate backup codes
        $backupCodes = totp_generate_backup_codes($pdo, $userId);

        // Clean up setup session keys, log user in
        unset($_SESSION['pending_totp_user_id'], $_SESSION['pending_totp_secret'], $_SESSION['pending_totp_qr']);
        $auth->login($userId, false);
        $_SESSION['show_backup_codes'] = $backupCodes;

        header("Location: /settings/backup-codes");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set up 2FA | plrsys</title>
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
                        <div class="setup-step-badge">STEP 2 OF 2</div>
                        <div class="setup-title">Set up 2FA</div>
                        <div class="setup-subtitle">Secure your account with an authenticator app</div>
                    </div>

                    <div class="setup-steps">
                        <!-- Step 1: Scan -->
                        <div class="setup-step">
                            <div class="step-number">// STEP 01</div>
                            <div class="step-title">Scan the QR code</div>
                            <div class="step-body">Open your authenticator app and scan this code.</div>
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
                            <div class="step-number">// OPTIONAL</div>
                            <div class="step-title">Can't scan?</div>
                            <div class="step-body">Enter the secret key manually instead.</div>
                            <button class="secret-toggle" type="button" id="secret-toggle">▼ show secret key</button>
                            <div class="secret-box" id="secret-box">
                                <code><?= htmlspecialchars($secret) ?></code>
                            </div>
                        </div>

                        <!-- Step 2: Verify -->
                        <div class="setup-step">
                            <div class="step-number">// STEP 02</div>
                            <div class="step-title">Verify the code</div>
                            <div class="step-body">Enter the 6-digit code shown in your app to confirm setup.</div>
                            <br>
                            <form action="totp" method="POST">
                                <label class="verify-label" for="code">// code</label>
                                <input type="text" id="code" name="code" class="verify-input" maxlength="6"
                                    placeholder="_ _ _ _ _ _" autocomplete="one-time-code" inputmode="numeric" required>
                                <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
                                <button type="submit" class="verify-submit">Verify &amp; Enable →</button>
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
            this.textContent = box.classList.contains('visible') ? '▲ hide secret key' : '▼ show secret key';
        });
    </script>
</body>

</html>