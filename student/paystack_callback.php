<?php
// student/paystack_callback.php

// Handles the callback from Paystack, verifies the transaction, updates payments table,
// sends confirmation email, and redirects appropriately.

// Enable error reporting for debugging.
// In a production environment, you might want to log errors without displaying them.
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set timezone to Africa/Lagos (WAT, UTC+1) for consistent date/time handling.
date_default_timezone_set('Africa/Lagos');

// Require necessary core files.
// These files are expected to define session handling, database connection ($pdo),
// utility functions (like sanitize_input, redirect, isLoggedIn, getUserId, BASE_URL),
// and email sending functionality.
require_once '../includes/session.php';
require_once '../includes/db.php'; // Expected to contain PAYSTACK_SECRET_KEY
require_once '../includes/functions.php'; // Expected to contain sanitize_input, redirect, isLoggedIn, getUserId, BASE_URL
require_once '../includes/send_email.php'; // Expected to contain sendEmail

// Log the start of the callback process for debugging and tracing.
error_log("Paystack Callback: Started processing for reference=" . (isset($_GET['reference']) ? $_GET['reference'] : 'none') . ", quiz_id=" . (isset($_GET['quiz_id']) ? $_GET['quiz_id'] : 'none'));

// Get and sanitize the transaction reference and quiz ID from the URL parameters.
$transaction_reference = sanitize_input($_GET['reference'] ?? null);
$quiz_id_from_callback = sanitize_input($_GET['quiz_id'] ?? null);

// --- Step 1: Validate essential parameters ---
if (empty($transaction_reference) || empty($quiz_id_from_callback)) {
    error_log("Paystack Callback: Missing reference or quiz_id. Redirecting to payment.php.");
    $_SESSION['form_message'] = "Invalid payment callback. Missing transaction reference or quiz ID.";
    $_SESSION['form_message_type'] = 'error';
    // Redirect back to the payment page with the quiz_id if available, otherwise a default.
    redirect(BASE_URL . 'auth/payment.php?quiz_id=' . urlencode($quiz_id_from_callback ?? '') . '&amount=0');
    exit();
}

// Initialize variables to hold payment and user details.
$payment_record = null;
$expected_amount_ngn = 0;
$user_email = '';
$quiz_title = '';
$quiz_description = '';
$quiz_open_datetime = '';
$quiz_duration_minutes = 0;
$quiz_grade = '';
$current_user_id = null;
$assessment_fee = 0; // Initialize assessment_fee for potential redirection

// --- Step 2: Fetch payment and user details from the database ---
try {
    // Prepare a SQL statement to retrieve payment, user, and quiz details.
    // This query uses JOINs to get all necessary information in one go.
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

    // If no payment record is found, log the error and redirect.
    if (!$payment_record) {
        error_log("Paystack Callback: No payment record found for ref {$transaction_reference}, quiz_id {$quiz_id_from_callback}.");
        $_SESSION['form_message'] = "Payment record not found. Please contact support with transaction reference: " . htmlspecialchars($transaction_reference);
        $_SESSION['form_message_type'] = 'error';
        redirect(BASE_URL . 'auth/payment.php?quiz_id=' . urlencode($quiz_id_from_callback) . '&amount=0'); // Default amount 0 if not found
        exit();
    }

    // Populate variables with fetched data.
    $current_user_id = $payment_record['user_id'];
    $user_email = $payment_record['email'];
    $expected_amount_ngn = (float)$payment_record['amount'];
    $quiz_title = htmlspecialchars($payment_record['quiz_title']);
    $quiz_description = htmlspecialchars($payment_record['description']);
    // Format the open datetime for display in the email.
    $quiz_open_datetime = date('F j, Y, g:i a', strtotime($payment_record['open_datetime']));
    $quiz_duration_minutes = (int)$payment_record['duration_minutes'];
    $quiz_grade = htmlspecialchars($payment_record['grade']);
    $assessment_fee = (float)$payment_record['assessment_fee']; // Used for redirection if verification fails

    // Security check: If a user is logged in, ensure their ID matches the payment record's user ID.
    if (isLoggedIn() && $current_user_id != getUserId()) {
        error_log("Paystack Callback: Security Alert: Logged-in user " . getUserId() . " mismatches payment user ID {$current_user_id} for ref {$transaction_reference}.");
        $_SESSION['form_message'] = "Security alert: Mismatch in user IDs for this transaction. Please contact support.";
        $_SESSION['form_message_type'] = 'error';
        redirect(BASE_URL . 'student/dashboard.php'); // Redirect to a safe page like dashboard
        exit();
    }
} catch (PDOException $e) {
    // Log any database errors during fetching.
    error_log("Paystack Callback: DB Error fetching payment record: SQLSTATE[{$e->getCode()}]: " . $e->getMessage());
    $_SESSION['form_message'] = "A database error occurred while fetching payment details. Please try again or contact support.";
    $_SESSION['form_message_type'] = 'error';
    // Redirect back to payment page with relevant quiz_id and assessment_fee.
    redirect(BASE_URL . 'auth/payment.php?quiz_id=' . urlencode($quiz_id_from_callback) . '&amount=' . urlencode($assessment_fee));
    exit();
}

