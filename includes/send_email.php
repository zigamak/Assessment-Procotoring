<?php
// includes/send_email.php
// Provides a function to send emails using PHPMailer.

// Autoload Composer dependencies (if using Composer)
// This path assumes vendor/autoload.php is one directory up from 'includes'
require_once __DIR__ . '/../vendor/autoload.php';

// IMPORTANT: SMTP constants (SMTP_HOST, SMTP_USERNAME, etc.) are now expected to be
// defined globally by the calling script (e.g., in db.php or directly in forgot_password.php).
// Removed: require_once __DIR__ . '/config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP; // Needed for debugging output

/**
 * Sends an email using PHPMailer with configured SMTP settings.
 * Assumes SMTP constants (SMTP_HOST, SMTP_USERNAME, etc.) are globally defined.
 *
 * @param string $to_email The recipient's email address.
 * @param string $subject The subject of the email.
 * @param string $body The HTML body of the email.
 * @param string $alt_body An optional plain-text alternative body.
 * @return bool True on success, false on failure.
 */
function sendEmail(string $to_email, string $subject, string $body, string $alt_body = ''): bool
{
    $mail = new PHPMailer(true); // Passing `true` enables exceptions

    try {
        // Server settings
        // Enable verbose debug output for testing. Disable in production for security and performance.
        // $mail->SMTPDebug = SMTP::DEBUG_SERVER;
        $mail->isSMTP();                       // Send using SMTP
        $mail->Host       = SMTP_HOST;         // Set the SMTP server to send through
        $mail->SMTPAuth   = true;              // Enable SMTP authentication
        $mail->Username   = SMTP_USERNAME;     // SMTP username
        $mail->Password   = SMTP_PASSWORD;     // SMTP password
        $mail->SMTPSecure = SMTP_SECURE;       // Enable implicit TLS encryption (ssl for 465, tls for 587)
        $mail->Port       = SMTP_PORT;         // TCP port to connect to; use 587 if you set `SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS`

        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($to_email); // Add a recipient

        // Content
        $mail->isHTML(true); // Set email format to HTML
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = $alt_body ?: strip_tags($body); // Plain-text alternative

        $mail->send();
        error_log("Email sent successfully to: " . $to_email); // Log success
        return true;
    } catch (Exception $e) {
        // Log the detailed error message from PHPMailer
        error_log("Email could not be sent to " . $to_email . ". Mailer Error: " . $mail->ErrorInfo . " | Exception: " . $e->getMessage());
        return false;
    }
}
