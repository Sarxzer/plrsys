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

$system_id = $_POST['system_id'] ?? $_GET['system'] ?? ($systems[0]['id'] ?? null);
$system_id = $system_id ? (int) $system_id : null;

// Validate system belongs to user
if ($system_id) {
    $stmt = $pdo->prepare("SELECT id FROM systems WHERE id = ? AND user_id = ?");
    $stmt->execute([$system_id, $user_id]);
    if (!$stmt->fetch()) {
        $system_id = null;
        Alert::error("Invalid system.");
    }
}

if (!$system_id && !empty($systems)) {
    Alert::error("No system selected.");
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();

    $action = $_POST['action'] ?? null;

    if ($action === 'start_session' && $system_id) {
        $member_ids = $_POST['members'] ?? [];

        if (empty($member_ids)) {
            Alert::error("Please select at least one member.");
        } else {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("INSERT INTO fronting_sessions (system_id, note) VALUES (?, ?)");
                $stmt->execute([$system_id, $_POST['note'] ?? '']);
                $session_id = $pdo->lastInsertId();

                $stmt = $pdo->prepare("INSERT INTO fronting_session_members (session_id, member_id) VALUES (?, ?)");
                foreach ($member_ids as $mid) {
                    $stmt->execute([$session_id, (int) $mid]);
                }

                $pdo->commit();
                Alert::success("Fronting session started.");
            } catch (Exception $e) {
                $pdo->rollBack();
                Alert::error("Failed to start session.");
            }
        }
    } elseif ($action === 'end_session' && $system_id) {
        $session_id = (int) ($_POST['session_id'] ?? 0);

        if (!$session_id) {
            Alert::error("Invalid session.");
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE fronting_sessions SET ended_at = NOW() WHERE id = ? AND system_id = ?");
                $stmt->execute([$session_id, $system_id]);
                Alert::success("Fronting session ended.");
            } catch (Exception $e) {
                Alert::error("Failed to end session.");
            }
        }
    } elseif ($action === 'update_members' && $system_id) {
        $session_id = (int) ($_POST['session_id'] ?? 0);
        $new_member_ids = $_POST['members'] ?? [];
        $new_member_ids = array_map('intval', $new_member_ids);

        if (!$session_id) {
            Alert::error("Invalid session.");
        } elseif (empty($new_member_ids)) {
            Alert::error("Please select at least one member.");
        } else {
            try {
                // Get current members
                $stmt = $pdo->prepare("
                    SELECT member_id FROM fronting_session_members WHERE session_id = ?
                ");
                $stmt->execute([$session_id]);
                $current_members = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'member_id');

                // Check if members actually changed
                $added = array_diff($new_member_ids, $current_members);
                $removed = array_diff($current_members, $new_member_ids);

                if (empty($added) && empty($removed)) {
                    Alert::error("No changes made.");
                } else {
                    $pdo->beginTransaction();

                    // Get current session note
                    $stmt = $pdo->prepare("SELECT note FROM fronting_sessions WHERE id = ?");
                    $stmt->execute([$session_id]);
                    $old_note = $stmt->fetchColumn();

                    // Build change message
                    $change_msg = "Member change: ";
                    $changes = [];

                    if (!empty($removed)) {
                        $stmt = $pdo->prepare("SELECT name FROM members WHERE id IN (" . implode(',', $removed) . ")");
                        $stmt->execute();
                        $removed_names = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'name');
                        $changes[] = "removed " . implode(', ', $removed_names);
                    }

                    if (!empty($added)) {
                        $stmt = $pdo->prepare("SELECT name FROM members WHERE id IN (" . implode(',', $added) . ")");
                        $stmt->execute();
                        $added_names = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'name');
                        $changes[] = "added " . implode(', ', $added_names);
                    }

                    $change_msg .= implode(', ', $changes);

                    // End current session with change note
                    $stmt = $pdo->prepare("UPDATE fronting_sessions SET ended_at = NOW(), note = ? WHERE id = ? AND system_id = ?");
                    $stmt->execute([$change_msg, $session_id, $system_id]);

                    // Start new session with updated members
                    $stmt = $pdo->prepare("INSERT INTO fronting_sessions (system_id, note) VALUES (?, ?)");
                    $stmt->execute([$system_id, $old_note ?? '']);
                    $new_session_id = $pdo->lastInsertId();

                    $stmt = $pdo->prepare("INSERT INTO fronting_session_members (session_id, member_id) VALUES (?, ?)");
                    foreach ($new_member_ids as $mid) {
                        $stmt->execute([$new_session_id, $mid]);
                    }

                    $pdo->commit();
                    Alert::success("Session members updated.");
                }
            } catch (Exception $e) {
                $pdo->rollBack();
                Alert::error("Failed to update session.");
            }
        }
    } elseif ($action === 'update_note' && $system_id) {
    $session_id = (int)($_POST['session_id'] ?? 0);
    $note = trim($_POST['note'] ?? '');

    if (!$session_id) {
        Alert::error("Invalid session.");
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE fronting_sessions SET note = ? WHERE id = ? AND system_id = ?");
            $stmt->execute([$note, $session_id, $system_id]);
            Alert::success("Note saved.");
        } catch (Exception $e) {
            Alert::error("Failed to save note.");
        }
    }
}

    // PRG pattern — redirect to avoid form resubmission on refresh
    header("Location: /fronting?system=" . $system_id);
    exit;
}

