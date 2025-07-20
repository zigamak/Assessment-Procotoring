<?php
// student/paystack_callback.php
// Handles the callback from Paystack, verifies the transaction, updates payments table, sends confirmation email, and redirects appropriately.

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set timezone to Africa/Lagos (WAT, UTC+1)
date_default_timezone_set('Africa/Lagos');

require_once '../includes/session.php';
require_once '../includes/db.php'; // Contains PAYSTACK_SECRET_KEY
require_once '../includes/functions.php'; // Contains sanitize_input, redirect, isLoggedIn, isStudent, getUserId, BASE_URL
require_once '../includes/send_email.php'; // Contains sendEmail

// Log entry to trace execution
error_log("Paystack Callback: Started processing for reference=" . (isset($_GET['reference']) ? $_GET['reference'] : 'none') . ", quiz_id=" . (isset($_GET['quiz_id']) ? $_GET['quiz_id'] : 'none'));
// Get parameters from Paystack callback
$transaction_reference = sanitize_input($_GET['reference'] ?? null);
$quiz_id_from_callback = sanitize_input($_GET['quiz_id'] ?? null);

// If essential parameters are missing, redirect to payment.php with error
if (empty($transaction_reference) || empty($quiz_id_from_callback)) {
    error_log("Paystack Callback: Missing reference or quiz_id. Redirecting to payment.php.");
    $_SESSION['form_message'] = "Invalid payment callback. Missing transaction reference or quiz ID.";
    $_SESSION['form_message_type'] = 'error';
    redirect(BASE_URL . 'auth/payment.php?quiz_id=' . urlencode($quiz_id_from_callback ?? '') . '&amount=0');
    exit();
}

