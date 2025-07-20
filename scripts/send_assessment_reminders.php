<?php
// scripts/send_assessment_reminders.php
// Sends reminder emails to users 3 days before the assessment open_datetime for completed payments.

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Disable display for cron job
ini_set('log_errors', 1);
ini_set('error_log', 'C:/xampp/php/logs/php_error_log');

// Set timezone to Africa/Lagos (WAT, UTC+1)
date_default_timezone_set('Africa/Lagos');

require_once '../includes/db.php'; // Contains PDO connection
require_once '../includes/functions.php'; // Contains sanitize_input, BASE_URL
require_once '../includes/send_email.php'; // Contains sendEmail

// Log script execution
error_log("Assessment Reminders: Started at " . date('Y-m-d H:i:s'));

try {
    // Calculate date 3 days from now
    $three_days_from_now = date('Y-m-d 00:00:00', strtotime('+1 days'));
    $three_days_from_now_end = date('Y-m-d 23:59:59', strtotime('+1 days'));

    // Fetch users with completed payments for quizzes opening in 3 days
    $stmt = $pdo->prepare("
        SELECT p.user_id, p.quiz_id, p.transaction_reference, p.amount, p.payment_date,
               u.email, u.username, u.auto_login_token,
               q.title as quiz_title, q.description, q.open_datetime, q.duration_minutes, q.grade
        FROM payments p
        JOIN users u ON p.user_id = u.user_id
        JOIN quizzes q ON p.quiz_id = q.quiz_id
        WHERE p.status = 'completed'
        AND q.open_datetime BETWEEN :start_date AND :end_date
    ");
    $stmt->execute([
        'start_date' => $three_days_from_now,
        'end_date' => $three_days_from_now_end
    ]);
    $reminders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($reminders)) {
        error_log("Assessment Reminders: No assessments due in 3 days.");
        exit();
    }

    foreach ($reminders as $reminder) {
        $user_id = $reminder['user_id'];
        $quiz_id = $reminder['quiz_id'];
        $transaction_reference = $reminder['transaction_reference'];
        $user_email = $reminder['email'];
        $quiz_title = htmlspecialchars($reminder['quiz_title']);
        $quiz_description = htmlspecialchars($reminder['description']);
        $quiz_open_datetime = date('F j, Y, g:i a', strtotime($reminder['open_datetime']));
        $quiz_duration_minutes = (int)$reminder['duration_minutes'];
        $quiz_grade = htmlspecialchars($reminder['grade']);
        $amount = number_format($reminder['amount'], 2);
        $payment_date = date('F j, Y, g:i a', strtotime($reminder['payment_date']));
        $auto_login_token = $reminder['auto_login_token'];

        // Generate new token if none exists
        if (!$auto_login_token) {
            $auto_login_token = bin2hex(random_bytes(32));
            $stmt_update_user = $pdo->prepare("
                UPDATE users
                SET auto_login_token = :token
                WHERE user_id = :user_id
            ");
            $stmt_update_user->execute([
                'token' => $auto_login_token,
                'user_id' => $user_id
            ]);
            error_log("Assessment Reminders: Generated new auto_login_token for user_id {$user_id}");
        }

        // Prepare email
        $auto_login_link = BASE_URL . "auth/auto_login.php?token=" . urlencode($auto_login_token);
        ob_start();
        require '../includes/email_templates/assessment_reminder_email.php';
        $email_body = ob_get_clean();

        $email_body = str_replace('{{subject}}', "Reminder: Your Mackenny Assessment is Approaching", $email_body);
        $email_body = str_replace('{{username}}', htmlspecialchars($reminder['username']), $email_body);
        $email_body = str_replace('{{email}}', htmlspecialchars($user_email), $email_body);
        $email_body = str_replace('{{auto_login_link}}', htmlspecialchars($auto_login_link), $email_body);
        $email_body = str_replace('{{quiz_title}}', $quiz_title, $email_body);
        $email_body = str_replace('{{description}}', $quiz_description, $email_body);
        $email_body = str_replace('{{open_datetime}}', $quiz_open_datetime, $email_body);
        $email_body = str_replace('{{duration_minutes}}', $quiz_duration_minutes, $email_body);
        $email_body = str_replace('{{grade}}', $quiz_grade, $email_body);
        $email_body = str_replace('{{amount}}', $amount, $email_body);
        $email_body = str_replace('{{transaction_reference}}', htmlspecialchars($transaction_reference), $email_body);
        $email_body = str_replace('{{payment_date}}', $payment_date, $email_body);
        $email_body = str_replace('{{message}}', "Your assessment '{$quiz_title}' is scheduled to open in three days. Please prepare and access it using the link below.", $email_body);

        $subject = "Reminder: Your Mackenny Assessment is Approaching";

        if (sendEmail($user_email, $subject, $email_body)) {
            error_log("Assessment Reminders: Reminder email sent to {$user_email} for quiz_id {$quiz_id}, ref {$transaction_reference}");
        } else {
            error_log("Assessment Reminders: Failed to send reminder email to {$user_email} for quiz_id {$quiz_id}, ref {$transaction_reference}");
        }
    }

    error_log("Assessment Reminders: Completed sending " . count($reminders) . " reminders.");
} catch (PDOException $e) {
    error_log("Assessment Reminders: DB Error: SQLSTATE[{$e->getCode()}]: " . $e->getMessage());
    exit(1);
}

exit();
?>