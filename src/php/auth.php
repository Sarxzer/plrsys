<?php

/**
 * Authentication class for handling user login, logout, and session management
 * Uses PDO for database interactions and secure password hashing
 * Implements "remember me" functionality with secure tokens
 */
class Auth
{

    public function __construct(private PDO $pdo)
    {
    }

    // -------------------------------------------------
    // Session
    // -------------------------------------------------

    /**
     * Check if user is logged in
     * @return bool
     */
    public function isLoggedIn(): bool
    {
        return isset($_SESSION['user_id']);
    }

    /**
     * Require user to be logged in, otherwise redirect to login page
     * @return void
     */
    public function requireLogin(): void
    {
        if (class_exists('Guards')) {
            Guards::requireLogin();
            return;
        }

        if (!$this->isLoggedIn()) {
            header('Location: /login');
            exit;
        }
    }

    /**
     * Check username/password and return user ID if valid
     * @param string $username
     * @param string $password
     * @return int|null User ID or null if invalid
     */
    public function checkCredentials(string $username, string $password): ?int
    {
        $stmt = $this->pdo->prepare("SELECT id, password_hash FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            return (int) $user['id'];
        }
        return null;
    }

    /**
     * Verify user's password (used for sensitive operations like changing email/password)
     * @param int $userId
     * @param string $password
     * @return bool
     */
    public function verifyPassword(int $userId, string $password): bool
    {
        $stmt = $this->pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $hash = $stmt->fetchColumn();
        return $hash ? password_verify($password, $hash) : false;
    }

    /**
     * Register a new user account
     * @param string $username
     * @param string $password
     * @return int|null User ID if successful, null if username exists
     */
    public function register(string $username, string $password): ?int
    {
        // Check if username already exists
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            return null; // Username already taken
        }

        // Create new user
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare("INSERT INTO users (username, password_hash) VALUES (?, ?)");
        $stmt->execute([$username, $hashedPassword]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Log in the user by setting session and optionally remember me cookie
     * @param int $userId
     * @param bool $remember
     * @return void
     */
    public function login(int $userId, bool $remember = false): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        $_SESSION['user_id'] = $userId;
        if ($remember) {
            $this->rememberUser($userId);
        }
    }

