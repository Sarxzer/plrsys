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
    Alert::error(__('manage.system_edit.error.system'));
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
            Alert::error(__('manage.system_edit.error.name_required'));
            header('Location: /manage/s/' . $system['handle']);
            exit;
        }

        if ($value === $system['name']) {
            Alert::info(__('manage.system_edit.info.name_unchanged'));
            header('Location: /manage/s/' . $system['handle']);
            exit;
        }

        $stmt = $pdo->prepare('UPDATE systems SET name = ? WHERE id = ?');
        $stmt->execute([$value, $systemId]);
        Alert::success(__('manage.system_edit.success.name'));
        $system['name'] = $value;
    } elseif ($field === 'handle') {
        $value = ltrim($value, '@');
        if ($value === '') {
            Alert::error(__('manage.system_edit.error.handle_required'));
            header('Location: /manage/s/' . $system['handle']);
            exit;
        }

        if (!preg_match('/^[a-z0-9\-]+$/', $value)) {
            Alert::error(__('manage.system_edit.error.handle_format'));
            header('Location: /manage/s/' . $system['handle']);
            exit;
        }

        if ($value === $system['handle']) {
            Alert::info(__('manage.system_edit.info.handle_unchanged'));
            header('Location: /manage/s/' . $system['handle']);
            exit;
        }

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM systems WHERE handle = ? AND id != ?');
        $stmt->execute([$value, $systemId]);
        if ((int) $stmt->fetchColumn() > 0) {
            Alert::error(__('manage.system_edit.error.handle_exists'));
            header('Location: /manage/s/' . $system['handle']);
            exit;
        }

        $stmt = $pdo->prepare('UPDATE systems SET handle = ? WHERE id = ?');
        $stmt->execute([$value, $systemId]);
        Alert::success(__('manage.system_edit.success.handle'));
        header('Location: /manage/s/' . $value);
        exit;
    } elseif ($field === 'visibility') {
        if (!in_array($value, ['public', 'private'], true)) {
            Alert::error(__('manage.system_edit.error.visibility'));
            header('Location: /manage/s/' . $system['handle']);
            exit;
        }

        $isPublic = $value === 'public' ? 1 : 0;
        if ((int) $system['is_public'] === $isPublic) {
            Alert::info(__('manage.system_edit.info.visibility_unchanged'));
            header('Location: /manage/s/' . $system['handle']);
            exit;
        }

        $stmt = $pdo->prepare('UPDATE systems SET is_public = ? WHERE id = ?');
        $stmt->execute([$isPublic, $systemId]);
        Alert::success(__('manage.system_edit.success.visibility'));
        $system['is_public'] = $isPublic;
    } else {
        Alert::error(__('manage.system_edit.error.invalid_request'));
    }

    header('Location: /manage/s/' . $system['handle']);
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM members WHERE system_id = ?');
$stmt->execute([(int) $system['id']]);
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="<?= $language ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('manage.system_edit.page_title', htmlspecialchars($system['name'])) ?></title>
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
                        <div class="header-badge"><?= __('manage.system_edit.badge') ?></div>
                        <div class="header-title inline-edit" data-inline-edit="name">
                            <button type="button" class="inline-edit-display" title="<?= __('manage.system_edit.edit_name') ?>"
                                aria-label="<?= __('manage.system_edit.edit_name_label') ?>">
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
                                    <button type="submit" class="inline-edit-save"><?= __('manage.system_edit.save') ?></button>
                                    <button type="button" class="inline-edit-cancel"><?= __('manage.system_edit.cancel') ?></button>
                                </div>
                            </form>
                        </div>
                        <div class="header-handle inline-edit" data-inline-edit="handle">
                            <button type="button" class="inline-edit-display" title="<?= __('manage.system_edit.edit_handle') ?>"
                                aria-label="<?= __('manage.system_edit.edit_handle_label') ?>">
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
                                    title="<?= __('manage.system_new.handle_title') ?>" required>
                                <div class="inline-edit-actions">
                                    <button type="submit" class="inline-edit-save"><?= __('manage.system_edit.save') ?></button>
                                    <button type="button" class="inline-edit-cancel"><?= __('manage.system_edit.cancel') ?></button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Info strip -->
                    <div class="system-info-strip">
                        <div class="info-chip"><?= __('manage.system_edit.members') ?> <span class="chip-val"><?= count($members) ?></span></div>
                        <div class="info-chip <?= $system['is_public'] ? 'public' : 'private' ?> inline-edit"
                            data-inline-edit="visibility">
                            <button type="button" class="inline-edit-display" title="<?= __('manage.system_edit.edit_visibility') ?>"
                                aria-label="<?= __('manage.system_edit.edit_visibility_label') ?>">
                                <?= __('manage.system_edit.visibility') ?> <span class="chip-val"><?= $system['is_public'] ? __('manage.system_edit.public') : __('manage.system_edit.private') ?></span>
                            </button>
                            <form class="inline-edit-form" method="POST"
                                action="/manage/s/<?= htmlspecialchars($system['handle']) ?>">
                                <input type="hidden" name="action" value="update-system">
                                <input type="hidden" name="field" value="visibility">
                                <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
                                <span class="inline-edit-label"><?= __('manage.system_edit.visibility') ?></span>
                                <select class="inline-edit-select" name="value">
                                    <option value="public" <?= $system['is_public'] ? 'selected' : '' ?>><?= __('manage.system_edit.public') ?></option>
                                    <option value="private" <?= !$system['is_public'] ? 'selected' : '' ?>><?= __('manage.system_edit.private') ?></option>
                                </select>
                                <div class="inline-edit-actions">
                                    <button type="submit" class="inline-edit-save"><?= __('manage.system_edit.save') ?></button>
                                    <button type="button" class="inline-edit-cancel"><?= __('manage.system_edit.cancel') ?></button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Members section -->
                    <div class="manage-system-section">
                        <div class="section-header">
                            <span class="section-label"><?= __('manage.system_edit.members_section') ?></span>
                            <span class="section-count"><?= count($members) ?> <?= __('manage.system_edit.total') ?></span>
                        </div>

                        <div class="member-list">
                            <?php if (empty($members)): ?>
                                <div class="empty-state">
                                    <span class="icon">◻</span>
                                    <div class="text"><?= __('manage.system_edit.no_members') ?></div>
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
                                <?= __('manage.system_edit.add_member') ?>
                            </a>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="manage-system-section">
                        <div class="section-header">
                            <span class="section-label"><?= __('manage.system_edit.actions') ?></span>
                        </div>
                        <div style="padding: 1rem 1.25rem;">
                            <div class="manage-actions">
                                <a href="/s/<?= htmlspecialchars($system['handle']) ?>" class="action-btn"><?= __('manage.system_edit.view_public') ?></a>
                                <a href="/dashboard" class="action-btn"><?= __('manage.system_edit.back_dashboard') ?></a>
                                <button class="action-btn danger" disabled title="<?= __('manage.system_edit.delete_soon') ?>"><?= __('manage.system_edit.delete') ?></button>
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