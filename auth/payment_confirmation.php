<?php
// auth/payment_confirmation.php
// Handles payment confirmation, updates payment status, sends email, and generates auto-login token in auto_login_tokens table.

require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/send_email.php';
require_once '../vendor/autoload.php'; // Composer autoloader for Paystack

use Yabacon\Paystack;
use Yabacon\Paystack\Exception\ApiException;

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set timezone to Africa/Lagos (WAT, UTC+1)
date_default_timezone_set('Africa/Lagos');

// Get reference and quiz_id from URL
$reference = sanitize_input($_GET['reference'] ?? '');
$quiz_id = sanitize_input($_GET['quiz_id'] ?? '');

// ---
if (empty($reference) || empty($quiz_id)) {
    error_log("Payment Confirmation: Missing reference or quiz_id. Reference: '$reference', Quiz ID: '$quiz_id'");
    $_SESSION['form_message'] = "Invalid payment confirmation request. Please try again or contact support.";
    $_SESSION['form_message_type'] = 'error';
    // Ensure BASE_URL is defined, e.g., define('BASE_URL', 'http://localhost/your_app/');
    redirect(BASE_URL . 'auth/login.php');
    exit;
}
// ---

// Ensure PAYSTACK_SECRET_KEY is defined
if (!defined('PAYSTACK_SECRET_KEY')) {
    error_log("PAYSTACK_SECRET_KEY is not defined.");
    $_SESSION['form_message'] = "Payment gateway not configured correctly. Please contact support.";
    $_SESSION['form_message_type'] = 'error';
    redirect(BASE_URL . 'auth/login.php');
    exit;
}

$paystack = new Paystack(PAYSTACK_SECRET_KEY);

