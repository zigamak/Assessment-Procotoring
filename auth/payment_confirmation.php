<?php
// auth/payment_confirmation.php
// Displays confirmation after successful payment and automatically sends comprehensive confirmation email.

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set timezone to Africa/Lagos (WAT, UTC+1)
date_default_timezone_set('Africa/Lagos');

require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/functions.php'; // Contains sanitize_input, redirect, isLoggedIn, getUserRole, BASE_URL
require_once '../includes/send_email.php'; // Contains sendEmail

error_log("Payment Confirmation: Accessed with reference=" . (isset($_GET['reference']) ? $_GET['reference'] : 'none') . ", quiz_id=" . (isset($_GET['quiz_id']) ? $_GET['quiz_id'] : 'none'));

// Admins should not be on this page, redirect them.
if (isLoggedIn() && getUserRole() === 'admin') {
    error_log("Payment Confirmation: Admin user redirected to admin/dashboard.php");
    redirect(BASE_URL . 'admin/dashboard.php');
    exit();
}

// Check for payment reference and quiz_id
$reference = sanitize_input($_GET['reference'] ?? '');
$quiz_id = sanitize_input($_GET['quiz_id'] ?? '');

if (empty($reference) || empty($quiz_id)) {
    error_log("Payment Confirmation: Missing reference or quiz_id. Redirecting to assessments.php");
    $_SESSION['form_message'] = "Invalid payment confirmation details. Please try again or contact support.";
    $_SESSION['form_message_type'] = 'error';
    redirect(BASE_URL . 'student/assessments.php'); // Assuming this is the general student assessment list
    exit();
}

// Verify payment and user details
$payment = null;
$quiz_title_display = 'Unknown Assessment';
$auto_login_link = null; // Initialize auto_login_link

