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
        $message = display_message("Please enter your username/email and password.", "error");
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
                $message = display_message("Invalid username/email or password. Please try again.", "error");
            }
        } catch (PDOException $e) {
            error_log("Login Error: " . $e->getMessage());
            $message = display_message("An unexpected error occurred. Please try again later.", "error");
        }
    }
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
            <!-- Left Section: Welcome and Description -->
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
            
            <!-- Right Section: Login Form -->
            <div class="p-12">
                <h2 class="text-3xl font-bold text-gray-800 mb-6 text-center">User Login</h2>
                
                <?php echo $message; // Display any feedback messages ?>

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
                            Don't have an account? 
                            <a href="register.php" class="text-blue-600 hover:text-blue-800 font-medium hover:underline">
                                Register Now
                            </a>
                        </p>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>