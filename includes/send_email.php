<?php
// includes/send_email.php
// Helper function to send emails using PHPMailer via SMTP.

// Include PHPMailer classes
require_once __DIR__ . '/../vendor/autoload.php'; // Adjust path if needed

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Ensure $pdo is available from db.php
// If db.php is included before this file, $pdo should be globally accessible.
// If not, you might need to pass $pdo as an argument to sendEmail function
// or include db.php directly here if it's not guaranteed to be loaded.
// For this example, we assume $pdo is already available.
global $pdo;

/**
 * Fetches application settings from the database.
 *
 * @param PDO $pdo The PDO database connection object.
 * @return array An associative array of settings.
 */
function getAppSettings(PDO $pdo): array
{
    $settings = [];
    try {
        $stmt = $pdo->query("SELECT setting_name, setting_value FROM app_settings");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['setting_name']] = $row['setting_value'];
        }
    } catch (PDOException $e) {
        error_log("Database Error fetching app settings: " . $e->getMessage());
        // Return empty settings or throw an exception, depending on desired error handling
    }
    return $settings;
}

/**
 * Sends an email using PHPMailer via SMTP.
 *
 * @param string $to_email The recipient's email address.
 * @param string $subject The subject of the email.
 * @param string $body The HTML body of the email.
 * @return bool True on success, false on failure.
 */
function sendEmail(string $to_email, string $subject, string $body): bool
{
    global $pdo; // Access the global PDO object

    if (!$pdo) {
        error_log("PDO object not available in sendEmail function.");
        return false;
    }

    $appSettings = getAppSettings($pdo);

    // Check if all required SMTP settings are available
    if (empty($appSettings['smtp_host']) || empty($appSettings['smtp_username']) ||
        empty($appSettings['smtp_password']) || empty($appSettings['smtp_port']) ||
        empty($appSettings['smtp_from_email']) || empty($appSettings['smtp_from_name'])) {
        error_log("Missing one or more SMTP settings in the database.");
        return false;
    }

    $mail = new PHPMailer(true); // Passing `true` enables exceptions

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = $appSettings['smtp_host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $appSettings['smtp_username'];
        $mail->Password   = $appSettings['smtp_password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // For port 465
        // If using port 587, change to PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int)$appSettings['smtp_port'];

        // Recipients
        $mail->setFrom($appSettings['smtp_from_email'], $appSettings['smtp_from_name']);
        $mail->addAddress($to_email);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = strip_tags($body);

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}
