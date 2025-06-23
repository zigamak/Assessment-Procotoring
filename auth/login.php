<?php
// auth/login.php
// Handles user login for all roles (Admin and Student).

require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

$message = ''; // Initialize message variable for feedback

// Check if the user is already logged in and redirect to their dashboard
if (isLoggedIn()) {
    if (getUserRole() === 'admin') {
        redirect('../admin/dashboard.php');
    } elseif (getUserRole() === 'student') {
        redirect('../student/dashboard.php');
    }
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize_input($_POST['username'] ?? '');
    $password = sanitize_input($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $message = display_message("Please enter both username and password.", "error");
    } else {
        try {
            // Prepare and execute the query to fetch user data
            $stmt = $pdo->prepare("SELECT user_id, username, password_hash, role, email FROM users WHERE username = :username");
            $stmt->execute(['username' => $username]);
            $user = $stmt->fetch();

            // Verify user and password
            if ($user && password_verify($password, $user['password_hash'])) {
                // Login successful
                loginUser($user['user_id'], $user['username'], $user['role']);

                // Redirect based on user role
                if ($user['role'] === 'admin') {
                    redirect('../admin/dashboard.php');
                } elseif ($user['role'] === 'student') {
                    redirect('../student/dashboard.php');
                }
            } else {
                $message = display_message("Invalid username or password. Please try again.", "error");
            }
        } catch (PDOException $e) {
            error_log("Login Error: " . $e->getMessage());
            $message = display_message("An unexpected error occurred. Please try again later.", "error");
        }
    }
}

// Include the public header for the login page
require_once '../includes/header_public.php';
?>

<div class="container mx-auto p-4 py-8">
    <div class="max-w-md mx-auto bg-white p-8 rounded-lg shadow-lg">
        <h1 class="text-3xl font-bold text-center mb-6" style="color: #1e4b31;">User Login</h1>

        <?php echo $message; // Display any feedback messages ?>

        <form action="login.php" method="POST" class="space-y-4">
            <div>
                <label for="username" class="block text-gray-700 text-sm font-bold mb-2">Username:</label>
                <input type="text" id="username" name="username" required
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-green-500">
            </div>
            <div>
                <label for="password" class="block text-gray-700 text-sm font-bold mb-2">Password:</label>
                <input type="password" id="password" name="password" required
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 mb-3 leading-tight focus:outline-none focus:shadow-outline focus:border-green-500">
            </div>
            <div class="flex items-center justify-between">
                <button type="submit"
                        class="bg-green-700 hover:bg-green-800 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-300">
                    Login
                </button>
                <a href="forgot_password.php" class="inline-block align-baseline font-bold text-sm text-purple-500 hover:text-purple-800">
                    Forgot Password?
                </a>
            </div>
            <div class="text-center mt-4">
                <a href="register.php" class="inline-block align-baseline font-bold text-sm text-blue-500 hover:text-blue-800">
                    Don't have an account? Register here.
                </a>
            </div>
        </form>
    </div>
</div>

<?php
// Include the public footer
require_once '../includes/footer_public.php';
?>