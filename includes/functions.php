<?php
// includes/functions.php
// Common utility functions for the assessment system.

// Ensure session is started if it hasn't been already
// This is crucial for accessing $_SESSION variables like 'user_id', 'username', 'role'.
// It's good practice to have session_start() in a central place like session.php,
// but checking here ensures it's always started if this file is included first.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include the database connection.
// This is necessary because getUserEmail() will need to access the $pdo object.
// Make sure db.php defines $pdo as a global variable or returns it.
require_once __DIR__ . '/db.php'; // Using __DIR__ for robust path inclusion

// Define BASE_URL if it's not already defined in a config file
// This is a placeholder; you should define it properly in a config file like db.php or a dedicated config.php
if (!defined('BASE_URL')) {
    define('BASE_URL', 'http://localhost/assessment/'); // Adjust as per your actual base URL
}

// IMPORTANT: isLoggedIn(), getUserRole(), getUserId(), and possibly logout()
// are assumed to be defined SOLELY in includes/session.php.
// Their definitions have been REMOVED from this file to prevent redeclaration errors.
// Ensure session.php is included wherever these functions are used.


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
 * This function expects a complete URL or a path relative to the current script.
 * For application-wide redirects using BASE_URL, ensure BASE_URL is prepended
 * before calling this function (e.g., redirect(BASE_URL . 'some/path.php')).
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
    $class = '';
    switch ($type) {
        case 'success':
            $class = 'bg-green-100 border-l-4 border-green-500 text-green-700';
            break;
        case 'error':
            $class = 'bg-red-100 border-l-4 border-red-500 text-red-700';
            break;
        case 'warning':
            $class = 'bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700';
            break;
        case 'info':
        default:
            $class = 'bg-blue-100 border-l-4 border-blue-500 text-blue-700';
            break;
    }
    return "<div class=\"{$class} p-4 mb-4 rounded-lg shadow-md\" role=\"alert\"><p class=\"font-bold\">" . ucfirst($type) . "!</p><p>" . sanitize_input($message) . "</p></div>";
}


/**
 * Checks if the currently logged-in user has an 'admin' role.
 * Assumes user role is stored in $_SESSION['role'] and isLoggedIn() is available.
 * @return bool True if the user is an admin, false otherwise.
 */
function isAdmin() {
    // Requires isLoggedIn() and getUserRole() from session.php
    return isLoggedIn() && getUserRole() === 'admin';
}

/**
 * Checks if the currently logged-in user has a 'student' role.
 * Assumes user role is stored in $_SESSION['role'] and isLoggedIn() is available.
 * @return bool True if the user is a student, false otherwise.
 */
function isStudent() {
    // Requires isLoggedIn() and getUserRole() from session.php
    return isLoggedIn() && getUserRole() === 'student';
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
    // Requires isLoggedIn() and getUserRole() from session.php
    if (!isLoggedIn() || getUserRole() !== $required_role) {
        redirect($redirect_url);
    }
}


/**
 * Retrieves the email of the currently logged-in user from the database.
 * Assumes a global $pdo (PDO object) is available and getUserId() is defined in session.php.
 *
 * @return string|null The user's email address, or null if not found or not logged in.
 */
function getUserEmail() {
    global $pdo; // Access the global PDO object
    $user_id = getUserId(); // Get the user ID from the session (defined in session.php)

    if ($user_id) {
        try {
            $stmt = $pdo->prepare("SELECT email FROM users WHERE user_id = :user_id");
            $stmt->execute(['user_id' => $user_id]);
            $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user_data && isset($user_data['email'])) {
                return $user_data['email'];
            }
        } catch (PDOException $e) {
            error_log("Error fetching user email in getUserEmail(): " . $e->getMessage());
            return null;
        }
    }
    return null; // Return null if user_id is not found or no email is retrieved
}


/**
 * Fetches all details for a given user ID from the database.
 *
 * @param PDO $pdo The PDO database connection object.
 * @param int $user_id The ID of the user to fetch details for.
 * @return array|null An associative array of user details, or null if not found.
 */
function fetchUserDetails($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("SELECT user_id, username, email, password_hash, role, passport_image_path, city, state, country, first_name, last_name FROM users WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching user details: " . $e->getMessage());
        return null;
    }
}


/**
 * Formats a given datetime string into a human-readable format.
 * @param string $datetime_str The datetime string (e.g., 'YYYY-MM-DD HH:MM:SS').
 * @param string $format The desired output format (default 'M d, Y h:i A').
 * @return string The formatted datetime string, or 'N/A' if invalid.
 */
function format_datetime($datetime_str, $format = 'M d, Y h:i A') {
    if (empty($datetime_str) || $datetime_str === '0000-00-00 00:00:00') {
        return 'N/A';
    }
    try {
        $dt = new DateTime($datetime_str);
        return $dt->format($format);
    } catch (Exception $e) {
        error_log("Error formatting datetime: " . $e->getMessage() . " for input: " . $datetime_str);
        return 'Invalid Date'; // Or handle as per your preference
    }
}

// Add more general utility functions as needed.
// For example, functions to validate emails, etc.

?>