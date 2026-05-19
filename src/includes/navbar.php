<?php

/**
 * @var array $parts
 * @var array $current_user
 * @var array $breadcrumbs
 */
?>
<nav class="top-nav">
    <div class="nav-inner">
        <div class="nav-logo">✦ INNER<br>SPACE</div>
        <button class="nav-toggle" aria-label="Toggle navigation" aria-expanded="false">
            <span></span><span></span><span></span>
        </button>
    </div>
    <div class="nav-links">
        <a href="/home" class="<?= ($parts[0] === 'home' || $parts[0] === '') ? 'active' : ''; ?>">Home</a>
        <a href="/system" class="<?= ($parts[0] === 'system') ? 'active' : ''; ?>">System</a>
        <?php if (isset($current_user)): ?>
            <a href="/dashboard" class="<?= ($parts[0] === 'dashboard') ? 'active' : ''; ?>">Dashboard</a>
            <a href="/manage" class="<?= ($parts[0] === 'manage') ? 'active' : ''; ?>">Manage</a>
            <a href="/settings" class="<?= ($parts[0] === 'settings') ? 'active' : ''; ?>">Settings</a>
            <a href="/logout">Logout (<?= htmlspecialchars($current_user['username']) ?>)</a>
        <?php else: ?>
            <a href="/login" class="<?= ($parts[0] === 'login') ? 'active' : '' ?>">Login</a>
            <a href="/register" class="nav-register <?= ($parts[0] === 'register') ? 'active' : '' ?>">Register</a>
        <?php endif; ?>
        <?php if ($_ENV['APP_DEBUG'] === 'true'): ?>
            <span class="debug-indicator" title="Debug mode is ON">[DEBUG]</span>
        <?php endif; ?>
    </div>
</nav>
<div class="breadcrumb">
    <?php foreach ($breadcrumbs as $crumb): ?>
        / <a href="<?= $crumb['url'] ?>"><?= $crumb['name'] ?></a>
    <?php endforeach; ?>
</div>
<marquee class="site-announcement" behavior="scroll" direction="left" scrollamount="5">
    Welcome to Innerspace! The website is still under construction. <a href="/changelog">Learn more</a>.
</marquee>
<nav class="bottom-nav">
    <a href="/home" class="bottom-nav-item <?= ($parts[0] === 'home' || $parts[0] === '') ? 'active' : '' ?>">
        <span class="bottom-nav-icon">⌂</span>
        <span class="bottom-nav-label">Home</span>
    </a>
    <?php if (isset($current_user)): ?>
        <a href="/dashboard" class="bottom-nav-item <?= ($parts[0] === 'dashboard') ? 'active' : '' ?>">
            <span class="bottom-nav-icon">◈</span>
            <span class="bottom-nav-label">Dashboard</span>
        </a>
        <a href="/system" class="bottom-nav-item <?= ($parts[0] === 'system' || $parts[0] === 's') ? 'active' : '' ?>">
            <span class="bottom-nav-icon">✦</span>
            <span class="bottom-nav-label">System</span>
        </a>
        <a href="/manage" class="bottom-nav-item <?= ($parts[0] === 'manage') ? 'active' : '' ?>">
            <span class="bottom-nav-icon">⚙</span>
            <span class="bottom-nav-label">Manage</span>
        </a>
        <a href="/settings" class="bottom-nav-item <?= ($parts[0] === 'settings') ? 'active' : '' ?>">
            <span class="bottom-nav-icon">◎</span>
            <span class="bottom-nav-label">Settings</span>
        </a>
    <?php else: ?>
        <a href="/login" class="bottom-nav-item <?= ($parts[0] === 'login') ? 'active' : '' ?>">
            <span class="bottom-nav-icon">⎆</span>
            <span class="bottom-nav-label">Login</span>
        </a>
    <?php endif; ?>
    <?php if ($_ENV['APP_DEBUG'] === 'true'): ?>
        <span class="bottom-nav-item debug-indicator" title="Debug mode is ON" aria-label="Debug mode is on">
            <span class="bottom-nav-icon">⟨/⟩</span>
            <span class="bottom-nav-label">Debug</span>
        </span>
    <?php endif; ?>
</nav>