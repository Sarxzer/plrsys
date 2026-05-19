<?php
/**
 * @var PDO $pdo
 */

$userId = isset($_GET['user']) ? (int) $_GET['user'] : 0;
$token = trim($_GET['token'] ?? '');

if ($userId <= 0 || $token === '') {
    Alert::error('Invalid email confirmation link.');
    header('Location: /login');
    exit;
}

$auth = new Auth($pdo);

if (!$auth->confirmEmailChange($userId, $token)) {
    Alert::error('That email confirmation link is invalid or expired.');
    header('Location: ' . (isset($_SESSION['user_id']) ? '/settings' : '/login'));
    exit;
}

Alert::success('Email updated successfully.');
header('Location: ' . (isset($_SESSION['user_id']) && (int) $_SESSION['user_id'] === $userId ? '/settings' : '/login'));
exit;