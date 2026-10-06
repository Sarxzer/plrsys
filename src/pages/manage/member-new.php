<?php
/**
 * @var array $parts
 * @var PDO $pdo
 * @var string $includesDir
 * @var string $cssDir
 * @var string $jsDir
 */

Guards::requireLogin();

$auth = new Auth($pdo);
$currentUser = $auth->requireCurrentUser();
$currentUserId = (int) $currentUser['id'];

$handle = $parts[2] ?? null;

$stmt = $pdo->prepare('SELECT * FROM systems WHERE handle = ?');
$stmt->execute([$handle]);
$system = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$system) {
    Alert::error(__('manage.member_new.error.system'));
    header('Location: /dashboard');
    exit;
}

Guards::requireSystemOwnership($pdo, (int) $system['id']);


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $memberHandle = trim($_POST['handle']);
    $memberName = trim($_POST['name']);
    $memberPronouns = trim($_POST['pronouns']);
    $memberRole = trim($_POST['role']);
    $memberColor = trim($_POST['color']);

    if (empty($memberHandle) || empty($memberName)) {
        Alert::error(__('manage.member_new.error.required'));
        header('Location: /manage/s/' . htmlspecialchars($handle) . '/new');
        exit;
    }

    if (!preg_match('/^[a-z0-9\-]+$/', $memberHandle)) {
        Alert::error(__('manage.member_new.error.handle_format'));
        header('Location: /manage/s/' . htmlspecialchars($handle) . '/new');
        exit;
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM members WHERE system_id = ? AND handle = ?');
    $stmt->execute([$system['id'], $memberHandle]);
    if ($stmt->fetchColumn() > 0) {
        Alert::error(__('manage.member_new.error.handle_exists'));
        header('Location: /manage/s/' . htmlspecialchars($handle) . '/new');
        exit;
    }

    $stmt = $pdo->prepare('INSERT INTO members (system_id, handle, name, pronouns, role, color) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$system['id'], $memberHandle, $memberName, $memberPronouns, $memberRole, $memberColor]);

    header('Location: /s/' . htmlspecialchars($handle) . '/@' . htmlspecialchars($memberHandle));
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('manage.member_new.page_title') ?></title>
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
                <div class="member-form-container">

                    <div class="member-form-header">
                        <div class="member-form-badge"><?= htmlspecialchars($system['name']) ?></div>
                        <div class="member-form-title"><?= __('manage.member_new.title') ?></div>
                        <div class="member-form-subtitle"><?= __('manage.member_new.subtitle') ?></div>
                    </div>

                    <form action="/manage/s/<?= htmlspecialchars($handle) ?>/new" method="post"
                        class="member-form-card">

                        <!-- Handle -->
                        <div class="field-group">
                            <label class="field-label" for="handle"><?= __('manage.member_new.handle') ?> <span
                                    class="required-star">*</span></label>
                            <div class="handle-wrapper">
                                <span class="handle-at">@</span>
                                <input type="text" id="handle" name="handle" class="handle-input"
                                    placeholder="<?= __('manage.member_new.handle_placeholder') ?>" pattern="[a-z0-9\-]+"
                                    title="<?= __('manage.member_new.handle_title') ?>" autocomplete="off" required>
                            </div>
                            <span class="field-hint"><?= __('manage.member_new.handle_hint') ?></span>
                        </div>

                        <hr class="form-divider">

                        <!-- Name -->
                        <div class="field-group">
                            <label class="field-label" for="name"><?= __('manage.member_new.name') ?> <span
                                    class="required-star">*</span></label>
                            <input type="text" id="name" name="name" class="field-input" placeholder="<?= __('manage.member_new.name_placeholder') ?>"
                                required>
                        </div>

                        <!-- Pronouns -->
                        <div class="field-group">
                            <label class="field-label" for="pronouns"><?= __('manage.member_new.pronouns') ?></label>
                            <input type="text" id="pronouns" name="pronouns" class="field-input"
                                placeholder="<?= __('manage.member_new.pronouns_placeholder') ?>">
                        </div>

                        <!-- Role -->
                        <div class="field-group">
                            <label class="field-label" for="role"><?= __('manage.member_new.role') ?></label>
                            <input type="text" id="role" name="role" class="field-input" placeholder="<?= __('manage.member_new.role_placeholder') ?>">
                        </div>

                        <hr class="form-divider">

                        <!-- Color -->
                        <div class="field-group">
                            <label class="field-label" for="color"><?= __('manage.member_new.color') ?></label>
                            <div class="color-wrapper">
                                <input type="color" id="color" name="color" class="color-picker" value="#a3c4f3">
                                <div class="color-dot-preview" id="color-dot"></div>
                                <span class="color-hex-display" id="color-hex">#a3c4f3</span>
                            </div>
                        </div>

                        <hr class="form-divider">

                        <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
                        <button type="submit" class="member-submit"><?= __('manage.member_new.submit') ?></button>
                    </form>

                    <div class="member-form-footer">
                        <a href="/manage/s/<?= htmlspecialchars($handle) ?>"><?= __('manage.member_new.back') ?></a>
                    </div>

                </div>
            </div>

            <?php include $includesDir . '/footer.php'; ?>
        </div>
    </div>

    <script>
        const colorInput = document.getElementById('color');
        const colorHex = document.getElementById('color-hex');
        const colorDot = document.getElementById('color-dot');

        function updateColor(val) {
            colorHex.textContent = val;
            colorHex.style.color = val;
            colorDot.style.backgroundColor = val;
            colorDot.style.boxShadow = `0 0 6px ${val}`;
        }

        updateColor(colorInput.value);

        colorInput.addEventListener('input', () => updateColor(colorInput.value));
    </script>
</body>

</html>