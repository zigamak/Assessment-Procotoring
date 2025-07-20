<?php
// auth/paystack_payment.php
// Initiates Paystack payment popup and redirects to paystack_callback.php.

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set timezone to Africa/Lagos (WAT, UTC+1)
date_default_timezone_set('Africa/Lagos');

require_once '../includes/session.php';
require_once '../includes/db.php'; // Contains PAYSTACK_PUBLIC_KEY
require_once '../includes/functions.php'; // Contains sanitize_input, redirect, BASE_URL

// Log entry
error_log("Paystack Payment: Started processing for payment_data=" . json_encode($_SESSION['payment_data'] ?? []));

// Check for payment data in session
if (!isset($_SESSION['payment_data']) || empty($_SESSION['payment_data']['user_id']) || empty($_SESSION['payment_data']['quiz_id']) || empty($_SESSION['payment_data']['transaction_reference']) || empty($_SESSION['payment_data']['email']) || empty($_SESSION['payment_data']['amount'])) {
    error_log("Paystack Payment: Missing payment data in session. Redirecting to assessments.php");
    $_SESSION['form_message'] = "Invalid payment initiation. Please try again.";
    $_SESSION['form_message_type'] = 'error';
    redirect(BASE_URL . 'student/assessments.php');
    exit();
}

$payment_data = $_SESSION['payment_data'];
$user_id = $payment_data['user_id'];
$quiz_id = $payment_data['quiz_id'];
$transaction_reference = $payment_data['transaction_reference'];
$email = $payment_data['email'];
$amount = $payment_data['amount'];

// Fetch quiz title for display
$quiz_title = 'Unknown Assessment';
try {
    $stmt = $pdo->prepare("SELECT title FROM quizzes WHERE quiz_id = :quiz_id");
    $stmt->execute(['quiz_id' => $quiz_id]);
    $quiz_title = htmlspecialchars($stmt->fetchColumn() ?: 'Unknown Assessment');
} catch (PDOException $e) {
    error_log("Paystack Payment: DB Error fetching quiz title: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Processing Payment - Mackenny Assessment</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://js.paystack.co/v1/inline.js"></script>
    <style>
        .bg-navy-900 { background-color: #0a1930; }
        .hover\:bg-navy-700:hover { background-color: #2c3e6a; }
        .focus\:ring-navy-900:focus { --tw-ring-color: #0a1930; }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-100 to-blue-50 min-h-screen flex items-center justify-center">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-md mx-auto bg-white rounded-xl shadow-2xl p-8 text-center">
            <h1 class="text-2xl font-bold text-gray-800 mb-4">Processing Your Payment</h1>
            <p class="text-gray-600 mb-6">Please complete the payment for "<?php echo $quiz_title; ?>" (₦<?php echo number_format($amount, 2); ?>).</p>
            <button id="paystack-button" class="bg-navy-900 hover:bg-navy-700 text-white font-semibold py-3 px-6 rounded-lg shadow-md focus:outline-none focus:ring-2 focus:ring-navy-900 focus:ring-offset-2 transition duration-200">
                Pay Now
            </button>
        </div>
    </div>

    <script>
        document.getElementById('paystack-button').addEventListener('click', function() {
            const handler = PaystackPop.setup({
                key: '<?php echo PAYSTACK_PUBLIC_KEY; ?>',
                email: '<?php echo htmlspecialchars($email); ?>',
                amount: <?php echo (int)($amount * 100); ?>,
                currency: 'NGN',
                ref: '<?php echo htmlspecialchars($transaction_reference); ?>',
                metadata: {
                    quiz_id: '<?php echo htmlspecialchars($quiz_id); ?>'
                },
                callback: function(response) {
                    // Log callback
                    console.log('Paystack Callback: Reference=' + response.reference);
                    // Redirect to paystack_callback.php
                    window.location.href = '<?php echo BASE_URL; ?>student/paystack_callback.php?reference=' + encodeURIComponent(response.reference) + '&quiz_id=' + encodeURIComponent('<?php echo $quiz_id; ?>');
                },
                onClose: function() {
                    // Redirect to payment.php on close
                    console.log('Paystack Payment: Window closed');
                    window.location.href = '<?php echo BASE_URL; ?>auth/payment.php?quiz_id=' + encodeURIComponent('<?php echo $quiz_id; ?>') + '&amount=' + encodeURIComponent('<?php echo $amount; ?>');
                }
            });
            handler.openIframe();
        });
    </script>
</body>
</html>
<?php
// Clean up session data
unset($_SESSION['payment_data']);
?>