<?php
// auth/payment.php
// Handles user registration and payment for an assessment, with auto-login for registered users.

require_once '../includes/session.php'; // For session management
require_once '../includes/db.php';     // For database connection ($pdo) and Paystack keys
require_once '../includes/functions.php'; // For utility functions (e.g., sanitize_input, redirect, display_message)
require_once '../includes/send_email.php'; // For sending emails

// Define BASE_URL if not already defined
if (!defined('BASE_URL')) {
    define('BASE_URL', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . '/');
}

$message = ''; // Initialize message variable for feedback
$quiz_id = sanitize_input($_GET['quiz_id'] ?? null);
$amount_from_url = sanitize_input($_GET['amount'] ?? null);
$auto_login_token = sanitize_input($_GET['token'] ?? null);
$quiz_title = '';
$assessment_fee = 0;
$user = null;

// Initialize form fields for sticky form
$first_name = '';
$last_name = '';
$email = '';
$gender = '';
$address = '';
$date_of_birth = '';

// Check for auto-login token
if ($auto_login_token) {
    try {
        $stmt = $pdo->prepare("
            SELECT user_id, username, email, first_name, last_name, role, auto_login_token_expiry
            FROM users
            WHERE auto_login_token = :token
            AND auto_login_token_expiry > NOW()
        ");
        $stmt->execute(['token' => $auto_login_token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Auto-login the user
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            // Optionally clear the token after use
            $stmt = $pdo->prepare("UPDATE users SET auto_login_token = NULL, auto_login_token_expiry = NULL WHERE user_id = :user_id");
            $stmt->execute(['user_id' => $user['user_id']]);
        } else {
            $message = "Invalid or expired auto-login token.";
            $auto_login_token = null;
        }
    } catch (PDOException $e) {
        error_log("Auto-login Error: " . $e->getMessage());
        $message = "An error occurred during auto-login. Please try again.";
        $auto_login_token = null;
    }
}

// Validate quiz_id and amount
if (!$quiz_id || !is_numeric($quiz_id) || !$amount_from_url || !is_numeric($amount_from_url)) {
    $message = "Invalid assessment details provided. Please return to the assessment selection page.";
} else {
    try {
        // Fetch quiz details
        $stmt = $pdo->prepare("SELECT title, assessment_fee FROM quizzes WHERE quiz_id = :quiz_id");
        $stmt->execute(['quiz_id' => $quiz_id]);
        $quiz_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$quiz_data) {
            $message = "Assessment not found. Please try again.";
            $quiz_id = null;
        } else {
            $quiz_title = htmlspecialchars($quiz_data['title']);
            $assessment_fee = (float)$quiz_data['assessment_fee'];

            // Validate amount
            if (abs($assessment_fee - (float)$amount_from_url) > 0.01) {
                $message = "Mismatch in assessment fee. Please try again.";
                $quiz_id = null;
            }
        }
    } catch (PDOException $e) {
        error_log("Payment Data Fetch Error: " . $e->getMessage());
        $message = "Could not retrieve assessment details. Please try again later.";
        $quiz_id = null;
    }
}

// Handle form submission for unregistered users
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $quiz_id && !$user) {
    $first_name = sanitize_input($_POST['first_name'] ?? '');
    $last_name = sanitize_input($_POST['last_name'] ?? '');
    $email = sanitize_input($_POST['email'] ?? '');
    $gender = sanitize_input($_POST['gender'] ?? '');
    $address = sanitize_input($_POST['address'] ?? '');
    $date_of_birth = sanitize_input($_POST['date_of_birth'] ?? '');

    // Validation
    if (empty($first_name) || empty($last_name) || empty($email) || empty($gender) || empty($address) || empty($date_of_birth)) {
        $message = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Invalid email format.";
    } elseif (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $date_of_birth)) {
        $message = "Invalid date of birth format.";
    } else {
        try {
            // Check if email already exists
            $stmt_check_email = $pdo->prepare("SELECT * FROM users WHERE email = :email");
            $stmt_check_email->execute(['email' => $email]);
            $existingUser = $stmt_check_email->fetch(PDO::FETCH_ASSOC);

            if ($existingUser) {
                $message = "An account with this email address already exists.";
            } else {
                // Generate username
                $base_username = strtolower(str_replace(' ', '', $first_name)) . '.' . strtolower(str_replace(' ', '', $last_name));
                $generated_username = $base_username;
                $counter = 1;

                while (true) {
                    $stmt_check_username = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = :username");
                    $stmt_check_username->execute(['username' => $generated_username]);
                    if ($stmt_check_username->fetchColumn() == 0) {
                        break;
                    }
                    $generated_username = $base_username . $counter++;
                }

                // Generate auto-login token
                $auto_login_token = bin2hex(random_bytes(32));
                $auto_login_token_expiry = date('Y-m-d H:i:s', strtotime('+2 weeks'));

                // Insert user (without password) with new fields
                $stmt = $pdo->prepare("
                    INSERT INTO users (first_name, last_name, username, email, gender, address, date_of_birth, role, auto_login_token, auto_login_token_expiry)
                    VALUES (:first_name, :last_name, :username, :email, :gender, :address, :date_of_birth, 'student', :auto_login_token, :auto_login_token_expiry)
                ");
                $stmt->execute([
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'username' => $generated_username,
                    'email' => $email,
                    'gender' => $gender,
                    'address' => $address,
                    'date_of_birth' => $date_of_birth,
                    'auto_login_token' => $auto_login_token,
                    'auto_login_token_expiry' => $auto_login_token_expiry
                ]);

                $new_user_id = $pdo->lastInsertId();

                // Generate transaction reference
                $transaction_reference = 'QZPAY_' . time() . '_' . $new_user_id . '_' . $quiz_id . '_' . bin2hex(random_bytes(4));

                // Insert pending payment
                $stmt_payment = $pdo->prepare("
                    INSERT INTO payments (user_id, quiz_id, amount, status, transaction_reference)
                    VALUES (:user_id, :quiz_id, :amount, 'pending', :transaction_reference)
                ");
                $stmt_payment->execute([
                    'user_id' => $new_user_id,
                    'quiz_id' => $quiz_id,
                    'amount' => $assessment_fee,
                    'transaction_reference' => $transaction_reference
                ]);

                // Send payment confirmation email
                ob_start();
                require '../includes/email_templates/payment_confirmation_email.php';
                $email_body = ob_get_clean();
                $email_body = str_replace('{{username}}', htmlspecialchars($generated_username), $email_body);
                $email_body = str_replace('{{quiz_title}}', htmlspecialchars($quiz_title), $email_body);
                $email_body = str_replace('{{amount}}', number_format($assessment_fee, 2), $email_body);

                $subject = "Payment Confirmation - Mackenny Assessment";

                if (!sendEmail($email, $subject, $email_body)) {
                    error_log("Failed to send payment confirmation email to " . $email);
                    $_SESSION['form_message'] = "Registration and payment initiated, but we could not send the confirmation email.";
                    $_SESSION['form_message_type'] = 'warning';
                }

                // Redirect to Paystack payment
                $_SESSION['payment_data'] = [
                    'user_id' => $new_user_id,
                    'quiz_id' => $quiz_id,
                    'transaction_reference' => $transaction_reference,
                    'email' => $email,
                    'amount' => $assessment_fee
                ];
                redirect('paystack_payment.php');
            }
        } catch (PDOException $e) {
            error_log("Payment Registration Error: " . $e->getMessage());
            $message = "An unexpected error occurred. Please try again later.";
        }
    }

    if (!empty($message)) {
        $_SESSION['form_message'] = $message;
        $_SESSION['form_message_type'] = 'error';
        header('Location: payment.php?quiz_id=' . urlencode($quiz_id) . '&amount=' . urlencode($amount_from_url));
        exit;
    }
}

// Handle payment initiation for logged-in users
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $quiz_id && $user) {
    try {
        // Generate transaction reference
        $transaction_reference = 'QZPAY_' . time() . '_' . $user['user_id'] . '_' . $quiz_id . '_' . bin2hex(random_bytes(4));

        // Insert pending payment
        $stmt_payment = $pdo->prepare("
            INSERT INTO payments (user_id, quiz_id, amount, status, transaction_reference)
            VALUES (:user_id, :quiz_id, :amount, 'pending', :transaction_reference)
        ");
        $stmt_payment->execute([
            'user_id' => $user['user_id'],
            'quiz_id' => $quiz_id,
            'amount' => $assessment_fee,
            'transaction_reference' => $transaction_reference
        ]);

        // Send payment confirmation email
        ob_start();
        require '../includes/email_templates/payment_confirmation_email.php';
        $email_body = ob_get_clean();
        $email_body = str_replace('{{username}}', htmlspecialchars($user['username']), $email_body);
        $email_body = str_replace('{{quiz_title}}', htmlspecialchars($quiz_title), $email_body);
        $email_body = str_replace('{{amount}}', number_format($assessment_fee, 2), $email_body);

        $subject = "Payment Confirmation - Mackenny Assessment";

        if (!sendEmail($user['email'], $subject, $email_body)) {
            error_log("Failed to send payment confirmation email to " . $user['email']);
            $_SESSION['form_message'] = "Payment initiated, but we could not send the confirmation email.";
            $_SESSION['form_message_type'] = 'warning';
        }

        // Redirect to Paystack payment
        $_SESSION['payment_data'] = [
            'user_id' => $user['user_id'],
            'quiz_id' => $quiz_id,
            'transaction_reference' => $transaction_reference,
            'email' => $user['email'],
            'amount' => $assessment_fee
        ];
        redirect('paystack_payment.php');
    } catch (PDOException $e) {
        error_log("Payment Initiation Error: " . $e->getMessage());
        $message = "An error occurred while initiating payment. Please try again.";
        $_SESSION['form_message'] = $message;
        $_SESSION['form_message_type'] = 'error';
        header('Location: payment.php?quiz_id=' . urlencode($quiz_id) . '&amount=' . urlencode($amount_from_url));
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment - Mackenny Assessment</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .bg-navy-900 { background-color: #0a1930; }
        .bg-navy-800 { background-color: #1a2b4a; }
        .hover\:bg-navy-700:hover { background-color: #2c3e6a; }
        .focus\:ring-navy-900:focus { --tw-ring-color: #0a1930; }
        .text-theme-color { color: #1e4b31; }
    </style>
    <script src="https://js.paystack.co/v1/inline.js"></script>
</head>
<body class="bg-gradient-to-br from-gray-100 to-blue-50 min-h-screen flex items-center justify-center">
    <div class="container mx-auto px-4 py-8">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 max-w-6xl mx-auto bg-white rounded-xl shadow-2xl overflow-hidden">
            <div class="bg-navy-900 text-white p-12 flex flex-col justify-center">
                <h1 class="text-4xl font-bold mb-4">Pay for <?php echo htmlspecialchars($quiz_title); ?></h1>
                <p class="text-lg mb-6">
                    <?php if ($user): ?>
                        You are about to make a payment for "<?php echo htmlspecialchars($quiz_title); ?>".
                    <?php else: ?>
                        Complete your registration and payment to access "<?php echo htmlspecialchars($quiz_title); ?>".
                    <?php endif; ?>
                    Amount Due: <span class="font-bold">₦<?php echo number_format($assessment_fee, 2); ?></span>
                </p>
                <p class="text-sm italic">
                    You'll receive a confirmation email after payment.
                </p>
            </div>
            <div class="p-12 relative">
                <h2 class="text-3xl font-bold text-gray-800 mb-6 text-center">
                    <?php echo $user ? 'Confirm Payment' : 'Registration & Payment'; ?>
                </h2>
                <div id="form-notification" class="absolute top-0 left-0 w-full px-4 py-3 rounded-md hidden" role="alert">
                    <strong class="font-bold"></strong>
                    <span class="block sm:inline" id="notification-message-content"></span>
                    <span class="absolute top-0 bottom-0 right-0 px-4 py-3 cursor-pointer" onclick="hideNotification()">
                        <svg fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24" class="h-6 w-6">
                            <path d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </span>
                </div>
                <?php if ($quiz_id && $user): ?>
                    <!-- Payment Confirmation for Logged-in User -->
                    <div class="space-y-6">
                        <div>
                            <p class="text-gray-700 text-sm font-semibold mb-2">Username: <span class="font-normal"><?php echo htmlspecialchars($user['username']); ?></span></p>
                            <p class="text-gray-700 text-sm font-semibold mb-2">Email: <span class="font-normal"><?php echo htmlspecialchars($user['email']); ?></span></p>
                            <p class="text-gray-700 text-sm font-semibold mb-2">Assessment: <span class="font-normal"><?php echo htmlspecialchars($quiz_title); ?></span></p>
                            <p class="text-gray-700 text-sm font-semibold mb-2">Amount: <span class="font-normal">₦<?php echo number_format($assessment_fee, 2); ?></span></p>
                        </div>
                        <form action="payment.php?quiz_id=<?php echo urlencode($quiz_id); ?>&amount=<?php echo urlencode($amount_from_url); ?>" method="POST" class="flex items-center justify-between">
                            <button type="submit" class="bg-navy-900 hover:bg-navy-700 text-white font-semibold py-3 px-6 rounded-lg shadow-md focus:outline-none focus:ring-2 focus:ring-navy-900 focus:ring-offset-2 transition duration-200">
                                Proceed to Payment
                            </button>
                        </form>
                    </div>
                <?php elseif ($quiz_id): ?>
                    <!-- Registration Form for Unregistered Users -->
                    <form action="payment.php?quiz_id=<?php echo urlencode($quiz_id); ?>&amount=<?php echo urlencode($amount_from_url); ?>" method="POST" class="space-y-6">
                        <div>
                            <label for="first_name" class="block text-gray-700 text-sm font-semibold mb-2">First Name</label>
                            <input type="text" id="first_name" name="first_name" required class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-navy-900 focus:border-transparent transition duration-200" placeholder="Enter your first name" value="<?php echo htmlspecialchars($first_name ?? ''); ?>">
                        </div>
                        <div>
                            <label for="last_name" class="block text-gray-700 text-sm font-semibold mb-2">Last Name</label>
                            <input type="text" id="last_name" name="last_name" required class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-navy-900 focus:border-transparent transition duration-200" placeholder="Enter your last name" value="<?php echo htmlspecialchars($last_name ?? ''); ?>">
                        </div>
                        <div>
                            <label for="email" class="block text-gray-700 text-sm font-semibold mb-2">Email</label>
                            <input type="email" id="email" name="email" required class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-navy-900 focus:border-transparent transition duration-200" placeholder="Enter your email address" value="<?php echo htmlspecialchars($email ?? ''); ?>">
                        </div>
                        <div>
                            <label for="gender" class="block text-gray-700 text-sm font-semibold mb-2">Gender</label>
                            <select id="gender" name="gender" required class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-navy-900 focus:border-transparent transition duration-200">
                                <option value="">Select Gender</option>
                                <option value="Male" <?php echo ($gender == 'Male') ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo ($gender == 'Female') ? 'selected' : ''; ?>>Female</option>
                            </select>
                        </div>
                        <div>
                            <label for="address" class="block text-gray-700 text-sm font-semibold mb-2">Address</label>
                            <textarea id="address" name="address" rows="3" required class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-navy-900 focus:border-transparent transition duration-200" placeholder="Enter your full address"><?php echo htmlspecialchars($address ?? ''); ?></textarea>
                        </div>
                        <div>
                            <label for="date_of_birth" class="block text-gray-700 text-sm font-semibold mb-2">Date of Birth</label>
                            <input type="date" id="date_of_birth" name="date_of_birth" required class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-navy-900 focus:border-transparent transition duration-200" value="<?php echo htmlspecialchars($date_of_birth ?? ''); ?>">
                        </div>
                        <div class="flex items-center justify-between">
                            <button type="submit" class="bg-navy-900 hover:bg-navy-700 text-white font-semibold py-3 px-6 rounded-lg shadow-md focus:outline-none focus:ring-2 focus:ring-navy-900 focus:ring-offset-2 transition duration-200">
                                Proceed to Payment
                            </button>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-lg shadow-md" role="alert">
                        <p class="font-bold">Error!</p>
                        <p><?php echo htmlspecialchars($message); ?></p>
                        <div class="mt-4 text-center">
                            <a href="<?php echo BASE_URL; ?>student/assessments.php" class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-md transition duration-300">
                                Return to Assessments
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
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

        <?php if (isset($_SESSION['form_message'])): ?>
            displayNotification("<?php echo htmlspecialchars($_SESSION['form_message']); ?>", "<?php echo htmlspecialchars($_SESSION['form_message_type']); ?>");
            <?php
            unset($_SESSION['form_message']);
            unset($_SESSION['form_message_type']);
            ?>
        <?php endif; ?>
    </script>
</body>
</html>