try {
    $stmt = $pdo->prepare("
        SELECT p.user_id, p.amount, p.status, p.payment_date, p.email_sent,
               u.email, u.username, u.first_name, u.last_name, -- Fetch first_name and last_name
               q.title as quiz_title
        FROM payments p
        JOIN users u ON p.user_id = u.user_id
        JOIN quizzes q ON p.quiz_id = q.quiz_id
        WHERE p.transaction_reference = :reference AND p.quiz_id = :quiz_id
    ");
    $stmt->execute(['reference' => $reference, 'quiz_id' => $quiz_id]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment || $payment['status'] !== 'completed') {
        error_log("Payment Confirmation: Payment not found or not completed for ref {$reference}, quiz_id {$quiz_id}");
        $_SESSION['form_message'] = "Payment not found or not completed. Please contact support if you believe this is an error.";
        $_SESSION['form_message_type'] = 'error';
        // Redirect to a payment page or home page if payment is not valid
        redirect(BASE_URL . 'auth/payment.php?quiz_id=' . urlencode($quiz_id) . '&amount=0');
        exit();
    }

    $quiz_title_display = htmlspecialchars($payment['quiz_title']);

    // Generate and store auto-login token if user is not already logged in
    // or if the existing token is expired/invalid.
    // Fetch current token details
    $stmt_fetch_token = $pdo->prepare("SELECT auto_login_token, auto_login_token_expiry FROM users WHERE user_id = :user_id");
    $stmt_fetch_token->execute(['user_id' => $payment['user_id']]);
    $user_token_data = $stmt_fetch_token->fetch(PDO::FETCH_ASSOC);

    $current_time = new DateTime();
    $token_is_valid = false;
    if ($user_token_data && $user_token_data['auto_login_token'] && $user_token_data['auto_login_token_expiry']) {
        try {
            $expiry_time = new DateTime($user_token_data['auto_login_token_expiry']);
            if ($current_time < $expiry_time) {
                $auto_login_link = BASE_URL . 'auth/auto_login.php?token=' . $user_token_data['auto_login_token'];
                $token_is_valid = true;
            }
        } catch (Exception $e) {
            // Log error if date parsing fails, treat token as invalid
            error_log("Payment Confirmation: Invalid auto_login_token_expiry format for user_id {$payment['user_id']}: " . $user_token_data['auto_login_token_expiry']);
        }
    }

    // If no valid token exists, generate a new one
    if (!$token_is_valid) {
        $auto_login_token_new = bin2hex(random_bytes(32)); // Generate a random 64-character hex token
        $expiry_time_new = (new DateTime())->modify('+24 hours')->format('Y-m-d H:i:s'); // Token valid for 24 hours

        $stmt_update_token = $pdo->prepare("
            UPDATE users SET auto_login_token = :token, auto_login_token_expiry = :expiry WHERE user_id = :user_id
        ");
        $stmt_update_token->execute([
            'token' => $auto_login_token_new,
            'expiry' => $expiry_time_new,
            'user_id' => $payment['user_id']
        ]);
        $auto_login_link = BASE_URL . 'auth/auto_login.php?token=' . $auto_login_token_new;
        error_log("Payment Confirmation: Generated NEW auto-login token for user_id {$payment['user_id']}. Link: {$auto_login_link}");
    } else {
        error_log("Payment Confirmation: Reusing existing valid auto-login token for user_id {$payment['user_id']}. Link: {$auto_login_link}");
    }

    // --- Start: Automatic Email Sending Logic (only if email_sent is 0) ---
    if ($payment['email_sent'] == 0) {
        try {
            ob_start();
            require '../includes/email_templates/payment_confirmation_email.php';
            $email_body = ob_get_clean();

            // Replace placeholders in the email body
            $email_body = str_replace('{{first_name}}', htmlspecialchars($payment['first_name']), $email_body); // New
            $email_body = str_replace('{{last_name}}', htmlspecialchars($payment['last_name']), $email_body);   // New
            $email_body = str_replace('{{email}}', htmlspecialchars($payment['email']), $email_body);
            $email_body = str_replace('{{auto_login_link}}', htmlspecialchars($auto_login_link), $email_body);
            $email_body = str_replace('{{quiz_title}}', $quiz_title_display, $email_body);
            $email_body = str_replace('{{amount}}', number_format($payment['amount'], 2), $email_body);
            $email_body = str_replace('{{transaction_reference}}', htmlspecialchars($reference), $email_body);
            // Format payment date as "June 2, 2025, 2:40 am"
            $email_body = str_replace('{{payment_date}}', date('F j, Y, g:i a', strtotime($payment['payment_date'])), $email_body);

            $subject = "Your Mackenny Assessment Payment Confirmation & Login Access";

            if (sendEmail($payment['email'], $subject, $email_body)) {
                $stmt_update_email_sent = $pdo->prepare("
                    UPDATE payments
                    SET email_sent = 1
                    WHERE transaction_reference = :reference AND quiz_id = :quiz_id
                ");
                $stmt_update_email_sent->execute([
                    'reference' => $reference,
                    'quiz_id' => $quiz_id
                ]);
                error_log("Payment Confirmation: Comprehensive confirmation email sent successfully to {$payment['email']} for ref {$reference}");
                $_SESSION['form_message'] = "Payment confirmed. A detailed confirmation email has been sent.";
                $_SESSION['form_message_type'] = 'success';
            } else {
                error_log("Payment Confirmation: Failed to send comprehensive confirmation email to {$payment['email']} for ref {$reference}");
                $_SESSION['form_message'] = "Payment confirmed, but failed to send the detailed confirmation email. Please contact support.";
                $_SESSION['form_message_type'] = 'error';
            }
        } catch (PDOException $e) {
            error_log("Payment Confirmation: Email Send DB Error: SQLSTATE[{$e->getCode()}]: " . $e->getMessage());
            $_SESSION['form_message'] = "An error occurred while sending the confirmation email. Please contact support.";
            $_SESSION['form_message_type'] = 'error';
        } catch (Exception $e) {
            error_log("Payment Confirmation: General Error during email send: " . $e->getMessage());
            $_SESSION['form_message'] = "An unexpected error occurred during email send. Please contact support.";
            $_SESSION['form_message_type'] = 'error';
        }
    } else {
        error_log("Payment Confirmation: Email already sent for ref {$reference}. Skipping send.");
        // If email was already sent, we can still show a success message if needed,
        // but avoid re-setting it if a previous error message is more relevant.
        if (!isset($_SESSION['form_message'])) {
             $_SESSION['form_message'] = "Payment confirmed. The detailed confirmation email was already sent.";
             $_SESSION['form_message_type'] = 'info';
        }
    }
    // --- End: Automatic Email Sending Logic ---

} catch (PDOException $e) {
    error_log("Payment Confirmation: DB Error fetching payment or updating token: SQLSTATE[{$e->getCode()}]: " . $e->getMessage());
    $_SESSION['form_message'] = "An error occurred while verifying your payment. Please try again or contact support.";
    $_SESSION['form_message_type'] = 'error';
    redirect(BASE_URL . 'auth/payment.php?quiz_id=' . urlencode($quiz_id) . '&amount=0');
    exit();
}
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
        .bg-navy-800 { background-color: #1a2b4a; }
        .hover\:bg-navy-700:hover { background-color: #2c3e6a; }
        .focus\:ring-navy-900:focus { --tw-ring-color: #0a1930; }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-100 to-blue-50 min-h-screen flex items-center justify-center">
    <div class="container mx-auto px-4 py-8">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 max-w-6xl mx-auto bg-white rounded-xl shadow-2xl overflow-hidden">
            <div class="bg-navy-900 text-white p-12 flex flex-col justify-center">
                <h1 class="text-4xl font-bold mb-4">Payment Successful!</h1>
                <p class="text-lg mb-6">
                    Thank you for your payment. A confirmation email with your login details has been sent to <strong><?php echo htmlspecialchars($payment['email']); ?></strong>.
                    You can log in instantly to your dashboard using the button below.
                </p>
                <p class="text-md italic">
                    Check your inbox (and spam/junk folder) for the confirmation email. You will receive a separate email with your full assessment schedule and details a few days before your scheduled assessment date.
                </p>
            </div>
            <div class="p-12 relative">
                <h2 class="text-3xl font-bold text-gray-800 mb-6 text-center">Payment Confirmation</h2>

                <div id="form-notification" class="absolute top-0 left-0 w-full px-4 py-3 rounded-md hidden" role="alert" style="transform: translateY(-100%);">
                    <strong class="font-bold"></strong>
                    <span class="block sm:inline" id="notification-message-content"></span>
                    <span class="absolute top-0 bottom-0 right-0 px-4 py-3 cursor-pointer" onclick="hideNotification()">
                        <svg fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24" class="h-6 w-6">
                            <path d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </span>
                </div>

                <div class="bg-gray-100 p-6 rounded-lg mb-6">
                    <p class="text-gray-700 mb-2"><strong>Transaction Reference:</strong> <?php echo htmlspecialchars($reference); ?></p>
                    <p class="text-gray-700 mb-2"><strong>Amount Paid:</strong> ₦<?php echo number_format($payment['amount'], 2); ?></p>
                    <p class="text-gray-700 mb-2"><strong>Payment For:</strong> <?php echo $quiz_title_display; ?></p>
                    <p class="text-gray-700 mb-2"><strong>Payment Date:</strong> <?php echo date('F j, Y, g:i a', strtotime($payment['payment_date'])); ?></p>
                    <p class="text-gray-700"><strong>Status:</strong>
                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Completed</span>
                    </p>
                </div>

                <div class="space-y-6">
                    <div>
                        <p class="text-gray-600 mb-4">
                            Click the button below to go to your dashboard and get started with your assessment.
                        </p>
                        <?php if ($auto_login_link): ?>
                            <a href="<?php echo htmlspecialchars($auto_login_link); ?>"
                               class="bg-navy-900 hover:bg-navy-700 text-white font-semibold py-3 px-6 rounded-lg shadow-md focus:outline-none focus:ring-2 focus:ring-navy-900 focus:ring-offset-2 transition duration-200">
                                Go to My Dashboard
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="text-center mt-6 space-y-4">
                        <p class="text-sm text-gray-600">
                            If you have any questions regarding your payment or upcoming assessment, please don't hesitate to contact our support team at
                            <a href="mailto:support@mackennyassessment.com" class="text-blue-600 hover:text-blue-800 font-medium hover:underline">
                                support@mackennyassessment.com
                            </a>.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function displayNotification(message, type) {
            const notificationContainer = document.getElementById('form-notification');
            const messageContentElement = document.getElementById('notification-message-content');
            const strongTag = notificationContainer.querySelector('strong');

            notificationContainer.classList.remove('bg-red-100', 'border-red-400', 'text-red-700', 'bg-green-100', 'border-green-400', 'text-green-700', 'bg-yellow-100', 'border-yellow-400', 'text-yellow-700');
            strongTag.textContent = '';

            if (message) {
                messageContentElement.textContent = message;
                if (type === 'error') {
                    notificationContainer.classList.add('bg-red-100', 'border-red-400', 'text-red-700');
                    strongTag.textContent = 'Error!';
                } else if (type === 'success') {
                    notificationContainer.classList.add('bg-green-100', 'border-green-400', 'text-green-700');
                    strongTag.textContent = 'Success!';
                } else if (type === 'warning') {
                    notificationContainer.classList.add('bg-yellow-100', 'border-yellow-400', 'text-yellow-700');
                    strongTag.textContent = 'Warning!';
                }
                notificationContainer.classList.remove('hidden');
                notificationContainer.style.transform = 'translateY(-100%)';
                setTimeout(() => {
                    notificationContainer.style.transition = 'transform 0.3s ease-out';
                    notificationContainer.style.transform = 'translateY(0)';
                }, 10);
            } else {
                hideNotification();
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
            <?php if (isset($_SESSION['form_message'])): ?>
                displayNotification("<?php echo htmlspecialchars($_SESSION['form_message']); ?>", "<?php echo htmlspecialchars($_SESSION['form_message_type']); ?>");
                <?php
                unset($_SESSION['form_message']);
                unset($_SESSION['form_message_type']);
                ?>
            <?php endif; ?>
        });
    </script>
</body>
</html>