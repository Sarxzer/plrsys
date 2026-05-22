<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../src/php/database.php';

use Dotenv\Dotenv;
Dotenv::createImmutable(__DIR__ . '/../../')->load();

header('Content-Type: application/json');

$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if ($auth !== 'Bearer ' . $_ENV['BOT_API_TOKEN']) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$discord_id = $_GET['discord_id'] ?? null;
$user_id    = $_GET['user_id'] ?? null;
$member_id  = $_GET['id'] ?? null;
$handle     = $_GET['handle'] ?? null;

if (!$discord_id && !$user_id && !$member_id && !$handle) {
    http_response_code(400);
    echo json_encode(['error' => 'Provide at least one of: discord_id, id, handle']);
    exit;
}

$db = new Database();
$pdo = $db->getPdo();

$where = [];
$params = [];

if ($discord_id) {
    $where[] = 'u.discord_id = ?';
    $params[] = $discord_id;
}
if ($user_id) {
    $where[] = 's.user_id = ?';
    $params[] = (int) $user_id;
}
if ($member_id) {
    $where[] = 'm.id = ?';
    $params[] = (int) $member_id;
}
if ($handle) {
    $where[] = 'm.handle = ?';
    $params[] = $handle;
}

$sql = "
    SELECT m.id, m.name, m.handle, m.avatar_url, m.proxy_prefix, m.color, m.pronouns, m.role
    FROM members m
    JOIN systems s ON s.id = m.system_id
    JOIN users u ON u.id = s.user_id
    WHERE " . implode(' AND ', $where);

// only filter by proxy_prefix when searching by discord_id
// (fetching a specific member by id/handle should always return it)
if ($discord_id && !$member_id && !$handle && !$user_id) {
    $sql .= ' AND m.proxy_prefix IS NOT NULL';
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

// single-member lookups (by id or handle) return an object, not an array
if (($member_id || $handle) && !$discord_id && !$user_id) {
    echo json_encode($members[0] ?? null);
} else {
    echo json_encode($members);
}