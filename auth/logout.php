<?php
// auth/logout.php
// Handles user logout.

// Enable error reporting for debugging purposes
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Require necessary files
require_once '../includes/db.php';      // Ensure BASE_URL and $pdo are available
require_once '../includes/session.php';  // Provides session management functions like logout()
require_once '../includes/functions.php';// Provides utility functions like redirect()

// Log the attempt to logout
error_log("Attempting to log out user. Session status: " . session_status() . ", Session ID: " . session_id());

// Call the logout function to destroy the session
logout();

// Log successful logout
error_log("User successfully logged out. Attempting redirect.");

// Redirect to the login page with a success message
if (!defined('BASE_URL')) {
    error_log("FATAL ERROR: BASE_URL is not defined in auth/logout.php. Cannot redirect.");
    // Fallback redirect
    header("Location: /auth/login.php?message=logged_out_error");
    exit();
}

// Redirect using the redirect function from functions.php
redirect(BASE_URL . 'auth/login.php?message=logged_out');

// Ensure no further script execution
exit();
?>