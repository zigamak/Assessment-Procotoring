<?php
// student/profile.php
// Allows students to view and edit their profile information, including changing email and password.

require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Include the student specific header. This also handles role enforcement.
require_once '../includes/header_student.php';

$message = ''; // Initialize message variable for feedback
$user_id = getUserId();

// Fetch current user data
$current_username = '';
$current_email = '';

try {
    $stmt = $pdo->prepare("SELECT username, email FROM users WHERE user_id = :user_id");
    $stmt->execute(['user_id' => $user_id]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user_data) {
        $current_username = htmlspecialchars($user_data['username']);
        $current_email = htmlspecialchars($user_data['email']);
    } else {
        // This should ideally not happen if role enforcement is working
        $message = display_message("User data not found. Please log in again.", "error");
        // Redirect to logout or login page
        redirect(BASE_URL . 'auth/logout.php');
    }
} catch (PDOException $e) {
    error_log("Profile Data Fetch Error: " . $e->getMessage());
    $message = display_message("An error occurred while fetching profile data. Please try again later.", "error");
}


// Handle POST requests for updating profile
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = sanitize_input($_POST['action']);

        switch ($action) {
            case 'update_email':
                $new_email = sanitize_input($_POST['email'] ?? '');

                if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
                    $message = display_message("Invalid email format.", "error");
                } elseif (empty($new_email)) {
                    $message = display_message("Email cannot be empty.", "error");
                } else {
                    try {
                        // Check if the new email already exists for another user
                        $stmt_check_email = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = :email AND user_id != :user_id");
                        $stmt_check_email->execute(['email' => $new_email, 'user_id' => $user_id]);
                        if ($stmt_check_email->fetchColumn() > 0) {
                            $message = display_message("This email is already registered by another user.", "error");
                        } else {
                            $stmt_update_email = $pdo->prepare("UPDATE users SET email = :email WHERE user_id = :user_id");
                            if ($stmt_update_email->execute(['email' => $new_email, 'user_id' => $user_id])) {
                                $_SESSION['email'] = $new_email; // Update session with new email
                                $current_email = htmlspecialchars($new_email); // Update display
                                $message = display_message("Email updated successfully!", "success");
                            } else {
                                $message = display_message("Failed to update email.", "error");
                            }
                        }
                    } catch (PDOException $e) {
                        error_log("Update Email Error: " . $e->getMessage());
                        $message = display_message("Database error while updating email.", "error");
                    }
                }
                break;

            case 'change_password':
                $current_password = sanitize_input($_POST['current_password'] ?? '');
                $new_password = sanitize_input($_POST['new_password'] ?? '');
                $confirm_new_password = sanitize_input($_POST['confirm_new_password'] ?? '');

                if (empty($current_password) || empty($new_password) || empty($confirm_new_password)) {
                    $message = display_message("All password fields are required.", "error");
                } elseif ($new_password !== $confirm_new_password) {
                    $message = display_message("New password and confirmation do not match.", "error");
                } elseif (strlen($new_password) < 6) { // Example: minimum 6 characters
                    $message = display_message("New password must be at least 6 characters long.", "error");
                } else {
                    try {
                        // Verify current password
                        $stmt_get_password = $pdo->prepare("SELECT password_hash FROM users WHERE user_id = :user_id");
                        $stmt_get_password->execute(['user_id' => $user_id]);
                        $user_password_data = $stmt_get_password->fetch(PDO::FETCH_ASSOC);

                        if ($user_password_data && password_verify($current_password, $user_password_data['password_hash'])) {
                            // Hash the new password
                            $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);

                            $stmt_update_password = $pdo->prepare("UPDATE users SET password_hash = :password_hash WHERE user_id = :user_id");
                            if ($stmt_update_password->execute(['password_hash' => $new_password_hash, 'user_id' => $user_id])) {
                                $message = display_message("Password changed successfully!", "success");
                            } else {
                                $message = display_message("Failed to change password.", "error");
                            }
                        } else {
                            $message = display_message("Incorrect current password.", "error");
                        }
                    } catch (PDOException $e) {
                        error_log("Change Password Error: " . $e->getMessage());
                        $message = display_message("Database error while changing password.", "error");
                    }
                }
                break;
        }
    }
}

?>

<div class="container mx-auto p-4 py-8">
    <h1 class="text-3xl font-bold text-theme-color mb-6">Your Profile</h1>

    <?php echo $message; // Display any feedback messages ?>

    <div class="bg-white p-6 rounded-lg shadow-md mb-8">
        <h2 class="text-2xl font-semibold text-gray-800 mb-4">Profile Information</h2>
        <div class="space-y-4">
            <p class="text-gray-700"><strong class="font-semibold">Username:</strong> <?php echo $current_username; ?></p>
            <p class="text-gray-700"><strong class="font-semibold">Email:</strong> <?php echo $current_email; ?></p>
        </div>
    </div>

    <!-- Edit Email Form -->
    <div class="bg-white p-6 rounded-lg shadow-md mb-8">
        <h2 class="text-2xl font-semibold text-gray-800 mb-4">Update Email Address</h2>
        <form action="profile.php" method="POST" class="space-y-4">
            <input type="hidden" name="action" value="update_email">
            <div>
                <label for="email" class="block text-gray-700 text-sm font-bold mb-2">New Email:</label>
                <input type="email" id="email" name="email" value="<?php echo $current_email; ?>" required
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-green-500">
            </div>
            <div>
                <button type="submit"
                        class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-300">
                    Update Email
                </button>
            </div>
        </form>
    </div>

    <!-- Change Password Form -->
    <div class="bg-white p-6 rounded-lg shadow-md mb-8">
        <h2 class="text-2xl font-semibold text-gray-800 mb-4">Change Password</h2>
        <form action="profile.php" method="POST" class="space-y-4">
            <input type="hidden" name="action" value="change_password">
            <div>
                <label for="current_password" class="block text-gray-700 text-sm font-bold mb-2">Current Password:</label>
                <input type="password" id="current_password" name="current_password" required
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-green-500">
            </div>
            <div>
                <label for="new_password" class="block text-gray-700 text-sm font-bold mb-2">New Password:</label>
                <input type="password" id="new_password" name="new_password" required
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-green-500">
            </div>
            <div>
                <label for="confirm_new_password" class="block text-gray-700 text-sm font-bold mb-2">Confirm New Password:</label>
                <input type="password" id="confirm_new_password" name="confirm_new_password" required
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-green-500">
            </div>
            <div>
                <button type="submit"
                        class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-300">
                    Change Password
                </button>
            </div>
        </form>
    </div>
</div>

<?php
// Include the student specific footer
require_once '../includes/footer_student.php';
?>