// Get active fronting session
$active_session = null;
if ($system_id) {
    $stmt = $pdo->prepare("
        SELECT fs.id, fs.started_at, fs.note,
               GROUP_CONCAT(m.name ORDER BY m.name SEPARATOR ', ') as member_names,
               GROUP_CONCAT(m.id ORDER BY m.name SEPARATOR ',') as member_ids
        FROM fronting_sessions fs
        LEFT JOIN fronting_session_members fsm ON fs.id = fsm.session_id
        LEFT JOIN members m ON fsm.member_id = m.id
        WHERE fs.system_id = ? AND fs.ended_at IS NULL
        GROUP BY fs.id
        LIMIT 1
    ");
    $stmt->execute([$system_id]);
    $active_session = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($active_session && !empty($active_session['member_ids'])) {
        $active_session['member_ids'] = array_map('intval', explode(',', $active_session['member_ids']));
    } else if ($active_session) {
        $active_session['member_ids'] = [];
    }
}

// Get members
$members = [];
if ($system_id) {
    $stmt = $pdo->prepare("SELECT id, name, color FROM members WHERE system_id = ? ORDER BY name");
    $stmt->execute([$system_id]);
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Duration helper
function formatDuration(int $seconds): string
{
    $hours = intdiv($seconds, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    $secs = $seconds % 60;

    $parts = [];
    if ($hours > 0)
        $parts[] = $hours . 'h';
    if ($minutes > 0)
        $parts[] = $minutes . 'm';
    $parts[] = $secs . 's';
    return implode(' ', $parts);
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fronting | plrsys</title>
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
                <h1>Fronting Sessions</h1>

                <?php if (empty($systems)): ?>
                    <div class="alert-box alert-error">
                        <p>You don't have any systems yet. <a href="/manage/systems">Create a system</a> to start tracking
                            fronting sessions.</p>
                    </div>
                <?php else: ?>

                    <!-- System Selector -->
                    <form method="GET" action="/fronting" class="system-selector">
                        <label for="system-select">Select System:</label>
                        <select id="system-select" name="system" onchange="this.form.submit()"
                            disabled="<?= count($systems) === 1 ? 'disabled' : '' ?>">
                            <?php foreach ($systems as $system): ?>
                                <option value="<?= $system['id'] ?>" <?= $system['id'] == $system_id ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($system['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <noscript><button type="submit">Go</button></noscript>
                    </form>

                    <!-- Active Session -->
                    <?php if ($active_session): ?>
                        <div class="active-session">
                            <h2>Currently Fronting</h2>
                            <div class="session-info">
                                <div class="info-item info-members">
                                    <strong>Members</strong>
                                    <span><?= htmlspecialchars($active_session['member_names'] ?? 'Unknown') ?></span>
                                </div>
                                <div class="info-item info-time">
                                    <strong>Started</strong>
                                    <span><?= date('g:i A', strtotime($active_session['started_at'])) ?></span>
                                </div>
                                <div class="info-item info-duration">
                                    <strong>Duration</strong>
                                    <?php if (!$active_session['started_at']): ?>
                                        <span>Unknown</span>
                                    <?php else: ?>
                                        <span class="duration-display" data-started="<?= $active_session['started_at'] ?>"></span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Adjust Members -->
                            <form method="POST" action="/fronting" class="session-subform">
                                <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
                                <input type="hidden" name="action" value="update_members">
                                <input type="hidden" name="system_id" value="<?= $system_id ?>">
                                <input type="hidden" name="session_id" value="<?= $active_session['id'] ?>">

                                <div class="subform-label">Adjust Members</div>
                                <div class="member-selector">
                                    <?php foreach ($members as $member): ?>
                                        <?php $checked = in_array($member['id'], $active_session['member_ids'] ?? []); ?>
                                        <label class="member-tile">
                                            <input type="checkbox" name="members[]" value="<?= $member['id'] ?>" <?= $checked ? 'checked' : '' ?>>
                                            <span class="member-tile__dot"
                                                style="background-color: <?= htmlspecialchars($member['color'] ?? '#808080') ?>"></span>
                                            <span class="member-tile__name"><?= htmlspecialchars($member['name']) ?></span>
                                            <span class="member-tile__check">✓</span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <button type="submit" class="btn btn-primary btn-sm">Update Members</button>
                            </form>

                            <!-- Edit Note -->
                            <form method="POST" action="/fronting" class="session-subform">
                                <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
                                <input type="hidden" name="action" value="update_note">
                                <input type="hidden" name="system_id" value="<?= $system_id ?>">
                                <input type="hidden" name="session_id" value="<?= $active_session['id'] ?>">

                                <div class="subform-label">Session Note</div>
                                <textarea name="note" class="note-field"
                                    placeholder="Add a note..."><?= htmlspecialchars($active_session['note'] ?? '') ?></textarea>
                                <button type="submit" class="btn btn-primary btn-sm">Save Note</button>
                            </form>

                            <!-- End Session -->
                            <form method="POST" action="/fronting" class="end-session-form">
                                <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
                                <input type="hidden" name="action" value="end_session">
                                <input type="hidden" name="system_id" value="<?= $system_id ?>">
                                <input type="hidden" name="session_id" value="<?= $active_session['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-full">End Session</button>
                            </form>
                        </div>

                    <?php elseif (!empty($members)): ?>
                        <!-- Start Session Form -->
                        <div class="session-form">
                            <h2>Start New Session</h2>
                            <form method="POST" action="/fronting">
                                <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
                                <input type="hidden" name="action" value="start_session">
                                <input type="hidden" name="system_id" value="<?= $system_id ?>">

                                <div class="form-group">
                                    <label>Select Members</label>
                                    <div class="member-checkboxes">
                                        <?php foreach ($members as $member): ?>
                                            <label class="checkbox-label">
                                                <input type="checkbox" name="members[]" value="<?= $member['id'] ?>">
                                                <span class="member-color"
                                                    style="background-color: <?= htmlspecialchars($member['color'] ?? '#808080') ?>"></span>
                                                <span class="member-name"><?= htmlspecialchars($member['name']) ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="note">Notes (Optional)</label>
                                    <textarea id="note" name="note"
                                        placeholder="Add any notes about this fronting session..."></textarea>
                                </div>

                                <button type="submit" class="btn btn-primary">Start Session</button>
                            </form>
                        </div>

                    <?php else: ?>
                        <div class="alert-box alert-info">
                            <p>This system has no members yet. <a href="/manage/members?system=<?= $system_id ?>">Add
                                    members</a> to start tracking fronting sessions.</p>
                        </div>
                    <?php endif; ?>

                <?php endif; ?>

                <a href="/dashboard" class="btn-secondary">Back to Dashboard</a>
                <a href="/history" class="btn-secondary">View History</a>
            </div>

            <?php include $includesDir . '/footer.php'; ?>
        </div>
    </div>
</body>

</html>