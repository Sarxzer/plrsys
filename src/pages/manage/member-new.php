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
    Alert::error("System not found or you don't have permission to manage it.");
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
        Alert::error("Member handle and name are required.");
        header('Location: /manage/s/' . htmlspecialchars($handle) . '/new');
        exit;
    }

    if (!preg_match('/^[a-z0-9\-]+$/', $memberHandle)) {
        Alert::error("Member handle can only contain lowercase letters, numbers, and hyphens.");
        header('Location: /manage/s/' . htmlspecialchars($handle) . '/new');
        exit;
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM members WHERE system_id = ? AND handle = ?');
    $stmt->execute([$system['id'], $memberHandle]);
    if ($stmt->fetchColumn() > 0) {
        Alert::error("Member handle already exists in this system. Please choose a different one.");
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
    <title>New Member | plrsys</title>
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
                        <div class="member-form-title">New Member</div>
                        <div class="member-form-subtitle">Add someone to your system</div>
                    </div>

                    <form action="/manage/s/<?= htmlspecialchars($handle) ?>/new" method="post"
                        class="member-form-card">

                        <!-- Handle -->
                        <div class="field-group">
                            <label class="field-label" for="handle">// handle <span
                                    class="required-star">*</span></label>
                            <div class="handle-wrapper">
                                <span class="handle-at">@</span>
                                <input type="text" id="handle" name="handle" class="handle-input"
                                    placeholder="e.g. nova" pattern="[a-z0-9\-]+"
                                    title="Lowercase letters, numbers, and hyphens only." autocomplete="off" required>
                            </div>
                            <span class="field-hint">lowercase, numbers, hyphens only</span>
                        </div>

                        <hr class="form-divider">

                        <!-- Name -->
                        <div class="field-group">
                            <label class="field-label" for="name">// display name <span
                                    class="required-star">*</span></label>
                            <input type="text" id="name" name="name" class="field-input" placeholder="e.g. Nova"
                                required>
                        </div>

                        <!-- Pronouns -->
                        <div class="field-group">
                            <label class="field-label" for="pronouns">// pronouns</label>
                            <input type="text" id="pronouns" name="pronouns" class="field-input"
                                placeholder="e.g. they/them">
                        </div>

                        <!-- Role -->
                        <div class="field-group">
                            <label class="field-label" for="role">// role</label>
                            <input type="text" id="role" name="role" class="field-input" placeholder="e.g. protector">
                        </div>

                        <hr class="form-divider">

                        <!-- Color -->
                        <div class="field-group">
                            <label class="field-label" for="color">// color</label>
                            <div class="color-wrapper">
                                <input type="color" id="color" name="color" class="color-picker" value="#a3c4f3">
                                <div class="color-dot-preview" id="color-dot"></div>
                                <span class="color-hex-display" id="color-hex">#a3c4f3</span>
                            </div>
                        </div>

                        <hr class="form-divider">

                        <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
                        <button type="submit" class="member-submit">Create Member →</button>
                    </form>

                    <div class="member-form-footer">
                        <a href="/manage/s/<?= htmlspecialchars($handle) ?>">← back to system</a>
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