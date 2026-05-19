<?php

/**
 * @var PDO $pdo
 * @var string $includesDir
 * @var string $cssDir
 * @var string $jsDir
 * @var array $current_user
 */

Guards::requireLogin();

$system_handle = $parts[2] ?? null;
$member_handle = ltrim($parts[3], '@');

$stmt = $pdo->prepare('SELECT * FROM systems WHERE handle = ?');
$stmt->execute([$system_handle]);
$system = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$system) {
    Alert::error("System not found or you don't have permission to manage it.");
    header('Location: /dashboard');
    exit;
}

Guards::requireSystemOwnership($pdo, (int) $system['id']);

Alert::dev("System ID: " . $system['id']);
Alert::dev($member_handle);
$stmt = $pdo->prepare('SELECT * FROM members WHERE handle = ? AND system_id = ?');
$stmt->execute([$member_handle, $system['id']]);
$member = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$member) {
    Alert::error("Member not found.");
    header('Location: /manage/system/' . $system_handle);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $pronouns = trim($_POST['pronouns']);
    $handle = trim($_POST['handle']);
    $color = trim($_POST['color']);

    if (empty($name)) {
        Alert::error("Name cannot be empty.");
        header('Location: /manage/s/' . $system_handle . '/@' . $member_handle);
        exit;
    }

    if (empty($handle)) {
        Alert::error("Handle cannot be empty.");
        header('Location: /manage/s/' . $system_handle . '/@' . $member_handle);
        exit;
    }

    if (!preg_match('/^[a-z0-9\-]+$/', $handle)) {
        Alert::error("Handle can only contain lowercase letters, numbers, and hyphens.");
        header('Location: /manage/s/' . $system_handle . '/@' . $member_handle);
        exit;
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM members WHERE handle = ? AND system_id = ? AND id != ?');
    $stmt->execute([$handle, $system['id'], $member['id']]);
    if ($stmt->fetchColumn() > 0) {
        Alert::error("Handle already exists. Please choose a different one.");
        header('Location: /manage/s/' . $system_handle . '/@' . $member_handle);
        exit;
    }

    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
        Alert::error("Invalid color format. Please use a hex color code like #ff0000.");
        header('Location: /manage/s/' . $system_handle . '/@' . $member_handle);
        exit;
    }

    $stmt = $pdo->prepare('UPDATE members SET name = ?, pronouns = ?, handle = ?, color = ? WHERE id = ?');
    $stmt->execute([$name, $pronouns, $handle, $color, $member['id']]);

    Alert::success("Member updated successfully.");
    header('Location: /manage/s/' . $system_handle . '/@' . $member_handle);
    exit;
}


?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Member | Innerspace</title>
    <link rel="stylesheet" href="<?= htmlspecialchars($cssDir) ?>">
    <script src="<?= htmlspecialchars($jsDir) ?>"></script>
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
                <h1>Edit @<?= htmlspecialchars($member['handle']) ?></h1>
                
                <form action="/manage/s/<?= htmlspecialchars($system['handle']) ?>/@<?= htmlspecialchars($member['handle']) ?>" method="POST">
                    <div class="form-group">
                        <label for="name">Name:</label>
                        <input type="text" id="name" name="name" value="<?= htmlspecialchars($member['name']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="pronouns">Pronouns:</label>
                        <input type="text" id="pronouns" name="pronouns" value="<?= htmlspecialchars($member['pronouns']) ?>">
                    </div>
                    <div class="form-group">
                        <label for="handle">Handle:</label>
                        <input type="text" id="handle" name="handle" value="<?= htmlspecialchars($member['handle']) ?>" required>
                        <small>Handle needs to be unique within the system.</small>
                    </div>
                    <div class="form-group">
                        <input type="color" id="color" name="color" value="<?= htmlspecialchars($member['color']) ?>">
                        <label for="color">Color</label>
                    </div>

                    <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
                    <button type="submit">Update Member</button>
                </form>
            </div>
        </div>
    </div>
</body>

</html>