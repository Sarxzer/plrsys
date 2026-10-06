<?php
/**
 * @var string $includesDir
 * @var string $cssDir
 * @var string $jsDir
 */
$codes = $_SESSION['show_backup_codes'] ?? null;
if (!$codes) {
    header("Location: /dashboard");
    exit;
}
unset($_SESSION['show_backup_codes']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('backup.page_title') ?></title>
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
                <div class="backup-container">
                    <div class="backup-header">
                        <div class="backup-warn-badge"><?= __('backup.important') ?></div>
                        <div class="backup-title"><?= __('backup.title') ?></div>
                        <div class="backup-subtitle"><?= __('backup.subtitle') ?></div>
                    </div>

                    <div class="backup-warning">
                        <span class="backup-warning-icon">⚠</span>
                        <div class="backup-warning-text">
                            <strong><?= __('backup.not_shown_again') ?></strong><br>
                            <?= __('backup.warning') ?>
                        </div>
                    </div>

                    <div class="backup-codes-panel">
                        <div class="backup-codes-label"><?= __('backup.codes_label') ?></div>
                        <div class="backup-codes-grid">
                            <?php foreach ($codes as $code): ?>
                                <div class="backup-code-item">
                                    <div class="backup-code-dot"></div>
                                    <span class="backup-code-text"><?= htmlspecialchars($code) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button class="backup-copy-btn" id="copy-btn"
                            data-codes="<?= htmlspecialchars(implode("\n", $codes)) ?>" data-copy-label="<?= __('backup.copy') ?>" data-copied-label="<?= __('backup.copied') ?>"><?= __('backup.copy') ?></button>
                    </div>

                    <a href="/dashboard" class="backup-cta"><?= __('backup.saved') ?></a>
                    <div class="backup-disclaimer"><?= __('backup.disclaimer') ?></div>
                </div>
            </div>
            <?php include $includesDir . '/footer.php'; ?>
        </div>
    </div>

    <script>
        document.getElementById('copy-btn').addEventListener('click', function () {
            const codes = this.dataset.codes;
            navigator.clipboard.writeText(codes).then(() => {
                this.textContent = this.dataset.copiedLabel;
                this.classList.add('copied');
                setTimeout(() => {
                    this.textContent = this.dataset.copyLabel;
                    this.classList.remove('copied');
                }, 2500);
            });
        });
    </script>
</body>

</html>