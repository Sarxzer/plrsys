<?php

/** @var array $parts
 * @var PDO $pdo
 * @var string $includesDir
 * @var string $cssDir
 * @var string $jsDir
 */

// Show all system (dev)

if ($_ENV['APP_DEBUG'] !== 'true') {
    Alert::error("This page is only accessible in debug mode.");
    header('Location: /');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM systems");
$stmt->execute();
$systems = $stmt->fetchAll(PDO::FETCH_ASSOC);

// echo "<h1>Public Systems</h1>";
// if (count($systems) === 0) {
//     echo "<p>No public systems found.</p>";
// } else {
//     echo "<ul>";
//     foreach ($systems as $system) {
//         if (!$system['is_public']) {
//             continue; // Skip non-public systems
//         }
//         echo "<li><a href='/system/" . htmlspecialchars($system['handle']) . "'>" . htmlspecialchars($system['name']) . "</a> (@" . htmlspecialchars($system['handle']) . ")</li>";
//     }
//     echo "</ul>";
// }

// echo "<h2>Private Systems</h2>";
// echo "<ul>";
// foreach ($systems as $system) {
//     if ($system['is_public']) {
//         continue; // Skip public systems
//     }
//     echo "<li><a href='/system/" . htmlspecialchars($system['handle']) . "'>" . htmlspecialchars($system['name']) . "</a> (@" . htmlspecialchars($system['handle']) . ")</li>";
// }
// echo "</ul>";
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Systems | plrsys</title>
    <link rel="stylesheet" href="<?= $cssDir ?>">
    <link rel="shortcut icon" href="/assets/images/favicon.png" type="image/png">
    <script src="<?= $jsDir?>" defer></script>
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
                <h1>Systems</h1>
                <?php if (count($systems) === 0): ?>
                    <p>No systems found.</p>
                <?php else: ?>
                    <ul>
                        <?php foreach ($systems as $system): ?>
                            <li><a href='/s/<?= htmlspecialchars($system['handle']) ?>'><?= htmlspecialchars($system['name']) ?></a> (/s/<?= htmlspecialchars($system['handle']) ?>) - <?= $system['is_public'] ? 'Public' : 'Private' ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <?php include $includesDir . '/footer.php'; ?>
        </div>
    </div>
</body>

</html>