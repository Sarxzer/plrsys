<?php
/**
 * @var string $includesDir
 * @var string $cssDir
 * @var string $jsDir
 * @var array $parts
 * @var array $current_user
 */
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('about.page_title') ?></title>
    <link rel="stylesheet" href="<?= $cssDir ?>">
    <link rel="shortcut icon" href="/assets/images/favicon.png" type="image/png">
    <script src="<?= $jsDir ?>" defer></script>

    <!-- Open Graph -->
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="plrsys">
    <meta property="og:title" content="<?= __('about.title') ?>">
    <meta property="og:description" content="<?= __('about.meta_description') ?>">
    <meta property="og:url" content="https://plrsys.xyz/about">

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
                <div class="about-wrapper">

                    <!-- Hero -->
                    <div class="about-hero">
                        <span class="about-hero-icon">✦</span>
                        <div class="about-hero-title"><?= __('about.title') ?></div>
                        <div class="about-hero-sub">
                            <?= __('about.intro') ?>
                        </div>
                    </div>

                    <!-- What is plurality -->
                    <div class="about-card">
                        <div class="about-card-label"><?= __('about.what_is_plurality') ?></div>
                        <p>
                            <?= __('about.plurality_definition.1') ?>
                        </p>
                        <p>
                            <?= __('about.plurality_definition.2') ?>
                        </p>
                        <p>
                            <?= __('about.plurality_definition.3') ?>
                        </p>
                    </div>

                    <!-- Why plrsys -->
                    <div class="about-card">
                        <div class="about-card-label"><?= __('about.why_plrsys') ?></div>
                        <p>
                            <?= __('about.why_plrsys_description.1') ?>
                        </p>
                        <p>
                            <?= __('about.why_plrsys_description.2') ?>
                        </p>
                        <p>
                            <?= __('about.why_plrsys_description.3') ?>
                        </p>
                    </div>

                    <!-- Built for Skye -->
                    <div class="about-skye">
                        <span class="about-skye-heart">♥</span>
                        <p>
                            <?= __('about.skye') ?>
                        </p>
                    </div>

                    <!-- Open source -->
                    <div class="about-oss">
                        <span class="about-oss-icon">⊕</span>
                        <div class="about-oss-content">
                            <div class="about-card-label"><?= __('about.oss') ?></div>
                            <p>
                                <?= __('about.oss_description.1') ?>
                            </p>
                            <p>
                                <?= __('about.oss_description.2') ?>
                            </p>
                        </div>
                    </div>

                    <!-- CTA -->
                    <div class="about-cta">
                        <?php if (isset($current_user)): ?>
                            <a href="/dashboard" class="cta-primary"><?= __('home.dashboard') ?> →</a>
                        <?php else: ?>
                            <a href="/register" class="btn cta-primary"><?= __('home.get_started') ?> →</a>
                            <a href="/login" class="btn cta-secondary"><?= __('home.login') ?></a>
                        <?php endif; ?>
                    </div>

                </div>
            </div>

            <?php include $includesDir . '/footer.php'; ?>
        </div>
    </div>
</body>

</html>