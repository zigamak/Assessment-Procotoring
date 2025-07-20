<?php
// auth/login.php
// Handles user login for all roles (Admin and Student).

require_once '../includes/session.php';
require_once '../includes/db.php'; // Assuming $pdo is initialized in db.php
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
    $identifier = sanitize_input($_POST['identifier'] ?? ''); // Can be username or email
    $password = sanitize_input($_POST['password'] ?? '');

    if (empty($identifier) || empty($password)) {
        // Store message in session instead of directly displaying
        $_SESSION['form_message'] = "Please enter your username/email and password.";
        $_SESSION['form_message_type'] = 'error';
    } else {
        try {
            // Determine if the identifier is an email or username
            if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
                // Prepare and execute the query to fetch user data by email
                $stmt = $pdo->prepare("SELECT user_id, username, password_hash, role, email FROM users WHERE email = :identifier");
            } else {
                // Prepare and execute the query to fetch user data by username
                $stmt = $pdo->prepare("SELECT user_id, username, password_hash, role, email FROM users WHERE username = :identifier");
            }
            
            $stmt->execute(['identifier' => $identifier]);
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
                // Store message in session instead of directly displaying
                $_SESSION['form_message'] = "Invalid username/email or password. Please try again.";
                $_SESSION['form_message_type'] = 'error';
            }
        } catch (PDOException $e) {
            error_log("Login Error: " . $e->getMessage());
            // Store message in session for unexpected errors
            $_SESSION['form_message'] = "An unexpected error occurred. Please try again later.";
            $_SESSION['form_message_type'] = 'error';
        }
    }
    // If there was an error message, ensure we redirect back to the login page to display it.
    // This is crucial for session messages to be picked up by the client-side JavaScript.
    // No explicit redirect here, as the PHP continues to render the page,
    // and the JS will handle displaying the message from the session.
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Mackenny Assessment</title>
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
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 max-w-6xl mx-auto bg-white rounded-xl shadow-2xl overflow-hidden">
            <div class="bg-navy-900 text-white p-12 flex flex-col justify-center">
                <h1 class="text-4xl font-bold mb-4">Welcome to Mackenny Assessment</h1>
                <p class="text-lg mb-6">
                    Mackenny Assessment is your platform for seamless learning and evaluation. 
                    Log in to access personalized dashboards, track your progress, and engage 
                    with our comprehensive assessment tools designed for students and administrators alike.
                </p>
                <p class="text-sm italic">
                    Empowering education through innovative solutions.
                </p>
            </div>
            
            <div class="p-12 relative"> <h2 class="text-3xl font-bold text-gray-800 mb-6 text-center">User Login</h2>
                
                <div id="error-notification" class="hidden absolute top-0 left-0 w-full bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-md mb-4" role="alert" style="transform: translateY(-100%);">
                    <strong class="font-bold">Error!</strong>
                    <span class="block sm:inline" id="error-message-content"></span>
                    <span class="absolute top-0 bottom-0 right-0 px-4 py-3">
                        <svg fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24" class="h-6 w-6 cursor-pointer" onclick="document.getElementById('error-notification').classList.add('hidden')">
                            <path d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </span>
                </div>

                <?php 
                if (isset($_GET['registration_success']) && $_GET['registration_success'] == 1) {
                    echo display_message("Registration successful! Please log in with your new credentials.", "success");
                }
                ?>

                <form action="login.php" method="POST" class="space-y-6">
                    <div>
                        <label for="identifier" class="block text-gray-700 text-sm font-semibold mb-2">Username or Email</label>
                        <input 
                            type="text" 
                            id="identifier" 
                            name="identifier" 
                            required
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-navy-900 focus:border-transparent transition duration-200"
                            placeholder="Enter your username or email"
                            value="<?= htmlspecialchars($identifier ?? '') ?>"
                        >
                    </div>
                    <div>
                        <label for="password" class="block text-gray-700 text-sm font-semibold mb-2">Password</label>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            required
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-navy-900 focus:border-transparent transition duration-200"
                            placeholder="Enter your password"
                        >
                    </div>
                    <div class="flex items-center justify-between">
                        <button 
                            type="submit"
                            class="bg-navy-900 hover:bg-navy-700 text-white font-semibold py-3 px-6 rounded-lg shadow-md focus:outline-none focus:ring-2 focus:ring-navy-900 focus:ring-offset-2 transition duration-200"
                        >
                            Login
                        </button>
                        <a href="forgot_password.php" class="text-sm text-blue-600 hover:text-blue-800 font-medium hover:underline">
                            Forgot Password?
                        </a>
                    </div>
                    <div class="text-center">
                        <p class="text-sm text-gray-600">
                            To access the assessment dashboard, please ensure you've paid and check your email for instructions.
                        </p>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Function to display a Tailwind CSS notification
        function displayNotification(message, type) {
            const notificationContainer = document.getElementById('error-notification');
            const messageContentElement = document.getElementById('error-message-content');

            if (type === 'error' && message) {
                messageContentElement.textContent = message;
                notificationContainer.classList.remove('hidden');
                // Optional: Make it slide down nicely
                notificationContainer.style.transition = 'transform 0.3s ease-out';
                notificationContainer.style.transform = 'translateY(0)';
            } else {
                // Optional: Make it slide up before hiding
                notificationContainer.style.transition = 'transform 0.3s ease-in';
                notificationContainer.style.transform = 'translateY(-100%)';
                notificationContainer.addEventListener('transitionend', function handler() {
                    notificationContainer.classList.add('hidden');
                    notificationContainer.removeEventListener('transitionend', handler);
                });
            }
        }

        // Check for messages from PHP session and display them on page load
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (isset($_SESSION['form_message'])): ?>
                displayNotification("<?= $_SESSION['form_message'] ?>", "<?= $_SESSION['form_message_type'] ?>");
                <?php
                unset($_SESSION['form_message']); // Clear the message after displaying
                unset($_SESSION['form_message_type']);
                ?>
            <?php endif; ?>
        });
    </script>
</body>
</html>