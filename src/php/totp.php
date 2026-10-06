<?php

use OTPHP\TOTP;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;
use Endroid\QrCode\ErrorCorrectionLevel;
use Symfony\Component\Clock\NativeClock;

function totp_generate_secret(string $userEmail, string $issuer): array
{
    $otp = TOTP::generate(clock: new NativeClock(), secretSize: 20); // 160-bit secret
    $otp->setLabel($userEmail);
    $otp->setIssuer($issuer);

    $uri = $otp->getProvisioningUri();
    // Endroid QrCode: instantiate directly instead of using create() helper
    $qrCode = new QrCode(
        data: $uri,
        errorCorrectionLevel: ErrorCorrectionLevel::High,
        size: 300,
        margin: 10,
    );

    $qr = (new SvgWriter())->write($qrCode);

    return [
        'secret' => $otp->getSecret(),
        'qr_base64' => base64_encode($qr->getString()),
    ];
}

function totp_verify(string $secret, string $code): bool
{
    $otp = TOTP::createFromSecret($secret, clock: new NativeClock());
    return $otp->verify($code, null, 1); // ±1 window = ±30s drift tolerance
}

function totp_generate_backup_codes(PDO $pdo, int $userId): array
{
    // Clear old ones first
    $pdo->prepare("DELETE FROM totp_backup_codes WHERE user_id = ?")->execute([$userId]);

    $plain = [];
    for ($i = 0; $i < 8; $i++) {
        $code = strtoupper(bin2hex(random_bytes(4))); // e.g. "A3F2B91C"
        $plain[] = $code;
        $pdo->prepare("INSERT INTO totp_backup_codes (user_id, code_hash) VALUES (?, ?)")
            ->execute([$userId, password_hash($code, PASSWORD_DEFAULT)]);
    }
    return $plain; // Show to user ONCE, never store plain
}

function totp_verify_backup(PDO $pdo, int $userId, string $inputCode): bool
{
    $rows = $pdo->prepare("SELECT id, code_hash FROM totp_backup_codes WHERE user_id = ? AND used_at IS NULL");
    $rows->execute([$userId]);

    foreach ($rows->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (password_verify(strtoupper(trim($inputCode)), $row['code_hash'])) {
            $pdo->prepare("UPDATE totp_backup_codes SET used_at = NOW() WHERE id = ?")
                ->execute([$row['id']]);
            return true;
        }
    }
    return false;
}
