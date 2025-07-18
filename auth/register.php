<?php
// auth/register.php
// Handles new user registration.

require_once '../includes/session.php'; // For session management (e.g., isLoggedIn, getUserRole, display_message)
require_once '../includes/db.php';     // For database connection ($pdo)
require_once '../includes/functions.php'; // For utility functions (e.g., sanitize_input, redirect, display_message)
require_once '../includes/send_email.php'; // For sending emails


$message = ''; // Initialize message variable for feedback

// If an admin is logged in, they should use 'users.php' to create new accounts
/*
if (isLoggedIn() && getUserRole() === 'admin') {
    redirect('../admin/users.php');
}
*/

// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = sanitize_input($_POST['first_name'] ?? '');
    $last_name = sanitize_input($_POST['last_name'] ?? '');
    $username = sanitize_input($_POST['username'] ?? ''); // Now optional
    $email = sanitize_input($_POST['email'] ?? '');
    $password = sanitize_input($_POST['password'] ?? '');
    $confirm_password = sanitize_input($_POST['confirm_password'] ?? '');

    // Basic validation for required fields
    if (empty($first_name) || empty($last_name) || empty($email) || empty($password) || empty($confirm_password)) {
        $message = "First Name, Last Name, Email, Password, and Confirm Password are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Invalid email format.";
    } elseif ($password !== $confirm_password) {
        $message = "Passwords do not match.";
    } elseif (strlen($password) < 6) {
        $message = "Password must be at least 6 characters long.";
    } else {
        $username_to_use = $username; // Start with user-provided username

        // If username is empty, auto-generate one
        if (empty($username_to_use)) {
            // Create a base username from first and last name (e.g., "john.doe")
            $base_username = strtolower(str_replace(' ', '', $first_name)) . '.' . strtolower(str_replace(' ', '', $last_name));
            $generated_username = $base_username;
            $counter = 1;

            // Ensure the generated username is unique
            while (true) {
                $stmt_check_gen_username = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = :username");
                $stmt_check_gen_username->execute(['username' => $generated_username]);
                if ($stmt_check_gen_username->fetchColumn() == 0) {
                    $username_to_use = $generated_username;
                    break;
                }
                $generated_username = $base_username . $counter++;
            }
        } else {
            // If user provided a username, check if it's unique
            $stmt_check_user_username = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = :username");
            $stmt_check_user_username->execute(['username' => $username_to_use]);
            if ($stmt_check_user_username->fetchColumn() > 0) {
                $message = "The chosen username is already taken. Please choose another or leave it blank to auto-generate one.";
            }
        }

        // Only proceed if no validation messages have been set by the username checks
        if (empty($message)) {
            try {
                // Check if email already exists
                $stmt_check_email = $pdo->prepare("SELECT * FROM users WHERE email = :email");
                $stmt_check_email->execute(['email' => $email]);
                $existingUser = $stmt_check_email->fetch(PDO::FETCH_ASSOC);

                if ($existingUser) {
                    $message = "An account with this email address already exists.";
                } else {
                    // Hash the password before storing
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $role = 'student'; // Default role for public registration

                    // Generate a one-time auto-login token
                    $auto_login_token = bin2hex(random_bytes(32));
                    $auto_login_token_expiry = date('Y-m-d H:i:s', strtotime('+15 minutes')); // Token valid for 15 minutes

                    // Insert new user into the database
                    $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, username, password_hash, email, role, auto_login_token, auto_login_token_expiry) VALUES (:first_name, :last_name, :username, :password_hash, :email, :role, :auto_login_token, :auto_login_token_expiry)");
                    if ($stmt->execute([
                        'first_name' => $first_name,
                        'last_name' => $last_name,
                        'username' => $username_to_use, // Use the auto-generated or user-provided unique username
                        'password_hash' => $password_hash,
                        'email' => $email,
                        'role' => $role,
                        'auto_login_token' => $auto_login_token,
                        'auto_login_token_expiry' => $auto_login_token_expiry
                    ])) {
                        $new_user_id = $pdo->lastInsertId();

                        // Prepare the auto-login link for the email
                        $auto_login_link = BASE_URL . "/auth/auto_login.php?token=" . $auto_login_token;

                        // Include the email template and capture its output
                        ob_start(); // Start output buffering
                        require '../includes/email_templates/welcome_email.php'; // Include the template
                        $email_body = ob_get_clean(); // Get the buffered content and clean the buffer

                        // Replace placeholders in the email body
                        $email_body = str_replace('{{username}}', htmlspecialchars($username_to_use), $email_body);
                        $email_body = str_replace('{{auto_login_link}}', htmlspecialchars($auto_login_link), $email_body);

                        $subject = "Welcome to Mackenny Assessment!";

                        // Send the welcome email
                        if (sendEmail($email, $subject, $email_body)) {
                            // Registration successful, now automatically log in the user (optional, can just redirect to login with message)
                            // For this prompt, we'll auto-login the user directly after registration.
                            $_SESSION['user_id'] = $new_user_id; // Get the ID of the newly inserted user
                            $_SESSION['username'] = $username_to_use;
                            $_SESSION['role'] = $role;

                            // After successful registration and email sending, redirect to dashboard.
                            redirect('../student/dashboard.php');
                        } else {
                            // If email fails to send, still register but give a warning
                            // It's a design choice if you want to prevent registration on email failure
                            error_log("Failed to send welcome email to " . $email . " for new user " . $username_to_use);
                            $_SESSION['form_message'] = "Registration successful, but we could not send the welcome email. Please check your login details.";
                            $_SESSION['form_message_type'] = 'warning'; // Use 'warning' type for partial success
                            redirect('../student/dashboard.php'); // Still log in the user, or redirect to login.php
                        }
                    } else {
                        $message = "Registration failed. Please try again.";
                    }
                }
            } catch (PDOException $e) {
                error_log("Registration Error: " . $e->getMessage()); // Log the error for debugging
                $message = "An unexpected error occurred during registration. Please try again later.";
            }
        }
    }
    // If there's an error message (from validation or DB check), store it in a session variable to be displayed via JavaScript
    if (!empty($message)) {
        $_SESSION['form_message'] = $message;
        $_SESSION['form_message_type'] = 'error';
        // Redirect back to the registration page to display the message
        header('Location: register.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Mackenny Assessment</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
    /* Custom theme colors consistent with login.php */
    .bg-navy-900 { background-color: #0a1930; }
    .bg-navy-800 { background-color: #1a2b4a; }
    .hover\:bg-navy-700:hover { background-color: #2c3e6a; }
    .focus\:ring-navy-900:focus { --tw-ring-color: #0a1930; }
    .text-theme-color { color: #1e4b31; } /* Keeping this for the main heading, though navy is dominant */
    </style>
    <script src="https://accounts.google.com/gsi/client" async defer></script>
</head>
<body class="bg-gradient-to-br from-gray-100 to-blue-50 min-h-screen flex items-center justify-center">

<div class="container mx-auto px-4 py-8">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 max-w-6xl mx-auto bg-white rounded-xl shadow-2xl overflow-hidden">
        <div class="bg-navy-900 text-white p-12 flex flex-col justify-center">
            <h1 class="text-4xl font-bold mb-4">Join Mackenny Assessment</h1>
            <p class="text-lg mb-6">
                Register now to unlock your personalized dashboard. Track your progress,
                access learning materials, and prepare for assessments with ease.
            </p>
            <p class="text-sm italic">
                Start your journey towards academic excellence today!
            </p>
        </div>
        
        <div class="p-12 relative">
            <h2 class="text-3xl font-bold text-gray-800 mb-6 text-center">Registration</h2>
            
            <div id="form-notification" class="absolute top-0 left-0 w-full px-4 py-3 rounded-md" role="alert" style="transform: translateY(-100%);">
                <strong class="font-bold"></strong>
                <span class="block sm:inline" id="notification-message-content"></span>
                <span class="absolute top-0 bottom-0 right-0 px-4 py-3 cursor-pointer" onclick="hideNotification()">
                    <svg fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24" class="h-6 w-6">
                        <path d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </span>
            </div>

            <form action="register.php" method="POST" class="space-y-6">
                <div>
                    <label for="first_name" class="block text-gray-700 text-sm font-semibold mb-2">First Name</label>
                    <input 
                        type="text" 
                        id="first_name" 
                        name="first_name" 
                        required
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-navy-900 focus:border-transparent transition duration-200"
                        placeholder="Enter your first name"
                        value="<?= htmlspecialchars($first_name ?? '') ?>"
                    >
                </div>
                <div>
                    <label for="last_name" class="block text-gray-700 text-sm font-semibold mb-2">Last Name</label>
                    <input 
                        type="text" 
                        id="last_name" 
                        name="last_name" 
                        required
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-navy-900 focus:border-transparent transition duration-200"
                        placeholder="Enter your last name"
                        value="<?= htmlspecialchars($last_name ?? '') ?>"
                    >
                </div>
                <div>
                    <label for="username" class="block text-gray-700 text-sm font-semibold mb-2">Username (Optional)</label>
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-navy-900 focus:border-transparent transition duration-200"
                        placeholder="Choose a username (or leave blank to auto-generate)"
                        value="<?= htmlspecialchars($username ?? '') ?>"
                    >
                </div>
                <div>
                    <label for="email" class="block text-gray-700 text-sm font-semibold mb-2">Email</label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        required
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-navy-900 focus:border-transparent transition duration-200"
                        placeholder="Enter your email address"
                        value="<?= htmlspecialchars($email ?? '') ?>"
                    >
                </div>
                <div>
                    <label for="password" class="block text-gray-700 text-sm font-semibold mb-2">Password</label>
                    <div class="relative">
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            required
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-navy-900 focus:border-transparent transition duration-200 pr-10"
                            placeholder="Enter your password"
                        >
                        <button type="button" onclick="togglePasswordVisibility('password')"
                            class="absolute inset-y-0 right-0 pr-3 flex items-center text-sm leading-5 focus:outline-none">
                            <svg id="password_icon_eye" class="h-5 w-5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            <svg id="password_icon_slash" class="h-5 w-5 text-gray-500 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.879 13.879a3 3 0 11-4.242-4.242M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </button>
                    </div>
                </div>
                <div>
                    <label for="confirm_password" class="block text-gray-700 text-sm font-semibold mb-2">Confirm Password</label>
                    <div class="relative">
                        <input 
                            type="password" 
                            id="confirm_password" 
                            name="confirm_password" 
                            required
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-navy-900 focus:border-transparent transition duration-200 pr-10"
                            placeholder="Confirm your password"
                        >
                        <button type="button" onclick="togglePasswordVisibility('confirm_password')"
                            class="absolute inset-y-0 right-0 pr-3 flex items-center text-sm leading-5 focus:outline-none">
                            <svg id="confirm_password_icon_eye" class="h-5 w-5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            <svg id="confirm_password_icon_slash" class="h-5 w-5 text-gray-500 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.879 13.879a3 3 0 11-4.242-4.242M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </button>
                    </div>
                </div>
                <div class="flex items-center justify-between">
                    <button 
                        type="submit"
                        class="bg-navy-900 hover:bg-navy-700 text-white font-semibold py-3 px-6 rounded-lg shadow-md focus:outline-none focus:ring-2 focus:ring-navy-900 focus:ring-offset-2 transition duration-200"
                    >
                        Register
                    </button>
                    <a href="login.php" class="text-sm text-blue-600 hover:text-blue-800 font-medium hover:underline">
                        Already have an account? Login here.
                    </a>
                </div>
            </form>

            <div class="mt-6 text-center">
                <div class="relative flex py-5 items-center">
                    <div class="flex-grow border-t border-gray-300"></div>
                    <span class="flex-shrink mx-4 text-gray-500">OR</span>
                    <div class="flex-grow border-t border-gray-300"></div>
                </div>

                <div id="g_id_onload"
                    data-client_id="YOUR_GOOGLE_CLIENT_ID.apps.googleusercontent.com"
                    data-callback="handleGoogleSignIn"
                    data-auto_prompt="false">
                </div>
                <div class="g_id_signin"
                    data-type="standard"
                    data-size="large"
                    data-theme="outline"
                    data-text="sign_in_with"
                    data-shape="rectangular"
                    data-logo_alignment="left">
                </div>
            </div>

        </div>
    </div>
</div>

<script>
    // Function to toggle password visibility for any given password input field
    function togglePasswordVisibility(fieldId) {
        const passwordField = document.getElementById(fieldId);
        const eyeIcon = document.getElementById(fieldId + '_icon_eye');
        const slashIcon = document.getElementById(fieldId + '_icon_slash');
        
        if (passwordField.type === 'password') {
            passwordField.type = 'text';
            eyeIcon.classList.add('hidden');
            slashIcon.classList.remove('hidden');
        } else {
            passwordField.type = 'password';
            eyeIcon.classList.remove('hidden');
            slashIcon.classList.add('hidden');
        }
    }

    // Function to display a Tailwind CSS notification
    function displayNotification(message, type) {
        const notificationContainer = document.getElementById('form-notification');
        const messageContentElement = document.getElementById('notification-message-content');
        const strongTag = notificationContainer.querySelector('strong');

        // Reset classes to remove previous styling
        notificationContainer.classList.remove('bg-red-100', 'border-red-400', 'text-red-700', 'bg-green-100', 'border-green-400', 'text-green-700', 'bg-yellow-100', 'border-yellow-400', 'text-yellow-700');
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
            } else if (type === 'warning') { // Added warning type
                notificationContainer.classList.add('bg-yellow-100', 'border-yellow-400', 'text-yellow-700');
                strongTag.textContent = 'Warning!'; // Set title for warning
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
        const notificationElement = document.getElementById('form-notification');
        notificationElement.style.transition = 'transform 0.3s ease-in';
        notificationElement.style.transform = 'translateY(-100%)'; // Slide up out of view

        // Add a single-use event listener to add 'hidden' class after transition
        notificationElement.addEventListener('transitionend', function handler() {
            notificationElement.classList.add('hidden');
            // Remove the event listener to prevent it from firing multiple times
            notificationElement.removeEventListener('transitionend', handler);
        });
    }

    // Check for messages from PHP session and display them
    <?php if (isset($_SESSION['form_message'])): ?>
        displayNotification("<?= htmlspecialchars($_SESSION['form_message']) ?>", "<?= htmlspecialchars($_SESSION['form_message_type']) ?>");
        <?php
        unset($_SESSION['form_message']); // Clear the message after displaying
        unset($_SESSION['form_message_type']);
        ?>
    <?php endif; ?>

    // Google Sign-In Callback Function
    async function handleGoogleSignIn(response) {
        const idToken = response.credential;

        console.log('Google ID Token:', idToken);

        try {
            const backendResponse = await fetch('google_auth.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ id_token: idToken })
            });

            const data = await backendResponse.json();

            if (data.success) {
                window.location.href = data.redirect_url;
            } else {
                displayNotification('Google registration/login failed: ' + (data.message || 'Unknown error.'), 'error');
            }
        } catch (error) {
            console.error('Error sending Google token to backend:', error);
            displayNotification('An error occurred during Google sign-in. Please try again.', 'error');
        }
    }
</script>

</body>
</html>