<?php
// student/paystack_callback.php
// Handles the callback from Paystack and verifies the transaction.

// Temporarily enable all error reporting for debugging this specific issue
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../includes/session.php';
require_once '../includes/db.php'; // Contains Paystack keys
require_once '../includes/functions.php';

// Ensure the user is logged in as a student
if (!isLoggedIn() || !isStudent()) {
    redirect(BASE_URL . 'auth/login.php'); // Redirect to login if not authenticated or not a student
    exit();
}

$user_id = getUserId();
$transaction_reference = $_GET['reference'] ?? null;
$quiz_id_from_callback = $_GET['quiz_id'] ?? null; // Passed from make_payment.php JS callback

// Initialize session variables for payment status page with a default error state
$_SESSION['payment_status'] = [
    'type' => 'error',
    'message' => 'An unexpected error occurred during payment verification. Please check your dashboard or contact support.',
    'quiz_title' => 'Unknown Assessment',
    'amount_paid' => 'N/A',
    'transaction_reference' => $transaction_reference,
    'quiz_id' => $quiz_id_from_callback,
    'redirect_dashboard' => BASE_URL . 'student/dashboard.php'
];

if (empty($transaction_reference) || empty($quiz_id_from_callback)) {
    $_SESSION['payment_status']['message'] = "Invalid payment callback. Missing transaction reference or quiz ID.";
    redirect(BASE_URL . 'student/payment_status.php'); // Redirect to the new status page
    exit();
}

// Fetch quiz title early for consistent display, even on error
$quiz_title = 'Unknown Assessment';
try {
    $stmt_quiz_title = $pdo->prepare("SELECT title FROM quizzes WHERE quiz_id = :quiz_id");
    $stmt_quiz_title->execute(['quiz_id' => $quiz_id_from_callback]);
    $fetched_quiz_title = $stmt_quiz_title->fetchColumn();
    if ($fetched_quiz_title) {
        $quiz_title = htmlspecialchars($fetched_quiz_title);
        $_SESSION['payment_status']['quiz_title'] = $quiz_title;
    }
} catch (PDOException $e) {
    error_log("Error fetching quiz title in paystack_callback: " . $e->getMessage());
}


// --- VERIFY PAYMENT WITH PAYSTACK ---
$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, "https://api.paystack.co/transaction/verify/" . rawurlencode($transaction_reference));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer " . PAYSTACK_SECRET_KEY, // Use your SECRET KEY here, defined in db.php
    "Cache-Control: no-cache",
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE); // Get HTTP status code
curl_close($ch);

