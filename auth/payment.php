<?php
// auth/payment.php
// Payment processing page that accepts user details in URL and proceeds to Paystack payment

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set timezone to Africa/Lagos (WAT, UTC+1)
date_default_timezone_set('Africa/Lagos');

require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Ensure PAYSTACK_PUBLIC_KEY is defined
if (!defined('PAYSTACK_PUBLIC_KEY')) {
    error_log("PAYSTACK_PUBLIC_KEY is not defined.");
    $_SESSION['form_message'] = "Payment gateway not configured. Please contact support.";
    $_SESSION['form_message_type'] = 'error';
    redirect(BASE_URL . 'index.php');
    exit;
}

// Get parameters from URL
$quiz_id = sanitize_input($_GET['quiz_id'] ?? 0);
$amount = sanitize_input($_GET['amount'] ?? 0);
$user_id = sanitize_input($_GET['user_id'] ?? 0);
$email = sanitize_input($_GET['email'] ?? '');
$first_name = sanitize_input($_GET['first_name'] ?? '');
$last_name = sanitize_input($_GET['last_name'] ?? '');

// Validate required parameters
if (empty($quiz_id) || empty($amount) || empty($user_id) || empty($email)) {
    error_log("Payment: Missing required parameters. Quiz ID: $quiz_id, Amount: $amount, User ID: $user_id, Email: $email");
    $_SESSION['form_message'] = "Invalid payment link. Please request a new payment link.";
    $_SESSION['form_message_type'] = 'error';
    redirect(BASE_URL . 'index.php');
    exit;
}

