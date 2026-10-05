<?php

final class ApiTokens
{
    private const ACCESS_TTL = 900;
    private const REFRESH_TTL = 2592000;

    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Issues a new access and refresh token pair for the given user ID.
     *
     * @param int $userId The user ID for which to issue the tokens.
     * @return array An associative array containing the access token, refresh token, token type, and expiration time.
     */
    public function issuePair(int $userId): array
    {
        $familyId = bin2hex(random_bytes(16));
        $access = $this->issue($userId, 'access', $familyId, self::ACCESS_TTL);
        $refresh = $this->issue($userId, 'refresh', $familyId, self::REFRESH_TTL);

        return [
            'access_token' => $access['token'],
            'refresh_token' => $refresh['token'],
            'token_type' => 'Bearer',
            'expires_in' => self::ACCESS_TTL,
        ];
    }

    /**
     * Issues a new API token for the given user ID.
     *
     * @param int $userId The user ID for which to issue the token.
     * @param string $type The type of the token to issue.
     * @param string $familyId The family ID to associate with the token.
     * @param int $ttl The time-to-live of the token in seconds.
     * @return array An associative array containing the token and its hash.
     */
    private function issue(int $userId, string $type, string $familyId, int $ttl): array
    {
        $token = 'plr_live_' . bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $expiresAt = (new DateTimeImmutable("+$ttl seconds"))->format('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare(
            'INSERT INTO api_tokens (user_id, token_hash, token_type, family_id, expires_at)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $hash, $type, $familyId, $expiresAt]);

        return ['token' => $token, 'hash' => $hash];
    }



    /**
     * Refreshes an API token.
     *
     * @param int $userId The user ID for which to refresh the token.
     * @param string $refreshToken The refresh token to use.
     * @return array An associative array containing the new access token, refresh token, token type, and expiration time.
     */
    public function refresh(int $userId, string $refreshToken): array
    {
        $hash = hash('sha256', $refreshToken);
        $stmt = $this->pdo->prepare(
            'SELECT family_id, expires_at FROM api_tokens WHERE token_hash = ? AND token_type = ? LIMIT 1'
        );
        $stmt->execute([$hash, 'refresh']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new Exception('Invalid refresh token');
        }

        $expiresAt = new DateTimeImmutable($row['expires_at']);
        if ($expiresAt < new DateTimeImmutable()) {
            throw new Exception('Refresh token expired');
        }

        // Invalidate all tokens in the same family
        $stmt = $this->pdo->prepare(
            'DELETE FROM api_tokens WHERE family_id = ?'
        );
        $stmt->execute([$row['family_id']]);

        return $this->issuePair($userId);
    }

    /**
     * Checks the health and metadata of an API token.
     *
     * @param string $token The API token to check.
     * @param int $userId The user ID associated with the token.
     * @return array|false Token metadata when valid, false otherwise.
     */
    public function checkHealth(string $token, int $userId): array|false
    {
        $hash = hash('sha256', $token);
        $stmt = $this->pdo->prepare(
            'SELECT token_type, expires_at FROM api_tokens
             WHERE token_hash = ? AND revoked_at IS NULL AND user_id = ? LIMIT 1'
        );
        $stmt->execute([$hash, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return false;
        }

        $expiresAt = new DateTimeImmutable($row['expires_at']);
        $now = new DateTimeImmutable();

        if ($expiresAt <= $now) {
            return false;
        }

        return [
            'token_type' => $row['token_type'],
            'expires_at' => $expiresAt->format(DateTimeInterface::ATOM),
            'expires_in' => $expiresAt->getTimestamp() - $now->getTimestamp(),
        ];
    }
}
