<?php
/**
 * Footer template for the site.
 *
 * @var string $version The current version of the application.
 */
?>
<footer class="site-footer">
    <span class="footer-version"><?= $version ?></span>
    <span class="footer-sep">-</span>
    <span class="footer-credit"><?= __('footer.credit') ?></span>
    <span class="footer-sep">-</span>
    <nav class="footer-links">
        <a href="/about"><?= __('common.about') ?></a>
        <span class="footer-dot">·</span>
        <a href="/changelog"><?= __('common.changelog') ?></a>
        <span class="footer-dot">·</span>
        <a href="/legal/privacy"><?= __('common.privacy') ?></a>
        <span class="footer-dot">·</span>
        <a href="/legal/tos"><?= __('common.terms') ?></a>
    </nav>
</footer>

<div class="cookie-banner" data-cookie-banner role="dialog" aria-live="polite" aria-label="Cookie consent">
    <div class="cookie-banner__content">
        <p class="cookie-banner__text"><?= __('footer.cookie_notice') ?></p>
        <p class="cookie-banner__text">
            <a href="/legal/privacy"><?= __('footer.privacy_policy') ?></a>
        </p>
        <div class="cookie-banner__actions">
            <button type="button" class="cookie-btn" data-cookie-accept><?= __('common.accept') ?></button>
            <button type="button" class="cookie-btn cookie-reject" data-cookie-reject><?= __('common.reject') ?></button>
        </div>
    </div>
</div>