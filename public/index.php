<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/php/database.php';
require_once __DIR__ . '/../src/php/auth.php';
require_once __DIR__ . '/../src/php/alert.php';
require_once __DIR__ . '/../src/php/utils.php';
require_once __DIR__ . '/../src/php/discord.php';

$sessionSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (!empty($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $sessionSecure,
    'httponly' => true,
    'samesite' => 'Lax',
]);

session_start();

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

if ($_ENV['APP_DEBUG'] === 'true') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

$database = new Database();
$pdo = $database->getPdo();

$auth = new Auth($pdo);
$auth->checkRememberedUser();

$active = new ActiveVisitors($pdo);

$active->ping($_SESSION['user_id'] ?? null);

// Discord logging — runs in production too
if (($_ENV['DISCORD_WEBHOOK_LOGGING'] ?? 'false') === 'true') {
    $discord = new DiscordWebhook($_ENV['DISCORD_WEBHOOK_URL']);

    set_exception_handler(function (Throwable $e) use ($discord) {
        $discord->log('error', $e->getMessage(), [
            ['name' => 'File', 'value' => basename($e->getFile()), 'inline' => true], // no full paths in prod
            ['name' => 'Line', 'value' => (string) $e->getLine(), 'inline' => true],
        ]);
    });

    set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline) use ($discord) {
        if (in_array($errno, [E_NOTICE, E_DEPRECATED, E_USER_DEPRECATED]))
            return false;
        $discord->log('error', $errstr, [
            ['name' => 'File', 'value' => basename($errfile), 'inline' => true],
            ['name' => 'Line', 'value' => (string) $errline, 'inline' => true],
        ]);
        return false;
    });
}

$pagesDir = __DIR__ . '/../src/pages';
$includesDir = __DIR__ . '/../src/includes';

// Cache busting for CSS and JS
$cssDir = '/assets/css/style.css?v=' . filemtime(__DIR__ . '/assets/css/style.css'); // Cache busting
$jsDir = '/assets/js/main.js?v=' . filemtime(__DIR__ . '/assets/js/main.js'); // Cache busting

if (!is_dir($pagesDir)) {
    die("Pages directory not found: $pagesDir");
}

$uri = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$parts = explode('/', $uri);

// CSRF token generation and verification
Csrf::generate();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();
}


// Fetch current user if logged in
$current_user = $auth->getCurrentUser();


// Get system and member names for navbar if we're on a system/member page
if ($parts[0] === 's' && isset($parts[1])) {
    $stmt = $pdo->prepare("SELECT name FROM systems WHERE handle = ?");
    $stmt->execute([$parts[1]]);
    $system_name = $stmt->fetchColumn();

    if (isset($parts[2])) {
        $stmt = $pdo->prepare("SELECT name FROM members WHERE system_id = (SELECT id FROM systems WHERE handle = ?) AND handle = ?");
        $stmt->execute([$parts[1], ltrim($parts[2], '@')]);
        $member_name = $stmt->fetchColumn();
    }
}


// Generate the breadcrumbs for the navbar as an array of ['name' => ..., 'url' => ...]
$breadcrumbs = [];
$accumulated_path = '';
if ($parts[0] !== '' && $parts[0] !== 'home') {
    $breadcrumbs[] = ['name' => 'Home', 'url' => '/home'];
}
foreach ($parts as $index => $part) {
    $accumulated_path .= '/' . $part;
    $name = ucfirst(htmlspecialchars($part));

    // Special handling for certain parts to make them more user-friendly
    if ($part === 's' && isset($parts[1])) {
        $name = 'System';
    } elseif ($part === 'manage') {
        $name = 'Manage';
    } elseif ($part === 'dashboard') {
        $name = 'Dashboard';
    } elseif ($part === 'fronting') {
        $name = 'Fronting';
    } elseif ($part === 'history') {
        $name = 'History';
    } elseif ($part === 'settings') {
        $name = 'Settings';
    } elseif ($part === 'friends') {
        $name = 'Friends';
    }

    if (isset($system_name, $parts[1]) && $part === $parts[1]) {
        $name = $system_name ?? $part;
    }

    if (isset($member_name, $parts[2]) && $part === $parts[2]) {
        $name = $member_name ?? $part;
    }

    $name = htmlspecialchars($name);

    $breadcrumbs[] = ['name' => $name, 'url' => $accumulated_path];
}