// Verify user exists and details match
try {
    $stmt = $pdo->prepare("SELECT user_id, email, first_name, last_name FROM users WHERE user_id = :user_id");
    $stmt->execute(['user_id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || $user['email'] !== $email) {
        error_log("Payment: User verification failed for user_id: $user_id, email provided: $email, email in DB: " . ($user['email'] ?? 'N/A'));
        $_SESSION['form_message'] = "User verification failed. Please log in and try again.";
        $_SESSION['form_message_type'] = 'error';
        redirect(BASE_URL . 'index.php');
        exit;
    }

    // Update first_name/last_name in the $user array if they were provided in URL but potentially missing in DB
    // This ensures the correct name is used for display and Paystack metadata.
    if (!empty($first_name)) {
        $user['first_name'] = $first_name;
    }
    if (!empty($last_name)) {
        $user['last_name'] = $last_name;
    }

} catch (PDOException $e) {
    error_log("Payment: DB Error verifying user: " . $e->getMessage());
    $_SESSION['form_message'] = "Database error during user verification. Please try again.";
    $_SESSION['form_message_type'] = 'error';
    redirect(BASE_URL . 'index.php');
    exit;
}

// Verify quiz exists and amount matches
try {
    $stmt = $pdo->prepare("SELECT title, assessment_fee FROM quizzes WHERE quiz_id = :quiz_id");
    $stmt->execute(['quiz_id' => $quiz_id]);
    $quiz = $stmt->fetch(PDO::FETCH_ASSOC);

    // Convert amount from URL and assessment_fee from DB to float for accurate comparison
    $url_amount_float = (float)$amount;
    $quiz_fee_float = (float)($quiz['assessment_fee'] ?? 0); // Default to 0 if $quiz['assessment_fee'] is not set

    if (!$quiz || $quiz_fee_float !== $url_amount_float) {
        error_log("Payment: Quiz verification failed for quiz_id: $quiz_id. Provided amount: $amount, DB fee: " . ($quiz['assessment_fee'] ?? 'N/A'));
        $_SESSION['form_message'] = "Assessment fee verification failed. Please request a new payment link.";
        $_SESSION['form_message_type'] = 'error';
        redirect(BASE_URL . 'student/assessments.php');
        exit;
    }

    $quiz_title = htmlspecialchars($quiz['title']);
} catch (PDOException $e) {
    error_log("Payment: DB Error verifying quiz: " . $e->getMessage());
    $_SESSION['form_message'] = "Database error during quiz verification. Please try again.";
    $_SESSION['form_message_type'] = 'error';
    redirect(BASE_URL . 'student/assessments.php');
    exit;
}

// Create payment record in database
try {
    $transaction_reference = 'MACK' . time() . rand(1000, 9999);
    
    $stmt = $pdo->prepare("INSERT INTO payments 
                            (user_id, quiz_id, amount, transaction_reference, status, created_at) 
                            VALUES (:user_id, :quiz_id, :amount, :reference, 'pending', NOW())");
    $stmt->execute([
        'user_id' => $user_id,
        'quiz_id' => $quiz_id,
        'amount' => $amount,
        'reference' => $transaction_reference
    ]);
} catch (PDOException $e) {
    error_log("Payment: DB Error creating payment record: " . $e->getMessage());
    $_SESSION['form_message'] = "Payment initialization failed. Please try again.";
    $_SESSION['form_message_type'] = 'error';
    redirect(BASE_URL . 'student/assessments.php');
    exit;
}

// Set user session (optional - if you want to auto-login or ensure they are logged in)
$_SESSION['user_id'] = $user_id;
$_SESSION['user_role'] = 'student'; // Assuming this is always for students
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Processing Payment - Mackenny Assessment</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://js.paystack.co/v1/inline.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" integrity="sha512-Fo3rlalDLsWzS/KxW+q9s+6e4f3sM+3X4fP2f/K2y2Xv2q+sPz1+K6aN+6q3/W8x+Q+F+S+A+G+H+T+Q==" crossorigin="anonymous" referrerpolicy="no-referrer" />
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
            <div class="text-left mb-6">
                <p class="text-gray-700 mb-2"><span class="font-semibold">Assessment:</span> <?php echo $quiz_title; ?></p>
                <p class="text-gray-700 mb-2"><span class="font-semibold">Amount:</span> ₦<?php echo number_format($amount, 2); ?></p>
                <p class="text-gray-700 mb-2"><span class="font-semibold">Student:</span> <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></p>
                <p class="text-gray-700"><span class="font-semibold">Email:</span> <?php echo htmlspecialchars($email); ?></p>
            </div>
            <button id="paystack-button" class="bg-navy-900 hover:bg-navy-700 text-white font-semibold py-3 px-6 rounded-lg shadow-md focus:outline-none focus:ring-2 focus:ring-navy-900 focus:ring-offset-2 transition duration-200">
                Pay Now
            </button>
            <p class="text-gray-500 text-sm mt-4">You'll be redirected to Paystack to complete your payment securely.</p>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const paystackButton = document.getElementById('paystack-button');
            
            paystackButton.addEventListener('click', function() {
                paystackButton.disabled = true;
                // Add a spinner icon from Font Awesome
                paystackButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing...';
                
                const handler = PaystackPop.setup({
                    key: '<?php echo PAYSTACK_PUBLIC_KEY; ?>',
                    email: '<?php echo htmlspecialchars($email); ?>',
                    amount: <?php echo (int)($amount * 100); ?>, // Amount in kobo
                    currency: 'NGN',
                    ref: '<?php echo htmlspecialchars($transaction_reference); ?>',
                    metadata: {
                        custom_fields: [
                            {
                                display_name: "Student Name",
                                variable_name: "student_name",
                                value: "<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>"
                            },
                            {
                                display_name: "Assessment",
                                variable_name: "assessment",
                                value: "<?php echo htmlspecialchars($quiz_title); ?>"
                            },
                            {
                                display_name: "User ID",
                                variable_name: "user_id",
                                value: "<?php echo htmlspecialchars($user_id); ?>"
                            },
                            {
                                display_name: "Quiz ID",
                                variable_name: "quiz_id",
                                value: "<?php echo htmlspecialchars($quiz_id); ?>"
                            }
                        ]
                    },
                    callback: function(response) {
                        // Redirect to payment confirmation page after successful payment initiation
                        window.location.href = '<?php echo BASE_URL; ?>auth/payment_confirmation.php?reference=' +
                            encodeURIComponent(response.reference) + '&quiz_id=' + encodeURIComponent('<?php echo $quiz_id; ?>');
                    },
                    onClose: function() {
                        // Re-enable button if the popup is closed without completing payment
                        paystackButton.disabled = false;
                        paystackButton.innerHTML = 'Pay Now';
                    }
                });
                handler.openIframe();
            });
            
            // Auto-trigger payment popup after 1 second (optional but good for user experience)
            setTimeout(function() {
                paystackButton.click();
            }, 1000);
        });
    </script>
</body>
</html>