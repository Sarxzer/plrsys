<?php

/**
 * Handle CSRF token generation and verification.
 */
class Csrf
{

    /**
     * Generate a CSRF token and store it in the session if it doesn't already exist.
     * @return void
     */
    public static function generate(): void
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    }

    public static function verify(): void
    {
        if (
            empty($_POST['csrf_token']) ||
            !isset($_SESSION['csrf_token']) ||
            !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
        ) {
            http_response_code(403);
            Alert::error('Invalid CSRF token. Please try again.');
            Alert::dev('CSRF token verification failed. Expected: ' . ($_SESSION['csrf_token'] ?? 'null') . ', Received: ' . ($_POST['csrf_token'] ?? 'null'));
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/'));
            exit;
        }
    }

    public static function token(): string
    {
        return $_SESSION['csrf_token'] ?? '';
    }
}


/**
 * Track active visitors on the site, both guests and logged-in users.
 * This can be used to display online counts, track popular pages, and more.
 */
class ActiveVisitors
{
    private PDO $pdo;
    private int $timeout;

    public function __construct(PDO $pdo, int $timeout = 300)
    {
        $this->pdo = $pdo;
        $this->timeout = $timeout;
    }

    /**
     * Ping the active visitors system to update the current user's last seen time and page.
     * @param mixed $userId
     * @return void
     */
    public function ping(?int $userId): void
    {
        $blockedBots = [
            'uptime-kuma',
            'bot',
            'crawler',
            'monitor',
            'pingdom',
        ];

        $id = session_id();
        $page = $_SERVER['REQUEST_URI'] ?? 'unknown';
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

        foreach ($blockedBots as $bot) {
            if (stripos($_SERVER['HTTP_USER_AGENT'] ?? '', $bot) !== false) {
                return; // Don't track bots
            }
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO active_visitors (id, user_id, page, last_seen, ip, user_agent)
            VALUES (:id, :user_id, :page, NOW(), :ip, :ua)
            ON DUPLICATE KEY UPDATE
                user_id = :user_id,
                page = :page,
                last_seen = NOW(),
                ip = :ip,
                user_agent = :ua
        ");

        $stmt->execute([
            ':id' => $id,
            ':user_id' => $userId,
            ':page' => $page,
            ':ip' => $ip,
            ':ua' => $ua,
        ]);


        $stmt = $this->pdo->prepare("
            INSERT INTO visitor_events (id, user_id, page, ip, user_agent)
            VALUES (:id, :user_id, :page, :ip, :ua)
        ");

        $stmt->execute([
            ':id' => $id . '-' . time(),
            ':user_id' => $userId,
            ':page' => $page,
            ':ip' => $ip,
            ':ua' => $ua,
        ]);
    }

    /**
     * Count the number of active visitors currently online based on the last seen time.
     * @return int
     */
    public function countOnline(): int
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM active_visitors
            WHERE last_seen > NOW() - INTERVAL :t SECOND
        ");
        $stmt->bindValue(':t', $this->timeout, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /**
     * Count the number of active guests currently online (users who are not logged in).
     * @return int
     */
    public function countGuests(): int
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM active_visitors
            WHERE user_id IS NULL
            AND last_seen > NOW() - INTERVAL :t SECOND
        ");
        $stmt->bindValue(':t', $this->timeout, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /**
     * Count the number of active registered users currently online.
     * @return int
     */
    public function countUsers(): int
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(DISTINCT user_id) FROM active_visitors
            WHERE user_id IS NOT NULL
            AND last_seen > NOW() - INTERVAL :t SECOND
        ");
        $stmt->bindValue(':t', $this->timeout, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /**
     * Get a breakdown of active visitors by page, showing the count of visitors on each page. 
     * @return array
     */
    public function perPage(): array
    {
        $stmt = $this->pdo->prepare("
            SELECT page, COUNT(*) as total
            FROM active_visitors
            WHERE last_seen > NOW() - INTERVAL :t SECOND
            GROUP BY page
            ORDER BY total DESC
        ");
        $stmt->bindValue(':t', $this->timeout, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cleanup old visitor records from the database.
     * @param int $olderThanSeconds
     * @return void
     */
    public function cleanup(int $olderThanSeconds = 600): void
    {
        $stmt = $this->pdo->prepare("
            DELETE FROM active_visitors
            WHERE last_seen < NOW() - INTERVAL :t SECOND
        ");
        $stmt->bindValue(':t', $olderThanSeconds, PDO::PARAM_INT);
        $stmt->execute();
    }
}



/**
 * Guard functions to protect routes and ensure users have the necessary permissions to access certain pages or perform certain actions.
 * These functions can be called at the top of any page or action handler to enforce access control
 */
class Guards 
{
    /**
     * Require that the user is logged in to access the page. If not logged in, redirect to the login page with an error message.
     * @return void
     */
    public static function requireLogin(): void
    {
        if (empty($_SESSION['user_id'])) {
            Alert::error('You must be logged in to access this page.');
            header('Location: /login');
            exit;
        }
    }

    /**
     * Require that the user is an administrator to access the page. If not an admin, redirect to the dashboard with an error message.
     * @param PDO $pdo
     * @return void
     */
    public static function requireAdmin(PDO $pdo): void
    {
        self::requireLogin();

        $stmt = $pdo->prepare('SELECT is_admin FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !$user['is_admin']) {
            Alert::error('You do not have permission to access this page.');
            header('Location: /dashboard');
            exit;
        }
    }

    /**
     * Require that the user owns the specified system to access the page. If not the owner, redirect to the dashboard with an error message.
     * @param PDO $pdo
     * @param int $systemId
     * @return void
     */
    public static function requireSystemOwnership(PDO $pdo, int $systemId): void
    {
        self::requireLogin();

        if (!self::isSystemOwner($pdo, $systemId)) {
            Alert::error('System not found or you do not have permission to manage it.');
            header('Location: /dashboard');
            exit;
        }
    }


    public static function isSystemOwner(PDO $pdo, int $systemId): bool
    {
        if (empty($_SESSION['user_id'])) {
            return false;
        }

        $stmt = $pdo->prepare('SELECT user_id FROM systems WHERE id = ?');
        $stmt->execute([$systemId]);
        $system = $stmt->fetch(PDO::FETCH_ASSOC);

        return $system && $system['user_id'] == $_SESSION['user_id'];
    }
}