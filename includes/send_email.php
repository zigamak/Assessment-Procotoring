<?php
// includes/send_email.php
// Provides a function to send emails using PHPMailer.

// Autoload Composer dependencies
// Assumes vendor/autoload.php is one directory up from 'includes'
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

/**
 * Sends an email using PHPMailer with configured SMTP settings.
 * Assumes SMTP constants (SMTP_HOST, SMTP_USERNAME, etc.) are globally defined in db.php.
 *
 * @param string $to_email The recipient's email address.
 * @param string $subject The subject of the email.
 * @param string $body The HTML body of the email.
 * @param string $alt_body An optional plain-text alternative body.
 * @return bool True on success, false on failure.
 */
function sendEmail(string $to_email, string $subject, string $body, string $alt_body = ''): bool
{
    // Validate input parameters
    if (empty($to_email) || empty($subject) || empty($body)) {
        error_log("sendEmail: Invalid input parameters. to_email=$to_email, subject=$subject, body_length=" . strlen($body));
        return false;
    }

    // Check if required SMTP constants are defined
    $required_constants = ['SMTP_HOST', 'SMTP_USERNAME', 'SMTP_PASSWORD', 'SMTP_SECURE', 'SMTP_PORT', 'SMTP_FROM_EMAIL', 'SMTP_FROM_NAME'];
    foreach ($required_constants as $const) {
        if (!defined($const)) {
            error_log("sendEmail: Missing required SMTP constant: $const");
            return false;
        }
    }

    $mail = new PHPMailer(true); // Enable exceptions

    try {
        // Server settings
        // Uncomment for debugging; remove in production
        // $mail->SMTPDebug = SMTP::DEBUG_SERVER;
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;         // e.g., 'mail.eventio.africa' or provider's SMTP host
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;     // e.g., 'mackenny@eventio.africa'
        $mail->Password   = SMTP_PASSWORD;     // Your SMTP password
        $mail->SMTPSecure = SMTP_SECURE;       // 'tls' or 'ssl'
        $mail->Port       = SMTP_PORT;         // 587 for TLS, 465 for SSL

        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($to_email);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = $alt_body ?: strip_tags($body);

        // Additional settings for reliability
        $mail->Timeout = 30; // Increase timeout for slow servers
        $mail->CharSet = 'UTF-8'; // Ensure proper encoding

        $mail->send();
        error_log("sendEmail: Email sent successfully to $to_email with subject '$subject'");
        return true;
    } catch (Exception $e) {
        error_log("sendEmail: Failed to send email to $to_email. Mailer Error: " . $mail->ErrorInfo . " | Exception: " . $e->getMessage());
        return false;
    }
}
?>