<?php
/**
 * @var PDO $pdo
 * @var string $includesDir
 * @var string $cssDir
 * @var string $jsDir

 */

// // Fetch latest version info from GitHub API
// $latestVersion = null;
// try {
//     $response = file_get_contents('https://api.github.com/repos/sarxzer/plrsys/commits/main ', false, stream_context_create([
//         'http' => [
//             'header' => 'User-Agent: plrsys/1.0'
//         ]
//     ]));
//     $data = json_decode($response, true);
//     if (isset($data['commit']['message'])) {
//         // Assuming the commit message contains the version like "Release v1.2.3"
//         if (preg_match('/Release\s+v?([\d\.]+)/i', $data['commit']['message'], $matches)) {
//             $latestVersion = $matches[1];
//         } else {
//             $latestVersion = substr($data['sha'], 0, 7); // Fallback to short commit hash if no version found in message
//         }
//     }
// } catch (Exception $e) {
//     Alert::error("Failed to fetch latest version info.");
// }

// Get changelog from CHANGELOG.md
$markdownFile = __DIR__ . '/../../CHANGELOG.md';
if (!file_exists($markdownFile)) {
    Alert::error('Changelog Not Found');
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
    <title>Changelog | plrsys</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="shortcut icon" href="/assets/images/favicon.png" type="image/png">
    <script src="/assets/js/main.js?v=<?= filemtime(__DIR__ . '/../../public/assets/js/main.js') ?>" defer></script>
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
                <h1>Changelog</h1>
                <p>Latest version: <?= $version ?? 'Unknown' ?></p>
                <div class="changelog-content">
                    <?= $htmlContent ?>
                </div>
            </div>

            <?php include $includesDir . '/footer.php'; ?>
        </div>
    </div>
</body>
</html>