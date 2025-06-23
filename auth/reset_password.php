<?php
// auth/reset_password.php
// Handles password reset: verifies token, allows new password input, and updates password.

require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

$message = '';
$token_valid = false;
$token = sanitize_input($_GET['token'] ?? '');

// Verify the token
if (!empty($token)) {
    try {
        $stmt = $pdo->prepare("SELECT user_id, expires_at FROM password_resets WHERE token = :token");
        $stmt->execute(['token' => $token]);
        $reset_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($reset_data) {
            $expires_at = strtotime($reset_data['expires_at']);
            if (time() < $expires_at) {
                // Token is valid and not expired
                $token_valid = true;
                $user_id = $reset_data['user_id'];
            } else {
                $message = display_message("Password reset link has expired. Please request a new one.", "error");
            }
        } else {
            $message = display_message("Invalid password reset token.", "error");
        }
    } catch (PDOException $e) {
        error_log("Reset Password Token Verification Error: " . $e->getMessage());
        $message = display_message("An unexpected error occurred during token verification. Please try again later.", "error");
    }
} else {
    $message = display_message("No reset token provided.", "error");
}

// Handle password reset form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password']) && $token_valid) {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $submitted_token = sanitize_input($_POST['token'] ?? ''); // Get token from hidden field

    // Re-verify the token to prevent tampering between page load and submission
    if ($submitted_token !== $token) {
        $message = display_message("Token mismatch. Please try requesting a new password reset.", "error");
        $token_valid = false; // Invalidate token for further processing
    }

    if ($token_valid) { // Only proceed if token is still considered valid
        if (empty($new_password) || empty($confirm_password)) {
            $message = display_message("Please enter and confirm your new password.", "error");
        } elseif ($new_password !== $confirm_password) {
            $message = display_message("Passwords do not match.", "error");
        } elseif (strlen($new_password) < 8) { // Example: Minimum password length
            $message = display_message("New password must be at least 8 characters long.", "error");
        } else {
            try {
                $pdo->beginTransaction();

                $password_hash = password_hash($new_password, PASSWORD_DEFAULT);

                // Update the user's password
                $stmt_update_password = $pdo->prepare("UPDATE users SET password_hash = :password_hash WHERE user_id = :user_id");
                $stmt_update_password->execute(['password_hash' => $password_hash, 'user_id' => $user_id]);

                // Invalidate the token after successful reset
                $stmt_delete_token = $pdo->prepare("DELETE FROM password_resets WHERE user_id = :user_id");
                $stmt_delete_token->execute(['user_id' => $user_id]);

                $pdo->commit();
                $message = display_message("Your password has been successfully reset. You can now login.", "success");
                $token_valid = false; // Prevent further password changes with this token
            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log("Password Reset Error: " . $e->getMessage());
                $message = display_message("An unexpected error occurred during password reset. Please try again later.", "error");
            }
        }
    }
}

require_once '../includes/header_public.php';
?>

<div class="container mx-auto p-4 py-8">
    <div class="max-w-md mx-auto bg-white p-8 rounded-lg shadow-lg">
        <h1 class="text-3xl font-bold text-center mb-6" style="color: #1e4b31;">Reset Your Password</h1>

        <?php echo $message; ?>

        <?php if ($token_valid): ?>
            <form action="reset_password.php?token=<?php echo htmlspecialchars($token); ?>" method="POST" class="space-y-4">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                <input type="hidden" name="reset_password" value="1">
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
                <div class="flex items-center justify-between">
                    <button type="submit"
                            class="bg-green-700 hover:bg-green-800 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-300">
                        Reset Password
                    </button>
                    <a href="login.php" class="inline-block align-baseline font-bold text-sm text-blue-500 hover:text-blue-800">
                        Back to Login
                    </a>
                </div>
            </form>
        <?php else: ?>
            <p class="text-center text-gray-600">Please go back to the <a href="login.php" class="text-blue-500 hover:underline">login page</a> or <a href="forgot_password.php" class="text-purple-500 hover:underline">request a new reset link</a> if you landed here incorrectly.</p>
        <?php endif; ?>
    </div>
</div>

<?php
require_once '../includes/footer_public.php';
?>