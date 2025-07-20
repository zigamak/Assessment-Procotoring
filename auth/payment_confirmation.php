<?php
// auth/payment_confirmation.php
// Displays confirmation after successful payment and allows resending the confirmation email.

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
try {
    $stmt = $pdo->prepare("
        SELECT p.user_id, p.amount, p.status, p.payment_date, p.email_sent, u.email, u.username, q.title as quiz_title
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
} catch (PDOException $e) {
    error_log("Payment Confirmation: DB Error fetching payment: SQLSTATE[{$e->getCode()}]: " . $e->getMessage());
    $_SESSION['form_message'] = "An error occurred while verifying your payment. Please try again or contact support.";
    $_SESSION['form_message_type'] = 'error';
    redirect(BASE_URL . 'auth/payment.php?quiz_id=' . urlencode($quiz_id) . '&amount=0');
    exit();
}

// Handle resend confirmation email request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend_link'])) {
    try {
        // No auto-login token generation or update needed
        // The existing email template is assumed to be updated externally to reflect the new messaging.
        ob_start();
        // The content of this file is assumed to be updated to match the new email messaging.
        // For example, payment_confirmation_email_no_login.php or similar.
        // For this example, we'll keep the name as payment_confirmation_email.php but assume its content is adjusted.
        require '../includes/email_templates/payment_confirmation_email.php';
        $email_body = ob_get_clean();

        $email_body = str_replace('{{username}}', htmlspecialchars($payment['username']), $email_body);
        $email_body = str_replace('{{email}}', htmlspecialchars($payment['email']), $email_body);
        // Removed auto_login_link replacement
        $email_body = str_replace('{{quiz_title}}', $quiz_title_display, $email_body);
        $email_body = str_replace('{{amount}}', number_format($payment['amount'], 2), $email_body);
        $email_body = str_replace('{{transaction_reference}}', htmlspecialchars($reference), $email_body);
        $email_body = str_replace('{{payment_date}}', date('F j, Y, g:i a', strtotime($payment['payment_date'])), $email_body);

        $subject = "Your Mackenny Assessment Payment Confirmation"; // Updated subject

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
            error_log("Payment Confirmation: Confirmation email resent successfully to {$payment['email']} for ref {$reference}");
            $_SESSION['form_message'] = "Payment confirmation email resent to your email address.";
            $_SESSION['form_message_type'] = 'success';
        } else {
            error_log("Payment Confirmation: Failed to resend confirmation email to {$payment['email']} for ref {$reference}");
            $_SESSION['form_message'] = "Failed to resend the payment confirmation email. Please try again or contact support.";
            $_SESSION['form_message_type'] = 'error';
        }
    } catch (PDOException $e) {
        error_log("Payment Confirmation: Resend Email Error: SQLSTATE[{$e->getCode()}]: " . $e->getMessage());
        $_SESSION['form_message'] = "An error occurred while resending the confirmation email. Please try again or contact support.";
        $_SESSION['form_message_type'] = 'error';
    }

    redirect(BASE_URL . 'auth/payment_confirmation.php?reference=' . urlencode($reference) . '&quiz_id=' . urlencode($quiz_id));
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
                    Thank you for your payment. A confirmation email has been sent to <strong><?php echo htmlspecialchars($payment['email']); ?></strong>.
                    You will receive a separate email with your login details and instructions to access your dashboard and assessment a few days before your scheduled assessment date.
                </p>
                <p class="text-sm italic">
                    Check your inbox (and spam/junk folder) for the confirmation email. If you don’t receive it, you can resend it below.
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

                <form action="payment_confirmation.php?reference=<?php echo urlencode($reference); ?>&quiz_id=<?php echo urlencode($quiz_id); ?>" method="POST" class="space-y-6">
                    <div>
                        <p class="text-gray-600 mb-4">
                            Didn’t receive the payment confirmation email? Click below to resend it to <strong><?php echo htmlspecialchars($payment['email']); ?></strong>.
                        </p>
                        <button type="submit" name="resend_link" value="1"
                                class="bg-navy-900 hover:bg-navy-700 text-white font-semibold py-3 px-6 rounded-lg shadow-md focus:outline-none focus:ring-2 focus:ring-navy-900 focus:ring-offset-2 transition duration-200">
                            Resend Confirmation Email
                        </button>
                    </div>
                    <div class="text-center mt-6 space-y-4">
                        <p class="text-sm text-gray-600">
                            If you have any questions regarding your payment or upcoming assessment, please don't hesitate to contact our support team at
                            <a href="mailto:support@mackennyassessment.com" class="text-blue-600 hover:text-blue-800 font-medium hover:underline">
                                support@mackennyassessment.com
                            </a>.
                        </p>
                    </div>
                </form>
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