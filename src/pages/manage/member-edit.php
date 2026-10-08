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
    Alert::error(__('manage.member_edit.error.system'));
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
    Alert::error(__('manage.member_edit.error.member'));
    header('Location: /manage/system/' . $system_handle);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $pronouns = trim($_POST['pronouns']);
    $handle = trim($_POST['handle']);
    $color = trim($_POST['color']);

    if (empty($name)) {
        Alert::error(__('manage.member_edit.error.name'));
        header('Location: /manage/s/' . $system_handle . '/@' . $member_handle);
        exit;
    }

    if (empty($handle)) {
        Alert::error(__('manage.member_edit.error.handle'));
        header('Location: /manage/s/' . $system_handle . '/@' . $member_handle);
        exit;
    }

    if (!preg_match('/^[a-z0-9\-]+$/', $handle)) {
        Alert::error(__('manage.member_edit.error.handle_format'));
        header('Location: /manage/s/' . $system_handle . '/@' . $member_handle);
        exit;
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM members WHERE handle = ? AND system_id = ? AND id != ?');
    $stmt->execute([$handle, $system['id'], $member['id']]);
    if ($stmt->fetchColumn() > 0) {
        Alert::error(__('manage.member_edit.error.handle_exists'));
        header('Location: /manage/s/' . $system_handle . '/@' . $member_handle);
        exit;
    }

    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
        Alert::error(__('manage.member_edit.error.color'));
        header('Location: /manage/s/' . $system_handle . '/@' . $member_handle);
        exit;
    }

    $stmt = $pdo->prepare('UPDATE members SET name = ?, pronouns = ?, handle = ?, color = ? WHERE id = ?');
    $stmt->execute([$name, $pronouns, $handle, $color, $member['id']]);

    Alert::success(__('manage.member_edit.success'));
    header('Location: /manage/s/' . $system_handle . '/@' . $member_handle);
    exit;
}


?>
<!DOCTYPE html>
<html lang="<?= $language ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('manage.member_edit.page_title') ?></title>
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
                <h1><?= __('manage.member_edit.title', htmlspecialchars($member['handle'])) ?></h1>
                
                <form action="/manage/s/<?= htmlspecialchars($system['handle']) ?>/@<?= htmlspecialchars($member['handle']) ?>" method="POST">
                    <div class="form-group">
                        <label for="name"><?= __('manage.member_edit.name') ?></label>
                        <input type="text" id="name" name="name" value="<?= htmlspecialchars($member['name']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="pronouns"><?= __('manage.member_edit.pronouns') ?></label>
                        <input type="text" id="pronouns" name="pronouns" value="<?= htmlspecialchars($member['pronouns']) ?>">
                    </div>
                    <div class="form-group">
                        <label for="handle"><?= __('manage.member_edit.handle') ?></label>
                        <input type="text" id="handle" name="handle" value="<?= htmlspecialchars($member['handle']) ?>" required>
                        <small><?= __('manage.member_edit.handle_hint') ?></small>
                    </div>
                    <div class="form-group">
                        <input type="color" id="color" name="color" value="<?= htmlspecialchars($member['color']) ?>">
                        <label for="color"><?= __('manage.member_edit.color') ?></label>
                    </div>

                    <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
                    <button type="submit"><?= __('manage.member_edit.submit') ?></button>
                </form>
            </div>
        </div>
    </div>
</body>

</html>