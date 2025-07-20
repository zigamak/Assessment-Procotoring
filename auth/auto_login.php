<?php
// auth/auto_login.php
// Handles one-time login links for welcome emails.

// Enable error reporting for debugging - REMOVE IN PRODUCTION
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/functions.php'; // For redirect() and BASE_URL

// Set timezone to Africa/Lagos (WAT, UTC+1) for consistency, although not directly used in this simplified logic.
date_default_timezone_set('Africa/Lagos');


if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = sanitize_input($_GET['token']);

    try {
        // Find the user with the given token.
        // Removed auto_login_token_expiry from the SELECT and WHERE clause.
        $stmt = $pdo->prepare("SELECT user_id, username, role FROM users WHERE auto_login_token = :token");
        $stmt->execute(['token' => $token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // User found, proceed to log them in

            // Log the user in
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];

            // Invalidate the one-time token immediately after use
            // Removed auto_login_token_expiry = NULL from the UPDATE statement.
            $update_stmt = $pdo->prepare("UPDATE users SET auto_login_token = NULL WHERE user_id = :user_id");
            $update_stmt->execute(['user_id' => $user['user_id']]);

            // Redirect to their dashboard based on role or a default page
            // Assuming BASE_URL is defined in functions.php
            if ($user['role'] === 'admin') {
                redirect(BASE_URL . 'admin/dashboard.php');
            } else {
                // Default for 'student' or other roles
                redirect(BASE_URL . 'student/dashboard.php');
            }


        } else {
            // No user found with that token (either never existed or already used/invalidated)
            $_SESSION['form_message'] = "Invalid or used auto-login link. Please log in manually.";
            $_SESSION['form_message_type'] = 'error';
            redirect(BASE_URL . 'auth/login.php'); // Redirect to login page
        }
    } catch (PDOException $e) {
        // Log the database error for debugging purposes (check your server's error logs)
        error_log("Auto-login Database Error: " . $e->getMessage());

        $_SESSION['form_message'] = "An unexpected error occurred during auto-login. Please try again.";
        $_SESSION['form_message_type'] = 'error';
        redirect(BASE_URL . 'auth/login.php'); // Redirect to login page
    }
} else {
    // No token was provided in the URL
    $_SESSION['form_message'] = "Invalid auto-login request: No token provided.";
    $_SESSION['form_message_type'] = 'error';
    redirect(BASE_URL . 'auth/login.php'); // Redirect to login page
}
exit; // Ensure script terminates after redirect
?>