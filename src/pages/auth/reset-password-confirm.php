<?php
/**
 * @var array $parts
 * @var PDO $pdo
 * @var string $includesDir
 * @var string $cssDir
 * @var string $jsDir
 */

$token = $_GET['token'] ?? null;

if (!$token) {
    Alert::error(__('reset.error.invalid_link'));
    header('Location: /login');
    exit;
}

$tokenHash = hash('sha256', $token);

$stmt = $pdo->prepare('
    SELECT * FROM password_reset_tokens 
    WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW()
');
$stmt->execute([$tokenHash]);
$resetToken = $stmt->fetch(PDO::FETCH_ASSOC);

$userId = $resetToken['user_id'] ?? null;

if (!$resetToken) {
    Alert::error(__('reset.error.expired_link'));
    header('Location: /login');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['password_confirm'] ?? '';

    if ($password !== $confirm) {
        Alert::error(__('reset.error.passwords_mismatch'));
        header('Location: /reset-password/confirm?token=' . $token . '&user=' . $userId);
        exit;
    }

    $check = $auth->passwordMeetsCriteria($password);
    if ($check !== true) {
        Alert::error($check);
        header('Location: /reset-password/confirm?token=' . $token . '&user=' . $userId);
        exit;
    }

    $auth->updatePassword($userId, $password);

    // mark token as used
    $pdo->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE id = ?')
        ->execute([$resetToken['id']]);

    Alert::success(__('reset.success'));
    header('Location: /login');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('reset.page_title') ?></title>
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
                <div class="register-layout">
                    <div class="register-container">
                        <h1 class="register-title"><?= __('reset.title') ?></h1>
                        <form method="POST" action="/reset-password/confirm?token=<?= htmlspecialchars($token) ?>&user=<?= $userId ?>" class="register-form">
                            <label for="password"><?= __('reset.confirm.new_password') ?></label>
                            <input type="password" id="password" name="password" required>
                            <label for="password_confirm"><?= __('reset.confirm.confirm_password') ?></label>
                            <input type="password" id="password_confirm" name="password_confirm" required>
                            <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
                            <input type="submit" value="<?= __('reset.confirm.submit') ?>">
                        </form>
                    </div>

                    <aside class="password-requirements" aria-labelledby="password-requirements-title">
                        <h2 class="title" id="password-requirements-title"><?= __('register.password_requirements') ?></h2>

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
    <script>
        (function () {
            const input = document.getElementById('password');
            const aside = document.querySelector('.password-requirements');
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