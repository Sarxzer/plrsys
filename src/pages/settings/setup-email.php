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
        Alert::error('All fields are required.');
        header('Location: /settings/email');
        exit;
    }

    if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
        Alert::error('Please enter a valid email address.');
        header('Location: /settings/email');
        exit;
    }

    if ($newEmail !== $confirmEmail) {
        Alert::error('The email addresses do not match.');
        header('Location: /settings/email');
        exit;
    }

    if (!$auth->verifyPassword($userId, $password)) {
        Alert::error('Incorrect password.');
        header('Location: /settings/email');
        exit;
    }

    if (strcasecmp($newEmail, $user['email']) === 0) {
        Alert::info('That is already your current email address.');
        header('Location: /settings');
        exit;
    }

    $token = $auth->requestEmailChange($userId, $newEmail);
    if ($token === null) {
        Alert::error('That email address is already in use.');
        header('Location: /settings/email');
        exit;
    }

    $mailer = new Mailer();
    if (!$mailer->sendEmailChangeConfirmation($newEmail, $userId, $token)) {
        $auth->clearPendingEmailChange($userId);
        Alert::error('We could not send the confirmation email. Please try again later.');
        header('Location: /settings/email');
        exit;
    }

    Alert::info("A confirmation link has been sent to $newEmail. Open it to finish updating your email.");
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
    <title>Set up Email | plrsys</title>
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
                        <div class="setup-step-badge">EMAIL CONFIRMATION</div>
                        <div class="setup-title">Update your email</div>
                        <div class="setup-subtitle">We will send a one-time confirmation link before making the change</div>
                    </div>

                    <div class="setup-steps">
                        <div class="setup-step">
                            <div class="step-number">// CURRENT</div>
                            <?php if (empty($user['email'])): ?>
                                <div class="step-title">No email set</div>
                                <div class="step-body">You currently don't have an email address associated with your account.</div>
                            <?php else: ?>
                                <div class="step-title">Your account email</div>
                                <div class="step-body"><?= htmlspecialchars($user['email']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($pendingEmail)): ?>
                                <div class="step-body">Pending confirmation: <strong><?= htmlspecialchars($pendingEmail) ?></strong><?php if (!empty($pendingExpiresAt)): ?> until <?= htmlspecialchars($pendingExpiresAt) ?><?php endif; ?>.</div>
                            <?php endif; ?>
                        </div>

                        <div class="setup-step">
                            <div class="step-number">// STEP 01</div>
                            <div class="step-title">Enter the new email</div>
                            <div class="step-body">Type the address you want to use, then confirm it to avoid typos.</div>
                            <form method="POST" action="/settings/email">
                                <div class="form-group">
                                    <label for="email">New email address</label>
                                    <input type="email" id="email" name="email" required placeholder="your.email@example.com">
                                </div>
                                <div class="form-group">
                                    <label for="email_confirm">Confirm new email address</label>
                                    <input type="email" id="email_confirm" name="email_confirm" required placeholder="repeat.your.email@example.com">
                                </div>
                                <div class="form-group">
                                    <label for="password">Current password</label>
                                    <input type="password" id="password" name="password" required placeholder="Enter your password">
                                </div>
                                <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
                                <button type="submit">Send confirmation link</button>
                            </form>
                        </div>

                        <div class="setup-step">
                            <div class="step-number">// STEP 02</div>
                            <div class="step-title">Confirm from email</div>
                            <div class="step-body">Open the message we send and click the confirmation link to apply the change.</div>
                        </div>
                    </div>
                </div>
            </div>
            <?php include $includesDir . '/footer.php'; ?>
        </div>
    </div>
</body>

</html>