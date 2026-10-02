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
    <title>About | plrsys</title>
    <link rel="stylesheet" href="<?= $cssDir ?>">
    <link rel="shortcut icon" href="/assets/images/favicon.png" type="image/png">
    <script src="<?= $jsDir ?>" defer></script>

    <!-- Open Graph -->
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="plrsys">
    <meta property="og:title" content="About plrsys">
    <meta property="og:description" content="A cozy space for plural systems to track, share, and understand themselves. Learn what plrsys is, who it's for, and why it exists.">
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
                        <div class="about-hero-title">About plrsys</div>
                        <div class="about-hero-sub">
                            A cozy, safe space for plural systems to track, share, and understand themselves - built with care, not as a product.
                        </div>
                    </div>

                    <!-- What is plurality -->
                    <div class="about-card">
                        <div class="about-card-label">// What is plurality?</div>
                        <p>
                            Plurality (or being a plural system) means that multiple distinct identities, personalities, or "members" share one body. Each member may have their own name, pronouns, preferences, and way of experiencing the world.
                        </p>
                        <p>
                            Plurality is most commonly associated with
                            <span class="about-highlight">Dissociative Identity Disorder (DID)</span>
                            and
                            <span class="about-highlight">OSDD</span>,
                            but many people experience plurality outside of a clinical context too.
                            It's not a monolith - every system is different, and that's okay.
                        </p>
                        <p>
                            One key part of plural life is <em>fronting</em> - the experience of a specific member being "in control" or most present at a given time. Keeping track of this, and sharing it with trusted people, can be really meaningful.
                        </p>
                    </div>

                    <!-- Why plrsys -->
                    <div class="about-card">
                        <div class="about-card-label">// Why plrsys?</div>
                        <p>
                            There are existing tools for plural systems - but many of them are tied to large platforms, lack privacy controls, or just don't feel like <em>home</em>. plrsys was built to be something smaller, more personal, and more intentional.
                        </p>
                        <p>
                            The goal is simple: give systems a place to manage their members, track fronting sessions, and share selectively with friends - on their own terms, with controls that actually make sense.
                        </p>
                        <p>
                            No ads. No algorithmic feed. No pressure. Just a tool that respects you.
                        </p>
                    </div>

                    <!-- Built for Skye -->
                    <div class="about-skye">
                        <span class="about-skye-heart">♥</span>
                        <p>
                            plrsys was originally built for
                            <span class="name">Skye</span>
                            - the person I love - who needed exactly this kind of space. What started as a personal project became something that felt worth sharing with anyone who might need it too.
                        </p>
                    </div>

                    <!-- Open source -->
                    <div class="about-oss">
                        <span class="about-oss-icon">⊕</span>
                        <div class="about-oss-content">
                            <div class="about-card-label">// Open source</div>
                            <p>
                                plrsys is free and open source, licensed under the
                                <a href="https://www.gnu.org/licenses/agpl-3.0.html" target="_blank" rel="noopener">AGPL-3.0 license</a>.
                                That means you can read the code, audit it, self-host it, or contribute to it.
                            </p>
                            <p>
                                Transparency matters - especially for an app that handles personal and sensitive information. You shouldn't have to just trust a black box.
                                The full source is available on <a href="https://github.com/sarxzer/plrsys" target="_blank" rel="noopener">GitHub</a>.
                            </p>
                        </div>
                    </div>

                    <!-- CTA -->
                    <div class="about-cta">
                        <?php if (isset($current_user)): ?>
                            <a href="/dashboard" class="cta-primary">Go to dashboard →</a>
                        <?php else: ?>
                            <a href="/register" class="cta-primary">Get started →</a>
                            <a href="/login" class="cta-secondary">Log in</a>
                        <?php endif; ?>
                    </div>

                </div>
            </div>

            <?php include $includesDir . '/footer.php'; ?>
        </div>
    </div>
</body>

</html>