// --- Step 3: Verify payment with Paystack API ---
$ch = curl_init();
// Construct the Paystack verification URL using the transaction reference.
curl_setopt($ch, CURLOPT_URL, "https://api.paystack.co/transaction/verify/" . rawurlencode($transaction_reference));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Return the response as a string.
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer " . PAYSTACK_SECRET_KEY, // Use your Paystack secret key.
    "Cache-Control: no-cache", // Prevent caching of the request.
]);

$response = curl_exec($ch); // Execute the cURL request.
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE); // Get the HTTP status code.
curl_close($ch); // Close the cURL session.

// Initialize flags and messages for the verification outcome.
$verification_successful = false;
$payment_message = "";
$payment_type = "error"; // Default message type is error.

// Handle cURL errors (e.g., network issues).
if ($response === false) {
    error_log("Paystack Callback: cURL Error for ref {$transaction_reference}: " . curl_error($ch));
    $payment_message = "Payment verification failed due to a network error. Please try again later.";
} elseif ($http_code !== 200) {
    // Handle non-200 HTTP responses from Paystack API.
    error_log("Paystack Callback: API Error (HTTP {$http_code}) for ref {$transaction_reference}: " . $response);
    $payment_message = "Payment verification failed. Paystack returned an error (Code: {$http_code}).";
} else {
    // Decode the JSON response from Paystack.
    $result = json_decode($response);

    // Check if the Paystack response indicates a successful transaction.
    if ($result && $result->status && $result->data->status === 'success') {
        $amount_paid_kobo = $result->data->amount; // Amount is in kobo (centavos).
        $gateway_response = $result->data->gateway_response;
        $transaction_date_paystack = $result->data->paid_at;
        $paystack_transaction_id = $result->data->id;
        $paystack_currency = $result->data->currency;
        $amount_paid_ngn = $amount_paid_kobo / 100; // Convert kobo to NGN.

        // Convert expected amount to kobo for accurate comparison.
        $expected_amount_kobo = (int)($expected_amount_ngn * 100);

        // Verify if the paid amount meets or exceeds the expected amount and currency is NGN.
        if ($amount_paid_kobo >= $expected_amount_kobo && $paystack_currency === 'NGN') {
            try {
                // Check if the payment status is already 'completed' in our database.
                if ($payment_record['status'] !== 'completed') {
                    // --- Update payment status in database ---
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

                    // --- Generate or reuse auto-login token for the user ---
                    $stmt_check_token = $pdo->prepare("SELECT auto_login_token FROM users WHERE user_id = :user_id");
                    $stmt_check_token->execute(['user_id' => $current_user_id]);
                    $existing_token = $stmt_check_token->fetchColumn();

                    // Generate a new token if one doesn't exist, or reuse the existing one.
                    $auto_login_token = $existing_token ?: bin2hex(random_bytes(32)); // Always ensure a token exists

                    if (!$existing_token) {
                        $stmt_update_user = $pdo->prepare("
                            UPDATE users
                            SET auto_login_token = :token
                            WHERE user_id = :user_id
                        ");
                        $stmt_update_user->execute([
                            'token' => $auto_login_token,
                            'user_id' => $current_user_id
                        ]);
                    }


                    // --- Send confirmation email if not already sent ---
                    if (!$payment_record['email_sent']) {
                        $auto_login_link = BASE_URL . "auth/auto_login.php?token=" . urlencode($auto_login_token);
                        ob_start(); // Start output buffering to capture email template content.
                        require '../includes/email_templates/assessment_payment_confirmation.php'; // Load the email template.
                        $email_body = ob_get_clean(); // Get the buffered content and clean the buffer.

                        // Replace placeholders in the email body with actual data.
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

                        // Attempt to send the email.
                        if (sendEmail($user_email, $subject, $email_body)) {
                            // If email sent successfully, update the email_sent flag in the payments table.
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
                            // If email sending fails, log and inform the user.
                            error_log("Paystack Callback: Failed to send email to {$user_email} for ref {$transaction_reference}");
                            $payment_message = "Payment successful, but we could not send the confirmation email. Please use the resend option on the confirmation page.";
                            $payment_type = "warning";
                        }
                    } else {
                        // If email was already sent, inform the user.
                        $payment_message = "Payment successful for '{$quiz_title}'! Amount: ₦" . number_format($amount_paid_ngn, 2) . ". A confirmation email was already sent.";
                        $payment_type = "info";
                    }

                    $verification_successful = true; // Mark as successful for redirection.
                } else {
                    // Payment was already completed, inform the user.
                    $payment_message = "This payment has already been successfully recorded. Thank you!";
                    $payment_type = "info";
                    $verification_successful = true; // Still consider it a successful outcome.
                    error_log("Paystack Callback: Payment already completed for ref {$transaction_reference}");
                }
            } catch (PDOException $e) {
                // Handle database update errors after successful Paystack verification.
                error_log("Paystack Callback: DB Update Error for ref {$transaction_reference}: SQLSTATE[{$e->getCode()}]: " . $e->getMessage());
                $payment_message = "Payment verified by Paystack, but a database error occurred while updating your payment. Please contact support with reference: " . htmlspecialchars($transaction_reference);
                $payment_type = "error";
                $verification_successful = false; // Set to false as our internal record is not updated.
            }
        } else {
            // Handle amount or currency mismatch.
            error_log("Paystack Callback: Amount/Currency mismatch for ref {$transaction_reference}. Expected NGN {$expected_amount_ngn}, Paid {$paystack_currency} {$amount_paid_ngn}.");
            $payment_message = "Payment amount or currency mismatch. Please contact support.";
            $payment_type = "error";

            try {
                // Update payment status to 'amount_mismatch' in the database.
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
        // Handle cases where Paystack indicates a failed or non-successful transaction.
        $paystack_data_status = $result->data->status ?? 'unknown';
        $paystack_gateway_response = $result->data->gateway_response ?? "Unknown reason.";
        error_log("Paystack Callback: Transaction failed for ref {$transaction_reference}. Paystack Status: {$paystack_data_status}, Response: {$paystack_gateway_response}");
        $payment_message = "Payment failed. Paystack Status: '{$paystack_data_status}'. Reason: '{$paystack_gateway_response}'. Please try again.";
        $payment_type = "error";

        try {
            // Update payment status to 'failed' in the database.
            $stmt_update_status = $pdo->prepare("
                UPDATE payments
                SET status = 'failed',
                    gateway_response = :paystack_gateway_response,
                    updated_at = NOW()
                WHERE transaction_reference = :reference AND quiz_id = :quiz_id
            ");
            $stmt_update_status->execute([
                'paystack_gateway_response' => $paystack_gateway_response,
                'reference' => $transaction_reference,
                'quiz_id' => $quiz_id_from_callback
            ]);
        } catch (PDOException $e) {
            error_log("Paystack Callback: DB Update Failed Status Error: SQLSTATE[{$e->getCode()}]: " . $e->getMessage());
        }
    }
}

// --- Step 4: Set session message and redirect the user ---
$_SESSION['form_message'] = $payment_message;
$_SESSION['form_message_type'] = $payment_type;

// Redirect based on whether the verification was ultimately successful.
if ($verification_successful) {
    error_log("Paystack Callback: Redirecting to payment_confirmation.php for ref {$transaction_reference}");
    // Redirect to a confirmation page on success.
    redirect(BASE_URL . 'auth/payment_confirmation.php?reference=' . urlencode($transaction_reference) . '&quiz_id=' . urlencode($quiz_id_from_callback));
} else {
    error_log("Paystack Callback: Redirecting to payment.php due to error for ref {$transaction_reference}");
    // Redirect back to the payment page with an error message on failure.
    redirect(BASE_URL . 'auth/payment.php?quiz_id=' . urlencode($quiz_id_from_callback) . '&amount=' . urlencode($assessment_fee));
}
exit(); // Ensure no further code is executed after redirection.

?>