// Fetch payment and user details from database
$payment_record = null;
$expected_amount_ngn = 0;
$user_email = '';
$quiz_title = '';
$quiz_description = '';
$quiz_open_datetime = '';
$quiz_duration_minutes = 0;
$quiz_grade = '';
$current_user_id = null;
$assessment_fee = 0;
try {
    $stmt_payment_check = $pdo->prepare("
        SELECT p.user_id, p.amount, p.status, p.email_sent, u.email, u.username, 
               q.title as quiz_title, q.description, q.open_datetime, q.duration_minutes, q.grade, q.assessment_fee
        FROM payments p
        JOIN users u ON p.user_id = u.user_id
        JOIN quizzes q ON p.quiz_id = q.quiz_id
        WHERE p.transaction_reference = :reference AND p.quiz_id = :quiz_id
    ");
    $stmt_payment_check->execute([
        'reference' => $transaction_reference,
        'quiz_id' => $quiz_id_from_callback
    ]);
    $payment_record = $stmt_payment_check->fetch(PDO::FETCH_ASSOC);

    if (!$payment_record) {
        error_log("Paystack Callback: No payment record found for ref {$transaction_reference}, quiz_id {$quiz_id_from_callback}.");
        $_SESSION['form_message'] = "Payment record not found. Please contact support with transaction reference: " . htmlspecialchars($transaction_reference);
        $_SESSION['form_message_type'] = 'error';
        redirect(BASE_URL . 'auth/payment.php?quiz_id=' . urlencode($quiz_id_from_callback) . '&amount=0');
        exit();
    }

    $current_user_id = $payment_record['user_id'];
    $user_email = $payment_record['email'];
    $expected_amount_ngn = (float)$payment_record['amount'];
    $quiz_title = htmlspecialchars($payment_record['quiz_title']);
    $quiz_description = htmlspecialchars($payment_record['description']);
    $quiz_open_datetime = date('F j, Y, g:i a', strtotime($payment_record['open_datetime']));
    $quiz_duration_minutes = (int)$payment_record['duration_minutes'];
    $quiz_grade = htmlspecialchars($payment_record['grade']);
    $assessment_fee = (float)$payment_record['assessment_fee'];

    // Verify user ID for logged-in users
    if (isLoggedIn() && $current_user_id != getUserId()) {
        error_log("Paystack Callback: Security Alert: Logged-in user " . getUserId() . " mismatches payment user ID {$current_user_id} for ref {$transaction_reference}.");
        $_SESSION['form_message'] = "Security alert: Mismatch in user IDs for this transaction. Please contact support.";
        $_SESSION['form_message_type'] = 'error';
        redirect(BASE_URL . 'student/dashboard.php');
        exit();
    }
} catch (PDOException $e) {
    error_log("Paystack Callback: DB Error fetching payment record: SQLSTATE[{$e->getCode()}]: " . $e->getMessage());
    $_SESSION['form_message'] = "A database error occurred while fetching payment details. Please try again or contact support.";
    $_SESSION['form_message_type'] = 'error';
    redirect(BASE_URL . 'auth/payment.php?quiz_id=' . urlencode($quiz_id_from_callback) . '&amount=' . urlencode($assessment_fee));
    exit();
}

// Verify payment with Paystack
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://api.paystack.co/transaction/verify/" . rawurlencode($transaction_reference));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer " . PAYSTACK_SECRET_KEY,
    "Cache-Control: no-cache",
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$verification_successful = false;
$payment_message = "";
$payment_type = "error";

if ($response === false) {
    error_log("Paystack Callback: cURL Error for ref {$transaction_reference}: " . curl_error($ch));
    $payment_message = "Payment verification failed due to a network error. Please try again later.";
} elseif ($http_code !== 200) {
    error_log("Paystack Callback: API Error (HTTP {$http_code}) for ref {$transaction_reference}: " . $response);
    $payment_message = "Payment verification failed. Paystack returned an error (Code: {$http_code}).";
} else {
    $result = json_decode($response);

    if ($result && $result->status && $result->data->status === 'success') {
        $amount_paid_kobo = $result->data->amount;
        $gateway_response = $result->data->gateway_response;
        $transaction_date_paystack = $result->data->paid_at;
        $paystack_transaction_id = $result->data->id;
        $paystack_currency = $result->data->currency;
        $amount_paid_ngn = $amount_paid_kobo / 100;

        $expected_amount_kobo = (int)($expected_amount_ngn * 100);

        if ($amount_paid_kobo >= $expected_amount_kobo && $paystack_currency === 'NGN') {
            try {
                if ($payment_record['status'] !== 'completed') {
                    // Update payment status
                    $stmt_update_payment = $pdo->prepare("
                        UPDATE payments
                        SET status = 'completed',
                            amount = :amount,
                            payment_date = :payment_date,
                            paystack_transaction_id = :paystack_transaction_id,
                            gateway_response = :gateway_response,
                            updated_at = NOW()
                        WHERE transaction_reference = :reference AND quiz_id = :quiz_id
                    ");
                    $stmt_update_payment->execute([
                        'amount' => $amount_paid_ngn,
                        'payment_date' => $transaction_date_paystack,
                        'paystack_transaction_id' => $paystack_transaction_id,
                        'gateway_response' => $gateway_response,
                        'reference' => $transaction_reference,
                        'quiz_id' => $quiz_id_from_callback
                    ]);

                    // Generate or reuse auto-login token
                    $stmt_check_token = $pdo->prepare("SELECT auto_login_token FROM users WHERE user_id = :user_id");
                    $stmt_check_token->execute(['user_id' => $current_user_id]);
                    $existing_token = $stmt_check_token->fetchColumn();

                    if (!$existing_token) {
                        $auto_login_token = bin2hex(random_bytes(32));
                        $stmt_update_user = $pdo->prepare("
                            UPDATE users
                            SET auto_login_token = :token
                            WHERE user_id = :user_id
                        ");
                        $stmt_update_user->execute([
                            'token' => $auto_login_token,
                            'user_id' => $current_user_id
                        ]);
                    } else {
                        $auto_login_token = $existing_token;
                    }

                    // Send confirmation email if not sent
                    if (!$payment_record['email_sent']) {
                        $auto_login_link = BASE_URL . "auth/auto_login.php?token=" . urlencode($auto_login_token);
                        ob_start();
                        require '../includes/email_templates/assessment_reminder_email.php';
                        $email_body = ob_get_clean();

                        $email_body = str_replace('{{subject}}', "Your Mackenny Assessment Payment Confirmation", $email_body);
                        $email_body = str_replace('{{username}}', htmlspecialchars($payment_record['username']), $email_body);
                        $email_body = str_replace('{{email}}', htmlspecialchars($user_email), $email_body);
                        $email_body = str_replace('{{auto_login_link}}', htmlspecialchars($auto_login_link), $email_body);
                        $email_body = str_replace('{{quiz_title}}', $quiz_title, $email_body);
                        $email_body = str_replace('{{description}}', $quiz_description, $email_body);
                        $email_body = str_replace('{{open_datetime}}', $quiz_open_datetime, $email_body);
                        $email_body = str_replace('{{duration_minutes}}', $quiz_duration_minutes, $email_body);
                        $email_body = str_replace('{{grade}}', $quiz_grade, $email_body);
                        $email_body = str_replace('{{amount}}', number_format($amount_paid_ngn, 2), $email_body);
                        $email_body = str_replace('{{transaction_reference}}', htmlspecialchars($transaction_reference), $email_body);
                        $email_body = str_replace('{{payment_date}}', date('F j, Y, g:i a', strtotime($transaction_date_paystack)), $email_body);
                        $email_body = str_replace('{{message}}', "Thank you for your payment! You can access your assessment using the link below. You will receive a reminder three days before the assessment opens.", $email_body);

                        $subject = "Your Mackenny Assessment Payment Confirmation";

                        if (sendEmail($user_email, $subject, $email_body)) {
                            $stmt_update_email_sent = $pdo->prepare("
                                UPDATE payments
                                SET email_sent = 1
                                WHERE transaction_reference = :reference AND quiz_id = :quiz_id
                            ");
                            $stmt_update_email_sent->execute([
                                'reference' => $transaction_reference,
                                'quiz_id' => $quiz_id_from_callback
                            ]);
                            error_log("Paystack Callback: Email sent successfully to {$user_email} for ref {$transaction_reference}");
                            $payment_message = "Payment successful for '{$quiz_title}'! Amount: ₦" . number_format($amount_paid_ngn, 2) . ". A confirmation email has been sent.";
                            $payment_type = "success";
                        } else {
                            error_log("Paystack Callback: Failed to send email to {$user_email} for ref {$transaction_reference}");
                            $payment_message = "Payment successful, but we could not send the confirmation email. Please use the resend option on the confirmation page.";
                            $payment_type = "warning";
                        }
                    } else {
                        $payment_message = "Payment successful for '{$quiz_title}'! Amount: ₦" . number_format($amount_paid_ngn, 2) . ". A confirmation email was already sent.";
                        $payment_type = "success";
                    }

                    $verification_successful = true;
                } else {
                    $payment_message = "This payment has already been successfully recorded. Thank you!";
                    $payment_type = "info";
                    $verification_successful = true;
                    error_log("Paystack Callback: Payment already completed for ref {$transaction_reference}");
                }
            } catch (PDOException $e) {
                error_log("Paystack Callback: DB Update Error for ref {$transaction_reference}: SQLSTATE[{$e->getCode()}]: " . $e->getMessage());
                $payment_message = "Payment verified by Paystack, but a database error occurred while updating your payment. Please contact support with reference: " . htmlspecialchars($transaction_reference);
                $payment_type = "error";
                $verification_successful = false;
            }
        } else {
            error_log("Paystack Callback: Amount/Currency mismatch for ref {$transaction_reference}. Expected NGN {$expected_amount_ngn}, Paid {$paystack_currency} {$amount_paid_ngn}.");
            $payment_message = "Payment amount or currency mismatch. Please contact support.";
            $payment_type = "error";

            try {
                $stmt_update_status = $pdo->prepare("
                    UPDATE payments
                    SET status = 'amount_mismatch',
                        gateway_response = :gateway_response,
                        updated_at = NOW()
                    WHERE transaction_reference = :reference AND quiz_id = :quiz_id
                ");
                $stmt_update_status->execute([
                    'gateway_response' => $gateway_response,
                    'reference' => $transaction_reference,
                    'quiz_id' => $quiz_id_from_callback
                ]);
            } catch (PDOException $e) {
                error_log("Paystack Callback: DB Update Amount Mismatch Error: SQLSTATE[{$e->getCode()}]: " . $e->getMessage());
            }
        }
    } else {
        $paystack_data_status = $result->data->status ?? 'unknown';
        $paystack_gateway_response = $result->data->gateway_response ?? "Unknown reason.";
        error_log("Paystack Callback: Transaction failed for ref {$transaction_reference}. Paystack Status: {$paystack_data_status}, Response: {$paystack_gateway_response}");
        $payment_message = "Payment failed. Paystack Status: '{$paystack_data_status}'. Reason: '{$paystack_gateway_response}'. Please try again.";
        $payment_type = "error";

        try {
            $stmt_update_status = $pdo->prepare("
                UPDATE payments
                SET status = 'failed',
                    gateway_response = :gateway_response,
                    updated_at = NOW()
                WHERE transaction_reference = :reference AND quiz_id = :quiz_id
            ");
            $stmt_update_status->execute([
                'gateway_response' => $paystack_gateway_response,
                'reference' => $transaction_reference,
                'quiz_id' => $quiz_id_from_callback
            ]);
        } catch (PDOException $e) {
            error_log("Paystack Callback: DB Update Failed Status Error: SQLSTATE[{$e->getCode()}]: " . $e->getMessage());
        }
    }
}

// Set session message
$_SESSION['form_message'] = $payment_message;
$_SESSION['form_message_type'] = $payment_type;

// Redirect based on verification status
if ($verification_successful) {
    error_log("Paystack Callback: Redirecting to payment_confirmation.php for ref {$transaction_reference}");
    redirect(BASE_URL . 'auth/payment_confirmation.php?reference=' . urlencode($transaction_reference) . '&quiz_id=' . urlencode($quiz_id_from_callback));
} else {
    error_log("Paystack Callback: Redirecting to payment.php due to error for ref {$transaction_reference}");
    redirect(BASE_URL . 'auth/payment.php?quiz_id=' . urlencode($quiz_id_from_callback) . '&amount=' . urlencode($assessment_fee));
}
exit();
?>