<?php
// includes/functions.php
// Common utility functions for the assessment system.

// Ensure session is started if it hasn't been already
// This is crucial for accessing $_SESSION variables like 'user_id', 'username', 'role'.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include the database connection.
// This is necessary because getUserEmail() will need to access the $pdo object.
// Make sure db.php defines $pdo as a global variable or returns it.
require_once __DIR__ . '/db.php'; // Using __DIR__ for robust path inclusion

// NOTE: getUserId() is assumed to be defined in includes/session.php
// and should NOT be redefined here to avoid "Cannot redeclare" errors.
// Therefore, we've removed its definition from this file.


/**
 * Sanitize input data to prevent XSS attacks and ensure data integrity.
 * @param mixed $data The data to sanitize.
 * @return mixed The sanitized data.
 */
function sanitize_input($data) {
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            $data[$key] = sanitize_input($value);
        }
    } else {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    }
    return $data;
}

/**
 * Redirects the user to a specified URL.
 * @param string $location The URL to redirect to.
 */
function redirect($location) {
    header("Location: " . $location);
    exit();
}

/**
 * Displays a system message (e.g., success, error, info).
 * This function is a placeholder; you'd typically store messages in sessions
 * and retrieve them on the next page load, then display them.
 * @param string $message The message content.
 * @param string $type The type of message (e.g., 'success', 'error', 'info').
 */
function display_message($message, $type = 'info') {
    // In a real application, you would store these in $_SESSION
    // and retrieve them on the next page load, then display them
    // using appropriate HTML/CSS (e.g., a modal or dismissible alert).
    echo "<div class='p-3 my-3 rounded-md text-white ";
    switch ($type) {
        case 'success':
            echo "bg-green-500";
            break;
        case 'error':
            echo "bg-red-500";
            break;
        case 'warning':
            echo "bg-yellow-500";
            break;
        case 'info':
        default:
            echo "bg-blue-500";
            break;
    }
    echo "'>" . sanitize_input($message) . "</div>";
}

/**
 * Checks if the currently logged-in user has an 'admin' role.
 * Assumes user role is stored in $_SESSION['role'].
 * @return bool True if the user is an admin, false otherwise.
 */
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Checks if the currently logged-in user has a 'student' role.
 * Assumes user role is stored in $_SESSION['role'].
 * @return bool True if the user is a student, false otherwise.
 */
function isStudent() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'student';
}

/**
 * Enforces a specific user role for access to a page.
 * If the user is not logged in or does not have the required role,
 * they are redirected to the specified URL.
 *
 * @param string $required_role The role required to access the page (e.g., 'admin', 'student').
 * @param string $redirect_url The URL to redirect to if the role check fails.
 */
function enforceRole($required_role, $redirect_url) {
    // Check if user is logged in at all
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        redirect($redirect_url);
    }

    // Check if the logged-in user has the required role
    if ($_SESSION['role'] !== $required_role) {
        redirect($redirect_url);
    }
}


/**
 * Retrieves the email of the currently logged-in user from the database.
 * Assumes a global $pdo (PDO object) is available and getUserId() is defined
 * (e.g., from includes/session.php).
 *
 * @return string|null The user's email address, or null if not found or not logged in.
 */
function getUserEmail() {
    // Access the global PDO connection.
    global $pdo;

    $user_id = getUserId(); // Call the getUserId function from includes/session.php

    if ($user_id && $pdo) { // Ensure user_id is available and PDO connection exists
        try {
            $stmt = $pdo->prepare("SELECT email FROM users WHERE user_id = :user_id LIMIT 1");
            $stmt->execute(['user_id' => $user_id]);
            $user_email = $stmt->fetchColumn();
            return $user_email ?: null; // Return email or null if not found
        } catch (PDOException $e) {
            error_log("Error fetching user email in getUserEmail(): " . $e->getMessage());
            return null;
        }
    }
    return null; // No user_id in session or PDO not available
}

// Add more general utility functions as needed.
// For example, functions to format dates, validate emails, etc.

?>