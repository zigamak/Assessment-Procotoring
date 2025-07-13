<?php
// index.php
// Redirects users to login page if not logged in, or to their respective dashboard based on role.

require_once 'includes/session.php';
require_once 'includes/functions.php';

// Check if the user is logged in using session.php function
if (isLoggedIn()) {
    // Redirect based on user role using getUserRole()
    if (getUserRole() === 'admin') {
        redirect('admin/dashboard.php');
    } elseif (getUserRole() === 'student') {
        redirect('student/dashboard.php');
    }
} else {
    // Redirect to login page if not logged in
    redirect('auth/login.php');
}
?>