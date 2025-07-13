<?php
// auth/logout.php
// Handles user logout.

require_once '../includes/session.php';
require_once '../includes/functions.php';

// Call the logout function to destroy the session
logout(); // <--- Changed from logoutUser() to logout()

// Redirect to the login page or the home page after logout
redirect('login.php?message=logged_out');
exit();
?>