try {
    // Verify payment with Paystack
    $transaction = $paystack->transaction->verify(['reference' => $reference]);

    if ($transaction->status && $transaction->data->status === 'success') {
        // Fetch payment details
        $stmt = $pdo->prepare("SELECT payment_id, user_id, quiz_id, amount, status, created_at AS payment_date FROM payments WHERE transaction_reference = :reference");
        $stmt->execute(['reference' => $reference]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);

        // Fetch quiz details
        $quiz_stmt = $pdo->prepare("SELECT title, description, open_datetime, duration_minutes, max_attempts, grade FROM quizzes WHERE quiz_id = :quiz_id");
        $quiz_stmt->execute(['quiz_id' => $quiz_id]);
        $quiz_details = $quiz_stmt->fetch(PDO::FETCH_ASSOC);

        // Fetch user details
        $user_stmt = $pdo->prepare("SELECT email, first_name, last_name FROM users WHERE user_id = :user_id");
        $user_stmt->execute(['user_id' => $payment['user_id']]);
        $user_details = $user_stmt->fetch(PDO::FETCH_ASSOC);

        if ($payment && $payment['quiz_id'] == $quiz_id && $payment['status'] === 'pending') {
            // Verify amount
            if ((float)$transaction->data->amount / 100 !== (float)$payment['amount']) {
                error_log("Payment Confirmation: Amount mismatch for reference $reference. Paystack: " . ($transaction->data->amount / 100) . ", DB: " . $payment['amount']);
                $_SESSION['form_message'] = "Amount mismatch detected. Payment not processed. Please contact support.";
                $_SESSION['form_message_type'] = 'error';
                redirect(BASE_URL . 'auth/login.php');
                exit;
            }

            // Update payment status
            $stmt = $pdo->prepare("UPDATE payments SET status = 'completed', updated_at = NOW() WHERE transaction_reference = :reference");
            $stmt->execute(['reference' => $reference]);

            // Grant quiz access
            $stmt = $pdo->prepare("INSERT INTO quiz_access (user_id, quiz_id, granted_at) VALUES (:user_id, :quiz_id, NOW()) ON DUPLICATE KEY UPDATE granted_at = NOW()");
            $stmt->execute(['user_id' => $payment['user_id'], 'quiz_id' => $quiz_id]);

            // Generate auto-login token
            $auto_login_token = bin2hex(random_bytes(16)); // Secure random token
            $expires_at = (new DateTime())->modify('+24 hours')->format('Y-m-d H:i:s'); // Expires in 24 hours
            $created_at = (new DateTime())->format('Y-m-d H:i:s');

            // Store token in auto_login_tokens table
            $stmt = $pdo->prepare("
                INSERT INTO auto_login_tokens (user_id, quiz_id, token, expires_at, used, created_at)
                VALUES (:user_id, :quiz_id, :token, :expires_at, 0, :created_at)
            ");
            $stmt->execute([
                'user_id' => $payment['user_id'],
                'quiz_id' => $quiz_id,
                'token' => $auto_login_token,
                'expires_at' => $expires_at,
                'created_at' => $created_at
            ]);

            $_SESSION['form_message'] = "Payment successful! You now have access to the assessment: <strong>" . htmlspecialchars($quiz_details['title'] ?? 'Unknown Assessment') . "</strong>.";
            $_SESSION['form_message_type'] = 'success';

            // Send Confirmation Email
            if (!empty($user_details['email'])) {
                $email_title = "Payment Confirmation: " . htmlspecialchars($quiz_details['title'] ?? 'Assessment');
                $header_text = "Mackenny Assessment - Payment Confirmed!";
                $first_name = htmlspecialchars($user_details['first_name'] ?? '');
                $last_name = htmlspecialchars($user_details['last_name'] ?? '');
                $quiz_title = htmlspecialchars($quiz_details['title'] ?? 'N/A');
                $description = htmlspecialchars($quiz_details['description'] ?? 'N/A');
                $open_datetime = htmlspecialchars(format_datetime($quiz_details['open_datetime'] ?? 'N/A'));
                $duration_minutes = htmlspecialchars($quiz_details['duration_minutes'] ?? 'N/A');
                $max_attempts = htmlspecialchars($quiz_details['max_attempts'] ?? 'N/A');
                $amount = number_format($payment['amount'], 2);
                $grade = htmlspecialchars($quiz_details['grade'] ?? 'N/A'); // Assuming grade is available in quizzes table
                $transaction_reference = htmlspecialchars($reference);
                $payment_date = htmlspecialchars(format_datetime($payment['payment_date'] ?? 'N/A'));
                $auto_login_link = BASE_URL . "auth/auto_login.php?token=" . urlencode($auto_login_token);

                // Start output buffering to capture the email content
                ob_start();
              include '../includes/email_templates/header.php';
                ?>
                <p>Dear <?php echo $first_name . ' ' . $last_name; ?>,</p>
                <p>We are pleased to confirm that your payment for the following assessment has been successfully processed.</p>

                <h3>Assessment Details</h3>
                <table class="details-table">
                    <tr><th>Assessment Title</th><td><?php echo $quiz_title; ?></td></tr>
                    <tr><th>Description</th><td><?php echo $description; ?></td></tr>
                    <tr><th>Start Date and Time</th><td><?php echo $open_datetime; ?></td></tr>
                    <tr><th>Duration</th><td><?php echo $duration_minutes; ?> minutes</td></tr>
                    <tr><th>Maximum Attempts</th><td><?php echo $max_attempts; ?></td></tr>
                    <tr><th>Assessment Fee</th><td>₦<?php echo $amount; ?></td></tr>
                    <tr><th>Grade</th><td><?php echo $grade; ?></td></tr>
                    <tr><th>Transaction Reference</th><td><?php echo $transaction_reference; ?></td></tr>
                    <tr><th>Payment Date</th><td><?php echo $payment_date; ?></td></tr>
                </table>

                <h3>Important Instructions</h3>
                <p>Please note the following regarding your assessment:</p>
                <ul>
                    <li><strong>Arrival Time:</strong> You are required to be ready and logged in at least 5 minutes before the assessment start time. The assessment portal will open 5 minutes prior to the scheduled start time (<?php echo $open_datetime; ?>).</li>
                    <li><strong>Assessment Window:</strong> The assessment will only be accessible on the scheduled date and time. Once the assessment duration (<?php echo $duration_minutes; ?> minutes) has elapsed, the portal will close, and you will no longer be able to take the assessment.</li>
                    <li><strong>Preparation:</strong> Ensure you have a stable internet connection and a quiet environment to complete the assessment without interruptions.</li>
                </ul>

                <p>You now have full access to this assessment. Click the button below to access your dashboard and prepare for the assessment.</p>
                <p style="text-align: center;">
                    <a href="<?php echo $auto_login_link; ?>" class="button">Go to Dashboard</a>
                </p>
                <p>Thank you for choosing Mackenny Assessment!</p>
                <?php
                include '../includes/email_templates/footer.php';
                $message = ob_get_clean(); // Get the buffered content

                // Construct plain text message for email fallback
                $plain_text_message = "Dear " . $first_name . " " . $last_name . ",\n\n";
                $plain_text_message .= "We are pleased to confirm that your payment for the following assessment has been successfully processed.\n\n";
                $plain_text_message .= "Assessment Details:\n";
                $plain_text_message .= "Assessment Title: " . $quiz_title . "\n";
                $plain_text_message .= "Description: " . $description . "\n";
                $plain_text_message .= "Start Date and Time: " . $open_datetime . "\n";
                $plain_text_message .= "Duration: " . $duration_minutes . " minutes\n";
                $plain_text_message .= "Maximum Attempts: " . $max_attempts . "\n";
                $plain_text_message .= "Assessment Fee: ₦" . $amount . "\n";
                $plain_text_message .= "Grade: " . $grade . "\n";
                $plain_text_message .= "Transaction Reference: " . $transaction_reference . "\n";
                $plain_text_message .= "Payment Date: " . $payment_date . "\n\n";
                $plain_text_message .= "Important Instructions:\n";
                $plain_text_message .= "- Arrival Time: You are required to be ready and logged in at least 5 minutes before the assessment start time. The assessment portal will open 5 minutes prior to the scheduled start time (" . $open_datetime . ").\n";
                $plain_text_message .= "- Assessment Window: The assessment will only be accessible on the scheduled date and time. Once the assessment duration (" . $duration_minutes . " minutes) has elapsed, the portal will close, and you will no longer be able to take the assessment.\n";
                $plain_text_message .= "- Preparation: Ensure you have a stable internet connection and a quiet environment to complete the assessment without interruptions.\n\n";
                $plain_text_message .= "You now have full access to this assessment. Click the link below to access your dashboard and prepare for the assessment:\n";
                $plain_text_message .= $auto_login_link . "\n\n";
                $plain_text_message .= "Thank you for choosing Mackenny Assessment!\n\n";
                $plain_text_message .= "© " . date("Y") . " Mackenny Assessment. All rights reserved.\n";
                $plain_text_message .= "If you have any questions, contact us at support@mackennyassessment.com.\n";


                if (sendEmail($user_details['email'], $email_title, $message, $plain_text_message)) {
                    error_log("Confirmation email sent to: " . $user_details['email'] . " for reference: $reference");
                } else {
                    error_log("Failed to send confirmation email to: " . $user_details['email'] . " for reference: $reference");
                }
            } else {
                error_log("Payment Confirmation: User email not found for user_id: " . $payment['user_id']);
            }

            // Redirect to auto_login.php with the token
            redirect(BASE_URL . 'auth/auto_login.php?token=' . urlencode($auto_login_token));
            exit;
        } else {
            error_log("Payment Confirmation: DB Record mismatch or already processed. Reference: $reference, Quiz ID: $quiz_id");
            $_SESSION['form_message'] = "Payment not found or already processed.";
            $_SESSION['form_message_type'] = 'warning';
            redirect(BASE_URL . 'auth/login.php');
            exit;
        }
    } else {
        error_log("Payment Confirmation: Paystack verification failed for reference=$reference. Status: " . ($transaction->data->status ?? 'N/A'));
        $_SESSION['form_message'] = "Payment verification failed. Please try again or contact support.";
        $_SESSION['form_message_type'] = 'error';
        redirect(BASE_URL . 'auth/login.php');
        exit;
    }
} catch (ApiException $e) {
    error_log("Paystack API Error: " . $e->getMessage() . " | Reference: $reference | Paystack Response: " . json_encode($transaction ?? []));
    $_SESSION['form_message'] = "Payment verification FAILED due to an API error. Please try again.";
    $_SESSION['form_message_type'] = 'error';
    redirect(BASE_URL . 'auth/login.php');
    exit;
} catch (Exception $e) {
    error_log("General Payment Confirmation Error: " . $e->getMessage() . " | Reference: $reference");
    $_SESSION['form_message'] = "An unexpected error occurred during payment verification. Please try again.";
    $_SESSION['form_message_type'] = 'error';
    redirect(BASE_URL . 'auth/login.php');
    exit;
}

// Helper function to format datetime, if not already in functions.php
if (!function_exists('format_datetime')) {
    function format_datetime($datetime_str) {
        if (empty($datetime_str) || $datetime_str === 'N/A') {
            return 'N/A';
        }
        try {
            $date = new DateTime($datetime_str);
            return $date->format('F j, Y, g:i A T'); // Example: July 24, 2025, 6:09 AM WAT
        } catch (Exception $e) {
            error_log("Error formatting datetime: " . $e->getMessage());
            return $datetime_str; // Return original if formatting fails
        }
    }
}
?>