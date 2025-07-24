<?php
// auth/payment_confirmation.php
// Displays payment confirmation details after the Paystack callback has been processed.
// Provides an option to resend the confirmation email.

require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
// We'll call send_email.php via a separate AJAX endpoint for resending, not directly here.

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set timezone to Africa/Lagos (WAT, UTC+1)
date_default_timezone_set('Africa/Lagos');

// Ensure BASE_URL is defined
if (!defined('BASE_URL')) {
    define('BASE_URL', 'http://localhost/mackennytutors/'); // Adjust this to your actual base URL
}

// Get reference and quiz_id from URL
$reference = sanitize_input($_GET['reference'] ?? '');
$quiz_id = sanitize_input($_GET['quiz_id'] ?? '');

$payment_details = null;
$quiz_details = null;
$user_details = null;
$auto_login_token = null;

if (empty($reference) || empty($quiz_id)) {
    error_log("Payment Confirmation Display: Missing reference or quiz_id. Ref: '$reference', Quiz ID: '$quiz_id'");
    $_SESSION['form_message'] = "Invalid payment confirmation request. Please try again or contact support.";
    $_SESSION['form_message_type'] = 'error';
    redirect(BASE_URL . 'auth/login.php');
    exit;
}

try {
    // Fetch payment details
    $stmt = $pdo->prepare("SELECT payment_id, user_id, quiz_id, amount, status, email_sent, transaction_reference, payment_date FROM payments WHERE transaction_reference = :reference AND quiz_id = :quiz_id");
    $stmt->execute(['reference' => $reference, 'quiz_id' => $quiz_id]);
    $payment_details = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment_details) {
        error_log("Payment Confirmation Display: No payment record found for ref {$reference}, quiz_id {$quiz_id}.");
        $_SESSION['form_message'] = "Payment record not found. Please contact support with transaction reference: " . htmlspecialchars($reference);
        $_SESSION['form_message_type'] = 'error';
        redirect(BASE_URL . 'auth/login.php');
        exit;
    }

    // Fetch quiz details
    $quiz_stmt = $pdo->prepare("SELECT title, description, open_datetime, duration_minutes, max_attempts, grade FROM quizzes WHERE quiz_id = :quiz_id");
    $quiz_stmt->execute(['quiz_id' => $payment_details['quiz_id']]);
    $quiz_details = $quiz_stmt->fetch(PDO::FETCH_ASSOC);

    // Fetch user details and auto-login token
    $user_stmt = $pdo->prepare("SELECT email, first_name, last_name, username, auto_login_token FROM users WHERE user_id = :user_id");
    $user_stmt->execute(['user_id' => $payment_details['user_id']]);
    $user_details = $user_stmt->fetch(PDO::FETCH_ASSOC);

    // If payment status is not 'completed', something went wrong in the callback.
    if ($payment_details['status'] !== 'completed') {
        $_SESSION['form_message'] = "Your payment status is '{$payment_details['status']}'. It might not have been fully processed. Please contact support with reference: " . htmlspecialchars($reference);
        $_SESSION['form_message_type'] = 'warning';
        // Redirect to a more appropriate page or just display the warning on this page.
        // For now, we'll let it display on this page.
    }

} catch (PDOException $e) {
    error_log("Payment Confirmation Display: DB Error fetching details: SQLSTATE[{$e->getCode()}]: " . $e->getMessage());
    $_SESSION['form_message'] = "A database error occurred while loading payment details. Please try again or contact support.";
    $_SESSION['form_message_type'] = 'error';
    redirect(BASE_URL . 'auth/login.php');
    exit;
}

// Set values for display
$first_name = htmlspecialchars($user_details['first_name'] ?? '');
$last_name = htmlspecialchars($user_details['last_name'] ?? '');
$user_email = htmlspecialchars($user_details['email'] ?? '');
$username = htmlspecialchars($user_details['username'] ?? '');

$quiz_title = htmlspecialchars($quiz_details['title'] ?? 'N/A');
$description = htmlspecialchars($quiz_details['description'] ?? 'N/A');
$open_datetime = htmlspecialchars(format_datetime($quiz_details['open_datetime'] ?? 'N/A'));
$duration_minutes = htmlspecialchars($quiz_details['duration_minutes'] ?? 'N/A');
$max_attempts = htmlspecialchars($quiz_details['max_attempts'] ?? 'N/A');
$grade = htmlspecialchars($quiz_details['grade'] ?? 'N/A');

