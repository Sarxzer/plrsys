<?php

/**
 * @var PDO $pdo
 * @var string $includesDir
 * @var string $cssDir
 * @var string $jsDir
 * @var array $current_user
 */

Guards::requireLogin();

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update-system') {
    $field = $_POST['field'] ?? '';
    $value = trim((string) ($_POST['value'] ?? ''));
    $systemId = (int) $system['id'];

    if ($field === 'name') {
        if ($value === '') {
            Alert::error('System name is required.');
            header('Location: /manage/s/' . $system['handle']);
            exit;
        }

        if ($value === $system['name']) {
            Alert::info('System name is unchanged.');
            header('Location: /manage/s/' . $system['handle']);
            exit;
        }

        $stmt = $pdo->prepare('UPDATE systems SET name = ? WHERE id = ?');
        $stmt->execute([$value, $systemId]);
        Alert::success('System name updated.');
        $system['name'] = $value;
    } elseif ($field === 'handle') {
        $value = ltrim($value, '@');
        if ($value === '') {
            Alert::error('System handle is required.');
            header('Location: /manage/s/' . $system['handle']);
            exit;
        }

        if (!preg_match('/^[a-z0-9\-]+$/', $value)) {
            Alert::error('Handle can only contain lowercase letters, numbers, and hyphens.');
            header('Location: /manage/s/' . $system['handle']);
            exit;
        }

        if ($value === $system['handle']) {
            Alert::info('System handle is unchanged.');
            header('Location: /manage/s/' . $system['handle']);
            exit;
        }

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM systems WHERE handle = ? AND id != ?');
        $stmt->execute([$value, $systemId]);
        if ((int) $stmt->fetchColumn() > 0) {
            Alert::error('Handle already exists. Please choose a different one.');
            header('Location: /manage/s/' . $system['handle']);
            exit;
        }

        $stmt = $pdo->prepare('UPDATE systems SET handle = ? WHERE id = ?');
        $stmt->execute([$value, $systemId]);
        Alert::success('System handle updated.');
        header('Location: /manage/s/' . $value);
        exit;
    } elseif ($field === 'visibility') {
        if (!in_array($value, ['public', 'private'], true)) {
            Alert::error('Visibility must be public or private.');
            header('Location: /manage/s/' . $system['handle']);
            exit;
        }

        $isPublic = $value === 'public' ? 1 : 0;
        if ((int) $system['is_public'] === $isPublic) {
            Alert::info('Visibility is unchanged.');
            header('Location: /manage/s/' . $system['handle']);
            exit;
        }

        $stmt = $pdo->prepare('UPDATE systems SET is_public = ? WHERE id = ?');
        $stmt->execute([$isPublic, $systemId]);
        Alert::success('Visibility updated.');
        $system['is_public'] = $isPublic;
    } else {
        Alert::error('Invalid update request.');
    }

    header('Location: /manage/s/' . $system['handle']);
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM members WHERE system_id = ?');
$stmt->execute([(int) $system['id']]);
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage <?= htmlspecialchars($system['name']) ?> | Innerspace</title>
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
                <div class="manage-system-container">

                    <!-- Header -->
                    <div class="manage-system-header">
                        <div class="header-badge">MANAGE SYSTEM</div>
                        <div class="header-title inline-edit" data-inline-edit="name">
                            <button type="button" class="inline-edit-display" title="Click to edit name"
                                aria-label="Edit system name">
                                <?= htmlspecialchars($system['name']) ?>
                            </button>
                            <form class="inline-edit-form" method="POST"
                                action="/manage/s/<?= htmlspecialchars($system['handle']) ?>">
                                <input type="hidden" name="action" value="update-system">
                                <input type="hidden" name="field" value="name">
                                <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
                                <input class="inline-edit-input" type="text" name="value"
                                    value="<?= htmlspecialchars($system['name']) ?>" required>
                                <div class="inline-edit-actions">
                                    <button type="submit" class="inline-edit-save">Save</button>
                                    <button type="button" class="inline-edit-cancel">Cancel</button>
                                </div>
                            </form>
                        </div>
                        <div class="header-handle inline-edit" data-inline-edit="handle">
                            <button type="button" class="inline-edit-display" title="Click to edit handle"
                                aria-label="Edit system handle">
                                <span>@</span><?= htmlspecialchars($system['handle']) ?>
                            </button>
                            <form class="inline-edit-form" method="POST"
                                action="/manage/s/<?= htmlspecialchars($system['handle']) ?>">
                                <input type="hidden" name="action" value="update-system">
                                <input type="hidden" name="field" value="handle">
                                <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
                                <span class="inline-edit-prefix">@</span>
                                <input class="inline-edit-input" type="text" name="value"
                                    value="<?= htmlspecialchars($system['handle']) ?>" pattern="[a-z0-9\-]+"
                                    title="Lowercase letters, numbers, and hyphens only." required>
                                <div class="inline-edit-actions">
                                    <button type="submit" class="inline-edit-save">Save</button>
                                    <button type="button" class="inline-edit-cancel">Cancel</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Info strip -->
                    <div class="system-info-strip">
                        <div class="info-chip">members <span class="chip-val"><?= count($members) ?></span></div>
                        <div class="info-chip <?= $system['is_public'] ? 'public' : 'private' ?> inline-edit"
                            data-inline-edit="visibility">
                            <button type="button" class="inline-edit-display" title="Click to edit visibility"
                                aria-label="Edit visibility">
                                visibility <span class="chip-val"><?= $system['is_public'] ? 'public' : 'private' ?></span>
                            </button>
                            <form class="inline-edit-form" method="POST"
                                action="/manage/s/<?= htmlspecialchars($system['handle']) ?>">
                                <input type="hidden" name="action" value="update-system">
                                <input type="hidden" name="field" value="visibility">
                                <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
                                <span class="inline-edit-label">visibility</span>
                                <select class="inline-edit-select" name="value">
                                    <option value="public" <?= $system['is_public'] ? 'selected' : '' ?>>public</option>
                                    <option value="private" <?= !$system['is_public'] ? 'selected' : '' ?>>private</option>
                                </select>
                                <div class="inline-edit-actions">
                                    <button type="submit" class="inline-edit-save">Save</button>
                                    <button type="button" class="inline-edit-cancel">Cancel</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Members section -->
                    <div class="manage-system-section">
                        <div class="section-header">
                            <span class="section-label">// Members</span>
                            <span class="section-count"><?= count($members) ?> total</span>
                        </div>

                        <div class="member-list">
                            <?php if (empty($members)): ?>
                                <div class="empty-state">
                                    <span class="icon">◻</span>
                                    <div class="text">no members yet — add the first one</div>
                                </div>
                            <?php else: ?>
                                <?php foreach ($members as $member): ?>
                                    <a href="/manage/s/<?= htmlspecialchars($system['handle']) ?>/@<?= htmlspecialchars($member['handle']) ?>"
                                        class="member-row">
                                        <div class="dot"
                                            style="--color-dot: <?= htmlspecialchars($member['color'] ?? '#9d9ab5') ?>;">
                                        </div>
                                        <div class="info">
                                            <div class="name"><?= htmlspecialchars($member['name']) ?></div>
                                            <div class="meta">
                                                <span class="handle">@<?= htmlspecialchars($member['handle']) ?></span>
                                                <?php if (!empty($member['pronouns'])): ?>
                                                    <span class="sep">·</span><?= htmlspecialchars($member['pronouns']) ?>
                                                <?php endif; ?>
                                                <?php if (!empty($member['role'])): ?>
                                                    <span class="sep">·</span><?= htmlspecialchars($member['role']) ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <span class="arrow">[→]</span>
                                    </a>
                                <?php endforeach; ?>
                            <?php endif; ?>

                            <a href="/manage/s/<?= htmlspecialchars($system['handle']) ?>/new" class="add-member-row">
                                <div class="plus-icon">+</div>
                                Add new member
                            </a>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="manage-system-section">
                        <div class="section-header">
                            <span class="section-label">// Actions</span>
                        </div>
                        <div style="padding: 1rem 1.25rem;">
                            <div class="manage-actions">
                                <a href="/s/<?= htmlspecialchars($system['handle']) ?>" class="action-btn">View public
                                    page →</a>
                                <a href="/dashboard" class="action-btn">← Back to dashboard</a>
                                <button class="action-btn danger" disabled title="Coming soon">Delete
                                    system</button>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <?php include $includesDir . '/footer.php'; ?>
        </div>
    </div>
</body>

</html>