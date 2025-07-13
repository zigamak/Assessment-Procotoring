<?php
// auth/forgot_password.php
// Handles the request for a password reset link.

require_once '../includes/session.php';
require_once '../includes/db.php'; // Assuming $pdo is initialized in db.php
require_once '../includes/functions.php'; // For sanitize_input, display_message, redirect
require_once '../includes/send_email.php'; // For the sendEmail function

$message = ''; // Initialize message variable for feedback

// Handle forgot password form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize_input($_POST['email'] ?? '');

    if (empty($email)) {
        $message = display_message("Please enter your email address.", "error");
    } else {
        try {
            // Check if the email exists in the database
            $stmt = $pdo->prepare("SELECT user_id, username FROM users WHERE email = :email");
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch();

            if ($user) {
                // Generate a unique token
                $token = bin2hex(random_bytes(32)); // 64 character hex string
                $expiry = date('Y-m-d H:i:s', strtotime('+1 hour')); // Token valid for 1 hour

                // Store the token and expiry in the database for the user
                $update_stmt = $pdo->prepare("UPDATE users SET reset_token = :token, reset_token_expiry = :expiry WHERE user_id = :user_id");
                $update_stmt->execute([
                    'token' => $token,
                    'expiry' => $expiry,
                    'user_id' => $user['user_id']
                ]);

                // Construct the reset link
                $reset_link = "http://" . $_SERVER['HTTP_HOST'] . "/auth/reset_password.php?token=" . $token;

                // Email content
                $subject = "Password Reset Request for Mackenny Assessment";
                $body = "
                    <p>Dear " . htmlspecialchars($user['username']) . ",</p>
                    <p>You have requested a password reset for your Mackenny Assessment account.</p>
                    <p>Please click on the following link to reset your password:</p>
                    <p><a href='" . htmlspecialchars($reset_link) . "'>" . htmlspecialchars($reset_link) . "</a></p>
                    <p>This link will expire in 1 hour.</p>
                    <p>If you did not request a password reset, please ignore this email.</p>
                    <p>Thank you,<br>The Mackenny Assessment Team</p>
                ";

                // Send the email
                if (sendEmail($email, $subject, $body)) {
                    $message = display_message("A password reset link has been sent to your email address. Please check your inbox (and spam folder).", "success");
                } else {
                    $message = display_message("Failed to send password reset email. Please try again later.", "error");
                    error_log("Failed to send password reset email to " . $email);
                }

            } else {
                // For security, do not reveal if the email exists or not.
                $message = display_message("If your email address is in our system, a password reset link will be sent to it.", "success");
            }
        } catch (PDOException $e) {
            error_log("Forgot Password Error: " . $e->getMessage());
            $message = display_message("An unexpected error occurred. Please try again later.", "error");
        }
    }
}

// Include the public header for the forgot password page
require_once '../includes/header_public.php';
?>

<div class="container mx-auto p-4 py-8">
    <div class="max-w-md mx-auto bg-white p-8 rounded-lg shadow-lg">
        <h1 class="text-3xl font-bold text-center mb-6" style="color: #1e4b31;">Forgot Password</h1>

        <?php echo $message; // Display any feedback messages ?>

        <form action="forgot_password.php" method="POST" class="space-y-4">
            <div>
                <label for="email" class="block text-gray-700 text-sm font-bold mb-2">Enter your Email Address:</label>
                <input type="email" id="email" name="email" required
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-green-500">
            </div>
            <div class="flex items-center justify-between">
                <button type="submit"
                        class="bg-green-700 hover:bg-green-800 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-300">
                    Send Reset Link
                </button>
                <a href="login.php" class="inline-block align-baseline font-bold text-sm text-blue-500 hover:text-blue-800">
                    Back to Login
                </a>
            </div>
        </form>
    </div>
</div>

<?php
// Include the public footer
require_once '../includes/footer_public.php';
?>
