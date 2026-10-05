<?php
/**
 * @var string $includesDir
 * @var string $cssDir
 * @var string $jsDir
 * @var string $langage
 */
?>
<!DOCTYPE html>
<html lang="<?= $langage ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, interactive-widget=resizes-content, viewport-fit=cover">
    <title>plrsys</title>
    <link rel="stylesheet" href="<?= $cssDir ?>">
    <link rel="shortcut icon" href="/assets/images/favicon.png" type="image/png">
    <script src="<?= $jsDir ?>" defer></script>
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#0f3460">
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
                <div class="home-hero">
                    <h1 class="home-title"><?= __('common.site_name') ?><span class="blinking">_</span></h1>
                    <p class="home-subtitle"><?= __('home.subtitle') ?></p>
                    <div class="home-actions">
                        <?php if (isset($current_user)): ?>
                            <a href="/dashboard" class="btn btn-primary"><?= __('home.dashboard') ?></a>
                        <?php else: ?>
                            <a href="/register" class="btn btn-primary"><?= __('home.get_started') ?></a>
                            <a href="/login" class="btn btn-secondary"><?= __('home.login') ?></a>
                        <?php endif; ?>
                    </div>
                    <nav class="home-menu" aria-label="Home menu">
                        <a href="/about"><?= __('common.about') ?></a>
                        <span class="home-menu-sep">&middot;</span>
                        <a href="/changelog"><?= __('common.changelog') ?></a>
                        <span class="home-menu-sep">&middot;</span>
                        <a href="/legal/privacy"><?= __('common.privacy') ?></a>
                        <span class="home-menu-sep">&middot;</span>
                        <a href="/legal/tos"><?= __('common.terms') ?></a>
                    </nav>
                </div>

                <div class="home-features">
                    <div class="home-feature">
                        <span class="home-feature-icon white glow-sm">><span class="blinking">_</span></span>
                        <h3><?= __('home.manage_system') ?></h3>
                        <p><?= __('home.manage_system_description') ?></p>
                    </div>
                    <div class="home-feature">
                        <span class="home-feature-icon green glow-sm">↺</span>
                        <h3><?= __('home.track_fronting') ?></h3>
                        <p><?= __('home.track_fronting_description') ?></p>
                    </div>
                    <div class="home-feature">
                        <span class="home-feature-icon yellow glow-sm">⊕</span>
                        <h3><?= __('home.share_friends') ?></h3>
                        <p><?= __('home.share_friends_description') ?></p>
                    </div>
                </div>
            </div>
            <?php include $includesDir . '/footer.php'; ?>
        </div>
    </div>
</body>

</html>