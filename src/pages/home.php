<?php

/**
 * @var string $includesDir
 * @var string $cssDir
 * @var string $jsDir
 */
?>
<!DOCTYPE html>
<html lang="en">

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
                    <h1 class="home-title">plrsys<span class="blinking">_</span></h1>
                    <p class="home-subtitle">A cozy space for plural systems to track, share, and understand themselves.</p>
                    <div class="home-actions">
                        <?php if (isset($current_user)): ?>
                            <a href="/dashboard" class="btn btn-primary">Go to Dashboard</a>
                        <?php else: ?>
                            <a href="/register" class="btn btn-primary">Get Started</a>
                            <a href="/login" class="btn btn-secondary">Log In</a>
                        <?php endif; ?>
                    </div>
                    <nav class="home-menu" aria-label="Home menu">
                        <a href="/about">About</a>
                        <span class="home-menu-sep">&middot;</span>
                        <a href="/changelog">Changelog</a>
                        <span class="home-menu-sep">&middot;</span>
                        <a href="/legal/privacy">Privacy</a>
                        <span class="home-menu-sep">&middot;</span>
                        <a href="/legal/tos">Terms</a>
                    </nav>
                </div>

                <div class="home-features">
                    <div class="home-feature">
                        <span class="home-feature-icon white glow-sm">><span class="blinking">_</span></span>
                        <h3>Manage your system</h3>
                        <p>Create and organise your system's members, with profiles, handles, and more.</p>
                    </div>
                    <div class="home-feature">
                        <span class="home-feature-icon green glow-sm">↺</span>
                        <h3>Track fronting</h3>
                        <p>Log who's fronting and when, and keep a history of fronting sessions.</p>
                    </div>
                    <div class="home-feature">
                        <span class="home-feature-icon yellow glow-sm">⊕</span>
                        <h3>Share with friends</h3>
                        <p>Invite trusted friends to view your system - on your terms, with granular sharing controls.</p>
                    </div>
                </div>
            </div>
            <?php include $includesDir . '/footer.php'; ?>
        </div>
    </div>
</body>

</html>