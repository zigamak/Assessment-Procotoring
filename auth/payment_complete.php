<?php
// auth/payment_complete.php
// Displays payment confirmation and redirects to the dashboard.

require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/send_email.php';

date_default_timezone_set('Africa/Lagos');

if (!defined('BASE_URL')) {
    define('BASE_URL', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . '/assessment/');
}

// Initialize variables
$transaction_reference = sanitize_input($_GET['reference'] ?? '');
$quiz_id = sanitize_input($_GET['quiz_id'] ?? '');
$user_id = sanitize_input($_GET['user_id'] ?? '');
$token = sanitize_input($_GET['token'] ?? '');
$token_id = sanitize_input($_GET['token_id'] ?? '');

$first_name = '';
$last_name = '';
$email = '';
$quiz_title = '';
$amount = 0;
$payment_status = 'pending';
$message = '';
$message_type = '';

// Validate parameters
if (empty($transaction_reference) || empty($quiz_id) || empty($user_id) || empty($token) || empty($token_id)) {
    $_SESSION['form_message'] = "Invalid payment completion link. Please contact support.";
    $_SESSION['form_message_type'] = 'error';
    redirect(BASE_URL . 'student/assessments.php');
    exit;
}

try {
    // Re-validate auto-login token
    $stmt = $pdo->prepare("SELECT id, user_id, expires_at, used FROM auto_login_tokens WHERE id = :id AND token = :token");
    $stmt->execute(['id' => $token_id, 'token' => $token]);
    $token_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$token_data || $token_data['user_id'] != $user_id) {
        $_SESSION['form_message'] = "Invalid or mismatched token. Please request a new payment link.";
        $_SESSION['form_message_type'] = 'error';
        redirect(BASE_URL . 'student/assessments.php');
        exit;
    }

    if ($token_data['used']) {
        $_SESSION['form_message'] = "This payment link has already been used. Please request a new link.";
        $_SESSION['form_message_type'] = 'warning';
        redirect(BASE_URL . 'student/assessments.php');
        exit;
    }

    if (strtotime($token_data['expires_at']) < time()) {
        $_SESSION['form_message'] = "The payment link has expired. Please request a new link.";
        $_SESSION['form_message_type'] = 'error';
        $stmt_delete_token = $pdo->prepare("DELETE FROM auto_login_tokens WHERE id = :id");
        $stmt_delete_token->execute(['id' => $token_data['id']]);
        redirect(BASE_URL . 'student/assessments.php');
        exit;
    }

    // Verify payment status
    $stmt = $pdo->prepare("SELECT user_id, quiz_id, amount, status FROM payments WHERE transaction_reference = :reference");
    $stmt->execute(['reference' => $transaction_reference]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        $_SESSION['form_message'] = "Payment record not found. Please contact support.";
        $_SESSION['form_message_type'] = 'error';
        redirect(BASE_URL . 'student/assessments.php');
        exit;
    }

    if ($payment['user_id'] != $user_id || $payment['quiz_id'] != $quiz_id) {
        $_SESSION['form_message'] = "Payment details mismatch. Please contact support.";
        $_SESSION['form_message_type'] = 'error';
        redirect(BASE_URL . 'student/assessments.php');
        exit;
    }

    // Assuming Paystack verification sets status to 'success' in paystack_payment.php
    if ($payment['status'] !== 'success') {
        $_SESSION['form_message'] = "Payment not yet verified or failed. Please try again or contact support.";
        $_SESSION['form_message_type'] = 'error';
        redirect(BASE_URL . 'student/assessments.php');
        exit;
    }

    // Fetch user details
    $stmt = $pdo->prepare("SELECT first_name, last_name, email, username, role FROM users WHERE user_id = :user_id");
    $stmt->execute(['user_id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $_SESSION['form_message'] = "User account not found. Please contact support.";
        $_SESSION['form_message_type'] = 'error';
        redirect(BASE_URL . 'auth/login.php');
        exit;
    }

    // Ensure user is logged in
    if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_id'] != $user['user_id']) {
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['logged_in'] = true;

        $stmt_update_login = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE user_id = :user_id");
        $stmt_update_login->execute(['user_id' => $user['user_id']]);
    }

    $first_name = htmlspecialchars($user['first_name'] ?? 'User');
    $last_name = htmlspecialchars($user['last_name'] ?? '');
    $email = htmlspecialchars($user['email'] ?? '');

    // Fetch quiz details
    $stmt = $pdo->prepare("SELECT title, assessment_fee FROM quizzes WHERE quiz_id = :quiz_id");
    $stmt->execute(['quiz_id' => $quiz_id]);
    $quiz = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$quiz) {
        $_SESSION['form_message'] = "Assessment not found. Please contact support.";
        $_SESSION['form_message_type'] = 'error';
        redirect(BASE_URL . 'student/assessments.php');
        exit;
    }

    $quiz_title = htmlspecialchars($quiz['title']);
    $amount = (float)$quiz['assessment_fee'];

    // Mark token as used
    $stmt_mark_token = $pdo->prepare("UPDATE auto_login_tokens SET used = 1 WHERE id = :id");
    $stmt_mark_token->execute(['id' => $token_id]);

    // Send payment completion email
    ob_start();
    require '../includes/email_templates/payment_complete_email.php';
    $email_body = ob_get_clean();
    $email_body = str_replace('{{first_name}}', $first_name, $email_body);
    $email_body = str_replace('{{last_name}}', $last_name, $email_body);
    $email_body = str_replace('{{quiz_title}}', $quiz_title, $email_body);
    $email_body = str_replace('{{amount}}', number_format($amount, 2), $email_body);
    $email_body = str_replace('{{transaction_reference}}', $transaction_reference, $email_body);
    $email_body = str_replace('{{payment_date}}', date('Y-m-d H:i:s'), $email_body);

    $subject = "Payment Confirmation - Mackenny Assessment (Ref: " . $transaction_reference . ")";

    if (!sendEmail($email, $subject, $email_body)) {
        error_log("Failed to send payment completion email to " . $email . " for ref: " . $transaction_reference);
        $message = "Payment completed successfully, but we encountered an issue sending the confirmation email.";
        $message_type = 'warning';
    } else {
        $message = "Payment completed successfully! You will be redirected to your dashboard shortly.";
        $message_type = 'success';
    }

} catch (PDOException $e) {
    error_log("Payment Completion Error: " . $e->getMessage());
    $_SESSION['form_message'] = "A database error occurred while confirming your payment. Please contact support.";
    $_SESSION['form_message_type'] = 'error';
    redirect(BASE_URL . 'student/assessments.php');
    exit;
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
        <div class="max-w-2xl mx-auto bg-white rounded-xl shadow-2xl p-8 text-center">
            <div id="form-notification" class="mb-6 px-4 py-3 rounded-md <?php echo $message_type === 'success' ? 'bg-green-100 border-green-400 text-green-700' : 'bg-yellow-100 border-yellow-400 text-yellow-700'; ?>" role="alert">
                <strong class="font-bold"><?php echo $message_type === 'success' ? 'Success!' : 'Warning!'; ?></strong>
                <span class="block sm:inline"><?php echo htmlspecialchars($message); ?></span>
            </div>
            <h1 class="text-3xl font-bold text-gray-800 mb-4">Payment Confirmation</h1>
            <div class="space-y-4 text-gray-700">
                <p><strong>Assessment:</strong> <?php echo $quiz_title; ?></p>
                <p><strong>Amount Paid:</strong> ₦<?php echo number_format($amount, 2); ?></p>
                <p><strong>Transaction Reference:</strong> <?php echo htmlspecialchars($transaction_reference); ?></p>
                <p><strong>Name:</strong> <?php echo $first_name . ' ' . $last_name; ?></p>
                <p><strong>Email:</strong> <?php echo $email; ?></p>
                <p class="text-sm italic">Redirecting to your dashboard in <span id="countdown">5</span> seconds...</p>
            </div>
            <div class="mt-6">
                <a href="<?php echo BASE_URL; ?>student/dashboard.php" class="inline-block bg-navy-900 hover:bg-navy-700 text-white font-semibold py-2 px-4 rounded-lg shadow-md transition duration-200">
                    Go to Dashboard Now
                </a>
            </div>
        </div>
    </div>

    <script>
        // Countdown timer for auto-redirect
        let countdown = 5;
        const countdownElement = document.getElementById('countdown');
        const redirectInterval = setInterval(() => {
            countdown--;
            countdownElement.textContent = countdown;
            if (countdown <= 0) {
                clearInterval(redirectInterval);
                window.location.href = '<?php echo BASE_URL; ?>student/dashboard.php';
            }
        }, 1000);

        // Display session messages if any
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (isset($_SESSION['form_message'])): ?>
                const notificationContainer = document.getElementById('form-notification');
                const messageContentElement = notificationContainer.querySelector('span');
                const strongTag = notificationContainer.querySelector('strong');
                notificationContainer.classList.remove('bg-green-100', 'border-green-400', 'text-green-700', 'bg-yellow-100', 'border-yellow-400', 'text-yellow-700');
                notificationContainer.classList.add('border-l-4', 'shadow-lg');
                messageContentElement.textContent = "<?php echo htmlspecialchars($_SESSION['form_message']); ?>";
                strongTag.textContent = "<?php echo htmlspecialchars($_SESSION['form_message_type']) === 'success' ? 'Success!' : 'Error!'; ?>";
                notificationContainer.classList.add(
                    "<?php echo $_SESSION['form_message_type'] === 'success' ? 'bg-green-100 border-green-400 text-green-700' : 'bg-red-100 border-red-400 text-red-700'; ?>"
                );
                <?php
                unset($_SESSION['form_message']);
                unset($_SESSION['form_message_type']);
                ?>
            <?php endif; ?>
        });
    </script>
</body>
</html>