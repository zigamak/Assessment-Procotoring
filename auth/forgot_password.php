<?php
// auth/forgot_password.php
// Handles the request for a password reset link.

// Ensure session is started at the very beginning
require_once '../includes/session.php';

// Include database connection and global configuration (including SMTP settings) FIRST
require_once '../includes/db.php';

// Include necessary files for utility functions and email sending
require_once '../includes/functions.php'; // Provides sanitize_input, redirect (if needed elsewhere)
require_once '../includes/send_email.php'; // Provides the sendEmail function


// Generate and store CSRF token if not already present
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle forgot password form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['form_message'] = "Invalid form submission. Please try again.";
        $_SESSION['form_message_type'] = 'error';
        header('Location: forgot_password.php');
        exit;
    }

    // Regenerate CSRF token after successful validation to prevent reuse
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    $email = sanitize_input($_POST['email'] ?? '');

    if (empty($email)) {
        // Store error message in session for display by JavaScript
        $_SESSION['form_message'] = "Please enter your email address.";
        $_SESSION['form_message_type'] = 'error';
    } else {
        try {
            // Check if the email exists in the database
            $stmt = $pdo->prepare("SELECT user_id, username FROM users WHERE email = :email");
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch();

            if ($user) {
                // Generate a unique token
                $token = bin2hex(random_bytes(32)); // Generates a 64-character hexadecimal string
                $expiry = date('Y-m-d H:i:s', strtotime('+1 hour')); // Token valid for 1 hour from now

                // Store the token and its expiry in the database for the user
                // This assumes your 'users' table has 'reset_token' and 'reset_token_expiry' columns.
                $update_stmt = $pdo->prepare("UPDATE users SET reset_token = :token, reset_token_expiry = :expiry WHERE user_id = :user_id");
                if ($update_stmt->execute([
                    'token' => $token,
                    'expiry' => $expiry,
                    'user_id' => $user['user_id']
                ])) {
                    // Construct the full password reset link using BASE_URL
                    $reset_link = BASE_URL . "/auth/reset_password.php?token=" . $token;

                    // Include the email template and capture its output
                    ob_start(); // Start output buffering
                    require '../includes/email_templates/password_reset.php'; // Include the template
                    $body = ob_get_clean(); // Get the buffered content and clean the buffer

                    // Replace placeholders in the email body
                    $body = str_replace('{{username}}', htmlspecialchars($user['username']), $body);
                    $body = str_replace('{{reset_link}}', htmlspecialchars($reset_link), $body);

                    $subject = "Password Reset Request for Mackenny Assessment";

                    // Send the email using the sendEmail function
                    if (sendEmail($email, $subject, $body)) {
                        $_SESSION['form_message'] = "A password reset link has been sent to your email address. Please check your inbox (and spam folder).";
                        $_SESSION['form_message_type'] = 'success'; // Use 'success' type for positive feedback
                    } else {
                        $_SESSION['form_message'] = "Failed to send password reset email. Please try again later.";
                        $_SESSION['form_message_type'] = 'error';
                        // The sendEmail function already logs the detailed error, no need for another error_log here.
                    }
                } else {
                    $_SESSION['form_message'] = "Failed to generate reset token. Please try again.";
                    $_SESSION['form_message_type'] = 'error';
                }

            } else {
                // For security reasons, always give a generic success message
                // to avoid revealing whether an email address exists in the system.
                $_SESSION['form_message'] = "If your email address is in our system, a password reset link will be sent to it.";
                $_SESSION['form_message_type'] = 'success';
            }
        } catch (PDOException $e) {
            // Catch and log any database-related errors
            error_log("Forgot Password Error: " . $e->getMessage());
            $_SESSION['form_message'] = "An unexpected error occurred. Please try again later.";
            $_SESSION['form_message_type'] = 'error';
        }
    }
    // Redirect to the same page to prevent form resubmission on refresh
    // and to ensure the session message is picked up by JavaScript.
    header('Location: forgot_password.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Mackenny Assessment</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Custom theme colors consistent with login.php and register.php */
        .bg-navy-900 { background-color: #0a1930; }
        .bg-navy-800 { background-color: #1a2b4a; }
        .hover\:bg-navy-700:hover { background-color: #2c3e6a; }
        .focus\:ring-navy-900:focus { --tw-ring-color: #0a1930; }
        /* The .text-theme-color was more for a specific heading, keeping navy dominant */
        .text-theme-color { color: #1e4b31; }

        /* Adjusted green colors to be more in line with a deeper, consistent theme */
        .bg-green-700 { background-color: #1e4b31; }
        .hover\:bg-green-800:hover { background-color: #1a3d28; }
        .focus\:border-green-500:focus { border-color: #1e4b31; }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-100 to-blue-50 min-h-screen flex items-center justify-center">

<div class="container mx-auto px-4 py-8">
    <div class="max-w-md mx-auto bg-white p-8 rounded-lg shadow-2xl relative overflow-hidden">
        <h1 class="text-3xl font-bold text-center mb-6 text-gray-800">Forgot Password</h1>

        <div id="form-notification" class="absolute top-0 left-0 w-full px-4 py-3 rounded-md" role="alert" style="transform: translateY(-100%);">
            <strong class="font-bold"></strong>
            <span class="block sm:inline" id="notification-message-content"></span>
            <span class="absolute top-0 bottom-0 right-0 px-4 py-3 cursor-pointer" onclick="hideNotification()">
                <svg fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24" class="h-6 w-6">
                    <path d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </span>
        </div>

        <form action="forgot_password.php" method="POST" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

            <div>
                <label for="email" class="block text-gray-700 text-sm font-semibold mb-2">Enter your Email Address:</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    required
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-navy-900 focus:border-transparent transition duration-200"
                    placeholder="e.g., your.email@example.com"
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                >
            </div>
            <div class="flex items-center justify-between">
                <button
                    type="submit"
                    class="bg-navy-900 hover:bg-navy-700 text-white font-semibold py-3 px-6 rounded-lg shadow-md focus:outline-none focus:ring-2 focus:ring-navy-900 focus:ring-offset-2 transition duration-200"
                >
                    Send Reset Link
                </button>
                <a href="login.php" class="inline-block align-baseline font-semibold text-sm text-blue-600 hover:text-blue-800">
                    Back to Login
                </a>
            </div>
        </form>
    </div>
</div>

<script>
    const notificationContainer = document.getElementById('form-notification');
    const messageContentElement = document.getElementById('notification-message-content');
    const strongTag = notificationContainer.querySelector('strong');

    // Function to display a Tailwind CSS notification (error or success)
    function displayNotification(message, type) {
        // Reset classes to remove previous styling
        notificationContainer.classList.remove('bg-red-100', 'border-red-400', 'text-red-700', 'bg-green-100', 'border-green-400', 'text-green-700');
        strongTag.textContent = ''; // Clear existing title text

        if (message) {
            messageContentElement.textContent = message; // Set the message content

            // Apply specific classes based on message type
            if (type === 'error') {
                notificationContainer.classList.add('bg-red-100', 'border-red-400', 'text-red-700');
                strongTag.textContent = 'Error!'; // Set title for error
            } else if (type === 'success') {
                notificationContainer.classList.add('bg-green-100', 'border-green-400', 'text-green-700');
                strongTag.textContent = 'Success!'; // Set title for success
            }

            // Remove hidden class and slide down
            notificationContainer.classList.remove('hidden');
            setTimeout(() => {
                notificationContainer.style.transition = 'transform 0.3s ease-out';
                notificationContainer.style.transform = 'translateY(0)'; // Slide down into view
            }, 10);
        } else {
            hideNotification(); // If no message, ensure it's hidden
        }
    }

    // Function to hide the notification
    function hideNotification() {
        notificationContainer.style.transition = 'transform 0.3s ease-in';
        notificationContainer.style.transform = 'translateY(-100%)'; // Slide up out of view

        // Add a single-use event listener to add 'hidden' class after transition
        notificationContainer.addEventListener('transitionend', function handler() {
            notificationContainer.classList.add('hidden');
            // Remove the event listener to prevent it from firing multiple times
            notificationContainer.removeEventListener('transitionend', handler);
        });
    }


    // Check for messages from PHP session when the DOM is fully loaded
    document.addEventListener('DOMContentLoaded', function() {
        <?php
        // This PHP block runs only once when the page is loaded by the server.
        // It injects JavaScript to display any messages stored in the session.
        if (isset($_SESSION['form_message'])):
        ?>
            displayNotification("<?= htmlspecialchars($_SESSION['form_message']) ?>", "<?= htmlspecialchars($_SESSION['form_message_type']) ?>");
            <?php
            // Clear the session messages immediately after they are used to prevent re-display
            unset($_SESSION['form_message']);
            unset($_SESSION['form_message_type']);
            ?>
        <?php endif; ?>
    });
</script>

</body>
</html>