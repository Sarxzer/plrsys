<?php

/**
 * @var array $parts
 * @var PDO $pdo
 * @var string $includesDir
 * @var string $cssDir
 * @var string $jsDir
 */
require_once __DIR__ . '/../../php/mailer.php';

Guards::requireLogin();

$auth = new Auth($pdo);
$user = $auth->requireCurrentUser();
$userId = (int) $user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newEmail = trim($_POST['email'] ?? '');
    $confirmEmail = trim($_POST['email_confirm'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($newEmail === '' || $confirmEmail === '' || $password === '') {
        Alert::error(__('settings.email.error.required'));
        header('Location: /settings/email');
        exit;
    }

    if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
        Alert::error(__('settings.email.error.invalid'));
        header('Location: /settings/email');
        exit;
    }

    if ($newEmail !== $confirmEmail) {
        Alert::error(__('settings.email.error.mismatch'));
        header('Location: /settings/email');
        exit;
    }

    if (!$auth->verifyPassword($userId, $password)) {
        Alert::error(__('settings.error.password'));
        header('Location: /settings/email');
        exit;
    }

    if (strcasecmp($newEmail, $user['email']) === 0) {
        Alert::info(__('settings.email.info.current'));
        header('Location: /settings');
        exit;
    }

    $token = $auth->requestEmailChange($userId, $newEmail);
    if ($token === null) {
        Alert::error(__('settings.email.error.in_use'));
        header('Location: /settings/email');
        exit;
    }

    $mailer = new Mailer();
    if (!$mailer->sendEmailChangeConfirmation($newEmail, $userId, $token)) {
        $auth->clearPendingEmailChange($userId);
        Alert::error(__('settings.email.error.send_failed'));
        header('Location: /settings/email');
        exit;
    }

    Alert::info(__('settings.email.info.sent', $newEmail));
    header('Location: /settings');
    exit;
}

$pendingEmail = $user['pending_email'] ?? null;
$pendingExpiresAt = $user['pending_email_expires_at'] ?? null;

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('settings.email.page_title') ?></title>
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
                        <div class="setup-step-badge"><?= __('settings.email.confirmation_badge') ?></div>
                        <div class="setup-title"><?= __('settings.email.update_title') ?></div>
                        <div class="setup-subtitle"><?= __('settings.email.update_subtitle') ?></div>
                    </div>

                    <div class="setup-steps">
                        <div class="setup-step">
                            <div class="step-number"><?= __('settings.email.current_step') ?></div>
                            <?php if (empty($user['email'])): ?>
                                <div class="step-title"><?= __('settings.email.none') ?></div>
                                <div class="step-body"><?= __('settings.email.none_description') ?></div>
                            <?php else: ?>
                                <div class="step-title"><?= __('settings.email.account') ?></div>
                                <div class="step-body"><?= htmlspecialchars($user['email']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($pendingEmail)): ?>
                                <div class="step-body"><?= __('settings.email.pending') ?> <strong><?= htmlspecialchars($pendingEmail) ?></strong><?php if (!empty($pendingExpiresAt)): ?> <?= __('settings.email.until') ?> <?= htmlspecialchars($pendingExpiresAt) ?><?php endif; ?>.</div>
                            <?php endif; ?>
                        </div>

                        <div class="setup-step">
                            <div class="step-number"><?= __('settings.email.step_one') ?></div>
                            <div class="step-title"><?= __('settings.email.enter_new') ?></div>
                            <div class="step-body"><?= __('settings.email.enter_description') ?></div>
                            <form method="POST" action="/settings/email">
                                <div class="form-group">
                                    <label for="email"><?= __('settings.email.new_address') ?></label>
                                    <input type="email" id="email" name="email" required placeholder="<?= __('settings.email.address_placeholder') ?>">
                                </div>
                                <div class="form-group">
                                    <label for="email_confirm"><?= __('settings.email.confirm_address') ?></label>
                                    <input type="email" id="email_confirm" name="email_confirm" required placeholder="<?= __('settings.email.confirm_placeholder') ?>">
                                </div>
                                <div class="form-group">
                                    <label for="password"><?= __('settings.email.password') ?></label>
                                    <input type="password" id="password" name="password" required placeholder="<?= __('settings.email.password_placeholder') ?>">
                                </div>
                                <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
                                <button type="submit"><?= __('settings.email.send') ?></button>
                            </form>
                        </div>

                        <div class="setup-step">
                            <div class="step-number"><?= __('settings.email.step_two') ?></div>
                            <div class="step-title"><?= __('settings.email.confirm_from_email') ?></div>
                            <div class="step-body"><?= __('settings.email.confirm_description') ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <?php include $includesDir . '/footer.php'; ?>
        </div>
    </div>
</body>

</html>