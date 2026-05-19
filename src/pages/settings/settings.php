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


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle settings form submission here
    // For example, you could update the user's username in the database
    $new_username = $_POST['username'] ?? '';

    if ($new_username) {

        $ok = $auth->updateUsername($userId, $new_username);
        if (!$ok) {
            Alert::error("Username already taken.");
            header('Location: /settings');
            exit;
        } else {
            Alert::success("Username updated successfully.");
            header('Location: /settings');
            exit;
        }
    } elseif (isset($_POST['new_password'], $_POST['new_password_confirm'], $_POST['password'])) {
        // Handle password update
        $new_password = $_POST['new_password'];
        $new_password_confirm = $_POST['new_password_confirm'];
        $current_password = $_POST['password'];

        if ($new_password !== $new_password_confirm) {
            Alert::error("New password and confirmation do not match.");
            header('Location: /settings');
            exit;
        } elseif (!$auth->verifyPassword($userId, $current_password)) {
            Alert::error("Incorrect current password.");
            header('Location: /settings');
            exit;
        } else {

            $passwordCheck = $auth->passwordMeetsCriteria($new_password);
            if ($passwordCheck !== true) {
                Alert::error($passwordCheck);
                header('Location: /settings');
                exit;
            }

            $auth->updatePassword($userId, $new_password);

            Alert::success("Password updated successfully.");

            header('Location: /settings');
            exit;
        }
    } elseif (isset($_POST['totp_code']) && $auth->hasTotpEnabled($userId)) {
        // Handle 2FA disable

        $code = trim($_POST['totp_code']);
        $secret = $auth->getTotpSecret($userId);

        $ok = totp_verify($secret, $code) || totp_verify_backup($pdo, $userId, $code);

        if ($ok) {
            // Disable 2FA in the database and delete all backup codes
            $auth->disableTotp($userId);

            Alert::success("2FA disabled successfully.");

            header("Location: /settings");
            exit;
        } else {
            Alert::error("Invalid 2FA code.");
            header("Location: /settings");
            exit;
        }

    } elseif (isset($_POST['password']) && !$auth->hasTotpEnabled($userId)) {
        // Handle 2FA enable

        $password = $_POST['password'];

        if (password_verify($password, $user['password_hash'])) {
            // Generate TOTP secret and save to database
            $data = totp_generate_secret($user['username'], 'Innerspace');

            $_SESSION['pending_totp_user_id'] = $userId;
            $_SESSION['pending_totp_secret'] = $data['secret'];
            $_SESSION['pending_totp_qr'] = $data['qr_base64'];

            header("Location: /settings/totp");
            exit;
        } else {
            Alert::error("Incorrect password.");
            header("Location: /settings");
            exit;
        }
    }
}


?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings | Innerspace</title>
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
                <h1>Settings</h1>
                <p>This is the settings page.</p>

                <form action="settings" method="POST">
                    <h2>Update Username</h2>
                    <label for="username">Username:</label>
                    <input type="text" id="username" name="username">

                    <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
                    <button type="submit">Update Username</button>
                </form>

                <div class="settings-card">
                    <?php if (empty($user['email'])): ?>
                        <p>You don't have an email set up for your account. We recommend adding one to enable password
                            resets and 2FA.</p>
                        <a href="/settings/email">Set up email</a>
                    <?php else: ?>
                        <h2>Update Email</h2>
                        <p>Change your account email with a confirmation link sent to the new address.</p>
                        <p><strong>Current email:</strong> <?= htmlspecialchars($user['email']) ?></p>
                        <a href="/settings/email">Update Email</a>
                    <?php endif; ?>
                </div>

                <form action="settings" method="POST">
                    <h2>Update Password</h2>
                    <label for="password">Actual Password:</label>
                    <input type="password" id="password" name="password">

                    <label for="new_password">New Password:</label>
                    <input type="password" id="new_password" name="new_password">

                    <label for="new_password_confirm">Confirm New Password:</label>
                    <input type="password" id="new_password_confirm" name="new_password_confirm">

                    <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
                    <button type="submit">Update Password</button>
                </form>

                <?php if ($auth->hasTotpEnabled($userId)): ?>
                    <form action="settings" method="POST">
                        <h2>Disable 2FA</h2>
                        <label for="totp_code">Current 2FA Code:</label>
                        <input type="text" id="totp_code" name="totp_code">

                        <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
                        <button type="submit">Disable 2FA</button>
                    </form>
                <?php else: ?>
                    <form action="settings" method="POST">
                        <h2>Enable 2FA</h2>
                        <label for="password">Actual Password:</label>
                        <input type="password" id="password" name="password">

                        <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
                        <button type="submit">Enable 2FA</button>
                    </form>
                <?php endif; ?>

                <a href="/logout">Logout</a>
            </div>

            <?php include $includesDir . '/footer.php'; ?>
        </div>
    </div>
</body>

</html>