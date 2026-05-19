<?php
/**
 * @var array $parts
 * @var PDO $pdo
 * @var string $includesDir
 * @var string $cssDir
 * @var string $jsDir
 */
// system creation page

Guards::requireLogin();

$auth = new Auth($pdo);
$currentUser = $auth->requireCurrentUser();
$userId = (int) $currentUser['id'];


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $handle = trim($_POST['handle']);

    // Validate input
    if (empty($name) || empty($handle)) {
        Alert::error("Name and handle are required.");
        header('Location: /manage/systems/new');
        exit;
    }

    if (!preg_match('/^[a-z0-9\-]+$/', $handle)) {
        Alert::error("Handle can only contain lowercase letters, numbers, and hyphens.");
        header('Location: /manage/systems/new');
        exit;
    }

    // Check if handle already exists
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM systems WHERE handle = ?');
    $stmt->execute([$handle]);
    if ($stmt->fetchColumn() > 0) {
        Alert::error("Handle already exists. Please choose a different one.");
        header('Location: /manage/systems/new');
        exit;
    }

    // Insert new system into database
    $stmt = $pdo->prepare('INSERT INTO systems (user_id, name, handle) VALUES (?, ?, ?)');
    $stmt->execute([$userId, $name, $handle]);

    // Redirect to the new system's page
    header('Location: /manage/system/' . $handle);
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New System | Innerspace</title>
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
                <h1>Create a new system</h1>
                <p>Welcome to the system creation page!</p>

                <form action="/manage/system/new" method="POST">
                    <label for="name">System Name:</label>
                    <input type="text" id="name" name="name" required>

                    <label for="handle">System Handle (lowercase, no spaces):</label>
                    <div class="input-wrapper">
                        <span class="input-prefix">@</span>
                        <input type="text" id="handle" name="handle" pattern="[a-z0-9\-]+"
                            title="Lowercase letters, numbers, and hyphens only." required>
                    </div>

                    <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
                    <button type="submit">Create System</button>
                </form>
            </div>

            <?php include $includesDir . '/footer.php'; ?>
        </div>
    </div>
</body>

</html>