$amount_paid = number_format($payment_details['amount'], 2);
$transaction_reference_display = htmlspecialchars($payment_details['transaction_reference']);
$payment_date_display = htmlspecialchars(format_datetime($payment_details['payment_date'] ?? 'N/A'));

$email_sent_status = (bool)($payment_details['email_sent'] ?? false);
$auto_login_link = BASE_URL . "auth/auto_login.php?token=" . urlencode($user_details['auto_login_token'] ?? '');

// Include header for the confirmation page
require_once '../includes/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Confirmation - Mackenny Assessment</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .bg-navy-900 { background-color: #0a1930; }
        .hover\:bg-navy-700:hover { background-color: #2c3e6a; }
        .focus\:ring-navy-900:focus { --tw-ring-color: #0a1930; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen flex flex-col">
    <?php // Assuming header includes basic HTML structure until <body> ?>

    <main class="flex-1 p-4 lg:p-8 mt-16 w-full max-w-3xl mx-auto">
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h1 class="text-3xl font-bold text-gray-800 mb-6 text-center">Payment Confirmation</h1>

            <div id="form-notification" class="px-4 py-3 rounded-md mb-6 hidden" role="alert">
                <strong class="font-bold"></strong>
                <span class="block sm:inline" id="notification-message-content"></span>
                <span class="absolute top-0 bottom-0 right-0 px-4 py-3 cursor-pointer" onclick="hideNotification()">
                    <svg fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24" class="h-6 w-6">
                        <path d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </span>
            </div>

            <?php if ($payment_details['status'] === 'completed'): ?>
                <div class="text-center text-green-700 bg-green-100 border border-green-400 p-4 rounded-md mb-6">
                    <p class="font-bold text-lg">Payment Successful!</p>
                    <p>Thank you for your payment. You now have access to the assessment.</p>
                </div>

                <div class="mb-6">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4 border-b pb-2">Assessment Details</h2>
                    <p class="mb-2"><strong class="w-40 inline-block">Title:</strong> <?php echo $quiz_title; ?></p>
                    <p class="mb-2"><strong class="w-40 inline-block">Description:</strong> <?php echo $description; ?></p>
                    <p class="mb-2"><strong class="w-40 inline-block">Opens:</strong> <?php echo $open_datetime; ?></p>
                    <p class="mb-2"><strong class="w-40 inline-block">Duration:</strong> <?php echo $duration_minutes; ?> minutes</p>
                    <p class="mb-2"><strong class="w-40 inline-block">Max Attempts:</strong> <?php echo $max_attempts; ?></p>
                    <p class="mb-2"><strong class="w-40 inline-block">Grade Level:</strong> <?php echo $grade; ?></p>
                </div>

                <div class="mb-6">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4 border-b pb-2">Payment Details</h2>
                    <p class="mb-2"><strong class="w-40 inline-block">Amount Paid:</strong> ₦<?php echo $amount_paid; ?></p>
                    <p class="mb-2"><strong class="w-40 inline-block">Transaction Ref:</strong> <?php echo $transaction_reference_display; ?></p>
                    <p class="mb-2"><strong class="w-40 inline-block">Payment Date:</strong> <?php echo $payment_date_display; ?></p>
                </div>

                <div class="mb-6 text-center">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4 border-b pb-2">Access Your Assessment</h2>
                    <p class="mb-4">
                        You can log in to your account and find the assessment on your dashboard.
                    </p>
                    <a href="<?php echo $auto_login_link; ?>"
                       class="bg-navy-900 hover:bg-navy-700 text-white font-semibold py-3 px-6 rounded-lg inline-flex items-center space-x-2 transition duration-300">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path>
                        </svg>
                        <span>Go to Assessment Dashboard</span>
                    </a>
                    <p class="text-sm text-gray-600 mt-4">
                        (This link will automatically log you in if you're not already)
                    </p>
                </div>

                <?php if (!$email_sent_status): ?>
                    <div class="text-center mt-8 p-4 bg-yellow-100 border border-yellow-400 rounded-md">
                        <p class="text-yellow-800 mb-3">It seems the confirmation email was not sent or recorded as sent. You can resend it now:</p>
                        <button type="button" id="resendEmailBtn"
                                class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg transition duration-300">
                            Resend Confirmation Email
                        </button>
                    </div>
                <?php else: ?>
                    <div class="text-center mt-8 p-4 bg-gray-100 border border-gray-300 rounded-md text-gray-700">
                        <p>A confirmation email has already been sent to <strong><?php echo $user_email; ?></strong>.</p>
                        <p class="text-sm mt-2">Please check your inbox and spam folder.</p>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="text-center text-red-700 bg-red-100 border border-red-400 p-4 rounded-md mb-6">
                    <p class="font-bold text-lg">Payment Status: <?php echo ucfirst(htmlspecialchars($payment_details['status'])); ?></p>
                    <p>There was an issue processing your payment. Please contact support with the transaction reference if this persists.</p>
                </div>
                <div class="flex justify-center mt-6">
                    <a href="<?php echo BASE_URL . 'auth/payment.php?quiz_id=' . urlencode($quiz_id); ?>"
                       class="bg-red-600 hover:bg-red-700 text-white font-semibold py-2 px-4 rounded-lg flex items-center space-x-2 transition duration-300">
                        Try Payment Again
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <?php require_once '../includes/footer.php'; ?>

    <script>
        function displayNotification(message, type) {
            const notificationContainer = document.getElementById('form-notification');
            const messageContentElement = document.getElementById('notification-message-content');
            const strongTag = notificationContainer.querySelector('strong');

            notificationContainer.classList.remove('bg-red-100', 'border-red-400', 'text-red-700', 'bg-green-100', 'border-green-400', 'text-green-700', 'bg-yellow-100', 'border-yellow-400', 'text-yellow-700', 'hidden');
            strongTag.textContent = ''; // Clear previous bold text

            if (message) {
                messageContentElement.textContent = message;
                if (type === 'success') {
                    notificationContainer.classList.add('bg-green-100', 'border-green-400', 'text-green-700');
                    strongTag.textContent = 'Success!';
                } else if (type === 'error') {
                    notificationContainer.classList.add('bg-red-100', 'border-red-400', 'text-red-700');
                    strongTag.textContent = 'Error!';
                } else if (type === 'warning') {
                    notificationContainer.classList.add('bg-yellow-100', 'border-yellow-400', 'text-yellow-700');
                    strongTag.textContent = 'Warning!';
                } else { // info or default
                    notificationContainer.classList.add('bg-gray-100', 'border-gray-400', 'text-gray-700');
                    strongTag.textContent = 'Info!';
                }
                notificationContainer.style.transform = 'translateY(-100%)';
                setTimeout(() => {
                    notificationContainer.style.transition = 'transform 0.3s ease-out';
                    notificationContainer.style.transform = 'translateY(0)';
                }, 10);
                setTimeout(() => {
                    hideNotification();
                }, 7000); // Keep notification visible slightly longer
            }
        }

        function hideNotification() {
            const notificationElement = document.getElementById('form-notification');
            notificationElement.style.transition = 'transform 0.3s ease-in';
            notificationElement.style.transform = 'translateY(-100%)';
            notificationElement.addEventListener('transitionend', function handler() {
                notificationElement.classList.add('hidden');
                notificationElement.removeEventListener('transitionend', handler);
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Display session messages from the callback
            <?php if (isset($_SESSION['form_message'])): ?>
                displayNotification("<?php echo htmlspecialchars($_SESSION['form_message']); ?>",
                                    "<?php echo htmlspecialchars($_SESSION['form_message_type']); ?>");
                <?php
                unset($_SESSION['form_message']);
                unset($_SESSION['form_message_type']);
                ?>
            <?php endif; ?>

            // Handle resend email button click
            const resendEmailBtn = document.getElementById('resendEmailBtn');
            if (resendEmailBtn) {
                resendEmailBtn.addEventListener('click', async function() {
                    resendEmailBtn.disabled = true; // Disable button to prevent multiple clicks
                    resendEmailBtn.textContent = 'Sending...';

                    try {
                        const response = await fetch('<?php echo BASE_URL; ?>api/resend_payment_email.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                reference: '<?php echo $reference; ?>',
                                quiz_id: '<?php echo $quiz_id; ?>'
                            })
                        });

                        const result = await response.json();

                        if (result.status === 'success') {
                            displayNotification(result.message, 'success');
                            // Optionally, hide the resend button after successful resend
                            resendEmailBtn.parentElement.innerHTML = '<p class="text-green-800">Confirmation email successfully resent!</p>';
                        } else {
                            displayNotification(result.message, 'error');
                            resendEmailBtn.textContent = 'Resend Confirmation Email';
                            resendEmailBtn.disabled = false;
                        }
                    } catch (error) {
                        console.error('Error resending email:', error);
                        displayNotification('An error occurred while trying to resend the email.', 'error');
                        resendEmailBtn.textContent = 'Resend Confirmation Email';
                        resendEmailBtn.disabled = false;
                    }
                });
            }
        });
    </script>
</body>
</html>