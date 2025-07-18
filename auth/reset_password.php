<?php
// auth/reset_password.php
// Handles password reset after clicking the link from the email.

require_once '../includes/session.php';
require_once '../includes/db.php'; // Assuming $pdo is initialized in db.php
require_once '../includes/functions.php'; // For sanitize_input, display_message, redirect

// Generate and store CSRF token if not already present
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message_html = ''; // Initialize message variable for feedback (will hold HTML)
$token = sanitize_input($_GET['token'] ?? '');
$user_id = null; // To store the user_id if token is valid

// Function to generate a Tailwind CSS styled message
function generate_tailwind_message(string $message, string $type): string {
    $class = '';
    $title = '';
    if ($type === 'error') {
        $class = 'bg-red-100 border-red-400 text-red-700';
        $title = 'Error!';
    } elseif ($type === 'success') {
        $class = 'bg-green-100 border-green-400 text-green-700';
        $title = 'Success!';
    }
    return "<div class='p-4 mb-4 text-sm rounded-lg border " . htmlspecialchars($class) . "' role='alert'>
                <strong class='font-bold'>" . htmlspecialchars($title) . "</strong>
                <span class='block sm:inline'>" . htmlspecialchars($message) . "</span>
            </div>";
}

// Validate the token
if (empty($token)) {
    $message_html = generate_tailwind_message("Invalid or missing password reset token.", "error");
} else {
    try {
        $stmt = $pdo->prepare("SELECT user_id, reset_token_expiry FROM users WHERE reset_token = :token");
        $stmt->execute(['token' => $token]);
        $user = $stmt->fetch();

        if (!$user || strtotime($user['reset_token_expiry']) < time()) {
            $message_html = generate_tailwind_message("Password reset token is invalid or has expired. Please request a new one.", "error");
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
        $message_html = generate_tailwind_message("An unexpected error occurred during token validation. Please try again later.", "error");
    }
}

// Handle password reset form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user_id) {
    // Validate CSRF token for POST request
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message_html = generate_tailwind_message("Invalid form submission. Please try again.", "error");
        // Do not proceed with password update if token is invalid
        $user_id = null; // Invalidate user_id to prevent form display
    } else {
        // Regenerate CSRF token after successful validation to prevent reuse
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        $new_password = sanitize_input($_POST['new_password'] ?? '');
        $confirm_password = sanitize_input($_POST['confirm_password'] ?? '');

        if (empty($new_password) || empty($confirm_password)) {
            $message_html = generate_tailwind_message("Please enter and confirm your new password.", "error");
        } elseif ($new_password !== $confirm_password) {
            $message_html = generate_tailwind_message("Passwords do not match. Please try again.", "error");
        } elseif (strlen($new_password) < 8) { // Example password policy
            $message_html = generate_tailwind_message("Password must be at least 8 characters long.", "error");
        } else {
            try {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

                // Update the user's password and clear the reset token
                $update_stmt = $pdo->prepare("UPDATE users SET password_hash = :password_hash, reset_token = NULL, reset_token_expiry = NULL WHERE user_id = :user_id");
                $update_stmt->execute([
                    'password_hash' => $hashed_password,
                    'user_id' => $user_id
                ]);

                $message_html = generate_tailwind_message("Your password has been successfully reset. You can now log in with your new password.", "success");
                // Redirect to login page after a short delay
                // Using JavaScript redirect for a smoother user experience after message display
                echo "<script>setTimeout(function(){ window.location.href = 'login.php'; }, 3000);</script>";
                // No exit() here as we want the HTML to render with the message and then redirect.

            } catch (PDOException $e) {
                error_log("Password Reset Error: " . $e->getMessage());
                $message_html = generate_tailwind_message("An unexpected error occurred during password reset. Please try again later.", "error");
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body>
    <div class="container mx-auto p-4 py-8">
        <div class="max-w-md mx-auto bg-white p-8 rounded-lg shadow-lg">
            <h1 class="text-3xl font-bold text-center mb-6" style="color: #1e4b31;">Reset Password</h1>

            <?php echo $message_html; // Display any feedback messages ?>

            <?php if ($user_id && strpos($message_html, 'error') === false): // Only show form if token is valid and no error from token validation ?>
                <form action="reset_password.php?token=<?php echo htmlspecialchars($token); ?>" method="POST" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

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
            <?php else: // If token is invalid/expired or an error occurred, show back to login ?>
                <div class="text-center mt-4">
                    <a href="login.php" class="inline-block align-baseline font-bold text-sm text-blue-500 hover:text-blue-800">
                        Back to Login
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>