    /**
     * Check if user has TOTP enabled
     * @param int $userId
     * @return bool
     */
    public function hasTotpEnabled(int $userId): bool
    {
        $stmt = $this->pdo->prepare("SELECT totp_enabled FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $result = $stmt->fetchColumn();
        return (bool) $result;
    }

    /**
     * Get user's TOTP secret (if TOTP is enabled)
     * @param int $userId
     * @return string|null
     */
    public function getTotpSecret(int $userId): ?string
    {
        $stmt = $this->pdo->prepare("SELECT totp_secret FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetchColumn() ?: null;
    }

    /**
     * Enable TOTP for a user by saving their secret
     * @param int $userId
     * @param string $secret
     * @return void
     */
    public function enableTotp(int $userId, string $secret): void
    {
        $stmt = $this->pdo->prepare("UPDATE users SET totp_secret = ?, totp_enabled = 1 WHERE id = ?");
        $stmt->execute([$secret, $userId]);
    }

    /**
     * Set up pending TOTP verification (after successful password login with TOTP enabled)
     * @param int $userId
     * @return void
     */
    public function loginWithTwoFactor(int $userId): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        $_SESSION['pending_2fa_user'] = $userId;
        $_SESSION['totp_attempts'] = 0;
    }

    /**
     * Get current logged in user data
     * @return array|null User data or null if not logged in
     */
    public function getCurrentUser(): ?array
    {
        if (!$this->isLoggedIn()) {
            return null;
        }
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Require a logged-in user record and return it.
     * @return array
     */
    public function requireCurrentUser(): array
    {
        $this->requireLogin();

        $user = $this->getCurrentUser();
        if (!$user) {
            $this->logout();
        }

        return $user;
    }

    /**
     * Log out the user by clearing session and cookies
     * @return void
     */
    public function logout(): void
    {
        $this->revokeRememberToken();
        session_unset();
        session_destroy();
        header('Location: /login');
        exit;
    }

    /**
     * Log out from all sessions (used when changing password or disabling 2FA)
     * @param int $userId
     * @return void
     */
    public function logoutOtherSessions(int $userId): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM sessions WHERE user_id = ? AND expires_at > NOW()");
        $stmt->execute([$userId]);
    }

    // -------------------------------------------------
    // Account management
    // -------------------------------------------------

    /**
     * Check if a password meets the defined criteria (length, complexity, etc.)
     * @param string $password
     * @return bool|string
     */
    public function passwordMeetsCriteria(string $password): bool|string
    {

        // passphrases
        $words = preg_split('/[\s\-]+/', trim($password), -1, PREG_SPLIT_NO_EMPTY);
        if (count($words) >= 4 && strlen($password) >= 20) {
            return true;
        }

        if (strlen($password) < 8) {
            return __('register.error.password_length');
        }
        if (!preg_match('/[A-Z]/', $password)) {
            return __('register.error.password_uppercase');
        }
        if (!preg_match('/[a-z]/', $password)) {
            return __('register.error.password_lowercase');
        }
        if (!preg_match('/[0-9]/', $password)) {
            return __('register.error.password_number');
        }
        if (!preg_match('/[\W_]/', $password)) {
            return __('register.error.password_special');
        }
        return true;
    }


    /**
     * Change user's password and log out from all other sessions
     * @param int $userId
     * @param string $newPassword
     * @return void
     */
    public function updatePassword(int $userId, string $newPassword): void
    {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->execute([$hashedPassword, $userId]);
        $this->logoutOtherSessions($userId);
    }

    /**
     * Change user's username
     * @param int $userId
     * @param string $newUsername
     * @return bool True if successful, false if username is taken
     */
    public function updateUsername(int $userId, string $newUsername): bool
    {
        // Check if new username is already taken by another user
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmt->execute([$newUsername, $userId]);
        if ($stmt->fetch()) {
            return false; // Username already taken
        }
        $stmt = $this->pdo->prepare("UPDATE users SET username = ? WHERE id = ?");
        $stmt->execute([$newUsername, $userId]);
        return true;
    }

    /**
     * Change user's email
     * @param int $userId
     * @param string $newEmail
     * @return void
     */
    public function updateEmail(int $userId, string $newEmail): void
    {
        $stmt = $this->pdo->prepare("UPDATE users SET email = ?, pending_email = NULL, pending_email_token_hash = NULL, pending_email_expires_at = NULL WHERE id = ?");
        $stmt->execute([$newEmail, $userId]);
    }

    /**
     * Start an email change request and return the confirmation token.
     * @param int $userId
     * @param string $newEmail
     * @return string|null Confirmation token, or null if the email is already in use
     */
    public function requestEmailChange(int $userId, string $newEmail): ?string
    {
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$newEmail, $userId]);
        if ($stmt->fetch()) {
            return null;
        }

        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);

        $stmt = $this->pdo->prepare("UPDATE users SET pending_email = ?, pending_email_token_hash = ?, pending_email_expires_at = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE id = ?");
        $stmt->execute([$newEmail, $tokenHash, $userId]);

        return $token;
    }

    /**
     * Confirm a pending email change.
     * @param int $userId
     * @param string $token
     * @return bool
     */
    public function confirmEmailChange(int $userId, string $token): bool
    {
        $stmt = $this->pdo->prepare("SELECT email, pending_email, pending_email_token_hash, pending_email_expires_at FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || empty($user['pending_email']) || empty($user['pending_email_token_hash']) || empty($user['pending_email_expires_at'])) {
            return false;
        }

        if (strtotime($user['pending_email_expires_at']) < time()) {
            $this->clearPendingEmailChange($userId);
            return false;
        }

        if (!hash_equals($user['pending_email_token_hash'], hash('sha256', $token))) {
            return false;
        }

        $stmt = $this->pdo->prepare("UPDATE users SET email = ?, pending_email = NULL, pending_email_token_hash = NULL, pending_email_expires_at = NULL WHERE id = ?");
        $stmt->execute([$user['pending_email'], $userId]);

        return true;
    }

    /**
     * Clear a pending email change without applying it.
     * @param int $userId
     * @return void
     */
    public function clearPendingEmailChange(int $userId): void
    {
        $stmt = $this->pdo->prepare("UPDATE users SET pending_email = NULL, pending_email_token_hash = NULL, pending_email_expires_at = NULL WHERE id = ?");
        $stmt->execute([$userId]);
    }

    /**
     * Disable TOTP for the user and log out from all sessions
     * @param int $userId
     * @return void
     */
    public function disableTotp(int $userId): void
    {
        $stmt = $this->pdo->prepare("UPDATE users SET totp_secret = NULL, totp_enabled = 0 WHERE id = ?");
        $stmt->execute([$userId]);
        $stmt = $this->pdo->prepare("DELETE FROM totp_backup_codes WHERE user_id = ?");
        $stmt->execute([$userId]);
        $this->logoutOtherSessions($userId);
    }


    /**
     * Delete a user account and all associated data
     * @param int $userId
     * @return void
     */
    public function deleteUser(int $userId): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        // Related data (sessions, backup codes) will be automatically deleted via foreign key constraints
    }


    // -------------------------------------------------
    // Remember me (persistent cookie)
    // -------------------------------------------------

    /**
     * Create a persistent login token and set cookie
     * @param int $userId
     * @return void
     */
    public function rememberUser(int $userId): void
    {
        $token = bin2hex(random_bytes(32));
        $expires = time() + (30 * 24 * 60 * 60); // 30 days

        $token_hash = hash('sha256', $token);

        $stmt = $this->pdo->prepare("
            INSERT INTO sessions (id, user_id, expires_at, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $token_hash,
            $userId,
            date('Y-m-d H:i:s', $expires),
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);

        // Secure flag should be true for HTTPS, false for HTTP (e.g., local testing)
        $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        setcookie('remember_token', $token, [
            'expires' => $expires,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    }

    /**
     * Check if there's a valid remember me token and log in the user
     * @return void
     */
    public function checkRememberedUser(): void
    {
        if ($this->isLoggedIn() || !isset($_COOKIE['remember_token'])) {
            return;
        }

        $token = $_COOKIE['remember_token'];

        $token_hash = hash('sha256', $token);

        $stmt = $this->pdo->prepare("
            SELECT * FROM sessions
            WHERE id = ?
              AND expires_at > NOW()
              AND is_revoked = 0
              AND ip_address = ?
              AND user_agent = ?
        ");
        $stmt->execute([
            $token_hash,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);

        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($session) {
            $_SESSION['user_id'] = $session['user_id'];
            $this->revokeToken($token_hash);       // rotate: one-time use token
            $this->rememberUser($session['user_id']);
        } else {
            $this->clearRememberCookie();
        }
    }

    // -------------------------------------------------
    // Internals
    // -------------------------------------------------

    /**
     * Revoke a remember me token
     * @param string $token
     * @return void
     */
    private function revokeToken(string $token): void
    {
        $stmt = $this->pdo->prepare("UPDATE sessions SET is_revoked = 1 WHERE id = ?");
        $stmt->execute([$token]);
    }

    /**
     * Revoke current remember me token if exists
     * @return void
     */
    private function revokeRememberToken(): void
    {
        if (isset($_COOKIE['remember_token'])) {
            $token_hash = hash('sha256', $_COOKIE['remember_token']);

            $this->revokeToken($token_hash);
            $this->clearRememberCookie();
        }
    }

    private function clearRememberCookie(): void
    {
        $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        setcookie('remember_token', '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    }
}
