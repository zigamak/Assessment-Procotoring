<?php
// auth/reset_password.php
// Handles password reset after clicking the link from the email.

require_once '../includes/session.php';
require_once '../includes/db.php'; // Assuming $pdo is initialized in db.php
require_once '../includes/functions.php'; // For sanitize_input, display_message, redirect

$message = ''; // Initialize message variable for feedback
$token = sanitize_input($_GET['token'] ?? '');
$user_id = null; // To store the user_id if token is valid

// Validate the token
if (empty($token)) {
    $message = display_message("Invalid or missing password reset token.", "error");
} else {
    try {
        $stmt = $pdo->prepare("SELECT user_id, reset_token_expiry FROM users WHERE reset_token = :token");
        $stmt->execute(['token' => $token]);
        $user = $stmt->fetch();

        if (!$user || strtotime($user['reset_token_expiry']) < time()) {
            $message = display_message("Password reset token is invalid or has expired. Please request a new one.", "error");
            // Clear the token in the database if it's expired or invalid to prevent reuse
            if ($user) {
                $clear_token_stmt = $pdo->prepare("UPDATE users SET reset_token = NULL, reset_token_expiry = NULL WHERE user_id = :user_id");
                $clear_token_stmt->execute(['user_id' => $user['user_id']]);
            }
        } else {
            $user_id = $user['user_id']; // Token is valid, store user_id
        }
    } catch (PDOException $e) {
        error_log("Reset Password Token Validation Error: " . $e->getMessage());
        $message = display_message("An unexpected error occurred during token validation. Please try again later.", "error");
    }
}

// Handle password reset form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user_id) {
    $new_password = sanitize_input($_POST['new_password'] ?? '');
    $confirm_password = sanitize_input($_POST['confirm_password'] ?? '');

    if (empty($new_password) || empty($confirm_password)) {
        $message = display_message("Please enter and confirm your new password.", "error");
    } elseif ($new_password !== $confirm_password) {
        $message = display_message("Passwords do not match. Please try again.", "error");
    } elseif (strlen($new_password) < 8) { // Example password policy
        $message = display_message("Password must be at least 8 characters long.", "error");
    } else {
        try {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

            // Update the user's password and clear the reset token
            $update_stmt = $pdo->prepare("UPDATE users SET password_hash = :password_hash, reset_token = NULL, reset_token_expiry = NULL WHERE user_id = :user_id");
            $update_stmt->execute([
                'password_hash' => $hashed_password,
                'user_id' => $user_id
            ]);

            $message = display_message("Your password has been successfully reset. You can now log in with your new password.", "success");
            // Redirect to login page after a short delay
            header("Refresh: 3; url=login.php"); // Redirect after 3 seconds
            exit();

        } catch (PDOException $e) {
            error_log("Password Reset Error: " . $e->getMessage());
            $message = display_message("An unexpected error occurred during password reset. Please try again later.", "error");
        }
    }
}

// Include the public header for the reset password page
require_once '../includes/header_public.php';
?>

<div class="container mx-auto p-4 py-8">
    <div class="max-w-md mx-auto bg-white p-8 rounded-lg shadow-lg">
        <h1 class="text-3xl font-bold text-center mb-6" style="color: #1e4b31;">Reset Password</h1>

        <?php echo $message; // Display any feedback messages ?>

        <?php if ($user_id && empty($message)): // Only show form if token is valid and no error message ?>
            <form action="reset_password.php?token=<?php echo htmlspecialchars($token); ?>" method="POST" class="space-y-4">
                <div>
                    <label for="new_password" class="block text-gray-700 text-sm font-bold mb-2">New Password:</label>
                    <input type="password" id="new_password" name="new_password" required
                           class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-green-500">
                </div>
                <div>
                    <label for="confirm_password" class="block text-gray-700 text-sm font-bold mb-2">Confirm New Password:</label>
                    <input type="password" id="confirm_password" name="confirm_password" required
                           class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 mb-3 leading-tight focus:outline-none focus:shadow-outline focus:border-green-500">
                </div>
                <div class="flex items-center justify-center">
                    <button type="submit"
                            class="bg-green-700 hover:bg-green-800 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-300">
                        Reset Password
                    </button>
                </div>
            </form>
        <?php elseif (!$user_id && empty($message)): // If token is invalid/expired and no message yet, show back to login ?>
            <div class="text-center mt-4">
                <a href="login.php" class="inline-block align-baseline font-bold text-sm text-blue-500 hover:text-blue-800">
                    Back to Login
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
// Include the public footer
require_once '../includes/footer_public.php';
?>
