<?php
// auth/auto_login.php
// Handles multi-use login links that expire.

// Enable error reporting for debugging - REMOVE IN PRODUCTION
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/functions.php'; // For sanitize_input(), redirect(), BASE_URL, format_datetime (if used for messages)

// Set timezone to Africa/Lagos (WAT, UTC+1) for consistency
date_default_timezone_set('Africa/Lagos');


if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = sanitize_input($_GET['token']);

    try {
        // Find the user with the given token AND its expiry.
        // We now select auto_login_token_expiry to check its validity.
        $stmt = $pdo->prepare("SELECT user_id, username, role, auto_login_token_expiry FROM users WHERE auto_login_token = :token");
        $stmt->execute(['token' => $token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $current_time = new DateTime();

            // Check if auto_login_token_expiry is not null and is a valid date string
            if ($user['auto_login_token_expiry'] !== null) {
                $expiry_time = new DateTime($user['auto_login_token_expiry']);

                if ($current_time < $expiry_time) {
                    // Token is valid and not expired - Log the user in

                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['loggedin'] = true; // Set a flag indicating user is logged in

                    // *** IMPORTANT CHANGE: DO NOT INVALIDATE THE TOKEN HERE! ***
                    // Removing the update statement that sets auto_login_token to NULL.
                    // The token will remain active until its expiry date.

                    // Optional: Update last login timestamp for the user
                    $update_last_login_stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE user_id = :user_id");
                    $update_last_login_stmt->execute(['user_id' => $user['user_id']]);

                    // Redirect to their dashboard based on role or a default page
                    if ($user['role'] === 'admin') {
                        redirect(BASE_URL . 'admin/dashboard.php');
                    } else {
                        // Default for 'student' or other roles
                        redirect(BASE_URL . 'student/dashboard.php');
                    }
                    exit; // Ensure script terminates after redirect

                } else {
                    // Token has expired - Invalidate the token in the database
                    $_SESSION['form_message'] = "Your auto-login link has expired. Please request a new one.";
                    $_SESSION['form_message_type'] = 'error';

                    $update_stmt = $pdo->prepare("UPDATE users SET auto_login_token = NULL, auto_login_token_expiry = NULL WHERE user_id = :user_id");
                    $update_stmt->execute(['user_id' => $user['user_id']]);
                    redirect(BASE_URL . 'auth/login.php');
                    exit;
                }
            } else {
                // Token exists but expiry is null (malformed/old record) - Invalidate it
                $_SESSION['form_message'] = "Invalid or malformed auto-login link. Please try logging in manually.";
                $_SESSION['form_message_type'] = 'error';
                $update_stmt = $pdo->prepare("UPDATE users SET auto_login_token = NULL, auto_login_token_expiry = NULL WHERE user_id = :user_id");
                $update_stmt->execute(['user_id' => $user['user_id']]);
                redirect(BASE_URL . 'auth/login.php');
                exit;
            }
        } else {
            // No user found with that token (either never existed or was cleared due to expiry)
            $_SESSION['form_message'] = "Invalid auto-login link. Please log in manually.";
            $_SESSION['form_message_type'] = 'error';
            redirect(BASE_URL . 'auth/login.php'); // Redirect to login page
            exit;
        }
    } catch (PDOException $e) {
        // Log the database error for debugging purposes
        error_log("Auto-login Database Error: " . $e->getMessage());

        $_SESSION['form_message'] = "An unexpected error occurred during auto-login. Please try again.";
        $_SESSION['form_message_type'] = 'error';
        redirect(BASE_URL . 'auth/login.php'); // Redirect to login page
        exit;
    }
} else {
    // No token was provided in the URL
    $_SESSION['form_message'] = "Invalid auto-login request: No token provided.";
    $_SESSION['form_message_type'] = 'error';
    redirect(BASE_URL . 'auth/login.php'); // Redirect to login page
    exit;
}
?>