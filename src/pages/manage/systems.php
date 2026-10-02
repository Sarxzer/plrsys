<?php
/**
 * @var PDO $pdo
 * @var string $includesDir
 * @var string $cssDir
 * @var string $jsDir
 */

Guards::requireLogin();

$auth = new Auth($pdo);
$currentUser = $auth->requireCurrentUser();
$userId = (int) $currentUser['id'];

$stmt = $pdo->prepare('SELECT * FROM systems WHERE user_id = ?');
$stmt->execute([$userId]);
$systems = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($systems)) {
    header('Location: /manage/system/new');
    exit;
}

// For now, this page will redirect to the first system's edit page, but in the future we can expand it for multiple systems per user and have a proper listing page here.
header('Location: /manage/s/' . $systems[0]['handle']);
exit;
