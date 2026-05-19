<?php
/**
 * @var array $parts
 * @var PDO $pdo
 * @var string $includesDir
 * @var string $cssDir
 * @var string $jsDir

 */

$parts ??= explode('/', trim($_SERVER['REQUEST_URI'], '/'));

Guards::requireLogin();


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Innerspace</title>
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
                <h1>Dashboard</h1>
                <p>Welcome to your dashboard! This is where you can manage your systems, members, and fronting sessions.</p>
                <ul>
                    <li><a href="/systems">View Systems</a></li>
                    <li><a href="/fronting">Manage Fronting Sessions</a></li>
                    <li><a href="/settings">Account Settings</a></li>
                </ul>
            </div>

            <?php include $includesDir . '/footer.php'; ?>
        </div>
    </div>

</body>
</html>