if ($response === false) {
    error_log("Paystack cURL Error: " . curl_error($ch));
    $_SESSION['payment_status']['message'] = "Payment verification failed due to a network error. Please try again later.";
} elseif ($http_code !== 200) {
    error_log("Paystack API Error (HTTP {$http_code}): " . $response);
    $_SESSION['payment_status']['message'] = "Payment verification failed. Paystack returned an error (Code: {$http_code}).";
} else {
    $result = json_decode($response);

    if ($result && $result->status && $result->data->status === 'success') {
        // Payment was successful! Now, verify details.
        $amount_paid_kobo = $result->data->amount; // Amount in kobo
        $gateway_response = $result->data->gateway_response;
        $transaction_date = $result->data->paid_at; // Use Paystack's paid_at timestamp
        $paystack_currency = $result->data->currency; // Get currency from Paystack

        try {
            // Fetch the expected amount for the quiz from your database
            $stmt_quiz_fee = $pdo->prepare("SELECT assessment_fee FROM quizzes WHERE quiz_id = :quiz_id");
            $stmt_quiz_fee->execute(['quiz_id' => $quiz_id_from_callback]);
            $quiz_data = $stmt_quiz_fee->fetch(PDO::FETCH_ASSOC);

            if (!$quiz_data) {
                error_log("Paystack Callback: Quiz ID {$quiz_id_from_callback} not found in DB.");
                $_SESSION['payment_status']['message'] = "Payment processed, but the linked assessment was not found. Please contact support.";
            } else {
                $expected_amount_ngn = (float)$quiz_data['assessment_fee'];
                $expected_amount_kobo = (int)($expected_amount_ngn * 100); // Convert to kobo

                // Calculate amount in NGN for logging, outside the string interpolation for clarity
                $amount_paid_ngn = $amount_paid_kobo / 100;

                // Check if paid amount is sufficient AND currency matches
                if ($amount_paid_kobo >= $expected_amount_kobo && $paystack_currency === 'NGN') {
                    // Payment is verified and amount matches or exceeds!
                    // Update your database to mark the payment as completed.

                    // Check if a payment record with this transaction reference already exists and is 'completed'
                    $stmt_check_existing = $pdo->prepare("SELECT status FROM payments WHERE transaction_reference = :ref AND user_id = :user_id");
                    $stmt_check_existing->execute(['ref' => $transaction_reference, 'user_id' => $user_id]);
                    $existing_payment = $stmt_check_existing->fetch(PDO::FETCH_ASSOC);

                    if ($existing_payment && $existing_payment['status'] === 'completed') {
                        $_SESSION['payment_status']['type'] = 'info';
                        $_SESSION['payment_status']['message'] = "This payment has already been successfully recorded. Thank you!";
                        $_SESSION['payment_status']['amount_paid'] = '₦' . number_format($amount_paid_ngn, 2); // Use the calculated NGN amount
                    } else {
                        // Update the existing 'pending' record or insert a new one if it somehow got missed
                        // The ON DUPLICATE KEY UPDATE is useful if you have a unique constraint on (user_id, quiz_id, transaction_reference)
                        // or just on transaction_reference if you expect unique references for all payments.
                        $stmt_update_payment = $pdo->prepare("
                            INSERT INTO payments (user_id, quiz_id, amount, status, transaction_reference, payment_date, gateway_response)
                            VALUES (:user_id, :quiz_id, :amount, 'completed', :transaction_reference, :payment_date, :gateway_response)
                            ON DUPLICATE KEY UPDATE 
                                status = 'completed', 
                                amount = VALUES(amount), 
                                payment_date = VALUES(payment_date),
                                gateway_response = VALUES(gateway_response)
                        ");

                        $stmt_update_payment->execute([
                            'user_id' => $user_id,
                            'quiz_id' => $quiz_id_from_callback,
                            'amount' => $amount_paid_ngn, // Store in NGN
                            'transaction_reference' => $transaction_reference,
                            'payment_date' => $transaction_date,
                            'gateway_response' => $gateway_response
                        ]);

                        $_SESSION['payment_status']['type'] = 'success';
                        $_SESSION['payment_status']['message'] = "Payment successful! You can now take the assessment for '{$quiz_title}'.";
                        $_SESSION['payment_status']['amount_paid'] = '₦' . number_format($amount_paid_ngn, 2);
                    }
                } else {
                    // Amount or Currency mismatch
                    error_log("Paystack Callback: Amount/Currency mismatch for ref {$transaction_reference}. Expected NGN {$expected_amount_ngn}, Paid {$paystack_currency} {$amount_paid_ngn}.");
                    $_SESSION['payment_status']['message'] = "Payment amount or currency mismatch. Please contact support immediately.";

                    // Optionally update payment status to 'failed' or 'amount_mismatch'
                    // This assumes a 'pending' record exists to update
                    $stmt_update_status = $pdo->prepare("UPDATE payments SET status = 'amount_mismatch', gateway_response = :gateway_response WHERE transaction_reference = :ref");
                    $stmt_update_status->execute(['gateway_response' => $gateway_response, 'ref' => $transaction_reference]);
                }
            }
        } catch (PDOException $e) {
            error_log("Paystack Callback DB Error: " . $e->getMessage());
            $_SESSION['payment_status']['message'] = "Payment processed, but there was a database error recording it. Please contact support.";
        }
    } else {
        // Transaction not successful or unknown status from Paystack
        error_log("Paystack Callback: Transaction not successful for ref {$transaction_reference}. Data: " . print_r($result, true));
        $paystack_error = $result->data->gateway_response ?? "Unknown reason.";
        $_SESSION['payment_status']['message'] = "Payment failed. Reason: {$paystack_error}. Please try again.";
        // Optionally update payment status to 'failed'
        try {
            $stmt_update_status = $pdo->prepare("UPDATE payments SET status = 'failed', gateway_response = :gateway_response WHERE transaction_reference = :ref");
            $stmt_update_status->execute(['gateway_response' => $gateway_response ?? 'N/A', 'ref' => $transaction_reference]);
        } catch (PDOException $e) {
            error_log("Paystack Callback DB Update Failed Status Error: " . $e->getMessage());
        }
    }
}

// Always redirect to the payment status page
redirect(BASE_URL . 'student/payment_status.php');
exit();