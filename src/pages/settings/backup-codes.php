<?php
/**
 * @var string $includesDir
 * @var string $cssDir
 * @var string $jsDir
 */

Guards::requireLogin();

$codes = $_SESSION['show_backup_codes'] ?? null;
if (!$codes) {
    header("Location: /settings");
    exit;
}
unset($_SESSION['show_backup_codes']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Backup Codes | Innerspace</title>
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
                        <div class="backup-warn-badge">⚠ IMPORTANT</div>
                        <div class="backup-title">Backup Codes</div>
                        <div class="backup-subtitle">Save these before continuing</div>
                    </div>

                    <div class="backup-warning">
                        <span class="backup-warning-icon">⚠</span>
                        <div class="backup-warning-text">
                            <strong>These codes won't be shown again.</strong><br>
                            Each code works once. If you lose access to your authenticator app, these are your only way
                            back in. Store them somewhere safe.
                        </div>
                    </div>

                    <div class="backup-codes-panel">
                        <div class="backup-codes-label">// BACKUP_CODES</div>
                        <div class="backup-codes-grid">
                            <?php foreach ($codes as $code): ?>
                                <div class="backup-code-item">
                                    <div class="backup-code-dot"></div>
                                    <span class="backup-code-text"><?= htmlspecialchars($code) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button class="backup-copy-btn" id="copy-btn"
                            data-codes="<?= htmlspecialchars(implode("\n", $codes)) ?>">⎘ copy all codes</button>
                    </div>
                    <a href="/settings" class="backup-cta">I've saved them → go back to settings</a>
                    <div class="backup-disclaimer">you won't be able to access these again</div>
                </div>
            </div>
            <?php include $includesDir . '/footer.php'; ?>
        </div>
    </div>

    <script>
        document.getElementById('copy-btn').addEventListener('click', function () {
            const codes = this.dataset.codes;
            navigator.clipboard.writeText(codes).then(() => {
                this.textContent = '✓ copied to clipboard';
                this.classList.add('copied');
                setTimeout(() => {
                    this.textContent = '⎘ copy all codes';
                    this.classList.remove('copied');
                }, 2500);
            });
        });
    </script>
</body>

</html>