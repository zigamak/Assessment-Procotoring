<?php
/**
 * index.php
 *
 * This file serves as the main entry point for the application.
 * It immediately redirects the user to the dashboard.php page.
 *
 * This is useful for setting a default landing page for your application
 * without having to explicitly type "dashboard.php" in the URL.
 */

// Set the HTTP status code for a permanent redirect (301 Moved Permanently)
// This tells browsers and search engines that the page has permanently moved.
// For a temporary redirect (e.g., during maintenance), you might use 302 Found.
header("HTTP/1.1 301 Moved Permanently");

// Specify the new location to redirect to.
// Make sure this path is correct relative to your web server's document root,
// or use a full URL if dashboard.php is on a different domain/subdomain.
header("Location: dashboard.php");

// It's good practice to include exit() after a header redirect
// to ensure that no further code is executed and output is sent.
exit();
?>
