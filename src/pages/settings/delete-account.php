<?php
/**
 * @var array $parts
 * @var PDO $pdo
 * @var string $includesDir
 * @var string $cssDir
 * @var string $jsDir
 */

include_once __DIR__ . '/../../php/auth.php';
include_once __DIR__ . '/../../php/totp.php';

Guards::requireLogin();

$auth = new Auth($pdo);
$user = $auth->requireCurrentUser();
$userId = (int) $user['id'];
$hasTotp = $auth->hasTotpEnabled($userId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $totpCode = $_POST['totp_code'] ?? '';

    if (!$auth->verifyPassword($userId, $password)) {
        Alert::error('Incorrect password');
        header('Location: /settings/delete');
        exit;
    }

    if ($hasTotp && !totp_verify($auth->getTotpSecret($userId), $totpCode) && !totp_verify_backup($pdo, $userId, $totpCode)) {
        Alert::error('Invalid 2FA code');
        header('Location: /settings/delete');
        exit;
    }

    // Delete the account
    $auth->deleteUser($userId);

    // Log the user out and redirect to home
    session_regenerate_id(true); // Invalidate the session
    Alert::success('Account deleted successfully');
    header('Location: /home');
    exit;


    // need to be redone for account anonymization instead of deletion
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Account | plrsys</title>
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
                <div class="settings-wrapper">
                    <!-- Header -->
                    <div class="settings-header">
                        <div class="settings-header-badge">DANGER ZONE</div>
                        <div class="settings-header-title">Delete Account</div>
                        <div class="settings-header-sub">This action permanently deletes your account and data.</div>
                    </div>

                    <!-- Summary -->
                    <div class="settings-section danger-zone">
                        <div class="settings-section-header">
                            <span class="settings-section-icon is-danger">!</span>
                            <span class="settings-section-label">// Account deletion</span>
                        </div>
                        <div class="settings-section-body">
                            <div class="settings-info-title">Review before you continue</div>
                            <div class="settings-info-desc">
                                Deleting your account removes your profile, login credentials, and all associated
                                system data. This cannot be undone.
                            </div>

                            <div class="settings-divider"></div>

                            <div class="settings-info-row">
                                <div class="info-left">
                                    <div class="settings-info-title">Account</div>
                                    <div class="settings-info-desc">
                                        <span class="settings-current">
                                            <span class="current-label">current</span>
                                            <?= htmlspecialchars($user['username']) ?>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="settings-divider"></div>

                            <div class="settings-info-row">
                                <div class="info-left">
                                    <div class="settings-info-title">Data removed</div>
                                    <div class="settings-info-desc">
                                        <div>- Profile, login credentials, and email</div>
                                        <div>- System, member, and fronting data</div>
                                        <div>- OAuth connections and linked providers</div>
                                        <div>- Backup codes and security settings</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Verification -->
                    <div class="settings-section">
                        <div class="settings-section-header">
                            <span class="settings-section-icon">#</span>
                            <span class="settings-section-label">// Verify identity</span>
                        </div>
                        <div class="settings-section-body">
                            <div class="settings-info-desc">
                                Confirm your password and 2FA code to delete this account.
                            </div>

                            <form method="POST" action="/settings/delete" class="settings-form">
                                <div class="settings-field">
                                    <label class="settings-label" for="delete_password">// password</label>
                                    <input class="settings-input" type="password" id="delete_password" name="password"
                                        required autocomplete="current-password">
                                </div>

                                <?php if ($hasTotp): ?>
                                    <div class="settings-field">
                                        <label class="settings-label" for="delete_totp">// 2fa code or backup code</label>
                                        <input class="settings-input settings-input--otp" type="text" id="delete_totp"
                                            name="totp_code" placeholder="_ _ _ _ _ _" maxlength="8" inputmode="numeric"
                                            autocomplete="one-time-code" required>
                                    </div>
                                    <div class="settings-info-desc">
                                        Use the 6-digit code from your authenticator app or a backup code.
                                    </div>
                                <?php endif; ?>

                                <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
                                <button type="submit" class="settings-submit danger">
                                    Delete account permanently ->
                                </button>
                            </form>

                            <div class="settings-divider"></div>

                            <a href="/settings" class="settings-link-btn">Back to settings</a>
                        </div>
                    </div>
                </div>
            </div>
            <?php include $includesDir . '/footer.php'; ?>
        </div>
    </div>
</body>
</html>