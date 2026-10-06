<?php

/**
 * @var array $parts
 * @var PDO $pdo
 * @var string $includesDir
 * @var string $cssDir
 * @var string $jsDir

 */

require_once __DIR__ . '/../../php/totp.php';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $totpEnabled = isset($_POST['totp']);

    $auth = new Auth($pdo);

    $_SESSION['last_failed_username'] = $username;
    $_SESSION['last_failed_totp'] = $totpEnabled ? 'checked' : '';


    if (empty($username) || empty($password)) {
        Alert::error(__('register.error.required'));
        $_SESSION['last_failed_username'] = $username;
        $_SESSION['last_failed_totp'] = $totpEnabled;
        header("Location: /register");
        exit;
    }

    $passwordCheck = $auth->passwordMeetsCriteria($password);
    if ($passwordCheck !== true) {
        Alert::error($passwordCheck);
        header("Location: /register");
        exit;
    }

    $userId = $auth->register($username, $password);

    if ($userId === null) {
        Alert::error(__('register.error.username_taken'));
        header("Location: /register");
        exit;
    }

    // Log the user in
    if ($totpEnabled) {
        $data = totp_generate_secret($username, "plrsys");

        $_SESSION['pending_totp_user_id'] = $userId;
        $_SESSION['pending_totp_secret'] = $data['secret'];
        $_SESSION['pending_totp_qr'] = $data['qr_base64'];

        Alert::info(__('register.info.scan_totp'));

        header("Location: /register/totp");
        exit;
    }
    $auth->login($userId, false);

    Alert::success(__('register.success', $username));
    Alert::warning(__('register.warning.security'));

    header("Location: /dashboard");
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('register.page_title') ?></title>
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
                    <div class="register-container">
                        <h1 class="register-title"><?= __('register.title') ?></h1>
                    <div class="register-box">
                        <form action="register" method="post" class="register-form">
                            <label for="username"><?= __('register.username') ?></label>
                            <input type="text" id="username" name="username"
                                value="<?= htmlspecialchars($_SESSION['last_failed_username'] ?? '') ?>"
                                required>

                            <label for="password"><?= __('register.password') ?></label>
                            <input type="password" id="password" name="password" required><br>

                            <label for="totp" class="checkbox-label">
                                <input type="checkbox" name="totp" id="totp" value="1"
                                    <?= isset($_SESSION['last_failed_totp']) && $_SESSION['last_failed_totp'] === 'checked' ? 'checked' : '' ?>>
                                <?= __('register.enable_totp') ?>
                            </label>

                            <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
                            <input type="submit" value="<?= __('register.submit') ?>">
                        </form>

                        <p class="register-subtext"><a href="/login"><?= __('register.login_prompt') ?></a></p>
                    </div>

                    <aside class="password-requirements" aria-labelledby="password-requirements-title">
                        <h2 class="title"><?= __('register.password_requirements') ?></h2>

                        <!-- Passphrase option -->
                        <div class="group">
                            <div class="label"><?= __('register.passphrase_option') ?></div>
                            <ul class="list">
                                <li class="item" data-rule="passphrase">
                                    <span class="pip"></span>
                                    <?= __('register.passphrase_rule') ?>
                                </li>
                            </ul>
                        </div>
                        <div class="or"><?= __('register.or') ?></div>

                        <!-- Standard option -->
                        <div class="group">
                            <div class="label"><?= __('register.classic_option') ?></div>
                            <ul class="list">
                                <li class="item" data-rule="length">
                                    <span class="pip"></span>
                                    <?= __('register.password_length') ?>
                                </li>
                                <li class="item" data-rule="uppercase">
                                    <span class="pip"></span>
                                    <?= __('register.password_uppercase') ?>
                                </li>
                                <li class="item" data-rule="lowercase">
                                    <span class="pip"></span>
                                    <?= __('register.password_lowercase') ?>
                                </li>
                                <li class="item" data-rule="number">
                                    <span class="pip"></span>
                                    <?= __('register.password_number') ?>
                                </li>
                                <li class="item" data-rule="special">
                                    <span class="pip"></span>
                                    <?= __('register.password_special') ?>
                                </li>
                            </ul>
                        </div>


                    </aside>
                </div>
            </div>

            <?php include $includesDir . '/footer.php'; ?>
        </div>
    </div>
</body>
<script>
    (function () {
        const input = document.getElementById('password');
        const aside = document.querySelector('.password-requirements');
        if (!input || !aside) return;

        // grab all rule items by their data-rule attribute
        const item = (rule) => aside.querySelector(`[data-rule="${rule}"]`);

        const rules = {
            // passphrase: 4+ words (split on space or hyphen), 20+ chars
            passphrase: (v) => {
                const words = v.split(/[\s\-]+/).filter(w => w.length >= 3);
                return words.length >= 4 && v.length >= 20;
            },
            // classic rules
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

            // update pip states
            setRule('passphrase', passphraseOk, dirty);
            classicRules.forEach(r => setRule(r, rules[r](v), dirty));

            // dim whichever group isn't being used
            aside.classList.toggle('mode-passphrase', passphraseOk);
            aside.classList.toggle('mode-classic', !passphraseOk && classicOk);
        });
    })();
</script>


</html>