match ($parts[0]) {
    // Public
    '' => header('Location: /home'), // Redirect root to home
    'home' => require $pagesDir . '/home.php',
    // Changelog
    'changelog' => require $pagesDir . '/changelog.php',
    // About
    'about' => require $pagesDir . '/about.php',
    // Privacy Policy and ToS
    'legal' => match (true) {
            isset($parts[1]) && $parts[1] === 'privacy' => require $pagesDir . '/legal/privacy.php', // /legal/privacy
            isset($parts[1]) && $parts[1] === 'tos' => require $pagesDir . '/legal/terms.php', // /legal/terms
            default => header('Location: /home'), // Redirect /legal to home for now
        },
    // Auth
    'login' => match (true) {
            isset($parts[1]) && $parts[1] === 'totp' => require $pagesDir . '/auth/login-totp.php', // /login/totp
            default => require $pagesDir . '/auth/login.php',      // /login
        },
    // register
    'register' => match (true) {
            isset($parts[1]) && $parts[1] === 'totp' => require $pagesDir . '/auth/setup-totp.php',          // /register/totp
            isset($parts[1]) && $parts[1] === 'backup-codes' => require $pagesDir . '/auth/backup-codes.php', // /register/backup-codes
            default => require $pagesDir . '/auth/register.php',      // /register
        },
    'confirm-email' => require $pagesDir . '/auth/confirm-email.php',
    'logout' => require $pagesDir . '/auth/logout.php',

    // Public system/member viewing
    'systems' => header('Location: /system'), // Redirect /systems to /system for now
    'system' => match (true) {
            isset($parts[1]) && isset($parts[2]) => header('Location: /s/' . $parts[1] . '/' . $parts[2]),   // /system/{handle}/{member_handle}
            isset($parts[1]) => header('Location: /s/' . $parts[1]), // /system/{handle}
            default => require $pagesDir . '/system/systems.php',       // /system alone makes no sense but we use it to list all systems in dev
        },
    's' => match (true) {
            isset($parts[1]) && isset($parts[2]) => require $pagesDir . '/system/member.php', // /s/{handle}/@{member_handle}
            isset($parts[1]) => require $pagesDir . '/system/system.php', // /s/{handle}
            default => header('Location: /home'), // Redirect /s to home for now
        },

    // Managed (authenticated) system/member editing
    'manage' => match (true) {
            isset($parts[1], $parts[2], $parts[3]) && $parts[1] === 's' && str_starts_with($parts[3], '@')
            => require $pagesDir . '/manage/member-edit.php',   // /manage/s/{handle}/@{member_handle}
            isset($parts[1], $parts[2], $parts[3]) && $parts[1] === 's' && $parts[3] === 'new'
            => require $pagesDir . '/manage/member-new.php',    // /manage/s/{handle}/new
            isset($parts[1], $parts[2]) && $parts[1] === 's'
            => require $pagesDir . '/manage/system-edit.php',   // /manage/s/{handle}
            isset($parts[1], $parts[2]) && $parts[1] === 'system' && $parts[2] === 'new'
            => require $pagesDir . '/manage/system-new.php',    // /manage/system/new
            default => require $pagesDir . '/manage/systems.php',       // /manage or /manage/systems
        },

    // Authenticated
    'dashboard' => require $pagesDir . '/dashboard/dashboard.php',
    'fronting' => require $pagesDir . '/dashboard/fronting.php',
    'history' => require $pagesDir . '/dashboard/history.php',
    // 'settings'  => require $pagesDir . '/settings/settings.php',
    'settings' => match (true) {
            isset($parts[1]) && $parts[1] === 'delete'
            => require $pagesDir . '/settings/delete-account.php',
            isset($parts[1]) && $parts[1] === 'discord' && isset($parts[2]) && $parts[2] === 'unlink'
            => require $pagesDir . '/settings/discord-unlink.php',
            isset($parts[1]) && $parts[1] === 'discord' && isset($parts[2]) && $parts[2] === 'callback'
            => require $pagesDir . '/settings/discord-callback.php',
            isset($parts[1]) && $parts[1] === 'discord' && isset($parts[2]) && $parts[2] === 'link'
            => require $pagesDir . '/settings/discord-redirect.php',
            isset($parts[1]) && $parts[1] === 'email' => require $pagesDir . '/settings/setup-email.php',          // /settings/setup-email
            isset($parts[1]) && $parts[1] === 'totp' => require $pagesDir . '/settings/setup-totp.php',          // /settings/setup-totp
            isset($parts[1]) && $parts[1] === 'backup-codes' => require $pagesDir . '/settings/backup-codes.php', // /settings/backup-codes
            default => require $pagesDir . '/settings/settings.php',      // /settings
        },

    'reset-password' => match (true) {
            isset($parts[1]) && $parts[1] === 'confirm' => require $pagesDir . '/auth/reset-password-confirm.php',
            default => require $pagesDir . '/auth/reset-password.php',
        },

    'friends' => match (true) {
            isset($parts[1]) && $parts[1] === 'invite' => require $pagesDir . '/friends/invite.php',  // /friends/invite
            default => require $pagesDir . '/friends/friends.php', // /friends
        },
    'friend' => require $pagesDir . '/friends/friend-view.php', // /friend/{token}  
    // fallback to 404
    default => require $pagesDir . '/errors/404.php',
};
