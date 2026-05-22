<?php
// send a mail to the email address specified in the .env file to test the mailer class
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/php/mailer.php';

$mailer = new Mailer();
$to = $_ENV['TEST_EMAIL'];
$subject = 'Test Email from Innerspace';
$body = 'This is a test email sent from the Innerspace mailer class. If you received this email, the mailer is working correctly.';
if ($mailer->sendEmail($to, $subject, $body)) {
    echo "Test email sent successfully to $to";
} else {
    echo "Failed to send test email to $to";
}

