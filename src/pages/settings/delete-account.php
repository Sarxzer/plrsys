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
        Alert::error(__('settings.delete.error.password'));
        header('Location: /settings/delete');
        exit;
    }

    if ($hasTotp && !totp_verify($auth->getTotpSecret($userId), $totpCode) && !totp_verify_backup($pdo, $userId, $totpCode)) {
        Alert::error(__('settings.delete.error.2fa'));
        header('Location: /settings/delete');
        exit;
    }

    // Delete the account
    $auth->deleteUser($userId);

    // Log the user out and redirect to home
    session_regenerate_id(true); // Invalidate the session
    Alert::success(__('settings.delete.success'));
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
    <title><?= __('settings.delete.page_title') ?></title>
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
                        <div class="settings-header-badge"><?= __('settings.delete.badge') ?></div>
                        <div class="settings-header-title"><?= __('settings.delete.title') ?></div>
                        <div class="settings-header-sub"><?= __('settings.delete.subtitle') ?></div>
                    </div>

                    <!-- Summary -->
                    <div class="settings-section danger-zone">
                        <div class="settings-section-header">
                            <span class="settings-section-icon is-danger">!</span>
                            <span class="settings-section-label"><?= __('settings.delete.section') ?></span>
                        </div>
                        <div class="settings-section-body">
                            <div class="settings-info-title"><?= __('settings.delete.review') ?></div>
                            <div class="settings-info-desc">
                                <?= __('settings.delete.review_description') ?>
                            </div>

                            <div class="settings-divider"></div>

                            <div class="settings-info-row">
                                <div class="info-left">
                                    <div class="settings-info-title"><?= __('settings.delete.account') ?></div>
                                    <div class="settings-info-desc">
                                        <span class="settings-current">
                                            <span class="current-label"><?= __('settings.current') ?></span>
                                            <?= htmlspecialchars($user['username']) ?>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="settings-divider"></div>

                            <div class="settings-info-row">
                                <div class="info-left">
                                    <div class="settings-info-title"><?= __('settings.delete.data_removed') ?></div>
                                    <div class="settings-info-desc">
                                        <div><?= __('settings.delete.profile_data') ?></div>
                                        <div><?= __('settings.delete.system_data') ?></div>
                                        <div><?= __('settings.delete.oauth_data') ?></div>
                                        <div><?= __('settings.delete.security_data') ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Verification -->
                    <div class="settings-section">
                        <div class="settings-section-header">
                            <span class="settings-section-icon">#</span>
                            <span class="settings-section-label"><?= __('settings.delete.verify') ?></span>
                        </div>
                        <div class="settings-section-body">
                            <div class="settings-info-desc">
                                <?= __('settings.delete.verify_description') ?>
                            </div>

                            <form method="POST" action="/settings/delete" class="settings-form">
                                <div class="settings-field">
                                    <label class="settings-label" for="delete_password"><?= __('settings.delete.password') ?></label>
                                    <input class="settings-input" type="password" id="delete_password" name="password"
                                        required autocomplete="current-password">
                                </div>

                                <?php if ($hasTotp): ?>
                                    <div class="settings-field">
                                        <label class="settings-label" for="delete_totp"><?= __('settings.delete.2fa') ?></label>
                                        <input class="settings-input settings-input--otp" type="text" id="delete_totp"
                                            name="totp_code" placeholder="_ _ _ _ _ _" maxlength="8" inputmode="numeric"
                                            autocomplete="one-time-code" required>
                                    </div>
                                    <div class="settings-info-desc">
                                        <?= __('settings.delete.2fa_description') ?>
                                    </div>
                                <?php endif; ?>

                                <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
                                <button type="submit" class="settings-submit danger">
                                    <?= __('settings.delete.submit') ?>
                                </button>
                            </form>

                            <div class="settings-divider"></div>

                            <a href="/settings" class="settings-link-btn"><?= __('settings.delete.back') ?></a>
                        </div>
                    </div>
                </div>
            </div>
            <?php include $includesDir . '/footer.php'; ?>
        </div>
    </div>
</body>
</html>