<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// load dotenv for environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

class Mailer
{
    private PHPMailer $mail;

    public function __construct()
    {
        $this->mail = new PHPMailer(true);
        $this->configure();
    }

    private function configure(): void
    {
        // SMTP configuration
        $this->mail->isSMTP();
        $this->mail->Host = $_ENV['SMTP_HOST'];
        $this->mail->SMTPAuth = true;
        $this->mail->Username = $_ENV['SMTP_USERNAME'];
        $this->mail->Password = $_ENV['SMTP_PASSWORD'];
        $this->mail->SMTPSecure = $_ENV['SMTP_ENCRYPTION'];
        $this->mail->Port = $_ENV['SMTP_PORT'];
    }

    public function sendEmail(string $to, string $subject, string $body, bool $isHTML = false   ): bool
    {
        try {
            // Set email parameters
            $this->mail->setFrom($_ENV['SMTP_USERNAME'], 'plrsys');
            $this->mail->addAddress($to);
            $this->mail->isHTML($isHTML);
            $this->mail->Subject = $subject;
            $this->mail->Body = $body;

            // Send the email
            return $this->mail->send();
        } catch (Exception $e) {
            error_log('Mailer Error: ' . $e->getMessage());
            return false;
        }
    }

    public function sendPasswordResetEmail(string $to, string $token): bool
    {
        $resetLink = ($_ENV['APP_URL'] ?? ('https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'))) . "/reset-password/confirm?token=$token";
        $subject = 'plrsys Password Reset Request';
        $body = "Hello,<br><br>We received a request to reset your password. Click the link below to reset it:<br><a href='$resetLink'>$resetLink</a><br><br>If you didn't request this, please ignore this email.<br><br>Best,<br>plrsys Team";

        return $this->sendEmail($to, $subject, $body, true);
    }

    public function send2FACodeEmail(string $to, string $code): bool
    {
        $subject = 'Your plrsys 2FA Code';
        $body = "Hello,<br><br>Your Two-Factor Authentication code is: <strong>$code</strong><br><br>This code will expire in 5 minutes.<br><br>Best,<br>plrsys Team";

        return $this->sendEmail($to, $subject, $body, true);
    }

    public function sendEmailChangeConfirmation(string $to, int $userId, string $token): bool
    {
        $baseUrl = $_ENV['APP_URL'] ?? ('https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        $confirmationUrl = rtrim($baseUrl, '/') . '/confirm-email?user=' . urlencode((string) $userId) . '&token=' . urlencode($token);
        $subject = 'Confirm your plrsys email address';
        $body = "Hello,<br><br>We received a request to update your plrsys email address. Confirm it by clicking the link below:<br><a href='$confirmationUrl'>$confirmationUrl</a><br><br>If you did not request this change, you can ignore this email and your current address will remain unchanged.<br><br>Best,<br>plrsys Team";

        return $this->sendEmail($to, $subject, $body, true);
    }
}
