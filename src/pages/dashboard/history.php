<?php

/**
 * @var array $parts
 * @var PDO $pdo
 * @var string $includesDir
 * @var string $cssDir
 * @var string $jsDir
 */

Guards::requireLogin();

$user_id = $_SESSION['user_id'];

// Get user's systems
$stmt = $pdo->prepare("SELECT id, name FROM systems WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$systems = $stmt->fetchAll(PDO::FETCH_ASSOC);

$system_id = $_GET['system'] ?? ($systems[0]['id'] ?? null);
$sort = $_GET['sort'] ?? 'newest'; // newest, oldest, longest, shortest
$search = $_GET['search'] ?? '';

if (!$system_id) {
    Alert::error(__('history.error.no_system'));
}

// Build query for history
$query = "
    SELECT 
        fs.id,
        fs.started_at,
        fs.ended_at,
        fs.note,
        GROUP_CONCAT(m.name SEPARATOR ', ') as member_names,
        ROUND(TIMESTAMPDIFF(SECOND, fs.started_at, COALESCE(fs.ended_at, NOW())) / 3600, 2) as duration_hours,
        TIMESTAMPDIFF(SECOND, fs.started_at, COALESCE(fs.ended_at, NOW())) as duration_seconds
    FROM fronting_sessions fs
    LEFT JOIN fronting_session_members fsm ON fs.id = fsm.session_id
    LEFT JOIN members m ON fsm.member_id = m.id
    WHERE fs.system_id = ?
";

$params = [$system_id];

// Add search filter
if (!empty($search)) {
    $query .= " AND (m.name LIKE ? OR fs.note LIKE ?)";
    $search_term = '%' . $search . '%';
    $params[] = $search_term;
    $params[] = $search_term;
}

// Add sorting
$query .= " GROUP BY fs.id";

if ($sort === 'newest') {
    $query .= " ORDER BY fs.started_at DESC";
} elseif ($sort === 'oldest') {
    $query .= " ORDER BY fs.started_at ASC";
} elseif ($sort === 'longest') {
    $query .= " ORDER BY duration_seconds DESC";
} elseif ($sort === 'shortest') {
    $query .= " ORDER BY duration_seconds ASC";
}

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="<?= $language ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('history.page_title') ?></title>
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
                <h1><?= __('history.title') ?></h1>

                <?php if (empty($systems)): ?>
                    <div class="alert-box alert-error">
                        <p><?= __('history.no_systems') ?> <a href="/manage/systems"><?= __('history.create_system') ?></a> <?= __('history.start_tracking') ?></p>
                    </div>
                <?php else: ?>

                    <!-- System Selector -->
                    <div class="history-filters">
                        <div class="filter-group system-selector">
                            <label for="system-select"><?= __('history.select_system') ?></label>
                            <select id="system-select" onchange="window.location.href = '/history?system=' + this.value">
                                <?php foreach ($systems as $system): ?>
                                    <option value="<?= $system['id'] ?>" <?= $system['id'] == $system_id ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($system['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Search -->
                        <form method="get" class="filter-group search-form">
                            <input type="hidden" name="system" value="<?= $system_id ?>">
                            <input type="search" name="search" placeholder="<?= __('history.search_placeholder') ?>" value="<?= htmlspecialchars($search) ?>" class="search-input">
                            <button type="submit" class="btn btn-small"><?= __('history.search') ?></button>
                            <?php if (!empty($search)): ?>
                                <a href="/history?system=<?= $system_id ?>" class="btn btn-small btn-secondary"><?= __('history.clear') ?></a>
                            <?php endif; ?>
                        </form>

                        <!-- Sort -->
                        <div class="filter-group sort-selector">
                            <label for="sort-select"><?= __('history.sort_by') ?></label>
                            <select id="sort-select" onchange="window.location.href = '/history?system=<?= $system_id ?>&sort=' + this.value + (window.location.search.includes('search=') ? '&search=' + new URLSearchParams(window.location.search).get('search') : '')">
                                <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>><?= __('history.newest') ?></option>
                                <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>><?= __('history.oldest') ?></option>
                                <option value="longest" <?= $sort === 'longest' ? 'selected' : '' ?>><?= __('history.longest') ?></option>
                                <option value="shortest" <?= $sort === 'shortest' ? 'selected' : '' ?>><?= __('history.shortest') ?></option>
                            </select>
                        </div>
                    </div>

                    <!-- Sessions List -->
                    <div class="sessions-list">
                        <?php if (empty($sessions)): ?>
                            <div class="no-sessions">
                                <p><?= __('history.no_sessions') ?></p>
                                <?php if (!empty($search)): ?>
                                    <p><a href="/history?system=<?= $system_id ?>"><?= __('history.clear') ?></a> <?= __('history.clear_to_see_all') ?></p>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <?php foreach ($sessions as $session): ?>
                                <div class="session-card">
                                    <div class="session-header">
                                        <div class="session-members">
                                            <strong><?= htmlspecialchars($session['member_names'] ?? __('fronting.unknown')) ?></strong>
                                        </div>
                                        <div class="session-dates">
                                            <span class="date"><?= date('M d, Y', strtotime($session['started_at'])) ?></span>
                                        </div>
                                    </div>

                                    <div class="session-body">
                                        <div class="time-info">
                                            <div class="start-time">
                                                <strong><?= __('history.started') ?></strong>
                                                <span><?= date('g:i A', strtotime($session['started_at'])) ?></span>
                                            </div>
                                            <div class="end-time">
                                                <strong><?= __('history.ended') ?></strong>
                                                <span><?= $session['ended_at'] ? date('g:i A', strtotime($session['ended_at'])) : __('history.ongoing') ?></span>
                                            </div>
                                            <div class="duration">
                                                <strong><?= __('history.duration') ?></strong>
                                                <?php if (!$session['ended_at']): ?>
                                                    <span class="duration-display" data-started="<?= $session['started_at'] ?>" data-hours-label="<?= __('dashboard.duration.hours', '') ?>" data-minutes-label="<?= __('dashboard.duration.minutes', '') ?>" data-seconds-label="<?= __('dashboard.duration.seconds', '') ?>">hi :3</span>
                                                <?php else: ?>
                                                    <span><?php
                                                        $seconds = $session['duration_seconds'];
                                                        $hours = floor($seconds / 3600);
                                                        $minutes = floor(($seconds % 3600) / 60);
                                                        $secs = $seconds % 60;
                                                        
                                                        $duration_str = '';
                                                        if ($hours > 0) $duration_str .= __('dashboard.duration.hours', $hours) . ' ';
                                                        if ($minutes > 0) $duration_str .= __('dashboard.duration.minutes', $minutes) . ' ';
                                                        $duration_str .= __('dashboard.duration.seconds', $secs);
                                                        
                                                        echo htmlspecialchars($duration_str);
                                                    ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <?php if (!empty($session['note'])): ?>
                                            <div class="session-note">
                                                <strong><?= __('history.notes') ?></strong>
                                                <p><?= htmlspecialchars($session['note']) ?></p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div class="history-stats">
                        <h3><?= __('history.statistics') ?></h3>
                        <div class="stats-grid">
                            <div class="stat">
                                <span class="stat-label"><?= __('history.total_sessions') ?></span>
                                <span class="stat-value"><?= count($sessions) ?></span>
                            </div>
                            <?php if (!empty($sessions)): ?>
                                <div class="stat">
                                    <span class="stat-label"><?= __('history.total_time') ?></span>
                                    <span class="stat-value">
                                        <?php
                                            $total_seconds = array_sum(array_column($sessions, 'duration_seconds'));
                                            $total_hours = floor($total_seconds / 3600);
                                            $total_minutes = floor(($total_seconds % 3600) / 60);
                                            echo __('dashboard.duration.hours', $total_hours) . ' ' . __('dashboard.duration.minutes', $total_minutes);
                                        ?>
                                    </span>
                                </div>
                                <div class="stat">
                                    <span class="stat-label"><?= __('history.average_duration') ?></span>
                                    <span class="stat-value">
                                        <?php
                                            $avg_seconds = $total_seconds / count($sessions);
                                            $avg_hours = floor($avg_seconds / 3600);
                                            $avg_minutes = floor((int)($avg_seconds % 3600) / 60);
                                            echo __('dashboard.duration.hours', $avg_hours) . ' ' . __('dashboard.duration.minutes', $avg_minutes);
                                        ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                <?php endif; ?>

                <a href="/fronting" class="btn-secondary"><?= __('history.start_session') ?></a>
                <a href="/dashboard" class="btn-secondary"><?= __('history.back_to_dashboard') ?></a>
            </div>

            <?php include $includesDir . '/footer.php'; ?>
        </div>
    </div>
</body>

</html>
