<?php
// Manual signup script for testing purposes. Not meant to be used in production.

require_once __DIR__ . '/../src/php/database.php';
require_once __DIR__ . '/../src/php/auth.php';

$username = readline("Enter username: ");
$password = readline("Enter password: ");

$database = new Database();
$pdo = $database->getPdo();
$auth = new Auth($pdo);
$userId = $auth->register($username, $password);
if ($userId === null) {
	echo "Username '$username' already exists.\n";
	exit(1);
}

echo "User '$username' created with ID " . $userId . "\n";