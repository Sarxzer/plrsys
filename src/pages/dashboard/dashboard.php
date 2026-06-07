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

$user_id = $_SESSION['user_id'];

// Get systems count
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM systems WHERE user_id = ?");
$stmt->execute([$user_id]);
$systems_count = $stmt->fetchColumn();

// Get total members
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM members m JOIN systems s ON m.system_id = s.id WHERE s.user_id = ?");
$stmt->execute([$user_id]);
$members_count = $stmt->fetchColumn();

// Get active session across all systems
$stmt = $pdo->prepare("
    SELECT fs.id, fs.system_id, fs.started_at, s.name as system_name,
           GROUP_CONCAT(m.name ORDER BY m.name SEPARATOR ', ') as member_names
    FROM fronting_sessions fs
    JOIN systems s ON fs.system_id = s.id
    LEFT JOIN fronting_session_members fsm ON fs.id = fsm.session_id
    LEFT JOIN members m ON fsm.member_id = m.id
    WHERE s.user_id = ? AND fs.ended_at IS NULL
    GROUP BY fs.id
    LIMIT 1
");
$stmt->execute([$user_id]);
$active_session = $stmt->fetch(PDO::FETCH_ASSOC);

// Get recent sessions (last 5)
$stmt = $pdo->prepare("
    SELECT fs.id, fs.started_at, fs.ended_at, s.name as system_name,
           GROUP_CONCAT(m.name ORDER BY m.name SEPARATOR ', ') as member_names,
           TIMESTAMPDIFF(SECOND, fs.started_at, COALESCE(fs.ended_at, NOW())) as duration_seconds
    FROM fronting_sessions fs
    JOIN systems s ON fs.system_id = s.id
    LEFT JOIN fronting_session_members fsm ON fs.id = fsm.session_id
    LEFT JOIN members m ON fsm.member_id = m.id
    WHERE s.user_id = ?
    GROUP BY fs.id
    ORDER BY fs.started_at DESC
    LIMIT 5
");
$stmt->execute([$user_id]);
$recent_sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

                <!-- Quick Stats -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-label">Systems</div>
                        <div class="stat-value"><?= $systems_count ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Members</div>
                        <div class="stat-value"><?= $members_count ?></div>
                    </div>
                </div>

                <!-- Active Session -->
                <?php if ($active_session): ?>
                    <div class="widget active-session-widget">
                        <h2>Currently Fronting</h2>
                        <div class="session-details">
                            <p><strong>System:</strong> <?= htmlspecialchars($active_session['system_name']) ?></p>
                            <p><strong>Members:</strong> <?= htmlspecialchars($active_session['member_names'] ?? 'Unknown') ?></p>
                            <p><strong>Started:</strong> <?= date('g:i A', strtotime($active_session['started_at'])) ?></p>
                            <p><strong>Duration:</strong> <span class="duration-display" data-started="<?= $active_session['started_at'] ?>"></span></p>
                        </div>
                        <a href="/fronting?system=<?= $active_session['system_id'] ?>" class="btn btn-primary">Manage Session</a>
                    </div>
                <?php endif; ?>

                <!-- Recent Sessions -->
                <?php if (!empty($recent_sessions)): ?>
                    <div class="widget recent-sessions-widget">
                        <h2>Recent Sessions</h2>
                        <div class="sessions-list">
                            <?php foreach ($recent_sessions as $session): ?>
                                <div class="session-item">
                                    <div class="session-header">
                                        <strong><?= htmlspecialchars($session['member_names'] ?? 'Unknown') ?></strong>
                                        <span class="system-badge"><?= htmlspecialchars($session['system_name']) ?></span>
                                    </div>
                                    <div class="session-meta">
                                        <span><?= date('M d, g:i A', strtotime($session['started_at'])) ?></span>
                                        <span>
                                            <?php
                                                $seconds = $session['duration_seconds'];
                                                $hours = intdiv($seconds, 3600);
                                                $minutes = intdiv($seconds % 3600, 60);
                                                echo ($hours > 0 ? $hours . 'h ' : '') . $minutes . 'm';
                                            ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <a href="/history" class="btn btn-secondary btn-small">View All History</a>
                    </div>
                <?php endif; ?>

                <!-- Quick Actions -->
                <div class="quick-actions">
                    <h2>Quick Actions</h2>
                    <div class="button-grid">
                        <a href="/fronting" class="btn btn-primary">Start Session</a>
                        <a href="/systems" class="btn btn-secondary">View Systems</a>
                        <a href="/history" class="btn btn-secondary">Session History</a>
                        <a href="/settings" class="btn btn-secondary">Settings</a>
                    </div>
                </div>
            </div>

            <?php include $includesDir . '/footer.php'; ?>
        </div>
    </div>

</body>
</html>