<?php
// auth/logout.php
// Handles user logout.

require_once '../includes/session.php';
require_once '../includes/functions.php';

// Call the logoutUser function to destroy the session
logoutUser();

// Redirect to the login page or the home page after logout
redirect('login.php?message=logged_out');
exit();
?>
