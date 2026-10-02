<?php
/**
 * @var array $parts
 * @var PDO $pdo
 * @var string $includesDir
 * @var string $cssDir
 * @var string $jsDir
 */

// Parse privacy policy markdown file and render it as HTML using parsedown
$markdownFile = __DIR__ . '/privacy.md';
if (!file_exists($markdownFile)) {
    Alert::error('Privacy Policy Not Found');
    header('Location: /home');
    exit;
}

$Parsedown = new ParsedownExtra();
$markdownContent = file_get_contents($markdownFile);
$htmlContent = $Parsedown->text($markdownContent);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy - plrsys</title>
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
                <div class="legal-container">
                    <div class="legal-content">
                        <?= $htmlContent ?>
                    </div>
                </div>
            </div>
            <?php include $includesDir . '/footer.php'; ?>
        </div>
    </div>
</body>
</html>