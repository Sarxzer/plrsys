<?php
/**
 * if ?token= is set to the token in env, make php sessiion user to test for prviewing the site without logging in. This is for testing the site before logging in, and for sharing the site with others without giving them access to the admin panel.
 */

$auth = new Auth($pdo);
if (isset($_GET['token']) && $_GET['token'] === $_ENV['PREVIEW_TOKEN']) {
    $auth->login(3, true);
    Alert::success('Preview mode enabled. You are logged in as a test user.');
    header('Location: /home');
    exit;
} else {
    Alert::error('Invalid preview token. Please check your URL and try again.');
    header('Location: /home');
    exit;
}