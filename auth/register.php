<?php
// auth/register.php
// Handles new student user registration.
// Admins will manage their own registration and other user roles.

require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

$message = ''; // Initialize message variable for feedback

// If an admin is logged in, they should use 'manage_users.php' to create new accounts
// if (isLoggedIn() && getUserRole() === 'admin') {
//     redirect('../admin/manage_users.php');
// }

// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize_input($_POST['username'] ?? '');
    $email = sanitize_input($_POST['email'] ?? '');
    $password = sanitize_input($_POST['password'] ?? '');
    $confirm_password = sanitize_input($_POST['confirm_password'] ?? '');

    // Basic validation
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $message = display_message("All fields are required.", "error");
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = display_message("Invalid email format.", "error");
    } elseif ($password !== $confirm_password) {
        $message = display_message("Passwords do not match.", "error");
    } elseif (strlen($password) < 6) {
        $message = display_message("Password must be at least 6 characters long.", "error");
    } else {
        try {
            // Check if username or email already exists
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = :username OR email = :email");
            $stmt->execute(['username' => $username, 'email' => $email]);
            if ($stmt->fetchColumn() > 0) {
                $message = display_message("Username or email already exists.", "error");
            } else {
                // Hash the password before storing
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $role = 'student'; // Default role for public registration

                // Insert new user into the database
                $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, email, role) VALUES (:username, :password_hash, :email, :role)");
                if ($stmt->execute([
                    'username' => $username,
                    'password_hash' => $password_hash,
                    'email' => $email,
                    'role' => $role
                ])) {
                    $message = display_message("Registration successful! You can now log in.", "success");
                    redirect('login.php?registration_success=1'); // Redirect to login page
                } else {
                    $message = display_message("Registration failed. Please try again.", "error");
                }
            }
        } catch (PDOException $e) {
            error_log("Registration Error: " . $e->getMessage());
            $message = display_message("An unexpected error occurred during registration. Please try again later.", "error");
        }
    }
}

// Include the public header for the registration page
require_once '../includes/header_public.php';
?>

<div class="container mx-auto p-4 py-8">
    <div class="max-w-md mx-auto bg-white p-8 rounded-lg shadow-lg">
        <h1 class="text-3xl font-bold text-center mb-6" style="color: #1e4b31;">Student Registration</h1>

        <?php
        // Display registration success message if redirected from successful registration
        if (isset($_GET['registration_success']) && $_GET['registration_success'] == 1) {
            display_message("Registration successful! You can now log in.", "success");
        }
        echo $message; // Display other feedback messages
        ?>

        <form action="register.php" method="POST" class="space-y-4">
            <div>
                <label for="username" class="block text-gray-700 text-sm font-bold mb-2">Username:</label>
                <input type="text" id="username" name="username" required
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-green-500">
            </div>
            <div>
                <label for="email" class="block text-gray-700 text-sm font-bold mb-2">Email:</label>
                <input type="email" id="email" name="email" required
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-green-500">
            </div>
            <div>
                <label for="password" class="block text-gray-700 text-sm font-bold mb-2">Password:</label>
                <input type="password" id="password" name="password" required
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 mb-3 leading-tight focus:outline-none focus:shadow-outline focus:border-green-500">
            </div>
            <div>
                <label for="confirm_password" class="block text-gray-700 text-sm font-bold mb-2">Confirm Password:</label>
                <input type="password" id="confirm_password" name="confirm_password" required
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 mb-3 leading-tight focus:outline-none focus:shadow-outline focus:border-green-500">
            </div>
            <div class="flex items-center justify-between">
                <button type="submit"
                        class="bg-green-700 hover:bg-green-800 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-300">
                    Register
                </button>
                <a href="login.php" class="inline-block align-baseline font-bold text-sm text-blue-500 hover:text-blue-800">
                    Already have an account? Login here.
                </a>
            </div>
        </form>
    </div>
</div>

<?php
// Include the public footer
require_once '../includes/footer_public.php';
?>
