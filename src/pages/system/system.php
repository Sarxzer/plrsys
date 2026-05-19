<?php

/** @var array $parts
 * @var PDO $pdo
 * @var string $includesDir
 * @var string $cssDir
 * @var string $jsDir
 */



$parts ??= explode('/', trim($_SERVER['REQUEST_URI'], '/'));

$system_handle = $parts[1] ?? null;

$stmt = $pdo->prepare("SELECT * FROM systems WHERE handle = ?");
$stmt->execute([$system_handle]);
$system = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$system) {
    Alert::error('System not found.');
    header('Location: /');
    exit;
}

$isOwner = Guards::isSystemOwner($pdo, (int) $system['id']);
if ((int) $system['is_public'] !== 1 && !$isOwner) {
    Alert::error('System not found.');
    header('Location: /');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM members WHERE system_id = ?");
$stmt->execute([$system['id']]);
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
$stmt->execute([$system['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT * FROM fronting_sessions WHERE system_id = ? AND ended_at IS NULL");
$stmt->execute([$system['id']]);
$active_sessions = $stmt->fetch(PDO::FETCH_ASSOC);

$fronting_session_members = [];
if ($active_sessions) {
    $stmt = $pdo->prepare("
        SELECT m.id, m.name
        FROM fronting_session_members fsm
        JOIN members m ON m.id = fsm.member_id
        WHERE fsm.session_id = ?
    ");
    $stmt->execute([$active_sessions['id']]);
    $fronting_session_members = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$nowFrontingMembers = [];
foreach ($fronting_session_members as $fsm) {
    if (!in_array($fsm['name'], $nowFrontingMembers, true)) {
        $nowFrontingMembers[] = $fsm['name'];
    }
}


$systemName = htmlspecialchars($system['name']);
$systemDescription = htmlspecialchars($system['description'] ?? "No description provided.");
$systemHandle = htmlspecialchars($system['handle']);
$canonicalUrl = htmlspecialchars('https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($system['name']) ?> | Innerspace</title>
    <link rel="stylesheet" href="<?= $cssDir ?>">
    <link rel="shortcut icon" href="/assets/images/favicon.png" type="image/png">
    <script src="<?= $jsDir?>" defer></script>

    <!-- Open Graph -->
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Innerspace">
    <meta property="og:title" content="<?= $systemName ?>">
    <meta property="og:description" content="<?= $systemDescription ?>">
    <meta property="og:url" content="<?= $canonicalUrl ?>">
    <meta property="og:image" content="https://innerspace.space/assets/icons/icon-512.png"> 
    <meta property="og:image:alt" content="<?= $systemName ?> system on Innerspace">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="<?= $systemName ?>">
    <meta name="twitter:description" content="<?= $systemDescription ?>">
    <meta name="twitter:image" content="https://innerspace.space/assets/icons/icon-512.png">
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
                <div class="system-header">
                    <div class="system-title"><?= $systemName ?></div>
                    <div class="system-meta">
                        <div class="meta-item">
                            <div class="meta-label">Handle</div>
                            <div class="meta-value">@<?= htmlspecialchars($system['handle']) ?></div>
                        </div>
                        <div class="meta-item">
                            <div class="meta-label">Owner</div>
                            <div class="meta-value"><?= htmlspecialchars($user['username']) ?></div>
                        </div>
                        <div class="meta-item">
                            <div class="meta-label">Members</div>
                            <div class="meta-value"><?= count($members) ?></div>
                        </div>
                    </div>
                    <div class="system-description"><?= nl2br($systemDescription) ?></div>
                    <div class="fronting-badge">
                        <div class="fronting-dot"></div>
                        <div class="fronting-text"><?= $nowFrontingMembers ? "Now fronting: " . htmlspecialchars(implode(", ", $nowFrontingMembers)) : "No one is fronting" ?></div>
                    </div>
                    <div class="system-actions">
                        <?php if ($isOwner): ?>
                            <a href="/manage/s/<?= htmlspecialchars($system['handle']) ?>" class="btn btn-secondary">Manage System</a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="section-title">Members</div>

                <div class="members-grid">
                    <?php foreach ($members as $member): ?>
                        <a href='/s/<?= htmlspecialchars($system['handle']) ?>/@<?= htmlspecialchars($member['handle'] ?? 'unnamed') ?>' class="member-card">
                            <div class="member-accent-bar" style="background: <?= htmlspecialchars($member['color'] ?? '#000000') ?>;"></div>
                            <div class="member-hex" style="color: <?= htmlspecialchars($member['color'] ?? '#000000') ?>">#<?= substr(htmlspecialchars($member['color'] ?? '#000000'), 1) ?></div>
                            <div class="member-top">
                                <div class="color-dot" style="--color-dot: <?= htmlspecialchars($member['color']) ?>"></div>
                                <div class="member-name"><?= htmlspecialchars($member['name'] ?? 'Unnamed') ?></div>
                                <div class="member-role"><?= htmlspecialchars($member['role'] ?? 'Member') ?></div>
                            </div>
                            <div class="member-pronouns"><?= htmlspecialchars($member['pronouns'] ?? 'Not specified') ?></div>
                            <div class="member-description"><?= nl2br(htmlspecialchars($member['description'] ?? 'No description provided.')) ?></div>
                            <div class="card-arrow">[->]</div>
                        </a>
                    <?php endforeach; ?>
                    <?php if ($isOwner): ?>
                        <a href="/manage/s/<?= htmlspecialchars($system['handle']) ?>/new" class="member-card new-member-card">
                            <div class="new-member-content">
                                <div class="plus-icon">+</div>
                                <div class="label">Add New Member</div>
                            </div>
                        </a>
                    <?php endif; ?>
                </div>

            </div>

            <?php include $includesDir . '/footer.php'; ?>
        </div>
    </div>
</body>

</html>