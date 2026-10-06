<?php

/**
 * @var array $parts
 * @var array $current_user
 * @var array $breadcrumbs
 */
?>
<nav class="top-nav">
    <div class="nav-inner">
        <div class="nav-logo"><?= __('common.site_name') ?><span class="blinking">_</span></div>
        <button class="nav-toggle" aria-label="Toggle navigation" aria-expanded="false">
            <span></span><span></span><span></span>
        </button>
    </div>
    <div class="nav-links">
        <a href="/home" class="<?= ($parts[0] === 'home' || $parts[0] === '') ? 'active' : ''; ?>"><?= __('nav.home') ?></a>
        <?php if (isset($current_user)): ?>
            <a href="/dashboard" class="<?= ($parts[0] === 'dashboard') ? 'active' : ''; ?>"><?= __('nav.dashboard') ?></a>
            <a href="/manage" class="<?= ($parts[0] === 'manage') ? 'active' : ''; ?>"><?= __('nav.manage') ?></a>
            <a href="/settings" class="<?= ($parts[0] === 'settings') ? 'active' : ''; ?>"><?= __('nav.settings') ?></a>
            <form action="/logout" method="POST" class="nav-link-form">
                <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
                <button type="submit" class="nav-link-button"><?= __('nav.logout') ?> (<?= htmlspecialchars($current_user['username']) ?>)</button>
            </form>
        <?php else: ?>
            <a href="/login" class="<?= ($parts[0] === 'login') ? 'active' : '' ?>"><?= __('nav.login') ?></a>
            <a href="/register" class="nav-register <?= ($parts[0] === 'register') ? 'active' : '' ?>"><?= __('nav.register') ?></a>
        <?php endif; ?>
        <?php if ($_ENV['APP_DEBUG'] === 'true'): ?>
            <a href="/system" class="<?= ($parts[0] === 'system') ? 'active' : ''; ?>"><?= __('nav.system') ?></a>
            <span class="debug-indicator" title="<?= __('nav.debug_mode') ?>" aria-label="<?= __('nav.debug_mode') ?>"><?= __('nav.debug_label') ?></span>
        <?php endif; ?>
    </div>
</nav>
<div class="breadcrumb">
    <?php foreach ($breadcrumbs as $crumb): ?>
        / <a href="<?= $crumb['url'] ?>"><?= $crumb['name'] ?></a>
    <?php endforeach; ?>
</div>
<marquee class="site-announcement" behavior="scroll" direction="left" scrollamount="5">
    <?= __('nav.announcement') ?>
</marquee>
<nav class="bottom-nav">
    <a href="/home" class="bottom-nav-item <?= ($parts[0] === 'home' || $parts[0] === '') ? 'active' : '' ?>">
        <span class="bottom-nav-icon">⌂</span>
        <span class="bottom-nav-label"><?= __('nav.home') ?></span>
    </a>
    <?php if (isset($current_user)): ?>
        <a href="/dashboard" class="bottom-nav-item <?= ($parts[0] === 'dashboard') ? 'active' : '' ?>">
            <span class="bottom-nav-icon">◈</span>
            <span class="bottom-nav-label"><?= __('nav.dashboard') ?></span>
        </a>
        <a href="/system" class="bottom-nav-item <?= ($parts[0] === 'system' || $parts[0] === 's') ? 'active' : '' ?>">
            <span class="bottom-nav-icon">✦</span>
            <span class="bottom-nav-label"><?= __('nav.system') ?></span>
        </a>
        <a href="/manage" class="bottom-nav-item <?= ($parts[0] === 'manage') ? 'active' : '' ?>">
            <span class="bottom-nav-icon">⚙</span>
            <span class="bottom-nav-label"><?= __('nav.manage') ?></span>
        </a>
        <a href="/settings" class="bottom-nav-item <?= ($parts[0] === 'settings') ? 'active' : '' ?>">
            <span class="bottom-nav-icon">◎</span>
            <span class="bottom-nav-label"><?= __('nav.settings') ?></span>
        </a>
        <form action="/logout" method="POST" class="bottom-nav-form">
            <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
            <button type="submit" class="bottom-nav-item">
                <span class="bottom-nav-icon">⎋</span>
                <span class="bottom-nav-label"><?= __('nav.logout') ?></span>
            </button>
        </form>
    <?php else: ?>
        <a href="/login" class="bottom-nav-item <?= ($parts[0] === 'login') ? 'active' : '' ?>">
            <span class="bottom-nav-icon">⎆</span>
            <span class="bottom-nav-label"><?= __('nav.login') ?></span>
        </a>
    <?php endif; ?>
    <?php if ($_ENV['APP_DEBUG'] === 'true'): ?>
        <span class="bottom-nav-item debug-indicator" title="<?= __('nav.debug_mode') ?>" aria-label="<?= __('nav.debug_mode') ?>">
            <span class="bottom-nav-icon">⟨/⟩</span>
            <span class="bottom-nav-label"><?= __('nav.debug') ?></span>
        </span>
    <?php endif; ?>
</nav>