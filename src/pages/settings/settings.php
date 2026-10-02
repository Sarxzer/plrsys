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

$discord = null;
if ($user['discord_id']) {
    $stmt = $pdo->prepare('SELECT provider_username, provider_avatar FROM oauth_connections WHERE user_id = ? AND provider = "discord"');
    $stmt->execute([$userId]);
    $discord = $stmt->fetch(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_username = $_POST['username'] ?? '';

    if ($new_username) {
        $ok = $auth->updateUsername($userId, $new_username);
        if (!$ok) {
            Alert::error("Username already taken.");
        } else {
            Alert::success("Username updated successfully.");
        }
        header('Location: /settings');
        exit;
    } elseif (isset($_POST['new_password'], $_POST['new_password_confirm'], $_POST['password'])) {
        $new_password = $_POST['new_password'];
        $new_password_confirm = $_POST['new_password_confirm'];
        $current_password = $_POST['password'];

        if ($new_password !== $new_password_confirm) {
            Alert::error("New password and confirmation do not match.");
        } elseif (!$auth->verifyPassword($userId, $current_password)) {
            Alert::error("Incorrect current password.");
        } else {
            $passwordCheck = $auth->passwordMeetsCriteria($new_password);
            if ($passwordCheck !== true) {
                Alert::error($passwordCheck);
            } else {
                $auth->updatePassword($userId, $new_password);
                Alert::success("Password updated successfully.");
            }
        }
        header('Location: /settings');
        exit;
    } elseif (isset($_POST['totp_code']) && $auth->hasTotpEnabled($userId)) {
        $code = trim($_POST['totp_code']);
        $secret = $auth->getTotpSecret($userId);
        $ok = totp_verify($secret, $code) || totp_verify_backup($pdo, $userId, $code);
        if ($ok) {
            $auth->disableTotp($userId);
            Alert::success("2FA disabled successfully.");
        } else {
            Alert::error("Invalid 2FA code.");
        }
        header('Location: /settings');
        exit;
    } elseif (isset($_POST['password']) && !$auth->hasTotpEnabled($userId)) {
        $password = $_POST['password'];
        if (password_verify($password, $user['password_hash'])) {
            $data = totp_generate_secret($user['username'], 'plrsys');
            $_SESSION['pending_totp_user_id'] = $userId;
            $_SESSION['pending_totp_secret'] = $data['secret'];
            $_SESSION['pending_totp_qr'] = $data['qr_base64'];
            header("Location: /settings/totp");
            exit;
        } else {
            Alert::error("Incorrect password.");
            header('Location: /settings');
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
    <title>Settings | plrsys</title>
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
                        <div class="settings-header-badge">ACCOUNT</div>
                        <div class="settings-header-title">Settings</div>
                        <div class="settings-header-sub">Manage your account, security, and connections.</div>
                    </div>

                    <!-- ---- Profile ---- -->
                    <div class="settings-section">
                        <div class="settings-section-header">
                            <span class="settings-section-icon">◈</span>
                            <span class="settings-section-label">// Profile</span>
                        </div>
                        <div class="settings-section-body">

                            <!-- Username -->
                            <div class="settings-info-row">
                                <div class="info-left">
                                    <div class="settings-info-title">Username</div>
                                    <div class="settings-info-desc">
                                        <span class="settings-current">
                                            <span class="current-label">current</span>
                                            <?= htmlspecialchars($user['username']) ?>
                                        </span>
                                    </div>
                                </div>
                                <button class="settings-toggle-btn" data-toggle="username-form">▼ change</button>
                            </div>

                            <div class="settings-collapsible" id="username-form">
                                <form method="POST" action="/settings" class="settings-form">
                                    <div class="settings-field">
                                        <label class="settings-label" for="username">// new username</label>
                                        <input class="settings-input" type="text" id="username" name="username"
                                            placeholder="<?= htmlspecialchars($user['username']) ?>"
                                            autocomplete="username">
                                    </div>
                                    <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
                                    <button type="submit" class="settings-submit">Update Username →</button>
                                </form>
                            </div>

                            <div class="settings-divider"></div>

                            <!-- Email -->
                            <div class="settings-info-row">
                                <div class="info-left">
                                    <div class="settings-info-title">Email</div>
                                    <div class="settings-info-desc">
                                        <?php if (empty($user['email'])): ?>
                                            <span class="settings-status disabled">
                                                <span class="status-dot"></span>
                                                not set - needed for account recovery
                                            </span>
                                        <?php else: ?>
                                            <span class="settings-current">
                                                <span class="current-label">current</span>
                                                <?= htmlspecialchars($user['email']) ?>
                                            </span>
                                            <?php if (!empty($user['pending_email'])): ?>
                                                <br><span class="settings-pending">
                                                    ⏳ pending: <?= htmlspecialchars($user['pending_email']) ?>
                                                </span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <a href="/settings/email" class="settings-link-btn">
                                    <?= empty($user['email']) ? '+ set up email' : '▼ change' ?>
                                </a>
                            </div>

                        </div>
                    </div>

                    <!-- ---- Security ---- -->
                    <div class="settings-section">
                        <div class="settings-section-header">
                            <span class="settings-section-icon">⊕</span>
                            <span class="settings-section-label">// Security</span>
                        </div>
                        <div class="settings-section-body">

                            <!-- Password -->
                            <div class="settings-info-row">
                                <div class="info-left">
                                    <div class="settings-info-title">Password</div>
                                    <div class="settings-info-desc">Change your login password.</div>
                                </div>
                                <button class="settings-toggle-btn" data-toggle="password-form">▼ change</button>
                            </div>

                            <div class="settings-collapsible" id="password-form">
                                <form method="POST" action="/settings" class="settings-form">
                                    <div class="settings-field">
                                        <label class="settings-label" for="password">// current password</label>
                                        <input class="settings-input" type="password" id="password" name="password"
                                            required autocomplete="current-password">
                                    </div>
                                    <div class="settings-field">
                                        <label class="settings-label" for="new_password">// new password</label>
                                        <input class="settings-input" type="password" id="new_password"
                                            name="new_password" required autocomplete="new-password">
                                    </div>
                                    <div class="settings-field">
                                        <label class="settings-label" for="new_password_confirm">// confirm new
                                            password</label>
                                        <input class="settings-input" type="password" id="new_password_confirm"
                                            name="new_password_confirm" required autocomplete="new-password">
                                    </div>
                                    <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
                                    <button type="submit" class="settings-submit">Update Password →</button>
                                </form>
                                <aside class="password-requirements settings-password-requirements"
                                    aria-labelledby="settings-password-requirements-title">
                                    <h2 class="title" id="settings-password-requirements-title">Password Requirements</h2>

                                    <div class="group">
                                        <div class="label">option A - passphrase</div>
                                        <ul class="list">
                                            <li class="item" data-rule="passphrase">
                                                <span class="pip"></span>
                                                4+ words separated by spaces or hyphens, 20+ chars total
                                            </li>
                                        </ul>
                                    </div>
                                    <div class="or">- or -</div>

                                    <div class="group">
                                        <div class="label">option B - classic password</div>
                                        <ul class="list">
                                            <li class="item" data-rule="length">
                                                <span class="pip"></span>
                                                At least 8 characters
                                            </li>
                                            <li class="item" data-rule="uppercase">
                                                <span class="pip"></span>
                                                One uppercase letter
                                            </li>
                                            <li class="item" data-rule="lowercase">
                                                <span class="pip"></span>
                                                One lowercase letter
                                            </li>
                                            <li class="item" data-rule="number">
                                                <span class="pip"></span>
                                                One number
                                            </li>
                                            <li class="item" data-rule="special">
                                                <span class="pip"></span>
                                                One special character (e.g. !@#$%^&*)
                                            </li>
                                        </ul>
                                    </div>
                                </aside>
                            </div>

                            <div class="settings-divider"></div>

                            <!-- 2FA -->
                            <div class="settings-info-row">
                                <div class="info-left">
                                    <div class="settings-info-title">Two-Factor Authentication</div>
                                    <div class="settings-info-desc">
                                        <?php if ($auth->hasTotpEnabled($userId)): ?>
                                            <span class="settings-status enabled">
                                                <span class="status-dot"></span>
                                                enabled - authenticator app
                                            </span>
                                        <?php else: ?>
                                            <span class="settings-status disabled">
                                                <span class="status-dot"></span>
                                                disabled - your account is less secure
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <button class="settings-toggle-btn" data-toggle="totp-form">
                                    <?= $auth->hasTotpEnabled($userId) ? '▼ disable' : '▼ enable' ?>
                                </button>
                            </div>

                            <div class="settings-collapsible" id="totp-form">
                                <?php if ($auth->hasTotpEnabled($userId)): ?>
                                    <form method="POST" action="/settings" class="settings-form">
                                        <div class="settings-field">
                                            <label class="settings-label" for="totp_code">// confirm with your 2FA code or
                                                backup code</label>
                                            <input class="settings-input settings-input--otp" type="text" id="totp_code"
                                                name="totp_code" placeholder="_ _ _ _ _ _" maxlength="8"
                                                inputmode="numeric" autocomplete="one-time-code">
                                        </div>
                                        <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
                                        <button type="submit" class="settings-submit danger">Disable 2FA →</button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST" action="/settings" class="settings-form">
                                        <div class="settings-field">
                                            <label class="settings-label" for="totp_password">// confirm with your password
                                                to continue</label>
                                            <input class="settings-input" type="password" id="totp_password" name="password"
                                                required autocomplete="current-password">
                                        </div>
                                        <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
                                        <button type="submit" class="settings-submit">Enable 2FA →</button>
                                    </form>
                                <?php endif; ?>
                            </div>

                            <?php if ($auth->hasTotpEnabled($userId)): ?>
                                <div class="settings-divider"></div>
                                <div class="settings-info-row">
                                    <div class="info-left">
                                        <div class="settings-info-title">Backup Codes</div>
                                        <div class="settings-info-desc">Regenerate your one-time recovery codes.</div>
                                    </div>
                                    <a href="/settings/backup-codes" class="settings-link-btn">↻ regenerate</a>
                                </div>
                            <?php endif; ?>

                        </div>
                    </div>

                    <!-- ---- Connections ---- -->
                    <div class="settings-section">
                        <div class="settings-section-header">
                            <span class="settings-section-icon">⇄</span>
                            <span class="settings-section-label">// Connections</span>
                        </div>
                        <div class="settings-section-body">

                            <!-- Discord -->
                            <div class="settings-info-row">
                                <div class="info-left">
                                    <div class="settings-info-title">Discord</div>
                                    <div class="settings-info-desc">
                                        <?php if ($user['discord_id'] && $discord): ?>
                                            <span class="settings-status linked">
                                                <span class="status-dot"></span>
                                                linked as <?= htmlspecialchars($discord['provider_username']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="settings-status disabled">
                                                <span class="status-dot"></span>
                                                not linked
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php if ($user['discord_id']): ?>
                                    <form method="POST" action="/settings/discord/unlink" class="settings-form-inline">
                                        <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
                                        <button type="submit" class="settings-link-btn is-danger">
                                            ✕ unlink
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <a href="/settings/discord/link" class="settings-link-btn">+ link Discord</a>
                                <?php endif; ?>
                            </div>

                        </div>
                    </div>

                    <!-- ---- Session ---- -->
                    <div class="settings-section">
                        <div class="settings-section-header">
                            <span class="settings-section-icon">◉</span>
                            <span class="settings-section-label">// Session</span>
                        </div>
                        <div class="settings-section-body">
                            <div class="settings-logout-row">
                                <div class="info-left">
                                    <div class="settings-info-title">Log out</div>
                                    <div class="settings-info-desc">End your current session on this device.</div>
                                </div>
                                <form method="POST" action="/logout" class="settings-form-inline">
                                    <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
                                    <button type="submit" class="settings-submit danger">Log out →</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- ---- Danger zone ---- -->
                    <div class="settings-section danger-zone">
                        <div class="settings-section-header">
                            <span class="settings-section-icon is-danger">⚠</span>
                            <span class="settings-section-label">// Danger Zone</span>
                        </div>
                        <div class="settings-section-body">
                            <div class="settings-info-row">
                                <div class="info-left">
                                    <div class="settings-info-title">Delete Account</div>
                                    <div class="settings-info-desc">Permanently delete your account and all associated
                                        data. This cannot be undone.</div>
                                </div>
                                <a href="/settings/delete" class="settings-link-btn danger">Delete Account</a>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <?php include $includesDir . '/footer.php'; ?>
        </div>
    </div>

    <script>
        // Collapsible toggle buttons
        document.querySelectorAll('[data-toggle]').forEach(btn => {
            btn.addEventListener('click', () => {
                const targetId = btn.dataset.toggle;
                const target = document.getElementById(targetId);
                if (!target) return;

                const isOpen = target.classList.toggle('is-open');
                btn.textContent = btn.textContent.replace(/[▼▲]/, isOpen ? '▲' : '▼');

                if (isOpen) {
                    const firstInput = target.querySelector('input:not([type=hidden])');
                    if (firstInput) firstInput.focus();
                }
            });
        });

        (function () {
            const input = document.getElementById('new_password');
            const aside = document.querySelector('.settings-password-requirements');
            if (!input || !aside) return;

            const item = (rule) => aside.querySelector(`[data-rule="${rule}"]`);

            const rules = {
                passphrase: (v) => {
                    const words = v.split(/[\s\-]+/).filter(w => w.length >= 3);
                    return words.length >= 4 && v.length >= 20;
                },
                length: (v) => v.length >= 8,
                uppercase: (v) => /[A-Z]/.test(v),
                lowercase: (v) => /[a-z]/.test(v),
                number: (v) => /[0-9]/.test(v),
                special: (v) => /[\W_]/.test(v),
            };

            const classicRules = ['length', 'uppercase', 'lowercase', 'number', 'special'];

            function setRule(name, met, dirty) {
                const el = item(name);
                if (!el) return;
                el.classList.toggle('met', met);
                el.classList.toggle('failed', dirty && !met);
            }

            input.addEventListener('input', function () {
                const v = this.value;
                const dirty = v.length > 0;

                const passphraseOk = rules.passphrase(v);
                const classicOk = classicRules.every(r => rules[r](v));

                setRule('passphrase', passphraseOk, dirty);
                classicRules.forEach(r => setRule(r, rules[r](v), dirty));

                aside.classList.toggle('mode-passphrase', passphraseOk);
                aside.classList.toggle('mode-classic', !passphraseOk && classicOk);
            });
        })();
    </script>
</body>

</html>